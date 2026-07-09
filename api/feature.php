<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../admin/_inc.php';

try {
    $r = db()->query('SELECT * FROM melikret_feature ORDER BY id ASC LIMIT 1')->fetch();

    if (!$r || !(int)$r['enabled']) {
        echo json_encode(null);
        exit;
    }

    echo json_encode([
        'image_url'    => $r['image_url'],
        'label'        => $r['label'],
        'title'        => $r['title'],
        'link_url'     => $r['link_url'],
        'button_label' => $r['button_label'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
