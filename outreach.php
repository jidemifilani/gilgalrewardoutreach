<?php
/** A single outreach: the story, the numbers, and its photographs. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

$stmt = $pdo->prepare(
    'SELECT o.*, p.title AS programme_title, p.slug AS programme_slug, p.icon AS programme_icon
       FROM outreaches o
       LEFT JOIN programmes p ON p.id = o.programme_id
      WHERE o.slug = ?
      LIMIT 1'
);
$stmt->execute([$slug]);
$outreach = $stmt->fetch();

if (!$outreach) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$photoStmt = $pdo->prepare('SELECT * FROM outreach_photos WHERE outreach_id = ? ORDER BY sort_order, id');
$photoStmt->execute([$outreach['id']]);
$photos = $photoStmt->fetchAll();

$galleryStmt = $pdo->prepare('SELECT * FROM gallery_items WHERE outreach_id = ? ORDER BY sort_order, id');
$galleryStmt->execute([$outreach['id']]);
$galleryItems = $galleryStmt->fetchAll();

$otherStmt = $pdo->prepare(
    'SELECT * FROM outreaches WHERE id <> ? ORDER BY outreach_date DESC LIMIT 3'
);
$otherStmt->execute([$outreach['id']]);
$others = $otherStmt->fetchAll();

// Neighbours in date order, for the previous/next links at the foot of the page.
$prevStmt = $pdo->prepare(
    'SELECT title, slug FROM outreaches WHERE outreach_date < ? ORDER BY outreach_date DESC LIMIT 1'
);
$prevStmt->execute([$outreach['outreach_date']]);
$previous = $prevStmt->fetch();

$nextStmt = $pdo->prepare(
    'SELECT title, slug FROM outreaches WHERE outreach_date > ? ORDER BY outreach_date ASC LIMIT 1'
);
$nextStmt->execute([$outreach['outreach_date']]);
$next = $nextStmt->fetch();

$shareUrl   = full_base_url('outreach.php?slug=' . urlencode($outreach['slug']));
$shareText  = $outreach['title'] . ' — ' . site_name($pdo);

$pageTitle       = $outreach['title'];
$pageDescription = $outreach['summary'];
$bannerEyebrow   = $outreach['programme_title'] ?: 'Outreach';
$bannerTitle     = $outreach['title'];
$bannerLede      = $outreach['summary'];
$breadcrumbs     = [
    ['label' => 'Outreaches', 'url' => base_url('outreaches.php')],
    ['label' => $outreach['title']],
];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== COVER + FACTS ===================== -->
<section class="section section-cream">
  <div class="container">

    <div class="outreach-hero-photo u-mb-lg">
      <img src="<?= e(media_url($outreach['cover_image'], 'outreach-' . $outreach['id'], $outreach['programme_icon'] ?? 'outreach')) ?>"
           alt="<?= e($outreach['title']) ?>" width="1200" height="675">
    </div>

    <div class="split">
      <div>
        <p class="eyebrow">The story</p>
        <?= paragraphs($outreach['story']) ?>
      </div>

      <div>
        <div class="card">
          <h3 class="h-md">At a glance</h3>

          <div class="meta-row u-mt">
            <span><?= icon('calendar') ?><?= e(fmt_date($outreach['outreach_date'], 'j F Y')) ?></span>
          </div>
          <div class="meta-row">
            <span><?= icon('pin') ?><?= e($outreach['location']) ?><?= $outreach['state'] ? ', ' . e($outreach['state']) : '' ?></span>
          </div>
          <?php if ($outreach['programme_title']): ?>
            <div class="meta-row">
              <span><?= icon('sparkle') ?><?= e($outreach['programme_title']) ?></span>
            </div>
          <?php endif; ?>

          <div class="figure-row">
            <div class="figure-box">
              <div class="figure-value"><?= number_format((int) $outreach['beneficiaries']) ?></div>
              <div class="figure-label">people reached</div>
            </div>
            <div class="figure-box">
              <div class="figure-value"><?= number_format((int) $outreach['volunteers']) ?></div>
              <div class="figure-label">volunteers</div>
            </div>
          </div>

          <a class="btn btn-primary btn-block" href="<?= e(base_url('volunteer.php#register')) ?>">
            Join the next one <?= icon('arrow') ?>
          </a>

          <div class="share-row u-mt-lg">
            <span class="share-label">Share</span>

            <a class="share-btn" target="_blank" rel="noopener noreferrer"
               aria-label="Share on Facebook"
               href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($shareUrl)) ?>">
              <?= icon('facebook') ?>
            </a>

            <a class="share-btn" target="_blank" rel="noopener noreferrer"
               aria-label="Share on X"
               href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($shareUrl)) ?>&amp;text=<?= e(rawurlencode($shareText)) ?>">
              <?= icon('twitter') ?>
            </a>

            <a class="share-btn" target="_blank" rel="noopener noreferrer"
               aria-label="Share on WhatsApp"
               href="https://wa.me/?text=<?= e(rawurlencode($shareText . ' ' . $shareUrl)) ?>">
              <?= icon('whatsapp') ?>
            </a>

            <a class="share-btn" aria-label="Share by email"
               href="mailto:?subject=<?= e(rawurlencode($shareText)) ?>&amp;body=<?= e(rawurlencode($shareUrl)) ?>">
              <?= icon('mail') ?>
            </a>

            <!-- Revealed by JS only where the browser supports them. -->
            <button class="share-btn" type="button" data-share-native hidden
                    aria-label="Share using your device"><?= icon('external') ?></button>
            <button class="share-btn" type="button" data-copy-link hidden
                    aria-label="Copy link"><?= icon('check') ?></button>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>


<!-- ===================== PHOTOS ===================== -->
<?php
  // Photos attached directly to the outreach, plus any gallery items tagged to it.
  $allPhotos = [];
  foreach ($photos as $photo) {
      $allPhotos[] = [
          'src'   => media_url($photo['image'], 'photo-' . $photo['id'] . '-' . $outreach['slug'], $outreach['programme_icon'] ?? 'outreach'),
          'title' => $photo['caption'] ?: $outreach['title'],
          'meta'  => fmt_date($outreach['outreach_date'], 'j F Y') . ' · ' . $outreach['state'],
      ];
  }
  foreach ($galleryItems as $item) {
      $allPhotos[] = [
          'src'   => media_url($item['image'], 'gallery-' . $item['id'] . '-' . $item['title'], strtolower($item['category'])),
          'title' => $item['title'],
          'meta'  => $item['caption'],
      ];
  }
?>
<?php if ($allPhotos): ?>
<section class="section section-paper">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">From the day</p>
      <h2 class="h-xl">Photographs</h2>
    </div>

    <div class="gallery-grid" id="galleryGrid">
      <?php foreach ($allPhotos as $i => $photo): ?>
        <button type="button" class="gallery-item"
                data-category="outreach"
                data-title="<?= e($photo['title']) ?>"
                data-meta="<?= e($photo['meta']) ?>">
          <img src="<?= e($photo['src']) ?>" alt="<?= e($photo['title']) ?>"
               loading="lazy" width="800" height="560">
          <span class="gallery-cap">
            <strong><?= e($photo['title']) ?></strong>
            <span><?= e($photo['meta']) ?></span>
          </span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/lightbox.php'; ?>
<?php endif; ?>


<!-- ===================== PREVIOUS / NEXT ===================== -->
<?php if ($previous || $next): ?>
<section class="section section-tight section-paper">
  <div class="container">
    <div class="prevnext">
      <?php if ($previous): ?>
        <a href="<?= e(base_url('outreach.php?slug=' . urlencode($previous['slug']))) ?>" rel="prev">
          <span class="dir">Earlier outreach</span>
          <span class="ttl"><?= e($previous['title']) ?></span>
        </a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>

      <?php if ($next): ?>
        <a class="next" href="<?= e(base_url('outreach.php?slug=' . urlencode($next['slug']))) ?>" rel="next">
          <span class="dir">Later outreach</span>
          <span class="ttl"><?= e($next['title']) ?></span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ===================== MORE OUTREACHES ===================== -->
<?php if ($others): ?>
<section class="section section-cream">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Keep reading</p>
      <h2 class="h-lg">Other outreaches</h2>
    </div>

    <div class="grid grid-3">
      <?php foreach ($others as $i => $other): ?>
        <article class="media-card">
          <div class="media-card-img">
            <img src="<?= e(media_url($other['cover_image'], 'outreach-' . $other['id'], 'outreach')) ?>"
                 alt="<?= e($other['title']) ?>" loading="lazy" width="800" height="560">
          </div>
          <div class="media-card-body">
            <div class="meta-row">
              <span><?= icon('calendar') ?><?= e(fmt_date($other['outreach_date'], 'M Y')) ?></span>
              <span><?= icon('pin') ?><?= e($other['state']) ?></span>
            </div>
            <h3 class="h-md"><?= e($other['title']) ?></h3>
            <div class="media-card-foot">
              <a class="link-arrow" href="<?= e(base_url('outreach.php?slug=' . urlencode($other['slug']))) ?>">
                Read the story <?= icon('arrow') ?>
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="text-center u-mt-lg">
      <a class="btn btn-ghost" href="<?= e(base_url('outreaches.php')) ?>">All outreaches</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
