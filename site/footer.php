<?php require_once './strings.php'; ?>
<br><br><br><br>
<div class="footer">
  <div class="panel footer-inner">
    <div class="flex m0 flex-center flex-wrap footer-links panel-inset">
      <?php echo $footerText1; ?>
    </div>
    <div class="panel-inset m0 p0 footer-rocket">
      <div class="rocket" id="rocket"></div>
      <div class="shadow-overlay"></div>
      <div class="shadow-overlay-bottom"></div>
    </div>
    <div class="panel-inset m0 footer-copyright">
      <?php echo $footerText2; ?>
    </div>
  </div>
</div>
<script
  src="https://factorio.com/static/js/factorio.js?v=89525277"></script>
<script>
  document.body.addEventListener("htmx:configRequest", (event) => {
    event.detail.headers["X-CsRFToken"] =
      "imiyNTu1MGZlYWE3MjNiMzq4NDhlMTc4ODg2OTFjY2JlYjawMjbhYjqi.Z3_46a.pWpVbOqamuN5ZeGpapbV5RlrsOq";
  });
</script>
