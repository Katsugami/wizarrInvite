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
//   - max_users                     → limite d'utilisateurs pour ce slot (défaut : 100)
//   - server_count                  → nombre de serveurs partageant le même compte Plex
//                                     effectif = total / server_count (défaut : 1)
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
 *  - max_users, server_count → paramètres de limite utilisateur : un changement
 *    de limite ne modifie pas l'invitation existante mais doit quand même
 *    invalider le cache pour forcer une réévaluation de la capacité
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
        'max_users'            => $slotConfig['max_users']     ?? '100',
        'server_count'         => $slotConfig['server_count']  ?? '1',
        'bundle_id'            => $slotConfig['bundle_id']     ?? '',
    ];
    ksort($key);

    return sha1(json_encode($key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
 *   count         → total dédupliqué pour les serveurs du slot
 *   max           → limite max_users du slot
 *   limit_reached → vrai si au moins un compte Plex du slot est plein
 *   per_account   → détail par compte (groupe de serveurs)
 *   available     → IDs de serveurs encore disponibles
 *   full          → IDs de serveurs dont le compte est plein
 *
 * @param  array $cfg
 * @param  array $slotConfig
 * @return array{ok: bool, message: string, data: array|null}
 */
function wizarrinvite_slot_user_stats($cfg, $slotConfig)
{
    $baseUrl     = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey      = trim($cfg['WIZARRINVITE-api-key'] ?? '');
    $maxUsers    = max(1, (int)($slotConfig['max_users']    ?? 100));
    $serverCount = max(1, (int)($slotConfig['server_count'] ?? 1));

    if (!$baseUrl || !$apiKey) {
        return ['ok' => false, 'message' => 'Configuration missing', 'data' => null];
    }

    $slotServerIds = wizarrinvite_parse_int_csv($slotConfig['server_ids'] ?? '');

    $result = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'users', null, 8);

    if ((int)$result['http'] === 200) {
        $allUsers   = is_array($result['body']['users'] ?? null) ? $result['body']['users'] : [];
        $totalCount = count($allUsers);

        // ── Filtrage par serveur (User.server = nom) + déduplication ─────────
        $usersForExpiry = $allUsers;
        $effectiveCount = null;

        if (!empty($slotServerIds)) {
            $serversResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'servers', null, 5);
            $allServers    = is_array($serversResult['body']['servers'] ?? null) ? $serversResult['body']['servers'] : [];
            $serverNames   = wizarrinvite_resolve_server_names($allServers, $slotServerIds);

            if (!empty($serverNames)) {
                $filtered       = wizarrinvite_filter_users_for_servers($allUsers, $serverNames);
                $effectiveCount = $filtered['count'];
                $usersForExpiry = $filtered['users'];
            }
        }

        // Fallback diviseur server_count si résolution serveur impossible
        if ($effectiveCount === null) {
            $effectiveCount = (int)ceil($totalCount / $serverCount);
        }

        $limitReached = $effectiveCount >= $maxUsers;

        return [
            'ok'      => true,
            'message' => 'Slot user stats loaded',
            'data'    => [
                'count'         => $effectiveCount,
                'max'           => $maxUsers,
                'limit_reached' => $limitReached,
                'next_expiry'   => wizarrinvite_find_next_expiry($usersForExpiry),
                'raw_count'     => $totalCount,
                'server_count'  => $serverCount,
                'slot_id'       => (int)($slotConfig['id']    ?? 0),
                'slot_label'    => $slotConfig['label'] ?? ('Slot ' . ($slotConfig['id'] ?? 0)),
            ],
        ];
    }

    // ── Repli sur /status si /users échoue ───────────────────────────────────
    $statusResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'status');

    if ((int)$statusResult['http'] === 200) {
        $totalCount     = (int)($statusResult['body']['users'] ?? 0);
        $effectiveCount = (int)ceil($totalCount / $serverCount);
        $limitReached   = $effectiveCount >= $maxUsers;

        return [
            'ok'      => true,
            'message' => 'Slot user stats loaded (status fallback)',
            'data'    => [
                'count'         => $effectiveCount,
                'max'           => $maxUsers,
                'limit_reached' => $limitReached,
                'next_expiry'   => null,
                'raw_count'     => $totalCount,
                'server_count'  => $serverCount,
                'slot_id'       => (int)($slotConfig['id']    ?? 0),
                'slot_label'    => $slotConfig['label'] ?? ('Slot ' . ($slotConfig['id'] ?? 0)),
            ],
        ];
    }

    // ── Les deux API ont échoué : compteur visible mais count inconnu ─────────
    return [
        'ok'      => false,
        'message' => $result['http'] ? 'HTTP error ' . $result['http'] : 'Network error: ' . $result['curl_error'],
        'data'    => [
            'count'         => null,
            'max'           => $maxUsers,
            'limit_reached' => false,
            'next_expiry'   => null,
            'server_count'  => $serverCount,
            'unavailable'   => true,
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

    // ── Retour rapide si le cache a été vérifié récemment (< 5 min) ──────────
    // Évite un appel GET /api/invitations sur chaque chargement de la page display.
    $verifiedAt = (int)($cache['verified_at_ts'] ?? 0);
    if ((time() - $verifiedAt) < 300) {
        $current = [
            'id'               => $cache['id']              ?? '',
            'code'             => $cache['code']            ?? '',
            'url'              => $cache['url']             ?? wizarrinvite_build_public_url($cfg, $cache),
            'expires'          => $cache['expires']         ?? null,
            'status'           => $cache['status']          ?? 'pending',
            'invite_mode'      => 'slot',
            'slot_id'          => $slotId,
            'slot_label'       => $slotConfig['label']      ?? ('Slot ' . $slotId),
            'payload'          => $cache['payload']         ?? null,
            'config_signature' => $desiredSignature,
            'access_days'      => $cache['access_days']     ?? null,
            'verified_at_ts'   => $verifiedAt,
        ];
        return ['state' => 'active', 'message' => 'Slot code active (cached)', 'current' => $current, 'cache_file' => $cacheFile];
    }

    // ── Vérification complète via l'API Wizarr ────────────────────────────────
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
        'verified_at_ts'   => time(),
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

    $slotId = (int)($slotConfig['id'] ?? 0);
    $status = wizarrinvite_slot_status($cfg, $slotConfig);

    // Invitation déjà active → retour immédiat
    if ($status['state'] === 'active' && !empty($status['current'])) {
        return ['ok' => true, 'message' => 'Slot code active', 'data' => $status['current']];
    }

    // Invitation périmée avec un ID connu → suppression avant recréation
    if ($status['state'] === 'stale' && !empty($status['current']['id'])) {
        $delete = wizarrinvite_delete_invite($cfg, $status['current']['id']);

        if (!$delete['ok']) {
            return [
                'ok'      => false,
                'message' => 'Unable to delete old slot code before recreation',
                'data'    => ['status' => $status, 'delete' => $delete],
            ];
        }

        wizarrinvite_clear_slot_cache($slotId);
    }

    return wizarrinvite_create_slot_invite($cfg, $slotConfig);
}

/**
 * Crée une nouvelle invitation Wizarr pour un slot donné.
 *
 * Vérifie la capacité via server_count : total / server_count = effectif.
 * Si l'effectif >= max_users, bloque la création.
 *
 * @param  array $cfg
 * @param  array $slotConfig
 * @return array{ok: bool, message: string, data: mixed}
 */
function wizarrinvite_create_slot_invite($cfg, $slotConfig)
{
    if (!wizarrinvite_plugin_enabled($cfg)) {
        return ['ok' => false, 'message' => 'Plugin disabled', 'data' => null];
    }

    $baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($cfg['WIZARRINVITE-api-key'] ?? '');

    if (!$baseUrl || !$apiKey) {
        return ['ok' => false, 'message' => 'Configuration missing', 'data' => null];
    }

    $slotId      = (int)($slotConfig['id'] ?? 0);
    $maxUsers    = max(1, (int)($slotConfig['max_users']    ?? 100));
    $serverCount = max(1, (int)($slotConfig['server_count'] ?? 1));

    // ── Vérification de la capacité (/users puis /status en fallback) ────────
    $slotServerIds  = wizarrinvite_parse_int_csv($slotConfig['server_ids'] ?? '');
    $usersResult    = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'users', null, 8);
    $effectiveCount = null;

    if ((int)$usersResult['http'] === 200) {
        $allUsers = is_array($usersResult['body']['users'] ?? null) ? $usersResult['body']['users'] : [];

        // Filtrage par serveur + déduplication si server_ids configurés
        if (!empty($slotServerIds)) {
            $serversResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'servers', null, 5);
            $allServers    = is_array($serversResult['body']['servers'] ?? null) ? $serversResult['body']['servers'] : [];
            $serverNames   = wizarrinvite_resolve_server_names($allServers, $slotServerIds);

            if (!empty($serverNames)) {
                $filtered       = wizarrinvite_filter_users_for_servers($allUsers, $serverNames);
                $effectiveCount = $filtered['count'];
            }
        }

        if ($effectiveCount === null) {
            $effectiveCount = (int)ceil(count($allUsers) / $serverCount);
        }
    } else {
        // Repli sur /status
        $statusResult = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'status');
        if ((int)$statusResult['http'] === 200) {
            $totalCount     = (int)($statusResult['body']['users'] ?? 0);
            $effectiveCount = (int)ceil($totalCount / $serverCount);
        }
    }

    if ($effectiveCount !== null && $effectiveCount >= $maxUsers) {
        return [
            'ok'      => false,
            'message' => 'User limit reached',
            'data'    => [
                'limit_reached' => true,
                'count'         => $effectiveCount,
                'max'           => $maxUsers,
            ],
        ];
    }
    // Si les deux API échouent, on continue (comportement conservateur)

    // ── Création de l'invitation ──────────────────────────────────────────────
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
