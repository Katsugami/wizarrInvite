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
