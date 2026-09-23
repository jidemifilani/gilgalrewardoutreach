<?php
/**
 * The generic engine behind manage.php / edit.php / delete.php.
 *
 * It reads a resource definition from resources.php and handles listing,
 * searching, rendering the form, validating, uploading images and saving.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/illustrations.php';
require_once __DIR__ . '/resources.php';

const UPLOAD_MAX_BYTES = 4194304;           // 4 MB
const UPLOAD_MAX_EDGE  = 1600;              // px, longest side after resize

// ---------------------------------------------------------------------------
// Reading
// ---------------------------------------------------------------------------

/** Column names actually present on the table, so we never write a stray key. */
function crud_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`') as $row) {
            $cache[$table][] = $row['Field'];
        }
    }
    return $cache[$table];
}

/** The WHERE clause and its bindings for the current search. */
function crud_where(array $resource, string $search): array
{
    if ($search === '' || empty($resource['search'])) {
        return ['', []];
    }

    $clauses = [];
    $params  = [];
    foreach ($resource['search'] as $column) {
        $clauses[] = '`' . $column . '` LIKE ?';
        $params[]  = '%' . $search . '%';
    }

    return [' WHERE (' . implode(' OR ', $clauses) . ')', $params];
}

function crud_total(PDO $pdo, array $resource, string $search = ''): int
{
    [$where, $params] = crud_where($resource, $search);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $resource['table'] . '`' . $where);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function crud_list(PDO $pdo, array $resource, string $search = '', int $limit = 200, int $offset = 0): array
{
    [$where, $params] = crud_where($resource, $search);

    $sql = 'SELECT * FROM `' . $resource['table'] . '`' . $where
         . ' ORDER BY ' . $resource['order']
         . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Applies one action to a set of ids.
 *
 * Only the operations named here are possible, and the column each one writes
 * is fixed in code -- nothing from the request reaches the SQL except the ids,
 * which are cast to integers.
 *
 * @return array{0:int,1:string} rows affected and a message
 */
function crud_bulk(PDO $pdo, array $resource, string $action, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [0, 'Nothing was selected.'];
    }

    $columns     = crud_columns($pdo, $resource['table']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $operations = [
        'read'     => ['is_read',     1, 'marked as read'],
        'unread'   => ['is_read',     0, 'marked as unread'],
        'approve'  => ['is_approved', 1, 'published'],
        'unapprove'=> ['is_approved', 0, 'unpublished'],
        'show'     => ['is_active',   1, 'shown on the site'],
        'hide'     => ['is_active',   0, 'hidden from the site'],
        'contacted'=> ['status', 'contacted', 'marked as contacted'],
        'archive'  => ['status', 'archived',  'archived'],
    ];

    if ($action === 'delete') {
        $affected = 0;
        foreach ($ids as $id) {
            crud_delete($pdo, $resource, $id);       // also removes uploaded files
            $affected++;
        }
        return [$affected, 'deleted'];
    }

    if (!isset($operations[$action])) {
        return [0, 'That action is not available here.'];
    }

    [$column, $value, $label] = $operations[$action];

    if (!in_array($column, $columns, true)) {
        return [0, 'That action does not apply to this section.'];
    }

    $stmt = $pdo->prepare(
        'UPDATE `' . $resource['table'] . '` SET `' . $column . '` = ? WHERE id IN (' . $placeholders . ')'
    );
    $stmt->execute([$value, ...$ids]);

    return [$stmt->rowCount(), $label];
}

/** The bulk actions that make sense for a given table. */
function crud_bulk_actions(PDO $pdo, array $resource): array
{
    $columns = crud_columns($pdo, $resource['table']);
    $actions = [];

    if (in_array('is_read', $columns, true)) {
        $actions['read'] = 'Mark as read';
        $actions['unread'] = 'Mark as unread';
    }
    if (in_array('is_approved', $columns, true)) {
        $actions['approve'] = 'Publish';
        $actions['unapprove'] = 'Unpublish';
    }
    if (in_array('is_active', $columns, true)) {
        $actions['show'] = 'Show on the site';
        $actions['hide'] = 'Hide from the site';
    }
    if (in_array('status', $columns, true)) {
        $actions['contacted'] = 'Mark as contacted';
        if ($resource['key'] === 'volunteers') {
            $actions['archive'] = 'Archive';
        }
    }

    $actions['delete'] = 'Delete permanently';

    return $actions;
}

function crud_find(PDO $pdo, array $resource, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM `' . $resource['table'] . '` WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function crud_count(PDO $pdo, string $table, string $where = ''): int
{
    $sql = 'SELECT COUNT(*) FROM `' . $table . '`' . ($where !== '' ? ' WHERE ' . $where : '');
    return (int) $pdo->query($sql)->fetchColumn();
}

// ---------------------------------------------------------------------------
// Image upload
// ---------------------------------------------------------------------------

/**
 * Validates and stores one uploaded image, returning the stored filename.
 *
 * The type is taken from the image's own bytes (getimagesize), never from the
 * client-supplied name or MIME type, and the file is re-encoded through GD so
 * anything hidden in the original bytes does not survive.
 *
 * @return array{0: ?string, 1: ?string} [filename|null, error|null]
 */
function crud_upload_image(string $field): array
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];                       // nothing uploaded, not an error
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'The upload did not complete. Please try again.'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return [null, 'That image is larger than 4 MB. Please use a smaller file.'];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return [null, 'That file is not a readable image.'];
    }

    $allowed = [
        IMAGETYPE_JPEG => ['jpg',  'imagecreatefromjpeg'],
        IMAGETYPE_PNG  => ['png',  'imagecreatefrompng'],
        IMAGETYPE_GIF  => ['gif',  'imagecreatefromgif'],
        IMAGETYPE_WEBP => ['webp', 'imagecreatefromwebp'],
    ];

    if (!isset($allowed[$info[2]])) {
        return [null, 'Please upload a JPG, PNG, GIF or WebP image.'];
    }

    [$extension, $reader] = $allowed[$info[2]];

    if (!function_exists($reader)) {
        return [null, 'This server cannot process that image format.'];
    }

    $source = @$reader($file['tmp_name']);
    if (!$source) {
        return [null, 'That image could not be read.'];
    }

    // Scale down anything larger than UPLOAD_MAX_EDGE on its longest side.
    $width  = imagesx($source);
    $height = imagesy($source);
    $scale  = min(1, UPLOAD_MAX_EDGE / max($width, $height));

    if ($scale < 1) {
        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized   = imagecreatetruecolor($newWidth, $newHeight);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        imagedestroy($source);
        $source = $resized;
    }

    $directory = dirname(__DIR__, 2) . '/assets/uploads';
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . ($extension === 'gif' ? 'png' : $extension);
    $path = $directory . '/' . $name;

    $saved = match ($extension) {
        'jpg'  => imagejpeg($source, $path, 86),
        'webp' => imagewebp($source, $path, 86),
        default => imagepng($source, $path, 6),   // png and gif both land here
    };

    imagedestroy($source);

    if (!$saved) {
        return [null, 'The image could not be saved. Check folder permissions on assets/uploads.'];
    }

    return [$name, null];
}

function crud_delete_upload(string $filename): void
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return;
    }
    $path = dirname(__DIR__, 2) . '/assets/uploads/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

// ---------------------------------------------------------------------------
// Saving
// ---------------------------------------------------------------------------

/**
 * Builds the row to write from $_POST / $_FILES.
 *
 * @return array{0: array, 1: array} [values, errors]
 */
function crud_collect(PDO $pdo, array $resource, ?array $existing): array
{
    $values = [];
    $errors = [];
    $columns = crud_columns($pdo, $resource['table']);

    foreach ($resource['fields'] as $name => $field) {
        $type = $field['type'] ?? 'text';

        if ($type === 'readonly' || !in_array($name, $columns, true)) {
            continue;
        }

        switch ($type) {

            case 'checkbox':
                $values[$name] = isset($_POST[$name]) ? 1 : 0;
                break;

            case 'number':
                $raw = trim((string) ($_POST[$name] ?? ''));
                $values[$name] = $raw === '' ? null : (int) $raw;
                break;

            case 'image':
                [$uploaded, $uploadError] = crud_upload_image($name);

                if ($uploadError) {
                    $errors[$name] = $uploadError;
                    $values[$name] = $existing[$name] ?? '';
                } elseif ($uploaded !== null) {
                    // Replacing an image removes the file it replaced.
                    if (!empty($existing[$name])) {
                        crud_delete_upload($existing[$name]);
                    }
                    $values[$name] = $uploaded;
                } elseif (!empty($_POST['remove_' . $name])) {
                    if (!empty($existing[$name])) {
                        crud_delete_upload($existing[$name]);
                    }
                    $values[$name] = '';
                } else {
                    $values[$name] = $existing[$name] ?? '';
                }
                break;

            case 'slug':
                $raw = trim((string) ($_POST[$name] ?? ''));
                if ($raw === '') {
                    $source = trim((string) ($_POST[$field['from'] ?? 'title'] ?? ''));
                    $raw = slugify($source !== '' ? $source : 'item');
                }
                $values[$name] = crud_unique_slug($pdo, $resource['table'], slugify($raw), $existing['id'] ?? null);
                break;

            case 'select':
                $raw = trim((string) ($_POST[$name] ?? ''));
                // "allow_other" fields carry a free-text companion input.
                if (!empty($field['allow_other']) && $raw === '__other__') {
                    $raw = trim((string) ($_POST[$name . '_other'] ?? ''));
                }
                if ($raw === '' && in_array($name, ['programme_id', 'outreach_id'], true)) {
                    $values[$name] = null;
                } else {
                    $values[$name] = mb_substr($raw, 0, 255);
                }
                break;

            case 'textarea':
                $values[$name] = mb_substr(trim((string) ($_POST[$name] ?? '')), 0, 20000);
                break;

            case 'date':
            case 'time':
                $raw = trim((string) ($_POST[$name] ?? ''));
                $values[$name] = $raw === '' ? null : $raw;
                break;

            default:
                $values[$name] = mb_substr(trim((string) ($_POST[$name] ?? '')), 0, 500);
        }

        // Required-field and format checks.
        $isEmpty = !isset($values[$name]) || $values[$name] === '' || $values[$name] === null;

        if (!empty($field['required']) && $isEmpty && $type !== 'image') {
            $errors[$name] = ($field['label'] ?? $name) . ' is required.';
        }
        if ($type === 'email' && !$isEmpty && !valid_email((string) $values[$name])) {
            $errors[$name] = 'Please enter a valid email address.';
        }
        if ($type === 'url' && !$isEmpty && !filter_var($values[$name], FILTER_VALIDATE_URL)) {
            $errors[$name] = 'Please enter a full web address, including https://';
        }
    }

    return [$values, $errors];
}

/** Appends -2, -3 ... until the slug is free on that table. */
function crud_unique_slug(PDO $pdo, string $table, string $slug, ?int $ignoreId): string
{
    if (!in_array('slug', crud_columns($pdo, $table), true)) {
        return $slug;
    }

    $base     = $slug !== '' ? $slug : 'item';
    $candidate = $base;
    $suffix    = 2;

    while (true) {
        $sql    = 'SELECT COUNT(*) FROM `' . $table . '` WHERE slug = ?';
        $params = [$candidate];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix++;
    }
}

function crud_save(PDO $pdo, array $resource, array $values, ?int $id): int
{
    $columns = array_keys($values);

    if ($id) {
        $sets = implode(', ', array_map(fn($c) => '`' . $c . '` = ?', $columns));
        $stmt = $pdo->prepare('UPDATE `' . $resource['table'] . '` SET ' . $sets . ' WHERE id = ?');
        $stmt->execute([...array_values($values), $id]);
        return $id;
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $names        = implode(', ', array_map(fn($c) => '`' . $c . '`', $columns));
    $stmt = $pdo->prepare('INSERT INTO `' . $resource['table'] . '` (' . $names . ') VALUES (' . $placeholders . ')');
    $stmt->execute(array_values($values));

    return (int) $pdo->lastInsertId();
}

function crud_delete(PDO $pdo, array $resource, int $id): void
{
    $row = crud_find($pdo, $resource, $id);
    if (!$row) {
        return;
    }

    // Remove any uploaded files this row owned.
    foreach ($resource['fields'] as $name => $field) {
        if (($field['type'] ?? '') === 'image' && !empty($row[$name])) {
            crud_delete_upload($row[$name]);
        }
    }

    $pdo->prepare('DELETE FROM `' . $resource['table'] . '` WHERE id = ?')->execute([$id]);
}

// ---------------------------------------------------------------------------
// Form rendering
// ---------------------------------------------------------------------------

function crud_field_html(string $name, array $field, array $row, array $errors): string
{
    $type    = $field['type'] ?? 'text';
    $label   = $field['label'] ?? $name;
    $value   = $row[$name] ?? '';
    $id      = 'f_' . $name;
    $error   = $errors[$name] ?? null;
    $classes = 'form-group' . (!empty($field['full']) ? ' span-2' : '') . ($error ? ' has-error' : '');
    $req     = !empty($field['required']) ? ' required' : '';

    $html = '<div class="' . $classes . '">';

    if ($type !== 'checkbox') {
        $html .= '<label for="' . e($id) . '">' . e($label)
               . (!empty($field['required']) ? '<span class="req" aria-hidden="true">*</span>' : '')
               . '</label>';
    }

    switch ($type) {

        case 'readonly':
            $html .= '<input type="text" id="' . e($id) . '" value="' . e((string) $value) . '" readonly disabled>';
            break;

        case 'textarea':
            $rows = (int) ($field['rows'] ?? 5);
            $html .= '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="' . $rows . '"' . $req . '>'
                   . e((string) $value) . '</textarea>';
            break;

        case 'checkbox':
            $html .= '<label class="check-pill"><input type="checkbox" id="' . e($id) . '" name="' . e($name) . '"'
                   . ((int) $value === 1 ? ' checked' : '') . '><span>' . e($label) . '</span></label>';
            break;

        case 'select':
            $options    = $field['options'] ?? [];
            $allowOther = !empty($field['allow_other']);
            $known      = array_key_exists((string) $value, $options) || $value === '' || $value === null;

            $html .= '<select id="' . e($id) . '" name="' . e($name) . '"' . $req . ' data-allow-other="'
                   . ($allowOther ? '1' : '0') . '">';

            if (!isset($options['']) && empty($field['required'])) {
                $html .= '<option value="">— none —</option>';
            }
            foreach ($options as $optionValue => $optionLabel) {
                $selected = ((string) $value === (string) $optionValue) ? ' selected' : '';
                $html .= '<option value="' . e((string) $optionValue) . '"' . $selected . '>' . e((string) $optionLabel) . '</option>';
            }
            if ($allowOther) {
                $html .= '<option value="__other__"' . (!$known ? ' selected' : '') . '>Something else…</option>';
            }
            $html .= '</select>';

            if ($allowOther) {
                $html .= '<input type="text" class="other-input u-mt" name="' . e($name) . '_other" '
                       . 'placeholder="Type the value" value="' . e(!$known ? (string) $value : '') . '"'
                       . ($known ? ' hidden' : '') . '>';
            }
            break;

        case 'image':
            if ($value !== '') {
                $html .= '<div class="upload-preview">'
                       . '<img src="' . e(BASE_URL . '/assets/uploads/' . $value) . '" alt="Current image">'
                       . '<label class="check-pill"><input type="checkbox" name="remove_' . e($name) . '">'
                       . '<span>Remove this image</span></label>'
                       . '</div>';
            }
            $html .= '<input type="file" id="' . e($id) . '" name="' . e($name) . '" accept="image/*">';
            break;

        case 'number':
            $html .= '<input type="number" id="' . e($id) . '" name="' . e($name) . '" '
                   . 'value="' . e((string) $value) . '"' . $req . '>';
            break;

        case 'date':
        case 'time':
            $html .= '<input type="' . $type . '" id="' . e($id) . '" name="' . e($name) . '" '
                   . 'value="' . e((string) $value) . '"' . $req . '>';
            break;

        default:
            $inputType = in_array($type, ['email', 'url', 'tel'], true) ? $type : 'text';
            $html .= '<input type="' . $inputType . '" id="' . e($id) . '" name="' . e($name) . '" '
                   . 'value="' . e((string) $value) . '"' . $req . '>';
    }

    if (!empty($field['hint'])) {
        $html .= '<p class="form-hint">' . e($field['hint']) . '</p>';
    }
    if ($error) {
        $html .= '<span class="field-error">' . e($error) . '</span>';
    }

    return $html . '</div>';
}

/** Formats one cell in the list table. */
function crud_cell_html(array $resource, string $column, array $row): string
{
    $value = $row[$column] ?? '';

    // Image columns render a thumbnail, falling back to the generated art.
    if (($resource['fields'][$column]['type'] ?? '') === 'image') {
        $seed = $resource['key'] . '-' . ($row['id'] ?? '') . '-' . ($row['full_name'] ?? $row['name'] ?? $row['title'] ?? '');
        $kind = in_array($resource['key'], ['volunteer_profiles', 'team'], true) ? 'portrait' : 'outreach';
        return '<img class="cell-thumb" src="' . e(media_url((string) $value, $seed, $kind)) . '" alt="">';
    }

    // Foreign keys show their label rather than a bare id.
    if (!empty($resource['labels'][$column])) {
        $map = $resource['labels'][$column];
        return e((string) ($map[$value] ?? '—'));
    }

    if (in_array($column, ['is_active', 'is_featured', 'is_read', 'is_approved', 'totp_enabled'], true)) {
        return (int) $value === 1
            ? '<span class="pill pill-yes">Yes</span>'
            : '<span class="pill pill-no">No</span>';
    }

    if ($column === 'status') {
        return '<span class="pill pill-' . e((string) $value) . '">' . e(ucfirst((string) $value)) . '</span>';
    }

    if (in_array($column, ['created_at', 'event_date', 'outreach_date', 'taken_on'], true)) {
        return e(fmt_date((string) $value, 'j M Y'));
    }

    if ($column === 'year') {
        return e((string) (int) $value);
    }

    if (in_array($column, ['beneficiaries', 'volunteers', 'value'], true)) {
        return e(number_format((int) $value));
    }

    return e(excerpt((string) $value, 70));
}

/**
 * Pager for admin lists.
 *
 * Separate from the public pager_html() because the admin builds its links
 * from explicit query parameters rather than the current $_GET, so a bulk
 * action redirect cannot smuggle anything into the next page's links.
 */
function admin_pager(array $p, string $basePath, array $query = []): string
{
    if ($p['pages'] < 2) {
        return '';
    }

    $link = function (int $page) use ($query, $basePath): string {
        $query['page'] = $page;
        return base_url($basePath . '?' . http_build_query(array_filter($query, fn($v) => $v !== '' && $v !== null)));
    };

    $out = '<nav class="pager" aria-label="Pagination"><ul>';

    if ($p['page'] > 1) {
        $out .= '<li><a class="pager-step" href="' . e($link($p['page'] - 1)) . '" rel="prev">Previous</a></li>';
    }

    $out .= '<li><span class="pager-gap">Page ' . $p['page'] . ' of ' . $p['pages']
          . ' &middot; ' . number_format($p['total']) . ' records</span></li>';

    if ($p['page'] < $p['pages']) {
        $out .= '<li><a class="pager-step" href="' . e($link($p['page'] + 1)) . '" rel="next">Next</a></li>';
    }

    return $out . '</ul></nav>';
}
