<?php
/** Admin dashboard. */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';

$pageTitle = 'Dashboard';

$counts = [
    'volunteers'  => crud_count($pdo, 'volunteers'),
    'newVols'     => crud_count($pdo, 'volunteers', "status = 'new'"),
    'messages'    => crud_count($pdo, 'contact_messages'),
    'unread'      => crud_count($pdo, 'contact_messages', 'is_read = 0'),
    'outreaches'  => crud_count($pdo, 'outreaches'),
    'gallery'     => crud_count($pdo, 'gallery_items'),
    'events'      => crud_count($pdo, 'events'),
    'upcoming'    => crud_count($pdo, 'events', 'event_date >= CURDATE()'),
    'profiles'    => crud_count($pdo, 'volunteer_profiles'),
];

$recentVolunteers = $pdo->query(
    'SELECT * FROM volunteers ORDER BY created_at DESC LIMIT 6'
)->fetchAll();

$recentMessages = $pdo->query(
    'SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 6'
)->fetchAll();

$byState = $pdo->query(
    'SELECT state, COUNT(*) AS total FROM volunteers
      WHERE state <> "" GROUP BY state ORDER BY total DESC LIMIT 8'
)->fetchAll();

$recentActivity = $pdo->query(
    'SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 8'
)->fetchAll();

// Sign-ups and messages over the last 12 weeks, for the dashboard chart.
// Built as a full 12-slot series so quiet weeks show as gaps, not as missing
// bars that make the trend look better than it was.
$series = [];
for ($w = 11; $w >= 0; $w--) {
    $series[date('o-W', strtotime("-{$w} weeks"))] = ['volunteers' => 0, 'messages' => 0, 'label' => date('j M', strtotime("monday this week -{$w} weeks"))];
}

foreach ([['volunteers', 'volunteers'], ['contact_messages', 'messages']] as [$table, $slot]) {
    $stmt = $pdo->query(
        "SELECT DATE_FORMAT(created_at, '%x-%v') AS wk, COUNT(*) AS total
           FROM `{$table}`
          WHERE created_at >= (NOW() - INTERVAL 12 WEEK)
          GROUP BY wk"
    );
    foreach ($stmt as $row) {
        if (isset($series[$row['wk']])) {
            $series[$row['wk']][$slot] = (int) $row['total'];
        }
    }
}

$chartMax = max(1, max(array_map(fn($p) => max($p['volunteers'], $p['messages']), $series)));

require __DIR__ . '/includes/header.php';
?>

<div class="grid grid-4">
  <a class="stat-tile" href="<?= e(base_url('admin/manage.php?r=volunteers')) ?>">
    <span class="stat-tile-value"><?= number_format($counts['volunteers']) ?></span>
    <span class="stat-tile-label">Volunteer sign-ups<?= $counts['newVols'] ? ' · ' . $counts['newVols'] . ' new' : '' ?></span>
  </a>
  <a class="stat-tile" href="<?= e(base_url('admin/manage.php?r=messages')) ?>">
    <span class="stat-tile-value"><?= number_format($counts['messages']) ?></span>
    <span class="stat-tile-label">Messages<?= $counts['unread'] ? ' · ' . $counts['unread'] . ' unread' : '' ?></span>
  </a>
  <a class="stat-tile" href="<?= e(base_url('admin/manage.php?r=outreaches')) ?>">
    <span class="stat-tile-value"><?= number_format($counts['outreaches']) ?></span>
    <span class="stat-tile-label">Outreaches published</span>
  </a>
  <a class="stat-tile" href="<?= e(base_url('admin/manage.php?r=events')) ?>">
    <span class="stat-tile-value"><?= number_format($counts['upcoming']) ?></span>
    <span class="stat-tile-label">Upcoming events</span>
  </a>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Latest volunteer sign-ups</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/manage.php?r=volunteers')) ?>">See all</a>
  </div>

  <?php if (!$recentVolunteers): ?>
    <p class="table-empty">No one has registered through the website yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Name</th><th>State</th><th>Phone</th><th>Status</th><th>Registered</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentVolunteers as $row): ?>
            <tr>
              <td><strong><?= e($row['full_name']) ?></strong><br><span class="form-hint"><?= e($row['email']) ?></span></td>
              <td><?= e($row['state']) ?></td>
              <td><?= e($row['phone']) ?></td>
              <td><span class="pill pill-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
              <td><?= e(fmt_date($row['created_at'], 'j M Y')) ?></td>
              <td class="actions">
                <a class="btn btn-ghost btn-sm"
                   href="<?= e(base_url('admin/edit.php?r=volunteers&id=' . (int) $row['id'])) ?>">Open</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Latest messages</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/manage.php?r=messages')) ?>">See all</a>
  </div>

  <?php if (!$recentMessages): ?>
    <p class="table-empty">No messages have come through the contact form yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>From</th><th>Subject</th><th>Read</th><th>Received</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentMessages as $row): ?>
            <tr>
              <td><strong><?= e($row['name']) ?></strong><br><span class="form-hint"><?= e($row['email']) ?></span></td>
              <td><?= e(excerpt($row['subject'] ?: 'General enquiry', 40)) ?></td>
              <td>
                <?= (int) $row['is_read'] === 1
                      ? '<span class="pill pill-yes">Yes</span>'
                      : '<span class="pill pill-new">New</span>' ?>
              </td>
              <td><?= e(fmt_date($row['created_at'], 'j M Y')) ?></td>
              <td class="actions">
                <a class="btn btn-ghost btn-sm"
                   href="<?= e(base_url('admin/edit.php?r=messages&id=' . (int) $row['id'])) ?>">Read</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="grid grid-3">
  <div class="panel">
    <h2>Last 12 weeks</h2>
    <?php if ($chartMax <= 1 && array_sum(array_column($series, 'volunteers')) === 0
              && array_sum(array_column($series, 'messages')) === 0): ?>
      <p class="chart-empty">Nothing has come in yet. This fills in as people use the site.</p>
    <?php else: ?>
      <?php
        $barWidth = 100 / (count($series) * 2.6);
        $x = 0;
      ?>
      <svg class="chart" viewBox="0 0 100 44" preserveAspectRatio="none"
           role="img" aria-label="Volunteer sign-ups and messages over the last twelve weeks">
        <?php foreach ($series as $point): ?>
          <?php
            $vh = ($point['volunteers'] / $chartMax) * 34;
            $mh = ($point['messages'] / $chartMax) * 34;
            $slot = $x * (100 / count($series));
          ?>
          <rect x="<?= round($slot + 0.6, 2) ?>" y="<?= round(36 - $vh, 2) ?>"
                width="<?= round($barWidth, 2) ?>" height="<?= round(max($vh, 0.4), 2) ?>"
                fill="#0E6E62" rx="0.6"></rect>
          <rect x="<?= round($slot + 0.6 + $barWidth + 0.4, 2) ?>" y="<?= round(36 - $mh, 2) ?>"
                width="<?= round($barWidth, 2) ?>" height="<?= round(max($mh, 0.4), 2) ?>"
                fill="#F0A73E" rx="0.6"></rect>
          <?php $x++; ?>
        <?php endforeach; ?>
        <line x1="0" y1="36" x2="100" y2="36" stroke="#D9E5E2" stroke-width="0.3"></line>
      </svg>

      <p class="form-hint u-mt">
        Green: volunteer sign-ups &middot; Amber: contact messages &middot; peak <?= (int) $chartMax ?> per week
      </p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Sign-ups by state</h2>
    <?php if (!$byState): ?>
      <p class="form-hint">Nothing to chart yet — this fills in as people register.</p>
    <?php else: ?>
      <?php $max = max(array_column($byState, 'total')); ?>
      <table>
        <tbody>
          <?php foreach ($byState as $row): ?>
            <tr>
              <td><?= e($row['state']) ?></td>
              <td>
                <?php $pct = $max > 0 ? round(((int) $row['total'] / $max) * 100) : 0; ?>
                <svg viewBox="0 0 100 10" preserveAspectRatio="none" width="100%" height="10" aria-hidden="true">
                  <rect x="0" y="2" width="100" height="6" rx="3" fill="#EAF6F3"/>
                  <rect x="0" y="2" width="<?= (int) $pct ?>" height="6" rx="3" fill="#0E6E62"/>
                </svg>
              </td>
              <td><strong><?= (int) $row['total'] ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Content at a glance</h2>
    <table>
      <tbody>
        <tr><td>Gallery photos</td><td><strong><?= number_format($counts['gallery']) ?></strong></td></tr>
        <tr><td>Volunteer profiles</td><td><strong><?= number_format($counts['profiles']) ?></strong></td></tr>
        <tr><td>Events (all)</td><td><strong><?= number_format($counts['events']) ?></strong></td></tr>
        <tr><td>Outreaches</td><td><strong><?= number_format($counts['outreaches']) ?></strong></td></tr>
      </tbody>
    </table>
  </div>

  <div class="panel">
    <h2>Recent admin activity</h2>
    <?php if (!$recentActivity): ?>
      <p class="form-hint">Nothing logged yet.</p>
    <?php else: ?>
      <table>
        <tbody>
          <?php foreach ($recentActivity as $row): ?>
            <tr>
              <td>
                <strong><?= e($row['action']) ?></strong><br>
                <span class="form-hint"><?= e(excerpt($row['detail'], 46)) ?></span>
              </td>
              <td class="form-hint"><?= e(fmt_date($row['created_at'], 'j M, ') . date('H:i', strtotime($row['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
