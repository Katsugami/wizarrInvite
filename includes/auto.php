<?php

// ─────────────────────────────────────────────────────────────────────────────
// Mode automatique : statut, création et mise à jour de l'invitation permanente
//
// Le mode auto maintient une invitation "permanente" dans Wizarr.
// Elle est recréée automatiquement si elle est utilisée, expirée,
// ou si les paramètres de configuration ont changé.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Vérifie l'état de l'invitation automatique.
 *
 * États possibles retournés :
 *   'disabled' → plugin ou mode auto désactivé
 *   'missing'  → pas de cache, ou invitation introuvable/expirée dans Wizarr
 *   'stale'    → invitation présente mais paramètres différents de la config actuelle
 *   'active'   → invitation valide et à jour
 *
 * @param  array $cfg  Configuration du plugin
 * @return array{state: string, message: string, current: array|null, cache_file: string}
 */
function wizarrinvite_auto_status($cfg)
{
    $cacheFile = wizarrinvite_cache_file();

    // Le plugin ou le mode auto est désactivé
    if (!wizarrinvite_plugin_enabled($cfg)) {
        return ['state' => 'disabled', 'message' => 'Plugin disabled',           'current' => null, 'cache_file' => $cacheFile];
    }
    if (empty($cfg['WIZARRINVITE-auto-enabled'])) {
        return ['state' => 'disabled', 'message' => 'Automatic mode disabled',   'current' => null, 'cache_file' => $cacheFile];
    }

    $cache            = wizarrinvite_load_cache();
    $desiredPayload   = wizarrinvite_build_payload($cfg, 'auto');
    $desiredSignature = wizarrinvite_payload_signature($desiredPayload);

    // Pas de cache local → invitation à créer
    if (!$cache) {
        return ['state' => 'missing', 'message' => 'No automatic code in cache', 'current' => null, 'cache_file' => $cacheFile];
    }

    // L'invitation existe-t-elle encore dans Wizarr ?
    $found = wizarrinvite_find_cached_invite_in_wizarr($cfg, $cache);

    if (!$found) {
        return ['state' => 'missing', 'message' => 'Automatic code missing from Wizarr', 'current' => $cache, 'cache_file' => $cacheFile];
    }

    // L'invitation est-elle encore utilisable ?
    $status = $found['status'] ?? 'pending';
    if ($status === 'used' || $status === 'expired') {
        return ['state' => 'missing', 'message' => 'Automatic code is ' . $status, 'current' => $cache, 'cache_file' => $cacheFile];
    }

    // Données actualisées depuis Wizarr
    $current = [
        'id'               => $found['id']      ?? $cache['id'],
        'code'             => $found['code']    ?? $cache['code'],
        'url'              => wizarrinvite_build_public_url($cfg, $found),
        'expires'          => $found['expires'] ?? ($cache['expires'] ?? null),
        'status'           => $status,
        'invite_mode'      => 'auto',
        'payload'          => $cache['payload']          ?? null,
        'config_signature' => $cache['config_signature'] ?? null,
        'access_days'      => $desiredPayload['duration'] ?? ($cache['access_days'] ?? null),
    ];

    // Cache ancien format (pas de signature) → marquer comme périmé
    if (empty($cache['config_signature']) || empty($cache['payload'])) {
        return ['state' => 'stale', 'message' => 'Automatic code cache format outdated', 'current' => $current, 'cache_file' => $cacheFile];
    }

    // Paramètres de config différents depuis la dernière création → périmé
    if ((string)$cache['config_signature'] !== (string)$desiredSignature) {
        $current['desired_payload'] = $desiredPayload;
        return ['state' => 'stale', 'message' => 'Automatic code parameters changed', 'current' => $current, 'cache_file' => $cacheFile];
    }

    // Tout est bon : on rafraîchit le cache avec les données Wizarr à jour
    $current['payload']          = $desiredPayload;
    $current['config_signature'] = $desiredSignature;
    wizarrinvite_save_cache($current);

    return ['state' => 'active', 'message' => 'Automatic code active', 'current' => $current, 'cache_file' => $cacheFile];
}

/**
 * Retourne l'invitation automatique active, ou en crée une nouvelle si nécessaire.
 *
 * Comportement :
 *   - Actif   → retourne directement
 *   - Périmé  → supprime l'ancienne et en crée une nouvelle
 *   - Absent  → crée une nouvelle invitation
 *
 * @param  array $cfg  Configuration du plugin
 * @return array{ok: bool, message: string, data: mixed}
 */
function wizarrinvite_get_or_create_auto($cfg)
{
    if (!wizarrinvite_plugin_enabled($cfg)) {
        return ['ok' => false, 'message' => 'Plugin disabled', 'data' => null];
    }

    if (empty($cfg['WIZARRINVITE-auto-enabled'])) {
        return ['ok' => false, 'message' => 'Automatic mode is disabled', 'data' => null];
    }

    $status = wizarrinvite_auto_status($cfg);

    // Invitation déjà active → vérifie quand même la limite avant de retourner
    if ($status['state'] === 'active' && !empty($status['current'])) {
        $stats = wizarrinvite_user_stats($cfg);
        if ($stats['ok'] && !empty($stats['data']['limit_reached'])) {
            return ['ok' => false, 'message' => 'User limit reached', 'data' => $stats['data']];
        }
        return ['ok' => true, 'message' => 'Automatic code active', 'data' => $status['current']];
    }

    // Invitation périmée avec un ID connu → on la supprime avant d'en créer une nouvelle
    if ($status['state'] === 'stale' && !empty($status['current']['id'])) {
        $delete = wizarrinvite_delete_invite($cfg, $status['current']['id']);

        if (!$delete['ok']) {
            return [
                'ok'      => false,
                'message' => 'Unable to delete old automatic code before recreation',
                'data'    => ['status' => $status, 'delete' => $delete],
            ];
        }

        wizarrinvite_clear_cache();
    }

    // Création d'une nouvelle invitation automatique
    return wizarrinvite_create_invite($cfg, 'auto');
}
