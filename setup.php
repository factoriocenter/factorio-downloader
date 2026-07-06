<?php
/**
 * setup.php
 *
 * One-time web installer for the .env file.
 *
 * It collects the Factorio login/password, authenticates against the official
 * Web authentication API, and writes .env (FACTORIO_LOGIN / FACTORIO_PASSWORD /
 * FACTORIO_TOKEN_FALLBACK) with 0600 permissions.
 *
 * Security model:
 *  - One-time only: if .env already exists (non-empty), it responds 403 and
 *    never renders the form. Delete .env to run it again.
 *  - HTTPS required (localhost exempt) so credentials are never sent in clear.
 *  - CSRF token per session; POST is rejected without a matching token.
 *  - Simple per-session attempt throttle.
 *  - No user input is ever reflected without htmlspecialchars(); the password is
 *    never echoed back.
 *  - CR/LF are rejected in login/password to prevent .env line injection.
 *
 * After a successful run, delete this file (setup.php) from the server.
 */

declare(strict_types=1);

session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header(
    "Content-Security-Policy: default-src 'self'; "
    . "style-src 'self' 'unsafe-inline' https://cdn.factorio.com https://factorio.com; "
    . "font-src https://cdn.factorio.com data:; "
    . "img-src https://cdn.factorio.com https://factorio.com data:; "
    . "script-src 'self' https://factorio.com https://cdn.factorio.com; "
    . "form-action 'self'; base-uri 'none'; frame-ancestors 'none'"
);

require_once __DIR__ . '/lib/factorio-auth.php';

const ENV_PATH  = __DIR__ . '/.env';
const CERT_DIR  = __DIR__ . '/certs';
const MAX_TRIES = 8;

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function env_exists(): bool
{
    return is_file(ENV_PATH) && filesize(ENV_PATH) > 0;
}

function request_is_secure(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    // Behind a reverse proxy / load balancer (cPanel, nginx, Cloudflare).
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

function request_is_local(): bool
{
    $addr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (in_array($addr, ['127.0.0.1', '::1'], true)) {
        return true;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return str_starts_with($host, 'localhost');
}

// ---------------------------------------------------------------------------
// One-time lock
// ---------------------------------------------------------------------------
if (env_exists()) {
    http_response_code(403);
    render_page('locked');
    exit;
}

// CSRF token
if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['setup_csrf'];

$secure         = request_is_secure() || request_is_local();
$errors         = [];
$needsEmailCode = false;
$loginValue     = '';

// ---------------------------------------------------------------------------
// Handle submission
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Per-session throttle within a 15-minute window.
    $now = time();
    if (($_SESSION['setup_win'] ?? 0) < $now - 900) {
        $_SESSION['setup_win'] = $now;
        $_SESSION['setup_tries'] = 0;
    }
    $_SESSION['setup_tries'] = ($_SESSION['setup_tries'] ?? 0) + 1;

    $loginValue = trim((string) ($_POST['login'] ?? ''));

    if ($_SESSION['setup_tries'] > MAX_TRIES) {
        $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
    } elseif (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, (string) $_POST['csrf'])) {
        $errors[] = 'Invalid or expired session token. Reload the page and try again.';
    } elseif (!$secure) {
        $errors[] = 'Refusing to send credentials over an insecure (non-HTTPS) connection. '
                  . 'Enable HTTPS/SSL for this site and try again.';
    } elseif (env_exists()) { // race guard
        http_response_code(403);
        render_page('locked');
        exit;
    } else {
        $password  = (string) ($_POST['password'] ?? '');
        $emailCode = trim((string) ($_POST['email_code'] ?? ''));

        if ($loginValue === '' || $password === '') {
            $errors[] = 'Login and password are required.';
        } elseif (preg_match('/[\r\n]/', $loginValue) || preg_match('/[\r\n]/', $password)) {
            $errors[] = 'Login or password contains invalid characters.';
        } else {
            $error = null;
            $needCode = false;
            $token = factorio_auth_login(
                $loginValue,
                $password,
                $emailCode !== '' ? $emailCode : null,
                CERT_DIR,
                $error,
                $needCode
            );

            if ($token === null && $needCode) {
                $needsEmailCode = true;
                $errors[] = 'Factorio e-mailed you an authentication code. Enter it below to finish.';
            } elseif ($token === null) {
                $errors[] = $error ?? 'Authentication failed.';
            } else {
                $content = "FACTORIO_LOGIN=" . $loginValue . "\n"
                         . "FACTORIO_PASSWORD=" . $password . "\n"
                         . "FACTORIO_TOKEN_FALLBACK=" . $token . "\n";
                $written = @file_put_contents(ENV_PATH, $content, LOCK_EX);
                if ($written === false) {
                    $errors[] = 'Could not write the .env file. The web server needs write '
                              . 'permission to the application directory. Create .env manually instead.';
                } else {
                    @chmod(ENV_PATH, 0600);
                    unset($_SESSION['setup_csrf']); // burn the token
                    render_page('success');
                    exit;
                }
            }
        }
    }
}

render_page('form');

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------
function render_page(string $state): void
{
    global $errors, $csrf, $secure, $needsEmailCode, $loginValue;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Factorio Downloader — Setup</title>
  <link href="https://cdn.factorio.com/assets/fonts/titillium-web.css" rel="stylesheet" />
  <link href="https://factorio.com/static/css/main.css?v=89525277" rel="stylesheet" />
  <link href="https://factorio.com/static/img/favicon.ico" rel="icon" type="image/x-icon" />
</head>
<body>
  <div class="top-bar" id="top"><div class="top-banner"></div></div>
  <div class="header">
    <div class="header-inner">
      <a href="/" class="header-logo">
        <img src="https://cdn.factorio.com/assets/img/web/factorio-logo2.png" alt="Factorio" />
      </a>
    </div>
  </div>

  <div class="container">
    <div class="container-inner">
      <div class="panel">
        <h2 class="mb0">Factorio Downloader — Setup</h2>
        <div class="panel-inset">
<?php if ($state === 'locked'): ?>
          <p><strong>This installer has already been used.</strong></p>
          <p>A <code>.env</code> file already exists, so setup is locked. If you need to
             reconfigure, delete the <code>.env</code> file on the server first.</p>
          <p>For security, you should also delete <code>setup.php</code> from the server.</p>
<?php elseif ($state === 'success'): ?>
          <p><strong>✓ Configuration saved.</strong></p>
          <p>Your <code>.env</code> file was created successfully and this installer is now
             locked.</p>
          <p><strong>Important:</strong> delete <code>setup.php</code> from the server now,
             and make sure <code>.env</code> is not publicly accessible
             (the bundled <code>.htaccess</code> / nginx rules handle this).</p>
          <p><a class="button-green download mt12" href="/">Go to the site</a></p>
<?php else: ?>
          <p>Enter your Factorio account credentials once. They will be verified against the
             official Factorio API and saved to a local <code>.env</code> file
             (never shared).</p>

<?php if (!$secure): ?>
          <p style="color:#d9534f;"><strong>Insecure connection.</strong> This page is not
             being served over HTTPS. For your safety, credentials cannot be submitted until
             SSL is enabled.</p>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
          <p style="color:#d9534f;"><?php echo h($e); ?></p>
<?php endforeach; ?>

          <form method="post" action="setup.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>" />
            <p>
              <label>Factorio login (username or e-mail)<br />
                <input type="text" name="login" value="<?php echo h($loginValue); ?>"
                       autocomplete="username" required />
              </label>
            </p>
            <p>
              <label>Factorio password<br />
                <input type="password" name="password" autocomplete="new-password" required />
              </label>
            </p>
<?php if ($needsEmailCode): ?>
            <p>
              <label>E-mail authentication code<br />
                <input type="text" name="email_code" inputmode="numeric" autocomplete="one-time-code" />
              </label>
            </p>
<?php endif; ?>
            <p>
              <button type="submit" class="button-green download" <?php echo $secure ? '' : 'disabled'; ?>>
                Verify &amp; save
              </button>
            </p>
          </form>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
<?php
}
