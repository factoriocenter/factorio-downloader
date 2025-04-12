<?php
/**
 * theme.php
 *
 * - Carrega config.php e strings.php
 * - Captura a versão selecionada via ?ver=
 * - Determina se cada seção deve ser exibida ($showFactorio, etc.)
 * - Define $currentFactorioVersion, etc.
 * - Verifica se a versão é experimental para cada categoria
 * - Define $factorioLabel, $demoLabel, etc.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/strings.php';

// Captura e "limpa" o parâmetro ?ver=
$selectedVersion = isset($_GET['ver']) ? trim($_GET['ver']) : '';

// Se nenhuma versão for selecionada, usamos os valores default e mostramos todas as seções
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
    // Agora consideramos também as versões experimentais
    $showFactorio = in_array($selectedVersion, $validFactorioVersions) || in_array($selectedVersion, $experimentalFactorioVersions);
    $showDemo     = in_array($selectedVersion, $validDemoVersions)     || in_array($selectedVersion, $experimentalDemoVersions);
    $showServer   = in_array($selectedVersion, $validServerVersions)   || in_array($selectedVersion, $experimentalServerVersions);
    $showSpaceAge = in_array($selectedVersion, $validSpaceAgeVersions) || in_array($selectedVersion, $experimentalSpaceAgeVersions);

    $currentFactorioVersion = $showFactorio ? $selectedVersion : $defaultFactorioVersion;
    $currentDemoVersion     = $showDemo     ? $selectedVersion : $defaultDemoVersion;
    $currentServerVersion   = $showServer   ? $selectedVersion : $defaultServerVersion;
    $currentSpaceAgeVersion = $showSpaceAge ? $selectedVersion : $defaultSpaceAgeVersion;
}

// Funções para determinar se uma versão é experimental em cada categoria
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

// Define os rótulos (Stable/Experimental) para cada seção
$factorioLabel = isFactorioExperimental($currentFactorioVersion) ? $experimentalLabel : $stableLabel;
$demoLabel     = isDemoExperimental($currentDemoVersion)         ? $experimentalLabel : $stableLabel;
$serverLabel   = isServerExperimental($currentServerVersion)     ? $experimentalLabel : $stableLabel;
$spaceAgeLabel = isSpaceAgeExperimental($currentSpaceAgeVersion) ? $experimentalLabel : $stableLabel;
?>
