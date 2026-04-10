<?php

// ─────────────────────────────────────────────────────────────────────────────
// Gestion des utilisateurs Plex Home (comptes non visibles via GET /api/users)
//
// Ces utilisateurs sont saisis manuellement par l'admin et stockés dans un
// fichier JSON séparé du cache users pour survivre aux vidages de cache.
//
// Fichier : wizarrinvite_plex_home.json
// Format  : [{"id":"PH1","name":"Username","servers":["Server1 (Plex)","Server2 (Plex)"]},...]
// ─────────────────────────────────────────────────────────────────────────────

function wizarrinvite_plex_home_file()
{
    return wizarrinvite_cache_dir() . '/wizarrinvite_plex_home.json';
}

/**
 * Charge la liste des utilisateurs Plex Home.
 * @return array
 */
function wizarrinvite_load_plex_home_users()
{
    $file = wizarrinvite_plex_home_file();
    if (!file_exists($file)) {
        return [];
    }
    $data = @json_decode(@file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/**
 * Sauvegarde la liste des utilisateurs Plex Home.
 * @param array $users
 */
function wizarrinvite_save_plex_home_users(array $users)
{
    wizarrinvite_ensure_cache_dir();
    @file_put_contents(
        wizarrinvite_plex_home_file(),
        json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Retourne le prochain ID disponible (PH1, PH2, ...).
 * @param array $users
 * @return string
 */
function wizarrinvite_next_plex_home_id(array $users)
{
    $existing = array_map(function ($u) { return $u['id'] ?? ''; }, $users);
    $n = 1;
    while (in_array('PH' . $n, $existing, true)) {
        $n++;
    }
    return 'PH' . $n;
}

/**
 * Construit des entrées utilisateur virtuelles au format GET /api/users
 * pour les utilisateurs Plex Home, afin de les injecter dans les calculs de comptage.
 *
 * Chaque utilisateur PH qui a accès à N serveurs génère N entrées (une par serveur),
 * ce qui correspond au format de l'API Wizarr (un enregistrement par combinaison user+server).
 *
 * @return array
 */
function wizarrinvite_build_virtual_plex_home_users()
{
    $phUsers = wizarrinvite_load_plex_home_users();
    $virtual = [];

    foreach ($phUsers as $ph) {
        $name    = trim($ph['name']    ?? '');
        $id      = trim($ph['id']      ?? '');
        $servers = is_array($ph['servers']) ? $ph['servers'] : [];

        if ($name === '' || empty($servers)) {
            continue;
        }

        foreach ($servers as $serverName) {
            $virtual[] = [
                'id'          => $id,
                'username'    => $name,
                'email'       => 'empty',
                'server'      => $serverName,
                'server_type' => 'plex',
                'expires'     => null,
                '_plex_home'  => true,
            ];
        }
    }

    return $virtual;
}
