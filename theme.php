<?php
/**
 * theme.php
 *
 * - Loads config.php and strings.php
 * - Captures the selected version via ?ver=
 * - Determines whether each section should be shown ($showFactorio, etc.)
 * - Defines $currentFactorioVersion, etc.
 * - Checks whether the version is experimental for each category
 * - Defines $factorioLabel, $demoLabel, etc.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/strings.php';

// Captures and trims the ?ver= parameter
$selectedVersion = isset($_GET['ver']) ? trim($_GET['ver']) : '';

// If no version is selected, use the defaults and show all sections
if (empty($selectedVersion)) {
    $currentFactorioVersion = $defaultFactorioVersion;
    $currentDemoVersion     = $defaultDemoVersion;
    $currentServerVersion   = $defaultServerVersion;
    $currentSpaceAgeVersion = $defaultSpaceAgeVersion;
    $showFactorio = true;
    $showDemo     = true;
    $showServer   = true;
    $showSpaceAge = true;
} else {
    // Now we also consider experimental versions
    $showFactorio = in_array($selectedVersion, $validFactorioVersions) || in_array($selectedVersion, $experimentalFactorioVersions);
    $showDemo     = in_array($selectedVersion, $validDemoVersions)     || in_array($selectedVersion, $experimentalDemoVersions);
    $showServer   = in_array($selectedVersion, $validServerVersions)   || in_array($selectedVersion, $experimentalServerVersions);
    $showSpaceAge = in_array($selectedVersion, $validSpaceAgeVersions) || in_array($selectedVersion, $experimentalSpaceAgeVersions);

    $currentFactorioVersion = $showFactorio ? $selectedVersion : $defaultFactorioVersion;
    $currentDemoVersion     = $showDemo     ? $selectedVersion : $defaultDemoVersion;
    $currentServerVersion   = $showServer   ? $selectedVersion : $defaultServerVersion;
    $currentSpaceAgeVersion = $showSpaceAge ? $selectedVersion : $defaultSpaceAgeVersion;
}

// Functions to determine whether a version is experimental in each category
if (!function_exists('isFactorioExperimental')) {
    function isFactorioExperimental($ver) {
        global $experimentalFactorioVersions;
        return is_array($experimentalFactorioVersions) && in_array($ver, $experimentalFactorioVersions);
    }
}
if (!function_exists('isDemoExperimental')) {
    function isDemoExperimental($ver) {
        global $experimentalDemoVersions;
        return is_array($experimentalDemoVersions) && in_array($ver, $experimentalDemoVersions);
    }
}
if (!function_exists('isServerExperimental')) {
    function isServerExperimental($ver) {
        global $experimentalServerVersions;
        return is_array($experimentalServerVersions) && in_array($ver, $experimentalServerVersions);
    }
}
if (!function_exists('isSpaceAgeExperimental')) {
    function isSpaceAgeExperimental($ver) {
        global $experimentalSpaceAgeVersions;
        return is_array($experimentalSpaceAgeVersions) && in_array($ver, $experimentalSpaceAgeVersions);
    }
}

// Defines the labels (Stable/Experimental) for each section
$factorioLabel = isFactorioExperimental($currentFactorioVersion) ? $experimentalLabel : $stableLabel;
$demoLabel     = isDemoExperimental($currentDemoVersion)         ? $experimentalLabel : $stableLabel;
$serverLabel   = isServerExperimental($currentServerVersion)     ? $experimentalLabel : $stableLabel;
$spaceAgeLabel = isSpaceAgeExperimental($currentSpaceAgeVersion) ? $experimentalLabel : $stableLabel;
?>
