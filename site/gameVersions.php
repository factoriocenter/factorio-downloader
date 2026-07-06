<?php
// site/gameVersions.php
?>
<div class="container-inner">
  <div class="small-center" id="flashed-messages"></div>
  <div class="panel pb0">
    <div class="flex-space-between flex-align-items-center flex-center">
      <h2 class="mb0"><?php echo $headerTitle; ?></h2>
    </div>
    <div class="panel-inset">
      <p class="mb12 flex-center">
        <?php echo $downloadText; ?>
      </p>
      <div class="flex flex-center flex-wrap">
        <?php
          // Display the $versions array from config.php.
          // Stable/experimental classification follows the base game (Factorio alpha),
          // matching the official site: stable in white/bold, experimental in gray.
          foreach ($versions as $ver) {
              $stateClass = isFactorioExperimental($ver) ? 'version-experimental' : 'version-stable';
              echo '<a href="?ver=' . urlencode($ver) . '" class="slot-button-inline ' . $stateClass . '">'
                   . htmlspecialchars($ver) . '</a> ';
          }
        ?>
      </div>
    </div>
  </div>
</div>
