<?php
/** Frequently asked questions, grouped by category. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$faqs = active_faqs($pdo);

// Group by category, preserving the order the query returned.
$grouped = [];
foreach ($faqs as $faq) {
    $grouped[$faq['category'] ?: 'General'][] = $faq;
}

$pageTitle       = 'Questions';
$pageDescription = 'Answers to the questions we are asked most often about volunteering, our work and our funding.';
$bannerEyebrow   = 'Questions';
$bannerTitle     = 'Things people ask us';
$bannerLede      = 'If your question is not here, send it over — we answer every message within two working days.';
$breadcrumbs     = [['label' => 'About Us', 'url' => base_url('about.php')], ['label' => 'Questions']];

require __DIR__ . '/includes/header.php';
?>

<section class="section section-cream">
  <div class="container">
    <div class="split">

      <div>
        <?php if (!$faqs): ?>
          <p class="gallery-empty">No questions have been published yet.</p>
        <?php else: ?>

          <?php $index = 0; ?>
          <?php foreach ($grouped as $category => $items): ?>
            <div class="u-mb-lg">
              <p class="eyebrow"><?= e($category) ?></p>

              <div class="accordion">
                <?php foreach ($items as $faq): ?>
                  <?php $panelId = 'faq-panel-' . (int) $faq['id']; $index++; ?>
                  <div class="accordion-item">
                    <h3 class="u-m0">
                      <button class="accordion-trigger" type="button"
                              aria-expanded="true" aria-controls="<?= e($panelId) ?>">
                        <span><?= e($faq['question']) ?></span>
                        <?= icon('chevron') ?>
                      </button>
                    </h3>
                    <div class="accordion-panel" id="<?= e($panelId) ?>">
                      <div><?= paragraphs($faq['answer']) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>

        <?php endif; ?>
      </div>

      <div class="split-media">
        <?= illu_contact() ?>

        <div class="card u-mt-lg">
          <h3 class="h-md">Still wondering?</h3>
          <p>Ask us directly. Every message is read by a person and answered within two working days.</p>
          <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(base_url('contact.php')) ?>">
              Ask a question <?= icon('arrow') ?>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<section class="section section-cream">
  <div class="container">
    <div class="cta-band">
      <div class="split">
        <div>
          <p class="eyebrow">Ready when you are</p>
          <h2 class="h-xl">The shortest answer is: come and see</h2>
          <p class="lede">Register as a volunteer and your chapter coordinator will tell you exactly
            what a day of service looks like before you commit to anything.</p>
          <div class="btn-row u-mt-lg">
            <a class="btn btn-accent" href="<?= e(base_url('volunteer.php#register')) ?>">
              Become a volunteer <?= icon('arrow') ?>
            </a>
            <a class="btn btn-outline-light" href="<?= e(base_url('stories.php')) ?>">Read their stories</a>
          </div>
        </div>
        <div class="split-media"><?= illu_join() ?></div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
