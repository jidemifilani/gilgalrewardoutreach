<?php
/** Contact: phone, Facebook, email, address, and the contact form. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';
require_once __DIR__ . '/includes/mailer.php';

start_session_if_needed();

$phone     = get_setting($pdo, 'contact_phone', '');
$phoneAlt  = get_setting($pdo, 'contact_phone_alt', '');
$email     = get_setting($pdo, 'contact_email', '');
$volEmail  = get_setting($pdo, 'volunteer_email', '');
$facebook  = get_setting($pdo, 'facebook_url', '');
$instagram = get_setting($pdo, 'instagram_url', '');
$whatsapp  = get_setting($pdo, 'whatsapp_number', '');
$addr1     = get_setting($pdo, 'address_line', '');
$addr2     = get_setting($pdo, 'address_city', '');
$hours     = get_setting($pdo, 'office_hours', '');

$errors = [];
$old    = ['subject' => trim((string) ($_GET['subject'] ?? ''))];

// ---------------------------------------------------------------------------
// Message submission
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    $old = [
        'name'    => post_str('name', 160),
        'email'   => post_str('email', 190),
        'phone'   => post_str('phone', 40),
        'subject' => post_str('subject', 200),
        'message' => post_str('message', 2000),
    ];

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Please send the message again.';
    } elseif (looks_like_spam()) {
        flash('success', 'Thank you for your message. We will reply shortly.');
        redirect(base_url('contact.php?sent=1'));
    } elseif (!rate_limit_ok($pdo, 'contact', 5, 60)) {
        $errors['form'] = 'That is several messages from this connection in a short time. '
                        . 'Please try again in an hour, or call us instead.';
    }

    if (!$errors) {
        if ($old['name'] === '')                  $errors['name']    = 'Please tell us your name.';
        if (!valid_email($old['email']))          $errors['email']   = 'Please enter a valid email address.';
        if ($old['phone'] !== '' && !valid_phone($old['phone'])) {
                                                  $errors['phone']   = 'That does not look like a valid phone number.';
        }
        if (mb_strlen($old['message']) < 10)      $errors['message'] = 'Please write a little more so we can help properly.';
    }

    if (!$errors) {
        $pdo->prepare(
            'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $old['name'], $old['email'], $old['phone'],
            $old['subject'] !== '' ? $old['subject'] : 'General enquiry',
            $old['message'],
        ]);

        send_notification(
            $email,
            'Website enquiry: ' . ($old['subject'] !== '' ? $old['subject'] : 'General enquiry'),
            "A message was sent through the website contact form.\n\n"
            . "Name:    {$old['name']}\n"
            . "Email:   {$old['email']}\n"
            . "Phone:   {$old['phone']}\n"
            . "Subject: {$old['subject']}\n\n"
            . "Message:\n{$old['message']}\n",
            $old['email']
        );

        flash('success', 'Thank you, ' . $old['name'] . '. Your message has been received — '
            . 'we reply to everything within two working days.');
        redirect(base_url('contact.php?sent=1'));
    }
}

$pageTitle       = 'Contact Us';
$pageDescription = 'Call us, message us on Facebook, email us, or send a message through the form.';
$bannerEyebrow   = 'Contact us';
$bannerTitle     = 'Talk to us';
$bannerLede      = 'Whether you want to volunteer, partner with a programme, invite us to a community '
                 . 'or simply ask a question — here is how to reach us.';
$breadcrumbs     = [['label' => 'Contact Us']];

require __DIR__ . '/includes/header.php';
?>

<!-- ===================== METHODS ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="split">

      <div>
        <p class="eyebrow">Reach us directly</p>
        <h2 class="h-xl">Four ways to get in touch</h2>
        <p class="lede">We answer the phone during office hours and reply to every email and
          Facebook message within two working days.</p>

        <div class="contact-methods u-mt-lg">

          <?php if ($phone): ?>
            <a class="contact-method" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>">
              <span class="contact-method-icon"><?= icon('phone') ?></span>
              <span>
                <span class="contact-method-label">Call us</span>
                <span class="contact-method-value"><?= e($phone) ?></span>
                <?php if ($phoneAlt): ?>
                  <span class="u-fs-sm text-muted">or <?= e($phoneAlt) ?></span>
                <?php endif; ?>
              </span>
            </a>
          <?php endif; ?>

          <?php if ($facebook): ?>
            <a class="contact-method" href="<?= e($facebook) ?>" target="_blank" rel="noopener noreferrer">
              <span class="contact-method-icon"><?= icon('facebook') ?></span>
              <span>
                <span class="contact-method-label">Facebook</span>
                <span class="contact-method-value">Message us on Facebook</span>
                <span class="u-fs-sm text-muted"><?= e(preg_replace('#^https?://#', '', $facebook)) ?></span>
              </span>
            </a>
          <?php endif; ?>

          <?php if ($email): ?>
            <a class="contact-method" href="mailto:<?= e($email) ?>">
              <span class="contact-method-icon"><?= icon('mail') ?></span>
              <span>
                <span class="contact-method-label">Email us</span>
                <span class="contact-method-value"><?= email_html($email) ?></span>
                <?php if ($volEmail && $volEmail !== $email): ?>
                  <span class="u-fs-sm text-muted">Volunteering: <?= email_html($volEmail) ?></span>
                <?php endif; ?>
              </span>
            </a>
          <?php endif; ?>

          <?php if ($addr1 || $addr2): ?>
            <div class="contact-method u-static">
              <span class="contact-method-icon"><?= icon('pin') ?></span>
              <span>
                <span class="contact-method-label">Visit the office</span>
                <span class="contact-method-value"><?= e($addr1) ?></span>
                <span class="u-fs-sm text-muted"><?= e($addr2) ?><?= $hours ? ' · ' . e($hours) : '' ?></span>
              </span>
            </div>
          <?php endif; ?>

        </div>

        <?php if ($whatsapp || $instagram): ?>
          <p class="u-mt-lg"><strong>Prefer to chat?</strong>
            <?php if ($whatsapp): ?>
              <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Message us on WhatsApp</a>
            <?php endif; ?>
            <?php if ($whatsapp && $instagram): ?> or <?php endif; ?>
            <?php if ($instagram): ?>
              <a href="<?= e($instagram) ?>" target="_blank" rel="noopener noreferrer">find us on Instagram</a><?php endif; ?>.
          </p>
        <?php endif; ?>
      </div>

      <div class="split-media">
        <?= illu_contact() ?>
      </div>

    </div>
  </div>
</section>


<!-- ===================== FORM ===================== -->
<section class="section section-paper" id="message">
  <div class="container">

    <div class="text-center u-mb-lg">
      <p class="eyebrow eyebrow-center">Send a message</p>
      <h2 class="h-xl">Write to us</h2>
      <p class="lede mx-auto text-center">Everything sent here reaches our inbox directly.
        We reply within two working days.</p>
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

      <form class="form-card" method="post" action="<?= e(base_url('contact.php')) ?>#message" data-enhance>
        <?= csrf_field() ?>
        <?= honeypot_field() ?>

        <div class="form-grid">

          <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
            <label for="name">Your name</label>
            <input type="text" id="name" name="name" required maxlength="160"
                   autocomplete="name" placeholder="e.g. Chinedu Okafor"
                   value="<?= e($old['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
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
            <input type="tel" id="phone" name="phone" maxlength="40"
                   autocomplete="tel" placeholder="Optional"
                   value="<?= e($old['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?><span class="field-error"><?= e($errors['phone']) ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label for="subject">What is this about?</label>
            <input type="text" id="subject" name="subject" maxlength="200"
                   placeholder="e.g. Partnership, donation, invite us to a community"
                   value="<?= e($old['subject'] ?? '') ?>">
          </div>

          <div class="form-group span-2 <?= isset($errors['message']) ? 'has-error' : '' ?>">
            <label for="message">Your message</label>
            <textarea id="message" name="message" required maxlength="2000"
                      placeholder="Tell us how we can help."><?= e($old['message'] ?? '') ?></textarea>
            <?php if (isset($errors['message'])): ?><span class="field-error"><?= e($errors['message']) ?></span><?php endif; ?>
          </div>

        </div>

        <button class="btn btn-primary btn-block" type="submit" data-busy="Sending...">
          Send message <?= icon('arrow') ?>
        </button>

        <p class="form-hint text-center u-mt">
          We use your details only to reply to you. We never sell or share them.
        </p>
      </form>
    </div>

  </div>
</section>


<!-- ===================== VOLUNTEER NUDGE ===================== -->
<section class="section section-cream">
  <div class="container">
    <div class="cta-band">
      <div class="split">
        <div>
          <p class="eyebrow">Or skip the form</p>
          <h2 class="h-xl">If you are writing to volunteer, register instead</h2>
          <p class="lede">The volunteer form asks the few extra things a coordinator needs to place
            you with a team near you — it will get you a faster answer than a general message.</p>
          <div class="btn-row u-mt-lg">
            <a class="btn btn-accent" href="<?= e(base_url('volunteer.php#register')) ?>">
              Register to volunteer <?= icon('arrow') ?>
            </a>
            <a class="btn btn-outline-light" href="<?= e(base_url('about.php')) ?>">Read about us first</a>
          </div>
        </div>
        <div class="split-media"><?= illu_join() ?></div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
