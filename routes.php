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
    $result = wizarrinvite_user_stats($plugin->config);

    $GLOBALS['api']['response']['result']  = $result['ok'] ? 'success' : 'error';
    $GLOBALS['api']['response']['message'] = $result['message'];
    $GLOBALS['api']['response']['data']    = $result['data'];

    $response->getBody()->write(jsonE($GLOBALS['api']));
    return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

// ── Récupère (ou crée) l'invitation d'un slot automatique ────────────────────
$app->get('/plugins/wizarrinvite/current/{slotId}', function ($request, $response, $args) {
    $plugin     = new WizarrInvite();
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

