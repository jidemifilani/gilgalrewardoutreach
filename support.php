<?php
/**
 * Support us: the ways to help, the bank details, and a pledge form.
 *
 * There is deliberately no payment processing anywhere in this project. The
 * form records an intention to support so the team can follow up; no card or
 * account details are ever collected, transmitted or stored by this site.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';
require_once __DIR__ . '/includes/mailer.php';

start_session_if_needed();

$programmes = active_programmes($pdo);

$errors = [];
$old    = ['support_type' => trim((string) ($_GET['type'] ?? ''))];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    $old = [
        'name'         => post_str('name', 160),
        'email'        => post_str('email', 190),
        'phone'        => post_str('phone', 40),
        'organisation' => post_str('organisation', 190),
        'support_type' => post_str('support_type', 120),
        'message'      => post_str('message', 2000),
    ];

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Please send the form again.';
    } elseif (looks_like_spam()) {
        flash('success', 'Thank you. We will be in touch shortly.');
        redirect(base_url('support.php?sent=1'));
    } elseif (!rate_limit_ok($pdo, 'pledge', 4, 60)) {
        $errors['form'] = 'That is several submissions from this connection in a short time. '
                        . 'Please try again in an hour, or call us instead.';
    }

    if (!$errors) {
        if ($old['name'] === '')             $errors['name']  = 'Please tell us your name.';
        if (!valid_email($old['email']))     $errors['email'] = 'Please enter a valid email address.';
        if ($old['phone'] !== '' && !valid_phone($old['phone'])) {
                                             $errors['phone'] = 'That does not look like a valid phone number.';
        }
        if ($old['support_type'] === '')     $errors['support_type'] = 'Please tell us how you would like to help.';
    }

    if (!$errors) {
        $pdo->prepare(
            'INSERT INTO pledges (name, email, phone, organisation, support_type, message)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $old['name'], $old['email'], $old['phone'],
            $old['organisation'], $old['support_type'], $old['message'],
        ]);

        send_notification(
            get_setting($pdo, 'contact_email', ''),
            'New support pledge: ' . $old['name'],
            "Someone offered support through the website.\n\n"
            . "Name:         {$old['name']}\n"
            . "Email:        {$old['email']}\n"
            . "Phone:        {$old['phone']}\n"
            . "Organisation: {$old['organisation']}\n"
            . "Offering:     {$old['support_type']}\n\n"
            . "Message:\n{$old['message']}\n",
            $old['email']
        );

        flash('success', 'Thank you, ' . $old['name'] . '. We have your offer and will be in touch '
            . 'within two working days.');
        redirect(base_url('support.php?sent=1'));
    }
}

$bankName    = get_setting($pdo, 'bank_name', '');
$accountName = get_setting($pdo, 'bank_account_name', '');
$accountNo   = get_setting($pdo, 'bank_account_number', '');

$pageTitle       = 'Support us';
$pageDescription = 'Ways to support the work: give, donate goods, offer a skill or partner with a programme.';
$bannerEyebrow   = 'Support us';
$bannerTitle     = 'Hope rises faster when more hands lift it';
$bannerLede      = 'Money is only one of the ways to help, and rarely the most useful. '
                 . 'Here is everything we actually need.';
$breadcrumbs     = [['label' => 'Get Involved'], ['label' => 'Support us']];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== WAYS TO HELP ===================== -->
<section class="section section-cream">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Four ways to help</p>
      <h2 class="h-xl">Pick whichever costs you least</h2>
    </div>

    <div class="grid grid-4">
      <?php
        $ways = [
          ['people', 'Give your time',   'Join a chapter near you. Four Saturdays a month changes what a whole class can read by June.', 'Volunteering', base_url('volunteer.php#register')],
          ['relief', 'Give goods',       'Exercise books, uniforms, mathematical sets, food staples, medical consumables, working laptops.', 'Donating goods', '#pledge'],
          ['skills', 'Give a skill',     'Teachers, nurses, doctors, accountants, photographers, drivers and trainers are all useful to us.', 'Offering a skill', '#pledge'],
          ['heart',  'Give money',       'Receipted, reported quarterly and independently audited once a year. Bank details are below.', 'A financial gift', '#giving'],
        ];
        foreach ($ways as $i => $way):
      ?>
        <article class="card reveal reveal-delay-<?= $i % 4 ?>">
          <?= illu_programme_icon($way[0]) ?>
          <h3 class="h-md"><?= e($way[1]) ?></h3>
          <p><?= e($way[2]) ?></p>
          <a class="link-arrow" href="<?= e($way[4]) ?>">
            <?= str_starts_with($way[4], '#') ? 'Tell us' : 'Register' ?> <?= icon('arrow') ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- ===================== GIVING ===================== -->
<section class="section section-paper" id="giving">
  <div class="container">
    <div class="split">

      <div>
        <p class="eyebrow">Giving money</p>
        <h2 class="h-xl">Straight to the work, and accounted for</h2>
        <p class="lede"><?= e(get_setting($pdo, 'giving_note', '')) ?></p>

        <div class="alert alert-info u-mt-lg">
          <?= icon('sparkle') ?>
          <span>We take bank transfers only. This website never asks for card details,
            and nobody from Gilgal Reward Outreach will ever ring you to ask for them.</span>
        </div>
      </div>

      <div class="split-media">
        <div class="bank-card">
          <p class="eyebrow">Bank transfer</p>

          <div class="bank-row">
            <span class="bank-label">Bank</span>
            <span class="bank-value"><?= e($bankName) ?></span>
          </div>
          <div class="bank-row">
            <span class="bank-label">Account name</span>
            <span class="bank-value"><?= e($accountName) ?></span>
          </div>
          <div class="bank-row">
            <span class="bank-label">Account number</span>
            <span class="bank-value"><?= e($accountNo) ?></span>
          </div>

          <p class="form-hint u-mt">
            Please use your name as the transfer reference so we can receipt it,
            then tell us below and we will send the receipt.
          </p>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ===================== PLEDGE FORM ===================== -->
<section class="section section-cream" id="pledge">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Tell us</p>
      <h2 class="h-xl">What can you offer?</h2>
      <p class="lede mx-auto text-center">Fill this in and a coordinator will come back to you within
        two working days to work out the practical details.</p>
    </div>

    <div class="container-narrow">
      <?php if (!empty($errors['form'])): ?>
        <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($errors['form']) ?></span></div>
      <?php elseif ($errors): ?>
        <div class="alert alert-error" role="alert">
          <?= icon('sparkle') ?><span>Please check the highlighted fields below and try again.</span>
        </div>
      <?php endif; ?>

      <form class="form-card" method="post" action="<?= e(base_url('support.php')) ?>#pledge" data-enhance>
        <?= csrf_field() ?>
        <?= honeypot_field() ?>

        <div class="form-grid">

          <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
            <label for="name">Your name</label>
            <input type="text" id="name" name="name" required maxlength="160"
                   autocomplete="name" value="<?= e($old['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required maxlength="190"
                   autocomplete="email" value="<?= e($old['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
            <label for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone" maxlength="40"
                   autocomplete="tel" placeholder="Optional" value="<?= e($old['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?><span class="field-error"><?= e($errors['phone']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label for="organisation">Organisation</label>
            <input type="text" id="organisation" name="organisation" maxlength="190"
                   placeholder="If you are writing on behalf of one"
                   value="<?= e($old['organisation'] ?? '') ?>">
          </div>

          <div class="form-group span-2 <?= isset($errors['support_type']) ? 'has-error' : '' ?>">
            <label for="support_type">How would you like to help?</label>
            <select id="support_type" name="support_type" required>
              <option value="">Choose one</option>
              <?php foreach ([
                'A financial gift', 'Donating goods', 'Offering a skill', 'Volunteering',
                'Partnering a programme', 'Offering a venue or transport', 'Something else',
              ] as $option): ?>
                <option value="<?= e($option) ?>" <?= ($old['support_type'] ?? '') === $option ? 'selected' : '' ?>>
                  <?= e($option) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['support_type'])): ?><span class="field-error"><?= e($errors['support_type']) ?></span><?php endif; ?>
          </div>

          <div class="form-group span-2">
            <label for="message">Anything else we should know?</label>
            <textarea id="message" name="message" maxlength="2000"
                      placeholder="What you have in mind, roughly when, and anything you would want from us."><?= e($old['message'] ?? '') ?></textarea>
          </div>

        </div>

        <button class="btn btn-primary btn-block" type="submit" data-busy="Sending...">
          Send this to the team <?= icon('arrow') ?>
        </button>

        <p class="form-hint text-center u-mt">
          We never ask for card or account details on this website, and we never pass your
          details to anyone else.
        </p>
      </form>
    </div>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
