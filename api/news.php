<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../admin/_inc.php';

try {
    $rows = db()->query(
        'SELECT id, news_date, category, body FROM melikret_news ORDER BY news_date DESC, id DESC'
    )->fetchAll();

    $result = array_map(fn($r) => [
        'id'           => (int)$r['id'],
        'date'         => $r['news_date'],
        'date_display' => date('Y.m.d', strtotime($r['news_date'])),
        'category'     => $r['category'],
        'text'         => $r['body'],
    ], $rows);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
