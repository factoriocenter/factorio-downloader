<?php
// site/downloadSpaceAge.php
if (!$showSpaceAge) return;

// Detect OS (default to Windows)
$userOs = 'win64';
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    if (stripos($ua, 'Linux') !== false) {
        $userOs = 'linux64';
    } elseif (stripos($ua, 'Mac') !== false || stripos($ua, 'Darwin') !== false) {
        $userOs = 'osx';
    }
}

// Set OS icon and big button text dynamically
switch ($userOs) {
    case 'osx':
        $osIcon = 'fab fa-apple';
        $downloadSpaceAgeBigText = "Download Space Age for macOS";
        break;
    case 'linux64':
        $osIcon = 'fab fa-linux';
        $downloadSpaceAgeBigText = "Download Space Age for Linux";
        break;
    default:
        $osIcon = 'fab fa-windows';
        $downloadSpaceAgeBigText = "Download Space Age for Windows";
        break;
}
?>
<div class="flex-column mt0 panel type-expansion">
  <h2 class="flex flex-space-between">
    <div><?php echo $spaceAgeSectionTitle; ?></div>
    <div class="download-version"><?php echo $spaceAgeLabel; ?> - <?php echo $currentSpaceAgeVersion; ?></div>
  </h2>
  <div class="panels2 flex-grow mh350">
    <div class="panel-inset m0 p0 download-banner position-relative">
      <div class="download-banner-2">
        <img src="https://factorio.com/static/img/space-age-capsule.png" />
      </div>
      <div class="shadow-overlay"></div>
    </div>
    <div class="flex-column flex-grow">
      <div class="flex m0 flex-column flex-grow panel-inset-lighter">
        <p><?php echo $spaceAgeSectionDescription; ?></p>
        <!-- Big button uses detected OS -->
        <div class="center margin-auto">
          <a href="download.php?ver=<?php echo urlencode($currentSpaceAgeVersion); ?>&build=expansion&target=<?php echo $userOs; ?>"
             class="button-green download-button-type-expansion download mt12">
            <div class="download-icon-container download-icon-type-expansion-container">
              <i class="<?php echo $osIcon; ?>"></i> <?php echo $downloadSpaceAgeBigText; ?>
            </div>
          </a>
        </div>
      </div>
      <!-- Icon panel -->
      <div class="panel-inset fs0 mb0 p4 text-right">
        <div class="flex flex-space-between flex-align-items-center">
          <div class="mr8 text-center">
            <strong><?php echo $spaceAgeLabel; ?></strong><br />
            <?php echo $currentSpaceAgeVersion; ?>
          </div>
          <div class="flex flex-space-between flex-align-items-center">
            <div class="flex-wrap inline-flex">
              <?php
                $platforms = [
                    [
                        'target' => 'win64-manual',
                        'icon'   => 'fab fa-windows',
                        'label'  => '.zip',
                        'tooltip'=> $spaceAgeTooltipWinZip
                    ],
                    [
                        'target' => 'win64',
                        'icon'   => 'fab fa-windows',
                        'label'  => '',
                        'tooltip'=> $spaceAgeTooltipWin
                    ],
                    [
                        'target' => 'osx',
                        'icon'   => 'fab fa-apple',
                        'label'  => '',
                        'tooltip'=> $spaceAgeTooltipMac
                    ],
                    [
                        'target' => 'linux64',
                        'icon'   => 'fab fa-linux',
                        'label'  => '',
                        'tooltip'=> $spaceAgeTooltipLinux
                    ],
                ];
                foreach ($platforms as $pf) {
                    $highlightClass = ($pf['target'] === $userOs ||
                        ($pf['target'] === 'win64-manual' && $userOs === 'win64')) ? ' detected-os' : '';
                    ?>
                    <a href="download.php?ver=<?php echo urlencode($currentSpaceAgeVersion); ?>&build=expansion&target=<?php echo $pf['target']; ?>"
                       class="button-green download-square download-button-type-expansion<?php echo $highlightClass; ?>">
                        <div class="download-icon-container download-icon-type-expansion-container">
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
