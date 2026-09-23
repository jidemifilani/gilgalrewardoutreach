<?php
/**
 * Site search across outreaches, events, gallery, programmes, FAQs and stories.
 *
 * A plain LIKE search over a handful of small tables. That is the right tool
 * at this size -- full-text indexing would add operational weight for a few
 * hundred rows.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

$query   = trim((string) ($_GET['q'] ?? ''));
$results = [];

if ($query !== '' && mb_strlen($query) >= 2) {
    $like = '%' . $query . '%';

    /**
     * Each source describes how to find matches and how to present them,
     * so adding a searchable table is one array entry.
     */
    $sources = [
        [
            'kind'  => 'Outreach',
            'sql'   => 'SELECT id, title, summary, slug, outreach_date AS d FROM outreaches
                         WHERE title LIKE ? OR summary LIKE ? OR story LIKE ? OR location LIKE ? OR state LIKE ?
                         ORDER BY outreach_date DESC LIMIT 12',
            'binds' => 5,
            'url'   => fn($r) => base_url('outreach.php?slug=' . urlencode($r['slug'])),
            'title' => fn($r) => $r['title'],
            'text'  => fn($r) => $r['summary'],
            'meta'  => fn($r) => fmt_date($r['d'], 'j M Y'),
        ],
        [
            'kind'  => 'Event',
            'sql'   => 'SELECT id, title, summary, event_date AS d FROM events
                         WHERE title LIKE ? OR summary LIKE ? OR description LIKE ? OR location LIKE ?
                         ORDER BY event_date DESC LIMIT 12',
            'binds' => 4,
            'url'   => fn($r) => base_url('events.php#event-' . (int) $r['id']),
            'title' => fn($r) => $r['title'],
            'text'  => fn($r) => $r['summary'],
            'meta'  => fn($r) => fmt_date($r['d'], 'j M Y'),
        ],
        [
            'kind'  => 'Programme',
            'sql'   => 'SELECT id, title, tagline, slug FROM programmes
                         WHERE is_active = 1 AND (title LIKE ? OR tagline LIKE ? OR description LIKE ?)
                         LIMIT 8',
            'binds' => 3,
            'url'   => fn($r) => base_url('about.php#programme-' . $r['slug']),
            'title' => fn($r) => $r['title'],
            'text'  => fn($r) => $r['tagline'],
            'meta'  => fn($r) => '',
        ],
        [
            'kind'  => 'Photograph',
            'sql'   => 'SELECT id, title, caption, category, state FROM gallery_items
                         WHERE title LIKE ? OR caption LIKE ? OR category LIKE ? OR state LIKE ?
                         LIMIT 12',
            'binds' => 4,
            'url'   => fn($r) => base_url('gallery.php?category=' . urlencode($r['category'])),
            'title' => fn($r) => $r['title'],
            'text'  => fn($r) => $r['caption'],
            'meta'  => fn($r) => trim(implode(' · ', array_filter([$r['category'], $r['state']]))),
        ],
        [
            'kind'  => 'Question',
            'sql'   => 'SELECT id, question, answer FROM faqs
                         WHERE is_active = 1 AND (question LIKE ? OR answer LIKE ?) LIMIT 8',
            'binds' => 2,
            'url'   => fn($r) => base_url('faq.php'),
            'title' => fn($r) => $r['question'],
            'text'  => fn($r) => $r['answer'],
            'meta'  => fn($r) => '',
        ],
        [
            'kind'  => 'Story',
            'sql'   => 'SELECT id, author_name, role, story FROM stories
                         WHERE is_approved = 1 AND (author_name LIKE ? OR story LIKE ? OR role LIKE ?)
                         LIMIT 8',
            'binds' => 3,
            'url'   => fn($r) => base_url('stories.php'),
            'title' => fn($r) => $r['author_name'],
            'text'  => fn($r) => $r['story'],
            'meta'  => fn($r) => $r['role'],
        ],
        [
            'kind'  => 'Chapter',
            'sql'   => 'SELECT id, name, hub_city, note FROM states
                         WHERE is_active = 1 AND (name LIKE ? OR hub_city LIKE ? OR note LIKE ?) LIMIT 8',
            'binds' => 3,
            'url'   => fn($r) => base_url('volunteer.php'),
            'title' => fn($r) => $r['name'] . ' chapter',
            'text'  => fn($r) => $r['note'],
            'meta'  => fn($r) => $r['hub_city'],
        ],
    ];

    foreach ($sources as $source) {
        $stmt = $pdo->prepare($source['sql']);
        $stmt->execute(array_fill(0, $source['binds'], $like));

        foreach ($stmt as $row) {
            $results[] = [
                'kind'  => $source['kind'],
                'url'   => ($source['url'])($row),
                'title' => (string) ($source['title'])($row),
                'text'  => (string) ($source['text'])($row),
                'meta'  => (string) ($source['meta'])($row),
            ];
        }
    }
}

/** Wraps the search term in <mark>, on already-escaped output. */
function highlight(string $text, string $term): string
{
    $text = e(excerpt($text, 190));
    if ($term === '') {
        return $text;
    }
    return preg_replace(
        '/(' . preg_quote(e($term), '/') . ')/i',
        '<mark>$1</mark>',
        $text
    ) ?? $text;
}

$pageTitle       = $query !== '' ? 'Search: ' . $query : 'Search';
$pageDescription = 'Search outreaches, events, photographs, programmes and questions.';
$bannerEyebrow   = 'Search';
$bannerTitle     = 'Find something';
$bannerLede      = 'Search across outreaches, events, photographs, programmes, questions and stories.';
$breadcrumbs     = [['label' => 'Search']];

require __DIR__ . '/includes/header.php';
?>

<section class="section section-cream">
  <div class="container">

    <form class="search-form u-mb-lg" method="get" action="<?= e(base_url('search.php')) ?>" role="search">
      <input type="search" name="q" value="<?= e($query) ?>"
             placeholder="Try &ldquo;reading club&rdquo; or &ldquo;Benue&rdquo;"
             aria-label="Search terms" autofocus>
      <button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button>
    </form>

    <?php if ($query === ''): ?>

      <p class="text-muted">Enter a word or two above. Some things people look for:</p>
      <div class="state-pills u-mt">
        <?php foreach (['reading club', 'health camp', 'Osun', 'volunteer', 'skills', 'food relief'] as $suggestion): ?>
          <a class="state-pill" href="<?= e(base_url('search.php?q=' . urlencode($suggestion))) ?>">
            <?= icon('search') ?><?= e($suggestion) ?>
          </a>
        <?php endforeach; ?>
      </div>

    <?php elseif (mb_strlen($query) < 2): ?>

      <p class="gallery-empty">Please enter at least two characters.</p>

    <?php elseif (!$results): ?>

      <div class="split">
        <div>
          <h2 class="h-lg">Nothing matched &ldquo;<?= e($query) ?>&rdquo;</h2>
          <p class="lede">Try a shorter or more general word. Or browse from here:</p>
          <div class="state-pills u-mt-lg">
            <?php foreach (nav_flat() as $path => $label): ?>
              <a class="state-pill" href="<?= e(base_url($path)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="split-media"><?= illu_contact() ?></div>
      </div>

    <?php else: ?>

      <p class="text-muted u-mb">
        <strong><?= count($results) ?></strong> result<?= count($results) === 1 ? '' : 's' ?>
        for &ldquo;<?= e($query) ?>&rdquo;
      </p>

      <ul class="result-list">
        <?php foreach ($results as $result): ?>
          <li class="result-item">
            <span class="result-kind"><?= e($result['kind']) ?><?= $result['meta'] !== '' ? ' &middot; ' . e($result['meta']) : '' ?></span>
            <h3><a href="<?= e($result['url']) ?>"><?= highlight($result['title'], $query) ?></a></h3>
            <p><?= highlight($result['text'], $query) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>

    <?php endif; ?>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
