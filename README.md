# Gilgal Reward Outreach

A website for a Nigerian NGO: Home, About Us, Outreaches, Gallery, Events, Volunteer
and Contact, plus an admin panel for managing every piece of content on it.

PHP 8 + MySQL, no framework. Runs on XAMPP at `http://localhost/gilgalrewardoutreach`.

---

## Getting it running

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Import the database:

   ```
   mysql -u root < database/schema.sql
   ```

   This drops and recreates the `gilgalrewardoutreach` database and seeds it with
   placeholder content, so it is safe to re-run whenever you want a clean slate.

   To take the v2 additions into an **existing** database without wiping it, run
   `mysql -u root gilgalrewardoutreach < database/upgrade_v2.sql`, then
   `php database/migrate.php`. The SQL file adds the new tables and settings; the PHP
   script adds the admin_users columns and indexes that came with it.

   Those live in a separate PHP script rather than more SQL because MySQL 8.0 rejects
   `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` as a syntax error — it is a MariaDB
   extension, and this project's local dev database is MariaDB. Found the hard way on
   first production deploy: on a real MySQL 8.0 host it silently truncated the rest of the
   install script, which meant `faqs`/`milestones`/`stories` never got seeded and admin
   login 500'd (it writes to `last_login_ip`, one of the columns that never got added).
   `migrate.php` checks `information_schema` before every write instead, which works
   identically on both engines and is safe to run any number of times.

3. Copy `config/config.example.php` to `config/config.php` if it is missing, and
   check the values there (database credentials, `BASE_URL`, SMTP).
4. Open `http://localhost/gilgalrewardoutreach`.

**Admin panel:** `http://localhost/gilgalrewardoutreach/admin`
Sign in with `admin@gilgalrewardoutreach.org` / `GilgalReward@2026`, then change the
password from **Settings → Change your password**. The login page shows that hint only
until the account has been used once.

---

## Pages

| Page | File | What it does |
|---|---|---|
| Home | `index.php` | Hero, the four programmes, story, impact numbers, recent outreaches, the volunteer map, volunteer faces, upcoming events |
| About Us | `about.php` | Story, mission and vision, the four programmes in full, how we work, team, chapters |
| Outreaches | `outreaches.php` | Every past outreach with photos and figures; filterable by programme |
| Outreach detail | `outreach.php?slug=…` | One outreach: full story, figures, its photographs |
| Gallery | `gallery.php` | All photographs, filterable by category, with a lightbox |
| Events | `events.php` | Featured next event, everything upcoming, then a timeline of past events |
| Volunteer | `volunteer.php` | Chapter map and state list, volunteer photographs, and the registration form |
| Contact | `contact.php` | Phone, Facebook, email, office address, and the contact form |
| Stories | `stories.php` | Approved testimonials, plus a public submission form (held for moderation) |
| Support us | `support.php` | Ways to help, bank transfer details, and a pledge form |
| Questions | `faq.php` | FAQs grouped by category, in an accordion |
| Search | `search.php` | Searches outreaches, events, gallery, programmes, FAQs, stories and chapters |
| Privacy | `privacy.php` | What the site collects, why, and how to have it removed |
| Terms | `terms.php` | Terms of use |
| Not found | `404.php` | Also used when an outreach slug does not resolve; offers search |

Non-page endpoints: `sitemap.xml`, `robots.txt`, `ics.php?id=N` (add an event to a
calendar) and `subscribe.php` (newsletter sign-up and one-click unsubscribe).

### Clean URLs

Links never show `.php`. Every internal link is built by `base_url()` in
`includes/functions.php`, which strips the extension, and the `.htaccess` rewrite maps
the extension-less path back to the real file.

The original `.php` URLs still work — nothing is redirected away, so any existing link
or bookmark stays valid. `/home` is an extra alias for the homepage.

To change the site's URL prefix (for example when moving to a real domain at the
document root), change `BASE_URL` in `config/config.php` and the `ErrorDocument` line at
the top of `.htaccess`. Nothing else hardcodes the path.

---

## Admin panel

| Section | Manages |
|---|---|
| **Inbox** → Volunteer sign-ups | People who registered through the site. Set a status, export to CSV |
| **Inbox** → Contact messages | Messages from the contact form. Mark as read, export to CSV |
| **Content** → Outreaches | Past outreaches: story, date, location, figures, cover photo |
| **Content** → Outreach photos | Extra photographs attached to an outreach |
| **Content** → Gallery | Gallery photographs and their categories |
| **Content** → Volunteer profiles | The volunteer faces shown on the volunteer page |
| **Content** → Events | Upcoming and past events |
| **Content** → Programmes | The four cause areas |
| **Content** → States / chapters | Which states you work in and their volunteer counts |
| **Content** → Team members | Leadership shown on the About page |
| **Content** → Impact numbers | The counting statistics |
| **Content** → Partners | Partner organisations |
| **Site** → Settings | Every piece of site copy, all contact details, social links, and your password |

### How the admin is built

The admin is **three generic pages driven by one definition file**, rather than three
files per table:

- `admin/includes/resources.php` — declares each managed table: its columns in the list
  view, its form fields, and how each field behaves.
- `admin/includes/crud.php` — the engine: listing, searching, validating, image upload
  and saving.
- `admin/manage.php`, `admin/edit.php`, `admin/delete.php` — generic list, form and
  delete, working from those definitions.

**To manage a new table**, add one entry to `admin_resources()` in `resources.php`. No
new pages are needed, and it appears in the sidebar automatically.

> **Watch out:** `admin/includes/header.php` is included partway down a page that already
> has its own variables. Everything it defines is prefixed `sidebar…` for that reason.
> An earlier version used `$resource`, `$key` and `$groups` and silently overwrote the
> calling page's variables — `manage.php` rendered one table's rows through another
> table's columns. Keep new variables in that file prefixed.

### Renaming the organisation

Change **Settings → Site name**. That is the whole job — no page hardcodes the brand.
The folder name, database name and the seeded admin email are technical identifiers and
are deliberately left alone.

---

## Artwork

There are no stock photographs. Everything visual is hand-authored SVG in
`includes/illustrations.php`:

- **Scene illustrations** — the hero, the community circle, the Nigeria map, the contact
  envelope, the "join us" group.
- **Programme icons** and a **UI icon set** (`icon('phone')`, `icon('pin')`, …).
- **Curved section dividers** (`illu_curve()`) and **organic blob masks**
  (`illu_blob_image()`), which is how every photograph is clipped to a blob shape.
- **Generated stand-in artwork.** Any picture slot with no uploaded photo is served by
  `image.php`, which draws a deterministic portrait or scene from a seed. The same seed
  always produces the same picture, so a volunteer's face never changes between page
  loads, and no page ever shows a broken or empty image box.

Upload a real photograph through the admin panel and `media_url()` stops pointing at the
generator for that record. Uploads are validated by reading the image's own bytes (not
the filename), re-encoded through GD, and scaled to 1600px on the longest side.

### Curved photo band

`includes/photo-wave.php` lays a set of photographs along an organic wave that
flows across the page, each cropped to a circle or a soft blob, with a dotted
guide line running through them. It appears on Home, About, Outreaches and
Volunteer, each with a different photo set and wave shape.

```php
<?= photo_wave(wave_photos($pdo, 'volunteers', 9), ['amplitude' => 23, 'cycles' => 1.25]) ?>
```

`wave_photos()` pulls a set from any content table (`gallery`, `volunteers`,
`outreaches`, `team`) and falls back to the generated artwork per photo, so a
band is never empty. Options: `amplitude` (how deep the wave, % of band height),
`cycles` (how many waves across), `phase` (where the curve starts),
`inset` (% kept clear at each side), `shape` (`circle`, `blob` or `mixed`) and
`sizes` (the repeating size factors).

Two things about it are deliberate and easy to break:

- **Positions are emitted as a `<style nonce>` block, not inline `style=""`.**
  The CSP has no `unsafe-inline` for styles, and a nonce covers a `<style>`
  element but never a style attribute. Anything that computes per-element
  positions on this site has to work the same way.
- **Those generated rules sit inside a `min-width: 780px` media query.** Below
  that the band falls back to the plain centred grid in `style.css`, because a
  wave needs horizontal room to read as a wave. The hover captions are hidden
  there too — they do nothing on a touch screen, and an absolutely positioned
  `nowrap` element still counts toward page scroll width even at `opacity: 0`,
  which is how they first pushed horizontal overflow onto phones.

### Design language

| Requirement | Where it lives |
|---|---|
| Organic shape masking (blob / circle crop) | `illu_blob_image()`, `.blob-frame`, `.blob-a…d`, `.circle-crop` |
| Curved colour accent | `illu_curve()`, `.curve`, the sweeping strokes inside each scene |
| Modern corporate illustration layout | `.split` — illustration one side, copy the other |
| Minimal editorial | `.eyebrow`, `.display`, `.lede`, `.pull-quote`; Fraunces over Plus Jakarta Sans |
| Hero banner | `.hero`, `.page-banner` |
| Contemporary presentation | `.cta-band`, `.stat-card`, `.timeline` |
| NGO / education branding | the green–amber–coral–sky palette at the top of `style.css` |

---

## Theme and branding

**Settings → Theme** and **Settings → Branding images**.

- **Brand colour** and **accent colour**. You pick two; everything else is derived in
  `includes/theme.php` — the deep shade, the pale panels, the dark-mode equivalents, the
  glows, the footer, and the text colour that sits on each. Derivation matters here: it
  means an admin cannot choose a pairing that makes text unreadable. `readable_on()` picks
  white or near-black by WCAG relative luminance, not by eyeballing.
- **Default appearance** — follow the visitor's device, or force light or dark.
- **Let visitors switch appearance** — shows or hides the sun/moon button.
- **Logo**, **browser tab icon** and **social sharing image**. Each falls back to the
  built-in drawn mark or a generated illustration when empty, so nothing is ever missing.

The derived palette is emitted as a nonced `<style>` block after `style.css`. At the stock
colours it emits **nothing at all**, so the common case ships no extra CSS and `style.css`
stays the single source of truth.

Colour values are normalised with `hex_norm()` on save, so a typo or a pasted
`javascript:` string can never reach the stylesheet (there is a test for exactly that).

> **One thing the theme does not repaint:** the scene illustrations in
> `includes/illustrations.php` keep their own fixed palette. They are artwork, not
> chrome — they read as printed stickers against any background. The *logo mark* does
> follow the brand colours, and uploading a real logo replaces it entirely.

---

## Dark mode

Follows the visitor's system preference, with a header toggle that overrides it and
remembers the choice in their own browser. A small inline script in `<head>` applies the
saved theme before first paint, so there is no flash of the wrong colours.

**Only tokens change between themes.** No component restates a colour in the dark block,
which is what stops the two themes drifting apart. The trap this design avoids is a pale
panel with dark text where only one of the two flips — so every such pairing has a named
token that moves as a unit:

| Token | Text that sits on |
|---|---|
| `--on-brand` | a solid `--brand` fill |
| `--brand-ink` | `--brand-wash` / `--brand-pale` panels |
| `--amber-ink`, `--coral-ink`, `--sky-ink` | their matching `*-pale` panels |
| `--ok-*`, `--bad-*`, `--info-*` | alert backgrounds |

Never write `color: var(--brand-deep)` on a `--brand-wash` background; use
`var(--brand-ink)`.

The same rule applies to SVG: `illu_curve()` fills with `currentColor` and takes a *tone*
name (`cream`, `paper`, `wash`) rather than a colour, because a hardcoded hex there becomes
a bright band across the page the moment dark mode is on.

---

## Two-factor authentication

Admin accounts can require a TOTP code as well as a password
(**Admin → Two-factor**). The implementation is in `includes/totp.php`, written out
rather than pulled from a library, and verified against all five RFC 6238 test vectors.

With 2FA on, a correct password does **not** create a signed-in session: it sets a
short-lived pending state and redirects to the code step. `$_SESSION['admin']` is only
ever written by `admin_establish_session()`.

**There is deliberately no QR code.** A hand-rolled QR encoder is a few hundred lines of
bit packing, Reed–Solomon and masking that cannot be verified without a decoder to check
it against, and a QR that silently fails to scan is worse than none. Every authenticator
app supports "Enter a setup key", so the setup page shows the secret and the `otpauth://`
URI instead.

If someone loses their authenticator, another admin can clear it from
**Admin → Administrators**, or directly:
`UPDATE admin_users SET totp_enabled = 0 WHERE email = '...';`

---

## Maintenance mode

**Settings → Site behaviour → Maintenance mode** puts a holding page (HTTP 503) in front
of the public site. The admin panel stays reachable so you can switch it back off, and a
signed-in admin still sees the real site for checking work before reopening.

---

## Other operational notes

- **Idle timeout.** An admin session with no activity for `session_timeout_min` (default
  45) is ended, not just hidden.
- **Password reset.** `admin/forgot-password.php` mails a one-hour link. Tokens are stored
  hashed. The response is identical whether or not the address exists, so the form cannot
  be used to enumerate accounts. With SMTP unconfigured the link is shown on screen —
  which only happens on a local install.
- **Backups.** `php cron/backup-database.php` writes a timestamped dump to `backups/` and
  prunes anything older than 14 days. CLI only, so a dump can never be triggered or
  downloaded over HTTP. The password is passed via `MYSQL_PWD`, never on the command line
  where the process list would expose it.
- **Bulk actions.** Admin lists support select-all and bulk publish / hide / mark read /
  archive / delete. Only the operations named in `crud_bulk()` are possible and the column
  each one writes is fixed in code; nothing from the request reaches the SQL but the ids.

---

## Security

- **CSRF tokens** on every form, checked before anything is written.
- **Honeypot + timing gate** on the public forms. A bot that fills the hidden field or
  submits within three seconds gets a success message and nothing is stored.
- **Per-IP rate limiting**: 4 volunteer registrations and 5 contact messages per hour.
- **Admin lockout** after 5 failed sign-ins, for 15 minutes. The failure message is the
  same whether the email exists or not.
- **Content-Security-Policy** with a per-request nonce, plus `X-Frame-Options`,
  `X-Content-Type-Options` and `Referrer-Policy` on every response.
  There is no `unsafe-inline` for styles, so **inline `style=""` attributes will not
  work** — use a utility class (see the end of `style.css`) instead.
- **Two-factor authentication** (TOTP, RFC 6238) available per admin account.
- **Idle session timeout**, configurable in Settings.
- **Password reset** with hashed, single-use, one-hour tokens.
- **Moderation.** Stories submitted through the website are never shown publicly until an
  admin publishes them; no public query reads unapproved rows.
- **No payment processing anywhere.** Giving is by bank transfer using details published
  on the Support page. The site never asks for card or account details, which is stated
  plainly on both the Support page and the privacy notice.
- **Open-redirect guard** on the newsletter endpoint: it only ever redirects back inside
  this site (covered by a test).
- **Audit log** of every admin create, update, delete, export, bulk action, sign-in,
  sign-out and two-factor change, shown on the dashboard.
- Uploads folder is blocked from executing PHP by `.htaccess`.
- CSV exports neutralise spreadsheet formula injection.

---

## Email

Mail is **off until you configure it**, and that is deliberate: with
`MAIL_HOST` left as `smtp.example.com`, `send_notification()` is a safe no-op. Forms
still validate, still save to the database and still confirm to the visitor — nothing
fails silently. Put real SMTP details in `config/config.php` to switch it on.

---

## Tests

```
php tests/smoke-test.php
php tests/smoke-test.php http://localhost/gilgalrewardoutreach
```

**171 checks** driven over real HTTP with a cookie jar: every page, clean URLs and the
legacy `.php` URLs, 404s, the generated artwork (including that a seed is stable),
security headers, all four public forms (valid, invalid, CSRF-less and honeypot
submissions), search, gallery filtering and pagination, the newsletter including the
open-redirect guard, sitemap/robots/calendar endpoints, admin sign-in including a wrong
password, all 17 admin lists, a full create → edit → delete round trip, bulk actions,
moderation (a submitted story stays private until approved), maintenance mode on and off,
CSV export, theme and branding (colour derivation, an invalid colour being rejected, hiding the toggle, a real logo upload / serve / removal, and a non-image upload being refused), and a complete **two-factor round trip** — enable, sign out, confirm the
password alone stops at the code step, confirm a wrong code fails, sign in with a real
computed code, disable again.

It creates records named `Smoke Test …` and deletes them at the end. If a run fails
part-way, look for leftover rows with that name. It clears `rate_limit_hits` at the start
so re-runs inside the hour are not throttled — that is its only direct database access.

`tests/mobile-preview.html` renders pages at a true CSS width inside same-origin
iframes — `?w=390&h=1200&p=volunteer,contact`. Use it to *look* at a narrow
layout, for the same reason as below.

`tests/viewport-check.html` is a horizontal-overflow probe: open it in a browser and it
loads every page in a same-origin iframe at 360/390/768px and reports any page whose
`scrollWidth` exceeds its `clientWidth`.

> Use it rather than trusting a narrow screenshot. Headless Edge renders small
> `--window-size` requests at a wider CSS viewport than requested, so a "390px"
> screenshot looks clipped even when nothing actually overflows.

---

## Still to do before this goes live

- Replace the placeholder contact details in **Settings**: phone numbers, the real
  Facebook page URL, email addresses, office address and WhatsApp number. The current
  values are plausible placeholders, not real.
- Replace the seeded copy and content with the organisation's own: story, mission,
  vision, programmes, outreach write-ups, events, team and impact numbers.
- Upload real photographs. Until then every picture is a generated illustration.
- Change the admin password, and confirm the CAC registration line in the footer note.
- Configure SMTP so form submissions are emailed as well as stored.
