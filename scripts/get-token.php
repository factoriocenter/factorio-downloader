#!/usr/bin/env php
<?php
/**
 * scripts/get-token.php
 *
 * Interactive, cross-platform (Windows / Linux / macOS) helper to obtain a
 * Factorio auth token, as described in the README ("Obtaining Your Token").
 *
 * It authenticates against https://auth.factorio.com/api-login (via the shared
 * lib/factorio-auth.php helper) and prints the token, then optionally writes it
 * to your local .env as FACTORIO_TOKEN_FALLBACK.
 *
 * Usage:
 *     php scripts/get-token.php
 *
 * Credentials are read from (in order): the FACTORIO_LOGIN / FACTORIO_PASSWORD
 * environment variables, otherwise an interactive prompt (password hidden).
 * The password is never printed or logged.
 *
 * Requirements: PHP CLI with the cURL extension (already required by this project).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}
if (!extension_loaded('curl')) {
    fwrite(STDERR, "The PHP cURL extension is required.\n");
    exit(1);
}

require __DIR__ . '/../lib/factorio-auth.php';

// ---------------------------------------------------------------------------
// Input helpers
// ---------------------------------------------------------------------------
function is_windows(): bool
{
    return strncasecmp(PHP_OS_FAMILY, 'Windows', 7) === 0;
}

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    return $line === false ? '' : trim($line);
}

/** Prompt for a secret without echoing it to the terminal (best effort). */
function prompt_hidden(string $label): string
{
    fwrite(STDOUT, $label);

    if (is_windows()) {
        $cmd = 'powershell -NoProfile -Command '
             . '"$p = Read-Host -AsSecureString; '
             . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
             . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        $out = shell_exec($cmd);
        fwrite(STDOUT, "\n");
        return $out === null ? '' : rtrim($out, "\r\n");
    }

    $hasStty = false;
    $sttyOrig = shell_exec('stty -g 2>/dev/null');
    if ($sttyOrig !== null && trim($sttyOrig) !== '') {
        $hasStty = true;
        shell_exec('stty -echo 2>/dev/null');
    }
    $line = fgets(STDIN);
    if ($hasStty) {
        shell_exec('stty ' . trim($sttyOrig) . ' 2>/dev/null');
    }
    fwrite(STDOUT, "\n");
    return $line === false ? '' : rtrim($line, "\r\n");
}

function upsert_env(string $envPath, string $key, string $value): bool
{
    $line = "$key=$value";
    $content = is_file($envPath) ? (string) file_get_contents($envPath) : '';
    if ($content !== '' && preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $content)) {
        $content = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $content);
    } else {
        if ($content !== '' && substr($content, -1) !== "\n") {
            $content .= "\n";
        }
        $content .= $line . "\n";
    }
    return file_put_contents($envPath, $content) !== false;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
$login = getenv('FACTORIO_LOGIN') ?: '';
if ($login === '') {
    $login = prompt('Factorio login (username or e-mail): ');
}
$password = getenv('FACTORIO_PASSWORD') ?: '';
if ($password === '') {
    $password = prompt_hidden('Factorio password (hidden): ');
}
if ($login === '' || $password === '') {
    fwrite(STDERR, "Login and password are required.\n");
    exit(1);
}

fwrite(STDOUT, "Requesting token...\n");
$token = factorio_auth_login($login, $password, null, null, $error, $needsEmailCode);

if ($token === null && $needsEmailCode) {
    fwrite(STDOUT, "\nFactorio e-mailed you an authentication code.\n");
    $code = prompt('Enter the e-mail authentication code: ');
    if ($code !== '') {
        $token = factorio_auth_login($login, $password, $code, null, $error, $needsEmailCode);
    }
}

if ($token === null) {
    fwrite(STDERR, 'Factorio API: ' . ($error ?? 'authentication failed') . "\n");
    exit(1);
}

fwrite(STDOUT, "\n\xE2\x9C\x93 Token obtained:\n\n    $token\n\n");
fwrite(STDOUT, "Add this line to your .env:\n\n    FACTORIO_TOKEN_FALLBACK=$token\n\n");

$answer = strtolower(prompt('Write it to ./.env now? [y/N]: '));
if (in_array($answer, ['y', 'yes', 's', 'sim'], true)) {
    $envPath = dirname(__DIR__) . '/.env';
    if (upsert_env($envPath, 'FACTORIO_TOKEN_FALLBACK', $token)) {
        @chmod($envPath, 0600);
        fwrite(STDOUT, "Saved to $envPath\n");
    } else {
        fwrite(STDERR, "Could not write to $envPath — add the line manually.\n");
        exit(1);
    }
}

exit(0);
