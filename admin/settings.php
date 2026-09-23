<?php
/**
 * Site settings: every piece of copy and contact detail on the public site,
 * plus the signed-in admin's own password.
 *
 * Renaming the organisation is a single change to "Site name" here — nothing
 * on the public site hardcodes the brand.
 */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';
require_once __DIR__ . '/../includes/theme.php';

$pageTitle = 'Settings';

/** Grouped so the page reads as sections rather than one long list. */
$groups = [
    'Identity' => [
        'site_name'       => ['Site name', 'text', 'Shown in the header, footer, page titles and emails.'],
        'site_short_name' => ['Short name', 'text', 'Used in the header logo where the full name would be too long.'],
        'tagline'         => ['Tagline', 'text', ''],
        'founded_year'    => ['Founded', 'text', ''],
        'footer_note'     => ['Footer note', 'textarea', 'Registration details or legal note, shown at the very bottom.'],
    ],
    'Homepage hero' => [
        'hero_eyebrow'    => ['Eyebrow label', 'text', 'The small line above the headline.'],
        'hero_heading'    => ['Headline', 'text', 'The last word is highlighted automatically.'],
        'hero_subheading' => ['Sub-heading', 'textarea', ''],
    ],
    'About' => [
        'mission'     => ['Mission', 'textarea', ''],
        'vision'      => ['Vision', 'textarea', ''],
        'about_story' => ['Our story', 'textarea', 'Leave a blank line between paragraphs.'],
    ],
    'Volunteer page' => [
        'volunteer_intro'   => ['Intro paragraph', 'textarea', 'Shown under the volunteer page heading.'],
        'volunteer_promise' => ['What volunteering involves', 'textarea', ''],
    ],
    'Call to action' => [
        'cta_heading' => ['Heading', 'text', 'Used in the green band at the bottom of most pages.'],
        'cta_text'    => ['Text', 'textarea', ''],
    ],
    'Contact details' => [
        'contact_phone'     => ['Phone number', 'text', 'Shown on the contact page and in the footer.'],
        'contact_phone_alt' => ['Second phone number', 'text', 'Optional.'],
        'contact_email'     => ['Email address', 'text', ''],
        'volunteer_email'   => ['Volunteering email', 'text', 'Where volunteer registrations are sent.'],
        'address_line'      => ['Street address', 'text', ''],
        'address_city'      => ['City and state', 'text', ''],
        'office_hours'      => ['Office hours', 'text', ''],
    ],
    'Giving' => [
        'bank_name'           => ['Bank name', 'text', 'Shown on the Support page.'],
        'bank_account_name'   => ['Account name', 'text', ''],
        'bank_account_number' => ['Account number', 'text', ''],
        'giving_note'         => ['Note about giving', 'textarea', ''],
    ],
    'Newsletter and stories' => [
        'newsletter_note' => ['Newsletter blurb', 'textarea', 'Shown beside the footer sign-up box.'],
        'stories_intro'   => ['Stories page intro', 'textarea', ''],
    ],
    'Theme' => [
        'brand_color'        => ['Brand colour', 'color',
                                 'Everything else is worked out from this: the deep shade, the pale panels and the text colour that sits on them.'],
        'accent_color'       => ['Accent colour', 'color',
                                 'Used for highlights, the eyebrow dashes and the amber buttons.'],
        'default_theme'      => ['Default appearance', 'select',
                                 'What a first-time visitor sees. "Follow their device" uses their own light or dark setting.',
                                 ['system' => 'Follow their device', 'light' => 'Always light', 'dark' => 'Always dark']],
        'allow_theme_toggle' => ['Let visitors switch appearance', 'toggle',
                                 'Shows the sun/moon button in the header. Their choice is remembered in their own browser.'],
    ],
    'Branding images' => [
        'logo_image'    => ['Logo', 'image',
                            'Shown in the header, the footer and the admin panel. A wide PNG or SVG with a transparent background works best, around 200x60. Leave empty to use the built-in drawn mark.'],
        'favicon_image' => ['Browser tab icon', 'image',
                            'A square image, 64x64 or larger. Leave empty to use the built-in mark.'],
        'social_image'  => ['Social sharing image', 'image',
                            'Shown when a page is shared on Facebook, WhatsApp or X. 1200x630 works best. Leave empty to use a generated illustration.'],
    ],
    'Site behaviour' => [
        'maintenance_mode'    => ['Maintenance mode', 'toggle',
                                  'Turn on to show a holding page to visitors. The admin panel stays open, and signed-in admins still see the real site.'],
        'maintenance_message' => ['Holding page message', 'textarea', ''],
        'session_timeout_min' => ['Admin idle timeout (minutes)', 'text',
                                  'How long an admin session can sit unused before it is signed out. Minimum 5.'],
    ],
    'Social links' => [
        'facebook_url'    => ['Facebook page URL', 'text', 'Full address, including https://'],
        'instagram_url'   => ['Instagram URL', 'text', 'Leave blank to hide the icon.'],
        'twitter_url'     => ['X / Twitter URL', 'text', 'Leave blank to hide the icon.'],
        'whatsapp_number' => ['WhatsApp number', 'text', 'Digits only with country code, e.g. 2348030000000. Leave blank to hide the chat button.'],
    ],
];

$errors  = [];
$notices = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Nothing was saved — please try again.';
    } else {

        // --- Settings -----------------------------------------------------
        if (($_POST['action'] ?? '') === 'settings') {
            $changed = 0;

            foreach ($groups as $fields) {
                foreach ($fields as $key => $meta) {
                    $type = $meta[1] ?? 'text';

                    // Images arrive as files, not POST fields, and an unchecked
                    // checkbox posts nothing at all -- so neither can be handled
                    // by the "skip absent keys" rule the text fields use.
                    if ($type === 'image') {
                        $existing = get_setting($pdo, $key, '');

                        [$uploaded, $uploadError] = crud_upload_image($key);

                        if ($uploadError) {
                            $errors[$key] = $uploadError;
                            continue;
                        }

                        if ($uploaded !== null) {
                            if ($existing !== '') {
                                crud_delete_upload($existing);
                            }
                            set_setting($pdo, $key, $uploaded);
                            $changed++;
                        } elseif (!empty($_POST['remove_' . $key])) {
                            if ($existing !== '') {
                                crud_delete_upload($existing);
                            }
                            set_setting($pdo, $key, '');
                            $changed++;
                        }
                        continue;
                    }

                    if ($type !== 'toggle' && !array_key_exists($key, $_POST)) {
                        continue;
                    }

                    $value = match ($type) {
                        'toggle' => isset($_POST[$key]) ? '1' : '0',
                        // Normalised here so a typo cannot reach the stylesheet.
                        'color'  => hex_norm((string) $_POST[$key],
                                        $key === 'accent_color' ? THEME_DEFAULT_ACCENT : THEME_DEFAULT_BRAND),
                        'select' => in_array((string) $_POST[$key], array_keys($meta[3] ?? []), true)
                                        ? (string) $_POST[$key]
                                        : get_setting($pdo, $key, ''),
                        default  => mb_substr(trim((string) $_POST[$key]), 0, 20000),
                    };

                    if ($value !== get_setting($pdo, $key, '')) {
                        $changed++;
                    }
                    set_setting($pdo, $key, $value);
                }
            }

            if ($errors) {
                flash('error', 'Some settings were saved, but an image could not be uploaded. See below.');
            }

            audit($pdo, admin_id(), 'settings.update', $changed . ' setting(s) changed');

            if (!$errors) {
                flash('success', $changed > 0
                    ? $changed . ' setting' . ($changed === 1 ? '' : 's') . ' saved.'
                    : 'Settings saved.');
                redirect(base_url('admin/settings.php'));
            }
        }

        // --- Password -----------------------------------------------------
        if (($_POST['action'] ?? '') === 'password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new     = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
            $stmt->execute([admin_id()]);
            $hash = (string) $stmt->fetchColumn();

            if (!password_verify($current, $hash)) {
                $errors['current_password'] = 'That is not your current password.';
            } elseif (strlen($new) < 10) {
                $errors['new_password'] = 'Use at least 10 characters.';
            } elseif ($new !== $confirm) {
                $errors['confirm_password'] = 'The two new passwords do not match.';
            } else {
                $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), admin_id()]);

                audit($pdo, admin_id(), 'password.change', '');
                flash('success', 'Your password has been changed.');
                redirect(base_url('admin/settings.php'));
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($errors['form'])): ?>
  <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('admin/settings.php')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="settings">

  <?php foreach ($groups as $groupName => $fields): ?>
    <div class="panel">
      <div class="panel-head"><h2><?= e($groupName) ?></h2></div>

      <div class="form-grid">
        <?php foreach ($fields as $key => $meta): ?>
          <?php [$label, $type, $hint] = $meta; ?>
          <div class="form-group <?= in_array($type, ['textarea', 'image'], true) ? 'span-2' : '' ?>
                                  <?= isset($errors[$key]) ? 'has-error' : '' ?>">
            <label for="s_<?= e($key) ?>"><?= e($label) ?></label>

            <?php if ($type === 'image'): ?>
              <?php $current = theme_image($pdo, $key); ?>
              <?php if ($current !== ''): ?>
                <div class="upload-preview">
                  <img src="<?= e($current) ?>" alt="Current <?= e(strtolower($label)) ?>">
                  <label class="check-pill">
                    <input type="checkbox" name="remove_<?= e($key) ?>">
                    <span>Remove this image</span>
                  </label>
                </div>
              <?php endif; ?>
              <input type="file" id="s_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/*">

            <?php elseif ($type === 'color'): ?>
              <?php
                $fallback = $key === 'accent_color' ? THEME_DEFAULT_ACCENT : THEME_DEFAULT_BRAND;
                $current  = hex_norm(get_setting($pdo, $key, $fallback), $fallback);
              ?>
              <div class="color-field">
                <input type="color" id="s_<?= e($key) ?>" value="<?= e($current) ?>"
                       aria-label="<?= e($label) ?> picker" data-color-for="hex_<?= e($key) ?>">
                <input type="text" id="hex_<?= e($key) ?>" name="<?= e($key) ?>"
                       value="<?= e($current) ?>" maxlength="7" spellcheck="false"
                       aria-label="<?= e($label) ?> hex value">
              </div>

            <?php elseif ($type === 'select'): ?>
              <select id="s_<?= e($key) ?>" name="<?= e($key) ?>">
                <?php foreach (($meta[3] ?? []) as $optValue => $optLabel): ?>
                  <option value="<?= e((string) $optValue) ?>"
                    <?= get_setting($pdo, $key, '') === (string) $optValue ? 'selected' : '' ?>>
                    <?= e((string) $optLabel) ?>
                  </option>
                <?php endforeach; ?>
              </select>

            <?php elseif ($type === 'toggle'): ?>
              <label class="check-pill">
                <input type="checkbox" name="<?= e($key) ?>" value="1"
                       <?= get_setting($pdo, $key, '0') === '1' ? 'checked' : '' ?>>
                <span>Turn this on</span>
              </label>
            <?php elseif ($type === 'textarea'): ?>
              <textarea id="s_<?= e($key) ?>" name="<?= e($key) ?>" rows="5"><?= e(get_setting($pdo, $key, '')) ?></textarea>
            <?php else: ?>
              <input type="text" id="s_<?= e($key) ?>" name="<?= e($key) ?>"
                     value="<?= e(get_setting($pdo, $key, '')) ?>">
            <?php endif; ?>

            <?php if ($hint): ?><p class="form-hint"><?= e($hint) ?></p><?php endif; ?>
            <?php if (isset($errors[$key])): ?>
              <span class="field-error"><?= e($errors[$key]) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="panel">
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Save all settings</button>
      <a class="btn btn-ghost" href="<?= e(base_url()) ?>" target="_blank" rel="noopener">
        <?= icon('external') ?> Preview the site
      </a>
      <a class="btn btn-ghost" href="<?= e(base_url('admin/two-factor.php')) ?>">
        Two-factor authentication
      </a>
    </div>
  </div>
</form>


<div class="panel">
  <div class="panel-head"><h2>Change your password</h2></div>

  <form method="post" action="<?= e(base_url('admin/settings.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">

    <div class="form-grid">
      <div class="form-group <?= isset($errors['current_password']) ? 'has-error' : '' ?>">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        <?php if (isset($errors['current_password'])): ?>
          <span class="field-error"><?= e($errors['current_password']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group <?= isset($errors['new_password']) ? 'has-error' : '' ?>">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="10">
        <p class="form-hint">At least 10 characters.</p>
        <?php if (isset($errors['new_password'])): ?>
          <span class="field-error"><?= e($errors['new_password']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
        <?php if (isset($errors['confirm_password'])): ?>
          <span class="field-error"><?= e($errors['confirm_password']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Change password</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
