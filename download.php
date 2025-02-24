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
 *  - cacert.pem is downloaded/stored in a writable directory (e.g. ./certs).
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

// Attempt to read fallback token from environment
$fallbackToken = $_ENV['FACTORIO_TOKEN_FALLBACK'] ?? '';

// If we have a fallback token, use it immediately
$token = $fallbackToken;

// If we do not have a fallback token, and the build requires auth, attempt direct auth
if (empty($token) && in_array($build, ['alpha', 'expansion'])) {
    if (empty($password)) {
        die("No password defined for $login.");
    }
    // Attempt to authenticate with Factorio’s API
    $authUrl = "https://auth.factorio.com/api-login?require_game_ownership=true&username=" .
               urlencode($login) . "&password=" . urlencode($password);
    $ch = curl_init($authUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $authResponse = curl_exec($ch);
    if (curl_errno($ch)) {
        die("Authentication error: " . curl_error($ch));
    }
    curl_close($ch);

    // Parse response
    $authData = json_decode($authResponse, true);
    if (!$authData) {
        die("Authentication error: unable to parse auth response.");
    }
    // Check if there's an error (e.g., invalid credentials or game not owned)
    if (isset($authData['error'])) {
        die("Authentication failed: " . $authData['error']);
    }
    // Assume token is the first value in the returned JSON
    foreach ($authData as $value) {
        $token = $value;
        break;
    }
    if (empty($token)) {
        die("Failed to obtain token for $login.");
    }
}

// --- 3. Build the Download URL ---
$factorioUrl = "https://www.factorio.com/get-download/{$version}/{$build}/{$target}";
if (!empty($token)) {
    // If we have a token (from fallback or direct auth), append it
    $factorioUrl .= "?username=" . urlencode($login) . "&token=" . urlencode($token);
}

// --- 4. Prepare cacert.pem in a writable directory
$certDir = __DIR__ . '/certs';
if (!is_dir($certDir)) {
    mkdir($certDir, 0755, true);
}
$caBundle = $certDir . '/cacert.pem';
if (!file_exists($caBundle)) {
    $cacertUrl = 'https://curl.se/ca/cacert.pem';
    $cacertData = file_get_contents($cacertUrl);
    if ($cacertData !== false) {
        file_put_contents($caBundle, $cacertData);
    } else {
        error_log("Could not download cacert.pem; SSL verification may fail.");
        $caBundle = false;
    }
}

// --- 5. Get the Effective URL ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $factorioUrl);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Optional: set a custom User-Agent
curl_setopt($ch, CURLOPT_USERAGENT, "FactorioDownloader/1.0 (Docker)");

if ($caBundle !== false) {
    curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
} else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
}

curl_exec($ch);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

if (curl_errno($ch)) {
    die("Error obtaining effective URL: " . curl_error($ch));
}
curl_close($ch);

if (!$effectiveUrl) {
    die("Failed to obtain effective URL.");
}

// Debug (uncomment if needed):
// echo "Effective URL: " . htmlspecialchars($effectiveUrl);
// exit;

// --- 6. Redirect the Browser ---
header("Location: " . $effectiveUrl);
exit;
