-- ---------------------------------------------------------------------------
-- Gilgal Reward Outreach — full schema + seed content
-- Import:  mysql -u root < database/schema.sql
-- Safe to re-run: it drops and recreates the database.
-- ---------------------------------------------------------------------------

DROP DATABASE IF EXISTS gilgalrewardoutreach;
CREATE DATABASE gilgalrewardoutreach CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gilgalrewardoutreach;

-- --- Settings --------------------------------------------------------------
CREATE TABLE site_settings (
  setting_key   VARCHAR(80) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Admin accounts --------------------------------------------------------
CREATE TABLE admin_users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  email           VARCHAR(160) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME NULL,
  last_login_at   DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Programmes (the causes the NGO works on) ------------------------------
CREATE TABLE programmes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(80) NOT NULL UNIQUE,
  title       VARCHAR(160) NOT NULL,
  tagline     VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT,
  icon        VARCHAR(40) NOT NULL DEFAULT 'education',
  accent      VARCHAR(20) NOT NULL DEFAULT 'brand',
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Events ----------------------------------------------------------------
CREATE TABLE events (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  slug        VARCHAR(200) NOT NULL UNIQUE,
  summary     VARCHAR(500) NOT NULL DEFAULT '',
  description TEXT,
  event_date  DATE NOT NULL,
  end_date    DATE NULL,
  start_time  TIME NULL,
  location    VARCHAR(200) NOT NULL DEFAULT '',
  state       VARCHAR(80) NOT NULL DEFAULT '',
  image       VARCHAR(255) NOT NULL DEFAULT '',
  cta_label   VARCHAR(80) NOT NULL DEFAULT '',
  cta_url     VARCHAR(255) NOT NULL DEFAULT '',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_events_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Outreaches (past field work) -----------------------------------------
CREATE TABLE outreaches (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(200) NOT NULL,
  slug          VARCHAR(200) NOT NULL UNIQUE,
  summary       VARCHAR(500) NOT NULL DEFAULT '',
  story         TEXT,
  outreach_date DATE NOT NULL,
  location      VARCHAR(200) NOT NULL DEFAULT '',
  state         VARCHAR(80) NOT NULL DEFAULT '',
  programme_id  INT NULL,
  beneficiaries INT NOT NULL DEFAULT 0,
  volunteers    INT NOT NULL DEFAULT 0,
  cover_image   VARCHAR(255) NOT NULL DEFAULT '',
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_outreach_date (outreach_date),
  CONSTRAINT fk_outreach_programme FOREIGN KEY (programme_id)
    REFERENCES programmes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE outreach_photos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  outreach_id  INT NOT NULL,
  image        VARCHAR(255) NOT NULL DEFAULT '',
  caption      VARCHAR(255) NOT NULL DEFAULT '',
  sort_order   INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_photo_outreach FOREIGN KEY (outreach_id)
    REFERENCES outreaches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Gallery ---------------------------------------------------------------
CREATE TABLE gallery_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  caption     VARCHAR(400) NOT NULL DEFAULT '',
  image       VARCHAR(255) NOT NULL DEFAULT '',
  category    VARCHAR(80) NOT NULL DEFAULT 'Outreach',
  state       VARCHAR(80) NOT NULL DEFAULT '',
  taken_on    DATE NULL,
  outreach_id INT NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_gallery_category (category),
  CONSTRAINT fk_gallery_outreach FOREIGN KEY (outreach_id)
    REFERENCES outreaches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Volunteer coverage states --------------------------------------------
CREATE TABLE states (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80) NOT NULL UNIQUE,
  code        VARCHAR(8) NOT NULL DEFAULT '',
  hub_city    VARCHAR(120) NOT NULL DEFAULT '',
  note        VARCHAR(255) NOT NULL DEFAULT '',
  volunteers  INT NOT NULL DEFAULT 0,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Featured volunteer faces (shown on the Volunteer page) ----------------
CREATE TABLE volunteer_profiles (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(160) NOT NULL,
  role          VARCHAR(160) NOT NULL DEFAULT 'Volunteer',
  state         VARCHAR(80) NOT NULL DEFAULT '',
  outreach      VARCHAR(200) NOT NULL DEFAULT '',
  quote         VARCHAR(500) NOT NULL DEFAULT '',
  photo         VARCHAR(255) NOT NULL DEFAULT '',
  serving_since SMALLINT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Volunteer sign-ups (public form submissions) -------------------------
CREATE TABLE volunteers (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  full_name    VARCHAR(160) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  phone        VARCHAR(40) NOT NULL DEFAULT '',
  state        VARCHAR(80) NOT NULL DEFAULT '',
  city         VARCHAR(120) NOT NULL DEFAULT '',
  occupation   VARCHAR(160) NOT NULL DEFAULT '',
  interests    VARCHAR(400) NOT NULL DEFAULT '',
  availability VARCHAR(80) NOT NULL DEFAULT '',
  skills       TEXT,
  motivation   TEXT,
  heard_from   VARCHAR(120) NOT NULL DEFAULT '',
  status       ENUM('new','contacted','active','archived') NOT NULL DEFAULT 'new',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_volunteers_status (status),
  INDEX idx_volunteers_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Contact messages ------------------------------------------------------
CREATE TABLE contact_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(160) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  phone      VARCHAR(40) NOT NULL DEFAULT '',
  subject    VARCHAR(200) NOT NULL DEFAULT '',
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_messages_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Team / leadership -----------------------------------------------------
CREATE TABLE team_members (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(160) NOT NULL,
  role       VARCHAR(160) NOT NULL DEFAULT '',
  bio        TEXT,
  photo      VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Impact numbers --------------------------------------------------------
CREATE TABLE impact_stats (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  label      VARCHAR(120) NOT NULL,
  value      INT NOT NULL DEFAULT 0,
  suffix     VARCHAR(10) NOT NULL DEFAULT '',
  icon       VARCHAR(40) NOT NULL DEFAULT 'heart',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Partners --------------------------------------------------------------
CREATE TABLE partners (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(160) NOT NULL,
  url        VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Admin audit log -------------------------------------------------------
CREATE TABLE audit_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT NULL,
  action     VARCHAR(80) NOT NULL,
  detail     VARCHAR(400) NOT NULL DEFAULT '',
  ip         VARCHAR(60) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Per-IP rate limiting for public forms --------------------------------
CREATE TABLE rate_limit_hits (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  bucket     VARCHAR(60) NOT NULL,
  ip         VARCHAR(60) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rl (bucket, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ===========================================================================
-- SEED DATA
-- ===========================================================================

-- Admin login: admin@gilgalrewardoutreach.org / GilgalReward@2026  (change after first login)
INSERT INTO admin_users (name, email, password_hash) VALUES
('Site Administrator', 'admin@gilgalrewardoutreach.org', '$2y$10$rMHbWhTZ5D2hp4X.2Qzpd.bPA2OqtQvpFAdGbKqoirR2YGwOSrxW.');

INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name',        'Gilgal Reward Outreach'),
('site_short_name',  'Gilgal Reward'),
('tagline',          'Lifting communities, one outreach at a time'),
('hero_eyebrow',     'A Nigerian non-profit'),
('hero_heading',     'We go where the need is, and we keep going back'),
('hero_subheading',  'We work across Nigeria on child literacy, community health, youth skills and food relief — carried by volunteers who show up, season after season.'),
('mission',          'To open doors of learning, health and dignity for underserved communities across Nigeria, through consistent, locally-led outreach.'),
('vision',           'A Nigeria where a child''s postcode never decides the size of their future.'),
('about_story',      'Gilgal Reward Outreach began in 2016 with nine friends, a borrowed bus and 200 exercise books for a primary school in Osogbo. We had no office and no funding — only a conviction that showing up consistently beats showing up impressively.\n\nTen years on, that conviction still runs the organisation. We do not parachute into communities for a photograph. We partner with local teachers, nurses, artisans and faith leaders who already know their neighbours by name, and we return to the same communities year after year until the change holds.\n\nToday our volunteer network reaches seven states and the Federal Capital Territory, and every programme we run is delivered by people who live in the community they serve.'),
('founded_year',     '2016'),
('contact_phone',    '+234 803 000 0000'),
('contact_phone_alt','+234 701 000 0000'),
('contact_email',    'hello@gilgalrewardoutreach.org'),
('volunteer_email',  'volunteer@gilgalrewardoutreach.org'),
('facebook_url',     'https://facebook.com/gilgalrewardoutreach'),
('instagram_url',    'https://instagram.com/gilgalrewardoutreach'),
('twitter_url',      'https://x.com/gilgalrewardng'),
('whatsapp_number',  '2348030000000'),
('address_line',     '14 Adeyemo Close, Off Ring Road'),
('address_city',     'Osogbo, Osun State, Nigeria'),
('office_hours',     'Monday - Friday, 9:00am - 5:00pm'),
('volunteer_intro',  'From Osogbo to Port Harcourt, ordinary people give a few hours a month and change what a whole community can expect from the next school term. Teachers, nurses, traders, students, drivers — no particular background required.'),
('volunteer_promise','You do not need money, a degree or free weekends every week. You need to be willing. We will train you, place you with a team near you, and tell you exactly what a day of service looks like before you commit.'),
('cta_heading',      'Hope rises faster when more hands lift it'),
('cta_text',         'Join a team near you, partner with a programme, or simply send us a message. Every conversation starts somewhere.'),
('footer_note',      'Gilgal Reward Outreach is a registered non-governmental organisation in Nigeria. CAC registration number pending update.'),
('map_embed',        '');

INSERT INTO programmes (slug, title, tagline, description, icon, accent, sort_order) VALUES
('education', 'Education & Child Literacy',
 'Books, uniforms and reading clubs for children who would otherwise drop out',
 'We keep children in school. That means termly school-supply drives, scholarship cover for fees that families cannot meet, and weekly reading clubs run by volunteers in community halls and church buildings. Our reading clubs currently run in 22 communities, and children who attend for two terms read on average three grade levels higher than when they started.',
 'education', 'brand', 1),
('health', 'Community Health Outreach',
 'Free checks, maternal care and health education where the nearest clinic is hours away',
 'Working with licensed nurses and doctors who volunteer their Saturdays, we run free health screening camps — blood pressure, blood sugar, malaria testing, antenatal checks and referrals. Every camp is paired with a health-education session in the local language, because a diagnosis without understanding rarely changes anything.',
 'health', 'coral', 2),
('skills', 'Youth Skills & Empowerment',
 'Tailoring, catering, digital and trade skills that turn into real income',
 'Our six-month skills tracks take young people from no income to a working trade. Tailoring, catering, phone repair, digital freelancing and barbering, each ending with a starter toolkit rather than just a certificate. We follow up for a year after graduation, because the first twelve months decide whether a skill becomes a livelihood.',
 'skills', 'sky', 3),
('relief', 'Food Relief & Welfare',
 'Dignified food support for widows, elders and displaced families',
 'Monthly food parcels for widows and elderly people living alone, emergency relief for displaced families, and a school feeding partnership that keeps children fed through the school day. We deliver quietly, in household quantities, without crowds or cameras — dignity is part of the parcel.',
 'relief', 'sun', 4);

INSERT INTO states (name, code, hub_city, note, volunteers, sort_order) VALUES
('Osun',    'OS', 'Osogbo',      'Our founding state and national coordination office.', 142, 1),
('Oyo',     'OY', 'Ibadan',      'Reading clubs across Ibadan North and Ogbomosho.',      118, 2),
('Lagos',   'LA', 'Ikeja',       'Our largest skills-training cohort and corporate partners.', 205, 3),
('Abuja',   'FC', 'Gwagwalada',  'Health camps serving satellite towns around the FCT.',   96, 4),
('Rivers',  'RV', 'Port Harcourt','Waterfront community literacy and health outreach.',    87, 5),
('Benue',   'BN', 'Makurdi',     'Food relief and support for displaced farming families.', 74, 6),
('Kwara',   'KW', 'Ilorin',      'Growing chapter, school-supply drives each term.',        41, 7),
('Ogun',    'OG', 'Abeokuta',    'Weekend reading clubs and artisan mentoring.',            38, 8);

INSERT INTO impact_stats (label, value, suffix, icon, sort_order) VALUES
('Children supported in school', 12400, '+', 'education', 1),
('Free health checks delivered',  8600, '+', 'health',    2),
('Young people trained in a trade', 1950, '+', 'skills',  3),
('Active volunteers nationwide',   801, '',  'heart',     4);

INSERT INTO team_members (name, role, bio, sort_order) VALUES
('Adebimpe Olaleye', 'Founder & Executive Director', 'A former secondary school teacher who started Gilgal Reward with eight colleagues in 2016. She leads programme strategy and still teaches at the Osogbo reading club most Saturdays.', 1),
('Emeka Nwachukwu',  'Director of Programmes', 'Public health specialist with twelve years in community medicine. He designs and audits every health camp, and trains our volunteer nurse coordinators.', 2),
('Fatima Bello',     'Head of Volunteer Network', 'Manages volunteer onboarding, placement and training across all chapters. If you register to volunteer, Fatima''s team is who you hear from.', 3),
('Tunde Akinbola',   'Finance & Accountability Lead', 'Chartered accountant responsible for donor reporting and the annual independent audit. He publishes our funds-use summary every quarter.', 4);

INSERT INTO partners (name, url, sort_order) VALUES
('Osun State Ministry of Education', '', 1),
('Lighthouse Medical Volunteers',    '', 2),
('Ibadan Artisans Guild',            '', 3),
('Harvest Community Foundation',     '', 4),
('Riverside Youth Network',          '', 5),
('Makurdi Farmers Cooperative',      '', 6);

INSERT INTO outreaches (title, slug, summary, story, outreach_date, location, state, programme_id, beneficiaries, volunteers, sort_order) VALUES
('Back-to-School Drive, Osogbo', 'back-to-school-osogbo',
 'Exercise books, uniforms and school bags for 640 pupils across four primary schools ahead of first term.',
 'We started the year where the organisation itself started. Four primary schools in Osogbo, 640 pupils, and a simple list drawn up with the head teachers themselves rather than guessed at from an office: exercise books, one uniform set, a bag, and mathematical sets for the older classes.\n\nSixty-three volunteers spent the Saturday sorting, sizing and distributing. The part that mattered most was not the handover — it was the two weeks before, when volunteers visited each school to count real needs class by class.',
 '2026-01-17', 'Ataoja Grammar School and 3 primary schools', 'Osun', 1, 640, 63, 1),
('Free Health Camp, Gwagwalada', 'health-camp-gwagwalada',
 'Two-day screening camp: blood pressure, blood sugar, malaria testing and antenatal checks for 1,180 residents.',
 'Gwagwalada sits close enough to Abuja to be forgotten by it. Over two days, eleven volunteer doctors and twenty-four nurses screened 1,180 people, referred 87 for follow-up care, and ran health-education sessions in Hausa and English between consultations.\n\nThe most common finding was untreated hypertension in adults under forty — a quiet, invisible risk that nobody had reason to check for. Every person flagged left with a referral letter and a three-month follow-up date.',
 '2026-03-07', 'Gwagwalada Community Town Hall', 'Abuja', 2, 1180, 35, 2),
('Skills Graduation, Ikeja', 'skills-graduation-ikeja',
 '92 young people completed six-month tracks in tailoring, catering, phone repair and digital freelancing.',
 'Ninety-two graduates, and ninety-two starter toolkits: industrial sewing machines, catering equipment, repair kits and refurbished laptops. A certificate that is not backed by tools is a certificate that changes nothing, so every track ends with the equipment to start work the following Monday.\n\nWe will follow this cohort for twelve months. Of the previous cohort, 71% were earning from their trade at the one-year mark.',
 '2026-05-23', 'Ikeja Skills Centre', 'Lagos', 3, 92, 28, 3),
('Waterfront Reading Club Launch, Port Harcourt', 'reading-club-port-harcourt',
 'A new weekly reading club for 180 children in three waterfront communities.',
 'Three waterfront communities, one shared problem: children attending school but not reading at grade level, and no library within reach. We launched weekly reading clubs in all three, stocked with 900 age-graded books and run by fourteen volunteers who live in those communities.\n\nClubs meet every Saturday morning. Attendance after four months is holding at 84%, which is the number we actually watch.',
 '2026-06-13', 'Bundu, Nembe and Marine Base communities', 'Rivers', 1, 180, 21, 4),
('Widows Food Parcel Distribution, Makurdi', 'food-parcels-makurdi',
 'Monthly food parcels delivered to 310 widows and elderly residents, plus emergency support for displaced families.',
 'Three hundred and ten households, delivered door to door over a weekend rather than distributed from a stage. Rice, beans, garri, oil, tomatoes and soap, in household quantities that last a month.\n\nWe deliver this way on purpose. Nobody should queue in public for food, and nobody should be photographed receiving it. Volunteers carry the parcels to the door, greet the family, and leave.',
 '2026-07-18', 'Wadata and North Bank districts', 'Benue', 4, 310, 32, 5),
('Term Supplies & Reading Club, Ogbomosho', 'term-supplies-ogbomosho',
 'School supplies for 420 pupils and a new reading club serving two rural communities.',
 'Two rural communities outside Ogbomosho, 420 pupils, and a reading club built into the existing community hall so it costs nothing to keep running. Supplies covered the full term: books, pens, mathematical sets and uniforms for the forty children the teachers identified as most at risk of dropping out.\n\nA local teacher now runs the club with two of our volunteers. That handover is the goal for every club we start.',
 '2026-09-05', 'Ogbomosho North community schools', 'Oyo', 1, 420, 26, 6);

INSERT INTO outreach_photos (outreach_id, caption, sort_order) VALUES
(1, 'Volunteers sorting exercise books the morning of the drive', 1),
(1, 'Pupils of Ataoja Grammar School receiving their bags', 2),
(1, 'Head teacher and volunteers confirming the class-by-class list', 3),
(2, 'Blood pressure screening station on day one', 1),
(2, 'Antenatal checks in the side hall', 2),
(2, 'Health education session between consultations', 3),
(3, 'Tailoring graduates with their sewing machines', 1),
(3, 'Digital freelancing track receiving refurbished laptops', 2),
(3, 'The full graduating cohort of 92', 3),
(4, 'First Saturday of the Bundu reading club', 1),
(4, 'Unpacking the 900-book starter library', 2),
(5, 'Volunteers loading parcels before door-to-door delivery', 1),
(5, 'A volunteer team covering the North Bank route', 2),
(6, 'Supplies laid out at the community hall', 1),
(6, 'The new Ogbomosho reading club, week one', 2);

INSERT INTO gallery_items (title, caption, category, state, taken_on, outreach_id, sort_order) VALUES
('First term, first books',        'A pupil opens a new exercise book at the Osogbo drive.',            'Education', 'Osun',   '2026-01-17', 1, 1),
('Sorting through the morning',    'Volunteers sorting supplies before distribution begins.',           'Volunteers','Osun',   '2026-01-17', 1, 2),
('Checks that nobody had run',     'Blood pressure screening at the Gwagwalada camp.',                  'Health',    'Abuja',  '2026-03-07', 2, 3),
('In two languages',               'A health education session delivered in Hausa and English.',        'Health',    'Abuja',  '2026-03-07', 2, 4),
('Tools, not just certificates',   'A tailoring graduate with her industrial sewing machine.',          'Skills',   'Lagos',   '2026-05-23', 3, 5),
('Ninety-two of them',             'The Ikeja graduating cohort on the day.',                           'Skills',   'Lagos',   '2026-05-23', 3, 6),
('Saturday morning, Bundu',        'Week one of the waterfront reading club.',                          'Education','Rivers',  '2026-06-13', 4, 7),
('Nine hundred books',             'Unpacking the starter library for three communities.',              'Education','Rivers',  '2026-06-13', 4, 8),
('Door to door',                   'Parcels delivered to the doorstep, never from a stage.',            'Relief',   'Benue',   '2026-07-18', 5, 9),
('The North Bank route',           'A volunteer team covering one of the delivery routes.',             'Volunteers','Benue',  '2026-07-18', 5, 10),
('Laid out and counted',           'Term supplies at the Ogbomosho community hall.',                    'Relief',   'Oyo',     '2026-09-05', 6, 11),
('Week one',                       'The first session of the Ogbomosho reading club.',                  'Education','Oyo',     '2026-09-05', 6, 12),
('Before the doors open',          'Volunteer briefing ahead of a health camp.',                        'Volunteers','Abuja',  '2026-03-07', 2, 13),
('Counting the real need',         'Class-by-class needs assessment two weeks before a drive.',         'Education','Osun',    '2026-01-10', 1, 14),
('The follow-up list',             'Referral letters prepared for 87 residents.',                       'Health',   'Abuja',   '2026-03-08', 2, 15),
('Handing the club over',          'A local teacher takes over the Ogbomosho reading club.',            'Volunteers','Oyo',    '2026-09-05', 6, 16);

INSERT INTO volunteer_profiles (full_name, role, state, outreach, quote, serving_since, sort_order) VALUES
('Yewande Adeniyi', 'Reading Club Facilitator', 'Osun',  'Back-to-School Drive, Osogbo',
 'I give four Saturdays a month. Four. And I have watched children who could not read a sentence in January read a full page by June.', 2018, 1),
('Dr. Ibrahim Musa', 'Volunteer Physician', 'Abuja', 'Free Health Camp, Gwagwalada',
 'Most of what we find is not dramatic. It is high blood pressure in a thirty-five year old who has never had a reason to check.', 2019, 2),
('Chidinma Okoro', 'Skills Track Mentor', 'Lagos', 'Skills Graduation, Ikeja',
 'A certificate without tools is a piece of paper. I stay with my mentees for the first year, because that year decides everything.', 2020, 3),
('Sunday Terver', 'Relief Coordinator', 'Benue', 'Widows Food Parcels, Makurdi',
 'We knock, we greet, we leave. No crowd, no photographs of anybody receiving. That is the whole method.', 2017, 4),
('Blessing Amadi', 'Community Literacy Lead', 'Rivers', 'Reading Club Launch, Port Harcourt',
 'I grew up in Bundu. The children here are not behind because they are slow. They are behind because nobody brought books.', 2021, 5),
('Kunle Ogundipe', 'Logistics Volunteer', 'Oyo', 'Term Supplies, Ogbomosho',
 'I drive the bus and I count the boxes. Unglamorous work, but nothing reaches anybody without it.', 2018, 6),
('Hauwa Suleiman', 'Volunteer Nurse Coordinator', 'Abuja', 'Free Health Camp, Gwagwalada',
 'I train the nurse volunteers before every camp. We rehearse, because a camp with no system becomes a crowd.', 2019, 7),
('Tolu Bamidele', 'Reading Club Facilitator', 'Lagos', 'Lagos Reading Club Network',
 'My club meets in a church hall on Saturdays. Twenty-two children. I know every one of their names and their reading level.', 2022, 8),
('Grace Iorhemba', 'Welfare Visitor', 'Benue', 'Widows Food Parcels, Makurdi',
 'The parcel is the reason for the visit, not the point of it. Most of these women simply want somebody to sit down.', 2020, 9),
('Segun Adeleke', 'Skills Instructor', 'Osun', 'Osogbo Artisan Track',
 'I teach phone repair. Six months, then a toolkit. Three of my old students now train others.', 2021, 10),
('Amara Eze', 'Volunteer Photographer', 'Rivers', 'Reading Club Launch, Port Harcourt',
 'I photograph the work, never a face without permission. Consent first, always — especially with children.', 2022, 11),
('Musa Danjuma', 'Chapter Coordinator', 'Kwara', 'Ilorin Term Supplies Drive',
 'Ilorin is our newest chapter. Forty-one volunteers and growing, and we run our own drive every term now.', 2023, 12);

INSERT INTO events (title, slug, summary, description, event_date, start_time, location, state, cta_label, is_featured) VALUES
('Volunteer Orientation: New Intake', 'volunteer-orientation-october',
 'A two-hour session for everyone who registered this quarter. Meet your chapter coordinator and pick your programme.',
 'If you have registered to volunteer with us, this is your starting point. We cover what each programme actually involves week to week, our child-safeguarding rules, what we expect of you and what you can expect of us. You will leave matched to a team near you.\n\nCome as you are. There is nothing to bring and nothing to pay.',
 '2026-10-11', '10:00:00', 'Gilgal Reward Office, Osogbo (and online)', 'Osun', 'Register to attend', 1),
('Health Camp: Ibadan North', 'health-camp-ibadan-north',
 'Free screening camp: blood pressure, blood sugar, malaria testing and antenatal checks. Open to all residents.',
 'A one-day free health camp open to every resident of Ibadan North, no registration and no fee. Services include blood pressure and blood sugar checks, malaria testing, antenatal checks, general consultation and referrals for anyone who needs follow-up care.\n\nVolunteer doctors and nurses are welcome to join — please register at least a week ahead so we can confirm licensing.',
 '2026-10-25', '08:30:00', 'Agodi Community Centre, Ibadan', 'Oyo', 'Volunteer for this camp', 1),
('Term Supplies Drive: Ilorin Chapter', 'term-supplies-ilorin',
 'Our Kwara chapter runs its first full-scale supplies drive for three community primary schools.',
 'The Ilorin chapter has grown to forty-one volunteers, enough to run a full drive independently. Three community primary schools, an expected 380 pupils, and a needs list built with the head teachers over the preceding fortnight.\n\nDonations of exercise books, pens and mathematical sets are welcome at the Ilorin drop-off point through the month.',
 '2026-11-14', '09:00:00', 'Ilorin South community schools', 'Kwara', 'Support this drive', 0),
('Skills Track Intake: January Cohort', 'skills-intake-january',
 'Applications open for tailoring, catering, phone repair, digital freelancing and barbering tracks.',
 'Applications are open for the January cohort across all five skills tracks. The programme runs six months, costs the participant nothing, and ends with a starter toolkit rather than only a certificate.\n\nOpen to young people aged 17-30. Priority goes to applicants who are out of school and out of work. Apply through any chapter coordinator or the contact form on this site.',
 '2026-12-05', '10:00:00', 'Ikeja Skills Centre, Lagos', 'Lagos', 'Ask about applying', 0),
('Christmas Food Relief Weekend', 'christmas-food-relief',
 'Our largest relief weekend of the year, covering widows and displaced families across four states.',
 'Every December we run a combined relief weekend across Osun, Oyo, Benue and Rivers, reaching roughly 1,200 households. Volunteers are needed for packing on the Friday and delivery on the Saturday.\n\nAs always, delivery is door to door. We do not run crowd distributions.',
 '2026-12-19', '07:00:00', 'Four states, simultaneous', '', 'Join a packing team', 1),
('Annual Report & Partners Evening', 'annual-report-evening',
 'We publish the year''s numbers, the independent audit summary and what we got wrong.',
 'An open evening for volunteers, partners and anyone who has supported the work. We present the full year: children reached, camps run, graduates placed, money received and money spent, alongside the independent audit summary.\n\nWe also present what did not work. That section is not optional and it is not short.',
 '2027-01-30', '17:00:00', 'Osogbo, and streamed online', 'Osun', 'Reserve a seat', 0),
('Reading Club Facilitator Training', 'facilitator-training-past',
 'Training weekend for new reading club facilitators across the South West chapters.',
 'A two-day training weekend covering phonics fundamentals, running a mixed-ability group, tracking reading levels and safeguarding. Completed by 48 new facilitators.',
 '2026-08-15', '09:00:00', 'Osogbo Training Hall', 'Osun', '', 0),
('World Literacy Day Read-Aloud', 'literacy-day-read-aloud',
 'A simultaneous read-aloud across every Gilgal Reward reading club in the country.',
 'On World Literacy Day, all 22 of our reading clubs held a simultaneous read-aloud session. Over 700 children took part, each reading a page out loud to their group — for many of them, the first time they had done so.',
 '2026-09-08', '10:00:00', 'All reading clubs, nationwide', '', '', 0);


-- ===========================================================================
-- v2 additions
--
-- Kept as a clearly marked block, and mirrored in database/upgrade_v2.sql so
-- an existing installation can take the same changes without being wiped.
-- ===========================================================================


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
