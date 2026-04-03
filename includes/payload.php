<?php

// ─────────────────────────────────────────────────────────────────────────────
// Construction et normalisation du payload d'invitation Wizarr
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Lit la configuration du plugin pour un mode donné ('manual' ou 'auto')
 * et retourne un tableau unifié avec toutes les options de l'invitation.
 *
 * @param  array  $cfg   Configuration complète du plugin (Organizr)
 * @param  string $mode  'manual' ou 'auto'
 * @return array
 */
function wizarrinvite_mode_cfg($cfg, $mode)
{
    // Préfixe des clés de config selon le mode
    $p = 'WIZARRINVITE-' . $mode . '-';

    $bundleRaw = trim((string)($cfg[$p . 'bundle-id'] ?? ''));

    return [
        'expiration'           => $cfg[$p . 'expiration']           ?? '1',
        'access_days'          => $cfg[$p . 'access-days']          ?? '7',
        'server_ids'           => $cfg[$p . 'server-ids']           ?? '',
        'library_ids'          => $cfg[$p . 'library-ids']          ?? '',
        'allow_downloads'      => !empty($cfg[$p . 'allow-downloads']),
        'allow_live_tv'        => !empty($cfg[$p . 'allow-live-tv']),
        'allow_mobile_uploads' => !empty($cfg[$p . 'allow-mobile-uploads']),
        // Plex Home uniquement disponible en mode manuel
        'invite_to_plex_home'  => $mode === 'manual' && !empty($cfg[$p . 'invite-to-plex-home']),
        // Bundle Wizarr : null = sélection automatique
        'bundle_id'            => ($bundleRaw !== '' && is_numeric($bundleRaw)) ? (int)$bundleRaw : null,
    ];
}

/**
 * Normalise un payload avant envoi à l'API ou calcul de signature.
 * - Trie et caste les ids en entiers
 * - Caste expires_in_days en int (ou null)
 * - Caste duration en string
 * - Caste les booléens
 * - Trie les clés alphabétiquement (cohérence de signature)
 *
 * @param  array $payload
 * @return array
 */
function wizarrinvite_normalize_payload($payload)
{
    $n = is_array($payload) ? $payload : [];

    // IDs serveurs et bibliothèques : entiers triés
    foreach (['server_ids', 'library_ids'] as $key) {
        if (isset($n[$key]) && is_array($n[$key])) {
            $n[$key] = array_values(array_map('intval', $n[$key]));
            sort($n[$key]);
        }
    }

    // Expiration : null ou entier
    if (array_key_exists('expires_in_days', $n)) {
        $n['expires_in_days'] = $n['expires_in_days'] === null ? null : (int)$n['expires_in_days'];
    }

    // Durée d'accès : toujours une string
    if (array_key_exists('duration', $n)) {
        $n['duration'] = (string)$n['duration'];
    }

    // Booléens
    $boolKeys = ['unlimited', 'allow_downloads', 'allow_live_tv', 'allow_mobile_uploads', 'invite_to_plex_home'];
    foreach ($boolKeys as $key) {
        if (array_key_exists($key, $n)) {
            $n[$key] = !empty($n[$key]);
        }
    }

    // wizard_bundle_id : entier ou absent (null → on n'envoie pas le champ)
    if (array_key_exists('wizard_bundle_id', $n)) {
        if ($n['wizard_bundle_id'] === null) {
            unset($n['wizard_bundle_id']);
        } else {
            $n['wizard_bundle_id'] = (int)$n['wizard_bundle_id'];
        }
    }

    // Tri alphabétique des clés pour des signatures stables
    ksort($n);

    return $n;
}

/**
 * Calcule une signature SHA-1 du payload normalisé.
 * Utilisée pour détecter si la configuration a changé depuis la dernière création.
 *
 * @param  array $payload  Payload déjà normalisé (issu de wizarrinvite_build_payload)
 * @return string
 */
function wizarrinvite_payload_signature($payload)
{
    // Le payload passé ici est déjà normalisé — on le retrie juste par sécurité
    $stable = is_array($payload) ? $payload : [];
    ksort($stable);

    return sha1(json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Construit le payload complet prêt à envoyer à l'API Wizarr,
 * en lisant les paramètres depuis la configuration du plugin.
 *
 * @param  array  $cfg   Configuration complète du plugin
 * @param  string $mode  'manual' ou 'auto'
 * @return array         Payload normalisé
 */
function wizarrinvite_build_payload($cfg, $mode)
{
    $modeCfg = wizarrinvite_mode_cfg($cfg, $mode);

    $payload = [
        'server_ids'      => wizarrinvite_parse_int_csv($modeCfg['server_ids']),
        'expires_in_days' => wizarrinvite_expiration_value($modeCfg['expiration']),
        'duration'        => (string)$modeCfg['access_days'],
        'unlimited'       => false,
        'allow_downloads' => $modeCfg['allow_downloads'],
        'allow_live_tv'   => $modeCfg['allow_live_tv'],
        'allow_mobile_uploads' => $modeCfg['allow_mobile_uploads'],
    ];

    // Les bibliothèques sont optionnelles
    $libraryIds = wizarrinvite_parse_int_csv($modeCfg['library_ids']);
    if (!empty($libraryIds)) {
        $payload['library_ids'] = $libraryIds;
    }

    // Plex Home uniquement en mode manuel
    if ($modeCfg['invite_to_plex_home']) {
        $payload['invite_to_plex_home'] = true;
    }

    // Bundle Wizarr (optionnel — omis si null)
    if ($modeCfg['bundle_id'] !== null) {
        $payload['wizard_bundle_id'] = $modeCfg['bundle_id'];
    }

    return wizarrinvite_normalize_payload($payload);
}
