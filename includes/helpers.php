<?php

// ─────────────────────────────────────────────────────────────────────────────
// Fonctions utilitaires générales
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Vérifie si le plugin est activé dans la configuration Organizr.
 */
function wizarrinvite_plugin_enabled($cfg)
{
    return !empty($cfg['WIZARRINVITE-enabled']);
}

/**
 * Convertit une chaîne CSV d'entiers en tableau PHP.
 * Exemple : "1,2, 3" → [1, 2, 3]
 */
function wizarrinvite_parse_int_csv($raw)
{
    $items = explode(',', (string)$raw);
    $items = array_map('trim', $items);
    $items = array_map('intval', $items);
    $items = array_filter($items); // supprime les 0 issus de chaînes vides

    return array_values($items);
}

/**
 * Convertit la valeur d'expiration du formulaire en nombre de jours (ou null = jamais).
 * Valeurs acceptées : '1', '7', '30', 'never'.
 * Toute autre valeur retourne 1 (défaut sécurisé).
 */
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
