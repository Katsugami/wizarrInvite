<?php

// ─────────────────────────────────────────────────────────────────────────────
// Gestion du cache JSON pour l'invitation automatique
// Le fichier est stocké dans /data/cache/wizarrinvite_current.json
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le chemin du dossier de cache d'Organizr.
 */
function wizarrinvite_cache_dir()
{
    return dirname(__DIR__, 4) . '/data/cache';
}

/**
 * Retourne le chemin complet du fichier de cache du plugin.
 */
function wizarrinvite_cache_file()
{
    return wizarrinvite_cache_dir() . '/wizarrinvite_current.json';
}

/**
 * Crée le dossier de cache s'il n'existe pas encore.
 */
function wizarrinvite_ensure_cache_dir()
{
    $dir = wizarrinvite_cache_dir();

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

/**
 * Charge le cache depuis le fichier JSON.
 * Retourne un tableau associatif ou null si le fichier est absent/invalide.
 */
function wizarrinvite_load_cache()
{
    $file = wizarrinvite_cache_file();

    if (!file_exists($file)) {
        return null;
    }

    $data = json_decode(@file_get_contents($file), true);

    return is_array($data) ? $data : null;
}

/**
 * Sauvegarde les données dans le fichier de cache JSON.
 */
function wizarrinvite_save_cache($data)
{
    wizarrinvite_ensure_cache_dir();

    @file_put_contents(
        wizarrinvite_cache_file(),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Supprime le fichier de cache s'il existe.
 */
function wizarrinvite_clear_cache()
{
    $file = wizarrinvite_cache_file();

    if (file_exists($file)) {
        @unlink($file);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Cache par slot automatique
// Chaque slot possède son propre fichier : wizarrinvite_slot_{id}.json
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le chemin du fichier de cache pour un slot donné.
 *
 * @param  int $slotId
 * @return string
 */
function wizarrinvite_slot_cache_file($slotId)
{
    return wizarrinvite_cache_dir() . '/wizarrinvite_slot_' . (int)$slotId . '.json';
}

/**
 * Charge le cache d'un slot depuis son fichier JSON.
 * Retourne null si le fichier est absent ou invalide.
 *
 * @param  int $slotId
 * @return array|null
 */
function wizarrinvite_load_slot_cache($slotId)
{
    $file = wizarrinvite_slot_cache_file($slotId);

    if (!file_exists($file)) {
        return null;
    }

    $data = json_decode(@file_get_contents($file), true);

    return is_array($data) ? $data : null;
}

/**
 * Sauvegarde les données d'un slot dans son fichier de cache JSON.
 *
 * @param  int   $slotId
 * @param  array $data
 */
function wizarrinvite_save_slot_cache($slotId, $data)
{
    wizarrinvite_ensure_cache_dir();

    @file_put_contents(
        wizarrinvite_slot_cache_file($slotId),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Supprime le fichier de cache d'un slot s'il existe.
 *
 * @param  int $slotId
 */
function wizarrinvite_clear_slot_cache($slotId)
{
    $file = wizarrinvite_slot_cache_file($slotId);

    if (file_exists($file)) {
        @unlink($file);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Cache du compteur d'utilisateurs (mode vérification différée)
// Fichier : wizarrinvite_count_cache.json
// Format  : { "slotId": { "count": 70, "updated_at": 1234567890 }, ... }
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le chemin du fichier de cache des compteurs.
 */
function wizarrinvite_count_cache_file()
{
    return wizarrinvite_cache_dir() . '/wizarrinvite_count_cache.json';
}

/**
 * Charge l'intégralité du cache des compteurs.
 *
 * @return array  Tableau indexé par slotId (string)
 */
function wizarrinvite_load_count_cache()
{
    $file = wizarrinvite_count_cache_file();

    if (!file_exists($file)) {
        return [];
    }

    $data = json_decode(@file_get_contents($file), true);

    return is_array($data) ? $data : [];
}

/**
 * Retourne l'entrée de cache pour un slot donné, ou null si absente.
 *
 * @param  int $slotId
 * @return array{count: int, updated_at: int}|null
 */
function wizarrinvite_get_slot_count_cache($slotId)
{
    $cache = wizarrinvite_load_count_cache();
    $key   = (string)(int)$slotId;

    return (isset($cache[$key]) && is_array($cache[$key])) ? $cache[$key] : null;
}

/**
 * Sauvegarde le compteur d'un slot dans le cache partagé.
 * Stocke server_ids pour détecter les changements de config.
 *
 * @param  int    $slotId
 * @param  int    $count
 * @param  string $serverIds
 * @param  array  $perServerCounts
 * @param  array  $serverNames
 */
function wizarrinvite_save_slot_count_cache($slotId, $count, $serverIds = '', array $perServerCounts = [], array $serverNames = [])
{
    wizarrinvite_ensure_cache_dir();
    $cache = wizarrinvite_load_count_cache();

    $cache[(string)(int)$slotId] = [
        'count'              => (int)$count,
        'server_ids'         => trim((string)$serverIds),
        'per_server_counts'  => $perServerCounts,
        'server_names'       => $serverNames,
        'updated_at'         => time(),
    ];

    @file_put_contents(
        wizarrinvite_count_cache_file(),
        json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Supprime le fichier de cache des compteurs s'il existe.
 */
function wizarrinvite_clear_count_cache()
{
    $file = wizarrinvite_count_cache_file();

    if (file_exists($file)) {
        @unlink($file);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Cache du tableau users brut (GET /api/users)
//
// Stocke la liste complète des utilisateurs retournée par Wizarr.
// Partagé entre wizarrinvite_user_stats et wizarrinvite_fetch_slot_effective_count
// pour éviter de réappeler l'API (lente) à chaque création d'invitation.
//
// Fichier : wizarrinvite_users_cache.json
// Contenu : { "users": [...], "updated_at": <timestamp unix> }
// ─────────────────────────────────────────────────────────────────────────────

function wizarrinvite_users_cache_file()
{
    return wizarrinvite_cache_dir() . '/wizarrinvite_users_cache.json';
}

/**
 * Charge le cache users. Retourne null si absent ou illisible.
 *
 * @return array{users: array, updated_at: int}|null
 */
function wizarrinvite_load_users_cache()
{
    $file = wizarrinvite_users_cache_file();
    if (!file_exists($file)) {
        return null;
    }
    $data = @json_decode(@file_get_contents($file), true);
    if (!is_array($data) || !isset($data['users']) || !isset($data['updated_at'])) {
        return null;
    }
    return $data;
}

/**
 * Sauvegarde le tableau users dans le cache.
 *
 * @param array  $users    Tableau brut retourné par GET /api/users
 * @param string $trigger  Source du check : 'manual-button', 'display', 'auto', 'api'
 */
function wizarrinvite_save_users_cache(array $users, string $trigger = 'api')
{
    wizarrinvite_ensure_cache_dir();
    @file_put_contents(
        wizarrinvite_users_cache_file(),
        json_encode(
            [
                'users'         => $users,
                'updated_at'    => time(),
                'last_trigger'  => $trigger,
                'last_check_at' => time(),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
    );
}

/**
 * Supprime le cache users s'il existe.
 */
function wizarrinvite_clear_users_cache()
{
    $file = wizarrinvite_users_cache_file();
    if (file_exists($file)) {
        @unlink($file);
    }
}
