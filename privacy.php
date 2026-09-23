<?php
/** Privacy notice. */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$siteName = site_name($pdo);
$email    = get_setting($pdo, 'contact_email', '');
$phone    = get_setting($pdo, 'contact_phone', '');

$pageTitle       = 'Privacy';
$pageDescription = 'What personal information this website collects, why, and how to have it removed.';
$bannerEyebrow   = 'Privacy';
$bannerTitle     = 'What we do with your details';
$bannerLede      = 'Short version: we collect only what a form asks for, we use it only to reply to '
                 . 'you or place you with a team, and we never sell or share it.';
$breadcrumbs     = [['label' => 'Privacy']];

require __DIR__ . '/includes/header.php';
?>

<section class="section section-cream">
  <div class="container-narrow">

    <p class="text-muted"><strong>Last updated:</strong> <?= e(date('F Y')) ?></p>

    <h2 class="h-lg">What we collect</h2>
    <p>Only what you type into a form on this site:</p>
    <ul>
      <li><strong>Volunteer registration</strong> — your name, email, phone, state, town, occupation,
        availability, the programmes that interest you, any skills you list and why you want to volunteer.</li>
      <li><strong>Contact form</strong> — your name, email, optional phone, subject and message.</li>
      <li><strong>Support pledge</strong> — your name, email, optional phone, organisation and what you are offering.</li>
      <li><strong>Story submission</strong> — your name, role, state, optional email and the story itself.</li>
      <li><strong>Newsletter</strong> — your email address, and your name if you give it.</li>
    </ul>

    <p>We also keep a short technical record of form submissions — the IP address and a timestamp —
      purely to stop the same connection flooding our inbox. Those records are deleted automatically
      after 24 hours.</p>

    <h2 class="h-lg">What we never collect</h2>
    <p>This website does not ask for card numbers, bank account details or any payment information,
      and nobody from <?= e($siteName) ?> will ever telephone you to ask for them. Giving is by bank
      transfer using the details published on our Support page, and the transfer happens at your bank,
      never here.</p>

    <h2 class="h-lg">Why we hold it</h2>
    <p>To reply to you, to place you with a volunteer chapter near where you live, to tell you about
      outreaches and events, and to keep an accurate record of who has served with us. Nothing else.</p>

    <h2 class="h-lg">Who sees it</h2>
    <p>Our own coordinators and administrators. We do not sell your details, we do not rent them, and
      we do not pass them to any other organisation for their own use.</p>

    <h2 class="h-lg">Cookies</h2>
    <p>This site sets one session cookie, which keeps your form submission secure while you are filling
      it in. It carries no advertising or tracking identifier and expires when you close your browser.</p>
    <p>If you use the dark-mode switch or dismiss the cookie notice, that preference is saved in your own
      browser's local storage. It never reaches our server.</p>
    <p>There is no analytics, no advertising network and no third-party tracking on this website.</p>

    <h2 class="h-lg">How long we keep it</h2>
    <p>Volunteer and supporter records are kept for as long as you are involved with us, and for two
      years afterwards in case you come back. Contact messages are kept for two years. Newsletter
      subscriptions are kept until you unsubscribe.</p>

    <h2 class="h-lg">Your choices</h2>
    <ul>
      <li><strong>See what we hold about you.</strong> Ask, and we will send you a copy.</li>
      <li><strong>Correct it.</strong> Tell us what is wrong and we will fix it.</li>
      <li><strong>Have it deleted.</strong> Ask, and we will remove your record entirely.</li>
      <li><strong>Leave the newsletter.</strong> Every newsletter carries a one-click unsubscribe link.</li>
    </ul>

    <div class="card u-mt-lg">
      <h3 class="h-md">Asking us about your data</h3>
      <p>Email <?php if ($email): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php else: ?>our office<?php endif; ?>
        <?php if ($phone): ?>or call <?= e($phone) ?><?php endif; ?>,
        with the words &ldquo;my data&rdquo; in the subject. We answer within two working days and act
        within thirty days.</p>
      <div class="btn-row u-mt">
        <a class="btn btn-primary" href="<?= e(base_url('contact.php?subject=' . urlencode('My data'))) ?>">
          Contact us about your data <?= icon('arrow') ?>
        </a>
      </div>
    </div>

    <p class="u-mt-lg text-muted"><small>This notice describes how a small Nigerian non-profit actually
      handles the details you give it. It is written plainly on purpose and is not a substitute for legal
      advice on the Nigeria Data Protection Act.</small></p>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
