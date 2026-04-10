<?php

// ─────────────────────────────────────────────────────────────────────────────
// Routes Slim du plugin WizarrInvite
// Ce fichier est inclus depuis api.php dans le contexte où $app existe déjà.
// ─────────────────────────────────────────────────────────────────────────────

// ── Paramètres du plugin (page de configuration Organizr) ────────────────────
$app->get('/plugins/wizarrinvite/settings', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if ($plugin->checkRoute($request) && $plugin->qualifyRequest(1, true)) {
        $GLOBALS['api']['response']['data'] = $plugin->wizarrInviteGetSettings();
    }

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8')
                    ->withStatus($GLOBALS['responseCode']);
});

// ── Test de connexion à Wizarr ────────────────────────────────────────────────
$app->get('/plugins/wizarrinvite/test', function ($request, $response, $args) {
    $plugin  = new WizarrInvite();
    $baseUrl = rtrim($plugin->config['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($plugin->config['WIZARRINVITE-api-key'] ?? '');

    if (!$baseUrl || !$apiKey) {
        $GLOBALS['api']['response']['result']  = 'error';
        $GLOBALS['api']['response']['message'] = 'Configuration missing';
    } else {
        $result = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'invitations');
        $ok     = (int)$result['http'] === 200;

        $GLOBALS['api']['response']['result']  = $ok ? 'success' : 'error';
        $GLOBALS['api']['response']['message'] = $ok
            ? 'Connection OK'
            : ($result['http'] ? 'HTTP error ' . $result['http'] : 'Network error: ' . $result['curl_error']);
        $GLOBALS['api']['response']['data'] = $result;
    }

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Liste des serveurs Wizarr ─────────────────────────────────────────────────
$app->get('/plugins/wizarrinvite/servers', function ($request, $response, $args) {
    $plugin = new WizarrInvite();
    $result = wizarrinvite_request(
        rtrim($plugin->config['WIZARRINVITE-url'] ?? '', '/'),
        trim($plugin->config['WIZARRINVITE-api-key'] ?? ''),
        'GET',
        'servers'
    );

    $ok = (int)$result['http'] === 200;

    $GLOBALS['api']['response']['result']  = $ok ? 'success' : 'error';
    $GLOBALS['api']['response']['message'] = $ok
        ? 'Servers loaded'
        : ($result['http'] ? 'HTTP error ' . $result['http'] : 'Network error: ' . $result['curl_error']);
    $GLOBALS['api']['response']['data'] = $result['body'];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Liste des bibliothèques Wizarr ────────────────────────────────────────────
$app->get('/plugins/wizarrinvite/libraries', function ($request, $response, $args) {
    $plugin = new WizarrInvite();
    $result = wizarrinvite_request(
        rtrim($plugin->config['WIZARRINVITE-url'] ?? '', '/'),
        trim($plugin->config['WIZARRINVITE-api-key'] ?? ''),
        'GET',
        'libraries'
    );

    $ok = (int)$result['http'] === 200;

    $GLOBALS['api']['response']['result']  = $ok ? 'success' : 'error';
    $GLOBALS['api']['response']['message'] = $ok
        ? 'Libraries loaded'
        : ($result['http'] ? 'HTTP error ' . $result['http'] : 'Network error: ' . $result['curl_error']);
    $GLOBALS['api']['response']['data'] = $result['body'];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Création d'une invitation manuelle ────────────────────────────────────────
$app->get('/plugins/wizarrinvite/create-manual', function ($request, $response, $args) {
    $plugin = new WizarrInvite();
    $result = wizarrinvite_create_invite($plugin->config, 'manual');

    if ($result['ok']) {
        wizarrinvite_log('info', 'Manual invite created', ['code' => $result['data']['code'] ?? '?']);
    } else {
        wizarrinvite_log('warn', 'Manual invite failed: ' . $result['message']);
    }

    $GLOBALS['api']['response']['result']  = $result['ok'] ? 'success' : 'error';
    $GLOBALS['api']['response']['message'] = $result['message'];
    $GLOBALS['api']['response']['data']    = $result['data'];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Statut de l'invitation automatique ───────────────────────────────────────
$app->get('/plugins/wizarrinvite/auto-status', function ($request, $response, $args) {
    $plugin = new WizarrInvite();
    $status = wizarrinvite_auto_status($plugin->config);

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = 'Status loaded';
    $GLOBALS['api']['response']['data']    = $status;

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Récupère (ou crée) l'invitation automatique active ───────────────────────
$app->get('/plugins/wizarrinvite/current', function ($request, $response, $args) {
    $plugin = new WizarrInvite();
    $result = wizarrinvite_get_or_create_auto($plugin->config);

    $GLOBALS['api']['response']['result']  = $result['ok'] ? 'success' : 'error';
    $GLOBALS['api']['response']['message'] = $result['message'];
    $GLOBALS['api']['response']['data']    = $result['data'];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Fichiers JS statiques du plugin (ex. slots.js) ───────────────────────────
$app->get('/plugins/wizarrinvite/js/{name}', function ($request, $response, $args) {
    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', basename($args['name'] ?? ''));
    $path = __DIR__ . '/includes/js/' . $name . '.js';

    if ($name && file_exists($path)) {
        $response->getBody()->write(file_get_contents($path));
        return $response->withHeader('Content-Type', 'application/javascript; charset=UTF-8');
    }

    return $response->withStatus(404);
});

// ── Liste des bundles Wizarr ──────────────────────────────────────────────────
$app->get('/plugins/wizarrinvite/bundles', function ($request, $response, $args) {
    $plugin  = new WizarrInvite();
    $baseUrl = rtrim($plugin->config['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($plugin->config['WIZARRINVITE-api-key'] ?? '');

    $result = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'bundles');

    if ((int)$result['http'] === 200) {
        $GLOBALS['api']['response']['result']  = 'success';
        $GLOBALS['api']['response']['message'] = 'Bundles loaded';
        $GLOBALS['api']['response']['data']    = $result['body'];
    } else {
        $GLOBALS['api']['response']['result']  = 'error';
        $GLOBALS['api']['response']['message'] = 'Bundles not available (HTTP ' . ((int)$result['http'] ?: 0) . ')';
        $GLOBALS['api']['response']['data']    = null;
    }

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Statistiques des utilisateurs (compteur + prochaine expiration) ──────────
$app->get('/plugins/wizarrinvite/user-stats', function ($request, $response, $args) {
    $plugin = new WizarrInvite();
    // Release session lock before slow Wizarr/Plex API calls so Organizr stays responsive
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

    $trigger = trim($request->getQueryParams()['trigger'] ?? 'api');
    $result  = wizarrinvite_user_stats($plugin->config, $trigger);

    $data = $result['data'] ?? [];
    if (is_array($data)) {
        $data['last_trigger']  = $trigger;
        $data['last_check_at'] = time();
    }

    $GLOBALS['api']['response']['result']  = $result['ok'] ? 'success' : 'error';
    $GLOBALS['api']['response']['message'] = $result['message'];
    $GLOBALS['api']['response']['data']    = $data;

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Récupère (ou crée) l'invitation d'un slot automatique ────────────────────
$app->get('/plugins/wizarrinvite/current/{slotId}', function ($request, $response, $args) {
    $plugin     = new WizarrInvite();
    // Release session lock before slow Wizarr/Plex API calls so Organizr stays responsive
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    $slotId     = (int)($args['slotId'] ?? 0);
    $slotConfig = wizarrinvite_get_slot_by_id($plugin->config, $slotId);

    if (!$slotConfig) {
        wizarrinvite_log('warn', 'Slot not found', ['slotId' => $slotId]);
        $GLOBALS['api']['response']['result']  = 'error';
        $GLOBALS['api']['response']['message'] = 'Slot not found';
        $GLOBALS['api']['response']['data']    = null;
    } else {
        $result = wizarrinvite_get_or_create_slot($plugin->config, $slotConfig);
        if ($result['ok']) {
            wizarrinvite_log('info', 'Slot #' . $slotId . ' invite ready', [
                'code'   => $result['data']['code'] ?? '?',
                'cached' => ($result['message'] === 'Slot code active'),
            ]);
        } else {
            wizarrinvite_log('warn', 'Slot #' . $slotId . ' failed: ' . $result['message']);
        }
        $GLOBALS['api']['response']['result']  = $result['ok'] ? 'success' : 'error';
        $GLOBALS['api']['response']['message'] = $result['message'];
        $GLOBALS['api']['response']['data']    = $result['data'];
    }

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Statut d'un slot (pour l'interface de configuration) ─────────────────────
$app->get('/plugins/wizarrinvite/slot-status/{slotId}', function ($request, $response, $args) {
    $plugin     = new WizarrInvite();
    $slotId     = (int)($args['slotId'] ?? 0);
    $slotConfig = wizarrinvite_get_slot_by_id($plugin->config, $slotId);

    if (!$slotConfig) {
        $GLOBALS['api']['response']['result']  = 'error';
        $GLOBALS['api']['response']['message'] = 'Slot not found';
        $GLOBALS['api']['response']['data']    = null;
    } else {
        $status = wizarrinvite_slot_status($plugin->config, $slotConfig);
        $GLOBALS['api']['response']['result']  = 'success';
        $GLOBALS['api']['response']['message'] = 'Slot status loaded';
        $GLOBALS['api']['response']['data']    = $status;
    }

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Statistiques utilisateurs d'un slot (limite + groupes de serveurs) ────────
$app->get('/plugins/wizarrinvite/slot-user-stats/{slotId}', function ($request, $response, $args) {
    $plugin     = new WizarrInvite();
    // Release session lock before slow Wizarr/Plex API calls so Organizr stays responsive
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    $slotId     = (int)($args['slotId'] ?? 0);
    $slotConfig = wizarrinvite_get_slot_by_id($plugin->config, $slotId);

    if (!$slotConfig) {
        $GLOBALS['api']['response']['result']  = 'error';
        $GLOBALS['api']['response']['message'] = 'Slot not found';
        $GLOBALS['api']['response']['data']    = null;
    } else {
        $result = wizarrinvite_slot_user_stats($plugin->config, $slotConfig);
        $GLOBALS['api']['response']['result']  = $result['ok'] ? 'success' : 'error';
        $GLOBALS['api']['response']['message'] = $result['message'];
        $GLOBALS['api']['response']['data']    = $result['data'];
    }

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Page d'affichage publique d'un slot automatique ───────────────────────────
$app->get('/plugins/wizarrinvite/display/{slotId}', function ($request, $response, $args) {
    $plugin     = new WizarrInvite();
    $cfg        = $plugin->config;
    $slotId     = (int)($args['slotId'] ?? 0);
    $slotConfig = wizarrinvite_get_slot_by_id($cfg, $slotId);

    if (!$slotConfig) {
        $response->getBody()->write('⚠️ Slot not found');
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(404);
    }

    if (!wizarrinvite_plugin_enabled($cfg)) {
        $response->getBody()->write('🔒 Plugin disabled');
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(403);
    }

    // Chaque slot peut avoir son propre groupe minimum, sinon on utilise le défaut global
    $minGroup = (int)($slotConfig['min_group'] ?? $cfg['WIZARRINVITE-min-group'] ?? 1);

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest($minGroup, true)) {
        $response->getBody()->write('🔒 Access denied');
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(403);
    }

    $file = wizarrinvite_resolve_display_file($cfg);

    if (!$file) {
        $response->getBody()->write('❌ Display file not found');
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(500);
    }

    ob_start();
    include $file;
    $html = ob_get_clean();

    // Redirige le fetch JS vers les endpoints du slot (str_replace sur les chaînes exactes)
    $html = str_replace(
        '"/api/v2/plugins/wizarrinvite/current"',
        '"/api/v2/plugins/wizarrinvite/current/' . $slotId . '"',
        $html
    );
    $html = str_replace(
        '"/api/v2/plugins/wizarrinvite/user-stats"',
        '"/api/v2/plugins/wizarrinvite/slot-user-stats/' . $slotId . '"',
        $html
    );

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
});

// ── Page d'affichage publique du plugin (sans ID — désactivée) ───────────────
$app->get('/plugins/wizarrinvite/display', function ($request, $response, $args) {
    $response->getBody()->write('⚠️ Missing slot ID. Use /display/1, /display/2, etc.');
    return $response->withHeader('Content-Type', 'text/plain; charset=UTF-8')->withStatus(400);
});

// ── Logs de debug ─────────────────────────────────────────────────────────────
$app->get('/plugins/wizarrinvite/debug/logs', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    $lines = wizarrinvite_log_read(200);

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = count($lines) . ' line(s)';
    $GLOBALS['api']['response']['data']    = [
        'lines' => $lines,
        'path'  => wizarrinvite_log_path(),
    ];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/debug/clear', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    wizarrinvite_log_clear();

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = 'Logs cleared';
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Affiche les données en cache (sans appel API) ─────────────────────────────
$app->get('/plugins/wizarrinvite/cached-user-stats', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    $cached = wizarrinvite_load_users_cache();

    if (!$cached || empty($cached['users'])) {
        $GLOBALS['api']['response']['result']  = 'error';
        $GLOBALS['api']['response']['message'] = 'No users cache — run Check Users first';
        $GLOBALS['api']['response']['data']    = null;
        $response->getBody()->write(jsonE($GLOBALS['api']));
        return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
    }

    $allUsers = $cached['users'];

    // Inject Plex Home virtual users
    $plexHomeUsers = wizarrinvite_build_virtual_plex_home_users();
    if (!empty($plexHomeUsers)) {
        $allUsers = array_merge($allUsers, $plexHomeUsers);
    }

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

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = 'Cached user stats loaded';
    $GLOBALS['api']['response']['data']    = [
        'count'           => count($allUsers),
        'unique_count'    => $dedup['count'],
        'next_expiry'     => wizarrinvite_find_next_expiry($allUsers),
        'per_server'      => $perServer,
        'users_by_server' => $usersByServer,
        'groups'          => $dedup['groups'],
        'ambiguous'       => $dedup['ambiguous'],
        'from_cache'      => true,
        'cache_age_s'     => (int)(time() - (int)($cached['updated_at'] ?? time())),
        'last_trigger'    => $cached['last_trigger']  ?? 'api',
        'last_check_at'   => $cached['last_check_at'] ?? ($cached['updated_at'] ?? null),
    ];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Vide le cache du tableau users brut ──────────────────────────────────────
$app->get('/plugins/wizarrinvite/clear-users-cache', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    wizarrinvite_clear_users_cache();

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = 'Users cache cleared';
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Statut du cache des compteurs (pour la mise à jour dynamique) ────────────
$app->get('/plugins/wizarrinvite/cache-status', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    $countCacheData = [];
    $countCacheFile = dirname(__DIR__, 4) . '/data/cache/wizarrinvite_count_cache.json';
    if (file_exists($countCacheFile)) {
        $raw = @file_get_contents($countCacheFile);
        if ($raw) $countCacheData = json_decode($raw, true) ?? [];
    }

    $cacheHours  = max(0, (int)($plugin->config['WIZARRINVITE-count-cache-hours']   ?? 24));
    $cacheMins   = max(0, (int)($plugin->config['WIZARRINVITE-count-cache-minutes'] ?? 0));
    $intervalSec = max(60, $cacheHours * 3600 + $cacheMins * 60);
    $slotsConfig = json_decode($plugin->config['WIZARRINVITE-slots-config'] ?? '[]', true) ?? [];
    $nowTs       = time();

    $slots = [];
    foreach ($slotsConfig as $slot) {
        $sid      = (int)($slot['id'] ?? 0);
        $deferred = !empty($slot['deferred_count']);
        $entry    = $countCacheData[(string)$sid] ?? null;

        $slots[] = [
            'id'         => $sid,
            'label'      => $slot['label'] ?? ('Slot ' . $sid),
            'deferred'   => $deferred,
            'count'      => $entry ? (int)($entry['count'] ?? 0) : null,
            'updated_at' => $entry ? (int)($entry['updated_at'] ?? 0) : null,
            'next_at'    => $entry ? ((int)($entry['updated_at'] ?? 0) + $intervalSec) : null,
        ];
    }

    // User cache info (for "last user check" display)
    $userCached = wizarrinvite_load_users_cache();

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = 'Cache status loaded';
    $GLOBALS['api']['response']['data']    = [
        'slots'              => $slots,
        'server_ts'          => $nowTs,
        'interval_sec'       => $intervalSec,
        'hours'              => $cacheHours,
        'minutes'            => $cacheMins,
        'last_user_check_at' => $userCached ? (int)($userCached['last_check_at'] ?? $userCached['updated_at'] ?? 0) : null,
        'last_user_trigger'  => $userCached ? ($userCached['last_trigger'] ?? 'api') : null,
    ];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Vide le cache du compteur d'utilisateurs (mode différé) ──────────────────
$app->get('/plugins/wizarrinvite/clear-count-cache', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    wizarrinvite_clear_count_cache();

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = 'Count cache cleared';
    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Rafraîchit le cache de comptage des slots différés expirés ────────────────
$app->get('/plugins/wizarrinvite/refresh-deferred-counts', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    // Release session lock before slow Wizarr/Plex API calls so Organizr stays responsive
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

    $cfg         = $plugin->config;
    $baseUrl     = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey      = trim($cfg['WIZARRINVITE-api-key'] ?? '');
    $cacheHours  = max(0, (int)($cfg['WIZARRINVITE-count-cache-hours']   ?? 24));
    $cacheMins   = max(0, (int)($cfg['WIZARRINVITE-count-cache-minutes'] ?? 0));
    $intervalSec = max(60, $cacheHours * 3600 + $cacheMins * 60);
    $slotsConfig = wizarrinvite_get_slots_config($cfg);
    $nowTs       = time();
    $refreshed   = [];
    $skipped     = [];

    foreach ($slotsConfig as $slot) {
        $slotId = (int)($slot['id'] ?? 0);
        if (empty($slot['deferred_count'])) {
            $skipped[] = $slotId;
            continue;
        }

        $cached       = wizarrinvite_get_slot_count_cache($slotId);
        $cacheAge     = $cached ? ($nowTs - (int)($cached['updated_at'] ?? 0)) : PHP_INT_MAX;
        $curServerIds = trim($slot['server_ids'] ?? '');
        $configMismatch = $cached && (($cached['server_ids'] ?? '') !== $curServerIds);

        $missingPerServer = $cached && !isset($cached['per_server_counts']);
        if ($configMismatch || ($cached === null) || ($cacheAge > $intervalSec) || $missingPerServer) {
            $reason    = $cached === null ? 'no cache' : ($configMismatch ? 'config changed' : 'cache expired');
            $liveStats = wizarrinvite_fetch_slot_effective_count($baseUrl, $apiKey, $slot);
            if ($liveStats !== null) {
                wizarrinvite_save_slot_count_cache($slotId, $liveStats['count'], $curServerIds, $liveStats['per_server_counts'], $liveStats['server_names']);
                wizarrinvite_log('info', 'Auto refresh — slot #' . $slotId . ' (' . $reason . '): ' . $liveStats['count'] . ' users');
                $refreshed[] = $slotId;
            } else {
                $skipped[] = $slotId;
            }
        } else {
            $skipped[] = $slotId;
        }
    }

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = count($refreshed) . ' slot(s) refreshed';
    $GLOBALS['api']['response']['data']    = ['refreshed' => $refreshed, 'skipped' => $skipped];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Gestion des utilisateurs Plex Home (lecture + écriture) ──────────────────
$app->get('/plugins/wizarrinvite/plex-home-users', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    $users = wizarrinvite_load_plex_home_users();
    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = count($users) . ' Plex Home user(s)';
    $GLOBALS['api']['response']['data']    = ['users' => $users];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/save-plex-home-users', function ($request, $response, $args) {
    $plugin = new WizarrInvite();

    if (!$plugin->checkRoute($request) || !$plugin->qualifyRequest(1, true)) {
        return $response->withStatus(403);
    }

    $queryParams = $request->getQueryParams();
    $usersJson   = $queryParams['users'] ?? '[]';
    $decoded     = json_decode($usersJson, true);
    $users       = is_array($decoded) ? $decoded : [];

    // Validate and sanitize
    $clean = [];
    foreach ($users as $u) {
        if (!is_array($u)) continue;
        $name = trim($u['name'] ?? '');
        if ($name === '') continue;
        $clean[] = [
            'id'      => trim($u['id']  ?? ''),
            'name'    => $name,
            'servers' => is_array($u['servers'] ?? null) ? array_values(array_filter(array_map('trim', $u['servers']))) : [],
        ];
    }

    // Re-assign IDs sequentially
    $n = 1;
    foreach ($clean as &$u) {
        $u['id'] = 'PH' . $n++;
    }
    unset($u);

    wizarrinvite_save_plex_home_users($clean);

    // Invalidate count cache so slots recount with updated PH users
    wizarrinvite_clear_count_cache();

    $GLOBALS['api']['response']['result']  = 'success';
    $GLOBALS['api']['response']['message'] = count($clean) . ' user(s) saved';
    $GLOBALS['api']['response']['data']    = ['users' => $clean];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

