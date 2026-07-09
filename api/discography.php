<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../admin/_inc.php';

try {
    $rows = db()->query(
        'SELECT id, release_date, title, type, jacket_url, link_url
         FROM melikret_discography ORDER BY sort_order DESC, release_date DESC, id DESC'
    )->fetchAll();

    $result = array_map(fn($r) => [
        'id'           => (int)$r['id'],
        'date_display' => date('Y.m.d', strtotime($r['release_date'])),
        'title'        => $r['title'],
        'type'         => $r['type'],
        'jacket_url'   => $r['jacket_url'],
        'link_url'     => $r['link_url'],
    ], $rows);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
