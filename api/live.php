<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../admin/_inc.php';

$dows = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
$wjp  = ['日', '月', '火', '水', '木', '金', '土'];

try {
    $rows = db()->query(
        'SELECT * FROM melikret_live ORDER BY event_date ASC, id ASC'
    )->fetchAll();

    $result = array_map(function($r) use ($dows, $wjp) {
        $ts  = strtotime($r['event_date']);
        $dow = $dows[(int)date('w', $ts)];

        $description = trim((string)($r['description'] ?? ''));
        $open_time   = trim((string)($r['open_time'] ?? ''));
        $start_time  = trim((string)($r['start_time'] ?? ''));
        $notes       = trim((string)($r['notes'] ?? ''));
        $image_url   = trim((string)($r['image_url'] ?? ''));

        // チケット（JSON配列 [{info,url}]）。空なら旧 ticket_info/ticket_url から復元
        $tickets = [];
        $raw = trim((string)($r['tickets'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $t) {
                    $ti = trim((string)($t['info'] ?? ''));
                    $tu = trim((string)($t['url'] ?? ''));
                    if ($ti === '' && $tu === '') continue;
                    $tickets[] = ['info' => $ti, 'url' => $tu];
                }
            }
        }
        if (!$tickets) {
            $li = trim((string)($r['ticket_info'] ?? ''));
            $lu = trim((string)($r['ticket_url'] ?? ''));
            if ($li !== '' || $lu !== '') $tickets[] = ['info' => $li, 'url' => $lu];
        }

        $has_detail = ($description !== '' || $open_time !== '' || $start_time !== ''
                     || count($tickets) > 0 || $notes !== '' || $image_url !== '');

        return [
            'id'          => (int)$r['id'],
            'event_date'  => $r['event_date'],
            'day_month'   => date('m.d', $ts),
            'year_dow'    => date('Y', $ts) . ' / ' . $dow,
            'date_jp'     => date('Y年n月j日', $ts) . '(' . $wjp[(int)date('w', $ts)] . ')',
            'event_name'  => $r['event_name'],
            'description' => $description,
            'venue'       => $r['venue'],
            'city'        => $r['city'] ?? '',
            'open_time'   => $open_time,
            'start_time'  => $start_time,
            'tickets'     => $tickets,
            'notes'       => $notes,
            'image_url'   => $image_url,
            'has_detail'  => $has_detail,
        ];
    }, $rows);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
