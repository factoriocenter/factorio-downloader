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

$versionsFile = __DIR__ . '/versions.json';
$versionsRaw  = @file_get_contents($versionsFile);
if ($versionsRaw === false) {
    die("config.php: could not read versions.json ($versionsFile). "
      . "Run scripts/update-versions.php to generate it.");
}
$versionData = json_decode($versionsRaw, true);
if (!is_array($versionData)) {
    die("config.php: versions.json is missing or not valid JSON.");
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
