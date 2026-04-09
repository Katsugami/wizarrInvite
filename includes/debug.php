<?php

// ─────────────────────────────────────────────────────────────────────────────
// Système de logs de debug du plugin WizarrInvite
// Le fichier de log est stocké dans le même dossier que le cache Organizr.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retourne le chemin du fichier de log.
 */
function wizarrinvite_log_path(): string
{
    return wizarrinvite_cache_dir() . '/wizarrinvite_debug.log';
}

/**
 * Écrit une entrée dans le journal de debug.
 *
 * @param string $level   INFO | WARN | ERROR
 * @param string $message Message principal
 * @param array  $ctx     Données contextuelles (optionnel)
 */
function wizarrinvite_log(string $level, string $message, array $ctx = []): void
{
    wizarrinvite_ensure_cache_dir();
    $path = wizarrinvite_log_path();

    // Determine timezone (resolved once per PHP process, then cached)
    static $logTz = null;
    if ($logTz === null) {
        // Priority: WIZARRINVITE-timezone plugin setting → Organizr global → PHP default
        $resolved = '';
        if (class_exists('WizarrInvite')) {
            try {
                $p = new WizarrInvite();
                $resolved = trim((string)($p->config['WIZARRINVITE-timezone'] ?? ''));
            } catch (Throwable $th) { /* ignore */ }
        }
        if ($resolved === '') {
            $resolved = trim((string)($GLOBALS['config']['timezone'] ?? ''));
        }
        $logTz = ($resolved !== '' && @timezone_open($resolved)) ? $resolved : date_default_timezone_get();
    }
    $dt   = new DateTime('now', new DateTimeZone($logTz));
    $line = $dt->format('Y-m-d H:i:s') . ' [' . strtoupper($level) . '] ' . $message;
    if ($ctx) {
        $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= PHP_EOL;

    // Rotation automatique si le fichier dépasse 100 Ko
    if (file_exists($path) && filesize($path) > 102400) {
        $lines   = @file($path) ?: [];
        $trimmed = array_slice($lines, (int)(count($lines) / 2));
        @file_put_contents($path, implode('', $trimmed));
    }

    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Lit les dernières entrées du journal (ordre chronologique inversé).
 *
 * @param  int   $tail Nombre maximum de lignes à retourner
 * @return string[]
 */
function wizarrinvite_log_read(int $tail = 200): array
{
    $path = wizarrinvite_log_path();

    if (!file_exists($path)) {
        return [];
    }

    $lines = @file($path) ?: [];

    return array_reverse(array_slice($lines, -$tail));
}

/**
 * Supprime le fichier de log.
 */
function wizarrinvite_log_clear(): void
{
    $path = wizarrinvite_log_path();

    if (file_exists($path)) {
        @unlink($path);
    }
}
