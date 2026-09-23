<?php
/** Generic create/edit form for any resource declared in includes/resources.php. */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';

$key      = (string) ($_GET['r'] ?? $_POST['r'] ?? '');
$resource = admin_resource($pdo, $key);

if (!$resource) {
    http_response_code(404);
    flash('error', 'That section does not exist.');
    redirect(base_url('admin/'));
}

$id       = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$existing = $id ? crud_find($pdo, $resource, $id) : null;

if ($id && !$existing) {
    flash('error', 'That record no longer exists.');
    redirect(base_url('admin/manage.php?r=' . urlencode($key)));
}

if (!$existing && !($resource['can_create'] ?? true)) {
    flash('error', ucfirst($resource['label']) . ' are created by visitors through the website, not here.');
    redirect(base_url('admin/manage.php?r=' . urlencode($key)));
}

$errors = [];
$row    = $existing ?? [];

// Sensible starting values on a brand-new record.
if (!$existing) {
    foreach ($resource['fields'] as $name => $field) {
        $row[$name] = match ($field['type'] ?? 'text') {
            'checkbox' => in_array($name, ['is_active'], true) ? 1 : 0,
            'number'   => 0,
            default    => '',
        };
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        [$values, $errors] = crud_collect($pdo, $resource, $existing);

        if (!$errors) {
            $savedId = crud_save($pdo, $resource, $values, $id ?: null);

            audit(
                $pdo,
                admin_id(),
                $id ? $key . '.update' : $key . '.create',
                $resource['singular'] . ' #' . $savedId . ': '
                    . ($values['title'] ?? $values['full_name'] ?? $values['name'] ?? $values['label'] ?? '')
            );

            flash('success', $resource['singular'] . ($id ? ' updated.' : ' created.'));
            redirect(base_url('admin/manage.php?r=' . urlencode($key)));
        }

        // Re-render the form with what was typed, so nothing is retyped.
        $row = array_merge($row, $values);
    }
}

$pageTitle = ($id ? 'Edit ' : 'Add ') . strtolower($resource['singular']);

require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($errors['form'])): ?>
  <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($errors['form']) ?></span></div>
<?php elseif ($errors): ?>
  <div class="alert alert-error" role="alert">
    <?= icon('sparkle') ?><span>Please check the highlighted fields below.</span>
  </div>
<?php endif; ?>

<?php if (!($resource['can_create'] ?? true)): ?>
  <div class="alert alert-info">
    <?= icon('sparkle') ?>
    <span>This record came from the website. You can update it here — for example to change a
      status or mark a message as read — but it was not created in the admin panel.</span>
  </div>
<?php endif; ?>

<div class="panel">
  <form method="post" action="<?= e(base_url('admin/edit.php')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="r" value="<?= e($key) ?>">
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-grid">
      <?php foreach ($resource['fields'] as $name => $field): ?>
        <?= crud_field_html($name, $field, $row, $errors) ?>
      <?php endforeach; ?>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">
        <?= $id ? 'Save changes' : 'Create ' . e(strtolower($resource['singular'])) ?>
      </button>
      <a class="btn btn-ghost" href="<?= e(base_url('admin/manage.php?r=' . urlencode($key))) ?>">Cancel</a>

      <span class="spacer"></span>
    </div>
  </form>
</div>

<?php if ($id): ?>
  <div class="panel" id="danger">
    <div class="panel-head">
      <h2>Danger zone</h2>
    </div>
    <p class="form-hint u-mb">Deleting removes this record and any image uploaded with it. It cannot be undone.</p>
    <form method="post" action="<?= e(base_url('admin/delete.php')) ?>"
          data-confirm="Delete this permanently? This cannot be undone.">
      <?= csrf_field() ?>
      <input type="hidden" name="r" value="<?= e($key) ?>">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <button class="btn btn-danger" type="submit">Delete this <?= e(strtolower($resource['singular'])) ?></button>
    </form>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
