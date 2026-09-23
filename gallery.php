<?php
/** Gallery — every photograph, filterable by category. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

// Category and state both filter server-side, so a filtered view is a real,
// shareable URL and works with JavaScript disabled.
$category = trim((string) ($_GET['category'] ?? ''));
$state    = trim((string) ($_GET['state'] ?? ''));

$where  = [];
$params = [];

if ($category !== '') { $where[] = 'g.category = ?'; $params[] = $category; }
if ($state !== '')    { $where[] = 'g.state = ?';    $params[] = $state; }

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM gallery_items g' . $whereSql);
$countStmt->execute($params);
$page = paginate((int) $countStmt->fetchColumn(), 12);

$stmt = $pdo->prepare(
    'SELECT g.*, o.slug AS outreach_slug
       FROM gallery_items g
       LEFT JOIN outreaches o ON o.id = g.outreach_id'
    . $whereSql .
    ' ORDER BY g.sort_order, g.id
      LIMIT ' . (int) $page['perPage'] . ' OFFSET ' . (int) $page['offset']
);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Filter options come from the whole table, not just the current page.
$allCategories = $pdo->query(
    "SELECT DISTINCT category FROM gallery_items WHERE category <> '' ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

$allStates = $pdo->query(
    "SELECT DISTINCT state FROM gallery_items WHERE state <> '' ORDER BY state"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle       = 'Gallery';
$pageDescription = 'Photographs from our outreaches, reading clubs, health camps and skills programmes across Nigeria.';
$bannerEyebrow   = 'Gallery';
$bannerTitle     = 'The work, in pictures';
$bannerLede      = 'Photographs from outreaches, reading clubs, health camps and graduation days. '
                 . 'We photograph the work, and never a face without permission.';
$breadcrumbs     = [['label' => 'Gallery']];

require __DIR__ . '/includes/header.php';
?>

<section class="section section-paper">
  <div class="container">

    <?php
      // Builds a filter link that keeps the other filter but resets the page.
      $filterLink = function (array $changes) use ($category, $state): string {
          $q = array_filter(array_merge(['category' => $category, 'state' => $state], $changes));
          return base_url('gallery.php' . ($q ? '?' . http_build_query($q) : ''));
      };
    ?>

    <?php if ($allCategories): ?>
      <div class="filter-bar">
        <a class="filter-btn <?= $category === '' ? 'is-active' : '' ?>"
           href="<?= e($filterLink(['category' => ''])) ?>">All photos</a>
        <?php foreach ($allCategories as $option): ?>
          <a class="filter-btn <?= $category === $option ? 'is-active' : '' ?>"
             href="<?= e($filterLink(['category' => $option])) ?>"><?= e($option) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($allStates): ?>
      <div class="filter-bar">
        <a class="filter-btn <?= $state === '' ? 'is-active' : '' ?>"
           href="<?= e($filterLink(['state' => ''])) ?>">Every state</a>
        <?php foreach ($allStates as $option): ?>
          <a class="filter-btn <?= $state === $option ? 'is-active' : '' ?>"
             href="<?= e($filterLink(['state' => $option])) ?>"><?= e($option) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <p class="gallery-empty">
        No photographs match that filter.
        <a href="<?= e(base_url('gallery.php')) ?>">Show them all</a>.
      </p>
    <?php else: ?>

      <div class="gallery-grid" id="galleryGrid">
        <?php foreach ($items as $item): ?>
          <?php
            $meta = trim(implode(' · ', array_filter([
                $item['state'],
                fmt_date($item['taken_on'], 'j M Y'),
            ])));
          ?>
          <button type="button" class="gallery-item"
                  data-category="<?= e($item['category']) ?>"
                  data-title="<?= e($item['title']) ?>"
                  data-meta="<?= e($item['caption'] ?: $meta) ?>">
            <img src="<?= e(media_url($item['image'], 'gallery-' . $item['id'] . '-' . $item['title'], strtolower($item['category']))) ?>"
                 alt="<?= e($item['title'] . '. ' . $item['caption']) ?>"
                 loading="lazy" width="800" height="560">
            <span class="gallery-cap">
              <strong><?= e($item['title']) ?></strong>
              <span><?= e($meta) ?></span>
            </span>
          </button>
        <?php endforeach; ?>
      </div>

      <?= pager_html($page, 'gallery.php') ?>

    <?php endif; ?>

  </div>
</section>

<?php require __DIR__ . '/includes/lightbox.php'; ?>

<section class="section section-cream">
  <div class="container">
    <div class="cta-band">
      <div class="split">
        <div>
          <p class="eyebrow">Behind every photo</p>
          <h2 class="h-xl">There is a community, and a team that showed up</h2>
          <p class="lede">Read the full story behind any of these pictures on the outreaches page,
            or join the volunteers who make them happen.</p>
          <div class="btn-row u-mt-lg">
            <a class="btn btn-accent" href="<?= e(base_url('outreaches.php')) ?>">
              Read the outreach stories <?= icon('arrow') ?>
            </a>
            <a class="btn btn-outline-light" href="<?= e(base_url('volunteer.php#register')) ?>">Volunteer with us</a>
          </div>
        </div>
        <div class="split-media"><?= illu_join() ?></div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
