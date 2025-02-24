<?php
// site/downloadFactorio.php
if (!$showFactorio) return;

// Detect OS do usuário
$userOs = 'win64'; // default
if (isset($_SERVER['HTTP_USER_AGENT'])) {
  $ua = $_SERVER['HTTP_USER_AGENT'];
  if (stripos($ua, 'Mac') !== false || stripos($ua, 'Darwin') !== false) {
    $userOs = 'osx';
  } elseif (stripos($ua, 'Linux') !== false) {
    $userOs = 'linux64';
  }
}

// Definir ícone para o botão grande de acordo com o OS
$osIcon = 'fab fa-windows';
if ($userOs === 'osx') {
  $osIcon = 'fab fa-apple';
} elseif ($userOs === 'linux64') {
  $osIcon = 'fab fa-linux';
}
?>
<div class="flex-column mt0 panel type-alpha">
  <h2 class="flex flex-space-between">
    <div><?php echo $factorioSectionTitle; ?></div>
    <div class="download-version"><?php echo $factorioLabel; ?> - <?php echo $currentFactorioVersion; ?></div>
  </h2>
  <div class="panels2 flex-grow mh350">
    <div class="panel-inset m0 p0 download-banner position-relative">
      <div class="download-banner-2">
        <img src="https://cdn.factorio.com/assets/img/web/factorio-cover-gds-2019-rgb.jpg" />
      </div>
      <div class="shadow-overlay"></div>
    </div>
    <div class="flex-column flex-grow">
      <div class="flex m0 flex-column flex-grow panel-inset-lighter">
        <p><?php echo $factorioSectionDescription; ?></p>
        <!-- Big button usa o OS detectado -->
        <div class="center margin-auto">
          <a href="download.php?ver=<?php echo urlencode($currentFactorioVersion); ?>&build=alpha&target=<?php echo $userOs; ?>"
             class="button-green download-button-type-alpha download mt12">
            <div class="download-icon-container download-icon-type-alpha-container">
              <i class="<?php echo $osIcon; ?>"></i> <?php echo $downloadFactorioWindowsBig; ?>
            </div>
          </a>
        </div>
      </div>
      <!-- Painel de ícones -->
      <div class="panel-inset fs0 mb0 p4 text-right">
        <div class="flex flex-space-between flex-align-items-center">
          <div class="mr8 text-center">
            <strong><?php echo $factorioLabel; ?></strong><br />
            <?php echo $currentFactorioVersion; ?>
          </div>
          <div class="flex flex-space-between flex-align-items-center">
            <div class="flex-wrap inline-flex">
              <?php
              $platforms = [
                [
                  'target' => 'win64-manual',
                  'icon'   => 'fab fa-windows',
                  'label'  => '.zip',
                  'tooltip'=> $factorioTooltipWinZip
                ],
                [
                  'target' => 'win64',
                  'icon'   => 'fab fa-windows',
                  'label'  => '',
                  'tooltip'=> $factorioTooltipWin
                ],
                [
                  'target' => 'osx',
                  'icon'   => 'fab fa-apple',
                  'label'  => '',
                  'tooltip'=> $factorioTooltipMac
                ],
                [
                  'target' => 'linux64',
                  'icon'   => 'fab fa-linux',
                  'label'  => '',
                  'tooltip'=> $factorioTooltipLinux
                ],
              ];

              foreach ($platforms as $pf) {
                $highlightClass = ($pf['target'] === $userOs ||
                  ($pf['target'] === 'win64-manual' && $userOs === 'win64')) ? ' detected-os' : '';
                ?>
                <a href="download.php?ver=<?php echo urlencode($currentFactorioVersion); ?>&build=alpha&target=<?php echo $pf['target']; ?>"
                   class="button-green download-square download-button-type-alpha<?php echo $highlightClass; ?>">
                  <div class="download-icon-container download-icon-type-alpha-container">
                    <i class="<?php echo $pf['icon']; ?>"></i>
                    <?php if (!empty($pf['label'])): ?>
                      <div class="download-icon-dotzip"><?php echo $pf['label']; ?></div>
                    <?php endif; ?>
                  </div>
                </a>
                <div class="tooltip" role="tooltip">
                  <div class="panel-tooltip">
                    <p><?php echo htmlspecialchars($pf['tooltip']); ?></p>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
