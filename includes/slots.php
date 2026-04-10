<?php

// ─────────────────────────────────────────────────────────────────────────────
// Gestion des slots automatiques
//
// Un slot est une invitation automatique indépendante avec ses propres paramètres.
// Chaque slot possède :
//   - label, min_group              → accès Organizr
//   - expiration, access_days       → durée de l'invitation Wizarr
//   - server_ids, library_ids       → serveurs et bibliothèques ciblés
//   - allow_*, ...                  → options Plex
//   - max_users                     → limite d'utilisateurs pour ce slot (vide = aucune limite)
//                                     le décompte est dédupliqué automatiquement (email / username)
//
// La config de tous les slots est sérialisée en JSON dans WIZARRINVITE-slots-config.
// Chaque slot a son propre fichier cache : wizarrinvite_slot_{id}.json
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le tableau de tous les slots depuis la configuration Organizr.
 *
 * @param  array $cfg
 * @return array
 */
function wizarrinvite_get_slots_config($cfg)
{
    $raw   = $cfg['WIZARRINVITE-slots-config'] ?? '[]';
    $slots = json_decode($raw, true);

    return is_array($slots) ? $slots : [];
}

/**
 * Retourne un slot par son ID, ou null s'il n'existe pas.
 *
 * @param  array $cfg
 * @param  int   $slotId
 * @return array|null
 */
function wizarrinvite_get_slot_by_id($cfg, $slotId)
{
    foreach (wizarrinvite_get_slots_config($cfg) as $slot) {
        if ((int)($slot['id'] ?? 0) === (int)$slotId) {
            return $slot;
        }
    }

    return null;
}

/**
 * Calcule la signature SHA-1 de la configuration d'un slot.
 *
 * Chaque fois que l'admin modifie un paramètre du slot, la signature change.
 * Lors du chargement de la page /display, elle est comparée à celle stockée
 * en cache : si elles diffèrent, l'invitation existante est supprimée et
 * une nouvelle est créée avec les nouveaux paramètres.
 *
 * Champs inclus dans la signature (et pourquoi) :
 *  - expiration, access_days, server_ids, library_ids, permissions, bundle_id
 *    → paramètres directs de l'invitation Wizarr
 *  - max_users → paramètre de limite utilisateur : un changement de limite ne
 *    modifie pas l'invitation existante mais doit quand même invalider le cache
 *
 * @param  array $slotConfig
 * @return string  SHA-1 hexadécimal
 */
function wizarrinvite_slot_config_signature($slotConfig)
{
    $key = [
        'expiration'           => $slotConfig['expiration']    ?? '1',
        'access_days'          => $slotConfig['access_days']   ?? '7',
        'server_ids'           => $slotConfig['server_ids']    ?? '',
        'library_ids'          => $slotConfig['library_ids']   ?? '',
        'allow_downloads'      => !empty($slotConfig['allow_downloads']),
        'allow_live_tv'        => !empty($slotConfig['allow_live_tv']),
        'allow_mobile_uploads' => !empty($slotConfig['allow_mobile_uploads']),
        'max_users'            => trim((string)($slotConfig['max_users'] ?? '')),
        'common_servers'       => !empty($slotConfig['common_servers']),
        'bundle_id'            => $slotConfig['bundle_id']     ?? '',
    ];
    ksort($key);

    return sha1(json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Effectue un appel pour obtenir le compte d'utilisateurs effectifs d'un slot.
 * Retourne un tableau avec count, per_server_counts et server_names, ou null.
 *
 * @param  string $baseUrl
 * @param  string $apiKey
 * @param  array  $slotConfig
 * @param  bool   $skipUsersCache  true = toujours appeler l'API (mode live)
 * @return array{count:int, per_server_counts:array, server_names:array}|null
 */
function wizarrinvite_fetch_slot_effective_count($baseUrl, $apiKey, $slotConfig, $skipUsersCache = false)
{
    $slotServerIds   = wizarrinvite_parse_int_csv($slotConfig['server_ids'] ?? '');
    $commonServers   = !empty($slotConfig['common_servers']);
    $effectiveCount  = null;
    $perServerCounts = [];
    $resolvedNames   = [];

    // Priorité : cache users (sauf si skipUsersCache=true pour mode live)
    if (!$skipUsersCache) {
        $usersCache = wizarrinvite_load_users_cache();
    } else {
        $usersCache = null;
    }

    if ($usersCache && !empty($usersCache['users'])) {
        $allUsers    = $usersCache['users'];
        $cacheAgeMin = round((time() - (int)($usersCache['updated_at'] ?? 0)) / 60);
        wizarrinvite_log('debug', 'Slot users — cache used (age: ' . $cacheAgeMin . ' min)');
    } else {
        $usersResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'users', null, 0);
        if ((int)$usersResult['http'] !== 200) {
            $statusResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'status');
            if ((int)$statusResult['http'] === 200) {
                return ['count' => (int)($statusResult['body']['users'] ?? 0), 'per_server_counts' => [], 'server_names' => []];
            }
            return null;
        }
        $allUsers = is_array($usersResult['body']['users'] ?? null) ? $usersResult['body']['users'] : [];
        if (!empty($allUsers)) {
            wizarrinvite_save_users_cache($allUsers);
        }
    }

    // Inject Plex Home virtual users
    $plexHomeUsers = wizarrinvite_build_virtual_plex_home_users();
    if (!empty($plexHomeUsers)) {
        $allUsers = array_merge($allUsers, $plexHomeUsers);
    }

    if (!empty($slotServerIds)) {
        $serversResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'servers', null, 5);
        $allServers    = is_array($serversResult['body']['servers'] ?? null) ? $serversResult['body']['servers'] : [];
        $resolvedNames = wizarrinvite_resolve_server_names($allServers, $slotServerIds);

        if (empty($resolvedNames)) {
            wizarrinvite_log('warn', 'fetch_slot_effective_count — server IDs [' . implode(',', $slotServerIds) . '] did not resolve to any server name'
                . ' (GET /servers HTTP ' . ($serversResult['http'] ?? '?') . ', ' . count($allServers) . ' servers returned)');
        } else {
            // Per-server counts (always computed regardless of common_servers)
            foreach ($resolvedNames as $name) {
                $sData = wizarrinvite_filter_users_for_servers($allUsers, [$name]);
                $perServerCounts[$name] = $sData['count'];
            }
            wizarrinvite_log('debug', 'fetch_slot_effective_count — resolved servers: ' . json_encode($perServerCounts));

            if ($commonServers) {
                $filtered       = wizarrinvite_filter_users_for_servers($allUsers, $resolvedNames);
                $effectiveCount = $filtered['count'];
            } else {
                $effectiveCount = empty($perServerCounts) ? 0 : max(array_values($perServerCounts));
            }
        }
    }

    if ($effectiveCount === null) {
        $dedup          = wizarrinvite_deduplicate_users($allUsers);
        $effectiveCount = $dedup['count'];
    }

    return [
        'count'             => $effectiveCount,
        'per_server_counts' => $perServerCounts,
        'server_names'      => $resolvedNames,
    ];
}

/**
 * Extrait les paramètres d'invitation depuis la config d'un slot.
 *
 * @param  array $slotConfig
 * @return array
 */
function wizarrinvite_slot_mode_cfg($slotConfig)
{
    $bundleRaw = trim((string)($slotConfig['bundle_id'] ?? ''));

    return [
        'expiration'           => $slotConfig['expiration']      ?? '1',
        'access_days'          => $slotConfig['access_days']     ?? '7',
        'server_ids'           => $slotConfig['server_ids']      ?? '',
        'library_ids'          => $slotConfig['library_ids']     ?? '',
        'allow_downloads'      => !empty($slotConfig['allow_downloads']),
        'allow_live_tv'        => !empty($slotConfig['allow_live_tv']),
        'allow_mobile_uploads' => !empty($slotConfig['allow_mobile_uploads']),
        'invite_to_plex_home'  => false,
        'bundle_id'            => ($bundleRaw !== '' && is_numeric($bundleRaw)) ? (int)$bundleRaw : null,
    ];
}

/**
 * Construit le payload API Wizarr depuis la config d'un slot.
 * Accepte un slotConfig modifié (ex : server_ids filtrés après vérification capacité).
 *
 * @param  array $slotConfig
 * @return array  Payload normalisé
 */
function wizarrinvite_build_payload_from_slot($slotConfig)
{
    $modeCfg = wizarrinvite_slot_mode_cfg($slotConfig);

    $payload = [
        'server_ids'           => wizarrinvite_parse_int_csv($modeCfg['server_ids']),
        'expires_in_days'      => wizarrinvite_expiration_value($modeCfg['expiration']),
        'duration'             => (string)$modeCfg['access_days'],
        'unlimited'            => false,
        'allow_downloads'      => $modeCfg['allow_downloads'],
        'allow_live_tv'        => $modeCfg['allow_live_tv'],
        'allow_mobile_uploads' => $modeCfg['allow_mobile_uploads'],
    ];

    $libraryIds = wizarrinvite_parse_int_csv($modeCfg['library_ids']);
    if (!empty($libraryIds)) {
        $payload['library_ids'] = $libraryIds;
    }

    // Bundle Wizarr (optionnel — omis si non défini)
    if ($modeCfg['bundle_id'] !== null) {
        $payload['wizard_bundle_id'] = $modeCfg['bundle_id'];
    }

    return wizarrinvite_normalize_payload($payload);
}

/**
 * Retourne les statistiques utilisateurs dédupliquées pour un slot.
 *
 * Utilise les groupes de serveurs du slot (server_groups) et sa limite (max_users)
 * pour calculer des comptes précis par compte Plex.
 *
 * Retourne :
 *   count              → total effectif (dédupliqué si common_servers, MAX sinon)
 *   max                → limite max_users du slot
 *   limit_reached      → vrai si le slot est plein
 *   common_servers     → true = serveurs partagés, false = indépendants
 *   per_server_counts  → [serverName => count] pour chaque serveur du slot
 *   server_names       → noms des serveurs résolus
 *
 * @param  array $cfg
 * @param  array $slotConfig
 * @return array{ok: bool, message: string, data: array|null}
 */
function wizarrinvite_slot_user_stats($cfg, $slotConfig)
{
    $baseUrl       = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey        = trim($cfg['WIZARRINVITE-api-key'] ?? '');
    $slotId        = (int)($slotConfig['id'] ?? 0);
    $commonServers = !empty($slotConfig['common_servers']);

    // max_users vide ou 0 → null (infini, pas de blocage)
    $rawMax   = trim((string)($slotConfig['max_users'] ?? ''));
    $maxUsers = ($rawMax !== '' && (int)$rawMax > 0) ? (int)$rawMax : null;

    // show_user_count absent sur les anciens slots → true (rétrocompatibilité)
    $showCount = isset($slotConfig['show_user_count']) ? (bool)$slotConfig['show_user_count'] : true;

    // show_next_expiry absent sur les anciens slots → false
    $showNextExpiry = isset($slotConfig['show_next_expiry']) ? (bool)$slotConfig['show_next_expiry'] : false;

    if (!$baseUrl || !$apiKey) {
        return ['ok' => false, 'message' => 'Configuration missing', 'data' => null];
    }

    // Compteur désactivé : aucun appel API, pas de blocage
    if (!$showCount) {
        return [
            'ok'      => true,
            'message' => 'User count tracking disabled',
            'data'    => [
                'show_user_count'  => false,
                'show_next_expiry' => false,
                'limit_reached'    => false,
                'count'            => null,
                'max'              => null,
                'next_expiry'      => null,
                'slot_id'          => (int)($slotConfig['id']    ?? 0),
                'slot_label'       => $slotConfig['label'] ?? ('Slot ' . ($slotConfig['id'] ?? 0)),
            ],
        ];
    }

    // Mode différé : retourne le compteur mis en cache (rafraîchi si expiré ou config changée)
    $deferredCount  = isset($slotConfig['deferred_count']) ? (bool)$slotConfig['deferred_count'] : false;
    $cacheHours     = max(0, (int)($cfg['WIZARRINVITE-count-cache-hours']   ?? 24));
    $cacheMins      = max(0, (int)($cfg['WIZARRINVITE-count-cache-minutes'] ?? 0));
    $intervalSec    = max(60, $cacheHours * 3600 + $cacheMins * 60);
    $smartEnabled   = !empty($cfg['WIZARRINVITE-smart-check-enabled']);
    $smartThreshold = max(0, (int)($cfg['WIZARRINVITE-smart-check-threshold'] ?? 5));

    if ($deferredCount) {
        $slotId       = (int)($slotConfig['id'] ?? 0);
        $curServerIds = trim($slotConfig['server_ids'] ?? '');
        $cached       = wizarrinvite_get_slot_count_cache($slotId);
        $cacheAge     = $cached ? (time() - (int)($cached['updated_at'] ?? 0)) : PHP_INT_MAX;
        $cachedCount  = $cached ? (int)$cached['count'] : null;

        // Invalide si server_ids a changé depuis le dernier cache
        $configMismatch = $cached && (($cached['server_ids'] ?? '') !== $curServerIds);

        // Also force live if cache predates the per_server_counts field (old format)
        $missingPerServer = $cached && !isset($cached['per_server_counts']);

        // Smart Check : live if close to the limit (even if cache is valid)
        $smartCheck = $smartEnabled
                   && $cachedCount !== null
                   && $maxUsers !== null
                   && ($maxUsers - $cachedCount) <= $smartThreshold;

        $needsLive = $configMismatch || ($cached === null) || ($cacheAge > $intervalSec)
                  || $missingPerServer || $smartCheck;

        $perServerCountsD = [];
        $serverNamesD     = [];

        if ($needsLive) {
            $reason     = $cached === null ? 'no cache'
                       : ($configMismatch   ? 'config changed'
                       : ($smartCheck       ? 'smart check (near limit)'
                       : ($missingPerServer ? 'old cache format'
                       : 'cache expired')));
            // Smart check must bypass users cache — we need a truly fresh API call
            $liveStats  = wizarrinvite_fetch_slot_effective_count($baseUrl, $apiKey, $slotConfig, $smartCheck);
            if ($liveStats !== null) {
                $perServerCountsD = $liveStats['per_server_counts'];
                $serverNamesD     = $liveStats['server_names'];
                wizarrinvite_save_slot_count_cache($slotId, $liveStats['count'], $curServerIds, $perServerCountsD, $serverNamesD);
                wizarrinvite_log('info', 'Deferred count live check — slot #' . $slotId . ' (' . $reason . '): ' . $liveStats['count'] . ' users');
                $effectiveCountD = $liveStats['count'];
                $cacheAge        = 0;
            } else {
                $effectiveCountD  = $cached ? (int)$cached['count'] : null;
                $perServerCountsD = $cached['per_server_counts'] ?? [];
                $serverNamesD     = $cached['server_names'] ?? [];
            }
        } else {
            $effectiveCountD  = (int)$cached['count'];
            $perServerCountsD = $cached['per_server_counts'] ?? [];
            $serverNamesD     = $cached['server_names'] ?? [];
        }

        $limitReached = ($maxUsers !== null) && ($effectiveCountD !== null) && ($effectiveCountD >= $maxUsers);

        // Calcul next_expiry depuis le cache users si l'option est activée
        $nextExpiryD = null;
        if ($showNextExpiry) {
            $usersCache  = wizarrinvite_load_users_cache();
            $cachedUsers = $usersCache ? ($usersCache['users'] ?? []) : [];
            if (!empty($cachedUsers)) {
                $nextExpiryD = wizarrinvite_find_next_expiry($cachedUsers);
            }
        }

        return [
            'ok'      => true,
            'message' => 'Slot user stats loaded (deferred)',
            'data'    => [
                'count'            => $effectiveCountD,
                'max'              => $maxUsers,
                'show_user_count'  => true,
                'show_next_expiry' => $showNextExpiry,
                'limit_reached'    => $limitReached,
                'next_expiry'      => $nextExpiryD,
                'raw_count'        => $effectiveCountD,
                'common_servers'   => $commonServers,
                'per_server_counts'=> $perServerCountsD,
                'server_names'     => $serverNamesD,
                'deferred'         => true,
                'cache_age_s'      => $cacheAge === PHP_INT_MAX ? null : $cacheAge,
                'slot_id'          => $slotId,
                'slot_label'       => $slotConfig['label'] ?? ('Slot ' . $slotId),
            ],
        ];
    }

    $slotServerIds = wizarrinvite_parse_int_csv($slotConfig['server_ids'] ?? '');

    // ── Mode live : appel API frais (pas de cache users) ─────────────────────
    $result   = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'users', null, 0);
    $httpOk   = (int)$result['http'] === 200;
    $allUsers = ($httpOk && is_array($result['body']['users'] ?? null)) ? $result['body']['users'] : [];
    if (!empty($allUsers)) {
        wizarrinvite_save_users_cache($allUsers); // update cache with fresh data
    }

    if ($httpOk) {
        // Inject Plex Home virtual users
        $plexHomeUsers = wizarrinvite_build_virtual_plex_home_users();
        if (!empty($plexHomeUsers)) {
            $allUsers = array_merge($allUsers, $plexHomeUsers);
        }
        $totalCount      = count($allUsers);
        $usersForExpiry  = $allUsers;
        $effectiveCount  = null;
        $perServerCounts = [];
        $resolvedNames   = [];

        if (!empty($slotServerIds)) {
            $serversResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'servers', null, 5);
            $allServers    = is_array($serversResult['body']['servers'] ?? null) ? $serversResult['body']['servers'] : [];
            $resolvedNames = wizarrinvite_resolve_server_names($allServers, $slotServerIds);

            if (empty($resolvedNames)) {
                wizarrinvite_log('warn', 'Slot #' . $slotId . ' user_stats — server IDs [' . implode(',', $slotServerIds) . '] did not resolve'
                    . ' (GET /servers HTTP ' . ($serversResult['http'] ?? '?') . ', ' . count($allServers) . ' servers returned)');
            } else {
                // Décompte par serveur (chaque serveur dédupliqué indépendamment)
                foreach ($resolvedNames as $srvName) {
                    $sData = wizarrinvite_filter_users_for_servers($allUsers, [$srvName]);
                    $perServerCounts[$srvName] = $sData['count'];
                }
                wizarrinvite_log('debug', 'Slot #' . $slotId . ' user_stats — per-server: ' . json_encode($perServerCounts));

                if ($commonServers) {
                    // Serveurs partagés (même compte) : déduplication globale
                    $filtered       = wizarrinvite_filter_users_for_servers($allUsers, $resolvedNames);
                    $effectiveCount = $filtered['count'];
                    $usersForExpiry = $filtered['users'];
                    foreach ($filtered['ambiguous'] as $a) {
                        wizarrinvite_log('debug', 'Slot #' . $slotId . ' ambiguous person: username "' . ($a['username'] ?? '?') . '" has different emails on different servers (IDs ' . implode(',', $a['existing_ids']) . ' vs ' . $a['new_id'] . ')');
                    }
                } else {
                    // Serveurs indépendants : MAX du décompte par serveur
                    $effectiveCount = empty($perServerCounts) ? 0 : max(array_values($perServerCounts));
                    // Collecter tous les utilisateurs des serveurs pour l'expiration
                    $allFiltered    = wizarrinvite_filter_users_for_servers($allUsers, $resolvedNames);
                    $usersForExpiry = $allFiltered['users'];
                    foreach ($allFiltered['ambiguous'] as $a) {
                        wizarrinvite_log('debug', 'Slot #' . $slotId . ' ambiguous person: username "' . ($a['username'] ?? '?') . '" has different emails on different servers (IDs ' . implode(',', $a['existing_ids']) . ' vs ' . $a['new_id'] . ')');
                    }
                }
            }
        }

        // Pas de filtre serveur résolu : dédupliquer tous les utilisateurs
        if ($effectiveCount === null) {
            $dedup = wizarrinvite_deduplicate_users($allUsers);
            $effectiveCount = $dedup['count'];
            foreach ($dedup['ambiguous'] as $a) {
                wizarrinvite_log('debug', 'Slot #' . $slotId . ' ambiguous person: username "' . ($a['username'] ?? '?') . '" has different emails on different servers (IDs ' . implode(',', $a['existing_ids']) . ' vs ' . $a['new_id'] . ')');
            }
        }

        // Blocage uniquement si une limite est définie (max_users non null)
        $limitReached = ($maxUsers !== null) && ($effectiveCount >= $maxUsers);

        return [
            'ok'      => true,
            'message' => 'Slot user stats loaded',
            'data'    => [
                'count'            => $effectiveCount,
                'max'              => $maxUsers,
                'show_user_count'  => true,
                'show_next_expiry' => $showNextExpiry,
                'limit_reached'    => $limitReached,
                'next_expiry'      => $showNextExpiry ? wizarrinvite_find_next_expiry($usersForExpiry) : null,
                'raw_count'        => $totalCount,
                'common_servers'   => $commonServers,
                'per_server_counts'=> $perServerCounts,
                'server_names'     => $resolvedNames,
                'slot_id'          => $slotId,
                'slot_label'       => $slotConfig['label'] ?? ('Slot ' . $slotId),
            ],
        ];
    }

    // ── Repli sur /status si /users échoue ───────────────────────────────────
    $statusResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'status');

    if ((int)$statusResult['http'] === 200) {
        // Compte brut (pas de déduplication possible avec /status)
        $totalCount     = (int)($statusResult['body']['users'] ?? 0);
        $effectiveCount = $totalCount;
        $limitReached   = ($maxUsers !== null) && ($effectiveCount >= $maxUsers);

        return [
            'ok'      => true,
            'message' => 'Slot user stats loaded (status fallback)',
            'data'    => [
                'count'            => $effectiveCount,
                'max'              => $maxUsers,
                'show_user_count'  => true,
                'show_next_expiry' => $showNextExpiry,
                'limit_reached'    => $limitReached,
                'next_expiry'      => null,
                'raw_count'        => $totalCount,
                'slot_id'          => (int)($slotConfig['id']    ?? 0),
                'slot_label'       => $slotConfig['label'] ?? ('Slot ' . ($slotConfig['id'] ?? 0)),
            ],
        ];
    }

    // ── Les deux API ont échoué : compteur visible mais count inconnu ─────────
    return [
        'ok'      => false,
        'message' => $result['http'] ? 'HTTP error ' . $result['http'] : 'Network error: ' . $result['curl_error'],
        'data'    => [
            'count'            => null,
            'max'              => $maxUsers,
            'show_next_expiry' => $showNextExpiry,
            'limit_reached'    => false,
            'next_expiry'      => null,
            'unavailable'      => true,
        ],
    ];
}

/**
 * Vérifie l'état de l'invitation d'un slot.
 *
 * États : 'disabled', 'missing', 'stale', 'active'
 * La comparaison de signature est basée sur le slot config complet (y compris
 * max_users et server_groups) pour détecter les vrais changements de config.
 *
 * @param  array $cfg
 * @param  array $slotConfig
 * @return array{state: string, message: string, current: array|null, cache_file: string}
 */
function wizarrinvite_slot_status($cfg, $slotConfig)
{
    $slotId    = (int)($slotConfig['id'] ?? 0);
    $cacheFile = wizarrinvite_slot_cache_file($slotId);

    if (!wizarrinvite_plugin_enabled($cfg)) {
        return ['state' => 'disabled', 'message' => 'Plugin disabled', 'current' => null, 'cache_file' => $cacheFile];
    }

    $cache            = wizarrinvite_load_slot_cache($slotId);
    $desiredSignature = wizarrinvite_slot_config_signature($slotConfig);

    if (!$cache) {
        return ['state' => 'missing', 'message' => 'No slot code in cache', 'current' => null, 'cache_file' => $cacheFile];
    }

    // ── Vérification de la signature AVANT tout appel réseau ─────────────────
    if (empty($cache['config_signature'])) {
        return ['state' => 'stale', 'message' => 'Slot code cache format outdated', 'current' => $cache, 'cache_file' => $cacheFile];
    }
    if ((string)$cache['config_signature'] !== (string)$desiredSignature) {
        return ['state' => 'stale', 'message' => 'Slot config changed', 'current' => $cache, 'cache_file' => $cacheFile];
    }

    // ── Vérification systématique via l'API Wizarr ───────────────────────────
    // GET /invitations est rapide — on vérifie à chaque appel que l'invitation
    // existe encore dans Wizarr (détecte les suppressions manuelles immédiatement).
    $found = wizarrinvite_find_cached_invite_in_wizarr($cfg, $cache);

    if (!$found) {
        return ['state' => 'missing', 'message' => 'Slot code missing from Wizarr', 'current' => $cache, 'cache_file' => $cacheFile];
    }

    $invStatus = $found['status'] ?? 'pending';
    if ($invStatus === 'used' || $invStatus === 'expired') {
        return ['state' => 'missing', 'message' => 'Slot code is ' . $invStatus, 'current' => $cache, 'cache_file' => $cacheFile];
    }

    $current = [
        'id'               => $found['id']      ?? $cache['id'],
        'code'             => $found['code']    ?? $cache['code'],
        'url'              => wizarrinvite_build_public_url($cfg, $found),
        'expires'          => $found['expires'] ?? ($cache['expires'] ?? null),
        'status'           => $invStatus,
        'invite_mode'      => 'slot',
        'slot_id'          => $slotId,
        'slot_label'       => $slotConfig['label'] ?? ('Slot ' . $slotId),
        'payload'          => $cache['payload']         ?? null,
        'config_signature' => $desiredSignature,
        'access_days'      => $cache['access_days']     ?? null,
    ];

    wizarrinvite_save_slot_cache($slotId, $current);

    return ['state' => 'active', 'message' => 'Slot code active', 'current' => $current, 'cache_file' => $cacheFile];
}

/**
 * Retourne l'invitation active d'un slot, ou en crée une nouvelle si nécessaire.
 *
 * Quand l'invitation est déjà active, elle est retournée directement sans
 * revérifier la capacité serveur (appel API coûteux évité à chaque affichage).
 * L'admin peut forcer la recréation via le bouton "Check / Recreate" en settings.
 *
 * @param  array $cfg
 * @param  array $slotConfig
 * @return array{ok: bool, message: string, data: mixed}
 */
function wizarrinvite_get_or_create_slot($cfg, $slotConfig)
{
    if (!wizarrinvite_plugin_enabled($cfg)) {
        return ['ok' => false, 'message' => 'Plugin disabled', 'data' => null];
    }

    $baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($cfg['WIZARRINVITE-api-key'] ?? '');
    $slotId  = (int)($slotConfig['id'] ?? 0);

    $rawMax   = trim((string)($slotConfig['max_users'] ?? ''));
    $maxUsers = ($rawMax !== '' && (int)$rawMax > 0) ? (int)$rawMax : null;
    $showCount       = isset($slotConfig['show_user_count']) ? (bool)$slotConfig['show_user_count'] : true;
    $deferredCount   = isset($slotConfig['deferred_count'])  ? (bool)$slotConfig['deferred_count']  : false;
    $cacheHours      = max(0, (int)($cfg['WIZARRINVITE-count-cache-hours']     ?? 24));
    $cacheMins       = max(0, (int)($cfg['WIZARRINVITE-count-cache-minutes']   ?? 0));
    $intervalSec     = max(60, $cacheHours * 3600 + $cacheMins * 60);
    $smartEnabled    = !empty($cfg['WIZARRINVITE-smart-check-enabled']);
    $smartThreshold  = max(0, (int)($cfg['WIZARRINVITE-smart-check-threshold'] ?? 5));

    // ── Étape 1 : vérification de la capacité (indépendante de l'invite) ──────
    if ($showCount && $maxUsers !== null) {
        $effectiveCount = null;

        if ($deferredCount) {
            $curServerIds   = trim($slotConfig['server_ids'] ?? '');
            $cached         = wizarrinvite_get_slot_count_cache($slotId);
            $cacheAge       = $cached ? (time() - (int)($cached['updated_at'] ?? 0)) : PHP_INT_MAX;
            $cachedCount    = $cached ? (int)$cached['count'] : null;
            $configMismatch = $cached && (($cached['server_ids'] ?? '') !== $curServerIds);
            $missingPerServer = $cached && !isset($cached['per_server_counts']);
            $smartCheck     = $smartEnabled && $cachedCount !== null && ($maxUsers - $cachedCount) <= $smartThreshold;
            $needsLive      = $configMismatch || ($cachedCount === null) || ($cacheAge > $intervalSec) || $smartCheck || $missingPerServer;

            if ($needsLive) {
                $reason     = $cached === null ? 'no cache'
                           : ($configMismatch   ? 'config changed'
                           : ($smartCheck       ? 'smart check (near limit)'
                           : ($missingPerServer ? 'old cache format'
                           : 'cache expired')));
                $liveStats  = wizarrinvite_fetch_slot_effective_count($baseUrl, $apiKey, $slotConfig, $smartCheck);
                $effectiveCount = $liveStats !== null ? $liveStats['count'] : null;
                if ($liveStats !== null) {
                    wizarrinvite_save_slot_count_cache($slotId, $liveStats['count'], $curServerIds, $liveStats['per_server_counts'], $liveStats['server_names']);
                    wizarrinvite_log('info', 'Slot #' . $slotId . ' count check (' . $reason . '): ' . $liveStats['count'] . ' users');
                }
            } else {
                $effectiveCount = $cachedCount;
            }
        } else {
            $liveStats = wizarrinvite_fetch_slot_effective_count($baseUrl, $apiKey, $slotConfig, true);
            $effectiveCount = $liveStats !== null ? $liveStats['count'] : null;
        }

        if ($effectiveCount !== null && $effectiveCount >= $maxUsers) {
            wizarrinvite_log('info', 'Slot #' . $slotId . ' — user limit reached (' . $effectiveCount . '/' . $maxUsers . ')');
            return [
                'ok'      => false,
                'message' => 'User limit reached',
                'data'    => ['limit_reached' => true, 'count' => $effectiveCount, 'max' => $maxUsers],
            ];
        }
    }

    // ── Étape 2 : vérification et création du lien invite (toujours exécutée) ─
    $status = wizarrinvite_slot_status($cfg, $slotConfig);

    if ($status['state'] === 'active' && !empty($status['current'])) {
        return ['ok' => true, 'message' => 'Slot code active', 'data' => $status['current']];
    }

    // Invitation périmée → suppression avant recréation
    if ($status['state'] === 'stale') {
        if (!empty($status['current']['id'])) {
            $delete = wizarrinvite_delete_invite($cfg, $status['current']['id']);
            if (!$delete['ok']) {
                wizarrinvite_log('warn', 'Slot #' . $slotId . ' stale invite deletion failed (' . $delete['message'] . ') — proceeding');
            }
        }
        wizarrinvite_clear_slot_cache($slotId);
    }

    // Créer l'invitation (sans refaire la vérification de capacité déjà faite ci-dessus)
    return wizarrinvite_create_slot_invite_only($cfg, $slotConfig);
}

/**
 * Crée physiquement l'invitation dans Wizarr et sauvegarde le cache local.
 * Ne vérifie PAS la capacité — appelée uniquement depuis wizarrinvite_get_or_create_slot
 * qui a déjà effectué cette vérification à l'étape 1.
 *
 * @param  array $cfg
 * @param  array $slotConfig
 * @return array{ok: bool, message: string, data: mixed}
 */
function wizarrinvite_create_slot_invite_only($cfg, $slotConfig)
{
    $baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($cfg['WIZARRINVITE-api-key'] ?? '');
    $slotId  = (int)($slotConfig['id'] ?? 0);

    if (!$baseUrl || !$apiKey) {
        return ['ok' => false, 'message' => 'Configuration missing', 'data' => null];
    }

    $payload = wizarrinvite_build_payload_from_slot($slotConfig);
    $result  = wizarrinvite_request($baseUrl, $apiKey, 'POST', 'invitations', $payload);

    if ((int)$result['http'] !== 201) {
        return [
            'ok'      => false,
            'message' => $result['http']
                ? 'HTTP error ' . $result['http']
                : 'Network error: ' . $result['curl_error'],
            'data'    => ['payload' => $payload, 'response' => $result['body']],
        ];
    }

    $inv = $result['body']['invitation'] ?? null;

    if (!$inv || empty($inv['id']) || empty($inv['code'])) {
        return ['ok' => false, 'message' => 'Invalid Wizarr response', 'data' => $result['body']];
    }

    $data = [
        'id'               => $inv['id'],
        'code'             => $inv['code'],
        'url'              => wizarrinvite_build_public_url($cfg, $inv),
        'expires'          => $inv['expires']  ?? null,
        'status'           => $inv['status']   ?? 'pending',
        'created_at_ts'    => time(),
        'invite_mode'      => 'slot',
        'slot_id'          => $slotId,
        'slot_label'       => $slotConfig['label'] ?? ('Slot ' . $slotId),
        'config_signature' => wizarrinvite_slot_config_signature($slotConfig),
        'payload'          => $payload,
        'access_days'      => $payload['duration'] ?? null,
    ];

    wizarrinvite_save_slot_cache($slotId, $data);

    return ['ok' => true, 'message' => 'Slot invite created', 'data' => $data];
}

/**
 * Crée une invitation Wizarr pour un slot donné (vérifie la capacité).
 * Conservé pour compatibilité — le flux principal utilise wizarrinvite_get_or_create_slot.
 *
 * @param  array $cfg
 * @param  array $slotConfig
 * @return array{ok: bool, message: string, data: mixed}
 */
function wizarrinvite_create_slot_invite($cfg, $slotConfig)
{
    return wizarrinvite_get_or_create_slot($cfg, $slotConfig);
}
