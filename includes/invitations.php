<?php

// ─────────────────────────────────────────────────────────────────────────────
// Création, suppression et recherche d'invitations Wizarr
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Construit l'URL publique d'une invitation.
 * Priorité : URL publique configurée > URL retournée par Wizarr > URL locale.
 *
 * @param  array $cfg        Configuration du plugin
 * @param  array $invitation Données de l'invitation (doit contenir 'code' et/ou 'url')
 * @return string
 */
function wizarrinvite_build_public_url($cfg, $invitation)
{
    $code   = $invitation['code'] ?? '';
    $public = trim($cfg['WIZARRINVITE-public-url'] ?? '');

    // 1. URL publique personnalisée + code
    if ($public !== '' && $code !== '') {
        return rtrim($public, '/') . '/j/' . rawurlencode($code);
    }

    // 2. URL retournée directement par Wizarr
    if (!empty($invitation['url'])) {
        return $invitation['url'];
    }

    // 3. URL locale Wizarr + code (fallback)
    $local = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    if ($local !== '' && $code !== '') {
        return $local . '/j/' . rawurlencode($code);
    }

    return '';
}

/**
 * Supprime une invitation dans Wizarr via l'API REST.
 *
 * @param  array      $cfg       Configuration du plugin
 * @param  int|string $inviteId  ID de l'invitation à supprimer
 * @return array{ok: bool, message: string, data: mixed}
 */
function wizarrinvite_delete_invite($cfg, $inviteId)
{
    $baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($cfg['WIZARRINVITE-api-key'] ?? '');

    if (!$baseUrl || !$apiKey) {
        return ['ok' => false, 'message' => 'Configuration missing', 'data' => null];
    }

    if (!$inviteId) {
        return ['ok' => false, 'message' => 'Missing invite id', 'data' => null];
    }

    $result = wizarrinvite_request($baseUrl, $apiKey, 'DELETE', 'invitations/' . rawurlencode((string)$inviteId));
    // 404 / 410 = already gone from Wizarr → treat as success
    $ok     = in_array((int)$result['http'], [200, 202, 204, 404, 410], true);

    return [
        'ok'      => $ok,
        'message' => $ok
            ? 'Invite deleted'
            : ($result['http']
                ? 'HTTP error ' . $result['http']
                : 'Network error: ' . $result['curl_error']),
        'data'    => $result['body'],
    ];
}

/**
 * Crée une nouvelle invitation dans Wizarr via l'API REST.
 *
 * @param  array  $cfg   Configuration du plugin
 * @param  string $mode  'manual' ou 'auto'
 * @return array{ok: bool, message: string, data: mixed}
 */
function wizarrinvite_create_invite($cfg, $mode)
{
    if (!wizarrinvite_plugin_enabled($cfg)) {
        return ['ok' => false, 'message' => 'Plugin disabled', 'data' => null];
    }

    $baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($cfg['WIZARRINVITE-api-key'] ?? '');

    if (!$baseUrl || !$apiKey) {
        return ['ok' => false, 'message' => 'Configuration missing', 'data' => null];
    }

    // Vérifie la limite d'utilisateurs avant de créer une invitation
    $stats = wizarrinvite_user_stats($cfg);
    if ($stats['ok'] && !empty($stats['data']['limit_reached'])) {
        return [
            'ok'      => false,
            'message' => 'User limit reached',
            'data'    => $stats['data'],
        ];
    }

    $payload = wizarrinvite_build_payload($cfg, $mode);
    $result  = wizarrinvite_request($baseUrl, $apiKey, 'POST', 'invitations', $payload);

    // Wizarr retourne 201 Created en cas de succès
    if ((int)$result['http'] !== 201) {
        return [
            'ok'      => false,
            'message' => $result['http']
                ? 'HTTP error ' . $result['http']
                : 'Network error: ' . $result['curl_error'],
            'data'    => [
                'payload'  => $payload,
                'response' => $result['body'],
            ],
        ];
    }

    $inv = $result['body']['invitation'] ?? null;

    if (!$inv || empty($inv['id']) || empty($inv['code'])) {
        return [
            'ok'      => false,
            'message' => 'Invalid Wizarr response',
            'data'    => $result['body'],
        ];
    }

    // Données à stocker/retourner
    $data = [
        'id'               => $inv['id'],
        'code'             => $inv['code'],
        'url'              => wizarrinvite_build_public_url($cfg, $inv),
        'expires'          => $inv['expires'] ?? null,
        'status'           => $inv['status'] ?? 'pending',
        'created_at_ts'    => time(),
        'invite_mode'      => $mode,
        'payload'          => $payload,
        'config_signature' => wizarrinvite_payload_signature($payload),
        'access_days'      => $payload['duration'] ?? null,
    ];

    // En mode automatique, on sauvegarde dans le cache local
    if ($mode === 'auto') {
        wizarrinvite_save_cache($data);
    }

    return [
        'ok'      => true,
        'message' => $mode === 'manual' ? 'Manual invite created' : 'Automatic invite created',
        'data'    => $data,
    ];
}

/**
 * Cherche dans Wizarr l'invitation correspondant aux données du cache local.
 *
 * Stratégie de correspondance (par ordre de priorité) :
 *  1. Par ID numérique Wizarr → correspondance exacte et rapide
 *  2. Par code alphanumérique → fallback si l'ID a changé entre deux versions
 *
 * Cette fonction est appelée lors de la vérification périodique (toutes les 5 min)
 * pour s'assurer que l'invitation en cache existe toujours côté Wizarr et n'a pas
 * été supprimée manuellement depuis l'interface Wizarr.
 *
 * @param  array $cfg    Configuration du plugin
 * @param  array $cache  Données du cache local (doit contenir 'id' et/ou 'code')
 * @return array|null    Données de l'invitation depuis Wizarr, ou null si introuvable
 */
function wizarrinvite_find_cached_invite_in_wizarr($cfg, $cache)
{
    if (!$cache || (empty($cache['id']) && empty($cache['code']))) {
        return null;
    }

    $baseUrl = rtrim($cfg['WIZARRINVITE-url'] ?? '', '/');
    $apiKey  = trim($cfg['WIZARRINVITE-api-key'] ?? '');

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

        // Correspondance par ID (prioritaire)
        if (!empty($cache['id']) && !empty($inv['id']) && (int)$inv['id'] === (int)$cache['id']) {
            return $inv;
        }

        // Correspondance par code (fallback si pas d'ID)
        if (!empty($cache['code']) && !empty($inv['code']) && (string)$inv['code'] === (string)$cache['code']) {
            return $inv;
        }
    }

    return null;
}
