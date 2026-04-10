<?php

// ─────────────────────────────────────────────────────────────────────────────
// Point d'entrée principal du plugin WizarrInvite
//
// Ce fichier est chargé par Organizr. Il inclut les modules dans l'ordre
// logique des dépendances, puis enregistre les routes Slim.
// ─────────────────────────────────────────────────────────────────────────────

// Utilitaires de base (aucune dépendance)
require_once __DIR__ . '/includes/helpers.php';

// Cache JSON local (dépend de helpers)
require_once __DIR__ . '/includes/cache.php';

// Plex Home users (manual entries for accounts invisible to Wizarr API)
require_once __DIR__ . '/includes/plex_home.php';

// Logs de debug (dépend de cache pour wizarrinvite_cache_dir)
require_once __DIR__ . '/includes/debug.php';

// Requêtes HTTP vers l'API Wizarr (aucune dépendance)
require_once __DIR__ . '/includes/request.php';

// Construction des payloads (dépend de helpers)
require_once __DIR__ . '/includes/payload.php';

// Création et gestion des invitations (dépend de helpers, cache, request, payload)
require_once __DIR__ . '/includes/invitations.php';

// Mode automatique (dépend de cache, invitations, payload)
require_once __DIR__ . '/includes/auto.php';

// Slots automatiques (dépend de cache, invitations, payload, users)
require_once __DIR__ . '/includes/slots.php';

// Résolution du fichier d'affichage (aucune dépendance)
require_once __DIR__ . '/includes/display.php';

// Statistiques des utilisateurs et vérification de la limite (dépend de request)
require_once __DIR__ . '/includes/users.php';

// Routes Slim — doit être inclus en dernier ($app doit exister dans le contexte)
require_once __DIR__ . '/routes.php';
