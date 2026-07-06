<?php include './config.php'?>
<?php include './strings.php'?>
    
<title>
  <?php echo $headerTitle; ?>
</title>
<link
      href="https://cdn.factorio.com/assets/fonts/titillium-web.css"
      rel="stylesheet"
/>
<link
      href="https://cdn.factorio.com/assets/vendor/fontawesome/css/all.min.css"
      rel="stylesheet"
      crossorigin="anonymous"
/>
<link
      href="https://factorio.com/static/img/favicon.ico"
      rel="icon"
      type="image/x-icon"
/>
<link
      href="https://factorio.com/static/img/favicon.ico"
      rel="shortcut icon"
      type="image/x-icon"
/>
<link
      href="https://factorio.com/static/css/main.css?v=89525277"
      rel="stylesheet"
/>
<meta content="width=device-width" name="viewport" />

<script src="https://factorio.com/static/js/jquery-3.7.0.min.js"></script>

<link
      href="https://cdn.factorio.com/assets/lite-youtube-embed/lite-yt-embed.css"
      rel="stylesheet"
/>
    

<script src="https://cdn.factorio.com/assets/lite-youtube-embed/lite-yt-embed.js"></script>

<script src="https://cdn.factorio.com/assets/js/floating-ui-core@1.0.1.js"></script>

<script src="https://cdn.factorio.com/assets/js/floating-ui-dom@1.0.4.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/htmx/1.9.10/htmx.min.js"></script>

<style>
  /* Matches the official Factorio site: stable versions in bold white,
     experimental-only versions dimmed, in the version picker list.
     !important guards against factorio.com's own main.css (which sets a
     uniform font-weight/color on .slot-button-inline) winning the cascade. */
  .slot-button-inline.version-stable { color: #ffffff !important; font-weight: 700 !important; }
  .slot-button-inline.version-experimental { color: #5a5a5a !important; font-weight: 400 !important; }
</style>