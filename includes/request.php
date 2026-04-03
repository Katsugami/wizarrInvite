<?php

// ─────────────────────────────────────────────────────────────────────────────
// Communication HTTP avec l'API REST de Wizarr
// Toutes les requêtes passent par wizarrinvite_request()
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Effectue une requête HTTP vers l'API Wizarr.
 *
 * @param string      $baseUrl   URL de base de Wizarr (ex: http://127.0.0.1:5690)
 * @param string      $apiKey    Clé API Wizarr (envoyée via X-API-Key)
 * @param string      $method    Méthode HTTP : GET, POST, DELETE…
 * @param string      $endpoint  Chemin de l'endpoint sans /api/ (ex: invitations)
 * @param array|null  $payload   Corps de la requête (encodé en JSON si fourni)
 * @param int         $timeout   Timeout total de transfert en secondes (défaut : 10)
 *
 * Le timeout de connexion est automatiquement plafonné à min(5, $timeout-1).
 * Cela évite de bloquer longtemps quand un serveur Plex/Wizarr est inaccessible :
 * si la connexion TCP ne s'établit pas en 5 s, la requête échoue immédiatement.
 *
 * @return array{http: int, body: mixed, curl_error: string}
 */
function wizarrinvite_request($baseUrl, $apiKey, $method, $endpoint, $payload = null, $timeout = 10)
{
    $url            = rtrim($baseUrl, '/') . '/api/' . ltrim($endpoint, '/');
    $connectTimeout = min(5, max(1, $timeout - 1));

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    $body      = curl_exec($ch);
    $http      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Tente de décoder le JSON ; conserve la chaîne brute si ce n'est pas du JSON
    $decoded = json_decode($body, true);

    return [
        'http'       => $http,
        'body'       => $decoded !== null ? $decoded : $body,
        'curl_error' => $curlError,
    ];
}
