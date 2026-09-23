<?php
/** Homepage. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$programmes = active_programmes($pdo);
$states     = active_states($pdo);
$stats      = $pdo->query('SELECT * FROM impact_stats ORDER BY sort_order, id')->fetchAll();
$events     = upcoming_events($pdo, 3);
$partners   = $pdo->query('SELECT * FROM partners ORDER BY sort_order, id')->fetchAll();

$outreaches = $pdo->query(
    'SELECT o.*, p.title AS programme_title, p.icon AS programme_icon
       FROM outreaches o
       LEFT JOIN programmes p ON p.id = o.programme_id
      ORDER BY o.outreach_date DESC
      LIMIT 3'
)->fetchAll();

$volunteers = $pdo->query(
    'SELECT * FROM volunteer_profiles WHERE is_active = 1 ORDER BY sort_order, id LIMIT 6'
)->fetchAll();

$stories = approved_stories($pdo, 3);

$pageDescription = get_setting($pdo, 'mission', '');

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== HERO ===================== -->
<section class="hero">
  <div class="container">
    <div class="hero-grid">

      <div class="hero-copy">
        <p class="eyebrow"><?= e(get_setting($pdo, 'hero_eyebrow', 'A Nigerian non-profit')) ?></p>

        <h1 class="display hero-title">
          <?php
            // The last word of the headline gets the hand-drawn highlight.
            $heading = get_setting($pdo, 'hero_heading', 'We go where the need is');
            $words   = explode(' ', $heading);
            $lastWord = array_pop($words);
            echo e(implode(' ', $words)) . ' <span class="mark">' . e($lastWord) . '</span>';
          ?>
        </h1>

        <p class="lede"><?= e(get_setting($pdo, 'hero_subheading', '')) ?></p>

        <div class="btn-row u-mt-lg">
          <a class="btn btn-primary" href="<?= e(base_url('volunteer.php#register')) ?>">
            Become a volunteer <?= icon('arrow') ?>
          </a>
          <a class="btn btn-ghost" href="<?= e(base_url('outreaches.php')) ?>">See our outreaches</a>
        </div>

        <?php if ($stats): ?>
          <div class="hero-stats">
            <?php foreach (array_slice($stats, 0, 3) as $stat): ?>
              <div>
                <span class="hero-stat-value">
                  <span data-count-to="<?= (int) $stat['value'] ?>">0</span><?= e($stat['suffix']) ?>
                </span>
                <span class="hero-stat-label"><?= e($stat['label']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="hero-media">
        <?= illu_hero() ?>
      </div>

    </div>
  </div>
  <?= illu_curve('bottom', 'paper') ?>
</section>


<!-- ===================== WHAT WE DO ===================== -->
<section class="section section-paper" id="programmes">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">What we do</p>
      <h2 class="h-xl">Four programmes, one method:<br>show up, and keep showing up</h2>
      <p class="lede mx-auto text-center"><?= e(get_setting($pdo, 'mission', '')) ?></p>
    </div>

    <div class="grid grid-4">
      <?php foreach ($programmes as $i => $programme): ?>
        <article class="card prog-card reveal reveal-delay-<?= $i % 4 ?>"
                 data-accent="<?= e($programme['accent']) ?>">
          <?= illu_programme_icon($programme['icon']) ?>
          <h3 class="h-md"><?= e($programme['title']) ?></h3>
          <p class="prog-tagline"><?= e($programme['tagline']) ?></p>
          <a class="link-arrow" href="<?= e(base_url('about.php#programmes')) ?>">
            Read more <?= icon('arrow') ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- ===================== OUR STORY ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="split">

      <div class="split-media">
        <?= illu_community() ?>
      </div>

      <div>
        <p class="eyebrow">Who we are</p>
        <h2 class="h-xl">Local people, doing local work, for as long as it takes</h2>

        <?php
          $story = get_setting($pdo, 'about_story', '');
          $first = explode("\n\n", $story);
          echo paragraphs($first[0] ?? '');
          echo paragraphs($first[1] ?? '');
        ?>

        <blockquote class="pull-quote u-mt-lg">
          <?= e(get_setting($pdo, 'vision', '')) ?>
        </blockquote>

        <div class="btn-row u-mt-lg">
          <a class="btn btn-primary" href="<?= e(base_url('about.php')) ?>">More about us <?= icon('arrow') ?></a>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ===================== IMPACT ===================== -->
<?php if ($stats): ?>
<section class="section section-wash">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">The numbers so far</p>
      <h2 class="h-xl">Ten years of turning up</h2>
    </div>

    <div class="grid grid-4">
      <?php foreach ($stats as $i => $stat): ?>
        <div class="stat-card reveal reveal-delay-<?= $i % 4 ?>">
          <?= illu_programme_icon($stat['icon'], 'stat-icon') ?>
          <span class="stat-value">
            <span data-count-to="<?= (int) $stat['value'] ?>">0</span><?= e($stat['suffix']) ?>
          </span>
          <span class="stat-label"><?= e($stat['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ===================== PHOTO WAVE ===================== -->
<section class="section section-paper">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Moments from the field</p>
      <h2 class="h-xl">Ten years, in faces and places</h2>
      <p class="lede mx-auto text-center">Reading clubs, health camps, graduation days and
        relief weekends &mdash; across <?= count($states) ?> states and counting.</p>
    </div>

    <?= photo_wave(wave_photos($pdo, 'gallery', 9), ['amplitude' => 22, 'cycles' => 1.2]) ?>

    <div class="text-center u-mt-lg">
      <a class="btn btn-ghost" href="<?= e(base_url('gallery.php')) ?>">See the full gallery</a>
    </div>

  </div>
</section>


<!-- ===================== RECENT OUTREACHES ===================== -->
<?php if ($outreaches): ?>
<section class="section section-paper">
  <div class="container">

       <div class="split u-mb-lg">
      <div>
        <p class="eyebrow">Out in the field</p>
        <h2 class="h-xl">Our most recent outreaches</h2>
      </div>
      <div>
        <p class="lede">Every outreach below happened in a real community, with a needs list drawn up
          alongside the people who live there. Photographs and full write-ups are on the outreaches page.</p>
        <a class="link-arrow" href="<?= e(base_url('outreaches.php')) ?>">
          See all outreaches <?= icon('arrow') ?>
        </a>
      </div>
    </div>

    <div class="grid grid-3">
      <?php foreach ($outreaches as $i => $outreach): ?>
        <article class="media-card reveal reveal-delay-<?= $i % 3 ?>">
          <div class="media-card-img">
            <img src="<?= e(media_url($outreach['cover_image'], 'outreach-' . $outreach['id'], $outreach['programme_icon'] ?? 'outreach')) ?>"
                 alt="<?= e($outreach['title']) ?>" loading="lazy" width="800" height="560">
          </div>
          <div class="media-card-body">
            <div class="meta-row">
              <span><?= icon('calendar') ?><?= e(fmt_date($outreach['outreach_date'], 'M Y')) ?></span>
              <span><?= icon('pin') ?><?= e($outreach['state']) ?></span>
            </div>
            <h3 class="h-md"><?= e($outreach['title']) ?></h3>
            <p><?= e(excerpt($outreach['summary'], 120)) ?></p>
            <div class="media-card-foot">
              <a class="link-arrow" href="<?= e(base_url('outreach.php?slug=' . urlencode($outreach['slug']))) ?>">
                Read the story <?= icon('arrow') ?>
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ===================== VOLUNTEERS ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="split split-media-first">

      <div class="split-media">
        <div class="map-wrap"><?= illu_nigeria_map($states) ?></div>
      </div>

      <div>
        <p class="eyebrow">Our volunteers</p>
        <h2 class="h-xl">Our volunteers cut across different states in Nigeria</h2>
        <p class="lede"><?= e(get_setting($pdo, 'volunteer_intro', '')) ?></p>

        <div class="state-pills u-mt-lg">
          <?php foreach ($states as $state): ?>
            <span class="state-pill"><?= icon('pin') ?><?= e($state['name']) ?></span>
          <?php endforeach; ?>
        </div>

        <p class="u-mt-lg"><strong><?= number_format(volunteer_total($pdo)) ?> volunteers</strong>
          and counting. Volunteers are welcomed &mdash; wherever you are, there is a team near you.</p>

        <div class="btn-row u-mt">
          <a class="btn btn-primary" href="<?= e(base_url('volunteer.php#register')) ?>">
            Register to volunteer <?= icon('arrow') ?>
          </a>
          <a class="btn btn-ghost" href="<?= e(base_url('volunteer.php')) ?>">Meet the volunteers</a>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ===================== FACES ===================== -->
<?php if ($volunteers): ?>
<section class="section section-paper">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Faces from the field</p>
      <h2 class="h-xl">The people who make it happen</h2>
    </div>

    <div class="people-grid">
      <?php foreach ($volunteers as $i => $person): ?>
        <div class="person reveal reveal-delay-<?= $i % 3 ?>">
          <div class="person-photo">
            <?= illu_blob_image(
                  media_url($person['photo'], 'volunteer-' . $person['id'] . '-' . $person['full_name'], 'portrait'),
                  $person['full_name'] . ', ' . $person['role'],
                  $i % 6
                ) ?>
          </div>
          <p class="person-name"><?= e($person['full_name']) ?></p>
          <p class="person-role"><?= e($person['role']) ?></p>
          <p class="person-state"><?= icon('pin') ?><?= e($person['state']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center u-mt-lg">
      <a class="btn btn-ghost" href="<?= e(base_url('volunteer.php')) ?>">See all our volunteers</a>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ===================== STORIES ===================== -->
<?php if ($stories): ?>
<section class="section section-cream">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">In their own words</p>
      <h2 class="h-xl">What people tell us</h2>
    </div>

    <div class="grid grid-3">
      <?php foreach ($stories as $story): ?>
        <article class="story-card">
          <?= icon('quote') ?>
          <p class="story-text">&ldquo;<?= e(excerpt($story['story'], 230)) ?>&rdquo;</p>
          <div class="story-by">
            <img src="<?= e(media_url($story['photo'], 'story-' . $story['id'] . '-' . $story['author_name'], 'portrait')) ?>"
                 alt="" loading="lazy" width="46" height="46">
            <span>
              <span class="story-name"><?= e($story['author_name']) ?></span>
              <span class="story-role"><?= e(trim(implode(' · ', array_filter([$story['role'], $story['state']])))) ?></span>
            </span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="text-center u-mt-lg">
      <a class="btn btn-ghost" href="<?= e(base_url('stories.php')) ?>">Read more stories</a>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ===================== EVENTS ===================== -->
<?php if ($events): ?>
<section class="section section-wash">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">What's coming up</p>
      <h2 class="h-xl">Next on the calendar</h2>
    </div>

    <?php foreach ($events as $event): ?>
      <article class="event-row">
        <div class="date-chip">
          <span class="d"><?= e(fmt_date($event['event_date'], 'j')) ?></span>
          <span class="m"><?= e(fmt_date($event['event_date'], 'M')) ?></span>
          <span class="y"><?= e(fmt_date($event['event_date'], 'Y')) ?></span>
        </div>
        <div class="event-body">
          <h3 class="h-md"><?= e($event['title']) ?></h3>
          <p><?= e(excerpt($event['summary'], 150)) ?></p>
          <div class="meta-row u-m0">
            <?php if ($event['start_time']): ?>
              <span><?= icon('clock') ?><?= e(fmt_time($event['start_time'])) ?></span>
            <?php endif; ?>
            <?php if ($event['location']): ?>
              <span><?= icon('pin') ?><?= e($event['location']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="event-action">
          <a class="btn btn-ghost btn-sm" href="<?= e(base_url('events.php#event-' . $event['id'])) ?>">Details</a>
        </div>
      </article>
    <?php endforeach; ?>

    <div class="text-center u-mt-lg">
      <a class="btn btn-primary" href="<?= e(base_url('events.php')) ?>">All events <?= icon('arrow') ?></a>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ===================== PARTNERS ===================== -->
<?php if ($partners): ?>
<section class="section section-tight section-paper">
  <div class="container text-center">
    <p class="eyebrow eyebrow-center">We work with</p>
    <div class="partner-row">
      <?php foreach ($partners as $partner): ?>
        <span class="partner-chip"><?= e($partner['name']) ?></span>
      <?php endforeach; ?>
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
          <p class="eyebrow">Get involved</p>
          <h2 class="h-xl"><?= e(get_setting($pdo, 'cta_heading', '')) ?></h2>
          <p class="lede"><?= e(get_setting($pdo, 'cta_text', '')) ?></p>
          <div class="btn-row u-mt-lg">
            <a class="btn btn-accent" href="<?= e(base_url('volunteer.php#register')) ?>">
              Become a volunteer <?= icon('arrow') ?>
            </a>
            <a class="btn btn-outline-light" href="<?= e(base_url('contact.php')) ?>">Contact us</a>
          </div>
        </div>
        <div class="split-media"><?= illu_join() ?></div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
