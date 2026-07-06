<?php
/**
 * download.php
 *
 * This script uses credentials from config.php to authenticate with Factorio’s API
 * and obtain the official download URL. If a fallback token (FACTORIO_TOKEN_FALLBACK)
 * is provided, it will skip in-container authentication and use that token directly.
 *
 * The download will only proceed if:
 *  - The account owns the requested build (base game or expansion).
 *  - A valid token is available (either via fallback or direct auth).
 *
 * Otherwise, an error message is shown.
 *
 * Requirements:
 *  - config.php must be properly configured (using Dotenv, etc.).
 *  - The cURL extension must be enabled.
 *  - TLS verification uses the system's CA trust store (or FACTORIO_CA_BUNDLE,
 *    see lib/factorio-auth.php); no CA bundle is ever downloaded at runtime.
 */

// Display errors for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration
require_once 'config.php';

// --- 1. Parameters and Defaults ---
$versionParam = isset($_GET['ver']) ? $_GET['ver'] : '';
$build        = isset($_GET['build']) ? $_GET['build'] : 'alpha';
$target       = isset($_GET['target']) ? $_GET['target'] : '';

// Default target based on OS
if (empty($target)) {
    $os = php_uname('s');
    if (stripos($os, 'Darwin') !== false) {
        $target = 'osx';
    } elseif (stripos($os, 'Windows') !== false) {
        $target = 'win64';
    } else {
        $target = 'linux64';
    }
}

// Remove any prefix like "factorio://"
$version = preg_replace('/^.*:\/\//', '', $versionParam);

// If version is empty or set to "experimental" or "stable", fetch latest from Factorio’s API
if (empty($version) || $version === 'experimental' || $version === 'stable') {
    $versionType = empty($version) ? 'experimental' : $version;
    $latestJson = file_get_contents("https://www.factorio.com/api/latest-releases");
    if ($latestJson === false) {
        die("Error fetching latest version.");
    }
    $latestData = json_decode($latestJson, true);
    if (!isset($latestData[$versionType][$build])) {
        die("Version not found for build '$build'.");
    }
    $version = $latestData[$versionType][$build];
}

// Validate version format (e.g. 2.0.32)
if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version)) {
    die("Invalid version format: $version");
}

// Validate build and target
$validBuilds = ['alpha', 'demo', 'headless', 'expansion'];
if (!in_array($build, $validBuilds)) {
    die("Invalid build: $build. Supported builds: " . implode(', ', $validBuilds));
}
$validTargets = ['linux64', 'osx', 'win64', 'win64-manual'];
if (!in_array($target, $validTargets)) {
    die("Unknown platform: $target");
}

// --- 2. Get Credentials from config.php
$login    = $FACTORIO_LOGIN;
$password = $FACTORIO_PASSWORD;

require_once __DIR__ . '/lib/factorio-auth.php';

// Attempt to read fallback token from environment
$fallbackToken = $_ENV['FACTORIO_TOKEN_FALLBACK'] ?? '';

// If we have a fallback token, use it immediately
$token = $fallbackToken;

// If we do not have a fallback token, and the build requires auth, attempt direct auth
if (empty($token) && in_array($build, ['alpha', 'expansion'])) {
    if (empty($password)) {
        die("No password defined for $login.");
    }
    // Authenticate through the shared helper (POST body, api_version=2, handles
    // the CA-bundle resolution and both response shapes).
    $authError = null;
    $token = factorio_auth_login($login, $password, null, null, $authError);
    if (empty($token)) {
        die("Authentication failed: " . htmlspecialchars($authError ?? "could not obtain a token for the account.", ENT_QUOTES));
    }
}

// --- 3. Build the Download URL ---
$factorioUrl = "https://www.factorio.com/get-download/{$version}/{$build}/{$target}";
if (!empty($token)) {
    // If we have a token (from fallback or direct auth), append it
    $factorioUrl .= "?username=" . urlencode($login) . "&token=" . urlencode($token);
}

// --- 4. Get the Effective URL ---
// TLS verification is never disabled: with no explicit CA bundle, cURL/PHP's own
// system trust store is used (same safe resolution as lib/factorio-auth.php).
// This request may carry the account token in $factorioUrl, so verification must
// stay on to prevent an on-path attacker from capturing it.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $factorioUrl);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "FactorioDownloader/1.0");

$caBundle = factorio_auth_ca_bundle(null);
if ($caBundle !== null) {
    curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
}

curl_exec($ch);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

if (curl_errno($ch)) {
    $errno = curl_errno($ch);
    $isCertIssue = $errno === CURLE_SSL_CACERT || $errno === CURLE_SSL_CACERT_BADFILE || $errno === CURLE_SSL_CERTPROBLEM;
    $msg = $isCertIssue
        ? "TLS certificate verification failed. Point FACTORIO_CA_BUNDLE at a trusted cacert.pem, or fix curl.cainfo in php.ini."
        : curl_error($ch);
    curl_close($ch);
    die("Error obtaining effective URL: " . htmlspecialchars($msg, ENT_QUOTES));
}
curl_close($ch);

if (!$effectiveUrl) {
    die("Failed to obtain effective URL.");
}

// Debug (uncomment if needed):
// echo "Effective URL: " . htmlspecialchars($effectiveUrl);
// exit;

// --- 5. Redirect the Browser ---
header("Location: " . $effectiveUrl);
exit;
