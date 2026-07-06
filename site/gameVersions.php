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
          // Vamos exibir o array $versions do config.php.
          // Classificação stable/experimental segue o jogo base (Factorio alpha),
          // igual ao site oficial: estável em branco/negrito, experimental em cinza.
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
