<?php
/**
 * tests/update-versions-test.php
 *
 * Plain-PHP tests (no framework) for the version delta logic in
 * scripts/update-versions.php. Run with:
 *
 *     php tests/update-versions-test.php
 *
 * Uses a fixed inline fixture (not the live versions.json), so it stays valid
 * regardless of which versions are current.
 */

define('FACTORIO_UPDATER_LIB', 1);
require __DIR__ . '/../scripts/update-versions.php';

$fails = 0;
function check(string $label, bool $cond): void
{
    global $fails;
    echo ($cond ? "  ok  " : "  FAIL ") . $label . "\n";
    if (!$cond) {
        $fails++;
    }
}

// Fixed fixture: 2.1.8/2.1.9 experimental, 2.0.76/2.0.77 stable.
function fixture(): array
{
    return [
        'factorio' => ['stable' => ['2.0.76', '2.0.77'], 'experimental' => ['2.1.8', '2.1.9']],
        'demo'     => ['stable' => ['2.0.77'],           'experimental' => []],
        'server'   => ['stable' => ['2.0.77'],           'experimental' => ['2.1.9']],
        'spaceage' => ['stable' => ['2.0.77'],           'experimental' => ['2.1.9']],
        'all'      => ['2.1.9', '2.1.8', '2.0.77', '2.0.76'],
    ];
}

$noAvail = ['core-linux_headless64' => []]; // isolate the latest-releases logic

// ---------------------------------------------------------------------------
echo "CASO 0: idempotencia (heads ja conhecidos, nada muda)\n";
$latest0 = [
    'stable'       => ['alpha' => '2.0.77', 'demo' => '2.0.77', 'headless' => '2.0.77', 'expansion' => '2.0.77'],
    'experimental' => ['alpha' => '2.1.9',  'demo' => '2.0.77', 'headless' => '2.1.9',  'expansion' => '2.1.9'],
];
$r0 = factorio_compute_update(fixture(), $latest0, $noAvail);
check('nenhuma mudanca reportada', count($r0['changes']) === 0);

// ---------------------------------------------------------------------------
echo "CASO 1: experimental -> stable (versao listada nos dois canais)\n";
$latest1 = [
    'stable'       => ['alpha' => '2.1.9', 'demo' => '2.0.77', 'headless' => '2.0.77', 'expansion' => '2.0.77'],
    'experimental' => ['alpha' => '2.1.9', 'demo' => '2.0.77', 'headless' => '2.1.9',  'expansion' => '2.1.9'],
];
$r1 = factorio_compute_update(fixture(), $latest1, $noAvail);
$f1 = $r1['data']['factorio'];
check('2.1.9 agora em stable',               in_array('2.1.9', $f1['stable'], true));
check('2.1.9 removido de experimental',      !in_array('2.1.9', $f1['experimental'], true));
check('2.1.8 permanece em experimental',     in_array('2.1.8', $f1['experimental'], true));
check('log contem "promoted 2.1.9"',         (bool) array_filter($r1['changes'], fn($c) => str_contains($c, 'promoted 2.1.9')));

// ---------------------------------------------------------------------------
echo "CASO 2: versao Y posterior sai direto como stable, X fica experimental\n";
$latest2 = [
    'stable'       => ['alpha' => '2.1.10', 'demo' => '2.0.77', 'headless' => '2.0.77', 'expansion' => '2.0.77'],
    'experimental' => ['alpha' => '2.1.10', 'demo' => '2.0.77', 'headless' => '2.1.9',  'expansion' => '2.1.9'],
];
$r2 = factorio_compute_update(fixture(), $latest2, $noAvail);
$f2 = $r2['data']['factorio'];
check('2.1.10 em stable',                    in_array('2.1.10', $f2['stable'], true));
check('2.1.9 continua em experimental',      in_array('2.1.9', $f2['experimental'], true));
check('2.1.10 NAO em experimental',          !in_array('2.1.10', $f2['experimental'], true));
check('log contem "+stable 2.1.10"',         (bool) array_filter($r2['changes'], fn($c) => str_contains($c, '+stable 2.1.10')));

// ---------------------------------------------------------------------------
echo "CASO 3: novo experimental e adicionado\n";
$latest3 = [
    'stable'       => ['alpha' => '2.0.77', 'demo' => '2.0.77', 'headless' => '2.0.77', 'expansion' => '2.0.77'],
    'experimental' => ['alpha' => '2.1.10', 'demo' => '2.0.77', 'headless' => '2.1.9',  'expansion' => '2.1.9'],
];
$r3 = factorio_compute_update(fixture(), $latest3, $noAvail);
check('2.1.10 adicionado em factorio.experimental', in_array('2.1.10', $r3['data']['factorio']['experimental'], true));
check('log contem "+experimental 2.1.10"',          (bool) array_filter($r3['changes'], fn($c) => str_contains($c, '+experimental 2.1.10')));

echo "\n" . ($fails === 0 ? "TODOS OS TESTES PASSARAM\n" : "$fails ASSERCOES FALHARAM\n");
exit($fails === 0 ? 0 : 1);
