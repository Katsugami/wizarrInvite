<?php

function wizarrinvite_plugin_enabled($cfg)
{
	return !empty($cfg['WIZARRINVITE-enabled']);
}

function wizarrinvite_parse_int_csv($raw)
{
	return array_values(array_filter(array_map('intval', array_map('trim', explode(',', (string)$raw)))));
}

function wizarrinvite_cache_dir()
{
	return dirname(__DIR__, 3) . '/data/cache';
}

function wizarrinvite_cache_file()
{
	return wizarrinvite_cache_dir() . '/wizarrinvite_current.json';
}

function wizarrinvite_ensure_cache_dir()
{
	if (!is_dir(wizarrinvite_cache_dir())) {
		@mkdir(wizarrinvite_cache_dir(), 0775, true);
	}
}

function wizarrinvite_load_cache()
{
	$file = wizarrinvite_cache_file();

	if (!file_exists($file)) {
		return null;
	}

	$json = @file_get_contents($file);
	$data = json_decode($json, true);

	return is_array($data) ? $data : null;
}

function wizarrinvite_save_cache($data)
{
	wizarrinvite_ensure_cache_dir();

	@file_put_contents(
		wizarrinvite_cache_file(),
		json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
	);
}

function wizarrinvite_clear_cache()
{
	$file = wizarrinvite_cache_file();

	if (file_exists($file)) {
		@unlink($file);
	}
}

function wizarrinvite_request($baseUrl, $apiKey, $method, $endpoint, $payload = null)
{
	$url = rtrim($baseUrl, '/') . '/api/' . ltrim($endpoint, '/');

	$headers = [
		'Accept: application/json',
		'Content-Type: application/json',
		'X-API-Key: ' . $apiKey
	];

	$ch = curl_init($url);

	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CUSTOMREQUEST => strtoupper($method),
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_TIMEOUT => 20
	]);

	if ($payload !== null) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	$body = curl_exec($ch);
	$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curlError = curl_error($ch);

	curl_close($ch);

	$decoded = json_decode($body, true);

	return [
		'http' => $http,
		'body' => $decoded !== null ? $decoded : $body,
		'curl_error' => $curlError
	];
}

function wizarrinvite_expiration_value($value)
{
	$value = (string)$value;

	if ($value === 'never') {
		return null;
	}

	if (in_array($value, ['1', '7', '30'], true)) {
		return (int)$value;
	}

	return 1;
}

function wizarrinvite_mode_cfg($cfg, $mode)
{
	if ($mode === 'manual') {
		return [
			'expiration' => $cfg['WIZARRINVITE-manual-expiration'] ?? '1',
			'access_days' => $cfg['WIZARRINVITE-manual-access-days'] ?? '7',
			'server_ids' => $cfg['WIZARRINVITE-manual-server-ids'] ?? '1,2',
			'library_ids' => $cfg['WIZARRINVITE-manual-library-ids'] ?? '4,5,7,9,10',
			'allow_downloads' => !empty($cfg['WIZARRINVITE-manual-allow-downloads']),
			'allow_live_tv' => !empty($cfg['WIZARRINVITE-manual-allow-live-tv']),
			'allow_mobile_uploads' => !empty($cfg['WIZARRINVITE-manual-allow-mobile-uploads']),
			'invite_to_plex_home' => !empty($cfg['WIZARRINVITE-manual-invite-to-plex-home'])
		];
	}

	return [
		'expiration' => $cfg['WIZARRINVITE-auto-expiration'] ?? '1',
		'access_days' => $cfg['WIZARRINVITE-auto-access-days'] ?? '7',
		'server_ids' => $cfg['WIZARRINVITE-auto-server-ids'] ?? '1,2',
		'library_ids' => $cfg['WIZARRINVITE-auto-library-ids'] ?? '4,5,7,9,10',
		'allow_downloads' => !empty($cfg['WIZARRINVITE-auto-allow-downloads']),
		'allow_live_tv' => !empty($cfg['WIZARRINVITE-auto-allow-live-tv']),
		'allow_mobile_uploads' => !empty($cfg['WIZARRINVITE-auto-allow-mobile-uploads']),
		'invite_to_plex_home' => false
	];
}

function wizarrinvite_normalize_payload($payload)
{
	$normalized = is_array($payload) ? $payload : [];

	if (isset($normalized['server_ids']) && is_array($normalized['server_ids'])) {
		$normalized['server_ids'] = array_values(array_map('intval', $normalized['server_ids']));
		sort($normalized['server_ids']);
	}

	if (isset($normalized['library_ids']) && is_array($normalized['library_ids'])) {
		$normalized['library_ids'] = array_values(array_map('intval', $normalized['library_ids']));
		sort($normalized['library_ids']);
	}

	if (array_key_exists('expires_in_days', $normalized)) {
		$normalized['expires_in_days'] = $normalized['expires_in_days'] === null ? null : (int)$normalized['expires_in_days'];
	}

	if (array_key_exists('duration', $normalized)) {
		$normalized['duration'] = (string)$normalized['duration'];
	}

	foreach (['unlimited', 'allow_downloads', 'allow_live_tv', 'allow_mobile_uploads', 'invite_to_plex_home'] as $boolKey) {
		if (array_key_exists($boolKey, $normalized)) {
			$normalized[$boolKey] = !empty($normalized[$boolKey]);
		}
	}

	ksort($normalized);

	return $normalized;
}

function wizarrinvite_payload_signature($payload)
{
	return sha1(json_encode(wizarrinvite_normalize_payload($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function wizarrinvite_build_payload($cfg, $mode)
{
	$modeCfg = wizarrinvite_mode_cfg($cfg, $mode);

	$payload = [
		'server_ids' => wizarrinvite_parse_int_csv($modeCfg['server_ids']),
		'expires_in_days' => wizarrinvite_expiration_value($modeCfg['expiration']),
		'duration' => (string)$modeCfg['access_days'],
		'unlimited' => false,
		'allow_downloads' => $modeCfg['allow_downloads'],
		'allow_live_tv' => $modeCfg['allow_live_tv'],
		'allow_mobile_uploads' => $modeCfg['allow_mobile_uploads']
	];

	$libraryIds = wizarrinvite_parse_int_csv($modeCfg['library_ids']);
	if (!empty($libraryIds)) {
		$payload['library_ids'] = $libraryIds;
	}

	if ($mode === 'manual' && $modeCfg['invite_to_plex_home']) {
		$payload['invite_to_plex_home'] = true;
	}

	return wizarrinvite_normalize_payload($payload);
}

function wizarrinvite_build_public_url($cfg, $invitation)
{
	$public = trim($cfg['WIZARRINVITE-public-url'] ?? '');
	$code = $invitation['code'] ?? '';

	if ($public !== '' && $code !== '') {
		return rtrim($public, '/') . '/j/' . rawurlencode($code);
	}

	if (!empty($invitation['url'])) {
		return $invitation['url'];
	}

	$localBase = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
	if ($localBase !== '' && $code !== '') {
		return $localBase . '/j/' . rawurlencode($code);
	}

	return '';
}

function wizarrinvite_delete_invite($cfg, $inviteId)
{
	$baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
	$apiKey = trim($cfg['WIZARRINVITE-api-key'] ?? '');

	if (!$baseUrl || !$apiKey) {
		return [
			'ok' => false,
			'message' => 'Configuration missing',
			'data' => null
		];
	}

	if (!$inviteId) {
		return [
			'ok' => false,
			'message' => 'Missing invite id',
			'data' => null
		];
	}

	$result = wizarrinvite_request($baseUrl, $apiKey, 'DELETE', 'invitations/' . rawurlencode((string)$inviteId));
	$ok = in_array((int)$result['http'], [200, 202, 204], true);

	return [
		'ok' => $ok,
		'message' => $ok
			? 'Invite deleted'
			: ($result['http'] ? ('HTTP error ' . $result['http']) : ('Network error: ' . $result['curl_error'])),
		'data' => $result['body']
	];
}

function wizarrinvite_create_invite($cfg, $mode)
{
	if (!wizarrinvite_plugin_enabled($cfg)) {
		return [
			'ok' => false,
			'message' => 'Plugin disabled',
			'data' => null
		];
	}

	$baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
	$apiKey = trim($cfg['WIZARRINVITE-api-key'] ?? '');

	if (!$baseUrl || !$apiKey) {
		return [
			'ok' => false,
			'message' => 'Configuration missing',
			'data' => null
		];
	}

	$payload = wizarrinvite_build_payload($cfg, $mode);
	$result = wizarrinvite_request($baseUrl, $apiKey, 'POST', 'invitations', $payload);

	if ((int)$result['http'] !== 201) {
		return [
			'ok' => false,
			'message' => $result['http'] ? ('HTTP error ' . $result['http']) : ('Network error: ' . $result['curl_error']),
			'data' => [
				'payload' => $payload,
				'response' => $result['body']
			]
		];
	}

	$inv = $result['body']['invitation'] ?? null;

	if (!$inv || empty($inv['id']) || empty($inv['code'])) {
		return [
			'ok' => false,
			'message' => 'Invalid Wizarr response',
			'data' => $result['body']
		];
	}

	$data = [
		'id' => $inv['id'],
		'code' => $inv['code'],
		'url' => wizarrinvite_build_public_url($cfg, $inv),
		'expires' => $inv['expires'] ?? null,
		'status' => $inv['status'] ?? 'pending',
		'created_at_ts' => time(),
		'invite_mode' => $mode,
		'payload' => $payload,
		'config_signature' => wizarrinvite_payload_signature($payload),
		'access_days' => $payload['duration'] ?? null
	];

	if ($mode === 'auto') {
		wizarrinvite_save_cache($data);
	}

	return [
		'ok' => true,
		'message' => $mode === 'manual' ? 'Manual invite created' : 'Automatic invite created',
		'data' => $data
	];
}

function wizarrinvite_find_cached_invite_in_wizarr($cfg, $cache)
{
	if (!$cache || (empty($cache['id']) && empty($cache['code']))) {
		return null;
	}

	$baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
	$apiKey = trim($cfg['WIZARRINVITE-api-key'] ?? '');

	if (!$baseUrl || !$apiKey) {
		return null;
	}

	$list = wizarrinvite_request($baseUrl, $apiKey, 'GET', 'invitations');

	if ((int)$list['http'] !== 200 || empty($list['body']['invitations']) || !is_array($list['body']['invitations'])) {
		return null;
	}

	foreach ($list['body']['invitations'] as $inv) {
		if (!is_array($inv)) {
			continue;
		}

		if (!empty($cache['id']) && !empty($inv['id']) && (int)$inv['id'] === (int)$cache['id']) {
			return $inv;
		}

		if (!empty($cache['code']) && !empty($inv['code']) && (string)$inv['code'] === (string)$cache['code']) {
			return $inv;
		}
	}

	return null;
}

function wizarrinvite_auto_status($cfg)
{
	if (!wizarrinvite_plugin_enabled($cfg)) {
		return [
			'state' => 'disabled',
			'message' => 'Plugin disabled',
			'current' => null,
			'cache_file' => wizarrinvite_cache_file()
		];
	}

	if (empty($cfg['WIZARRINVITE-auto-enabled'])) {
		return [
			'state' => 'disabled',
			'message' => 'Automatic mode disabled',
			'current' => null,
			'cache_file' => wizarrinvite_cache_file()
		];
	}

	$cache = wizarrinvite_load_cache();
	$desiredPayload = wizarrinvite_build_payload($cfg, 'auto');
	$desiredSignature = wizarrinvite_payload_signature($desiredPayload);

	if (!$cache) {
		return [
			'state' => 'missing',
			'message' => 'No automatic code in cache',
			'current' => null,
			'cache_file' => wizarrinvite_cache_file()
		];
	}

	$found = wizarrinvite_find_cached_invite_in_wizarr($cfg, $cache);

	if (!$found) {
		return [
			'state' => 'missing',
			'message' => 'Automatic code missing from Wizarr',
			'current' => $cache,
			'cache_file' => wizarrinvite_cache_file()
		];
	}

	$status = $found['status'] ?? 'pending';

	if ($status === 'used' || $status === 'expired') {
		return [
			'state' => 'missing',
			'message' => 'Automatic code is ' . $status,
			'current' => $cache,
			'cache_file' => wizarrinvite_cache_file()
		];
	}

	$current = [
		'id' => $found['id'] ?? $cache['id'],
		'code' => $found['code'] ?? $cache['code'],
		'url' => wizarrinvite_build_public_url($cfg, $found),
		'expires' => $found['expires'] ?? ($cache['expires'] ?? null),
		'status' => $status,
		'invite_mode' => 'auto',
		'payload' => $cache['payload'] ?? null,
		'config_signature' => $cache['config_signature'] ?? null,
		'access_days' => $desiredPayload['duration'] ?? ($cache['access_days'] ?? null)
	];

	if (empty($cache['config_signature']) || empty($cache['payload'])) {
		$current['payload'] = $cache['payload'] ?? null;
		return [
			'state' => 'stale',
			'message' => 'Automatic code cache format outdated',
			'current' => $current,
			'cache_file' => wizarrinvite_cache_file()
		];
	}

	if ((string)$cache['config_signature'] !== (string)$desiredSignature) {
		$current['payload'] = $cache['payload'];
		$current['desired_payload'] = $desiredPayload;
		return [
			'state' => 'stale',
			'message' => 'Automatic code parameters changed',
			'current' => $current,
			'cache_file' => wizarrinvite_cache_file()
		];
	}

	$current['payload'] = $desiredPayload;
	$current['config_signature'] = $desiredSignature;
	wizarrinvite_save_cache($current);

	return [
		'state' => 'active',
		'message' => 'Automatic code active',
		'current' => $current,
		'cache_file' => wizarrinvite_cache_file()
	];
}

function wizarrinvite_get_or_create_auto($cfg)
{
	if (!wizarrinvite_plugin_enabled($cfg)) {
		return [
			'ok' => false,
			'message' => 'Plugin disabled',
			'data' => null
		];
	}

	if (empty($cfg['WIZARRINVITE-auto-enabled'])) {
		return [
			'ok' => false,
			'message' => 'Automatic mode is disabled',
			'data' => null
		];
	}

	$status = wizarrinvite_auto_status($cfg);

	if ($status['state'] === 'active' && !empty($status['current'])) {
		return [
			'ok' => true,
			'message' => 'Automatic code active',
			'data' => $status['current']
		];
	}

	if ($status['state'] === 'stale' && !empty($status['current']['id'])) {
		$deleteResult = wizarrinvite_delete_invite($cfg, $status['current']['id']);

		if (!$deleteResult['ok']) {
			return [
				'ok' => false,
				'message' => 'Unable to delete old automatic code before recreation',
				'data' => [
					'status' => $status,
					'delete' => $deleteResult
				]
			];
		}

		wizarrinvite_clear_cache();
	}

	return wizarrinvite_create_invite($cfg, 'auto');
}

function wizarrinvite_resolve_display_file($cfg)
{
	$baseDir = __DIR__;
	$customFile = trim((string)($cfg['WIZARRINVITE-display-custom-file'] ?? ''));

	if ($customFile === '') {
		return null;
	}

	$customFile = basename($customFile);

	if (substr($customFile, -4) !== '.php') {
		$customFile .= '.php';
	}

	$file = $baseDir . '/' . $customFile;

	if (!file_exists($file)) {
		return null;
	}

	return $file;
}

$app->get('/plugins/wizarrinvite/settings', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();

	if ($WizarrInvite->checkRoute($request) && $WizarrInvite->qualifyRequest(1, true)) {
		$GLOBALS['api']['response']['data'] = $WizarrInvite->wizarrInviteGetSettings();
	}

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response->withHeader('Content-Type', 'application/json;charset=UTF-8')->withStatus($GLOBALS['responseCode']);
});

$app->get('/plugins/wizarrinvite/test', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();
	$baseUrl = rtrim($WizarrInvite->config['WIZARRINVITE-url'] ?? '', '/');
	$key = trim($WizarrInvite->config['WIZARRINVITE-api-key'] ?? '');

	if (!$baseUrl || !$key) {
		$GLOBALS['api']['response']['result'] = 'error';
		$GLOBALS['api']['response']['message'] = 'Configuration missing';
	} else {
		$result = wizarrinvite_request($baseUrl, $key, 'GET', 'invitations');
		$GLOBALS['api']['response']['result'] = ((int)$result['http'] === 200) ? 'success' : 'error';
		$GLOBALS['api']['response']['message'] = ((int)$result['http'] === 200)
			? 'Connection OK with X-API-Key on /api/invitations'
			: ($result['http'] ? ('HTTP error ' . $result['http']) : ('Network error: ' . $result['curl_error']));
		$GLOBALS['api']['response']['data'] = $result;
	}

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/servers', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();
	$result = wizarrinvite_request(
		rtrim($WizarrInvite->config['WIZARRINVITE-url'] ?? '', '/'),
		trim($WizarrInvite->config['WIZARRINVITE-api-key'] ?? ''),
		'GET',
		'servers'
	);

	$GLOBALS['api']['response']['result'] = ((int)$result['http'] === 200) ? 'success' : 'error';
	$GLOBALS['api']['response']['message'] = ((int)$result['http'] === 200) ? 'Servers loaded' : ($result['http'] ? ('HTTP error ' . $result['http']) : ('Network error: ' . $result['curl_error']));
	$GLOBALS['api']['response']['data'] = $result['body'];

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/libraries', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();
	$result = wizarrinvite_request(
		rtrim($WizarrInvite->config['WIZARRINVITE-url'] ?? '', '/'),
		trim($WizarrInvite->config['WIZARRINVITE-api-key'] ?? ''),
		'GET',
		'libraries'
	);

	$GLOBALS['api']['response']['result'] = ((int)$result['http'] === 200) ? 'success' : 'error';
	$GLOBALS['api']['response']['message'] = ((int)$result['http'] === 200) ? 'Libraries loaded' : ($result['http'] ? ('HTTP error ' . $result['http']) : ('Network error: ' . $result['curl_error']));
	$GLOBALS['api']['response']['data'] = $result['body'];

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/create-manual', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();
	$result = wizarrinvite_create_invite($WizarrInvite->config, 'manual');

	$GLOBALS['api']['response']['result'] = $result['ok'] ? 'success' : 'error';
	$GLOBALS['api']['response']['message'] = $result['message'];
	$GLOBALS['api']['response']['data'] = $result['data'];

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/auto-status', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();
	$status = wizarrinvite_auto_status($WizarrInvite->config);

	$GLOBALS['api']['response']['result'] = 'success';
	$GLOBALS['api']['response']['message'] = 'Status loaded';
	$GLOBALS['api']['response']['data'] = $status;

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/current', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();
	$result = wizarrinvite_get_or_create_auto($WizarrInvite->config);

	$GLOBALS['api']['response']['result'] = $result['ok'] ? 'success' : 'error';
	$GLOBALS['api']['response']['message'] = $result['message'];
	$GLOBALS['api']['response']['data'] = $result['data'];

	$response->getBody()->write(jsonE($GLOBALS['api']));
	return $response->withHeader('Content-Type', 'application/json;charset=UTF-8');
});

$app->get('/plugins/wizarrinvite/display', function ($request, $response, $args) {
	$WizarrInvite = new WizarrInvite();
	$cfg = $WizarrInvite->config;
	$minGroup = (int)($cfg['WIZARRINVITE-min-group'] ?? 1);

	if (!wizarrinvite_plugin_enabled($cfg)) {
		$response->getBody()->write('Plugin disabled');
		return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(403);
	}

	if (!$WizarrInvite->checkRoute($request) || !$WizarrInvite->qualifyRequest($minGroup, true)) {
		$response->getBody()->write('Access denied');
		return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(403);
	}

	$file = wizarrinvite_resolve_display_file($cfg);

	if (!$file || !file_exists($file)) {
		$response->getBody()->write('Display file not found: ' . ($file ?: 'undefined'));
		return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(500);
	}

	ob_start();
	include $file;
	$html = ob_get_clean();

	$response->getBody()->write($html);
	return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
});
