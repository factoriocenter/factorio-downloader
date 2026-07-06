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
 * Two promotion situations, both handled (see factorio_compute_update):
 *   1. A version that was experimental becomes stable. latest-releases then lists
 *      it under BOTH "experimental" and "stable"; it is moved to stable and taken
 *      out of experimental (it is NOT re-added as experimental).
 *   2. A newer version Y is released straight as stable while an older X stays
 *      experimental. Y goes to stable, X remains in experimental.
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
 *
 * Testability: defining FACTORIO_UPDATER_LIB before including this file loads the
 * helpers and factorio_compute_update() without running the fetch/write "main".
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

/**
 * Pure core: given the current data plus the two API payloads, return the updated
 * data and a human-readable list of what changed. No I/O, so it is easy to test.
 *
 * @return array{data: array, changes: string[]}
 */
function factorio_compute_update(array $data, array $latest, array $available): array
{
    // Ensure the expected shape so callers can pass partial/empty data safely.
    foreach (array_keys(DISTROS) as $d) {
        if (!isset($data[$d]['stable']) || !is_array($data[$d]['stable'])) {
            $data[$d]['stable'] = [];
        }
        if (!isset($data[$d]['experimental']) || !is_array($data[$d]['experimental'])) {
            $data[$d]['experimental'] = [];
        }
    }

    $changes = [];

    // Snapshot the highest headless version we know BEFORE mutating, so the safety
    // net only ever injects genuinely new versions (never old incremental patches).
    $serverMaxBefore = highest_version(
        array_merge($data['server']['stable'], $data['server']['experimental'])
    );

    // Per-distro deltas from latest-releases. The promotion (stable) block runs
    // BEFORE the new-experimental block: when a version is listed under both
    // channels, it lands in stable and the experimental check then skips it.
    foreach (DISTROS as $distro => $build) {
        $stableHead = $latest['stable'][$build] ?? null;
        $experimentalHead = $latest['experimental'][$build] ?? null;

        // Promotion / new stable: the current stable head is not in our stable list.
        if (is_valid_version($stableHead) && !in_array($stableHead, $data[$distro]['stable'], true)) {
            $data[$distro]['stable'][] = $stableHead;
            $wasExperimental = in_array($stableHead, $data[$distro]['experimental'], true);
            if ($wasExperimental) {
                // Case 1: experimental -> stable. Remove it from experimental.
                $data[$distro]['experimental'] = array_values(array_filter(
                    $data[$distro]['experimental'],
                    fn($v) => $v !== $stableHead
                ));
                $changes[] = "$distro: promoted $stableHead (experimental -> stable)";
            } else {
                // Case 2: a newer version shipped straight as stable.
                $changes[] = "$distro: +stable $stableHead";
            }
        }

        // New experimental: the current experimental head is unknown to us.
        if (is_valid_version($experimentalHead) && !distro_has($data, $distro, $experimentalHead)) {
            $data[$distro]['experimental'][] = $experimentalHead;
            $changes[] = "$distro: +experimental $experimentalHead";
        }
    }

    // Safety net: catch recent headless versions missed between runs. Only versions
    // newer than what we already had are considered, so the updater's full historical
    // patch list can never pollute the curated data.
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

    // Normalize each list and rebuild the master display list.
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

    return ['data' => $out, 'changes' => $changes];
}

// ---------------------------------------------------------------------------
// Main (skipped when the file is included as a library for tests)
// ---------------------------------------------------------------------------
if (!defined('FACTORIO_UPDATER_LIB')) {
    $root = dirname(__DIR__);
    $file = $root . '/versions.json';

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

    try {
        $latest = http_get_json(LATEST_RELEASES_URL);
        $available = http_get_json(AVAILABLE_VERSIONS_URL);
    } catch (Throwable $e) {
        fwrite(STDERR, 'update-versions: ' . $e->getMessage() . "\n");
        exit(1);
    }

    $result = factorio_compute_update($data, $latest, $available);
    $changes = $result['changes'];
    $newJson = json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
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
        file_put_contents($ghOutput, 'delta=' . str_replace(["\r", "\n"], ' ', $deltaSummary) . "\n", FILE_APPEND);
    }

    exit(0);
}
