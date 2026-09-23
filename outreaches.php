<?php
/** Outreaches — pictures and write-ups of past field work. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$programmes = active_programmes($pdo);

// Optional filter by programme slug, handled server-side so it works
// without JavaScript and produces a shareable URL.
$filter = trim((string) ($_GET['programme'] ?? ''));

$sql = 'SELECT o.*, p.title AS programme_title, p.slug AS programme_slug, p.icon AS programme_icon
          FROM outreaches o
          LEFT JOIN programmes p ON p.id = o.programme_id';
$params = [];

if ($filter !== '') {
    $sql .= ' WHERE p.slug = ?';
    $params[] = $filter;
}
$countSql = 'SELECT COUNT(*) FROM outreaches o LEFT JOIN programmes p ON p.id = o.programme_id'
          . ($filter !== '' ? ' WHERE p.slug = ?' : '');
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$page = paginate((int) $countStmt->fetchColumn(), 9);

$sql .= ' ORDER BY o.outreach_date DESC LIMIT ' . (int) $page['perPage']
      . ' OFFSET ' . (int) $page['offset'];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$outreaches = $stmt->fetchAll();

$totals = $pdo->query(
    'SELECT COUNT(*) AS runs, COALESCE(SUM(beneficiaries),0) AS people, COALESCE(SUM(volunteers),0) AS vols
       FROM outreaches'
)->fetch();

$pageTitle       = 'Outreaches';
$pageDescription = 'Photographs and write-ups from our past outreaches across Nigeria.';
$bannerEyebrow   = 'Out in the field';
$bannerTitle     = 'Our past outreaches';
$bannerLede      = 'Every outreach we have run, with the numbers behind it and photographs from the day. '
                 . 'Nothing here is a plan — this is work that has already happened.';
$breadcrumbs     = [['label' => 'Outreaches']];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== TOTALS ===================== -->
<section class="section section-tight section-cream">
  <div class="container">
    <div class="grid grid-3">
      <div class="stat-card">
        <?= illu_programme_icon('heart', 'stat-icon') ?>
        <span class="stat-value"><span data-count-to="<?= (int) $totals['runs'] ?>">0</span></span>
        <span class="stat-label">Outreaches completed</span>
      </div>
      <div class="stat-card">
        <?= illu_programme_icon('education', 'stat-icon') ?>
        <span class="stat-value"><span data-count-to="<?= (int) $totals['people'] ?>">0</span></span>
        <span class="stat-label">People directly reached</span>
      </div>
      <div class="stat-card">
        <?= illu_programme_icon('skills', 'stat-icon') ?>
        <span class="stat-value"><span data-count-to="<?= (int) $totals['vols'] ?>">0</span></span>
        <span class="stat-label">Volunteer turnouts</span>
      </div>
    </div>
  </div>
</section>


<!-- ===================== PHOTO WAVE ===================== -->
<section class="section section-wash">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">From the field</p>
      <h2 class="h-xl">Photographs from every drive</h2>
    </div>

    <?= photo_wave(wave_photos($pdo, 'gallery', 9), ['amplitude' => 20, 'cycles' => 1.3, 'phase' => 3.1]) ?>

  </div>
</section>


<!-- ===================== LIST ===================== -->
<section class="section section-paper">
  <div class="container">

    <div class="filter-bar">
      <a class="filter-btn <?= $filter === '' ? 'is-active' : '' ?>"
         href="<?= e(base_url('outreaches.php')) ?>">All outreaches</a>
      <?php foreach ($programmes as $programme): ?>
        <a class="filter-btn <?= $filter === $programme['slug'] ? 'is-active' : '' ?>"
           href="<?= e(base_url('outreaches.php?programme=' . urlencode($programme['slug']))) ?>">
          <?= e($programme['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (!$outreaches): ?>
      <p class="gallery-empty">No outreaches recorded under that programme yet.
        <a href="<?= e(base_url('outreaches.php')) ?>">See them all</a>.</p>
    <?php else: ?>

      <div class="grid grid-3">
        <?php foreach ($outreaches as $i => $outreach): ?>
          <article class="media-card reveal reveal-delay-<?= $i % 3 ?>">
            <div class="media-card-img">
              <img src="<?= e(media_url($outreach['cover_image'], 'outreach-' . $outreach['id'], $outreach['programme_icon'] ?? 'outreach')) ?>"
                   alt="<?= e($outreach['title']) ?>" loading="lazy" width="800" height="560">
            </div>
            <div class="media-card-body">
              <?php if ($outreach['programme_title']): ?>
                <span class="chip <?= ['','chip-coral','chip-sky','chip-amber'][$i % 4] ?>">
                  <?= e($outreach['programme_title']) ?>
                </span>
              <?php endif; ?>

              <h3 class="h-md u-mt"><?= e($outreach['title']) ?></h3>

              <div class="meta-row">
                <span><?= icon('calendar') ?><?= e(fmt_date($outreach['outreach_date'], 'j M Y')) ?></span>
                <span><?= icon('pin') ?><?= e($outreach['state']) ?></span>
              </div>

              <p><?= e(excerpt($outreach['summary'], 140)) ?></p>

              <div class="figure-row">
                <div class="figure-box">
                  <div class="figure-value"><?= number_format((int) $outreach['beneficiaries']) ?></div>
                  <div class="figure-label">reached</div>
                </div>
                <div class="figure-box">
                  <div class="figure-value"><?= number_format((int) $outreach['volunteers']) ?></div>
                  <div class="figure-label">volunteers</div>
                </div>
              </div>

              <div class="media-card-foot">
                <a class="btn btn-ghost btn-sm btn-block"
                   href="<?= e(base_url('outreach.php?slug=' . urlencode($outreach['slug']))) ?>">
                  Read the story and see photos
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?= pager_html($page, 'outreaches.php') ?>

    <?php endif; ?>

  </div>
</section>


<!-- ===================== CTA ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="cta-band">
      <div class="split">
        <div>
          <p class="eyebrow">Be part of the next one</p>
          <h2 class="h-xl">The next outreach needs hands, not money</h2>
          <p class="lede">Volunteers are welcomed from every state we work in, and from states we
            have not reached yet. Register and your chapter coordinator will be in touch.</p>
          <div class="btn-row u-mt-lg">
            <a class="btn btn-accent" href="<?= e(base_url('volunteer.php#register')) ?>">
              Register to volunteer <?= icon('arrow') ?>
            </a>
            <a class="btn btn-outline-light" href="<?= e(base_url('gallery.php')) ?>">See the gallery</a>
          </div>
        </div>
        <div class="split-media"><?= illu_join() ?></div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
