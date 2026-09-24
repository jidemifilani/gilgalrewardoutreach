<?php
/** Shared site footer, floating actions and script tags. */

$footerStates     = active_states($pdo);
$footerProgrammes = active_programmes($pdo);

$phone     = get_setting($pdo, 'contact_phone', '');
$email     = get_setting($pdo, 'contact_email', '');
$facebook  = get_setting($pdo, 'facebook_url', '');
$instagram = get_setting($pdo, 'instagram_url', '');
$twitter   = get_setting($pdo, 'twitter_url', '');
$whatsapp  = get_setting($pdo, 'whatsapp_number', '');
$addr1     = get_setting($pdo, 'address_line', '');
$addr2     = get_setting($pdo, 'address_city', '');
?>
</main>

<footer class="site-footer">

  <div class="footer-newsletter">
    <div class="container footer-newsletter-inner">
      <div>
        <h4>Our monthly note</h4>
        <p class="u-m0"><?= e(get_setting($pdo, 'newsletter_note', '')) ?></p>
      </div>

      <form class="newsletter-form" method="post" action="<?= e(base_url('subscribe.php')) ?>" data-enhance>
        <?= csrf_field() ?>
        <?= honeypot_field() ?>
        <input type="hidden" name="source" value="footer">
        <input type="hidden" name="return_to" value="<?= e(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>">

        <label class="sr-only" for="newsletterEmail">Your email address</label>
        <input type="email" id="newsletterEmail" name="email" required
               maxlength="190" autocomplete="email" placeholder="you@example.com">

        <button class="btn btn-accent" type="submit" data-busy="Adding...">
          Subscribe
        </button>
      </form>
    </div>
    <p class="container newsletter-note">No appeals for money. Unsubscribe in one click.</p>
  </div>

  <div class="container">
    <div class="footer-grid">

      <div class="footer-brand">
        <span class="brand u-static">
          <?php if ($logoImage !== ''): ?>
            <img class="brand-logo" src="<?= e($logoImage) ?>" alt="<?= e($siteName) ?>">
          <?php else: ?>
            <?= illu_logo('brand-mark', $brandColor, $accentColor) ?>
            <span class="brand-text">
              <span class="brand-name"><?= e($shortName) ?></span>
              <span class="brand-tag">Outreach</span>
            </span>
          <?php endif; ?>
        </span>
        <p class="footer-about"><?= e(get_setting($pdo, 'tagline', '')) ?>. <?= e(excerpt(get_setting($pdo, 'mission', ''), 120)) ?></p>

        <div class="social-row">
          <?php if ($facebook): ?>
            <a class="social-btn" href="<?= e($facebook) ?>" target="_blank" rel="noopener noreferrer"
               aria-label="Visit our Facebook page"><?= icon('facebook') ?></a>
          <?php endif; ?>
          <?php if ($instagram): ?>
            <a class="social-btn" href="<?= e($instagram) ?>" target="_blank" rel="noopener noreferrer"
               aria-label="Visit our Instagram page"><?= icon('instagram') ?></a>
          <?php endif; ?>
          <?php if ($twitter): ?>
            <a class="social-btn" href="<?= e($twitter) ?>" target="_blank" rel="noopener noreferrer"
               aria-label="Visit our X page"><?= icon('twitter') ?></a>
          <?php endif; ?>
          <?php if ($whatsapp): ?>
            <a class="social-btn" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
               aria-label="Chat with us on WhatsApp"><?= icon('whatsapp') ?></a>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <h4>Explore</h4>
        <ul class="footer-links">
          <?php foreach (nav_items() as $item): ?>
            <li><a href="<?= e(base_url($item['path'])) ?>"><?= e($item['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h4>What we do</h4>
        <ul class="footer-links">
          <?php foreach ($footerProgrammes as $programme): ?>
            <li>
              <a href="<?= e(base_url('about.php#programmes')) ?>"><?= e($programme['title']) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div>
        <h4>Reach us</h4>
        <ul class="footer-contact">
          <?php if ($phone): ?>
            <li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></li>
          <?php endif; ?>
          <?php if ($email): ?>
            <li><?= icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= email_html($email) ?></a></li>
          <?php endif; ?>
          <?php if ($addr1 || $addr2): ?>
            <li><?= icon('pin') ?><span><?= e($addr1) ?><?= $addr1 && $addr2 ? '<br>' : '' ?><?= e($addr2) ?></span></li>
          <?php endif; ?>
        </ul>

        <?php if ($footerStates): ?>
          <h4 class="u-mt-lg">Where we serve</h4>
          <p class="u-fs-sm u-m0"><?= e(state_sentence($footerStates, 8)) ?>.</p>
        <?php endif; ?>
      </div>

    </div>

    <div class="footer-bottom">
      <p class="u-m0">&copy; <?= date('Y') ?> <?= e($siteName) ?>. <?= e(get_setting($pdo, 'footer_note', '')) ?></p>
      <p class="u-m0">
        <a href="<?= e(base_url('volunteer.php#register')) ?>">Volunteer</a> &middot;
        <a href="<?= e(base_url('support.php')) ?>">Support us</a> &middot;
        <a href="<?= e(base_url('privacy.php')) ?>">Privacy</a> &middot;
        <a href="<?= e(base_url('terms.php')) ?>">Terms</a>
      </p>
    </div>
  </div>
</footer>

<!-- Shown once, then remembered in the visitor's own browser. -->
<div class="cookie-note" role="region" aria-label="Cookie notice">
  <p>This site uses one cookie to keep forms secure, and remembers your dark-mode choice in your
     own browser. There is no tracking and no advertising.
     <a href="<?= e(base_url('privacy.php')) ?>">Read the privacy notice</a>.</p>
  <div class="btn-row">
    <button class="btn btn-primary btn-sm" type="button" data-dismiss>Got it</button>
  </div>
</div>

<!-- Phone-width action bar: the two things most visitors came to do. -->
<div class="mobile-bar">
  <a class="btn btn-primary" href="<?= e(base_url('volunteer.php#register')) ?>">Volunteer</a>
  <a class="btn btn-ghost" href="<?= e(base_url('support.php')) ?>">Support us</a>
</div>

<?php if ($whatsapp): ?>
  <a class="wa-fab" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= e(rawurlencode('Hello ' . $siteName . ', I would like to know more about your work.')) ?>"
     target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp">
    <?= icon('whatsapp') ?>
  </a>
<?php endif; ?>

<button class="to-top" type="button" aria-label="Back to top"><?= icon('arrow-up') ?></button>

<script src="<?= e(asset_url('js/main.js')) ?>?v=3"></script>
</body>
</html>
