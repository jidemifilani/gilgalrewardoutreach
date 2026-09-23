<?php
/**
 * Serves one event as an .ics file so visitors can add it to their calendar.
 *
 *   /ics?id=3
 */

require_once __DIR__ . '/includes/db.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$siteName = site_name($pdo);

/** Escapes the characters iCalendar treats specially. */
function ics_escape(string $text): string
{
    $text = str_replace(["\\", ';', ','], ['\\', '\;', '\,'], $text);
    return str_replace(["\r\n", "\n", "\r"], '\n', $text);
}

/** Folds long lines to 75 octets, as RFC 5545 requires. */
function ics_fold(string $line): string
{
    if (strlen($line) <= 73) {
        return $line;
    }
    $out = substr($line, 0, 73);
    $rest = substr($line, 73);
    foreach (str_split($rest, 72) as $chunk) {
        $out .= "\r\n " . $chunk;
    }
    return $out;
}

$start = strtotime($event['event_date'] . ' ' . ($event['start_time'] ?: '09:00:00'));
$end   = $start + (2 * 3600);
if (!empty($event['end_date'])) {
    $end = max($end, strtotime($event['end_date'] . ' 17:00:00'));
}

$location = trim($event['location'] . ($event['state'] ? ', ' . $event['state'] : ''));

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//' . ics_escape($siteName) . '//Events//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:event-' . (int) $event['id'] . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . gmdate('Ymd\THis\Z', $start),
    'DTEND:'   . gmdate('Ymd\THis\Z', $end),
    ics_fold('SUMMARY:' . ics_escape($event['title'])),
    ics_fold('DESCRIPTION:' . ics_escape($event['summary'] ?: $event['description'])),
    ics_fold('LOCATION:' . ics_escape($location)),
    ics_fold('URL:' . full_base_url('events.php') . '#event-' . (int) $event['id']),
    'END:VEVENT',
    'END:VCALENDAR',
];

$filename = slugify($event['title']) . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo implode("\r\n", $lines) . "\r\n";
