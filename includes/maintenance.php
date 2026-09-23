<?php
/**
 * The holding page shown while Settings -> Maintenance mode is on.
 *
 * Self-contained: it does not load the main stylesheet, because the whole
 * point is that it still renders if something about the site is mid-change.
 * Expects $siteName, $message, $phone and $email from maintenance_guard().
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Back shortly | <?= e($siteName) ?></title>
<meta name="robots" content="noindex">
<style nonce="<?= e(csp_nonce()) ?>">
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh;
    display: grid; place-items: center; padding: 1.5rem;
    font-family: ui-sans-serif, system-ui, "Segoe UI", Roboto, sans-serif;
    line-height: 1.65; color: #3D554D;
    background:
      radial-gradient(circle at 18% 18%, rgba(47,160,144,.20), transparent 46%),
      radial-gradient(circle at 82% 78%, rgba(240,167,62,.18), transparent 46%),
      #FFF7EE;
  }
  .card {
    width: min(540px, 100%); text-align: center;
    background: #fff; border: 1px solid rgba(19,36,31,.1);
    border-radius: 28px; padding: clamp(1.75rem, 5vw, 3rem);
    box-shadow: 0 18px 50px rgba(8,74,66,.12);
  }
  h1 { font-family: Georgia, "Times New Roman", serif; color: #13241F;
       font-size: clamp(1.6rem, 1.2rem + 1.8vw, 2.2rem); margin: 0 0 .5rem; }
  p { margin: 0 0 1rem; }
  .mark { width: 62px; height: 62px; margin: 0 auto 1.1rem; display: block; }
  .links { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid rgba(19,36,31,.1);
           font-size: .95rem; }
  a { color: #0E6E62; font-weight: 600; }
</style>
</head>
<body>
  <main class="card">
    <img class="mark" src="<?= e(asset_url('img/logo.svg')) ?>" alt="" width="62" height="62">
    <h1><?= e($siteName) ?></h1>
    <p><?= e($message) ?></p>

    <?php if ($phone || $email): ?>
      <p class="links">
        Need us now?
        <?php if ($phone): ?>
          Call <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a><?= $email ? ' or e' : '' ?>
        <?php endif; ?>
        <?php if ($email): ?>
          <?= $phone ? '' : 'E' ?>mail <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        <?php endif; ?>.
      </p>
    <?php endif; ?>
  </main>
</body>
</html>
