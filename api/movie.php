<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../admin/_inc.php';

try {
    $rows = db()->query(
        'SELECT id, youtube_id, title FROM melikret_movie ORDER BY sort_order DESC, id DESC'
    )->fetchAll();

    $result = array_map(fn($r) => [
        'id'         => (int)$r['id'],
        'youtube_id' => $r['youtube_id'],
        'title'      => $r['title'],
    ], $rows);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
