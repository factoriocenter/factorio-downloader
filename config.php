<?php
/**
 * config.php
 *
 * This configuration file loads environment variables from the .env file and the
 * available Factorio versions from versions.json.
 *
 * Environment loading is dependency-optional: if Composer's vlucas/phpdotenv is
 * installed (vendor/) it is used; otherwise a small built-in parser reads .env.
 * This lets the app run on a plain host (e.g. cPanel via git) without running
 * `composer install`.
 */

// Minimal .env parser used when vlucas/phpdotenv is not installed. Mirrors the
// key behaviour we rely on: KEY=VALUE lines, comments/blank lines ignored, and
// existing environment variables are never overwritten (like createImmutable).
if (!function_exists('factorio_load_env')) {
    function factorio_load_env(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip a single layer of matching surrounding quotes.
            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[strlen($value) - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            if ($key === '' || getenv($key) !== false || isset($_ENV[$key])) {
                continue;
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Prefer Composer's Dotenv when available; fall back to the built-in parser.
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
// Use phpdotenv only if it is present AND recent enough (createImmutable exists
// since v4). An old/incompatible vendor/ falls back to the built-in parser
// instead of fataling.
if (class_exists(\Dotenv\Dotenv::class) && method_exists(\Dotenv\Dotenv::class, 'createImmutable')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
} else {
    factorio_load_env(__DIR__ . '/.env');
}

// Credentials from the .env file
$FACTORIO_LOGIN    = $_ENV['FACTORIO_LOGIN'] ?? 'your_login';
$FACTORIO_PASSWORD = $_ENV['FACTORIO_PASSWORD'] ?? 'your_password';
$FACTORIO_PD       = $_ENV['FACTORIO_PD'] ?? '';

// ------------------------------------------------------------------
// Version data (auto-generated)
// ------------------------------------------------------------------
// The version lists below are NO LONGER edited by hand. They are loaded from
// versions.json, which is kept up to date automatically by the daily GitHub
// Action (.github/workflows/update-versions.yml -> scripts/update-versions.php).
//
// Structure of versions.json (one block per distro + a master display list):
//   {
//     "factorio": { "stable": [...], "experimental": [...] },  // build "alpha"
//     "demo":     { "stable": [...], "experimental": [...] },  // build "demo"
//     "server":   { "stable": [...], "experimental": [...] },  // build "headless"
//     "spaceage": { "stable": [...], "experimental": [...] },  // build "expansion"
//     "all":      ["2.1.9", ..., "0.6.4"]                      // descending
//   }
//
// This file only READS that data and rebuilds the exact same variables the rest
// of the site already relies on ($valid*/$experimental*/$versions/$default*), so
// theme.php, site/* and download.php require no changes.

// The site pulls the latest versions.json straight from GitHub (where the daily
// Action commits it) and caches it locally for a short TTL, so new Factorio
// releases appear automatically with no redeploy. On any failure it falls back to
// the last cached copy, then to the versions.json shipped with the repo. Set
// FACTORIO_VERSIONS_REMOTE=0 to use only the local file.
$versionsFile  = __DIR__ . '/versions.json';        // committed fallback
$versionsCache = __DIR__ . '/versions.cache.json';  // remote cache (git-ignored)
$versionsUrl   = getenv('FACTORIO_VERSIONS_URL')
    ?: 'https://raw.githubusercontent.com/factoriocenter/factorio-downloader/main/versions.json';
$versionsTtl   = (int) (getenv('FACTORIO_VERSIONS_TTL') ?: 3600); // seconds
$versionsFresh = null; // body just fetched this request, if any

if (getenv('FACTORIO_VERSIONS_REMOTE') !== '0' && $versionsUrl !== '' && function_exists('curl_init')) {
    $cacheIsFresh = is_file($versionsCache) && (time() - filemtime($versionsCache)) < $versionsTtl;
    if (!$cacheIsFresh) {
        $ch = curl_init($versionsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_USERAGENT      => 'factorio-downloader/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body !== false && $code === 200 && is_array(json_decode($body, true))) {
            $versionsFresh = $body;
            @file_put_contents($versionsCache, $body, LOCK_EX);   // refresh cache
        } elseif (is_file($versionsCache)) {
            @touch($versionsCache);                                // don't hammer on failure
        }
    }
}

// Prefer the just-fetched body, then the cache, then the committed file.
$versionsRaw = $versionsFresh;
if (($versionsRaw === null || $versionsRaw === false || $versionsRaw === '') && is_file($versionsCache)) {
    $versionsRaw = @file_get_contents($versionsCache);
}
if ($versionsRaw === null || $versionsRaw === false || $versionsRaw === '') {
    $versionsRaw = @file_get_contents($versionsFile);
}
if ($versionsRaw === false || $versionsRaw === null) {
    die("config.php: could not read versions data ($versionsFile).");
}
$versionData = json_decode($versionsRaw, true);
if (!is_array($versionData)) {
    // Cache/remote may be corrupt; fall back to the committed file.
    $versionData = json_decode((string) @file_get_contents($versionsFile), true);
}
if (!is_array($versionData)) {
    die("config.php: versions data is missing or not valid JSON.");
}

// Small helpers scoped to the loader.
if (!function_exists('factorio_versions_get')) {
    // Safely read a distro/channel list from the decoded data.
    function factorio_versions_get(array $data, string $distro, string $channel): array {
        return isset($data[$distro][$channel]) && is_array($data[$distro][$channel])
            ? array_values($data[$distro][$channel])
            : [];
    }
}
if (!function_exists('factorio_versions_latest_stable')) {
    // Highest stable version for a distro (used as the default selection).
    function factorio_versions_latest_stable(array $data, string $distro, string $fallback): string {
        $stable = factorio_versions_get($data, $distro, 'stable');
        if (empty($stable)) {
            return $fallback;
        }
        usort($stable, 'version_compare');
        return end($stable);
    }
}

// Rebuild the per-distro arrays consumed by theme.php.
$validFactorioVersions        = factorio_versions_get($versionData, 'factorio', 'stable');
$experimentalFactorioVersions = factorio_versions_get($versionData, 'factorio', 'experimental');
$validDemoVersions            = factorio_versions_get($versionData, 'demo', 'stable');
$experimentalDemoVersions     = factorio_versions_get($versionData, 'demo', 'experimental');
$validServerVersions          = factorio_versions_get($versionData, 'server', 'stable');
$experimentalServerVersions   = factorio_versions_get($versionData, 'server', 'experimental');
$validSpaceAgeVersions        = factorio_versions_get($versionData, 'spaceage', 'stable');
$experimentalSpaceAgeVersions = factorio_versions_get($versionData, 'spaceage', 'experimental');

// Master display list (descending). Start from "all" and union in every distro
// list, so nothing can disappear from the page even if the data ever diverges.
$versions = isset($versionData['all']) && is_array($versionData['all']) ? $versionData['all'] : [];
$versions = array_merge(
    $versions,
    $validFactorioVersions, $experimentalFactorioVersions,
    $validDemoVersions, $experimentalDemoVersions,
    $validServerVersions, $experimentalServerVersions,
    $validSpaceAgeVersions, $experimentalSpaceAgeVersions
);
$versions = array_values(array_unique($versions));
usort($versions, fn($a, $b) => version_compare($b, $a)); // descending

// ------------------------------------------------------------------
// Defaults for each category (highest stable release per distro)
// ------------------------------------------------------------------
$defaultFactorioVersion = factorio_versions_latest_stable($versionData, 'factorio', '2.0.77');
$defaultDemoVersion     = factorio_versions_latest_stable($versionData, 'demo', '2.0.77');
$defaultServerVersion   = factorio_versions_latest_stable($versionData, 'server', '2.0.77');
$defaultSpaceAgeVersion = factorio_versions_latest_stable($versionData, 'spaceage', '2.0.77');
