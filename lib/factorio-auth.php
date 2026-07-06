<?php
/**
 * lib/factorio-auth.php
 *
 * Shared helper for the Factorio Web authentication API
 * (https://wiki.factorio.com/Web_authentication_API).
 *
 * Credentials are sent in the POST body (application/x-www-form-urlencoded) with
 * api_version=2, so the success response is {"token": "...", "username": "..."}
 * (a legacy array response is also accepted). Used by the CLI helper
 * (scripts/get-token.php), the web installer (setup.php) and download.php.
 *
 * This file only defines functions; it produces no output if requested directly.
 */

if (!defined('FACTORIO_AUTH_URL')) {
    define('FACTORIO_AUTH_URL', 'https://auth.factorio.com/api-login');
}

if (!function_exists('factorio_auth_ca_bootstrap')) {
    /**
     * Fetch a known-good CA bundle when the host's bundle is missing/broken. Only
     * this download skips verification (it is a public file); it is then used to
     * verify the real credential-bearing request.
     */
    function factorio_auth_ca_bootstrap(string $certDir): ?string
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
}

if (!function_exists('_factorio_auth_request')) {
    /** @return array{0: string|false, 1: int, 2: string} [body, errno, error] */
    function _factorio_auth_request(string $login, string $password, ?string $caInfo, ?string $emailCode): array
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

        $ch = curl_init(FACTORIO_AUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'factorio-downloader/1.0',
        ]);
        if ($caInfo !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
        }
        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [$body, $errno, $error];
    }
}

if (!function_exists('_factorio_auth_extract_token')) {
    /** Token from either response shape: {"token": "..."} (v2) or ["..."] (v1). */
    function _factorio_auth_extract_token($data): ?string
    {
        if (is_array($data)) {
            if (isset($data['token']) && is_string($data['token']) && $data['token'] !== '') {
                return $data['token'];
            }
            if (isset($data[0]) && is_string($data[0]) && $data[0] !== '') {
                return $data[0];
            }
        }
        return null;
    }
}

if (!function_exists('_factorio_auth_needs_email_code')) {
    /** True when the API says an e-mail authentication code is required. */
    function _factorio_auth_needs_email_code($data): bool
    {
        if (!is_array($data)) {
            return false;
        }
        $error   = strtolower((string) ($data['error'] ?? ''));
        $message = strtolower((string) ($data['message'] ?? ''));
        return (str_contains($error, 'email') && str_contains($error, 'authentication'))
            || (str_contains($message, 'email') && str_contains($message, 'code'));
    }
}

if (!function_exists('factorio_auth_login')) {
    /**
     * Authenticate and return a token, or null on failure.
     *
     * @param string      $certDir        writable dir for a fallback CA bundle
     * @param string|null $error          set to a human-readable message on failure
     * @param bool        $needsEmailCode set true when an e-mail code is required
     */
    function factorio_auth_login(
        string $login,
        string $password,
        ?string $emailCode,
        string $certDir,
        &$error = null,
        &$needsEmailCode = false
    ): ?string {
        $error = null;
        $needsEmailCode = false;

        [$body, $errno, $err] = _factorio_auth_request($login, $password, null, $emailCode);

        // Retry once with a fresh CA bundle on a certificate problem.
        if ($errno === CURLE_SSL_CACERT || $errno === CURLE_SSL_CACERT_BADFILE || $errno === CURLE_SSL_CERTPROBLEM) {
            $ca = factorio_auth_ca_bootstrap($certDir);
            if ($ca !== null) {
                [$body, $errno, $err] = _factorio_auth_request($login, $password, $ca, $emailCode);
            }
        }

        if ($errno !== 0 || $body === false) {
            $error = 'Connection to the authentication server failed'
                   . ($err !== '' ? ": $err" : '.');
            return null;
        }

        $data = json_decode($body, true);

        if (_factorio_auth_needs_email_code($data)) {
            $needsEmailCode = true;
            $error = 'An e-mail authentication code is required.';
            return null;
        }

        $token = _factorio_auth_extract_token($data);
        if ($token !== null) {
            return $token;
        }

        if (is_array($data) && (isset($data['error']) || isset($data['message']))) {
            $error = (string) ($data['message'] ?? $data['error']);
        } else {
            $error = 'Unexpected response from the authentication server.';
        }
        return null;
    }
}
