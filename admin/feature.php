<?php
require '_inc.php';
requireLogin();

$error = '';
$flash = '';

/* 常に id=1 相当（最初の1件）を単一レコードとして扱う */
function getFeatureRow(): ?array {
    $r = db()->query('SELECT * FROM melikret_feature ORDER BY id ASC LIMIT 1')->fetch();
    return $r ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $enabled     = !empty($_POST['enabled']) ? 1 : 0;
    $label       = trim($_POST['label']        ?? '');
    $title       = trim($_POST['title']        ?? '');
    $linkUrl     = trim($_POST['link_url']     ?? '');
    $buttonLabel = trim($_POST['button_label'] ?? '') ?: 'ツアー詳細へ';
    $imageUrlTxt = trim($_POST['image_url']    ?? '');

    if (!$title || !$linkUrl) {
        $error = 'タイトル・リンクURLは必須です';
    } else {
        $existing = getFeatureRow();
        $oldImage = $existing['image_url'] ?? '';

        $uploaded = handleImageUpload($error, 'feature');
        if (!$error) {
            $image = $oldImage;
            if ($uploaded !== null) {
                if (strpos($oldImage, 'uploads/') === 0) deleteImage($oldImage);
                $image = $uploaded;
            } elseif ($imageUrlTxt !== '' && $imageUrlTxt !== $oldImage) {
                if (strpos($oldImage, 'uploads/') === 0) deleteImage($oldImage);
                $image = $imageUrlTxt;
            }
            if (!$image) {
                $error = '画像（URLまたはアップロード）を指定してください';
            } else {
                if ($existing) {
                    db()->prepare(
                        'UPDATE melikret_feature SET enabled=?, image_url=?, label=?, title=?, link_url=?, button_label=? WHERE id=?'
                    )->execute([$enabled, $image, $label, $title, $linkUrl, $buttonLabel, $existing['id']]);
                } else {
                    db()->prepare(
                        'INSERT INTO melikret_feature (enabled, image_url, label, title, link_url, button_label) VALUES (?,?,?,?,?,?)'
                    )->execute([$enabled, $image, $label, $title, $linkUrl, $buttonLabel]);
                }
                header('Location: feature.php?flash=updated'); exit;
            }
        }
    }
}

$flashMap = ['updated' => '保存しました'];
$flash = $flashMap[$_GET['flash'] ?? ''] ?? '';

$v = getFeatureRow() ?? [
    'enabled' => 1, 'image_url' => '', 'label' => 'TOUR',
    'title' => '', 'link_url' => '', 'button_label' => 'ツアー詳細へ',
];

adminHead('TOP売出項目', 'feature');
if ($flash) echo '<div class="alert alert-ok">' . h($flash) . '</div>';
echo '<div class="card"><h2>TOP売出項目（ツアーバナー等）</h2>';
echo '<p class="hint" style="margin-bottom:20px">サイトトップの大きなバナー（現在は「ツアー」告知）を編集できます。「表示する」を外すと、フロントから非表示になります。</p>';
if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';

echo '<form method="post" enctype="multipart/form-data">';
echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';

echo '<div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600"><input type="checkbox" name="enabled" value="1" style="width:auto"' . (!empty($v['enabled']) ? ' checked' : '') . '> 表示する</label></div>';

echo '<div class="form-row">';
echo '<div class="form-group"><label>ラベル（小見出し）</label><input type="text" name="label" value="' . h($v['label'] ?? '') . '" placeholder="例: TOUR"></div>';
echo '<div class="form-group"><label>ボタン文言</label><input type="text" name="button_label" value="' . h($v['button_label'] ?? 'ツアー詳細へ') . '"></div>';
echo '</div>';

echo '<div class="form-group"><label>タイトル <span style="color:#e74c3c">*</span></label><textarea name="title" required placeholder="例: メリクレット&#10;2nd Tour 2026">' . h($v['title'] ?? '') . '</textarea><p class="hint">改行するとバナー上でも改行されます。</p></div>';

echo '<div class="form-group"><label>画像URL</label><input type="url" name="image_url" value="' . h($v['image_url'] ?? '') . '" placeholder="https://..."><p class="hint">下からアップロードした場合はそちらが優先されます。</p></div>';
$img = trim((string)($v['image_url'] ?? ''));
if ($img !== '') {
    $imgSrc = (strpos($img, 'http') === 0) ? $img : '../' . $img;
    echo '<div style="margin-bottom:14px"><img src="' . h($imgSrc) . '" alt="" style="max-width:220px;max-height:300px;border-radius:8px;border:1px solid #d0e6ef"></div>';
}
echo '<div class="form-group"><label>画像アップロード</label><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;padding:8px 0"><p class="hint">JPEG / PNG / GIF / WebP・6MBまで。</p></div>';

echo '<div class="form-group"><label>リンクURL <span style="color:#e74c3c">*</span></label><input type="url" name="link_url" value="' . h($v['link_url'] ?? '') . '" required placeholder="https://melikret.lnk.to/..."></div>';

echo '<button type="submit" class="btn btn-primary">保存する</button>';
echo '</form>';
echo '</div>';
adminFoot();
