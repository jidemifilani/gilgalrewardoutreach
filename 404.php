<?php
/** Not found. Also included by outreach.php when a slug does not resolve. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

if (http_response_code() === 200) {
    http_response_code(404);
}

$pageTitle       = 'Page not found';
$pageDescription = 'That page does not exist.';

require __DIR__ . '/includes/header.php';
?>

<section class="section section-cream">
  <div class="container">
    <div class="split">

      <div>
        <p class="eyebrow">404</p>
        <h1 class="display">This page went out on an outreach</h1>
        <p class="lede">The link you followed does not lead anywhere on this site. It may have moved,
          or the address may have a typo in it.</p>

        <form class="search-form u-mt-lg" method="get" action="<?= e(base_url('search.php')) ?>" role="search">
          <input type="search" name="q" placeholder="Search the site instead" aria-label="Search terms">
          <button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button>
        </form>

        <div class="btn-row u-mt-lg">
          <a class="btn btn-ghost" href="<?= e(base_url()) ?>">Back to the homepage <?= icon('arrow') ?></a>
          <a class="btn btn-ghost" href="<?= e(base_url('contact.php')) ?>">Tell us what you were looking for</a>
        </div>

        <div class="u-mt-lg">
          <p class="eyebrow">Try one of these</p>
          <div class="state-pills">
            <?php foreach (nav_flat() as $path => $label): ?>
              <a class="state-pill" href="<?= e(base_url($path)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="split-media"><?= illu_contact() ?></div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
