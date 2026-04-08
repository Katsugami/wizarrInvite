<?php

// ─────────────────────────────────────────────────────────────────────────────
// Configuration par défaut du plugin WizarrInvite
// Ces valeurs sont utilisées si l'utilisateur n'a rien sauvegardé.
// ─────────────────────────────────────────────────────────────────────────────

return [

    // ── Général ───────────────────────────────────────────────────────────────
    'WIZARRINVITE-enabled'              => false,
    'WIZARRINVITE-url'                  => '',           // URL interne de Wizarr
    'WIZARRINVITE-api-key'              => '',           // Clé API Wizarr
    'WIZARRINVITE-public-url'           => '',           // URL publique (optionnel)
    'WIZARRINVITE-display-custom-file'  => 'display/default/display-en', // Chemin relatif du fichier d'affichage (sans .php)

    // ── Invitation manuelle ───────────────────────────────────────────────────
    'WIZARRINVITE-manual-min-group'            => '2',   // Groupe Organizr minimum pour accéder à la création manuelle
    'WIZARRINVITE-manual-expiration'           => '1',
    'WIZARRINVITE-manual-access-days'          => '7',
    'WIZARRINVITE-manual-server-ids'           => '',
    'WIZARRINVITE-manual-library-ids'          => '',
    'WIZARRINVITE-manual-allow-downloads'      => false,
    'WIZARRINVITE-manual-allow-live-tv'        => false,
    'WIZARRINVITE-manual-allow-mobile-uploads' => false,
    'WIZARRINVITE-manual-invite-to-plex-home'  => false,
    'WIZARRINVITE-manual-bundle-id'            => '',    // ID du bundle Wizarr (vide = automatique)

    // ── Slots automatiques (config JSON sérialisée) ───────────────────────────
    'WIZARRINVITE-slots-config'                => '[]',  // Tableau JSON des slots : [{id, label, ...}, ...]

    // ── Invitation automatique (mode legacy, conservé pour compatibilité) ─────
    'WIZARRINVITE-auto-enabled'                => false,
    'WIZARRINVITE-auto-expiration'             => '1',
    'WIZARRINVITE-auto-access-days'            => '7',
    'WIZARRINVITE-auto-server-ids'             => '',
    'WIZARRINVITE-auto-library-ids'            => '',
    'WIZARRINVITE-auto-allow-downloads'        => false,
    'WIZARRINVITE-auto-allow-live-tv'          => false,
    'WIZARRINVITE-auto-allow-mobile-uploads'   => false,

];
