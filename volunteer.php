<?php
/**
 * Volunteer page: the volunteers themselves, the states they cover,
 * and the registration form.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';
require_once __DIR__ . '/includes/mailer.php';

start_session_if_needed();

$states     = active_states($pdo);
$programmes = active_programmes($pdo);

$profiles = $pdo->query(
    'SELECT * FROM volunteer_profiles WHERE is_active = 1 ORDER BY sort_order, id'
)->fetchAll();

$errors = [];
$old    = [];

// ---------------------------------------------------------------------------
// Registration
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    $old = [
        'full_name'    => post_str('full_name', 160),
        'email'        => post_str('email', 190),
        'phone'        => post_str('phone', 40),
        'state'        => post_str('state', 80),
        'city'         => post_str('city', 120),
        'occupation'   => post_str('occupation', 160),
        'availability' => post_str('availability', 80),
        'heard_from'   => post_str('heard_from', 120),
        'skills'       => post_str('skills', 1500),
        'motivation'   => post_str('motivation', 1500),
    ];
    $interests = post_list('interests');

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } elseif (looks_like_spam()) {
        // Silently accept so a bot learns nothing, but store nothing.
        flash('success', 'Thank you for registering. We will be in touch shortly.');
        redirect(base_url('volunteer.php?registered=1'));
    } elseif (!rate_limit_ok($pdo, 'volunteer', 4, 60)) {
        $errors['form'] = 'That is several registrations from this connection in a short time. '
                        . 'Please try again in an hour, or email us directly.';
    }

    if (!$errors) {
        if ($old['full_name'] === '')                 $errors['full_name'] = 'Please tell us your name.';
        if (!valid_email($old['email']))              $errors['email']     = 'Please enter a valid email address.';
        if (!valid_phone($old['phone']))              $errors['phone']     = 'Please enter a reachable phone number.';
        if ($old['state'] === '')                     $errors['state']     = 'Please choose the state you are in.';
        if (!$interests)                              $errors['interests'] = 'Please pick at least one programme.';
        if (mb_strlen($old['motivation']) < 10)       $errors['motivation']= 'Tell us a little about why you want to volunteer.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO volunteers
               (full_name, email, phone, state, city, occupation, interests,
                availability, skills, motivation, heard_from)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $old['full_name'], $old['email'], $old['phone'], $old['state'], $old['city'],
            $old['occupation'], implode(', ', $interests), $old['availability'],
            $old['skills'], $old['motivation'], $old['heard_from'],
        ]);

        send_notification(
            get_setting($pdo, 'volunteer_email', get_setting($pdo, 'contact_email', '')),
            'New volunteer registration: ' . $old['full_name'],
            "A new volunteer has registered.\n\n"
            . "Name:        {$old['full_name']}\n"
            . "Email:       {$old['email']}\n"
            . "Phone:       {$old['phone']}\n"
            . "State:       {$old['state']}\n"
            . "City:        {$old['city']}\n"
            . "Occupation:  {$old['occupation']}\n"
            . 'Interests:   ' . implode(', ', $interests) . "\n"
            . "Available:   {$old['availability']}\n\n"
            . "Skills:\n{$old['skills']}\n\n"
            . "Why:\n{$old['motivation']}\n",
            $old['email']
        );

        flash('success', 'Thank you, ' . $old['full_name'] . '. Your registration is in — '
            . 'your chapter coordinator will contact you within a week.');
        redirect(base_url('volunteer.php?registered=1'));
    }
}

$pageTitle       = 'Volunteer';
$pageDescription = 'Our volunteers cut across Osun, Oyo, Lagos, Abuja, Rivers, Benue and more. '
                 . 'Register to join a team near you.';
$bannerEyebrow   = 'Volunteer with us';
$bannerTitle     = 'Our volunteers cut across different states in Nigeria';
$bannerLede      = get_setting($pdo, 'volunteer_intro', '');
$breadcrumbs     = [['label' => 'Volunteer']];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== PHOTO WAVE ===================== -->
<section class="section section-paper">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">The people who show up</p>
      <h2 class="h-xl">Volunteers, from every chapter</h2>
      <p class="lede mx-auto text-center">Teachers, nurses, traders, students and drivers &mdash;
        from every chapter we run. Their names and stories are further down this page.</p>
    </div>

    <?= photo_wave(wave_photos($pdo, 'volunteers', 9), ['amplitude' => 23, 'cycles' => 1.25, 'shape' => 'mixed']) ?>

  </div>
</section>


<!-- ===================== STATES ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="split split-media-first">

      <div class="split-media">
        <div class="map-wrap"><?= illu_nigeria_map($states) ?></div>
      </div>

      <div>
        <p class="eyebrow">Where our volunteers are</p>
        <h2 class="h-xl"><?= count($states) ?> states, <?= number_format(volunteer_total($pdo)) ?> volunteers</h2>

        <p class="lede">
          We have active chapters in <?= e(state_sentence($states, 8)) ?>.
          Each one runs its own drives and keeps its own roster.
        </p>

        <div class="state-pills u-mt-lg">
          <?php foreach ($states as $state): ?>
            <span class="state-pill"><?= icon('pin') ?><?= e($state['name']) ?></span>
          <?php endforeach; ?>
        </div>

        <div class="alert alert-info u-mt-lg">
          <?= icon('heart') ?>
          <span><strong>Volunteers are welcomed.</strong> Not in one of these states? Register anyway —
            new chapters start exactly this way, with one person putting their name down.</span>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ===================== CHAPTERS ===================== -->
<section class="section section-paper">
  <div class="container">
    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Our chapters</p>
      <h2 class="h-xl">Find the team nearest you</h2>
    </div>

    <div class="state-grid">
      <?php foreach ($states as $state): ?>
        <div class="state-card">
          <p class="state-name"><?= e($state['name']) ?></p>
          <p class="state-hub"><?= icon('pin') ?> <?= e($state['hub_city']) ?></p>
          <p class="state-note"><?= e($state['note']) ?></p>
          <span class="state-count"><?= number_format((int) $state['volunteers']) ?> volunteers</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- ===================== VOLUNTEER FACES ===================== -->
<?php if ($profiles): ?>
<section class="section section-wash">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Faces from our outreaches</p>
      <h2 class="h-xl">The volunteers</h2>
      <p class="lede mx-auto text-center">Photographs from reading clubs, health camps, skills
        graduations and relief weekends across the country.</p>
    </div>

    <div class="people-grid">
      <?php foreach ($profiles as $i => $person): ?>
        <div class="person reveal reveal-delay-<?= $i % 3 ?>">
          <div class="person-photo">
            <?= illu_blob_image(
                  media_url($person['photo'], 'volunteer-' . $person['id'] . '-' . $person['full_name'], 'portrait'),
                  $person['full_name'] . ', ' . $person['role'] . ', ' . $person['state'],
                  $i % 6
                ) ?>
          </div>
          <p class="person-name"><?= e($person['full_name']) ?></p>
          <p class="person-role"><?= e($person['role']) ?></p>
          <p class="person-state"><?= icon('pin') ?><?= e($person['state']) ?><?php if ($person['serving_since']): ?> &middot; since <?= (int) $person['serving_since'] ?><?php endif; ?></p>
          <?php if ($person['outreach']): ?>
            <p class="person-state"><?= icon('sparkle') ?><?= e($person['outreach']) ?></p>
          <?php endif; ?>
          <?php if ($person['quote']): ?>
            <p class="person-quote">&ldquo;<?= e($person['quote']) ?>&rdquo;</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ===================== WHAT IT INVOLVES ===================== -->
<section class="section section-paper">
  <div class="container">
    <div class="split">
      <div>
        <p class="eyebrow">Before you sign up</p>
        <h2 class="h-xl">What volunteering actually involves</h2>
        <p class="lede"><?= e(get_setting($pdo, 'volunteer_promise', '')) ?></p>
      </div>

      <div class="grid">
        <?php
          $facts = [
            ['clock', 'A few hours a month', 'Most volunteers give one or two Saturdays a month. Some give one day a term. Both matter.'],
            ['users', 'A team near you', 'You are placed with the chapter closest to where you live, never sent across the country.'],
            ['check', 'Training first', 'Every volunteer is trained before their first outreach, including our child-safeguarding rules.'],
            ['heart', 'No money required', 'Volunteering costs you nothing. We never ask volunteers to fund the work they deliver.'],
          ];
          foreach ($facts as $fact):
        ?>
          <div class="contact-method u-static">
            <span class="contact-method-icon"><?= icon($fact[0]) ?></span>
            <span>
              <span class="contact-method-value"><?= e($fact[1]) ?></span>
              <span class="u-fs-sm"><?= e($fact[2]) ?></span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>


<!-- ===================== REGISTRATION FORM ===================== -->
<section class="section section-cream" id="register">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Register</p>
      <h2 class="h-xl">Become a volunteer</h2>
      <p class="lede mx-auto text-center">Fill this in once. Your chapter coordinator will contact you
        within a week with the next orientation near you.</p>
    </div>

    <div class="container-narrow">
      <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-error" role="alert">
          <?= icon('sparkle') ?><span><?= e($errors['form']) ?></span>
        </div>
      <?php elseif ($errors): ?>
        <div class="alert alert-error" role="alert">
          <?= icon('sparkle') ?><span>Please check the highlighted fields below and try again.</span>
        </div>
      <?php endif; ?>

      <form class="form-card" method="post" action="<?= e(base_url('volunteer.php')) ?>#register" data-enhance>
        <?= csrf_field() ?>
        <?= honeypot_field() ?>

        <div class="form-grid">

          <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" required maxlength="160"
                   autocomplete="name" placeholder="e.g. Adaeze Okonkwo"
                   value="<?= e($old['full_name'] ?? '') ?>">
            <?php if (isset($errors['full_name'])): ?><span class="field-error"><?= e($errors['full_name']) ?></span><?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required maxlength="190"
                   autocomplete="email" placeholder="you@example.com"
                   value="<?= e($old['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
            <label for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone" required maxlength="40"
                   autocomplete="tel" placeholder="0803 000 0000"
                   value="<?= e($old['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?><span class="field-error"><?= e($errors['phone']) ?></span><?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['state']) ? 'has-error' : '' ?>">
            <label for="state">Which state are you in?</label>
            <select id="state" name="state" required>
              <option value="">Choose a state</option>
              <?php foreach ($states as $state): ?>
                <option value="<?= e($state['name']) ?>" <?= ($old['state'] ?? '') === $state['name'] ? 'selected' : '' ?>>
                  <?= e($state['name']) ?> — <?= e($state['hub_city']) ?>
                </option>
              <?php endforeach; ?>
              <option value="Other" <?= ($old['state'] ?? '') === 'Other' ? 'selected' : '' ?>>
                Another state — start a new chapter
              </option>
            </select>
            <?php if (isset($errors['state'])): ?><span class="field-error"><?= e($errors['state']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label for="city">Town or city</label>
            <input type="text" id="city" name="city" maxlength="120"
                   placeholder="e.g. Ibadan" value="<?= e($old['city'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="occupation">What do you do?</label>
            <input type="text" id="occupation" name="occupation" maxlength="160"
                   placeholder="e.g. Teacher, nurse, student, trader"
                   value="<?= e($old['occupation'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="availability">How often can you serve?</label>
            <select id="availability" name="availability">
              <?php foreach (['A few hours a month', 'One Saturday a month', 'Two Saturdays a month',
                              'Once a term', 'Whenever there is an outreach near me'] as $option): ?>
                <option value="<?= e($option) ?>" <?= ($old['availability'] ?? '') === $option ? 'selected' : '' ?>>
                  <?= e($option) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="heard_from">How did you hear about us?</label>
            <select id="heard_from" name="heard_from">
              <?php foreach (['A friend or family member', 'Facebook', 'Instagram', 'At an outreach',
                              'Through my church or mosque', 'A school or workplace', 'Search engine',
                              'Other'] as $option): ?>
                <option value="<?= e($option) ?>" <?= ($old['heard_from'] ?? '') === $option ? 'selected' : '' ?>>
                  <?= e($option) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group span-2 <?= isset($errors['interests']) ? 'has-error' : '' ?>">
            <label>Which programmes interest you? <span class="req" aria-hidden="true">*</span></label>
            <div class="checkbox-grid">
              <?php foreach ($programmes as $programme): ?>
                <label class="check-pill">
                  <input type="checkbox" name="interests[]" value="<?= e($programme['title']) ?>"
                         <?= in_array($programme['title'], $interests ?? [], true) ? 'checked' : '' ?>>
                  <span><?= e($programme['title']) ?></span>
                </label>
              <?php endforeach; ?>
              <label class="check-pill">
                <input type="checkbox" name="interests[]" value="Wherever I am needed"
                       <?= in_array('Wherever I am needed', $interests ?? [], true) ? 'checked' : '' ?>>
                <span>Wherever I am needed</span>
              </label>
            </div>
            <?php if (isset($errors['interests'])): ?><span class="field-error"><?= e($errors['interests']) ?></span><?php endif; ?>
          </div>

          <div class="form-group span-2">
            <label for="skills">Any skills we should know about?</label>
            <textarea id="skills" name="skills" maxlength="1500"
                      placeholder="Teaching, nursing, driving, photography, tailoring, bookkeeping, or simply a willing pair of hands."><?= e($old['skills'] ?? '') ?></textarea>
            <p class="form-hint">Optional. There is useful work here for every skill, and for none in particular.</p>
          </div>

          <div class="form-group span-2 <?= isset($errors['motivation']) ? 'has-error' : '' ?>">
            <label for="motivation">Why do you want to volunteer?</label>
            <textarea id="motivation" name="motivation" required maxlength="1500"
                      placeholder="A sentence or two is plenty."><?= e($old['motivation'] ?? '') ?></textarea>
            <?php if (isset($errors['motivation'])): ?><span class="field-error"><?= e($errors['motivation']) ?></span><?php endif; ?>
          </div>

        </div>

        <button class="btn btn-primary btn-block" type="submit" data-busy="Registering...">
          Register as a volunteer <?= icon('arrow') ?>
        </button>

        <p class="form-hint text-center u-mt">
          We use your details only to place you with a chapter and tell you about outreaches.
          We never sell or share them.
        </p>
      </form>
    </div>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
