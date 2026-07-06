<?php
/**
 * scripts/update-versions.php
 *
 * Keeps versions.json up to date from Factorio's official APIs. Run daily by
 * .github/workflows/update-versions.yml (and manually via `php scripts/update-versions.php`).
 *
 * State lives entirely in versions.json. Each run applies deltas only:
 *   - New experimental release for a distro  -> added to its "experimental" list.
 *   - Promotion (experimental -> stable)     -> moved into its "stable" list.
 * Nothing is ever removed except when a version is promoted from experimental to
 * stable, so no version is lost.
 *
 * Data sources:
 *   - https://factorio.com/api/latest-releases        -> current stable/experimental
 *                                                        head of each distro.
 *   - https://updater.factorio.com/get-available-versions -> full headless history;
 *                                                        used as a safety net to catch
 *                                                        recent versions missed between
 *                                                        runs (only versions newer than
 *                                                        the highest we already know, so
 *                                                        old incremental patches are never
 *                                                        injected).
 *
 * The script writes versions.json only when something actually changed, so the
 * GitHub Action produces a commit only on a real update.
 */

const LATEST_RELEASES_URL = 'https://factorio.com/api/latest-releases';
const AVAILABLE_VERSIONS_URL = 'https://updater.factorio.com/get-available-versions';

// distro (versions.json key) => build name (latest-releases key)
const DISTROS = [
    'factorio' => 'alpha',
    'demo'     => 'demo',
    'server'   => 'headless',
    'spaceage' => 'expansion',
];

const HEADLESS_PACKAGE = 'core-linux_headless64';

$root = dirname(__DIR__);
$file = $root . '/versions.json';

// ---------------------------------------------------------------------------
// Load current state
// ---------------------------------------------------------------------------
$raw = @file_get_contents($file);
if ($raw === false) {
    fwrite(STDERR, "update-versions: cannot read $file\n");
    exit(1);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "update-versions: versions.json is not valid JSON\n");
    exit(1);
}
foreach (array_keys(DISTROS) as $d) {
    if (!isset($data[$d]['stable']) || !is_array($data[$d]['stable'])) {
        $data[$d]['stable'] = [];
    }
    if (!isset($data[$d]['experimental']) || !is_array($data[$d]['experimental'])) {
        $data[$d]['experimental'] = [];
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function http_get_json(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'factorio-downloader-updater/1.0 (+github-actions)',
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("request failed for $url: $err");
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("unexpected HTTP $status for $url");
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException("invalid JSON from $url");
    }
    return $json;
}

function is_valid_version($v): bool
{
    return is_string($v) && preg_match('/^\d+\.\d+\.\d+$/', $v) === 1;
}

function distro_has(array $data, string $distro, string $version): bool
{
    return in_array($version, $data[$distro]['stable'], true)
        || in_array($version, $data[$distro]['experimental'], true);
}

function highest_version(array $versions): string
{
    $max = '0.0.0';
    foreach ($versions as $v) {
        if (version_compare($v, $max, '>')) {
            $max = $v;
        }
    }
    return $max;
}

function uniq_sorted_asc(array $versions): array
{
    $versions = array_values(array_unique($versions));
    usort($versions, 'version_compare');
    return $versions;
}

// ---------------------------------------------------------------------------
// Fetch data
// ---------------------------------------------------------------------------
try {
    $latest = http_get_json(LATEST_RELEASES_URL);
    $available = http_get_json(AVAILABLE_VERSIONS_URL);
} catch (Throwable $e) {
    fwrite(STDERR, 'update-versions: ' . $e->getMessage() . "\n");
    exit(1);
}

$changes = [];

// Snapshot the highest headless version we know BEFORE mutating, so the safety
// net only ever injects genuinely new versions (never old incremental patches).
$serverMaxBefore = highest_version(
    array_merge($data['server']['stable'], $data['server']['experimental'])
);

// ---------------------------------------------------------------------------
// Apply per-distro deltas from latest-releases
// ---------------------------------------------------------------------------
foreach (DISTROS as $distro => $build) {
    $stableHead = $latest['stable'][$build] ?? null;
    $experimentalHead = $latest['experimental'][$build] ?? null;

    // Promotion / new stable: the current stable head is not in our stable list.
    if (is_valid_version($stableHead) && !in_array($stableHead, $data[$distro]['stable'], true)) {
        $data[$distro]['stable'][] = $stableHead;
        $wasExperimental = in_array($stableHead, $data[$distro]['experimental'], true);
        if ($wasExperimental) {
            $data[$distro]['experimental'] = array_values(array_filter(
                $data[$distro]['experimental'],
                fn($v) => $v !== $stableHead
            ));
            $changes[] = "$distro: promoted $stableHead (experimental -> stable)";
        } else {
            $changes[] = "$distro: +stable $stableHead";
        }
    }

    // New experimental: the current experimental head is unknown to us.
    if (is_valid_version($experimentalHead) && !distro_has($data, $distro, $experimentalHead)) {
        $data[$distro]['experimental'][] = $experimentalHead;
        $changes[] = "$distro: +experimental $experimentalHead";
    }
}

// ---------------------------------------------------------------------------
// Safety net: catch recent headless versions missed between runs.
// Only versions newer than what we already had are considered, so the updater's
// full historical patch list can never pollute the curated data.
// ---------------------------------------------------------------------------
if (isset($available[HEADLESS_PACKAGE]) && is_array($available[HEADLESS_PACKAGE])) {
    $headlessTos = [];
    foreach ($available[HEADLESS_PACKAGE] as $entry) {
        if (isset($entry['to']) && is_valid_version($entry['to'])) {
            $headlessTos[] = $entry['to'];
        }
    }
    $headlessTos = uniq_sorted_asc($headlessTos);
    foreach ($headlessTos as $v) {
        if (version_compare($v, $serverMaxBefore, '>') && !distro_has($data, 'server', $v)) {
            $data['server']['experimental'][] = $v;
            $changes[] = "server: +experimental $v (updater safety net)";
        }
    }
}

// ---------------------------------------------------------------------------
// Normalize and rebuild the master display list
// ---------------------------------------------------------------------------
$out = [];
$allUnion = [];
foreach (array_keys(DISTROS) as $distro) {
    $stable = uniq_sorted_asc($data[$distro]['stable']);
    $experimental = uniq_sorted_asc($data[$distro]['experimental']);
    $out[$distro] = ['stable' => $stable, 'experimental' => $experimental];
    $allUnion = array_merge($allUnion, $stable, $experimental);
}
$allUnion = array_values(array_unique($allUnion));
usort($allUnion, fn($a, $b) => version_compare($b, $a)); // descending
$out['all'] = $allUnion;

// ---------------------------------------------------------------------------
// Write only if something changed
// ---------------------------------------------------------------------------
$newJson = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$changed = ($newJson !== $raw);

if ($changed) {
    if (file_put_contents($file, $newJson) === false) {
        fwrite(STDERR, "update-versions: failed to write $file\n");
        exit(1);
    }
    echo "versions.json updated:\n";
    foreach ($changes as $c) {
        echo "  - $c\n";
    }
} else {
    echo "versions.json: no changes\n";
}

// Machine-readable output for the GitHub Action.
$deltaSummary = $changed
    ? (empty($changes) ? 'update version data' : implode('; ', $changes))
    : '';
$ghOutput = getenv('GITHUB_OUTPUT');
if ($ghOutput !== false && $ghOutput !== '') {
    file_put_contents($ghOutput, 'changed=' . ($changed ? 'true' : 'false') . "\n", FILE_APPEND);
    // Multiline-safe single-line delta.
    file_put_contents($ghOutput, 'delta=' . str_replace(["\r", "\n"], ' ', $deltaSummary) . "\n", FILE_APPEND);
}

exit(0);
