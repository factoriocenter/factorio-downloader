<?php
require_once 'theme.php';
?>
<!DOCTYPE html>
<html>
  <head>
    <?php require_once ('./site/header.php'); ?>
  </head>
  <body>
    <?php require_once ('./site/menu.php'); ?>
    <div class="container">
      <?php require_once ('./site/gameVersions.php'); ?>
      <div class="container">
        <div class="container-inner">
          <div class="small-center" id="flashed-messages"></div>
          <?php require_once ('./site/downloadSpaceAge.php'); ?>
          <?php require_once ('./site/downloadFactorio.php'); ?>
          <div class="flex panels2">
            <?php require_once ('./site/downloadDemo.php'); ?>
            <?php require_once ('./site/downloadServer.php'); ?>
          </div>
        </div>
      </div>
      <?php require_once ('./site/footer.php'); ?>
    </div>
  </body>
</html>
