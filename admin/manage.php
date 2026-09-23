<?php
/** Generic list view for any resource declared in includes/resources.php. */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';

$key      = (string) ($_GET['r'] ?? '');
$resource = admin_resource($pdo, $key);

if (!$resource) {
    http_response_code(404);
    flash('error', 'That section does not exist.');
    redirect(base_url('admin/'));
}

// --- Bulk actions --------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Your session expired. Nothing was changed.');
    } else {
        $action = (string) ($_POST['bulk_action'] ?? '');
        $ids    = (array) ($_POST['ids'] ?? []);

        [$affected, $label] = crud_bulk($pdo, $resource, $action, $ids);

        if ($affected > 0) {
            audit($pdo, admin_id(), $key . '.bulk', $action . ' x' . $affected);
            flash('success', $affected . ' ' . ($affected === 1 ? 'record' : 'records') . ' ' . $label . '.');
        } else {
            flash('error', $label);
        }
    }
    redirect(base_url('admin/manage.php?' . http_build_query(array_filter([
        'r' => $key,
        'q' => (string) ($_POST['q'] ?? ''),
        'page' => (int) ($_POST['page'] ?? 0) ?: null,
    ]))));
}

$search = trim((string) ($_GET['q'] ?? ''));
$page   = paginate(crud_total($pdo, $resource, $search), 25);
$rows   = crud_list($pdo, $resource, $search, $page['perPage'], $page['offset']);
$bulk   = crud_bulk_actions($pdo, $resource);

$pageTitle = $resource['label'];

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <h2><?= e($resource['label']) ?> <span class="form-hint">(<?= number_format($page['total']) ?>)</span></h2>

    <div class="btn-row">
      <?php if (!empty($resource['search'])): ?>
        <form class="search-box" method="get" action="<?= e(base_url('admin/manage.php')) ?>">
          <input type="hidden" name="r" value="<?= e($key) ?>">
          <?= icon('search') ?>
          <input type="search" id="tableSearch" name="q" value="<?= e($search) ?>"
                 placeholder="Search <?= e(strtolower($resource['label'])) ?>" aria-label="Search">
        </form>
      <?php endif; ?>

      <?php if (!empty($resource['export'])): ?>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/export.php?r=' . urlencode($key))) ?>">
          <?= icon('download') ?> Export CSV
        </a>
      <?php endif; ?>

      <?php if ($resource['can_create'] ?? true): ?>
        <a class="btn btn-primary" href="<?= e(base_url('admin/edit.php?r=' . urlencode($key))) ?>">
          Add <?= e(strtolower($resource['singular'])) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <p class="table-empty">
      <?php if ($search !== ''): ?>
        Nothing matched &ldquo;<?= e($search) ?>&rdquo;.
        <a href="<?= e(base_url('admin/manage.php?r=' . urlencode($key))) ?>">Clear the search</a>.
      <?php else: ?>
        Nothing here yet.
        <?php if ($resource['can_create'] ?? true): ?>
          <a href="<?= e(base_url('admin/edit.php?r=' . urlencode($key))) ?>">Add the first one</a>.
        <?php endif; ?>
      <?php endif; ?>
    </p>
  <?php else: ?>

    <form method="post" action="<?= e(base_url('admin/manage.php?r=' . urlencode($key))) ?>" id="bulkForm">
      <?= csrf_field() ?>
      <input type="hidden" name="q" value="<?= e($search) ?>">
      <input type="hidden" name="page" value="<?= (int) $page['page'] ?>">

      <div class="bulk-bar" id="bulkBar" hidden>
        <span class="bulk-count"><strong id="bulkCount">0</strong> selected</span>

        <label class="sr-only" for="bulkAction">Action to apply</label>
        <select id="bulkAction" name="bulk_action" required>
          <option value="">Choose an action</option>
          <?php foreach ($bulk as $value => $label): ?>
            <option value="<?= e($value) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>

        <button class="btn btn-primary btn-sm" type="submit"
                data-bulk-confirm="Apply this to the selected records?">Apply</button>
      </div>

    <div class="table-wrap">
      <table id="listTable">
        <thead>
          <tr>
            <th class="col-check">
              <input type="checkbox" id="checkAll" aria-label="Select all rows on this page">
            </th>
            <?php foreach ($resource['list'] as $column => $heading): ?>
              <th><?= e($heading) ?></th>
            <?php endforeach; ?>
            <th><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="col-check">
                <input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>"
                       class="row-check" aria-label="Select this record">
              </td>
              <?php foreach ($resource['list'] as $column => $heading): ?>
                <td><?= crud_cell_html($resource, $column, $row) ?></td>
              <?php endforeach; ?>
              <td class="actions">
                <a class="btn btn-ghost btn-sm"
                   href="<?= e(base_url('admin/edit.php?r=' . urlencode($key) . '&id=' . (int) $row['id'])) ?>">
                  <?= ($resource['can_create'] ?? true) ? 'Edit' : 'Open' ?>
                </a>
                <a class="btn btn-danger btn-sm"
                   href="<?= e(base_url('admin/edit.php?r=' . urlencode($key) . '&id=' . (int) $row['id'])) ?>#danger">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="table-empty" id="tableEmpty" hidden>Nothing matched that search.</p>
    </form>

    <?= admin_pager($page, 'admin/manage.php', ['r' => $key, 'q' => $search]) ?>

  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
