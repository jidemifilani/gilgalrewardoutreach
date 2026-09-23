<?php
/**
 * Stories: volunteers and community members in their own words.
 *
 * Anyone can submit one, but nothing appears on this page until an admin
 * ticks "Publish" in the admin panel. Unapproved submissions are never
 * queried by anything public-facing.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';
require_once __DIR__ . '/includes/mailer.php';

start_session_if_needed();

$states = active_states($pdo);

$errors = [];
$old    = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    $old = [
        'author_name' => post_str('author_name', 160),
        'role'        => post_str('role', 160),
        'state'       => post_str('state', 80),
        'email'       => post_str('email', 190),
        'story'       => post_str('story', 2000),
    ];

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Please send your story again.';
    } elseif (looks_like_spam()) {
        flash('success', 'Thank you for sharing your story.');
        redirect(base_url('stories.php?sent=1'));
    } elseif (!rate_limit_ok($pdo, 'story', 3, 60)) {
        $errors['form'] = 'That is several submissions from this connection in a short time. '
                        . 'Please try again in an hour.';
    }

    if (!$errors) {
        if ($old['author_name'] === '')        $errors['author_name'] = 'Please tell us your name.';
        if ($old['email'] !== '' && !valid_email($old['email'])) {
                                               $errors['email'] = 'Please enter a valid email address.';
        }
        if (mb_strlen($old['story']) < 40)     $errors['story'] = 'Please write a little more — at least a few sentences.';
    }

    if (!$errors) {
        $pdo->prepare(
            'INSERT INTO stories (author_name, role, state, email, story, is_approved)
             VALUES (?, ?, ?, ?, ?, 0)'
        )->execute([
            $old['author_name'], $old['role'], $old['state'], $old['email'], $old['story'],
        ]);

        send_notification(
            get_setting($pdo, 'contact_email', ''),
            'New story submitted: ' . $old['author_name'],
            "Someone submitted a story through the website. It is held for approval.\n\n"
            . "Name:  {$old['author_name']}\n"
            . "Role:  {$old['role']}\n"
            . "State: {$old['state']}\n"
            . "Email: {$old['email']}\n\n"
            . "Story:\n{$old['story']}\n",
            $old['email']
        );

        flash('success', 'Thank you, ' . $old['author_name'] . '. Your story has been received — '
            . 'we read every one before publishing, so it may take a few days to appear.');
        redirect(base_url('stories.php?sent=1'));
    }
}

$stories = approved_stories($pdo);

$pageTitle       = 'Stories';
$pageDescription = 'Volunteers and community members describing the work in their own words.';
$bannerEyebrow   = 'In their own words';
$bannerTitle     = 'Stories from the communities we serve';
$bannerLede      = get_setting($pdo, 'stories_intro', '');
$breadcrumbs     = [['label' => 'About Us', 'url' => base_url('about.php')], ['label' => 'Stories']];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== STORIES ===================== -->
<section class="section section-cream">
  <div class="container">

    <?php if (!$stories): ?>
      <p class="gallery-empty">No stories have been published yet. Be the first to send one.</p>
    <?php else: ?>
      <div class="story-grid">
        <?php foreach ($stories as $story): ?>
          <article class="story-card">
            <?= icon('quote') ?>
            <p class="story-text">&ldquo;<?= e($story['story']) ?>&rdquo;</p>
            <div class="story-by">
              <img src="<?= e(media_url($story['photo'], 'story-' . $story['id'] . '-' . $story['author_name'], 'portrait')) ?>"
                   alt="" loading="lazy" width="46" height="46">
              <span>
                <span class="story-name"><?= e($story['author_name']) ?></span>
                <span class="story-role">
                  <?= e(trim(implode(' · ', array_filter([$story['role'], $story['state']])))) ?>
                </span>
              </span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>


<!-- ===================== SUBMIT ===================== -->
<section class="section section-paper" id="share">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Share yours</p>
      <h2 class="h-xl">Tell us what you have seen</h2>
      <p class="lede mx-auto text-center">Volunteer, teacher, nurse, parent or neighbour — if our work
        has crossed your path, we would like to hear it in your words. We read every submission
        before publishing, so nothing appears here automatically.</p>
    </div>

    <div class="container-narrow">
      <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($errors['form']) ?></span></div>
      <?php elseif ($errors): ?>
        <div class="alert alert-error" role="alert">
          <?= icon('sparkle') ?><span>Please check the highlighted fields below and try again.</span>
        </div>
      <?php endif; ?>

      <form class="form-card" method="post" action="<?= e(base_url('stories.php')) ?>#share" data-enhance>
        <?= csrf_field() ?>
        <?= honeypot_field() ?>

        <div class="form-grid">

          <div class="form-group <?= isset($errors['author_name']) ? 'has-error' : '' ?>">
            <label for="author_name">Your name</label>
            <input type="text" id="author_name" name="author_name" required maxlength="160"
                   autocomplete="name" value="<?= e($old['author_name'] ?? '') ?>">
            <?php if (isset($errors['author_name'])): ?><span class="field-error"><?= e($errors['author_name']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label for="role">Your role</label>
            <input type="text" id="role" name="role" maxlength="160"
                   placeholder="e.g. Volunteer, teacher, parent" value="<?= e($old['role'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="state">State</label>
            <select id="state" name="state">
              <option value="">Prefer not to say</option>
              <?php foreach ($states as $state): ?>
                <option value="<?= e($state['name']) ?>" <?= ($old['state'] ?? '') === $state['name'] ? 'selected' : '' ?>>
                  <?= e($state['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="190"
                   placeholder="Optional" value="<?= e($old['email'] ?? '') ?>">
            <p class="form-hint">Only so we can check back with you. Never shown on the site.</p>
            <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
          </div>

          <div class="form-group span-2 <?= isset($errors['story']) ? 'has-error' : '' ?>">
            <label for="story">Your story</label>
            <textarea id="story" name="story" required maxlength="2000"
                      placeholder="What did you see, and what changed?"><?= e($old['story'] ?? '') ?></textarea>
            <?php if (isset($errors['story'])): ?><span class="field-error"><?= e($errors['story']) ?></span><?php endif; ?>
          </div>

        </div>

        <button class="btn btn-primary btn-block" type="submit" data-busy="Sending...">
          Send my story <?= icon('arrow') ?>
        </button>

        <p class="form-hint text-center u-mt">
          By sending this you are happy for us to publish your words and your first name.
          Tell us in the story itself if you would rather stay anonymous.
        </p>
      </form>
    </div>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
