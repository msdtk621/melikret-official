<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../admin/_inc.php';

try {
    $rows = db()->query(
        'SELECT id, news_date, category, title, body, description, image_url, link_url, link_label
         FROM melikret_news ORDER BY news_date DESC, id DESC'
    )->fetchAll();

    $result = array_map(function ($r) {
        $title       = trim((string)($r['title'] ?? ''));
        $description = trim((string)($r['description'] ?? ''));
        $image_url   = trim((string)($r['image_url'] ?? ''));
        $link_url    = trim((string)($r['link_url'] ?? ''));
        $link_label  = trim((string)($r['link_label'] ?? '')) ?: '詳しく見る →';
        $has_detail  = ($title !== '' || $description !== '' || $image_url !== '' || $link_url !== '');

        return [
            'id'           => (int)$r['id'],
            'date'         => $r['news_date'],
            'date_display' => date('Y.m.d', strtotime($r['news_date'])),
            'category'     => $r['category'],
            'text'         => $r['body'],
            'title'        => $title,
            'description'  => $description,
            'image_url'    => $image_url,
            'link_url'     => $link_url,
            'link_label'   => $link_label,
            'has_detail'   => $has_detail,
        ];
    }, $rows);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
