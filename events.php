<?php
/** Events — upcoming first, then a record of what has already happened. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$upcoming = $pdo->query(
    'SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC'
)->fetchAll();

$past = $pdo->query(
    'SELECT * FROM events WHERE event_date < CURDATE() ORDER BY event_date DESC'
)->fetchAll();

$featured = null;
foreach ($upcoming as $event) {
    if ((int) $event['is_featured'] === 1) { $featured = $event; break; }
}
if (!$featured && $upcoming) {
    $featured = $upcoming[0];
}

$pageTitle       = 'Events';
$pageDescription = 'Upcoming outreaches, health camps, volunteer orientations and training weekends.';
$bannerEyebrow   = 'Events';
$bannerTitle     = "What's coming up";
$bannerLede      = 'Orientations, health camps, supply drives and training weekends. '
                 . 'Everything here is open — you do not need an invitation to turn up.';
$breadcrumbs     = [['label' => 'Events']];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== FEATURED ===================== -->
<?php if ($featured): ?>
<section class="section section-cream">
  <div class="container">
    <div class="split">

      <div>
        <span class="chip chip-solid">Next up</span>
        <h2 class="h-xl u-mt"><?= e($featured['title']) ?></h2>

        <div class="meta-row">
          <span><?= icon('calendar') ?><?= e(fmt_date($featured['event_date'], 'l j F Y')) ?></span>
          <?php if ($featured['start_time']): ?>
            <span><?= icon('clock') ?><?= e(fmt_time($featured['start_time'])) ?></span>
          <?php endif; ?>
          <?php if ($featured['location']): ?>
            <span><?= icon('pin') ?><?= e($featured['location']) ?></span>
          <?php endif; ?>
        </div>

        <p class="lede"><?= e($featured['summary']) ?></p>

        <div class="btn-row u-mt-lg">
          <a class="btn btn-primary" href="#event-<?= (int) $featured['id'] ?>">
            Full details <?= icon('arrow') ?>
          </a>
          <a class="btn btn-ghost" href="<?= e(base_url('contact.php?subject=' . urlencode($featured['title']))) ?>">
            <?= e($featured['cta_label'] ?: 'Ask about this event') ?>
          </a>
          <a class="btn btn-ghost" href="<?= e(base_url('ics.php?id=' . (int) $featured['id'])) ?>">
            <?= icon('calendar') ?> Add to calendar
          </a>
        </div>
      </div>

      <div class="split-media">
        <?= illu_join() ?>
      </div>

    </div>
  </div>
</section>
<?php endif; ?>


<!-- ===================== UPCOMING ===================== -->
<section class="section section-paper">
  <div class="container">

    <div class="u-mb-lg">
      <p class="eyebrow">Upcoming</p>
      <h2 class="h-xl">
        <?= $upcoming ? count($upcoming) . ' event' . (count($upcoming) === 1 ? '' : 's') . ' ahead' : 'Nothing scheduled right now' ?>
      </h2>
    </div>

    <?php if (!$upcoming): ?>
      <p class="gallery-empty">There are no events on the calendar at the moment.
        <a href="<?= e(base_url('contact.php')) ?>">Get in touch</a> and we will let you know
        as soon as the next one is set.</p>
    <?php else: ?>

      <?php foreach ($upcoming as $event): ?>
        <article class="event-row" id="event-<?= (int) $event['id'] ?>">
          <div class="date-chip">
            <span class="d"><?= e(fmt_date($event['event_date'], 'j')) ?></span>
            <span class="m"><?= e(fmt_date($event['event_date'], 'M')) ?></span>
            <span class="y"><?= e(fmt_date($event['event_date'], 'Y')) ?></span>
          </div>

          <div class="event-body">
            <h3 class="h-md"><?= e($event['title']) ?></h3>

            <div class="meta-row">
              <?php if ($event['start_time']): ?>
                <span><?= icon('clock') ?><?= e(fmt_time($event['start_time'])) ?></span>
              <?php endif; ?>
              <?php if ($event['location']): ?>
                <span><?= icon('pin') ?><?= e($event['location']) ?></span>
              <?php endif; ?>
              <?php if ($event['state']): ?>
                <span><?= icon('users') ?><?= e($event['state']) ?> chapter</span>
              <?php endif; ?>
            </div>

            <?= paragraphs($event['description']) ?>
          </div>

          <div class="event-action">
            <a class="btn btn-primary btn-sm"
               href="<?= e(base_url('contact.php?subject=' . urlencode($event['title']))) ?>">
              <?= e($event['cta_label'] ?: 'Ask about this') ?>
            </a>
            <a class="btn btn-ghost btn-sm u-mt"
               href="<?= e(base_url('ics.php?id=' . (int) $event['id'])) ?>">
              <?= icon('calendar') ?> Add to calendar
            </a>
          </div>
        </article>
      <?php endforeach; ?>

    <?php endif; ?>

  </div>
</section>


<!-- ===================== PAST ===================== -->
<?php if ($past): ?>
<section class="section section-wash">
  <div class="container">

    <div class="u-mb-lg">
      <p class="eyebrow">Already happened</p>
      <h2 class="h-xl">Recent events</h2>
    </div>

    <div class="timeline">
      <?php foreach ($past as $event): ?>
        <div class="timeline-item">
          <span class="timeline-dot"></span>
          <p class="timeline-date"><?= e(fmt_date($event['event_date'], 'j F Y')) ?></p>
          <h3 class="h-md"><?= e($event['title']) ?></h3>
          <p><?= e($event['summary']) ?></p>
          <?php if ($event['location']): ?>
            <div class="meta-row"><span><?= icon('pin') ?><?= e($event['location']) ?></span></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="u-mt-lg">
      <a class="link-arrow" href="<?= e(base_url('outreaches.php')) ?>">
        See the outreaches behind these events <?= icon('arrow') ?>
      </a>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ===================== CTA ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="cta-band">
      <div class="split">
        <div>
          <p class="eyebrow">Turn up</p>
          <h2 class="h-xl">Most of our events need volunteers, not tickets</h2>
          <p class="lede">Register once and your chapter coordinator will tell you which events
            near you need hands, and exactly what the day involves before you commit.</p>
          <div class="btn-row u-mt-lg">
            <a class="btn btn-accent" href="<?= e(base_url('volunteer.php#register')) ?>">
              Register to volunteer <?= icon('arrow') ?>
            </a>
            <a class="btn btn-outline-light" href="<?= e(base_url('contact.php')) ?>">Ask a question</a>
          </div>
        </div>
        <div class="split-media"><?= illu_contact() ?></div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
