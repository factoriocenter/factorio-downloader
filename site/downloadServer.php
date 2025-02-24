<?php
// site/downloadServer.php
if (!$showServer) {
    return;
}

// Detect OS (se não for Linux, userOs fica em branco só para não destacar nada)
$userOs = '';
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    if (stripos($ua, 'Linux') !== false) {
        $userOs = 'linux64';
    }
}
?>
<div class="flex-column mt0 panel type-headless">
  <h2 class="flex flex-space-between">
    <div><?php echo $serverSectionTitle; ?></div>
    <div class="download-version"><?php echo $serverLabel; ?> - <?php echo $currentServerVersion; ?></div>
  </h2>
  <div class="panels2 flex-grow mh350">
    <div class="flex-column flex-grow">
      <div class="flex m0 flex-column flex-grow panel-inset-lighter">
        <p><?php echo $serverSectionDescription; ?></p>
        <!-- Big button: só aparece se for Linux -->
        <?php if ($userOs === 'linux64'): ?>
          <div class="center margin-auto">
            <a
              href="download.php?ver=<?php echo urlencode($currentServerVersion); ?>&build=headless&target=linux64"
              class="button-green download-button-type-headless download mt12"
            >
              <div class="download-icon-container download-icon-type-headless-container">
                <i class="fab fa-linux"></i>
                <?php echo $downloadServerLinuxBig; ?>
              </div>
            </a>
          </div>
        <?php endif; ?>
      </div>
      <!-- Painel de ícones: somente Linux (headless) -->
      <div class="panel-inset fs0 mb0 p4 text-right">
        <div class="flex flex-space-between flex-align-items-center">
          <div class="mr8 text-center">
            <strong><?php echo $serverLabel; ?></strong><br />
            <?php echo $currentServerVersion; ?>
          </div>
          <div class="flex flex-space-between flex-align-items-center">
            <div class="flex-wrap inline-flex">
              <?php
              // Como só existe Linux, exibimos apenas um botão menor
              $platform = [
                'target' => 'linux64',
                'icon'   => 'fab fa-linux',
                'label'  => '',
                'tooltip'=> $serverTooltipLinux
              ];
              // Se userOs for 'linux64', destacamos
              $highlightClass = ($userOs === 'linux64') ? ' detected-os' : '';
              ?>
              <a
                href="download.php?ver=<?php echo urlencode($currentServerVersion); ?>&build=headless&target=<?php echo $platform['target']; ?>"
                class="button-green download-square download-button-type-headless<?php echo $highlightClass; ?>"
              >
                <div class="download-icon-container download-icon-type-headless-container">
                  <i class="<?php echo $platform['icon']; ?>"></i>
                  <?php if (!empty($platform['label'])): ?>
                    <div class="download-icon-dotzip"><?php echo $platform['label']; ?></div>
                  <?php endif; ?>
                </div>
              </a>
              <div class="tooltip" role="tooltip">
                <div class="panel-tooltip">
                  <p><?php echo htmlspecialchars($platform['tooltip']); ?></p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
