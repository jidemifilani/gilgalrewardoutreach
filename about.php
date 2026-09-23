<?php
/** About Us. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$programmes = active_programmes($pdo);
$states     = active_states($pdo);
$stats      = $pdo->query('SELECT * FROM impact_stats ORDER BY sort_order, id')->fetchAll();
$team       = $pdo->query('SELECT * FROM team_members WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$partners   = $pdo->query('SELECT * FROM partners ORDER BY sort_order, id')->fetchAll();
$timeline   = milestones($pdo);

$pageTitle       = 'About Us';
$pageDescription = get_setting($pdo, 'mission', '');
$bannerEyebrow   = 'About us';
$bannerTitle     = 'Ten years of local work, in the places that get overlooked';
$bannerLede      = get_setting($pdo, 'tagline', '');
$breadcrumbs     = [['label' => 'About Us']];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== STORY ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="split">

      <div>
        <p class="eyebrow">Our story</p>
        <h2 class="h-xl">It started with nine friends and 200 exercise books</h2>
        <?= paragraphs(get_setting($pdo, 'about_story', '')) ?>
      </div>

      <div class="split-media">
        <?= illu_community() ?>
      </div>

    </div>
  </div>
</section>


<!-- ===================== MISSION / VISION ===================== -->
<section class="section section-paper">
  <div class="container">
    <div class="grid grid-2">

      <article class="card">
        <?= illu_programme_icon('heart') ?>
        <h3 class="h-lg">Our mission</h3>
        <p class="lede"><?= e(get_setting($pdo, 'mission', '')) ?></p>
      </article>

      <article class="card">
        <?= illu_programme_icon('education') ?>
        <h3 class="h-lg">Our vision</h3>
        <p class="lede"><?= e(get_setting($pdo, 'vision', '')) ?></p>
      </article>

    </div>
  </div>
</section>


<!-- ===================== PROGRAMMES ===================== -->
<section class="section section-wash" id="programmes">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">What we do</p>
      <h2 class="h-xl">Our four programmes</h2>
      <p class="lede mx-auto text-center">Each one runs year-round and is delivered by volunteers
        who live in the community it serves.</p>
    </div>

    <div class="stack">
      <?php foreach ($programmes as $i => $programme): ?>
        <article class="card reveal" id="programme-<?= e($programme['slug']) ?>">
          <div class="split">
            <div>
              <?= illu_programme_icon($programme['icon']) ?>
              <span class="chip <?= $i % 4 === 1 ? 'chip-coral' : ($i % 4 === 2 ? 'chip-sky' : ($i % 4 === 3 ? 'chip-amber' : '')) ?>">
                Programme <?= $i + 1 ?>
              </span>
              <h3 class="h-lg u-mt"><?= e($programme['title']) ?></h3>
              <p class="prog-tagline"><?= e($programme['tagline']) ?></p>
            </div>
            <div>
              <?= paragraphs($programme['description']) ?>
              <a class="link-arrow" href="<?= e(base_url('outreaches.php')) ?>">
                See this programme in the field <?= icon('arrow') ?>
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- ===================== HOW WE WORK ===================== -->
<section class="section section-paper">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">How we work</p>
      <h2 class="h-xl">Four rules we do not bend</h2>
    </div>

    <div class="grid grid-4">
      <?php
        $rules = [
          ['Local first', 'Every outreach is delivered by volunteers who live in that community. We partner with the teachers, nurses and artisans already there.'],
          ['We come back', 'We return to the same communities year after year. A single visit makes a photograph; repeated visits make a difference.'],
          ['Dignity always', 'No crowd distributions, no photographs of anyone receiving aid without consent, no queues for food. We deliver to the door.'],
          ['Open books', 'We publish what we received and what we spent every quarter, alongside an independent audit summary and the things that did not work.'],
        ];
        foreach ($rules as $i => $rule):
      ?>
        <article class="card reveal reveal-delay-<?= $i % 4 ?>">
          <span class="chip <?= ['','chip-coral','chip-sky','chip-amber'][$i % 4] ?>"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3 class="h-md u-mt"><?= e($rule[0]) ?></h3>
          <p><?= e($rule[1]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ===================== IMPACT ===================== -->
<?php if ($stats): ?>
<section class="section section-cream">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Since <?= e(get_setting($pdo, 'founded_year', '2016')) ?></p>
      <h2 class="h-xl">What that has added up to</h2>
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


<!-- ===================== TIMELINE ===================== -->
<?php if ($timeline): ?>
<section class="section section-paper">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">How we got here</p>
      <h2 class="h-xl">Year by year</h2>
    </div>

    <div class="milestones">
      <?php foreach ($timeline as $milestone): ?>
        <div class="milestone reveal">
          <div class="milestone-year"><?= (int) $milestone['year'] ?></div>
          <div class="milestone-body">
            <h3><?= e($milestone['title']) ?></h3>
            <p><?= e($milestone['description']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ===================== TEAM ===================== -->
<?php if ($team): ?>
<section class="section section-paper">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Who leads the work</p>
      <h2 class="h-xl">Our team</h2>
    </div>

    <div class="grid grid-4">
      <?php foreach ($team as $i => $member): ?>
        <div class="person reveal reveal-delay-<?= $i % 4 ?>">
          <div class="person-photo">
            <?= illu_blob_image(
                  media_url($member['photo'], 'team-' . $member['id'] . '-' . $member['name'], 'portrait'),
                  $member['name'] . ', ' . $member['role'],
                  $i % 6
                ) ?>
          </div>
          <p class="person-name"><?= e($member['name']) ?></p>
          <p class="person-role"><?= e($member['role']) ?></p>
          <p class="person-quote"><?= e($member['bio']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ===================== PHOTO WAVE ===================== -->
<section class="section section-cream">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Where the work happens</p>
      <h2 class="h-xl">Out in the communities we serve</h2>
    </div>

    <?= photo_wave(wave_photos($pdo, 'outreaches', 6), ['amplitude' => 24, 'cycles' => 1, 'phase' => 0.4]) ?>

  </div>
</section>


<!-- ===================== WHERE WE WORK ===================== -->
<section class="section section-wash">
  <div class="container">
    <div class="split split-media-first">

      <div class="split-media">
        <div class="map-wrap"><?= illu_nigeria_map($states) ?></div>
      </div>

      <div>
        <p class="eyebrow">Where we work</p>
        <h2 class="h-xl">Chapters in <?= count($states) ?> states</h2>
        <p class="lede">Each chapter runs its own drives, keeps its own volunteer roster and
          reports into the coordination office in <?= e(get_setting($pdo, 'address_city', 'Osogbo')) ?>.</p>

        <div class="state-grid u-mt-lg">
          <?php foreach ($states as $state): ?>
            <div class="state-card">
              <p class="state-name"><?= e($state['name']) ?></p>
              <p class="state-hub"><?= e($state['hub_city']) ?></p>
              <p class="state-note"><?= e($state['note']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ===================== PARTNERS ===================== -->
<?php if ($partners): ?>
<section class="section section-tight section-paper">
  <div class="container text-center">
    <p class="eyebrow eyebrow-center">Our partners</p>
    <h2 class="h-lg u-mb-lg">Organisations we work alongside</h2>
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
          <p class="eyebrow">Join in</p>
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
