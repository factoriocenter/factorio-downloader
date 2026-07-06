#!/usr/bin/env php
<?php
/**
 * scripts/get-token.php
 *
 * Interactive, cross-platform (Windows / Linux / macOS) helper to obtain a
 * Factorio auth token, as described in the README ("Obtaining Your Token").
 *
 * It POSTs your credentials to https://auth.factorio.com/api-login and prints the
 * token, then optionally writes it to your local .env as FACTORIO_TOKEN_FALLBACK.
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

const AUTH_URL = 'https://auth.factorio.com/api-login';

if (!extension_loaded('curl')) {
    fwrite(STDERR, "The PHP cURL extension is required.\n");
    exit(1);
}

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
        // Use PowerShell's SecureString to read without echo.
        $cmd = 'powershell -NoProfile -Command '
             . '"$p = Read-Host -AsSecureString; '
             . '[Runtime.InteropServices.Marshal]::PtrToStringAuto('
             . '[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
        $out = shell_exec($cmd);
        fwrite(STDOUT, "\n");
        return $out === null ? '' : rtrim($out, "\r\n");
    }

    // POSIX: disable echo via stty, read, restore.
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

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------
/**
 * Per the official Web authentication API, credentials are sent in the POST body
 * as application/x-www-form-urlencoded (not as URL query parameters).
 *
 * @return array{0: string|false, 1: int, 2: string} [body, curl_errno, curl_error]
 */
function auth_request(string $login, string $password, ?string $caInfo, ?string $emailCode = null): array
{
    $fields = [
        'username'               => $login,
        'password'               => $password,
        'require_game_ownership' => 'true',
        'api_version'            => '2',
    ];
    if ($emailCode !== null && $emailCode !== '') {
        $fields['email_authentication_code'] = $emailCode;
    }

    $ch = curl_init(AUTH_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields), // sets urlencoded body
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'factorio-downloader-get-token/1.0',
    ]);
    if ($caInfo !== null) {
        curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
    }
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    return [$body, $errno, $error];
}

/** Extract the token from either response shape (array v1, or object v2+). */
function extract_token($data): ?string
{
    if (is_array($data)) {
        if (isset($data['token']) && is_string($data['token'])) {
            return $data['token'];               // api_version >= 2
        }
        if (isset($data[0]) && is_string($data[0])) {
            return $data[0];                     // api_version <= 1
        }
    }
    return null;
}

/** True when the API says an email authentication code is required. */
function needs_email_code($data): bool
{
    if (!is_array($data)) {
        return false;
    }
    $error = strtolower((string) ($data['error'] ?? ''));
    $message = strtolower((string) ($data['message'] ?? ''));
    return str_contains($error, 'email') && str_contains($error, 'authentication')
        || (str_contains($message, 'email') && str_contains($message, 'code'));
}

/**
 * Bootstrap a known-good CA bundle when the local one is missing/broken. The
 * bundle itself is a public file; only this fetch skips verification, and it is
 * then used to verify the real (credential-bearing) auth request.
 */
function bootstrap_cacert(string $certDir): ?string
{
    if (!is_dir($certDir) && !@mkdir($certDir, 0755, true) && !is_dir($certDir)) {
        return null;
    }
    $ch = curl_init('https://curl.se/ca/cacert.pem');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $data = curl_exec($ch);
    curl_close($ch);
    if ($data === false || strlen($data) < 1000) {
        return null;
    }
    $path = $certDir . '/cacert.pem';
    return file_put_contents($path, $data) !== false ? $path : null;
}

// ---------------------------------------------------------------------------
// .env writing
// ---------------------------------------------------------------------------
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
[$body, $errno, $error] = auth_request($login, $password, null);

// Retry once with a fresh CA bundle if the failure was a certificate problem.
if ($errno === CURLE_SSL_CACERT || $errno === CURLE_SSL_CACERT_BADFILE || $errno === CURLE_SSL_CERTPROBLEM) {
    fwrite(STDOUT, "TLS certificate issue detected, fetching a CA bundle and retrying...\n");
    $ca = bootstrap_cacert(dirname(__DIR__) . '/certs');
    if ($ca !== null) {
        [$body, $errno, $error] = auth_request($login, $password, $ca);
    }
}

if ($errno !== 0 || $body === false) {
    fwrite(STDERR, "Request failed: " . ($error !== '' ? $error : 'unknown error') . "\n");
    exit(1);
}

$data = json_decode($body, true);

// Some accounts require a one-time code e-mailed on login. Prompt for it and retry.
if (needs_email_code($data)) {
    fwrite(STDOUT, "\nFactorio e-mailed you an authentication code.\n");
    $code = prompt('Enter the e-mail authentication code: ');
    if ($code !== '') {
        [$body, $errno, $error] = auth_request($login, $password, $ca ?? null, $code);
        if ($errno !== 0 || $body === false) {
            fwrite(STDERR, "Request failed: " . ($error !== '' ? $error : 'unknown error') . "\n");
            exit(1);
        }
        $data = json_decode($body, true);
    }
}

// API errors come back as an object with error/message fields.
if (is_array($data) && (isset($data['error']) || isset($data['message'])) && extract_token($data) === null) {
    $msg = $data['message'] ?? $data['error'] ?? 'authentication failed';
    fwrite(STDERR, "Factorio API: $msg\n");
    exit(1);
}

$token = extract_token($data);
if ($token === null || $token === '') {
    fwrite(STDERR, "Unexpected response: $body\n");
    exit(1);
}

fwrite(STDOUT, "\n✓ Token obtained:\n\n    $token\n\n");
fwrite(STDOUT, "Add this line to your .env:\n\n    FACTORIO_TOKEN_FALLBACK=$token\n\n");

$answer = strtolower(prompt('Write it to ./.env now? [y/N]: '));
if ($answer === 'y' || $answer === 'yes' || $answer === 's' || $answer === 'sim') {
    $envPath = dirname(__DIR__) . '/.env';
    if (upsert_env($envPath, 'FACTORIO_TOKEN_FALLBACK', $token)) {
        fwrite(STDOUT, "Saved to $envPath\n");
    } else {
        fwrite(STDERR, "Could not write to $envPath — add the line manually.\n");
        exit(1);
    }
}

exit(0);
