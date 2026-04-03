<?php

// ─────────────────────────────────────────────────────────────────────────────
// Résolution du fichier d'affichage (page publique du plugin)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le chemin absolu du fichier d'affichage sélectionné dans la config.
 *
 * Règles :
 *   - Le nom du fichier est lu dans WIZARRINVITE-display-custom-file
 *   - Les sous-répertoires sont autorisés (ex : display/default/display-fr)
 *   - Seuls les caractères alphanumériques, tirets, underscores et / sont permis
 *   - Le chemin résolu doit rester à l'intérieur du dossier du plugin
 *   - L'extension .php est ajoutée automatiquement si absente
 *   - Retourne null si la config est vide ou le fichier inexistant
 *
 * @param  array       $cfg  Configuration du plugin
 * @return string|null       Chemin absolu du fichier, ou null
 */
function wizarrinvite_resolve_display_file($cfg)
{
    $name = trim((string)($cfg['WIZARRINVITE-display-custom-file'] ?? ''));

    if ($name === '') {
        return null;
    }

    // Sanitize : uniquement alphanumériques, tirets, underscores et /
    $name = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $name);
    $name = trim($name, '/');
    // Collapse double slashes
    $name = preg_replace('/\/+/', '/', $name);

    if ($name === '') {
        return null;
    }

    // Ajoute .php si l'extension est absente
    if (substr($name, -4) !== '.php') {
        $name .= '.php';
    }

    $pluginDir = realpath(__DIR__ . '/..');

    if ($pluginDir === false) {
        return null;
    }

    $file = $pluginDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);

    if (!file_exists($file)) {
        return null;
    }

    // Sécurité : le chemin résolu doit être dans le dossier du plugin
    $real = realpath($file);

    if ($real === false) {
        return null;
    }

    if (strpos($real, $pluginDir . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    return $real;
}
