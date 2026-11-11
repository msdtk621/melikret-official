<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../admin/_inc.php';

/* TOP売出項目（表示ONのものを表示順の大きい順で配列として返す。0件なら []）
   link_url が「#live-<ライブID>」のときは live_id を返し、フロントはそのライブの詳細ポップアップを開く */
try {
    $rows = db()->query(
        'SELECT id, image_url, label, title, link_url
           FROM melikret_feature
          WHERE enabled = 1
          ORDER BY sort_order DESC, id ASC'
    )->fetchAll();

    $result = array_map(function ($r) {
        $link   = (string)$r['link_url'];
        $liveId = preg_match('/^#live-(\d+)$/', $link, $m) ? (int)$m[1] : null;
        return [
            'id'        => (int)$r['id'],
            'image_url' => $r['image_url'],
            'label'     => $r['label'],
            'title'     => $r['title'],
            'link_url'  => $liveId === null ? $link : '',
            'live_id'   => $liveId,
        ];
    }, $rows);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
