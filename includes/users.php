<?php

// ─────────────────────────────────────────────────────────────────────────────
// Statistiques des utilisateurs Plex
//
// Ce fichier expose deux niveaux de statistiques :
//
//   1. wizarrinvite_user_stats($cfg)
//      Statistiques globales brutes (sans déduplication inter-serveurs).
//      Utilisé pour le compteur général et les modes manuel / auto legacy.
//      Retourne le nombre total d'enregistrements dans Wizarr (peut inclure
//      des doublons si le même utilisateur Plex est sur plusieurs serveurs).
//      Repli sur GET /api/status si GET /api/users échoue.
//
//   2. Fonctions de déduplication (utilisées par slots.php)
//      wizarrinvite_user_key()
//      wizarrinvite_parse_server_groups()
//      wizarrinvite_group_users_by_server()
//      wizarrinvite_compute_account_stats()
//      wizarrinvite_check_server_availability()
//
//      Ces fonctions permettent aux slots automatiques de vérifier la capacité
//      de chaque serveur en dédupliquant les utilisateurs selon les groupes
//      configurés dans le slot (même compte Plex = même utilisateurs).
//
//      Algorithme de déduplication pour les groupes :
//      On prend le MAXIMUM des comptes par serveur au sein du groupe.
//      Hypothèse : les serveurs d'un même groupe partagent les mêmes utilisateurs
//      (même compte Plex admin). Cela évite le double-comptage même lorsque
//      l'API ne retourne pas d'identifiant Plex stable (email/username absents).
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Extrait une clé d'identification stable pour un utilisateur Plex.
 *
 * L'ordre des champs suit une priorité décroissante de fiabilité :
 *  1. email / plex_email       → identifiant Plex le plus stable
 *  2. username / plex_username → stable mais peut changer
 *  3. plex_id                  → ID numérique Plex (pas toujours exposé)
 *  4. token                    → présent dans certaines versions de Wizarr
 *  5. wid_{id}                 → dernier recours : ID interne Wizarr (unique par serveur,
 *                                 mais pas cross-serveur → déduplication imparfaite)
 *
 * Cet ordre est important car l'API Wizarr n'expose pas toujours les mêmes champs
 * selon la version installée.
 *
 * @param  array $user
 * @return string  Clé normalisée en minuscules
 */
function wizarrinvite_user_key($user)
{
    foreach (['email', 'plex_email', 'username', 'plex_username', 'plex_id', 'token'] as $field) {
        $val = trim((string)($user[$field] ?? ''));
        if ($val !== '') {
            return strtolower($val);
        }
    }

    // Dernier recours : ID interne Wizarr (unique par serveur, pas idéal)
    return 'wid_' . ($user['id'] ?? uniqid('', true));
}

/**
 * Analyse la configuration des groupes de serveurs partagés.
 * Format : "1,2|3,4" → [[1, 2], [3, 4]]
 * Les serveurs non déclarés dans un groupe sont chacun leur propre compte.
 *
 * @param  string $rawGroups
 * @return int[][]
 */
function wizarrinvite_parse_server_groups($rawGroups)
{
    $groups = [];
    foreach (explode('|', (string)$rawGroups) as $segment) {
        $ids = array_values(array_filter(array_map('intval', explode(',', $segment))));
        if (!empty($ids)) {
            $groups[] = $ids;
        }
    }
    return $groups;
}

/**
 * Groupe les utilisateurs de l'API par server_id.
 * Retourne [server_id => [user_key => true, ...]]
 * La déduplication intra-serveur est assurée par wizarrinvite_user_key().
 *
 * @param  array $allUsers
 * @return array<int, array<string, true>>
 */
function wizarrinvite_group_users_by_server($allUsers)
{
    $byServer = [];

    foreach ($allUsers as $user) {
        if (!is_array($user)) {
            continue;
        }
        $sid = (int)($user['server_id'] ?? 0);
        if ($sid === 0) {
            continue;
        }
        $key = wizarrinvite_user_key($user);
        if (!isset($byServer[$sid])) {
            $byServer[$sid] = [];
        }
        $byServer[$sid][$key] = true;
    }

    return $byServer;
}

/**
 * Calcule les statistiques par compte Plex (groupes de serveurs).
 *
 * Pour chaque groupe configuré : prend le MAXIMUM des comptes par serveur.
 * Hypothèse : les serveurs d'un même groupe ont les mêmes utilisateurs (même
 * compte Plex admin). Cette approche corrige le double-comptage même quand
 * l'API ne retourne pas d'identifiant Plex stable cross-serveur.
 *
 * Les serveurs sans groupe sont traités comme des comptes indépendants.
 *
 * @param  array<int, array<string, true>> $byServer
 * @param  int[][]                         $serverGroups
 * @param  int                             $maxUsers
 * @return array  [group_key => ['server_ids'=>[...], 'unique_count'=>N, 'max'=>N, 'limit_reached'=>bool]]
 */
function wizarrinvite_compute_account_stats($byServer, $serverGroups, $maxUsers)
{
    $accounts        = [];
    $assignedServers = [];

    foreach ($serverGroups as $group) {
        // Pour un groupe : maximum des comptes individuels de chaque serveur
        // (même compte Plex = mêmes utilisateurs sur chaque serveur du groupe)
        $maxCount = 0;
        foreach ($group as $sid) {
            $cnt = count($byServer[(int)$sid] ?? []);
            if ($cnt > $maxCount) {
                $maxCount = $cnt;
            }
            $assignedServers[] = (int)$sid;
        }

        $accounts['group_' . implode('_', $group)] = [
            'server_ids'    => array_map('intval', $group),
            'unique_count'  => $maxCount,
            'max'           => $maxUsers,
            'limit_reached' => $maxCount >= $maxUsers,
        ];
    }

    // Serveurs sans groupe : chacun est son propre compte
    foreach (array_keys($byServer) as $sid) {
        if (!in_array((int)$sid, $assignedServers, true)) {
            $count = count($byServer[$sid]);
            $accounts['server_' . $sid] = [
                'server_ids'    => [(int)$sid],
                'unique_count'  => $count,
                'max'           => $maxUsers,
                'limit_reached' => $count >= $maxUsers,
            ];
        }
    }

    return $accounts;
}

/**
 * Vérifie la disponibilité des serveurs sélectionnés pour un slot.
 *
 * Pour chaque serveur, cherche son compte Plex (groupe ou indépendant) et
 * vérifie si ce compte a atteint sa limite.
 * Utilisé uniquement lors de la création d'une invitation de slot.
 *
 * @param  string  $baseUrl
 * @param  string  $apiKey
 * @param  int[]   $selectedServerIds
 * @param  int[][] $serverGroups
 * @param  int     $maxUsers
 * @return array{available: int[], full: int[], per_account: array, error: bool}
 */
function wizarrinvite_check_server_availability($baseUrl, $apiKey, $selectedServerIds, $serverGroups, $maxUsers)
{
    if (empty($selectedServerIds)) {
        return ['available' => [], 'full' => [], 'per_account' => [], 'error' => false];
    }

    $result = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'users', null, 8);

    if ((int)$result['http'] !== 200) {
        // En cas d'échec API : tous disponibles (comportement conservateur)
        return [
            'available'   => $selectedServerIds,
            'full'        => [],
            'per_account' => [],
            'error'       => true,
        ];
    }

    $allUsers   = is_array($result['body']['users'] ?? null) ? $result['body']['users'] : [];
    $byServer   = wizarrinvite_group_users_by_server($allUsers);
    $perAccount = wizarrinvite_compute_account_stats($byServer, $serverGroups, $maxUsers);

    $available = [];
    $full      = [];

    foreach ($selectedServerIds as $sid) {
        $accountFull = false;
        foreach ($perAccount as $account) {
            if (in_array((int)$sid, $account['server_ids'], true)) {
                if ($account['limit_reached']) {
                    $accountFull = true;
                }
                break;
            }
        }
        if ($accountFull) {
            $full[] = (int)$sid;
        } else {
            $available[] = (int)$sid;
        }
    }

    return [
        'available'   => $available,
        'full'        => $full,
        'per_account' => $perAccount,
        'error'       => false,
    ];
}

/**
 * Déduplique une liste d'utilisateurs en groupes de personnes uniques.
 *
 * Règles de déduplication (par ordre de priorité) :
 *  1. Même email non vide (email != "empty") → même personne, toujours
 *  2. Même username + au moins un email vide  → probablement même personne, auto-merge
 *  3. Même username + emails différents non vides → personnes différentes + flag ambiguous
 *
 * @param  array $users  Liste brute de GET /api/users (déjà filtrée si nécessaire)
 * @return array{count: int, groups: array, ambiguous: array}
 */
function wizarrinvite_deduplicate_users(array $users)
{
    $groups     = [];
    $emailIndex = [];  // email_lower → group_idx
    $nameIndex  = [];  // name_lower  → [group_idx, ...]
    $ambiguous  = [];

    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }

        $uid      = (int)($user['id'] ?? 0);
        $uname    = trim($user['username'] ?? '');
        $rawEmail = trim($user['email'] ?? '');
        $server   = $user['server']      ?? '';
        $stype    = $user['server_type'] ?? '';
        $expires  = $user['expires']     ?? null;
        $uLow     = ($uname !== '') ? mb_strtolower($uname) : '';
        $hasEmail = ($rawEmail !== '' && strcasecmp($rawEmail, 'empty') !== 0);
        $eLow     = $hasEmail ? mb_strtolower($rawEmail) : '';

        $matchIdx = null;

        // Phase 1 : match par email
        if ($eLow !== '' && isset($emailIndex[$eLow])) {
            $matchIdx = $emailIndex[$eLow];
        }

        // Phase 2 : match par username (pas de conflit d'email)
        if ($matchIdx === null && $uLow !== '' && isset($nameIndex[$uLow])) {
            foreach ($nameIndex[$uLow] as $candIdx) {
                $emailConflict = false;
                if ($eLow !== '') {
                    foreach ($groups[$candIdx]['emails'] as $existingE) {
                        if ($existingE !== '' && $existingE !== $eLow) {
                            $emailConflict = true;
                            break;
                        }
                    }
                }
                if (!$emailConflict) {
                    $matchIdx = $candIdx;
                    break;
                } else {
                    // Même nom, emails différents → signaler sans fusionner
                    $ambiguous[] = [
                        'username'         => $uname,
                        'new_id'           => $uid,
                        'new_server'       => $server,
                        'new_email'        => $rawEmail,
                        'existing_ids'     => $groups[$candIdx]['ids'],
                        'existing_servers' => $groups[$candIdx]['servers'],
                        'existing_email'   => $groups[$candIdx]['emails'][0] ?? '',
                    ];
                }
            }
        }

        if ($matchIdx !== null) {
            $groups[$matchIdx]['ids'][]         = $uid;
            $groups[$matchIdx]['servers'][]      = $server;
            $groups[$matchIdx]['server_types'][] = $stype;
            if ($expires !== null) {
                $groups[$matchIdx]['expires'][] = $expires;
            }
            if ($eLow !== '' && !in_array($eLow, $groups[$matchIdx]['emails'], true)) {
                $groups[$matchIdx]['emails'][]  = $eLow;
                $emailIndex[$eLow]              = $matchIdx;
            }
            if ($uLow !== '' && !in_array($matchIdx, $nameIndex[$uLow] ?? [], true)) {
                $nameIndex[$uLow][]             = $matchIdx;
            }
        } else {
            $newIdx         = count($groups);
            $groups[$newIdx] = [
                'ids'          => [$uid],
                'username'     => $uname,
                'emails'       => $eLow !== '' ? [$eLow] : [],
                'servers'      => [$server],
                'server_types' => [$stype],
                'expires'      => $expires !== null ? [$expires] : [],
            ];
            if ($eLow !== '') {
                $emailIndex[$eLow] = $newIdx;
            }
            if ($uLow !== '') {
                $nameIndex[$uLow][] = $newIdx;
            }
        }
    }

    return [
        'count'     => count($groups),
        'groups'    => array_values($groups),
        'ambiguous' => $ambiguous,
    ];
}

/**
 * Filtre et déduplique une liste d'utilisateurs pour un ensemble de serveurs.
 *
 * Utilise le champ User.server (nom du serveur) pour filtrer, puis
 * déduplique correctement via wizarrinvite_deduplicate_users (gère email "empty").
 *
 * @param  array    $allUsers     Liste brute de GET /api/users
 * @param  string[] $serverNames  Noms des serveurs à cibler
 * @return array  ['users' => [...], 'count' => N, 'groups' => [...], 'ambiguous' => [...]]
 */
function wizarrinvite_filter_users_for_servers(array $allUsers, array $serverNames)
{
    $filtered = [];
    foreach ($allUsers as $user) {
        if (is_array($user) && in_array($user['server'] ?? '', $serverNames, true)) {
            $filtered[] = $user;
        }
    }

    $dedup = wizarrinvite_deduplicate_users($filtered);

    return [
        'users'     => $filtered,          // Enregistrements bruts pour calcul d'expiration
        'count'     => $dedup['count'],
        'groups'    => $dedup['groups'],
        'ambiguous' => $dedup['ambiguous'],
    ];
}

/**
 * Résout les noms des serveurs correspondant à une liste d'IDs.
 *
 * @param  array $allServers  Liste brute de GET /api/servers
 * @param  int[] $serverIds   IDs à résoudre
 * @return string[]  Noms trouvés (sans doublons ni chaînes vides)
 */
function wizarrinvite_resolve_server_names(array $allServers, array $serverIds)
{
    $names = [];
    foreach ($allServers as $srv) {
        if (!is_array($srv)) {
            continue;
        }
        if (in_array((int)($srv['id'] ?? 0), $serverIds, true)) {
            $name = trim($srv['name'] ?? '');
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
    }
    return $names;
}

/**
 * Retourne les statistiques globales des utilisateurs avec décompte par serveur.
 *
 * Utilise GET /api/users (timeout 15 s), repli sur GET /api/status.
 * Retourne également une décomposition par nom de serveur (User.server).
 *
 * @param  array $cfg
 * @return array{ok: bool, message: string, data: array|null}
 */
function wizarrinvite_user_stats($cfg, $trigger = 'api')
{
    $baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($cfg['WIZARRINVITE-api-key'] ?? '');

    if (!$baseUrl || !$apiKey) {
        return ['ok' => false, 'message' => 'Configuration missing', 'data' => null];
    }

    // ── Requête GET /users — timeout 0 = illimité (l'API peut être très lente) ──
    $usersResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'users', null, 0);
    $httpCode    = (int)$usersResult['http'];

    if ($httpCode !== 200) {
        wizarrinvite_log('warn', 'GET /users failed — HTTP ' . $httpCode
            . ($usersResult['curl_error'] ? ' / cURL: ' . $usersResult['curl_error'] : '')
            . ' — falling back to /status');
    }

    if ($httpCode === 200) {
        $allUsers = is_array($usersResult['body']['users'] ?? null) ? $usersResult['body']['users'] : [];

        if (empty($allUsers)) {
            wizarrinvite_log('warn', 'GET /users returned HTTP 200 but users array is empty or missing');
        }

        // Sauvegarder les users bruts en cache pour les slots (réutilisation sans nouvel appel API)
        if (!empty($allUsers)) {
            wizarrinvite_save_users_cache($allUsers, $trigger);
        }

        // Inject Plex Home virtual users (after cache save — don't persist PH in users cache)
        $plexHomeUsers = wizarrinvite_build_virtual_plex_home_users();
        if (!empty($plexHomeUsers)) {
            $allUsers = array_merge($allUsers, $plexHomeUsers);
        }

        // Déduplication globale + décompte par serveur (User.server) avec PH inclus
        $dedup         = wizarrinvite_deduplicate_users($allUsers);
        $perServer     = [];
        $usersByServer = [];

        foreach ($allUsers as $user) {
            if (!is_array($user)) continue;
            $serverName = trim($user['server'] ?? '');
            if ($serverName === '') $serverName = 'Unknown';
            $perServer[$serverName] = ($perServer[$serverName] ?? 0) + 1;
            $usersByServer[$serverName][] = [
                'username' => $user['username'] ?? ($user['email'] ?? '?'),
                'email'    => $user['email']    ?? '',
                'expires'  => $user['expires']  ?? null,
            ];
        }
        arsort($perServer);

        wizarrinvite_log('info', 'GET /users — ' . count($allUsers) . ' records / '
            . $dedup['count'] . ' unique / '
            . count($perServer) . ' servers: ' . implode(', ', array_keys($perServer))
            . ' — trigger: ' . $trigger);

        return [
            'ok'      => true,
            'message' => 'User stats loaded',
            'data'    => [
                'count'           => count($allUsers),
                'unique_count'    => $dedup['count'],
                'next_expiry'     => wizarrinvite_find_next_expiry($allUsers),
                'per_server'      => $perServer,
                'users_by_server' => $usersByServer,
                'groups'          => $dedup['groups'],
                'ambiguous'       => $dedup['ambiguous'],
            ],
        ];
    }

    // ── Repli sur /status (pas de décompte par serveur possible) ─────────────
    $statusResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'status');
    $fallbackCount = (int)($statusResult['body']['users'] ?? 0);

    return [
        'ok'      => true,
        'message' => 'User stats loaded (status fallback — /users unavailable)',
        'data'    => [
            'count'              => $fallbackCount,
            'unique_count'       => null,
            'next_expiry'        => null,
            'per_server'         => [],
            'users_by_server'    => [],
            'groups'             => [],
            'ambiguous'          => [],
            'fallback'           => true,
        ],
    ];
}

/**
 * Trouve la prochaine date d'expiration parmi la liste des utilisateurs.
 * Seules les expirations futures sont considérées.
 *
 * @param  array       $users
 * @return string|null  Date ISO ou null
 */
function wizarrinvite_find_next_expiry(array $users)
{
    $now     = time();
    $nextTs  = null;
    $nextIso = null;

    // Noms de champ possibles selon la version de Wizarr
    $expiryFields = ['expires', 'expiry', 'expire', 'auth_expiry', 'plex_expiry', 'expired_at', 'expire_date'];

    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }

        $exp = null;
        foreach ($expiryFields as $field) {
            $val = $user[$field] ?? null;
            if ($val !== null && $val !== '' && $val !== false) {
                $exp = $val;
                break;
            }
        }

        if (!$exp) {
            continue;
        }

        $ts = strtotime((string)$exp);
        if ($ts === false || $ts <= $now) {
            continue;
        }

        if ($nextTs === null || $ts < $nextTs) {
            $nextTs  = $ts;
            $nextIso = (string)$exp;
        }
    }

    return $nextIso;
}
