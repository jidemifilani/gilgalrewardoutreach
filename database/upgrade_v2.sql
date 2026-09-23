-- ---------------------------------------------------------------------------
-- Gilgal Reward Outreach -- upgrade v2
--
-- Additive only: creates new tables and adds new columns/settings. It does not
-- drop or rewrite anything, so it is safe to run against a live database and
-- safe to re-run (every statement is guarded with IF NOT EXISTS).
--
--   mysql -u root gilgalrewardoutreach < database/upgrade_v2.sql
--
-- These same statements are folded into database/schema.sql so a fresh install
-- gets them too.
-- ---------------------------------------------------------------------------

USE gilgalrewardoutreach;

-- --- Newsletter ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(190) NOT NULL UNIQUE,
  name         VARCHAR(160) NOT NULL DEFAULT '',
  source       VARCHAR(80) NOT NULL DEFAULT 'footer',
  token        VARCHAR(64) NOT NULL DEFAULT '',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_news_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Frequently asked questions -------------------------------------------
CREATE TABLE IF NOT EXISTS faqs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  question    VARCHAR(300) NOT NULL,
  answer      TEXT,
  category    VARCHAR(80) NOT NULL DEFAULT 'General',
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_faq_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Stories (volunteer and beneficiary testimonials) ---------------------
-- Submitted publicly, but nothing appears on the site until is_approved = 1.
CREATE TABLE IF NOT EXISTS stories (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  author_name  VARCHAR(160) NOT NULL,
  role         VARCHAR(160) NOT NULL DEFAULT '',
  state        VARCHAR(80) NOT NULL DEFAULT '',
  email        VARCHAR(190) NOT NULL DEFAULT '',
  story        TEXT NOT NULL,
  photo        VARCHAR(255) NOT NULL DEFAULT '',
  is_approved  TINYINT(1) NOT NULL DEFAULT 0,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_stories_approved (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Partnership / giving pledges -----------------------------------------
-- Records an intention to support. No card details and no payment processing
-- happen anywhere in this project.
CREATE TABLE IF NOT EXISTS pledges (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(160) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  phone         VARCHAR(40) NOT NULL DEFAULT '',
  organisation  VARCHAR(190) NOT NULL DEFAULT '',
  support_type  VARCHAR(120) NOT NULL DEFAULT '',
  message       TEXT,
  status        ENUM('new','contacted','active','closed') NOT NULL DEFAULT 'new',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pledges_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Milestones (the About page timeline) ---------------------------------
CREATE TABLE IF NOT EXISTS milestones (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  year        SMALLINT NOT NULL,
  title       VARCHAR(200) NOT NULL,
  description TEXT,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_milestone_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Admin accounts: 2FA and password reset -------------------------------
ALTER TABLE admin_users
  ADD COLUMN IF NOT EXISTS totp_secret     VARCHAR(64) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS totp_enabled    TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS reset_token     VARCHAR(64) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS reset_expires   DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_login_ip   VARCHAR(60) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS is_active       TINYINT(1) NOT NULL DEFAULT 1;

-- --- Helpful indexes on existing tables -----------------------------------
ALTER TABLE gallery_items    ADD INDEX IF NOT EXISTS idx_gallery_state (state);
ALTER TABLE outreaches       ADD INDEX IF NOT EXISTS idx_outreach_state (state);
ALTER TABLE audit_log        ADD INDEX IF NOT EXISTS idx_audit_created (created_at);
ALTER TABLE volunteers       ADD INDEX IF NOT EXISTS idx_volunteers_email (email);

-- --- New settings ----------------------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('maintenance_mode',   '0'),
('maintenance_message','We are carrying out a short update. Please check back in a few minutes.'),
('bank_name',          'Placeholder Bank Plc'),
('bank_account_name',  'Gilgal Reward Outreach'),
('bank_account_number','0000000000'),
('giving_note',        'Every naira is receipted and reported. We publish a funds-use summary every quarter, alongside an independent audit summary at the end of the year.'),
('newsletter_note',    'One short email a month: where we went, what happened, and what is coming up. No appeals for money, and one click to unsubscribe.'),
('session_timeout_min','45'),
('stories_intro',      'The work only means anything in the words of the people who live it. These are volunteers and community members telling it themselves.')
ON DUPLICATE KEY UPDATE setting_value = setting_value;   -- keep existing values


-- ===========================================================================
-- SEED DATA for the new tables (skipped if the table already has rows)
-- ===========================================================================

INSERT INTO faqs (question, answer, category, sort_order)
SELECT * FROM (
  SELECT 'Do I need any qualification to volunteer?' AS q,
         'No. Most of our volunteers are teachers, traders, students, nurses, drivers and artisans who give a few hours a month. We train every volunteer before their first outreach, including our child-safeguarding rules. Willingness is the only requirement.' AS a,
         'Volunteering' AS c, 1 AS s
  UNION ALL SELECT 'How much time does volunteering take?',
         'Most volunteers give one or two Saturdays a month. Some give one day a term. Both matter, and you tell us what you can manage rather than the other way round.',
         'Volunteering', 2
  UNION ALL SELECT 'I am not in one of your states. Can I still register?',
         'Yes, please do. New chapters start exactly this way, with one person putting their name down. We will keep you informed and connect you with others nearby as the numbers grow.',
         'Volunteering', 3
  UNION ALL SELECT 'Does volunteering cost me anything?',
         'No. Volunteering costs you nothing and we never ask volunteers to fund the work they deliver. Transport to an outreach is arranged and covered by the chapter.',
         'Volunteering', 4
  UNION ALL SELECT 'How do you choose which communities to work in?',
         'We work where a local partner already exists -- a head teacher, a nurse, a faith leader -- who knows the community by name and can tell us what is actually needed. We never pick a community from an office.',
         'Our work', 5
  UNION ALL SELECT 'Why do you keep returning to the same places?',
         'Because a single visit makes a photograph and repeated visits make a difference. We return to the same communities year after year until the change holds, which is slower and far less dramatic than it sounds.',
         'Our work', 6
  UNION ALL SELECT 'Do you photograph the people you help?',
         'Only with permission, and never a child without a guardian present and consenting. We photograph the work, not faces receiving aid. Relief is delivered door to door for the same reason.',
         'Our work', 7
  UNION ALL SELECT 'How is the organisation funded?',
         'Individual giving, small local partnerships and in-kind donations of books, equipment and food. We publish what we received and what we spent every quarter, alongside an independent audit summary once a year.',
         'Money', 8
  UNION ALL SELECT 'How do I know my donation was used well?',
         'Every gift is receipted and reported. Our quarterly funds-use summary and the annual independent audit summary are presented openly at the Annual Report evening, including the things that did not work.',
         'Money', 9
  UNION ALL SELECT 'Can my organisation partner with you?',
         'Yes. We work with schools, clinics, cooperatives, faith organisations and businesses. Tell us what you can offer through the contact form and we will come back to you within two working days.',
         'Money', 10
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM faqs LIMIT 1);

INSERT INTO milestones (year, title, description, sort_order)
SELECT * FROM (
  SELECT 2016 AS y, 'Nine friends and a borrowed bus' AS t,
         'Two hundred exercise books for a primary school in Osogbo. No office, no funding, no name -- just a Saturday and a list drawn up with the head teacher.' AS d, 1 AS s
  UNION ALL SELECT 2017, 'The first reading club',
         'A weekly club in a borrowed community hall. Eleven children in week one, forty by the end of the term. The format has barely changed since.', 2
  UNION ALL SELECT 2018, 'Oyo chapter opens',
         'Volunteers in Ibadan asked to run their own drive rather than wait for ours. That became the model for every chapter after it.', 3
  UNION ALL SELECT 2019, 'First free health camp',
         'Eleven volunteer doctors gave a Saturday in Gwagwalada. The most common finding was untreated hypertension in adults under forty.', 4
  UNION ALL SELECT 2020, 'Food relief through the lockdown',
         'Door-to-door parcels for widows and elderly people living alone, when no one could gather. The delivery method we still use started here, out of necessity.', 5
  UNION ALL SELECT 2021, 'Skills tracks begin',
         'Six months of training ending with a starter toolkit rather than only a certificate, because a certificate without tools changes nothing.', 6
  UNION ALL SELECT 2023, 'Ten chapters, one method',
         'Rivers, Benue, Kwara and Ogun joined. Every chapter runs its own drives and reports into Osogbo.', 7
  UNION ALL SELECT 2026, 'Twenty-two reading clubs',
         'Over seven hundred children read aloud on the same morning for World Literacy Day. For many of them it was the first time.', 8
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM milestones LIMIT 1);

INSERT INTO stories (author_name, role, state, story, is_approved, sort_order)
SELECT * FROM (
  SELECT 'Mrs. Folake Adewale' AS n, 'Head Teacher' AS r, 'Osun' AS st,
         'I have taught for twenty-six years and I have seen many organisations come once with cameras. These ones came two weeks before the drive to count what we actually needed, class by class, and then they came back the next term, and the term after that. That is the difference.' AS s,
         1 AS a, 1 AS o
  UNION ALL SELECT 'Ibrahim Yusuf', 'Skills programme graduate', 'Lagos',
         'I finished the phone repair track with a toolkit, not just a certificate. Eight months later I am renting my own small shop and I have taken on an apprentice. Nobody handed me money. They handed me a trade.',
         1, 2
  UNION ALL SELECT 'Blessing Amadi', 'Community Literacy Lead', 'Rivers',
         'I grew up in Bundu. The children here are not behind because they are slow -- they are behind because nobody brought books. Now twenty of them meet me every Saturday morning and I know each of their reading levels by heart.',
         1, 3
  UNION ALL SELECT 'Mama Iorhemba', 'Community member', 'Benue',
         'They knock, they greet, they leave the parcel at the door. No crowd, no queue, no photograph of me holding anything. At my age that quiet is worth as much as the rice.',
         1, 4
  UNION ALL SELECT 'Dr. Ibrahim Musa', 'Volunteer Physician', 'Abuja',
         'I give one Saturday a month. In that time we screen over a thousand people across two days and refer the ones who need it. Most of what we find is not dramatic. It is a thirty-five year old who has never had a reason to check their blood pressure.',
         1, 5
  UNION ALL SELECT 'Tolu Bamidele', 'Reading Club Facilitator', 'Lagos',
         'I was told volunteering would take over my weekends. It takes four mornings a month. In return I have watched children who could not read a sentence in January read a full page aloud by June.',
         1, 6
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM stories LIMIT 1);

-- --- Theme and branding (v3) ----------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('brand_color',        '#0E6E62'),
('accent_color',       '#F0A73E'),
('default_theme',      'system'),
('allow_theme_toggle', '1'),
('logo_image',         ''),
('favicon_image',      ''),
('social_image',       '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;   -- keep existing values
