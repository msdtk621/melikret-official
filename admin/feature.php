<?php
require '_inc.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$flash  = '';
$error  = '';

/* ── POST 処理 ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act         = $_POST['_action'] ?? '';
    $enabled     = !empty($_POST['enabled']) ? 1 : 0;
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
    $label       = trim($_POST['label']        ?? '');
    $title       = trim($_POST['title']        ?? '');
    $linkUrl     = trim($_POST['link_url']     ?? '');
    $buttonLabel = trim($_POST['button_label'] ?? '') ?: '詳しく見る';
    $imageUrlTxt = trim($_POST['image_url']    ?? '');

    if ($act === 'add') {
        if (!$title || !$linkUrl) {
            $error = 'タイトル・リンクURLは必須です';
        } else {
            $uploaded = handleImageUpload($error, 'feature');
            if (!$error) {
                $image = $uploaded ?? $imageUrlTxt;
                if (!$image) {
                    $error = '画像（URLまたはアップロード）を指定してください';
                } else {
                    db()->prepare(
                        'INSERT INTO melikret_feature (enabled, sort_order, image_url, label, title, link_url, button_label)
                         VALUES (?,?,?,?,?,?,?)'
                    )->execute([$enabled, $sortOrder, $image, $label, $title, $linkUrl, $buttonLabel]);
                    header('Location: feature.php?flash=added'); exit;
                }
            }
        }
    } elseif ($act === 'edit') {
        $eid = (int)($_POST['id'] ?? 0);
        if (!$title || !$linkUrl) {
            $error = 'タイトル・リンクURLは必須です';
        } else {
            $cur = db()->prepare('SELECT image_url FROM melikret_feature WHERE id=?');
            $cur->execute([$eid]);
            $oldImage = (string)($cur->fetchColumn() ?: '');

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
                    db()->prepare(
                        'UPDATE melikret_feature SET enabled=?, sort_order=?, image_url=?, label=?, title=?, link_url=?, button_label=?
                         WHERE id=?'
                    )->execute([$enabled, $sortOrder, $image, $label, $title, $linkUrl, $buttonLabel, $eid]);
                    header('Location: feature.php?flash=updated'); exit;
                }
            }
        }
    } elseif ($act === 'delete') {
        $did = (int)($_POST['id'] ?? 0);
        $cur = db()->prepare('SELECT image_url FROM melikret_feature WHERE id=?');
        $cur->execute([$did]);
        $img = (string)($cur->fetchColumn() ?: '');
        if (strpos($img, 'uploads/') === 0) deleteImage($img);
        db()->prepare('DELETE FROM melikret_feature WHERE id=?')->execute([$did]);
        header('Location: feature.php?flash=deleted'); exit;
    }
}

$flashMap = ['added' => '追加しました', 'updated' => '保存しました', 'deleted' => '削除しました'];
$flash = $flashMap[$_GET['flash'] ?? ''] ?? '';

/* ── 追加フォーム ───────────────────────────────── */
if ($action === 'add') {
    adminHead('バナー追加', 'feature');
    echo '<a class="back" href="feature.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>バナー追加</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    $nextOrder = (int)db()->query('SELECT GREATEST(COALESCE(MIN(sort_order),10)-10,0) FROM melikret_feature')->fetchColumn();
    $v = ['enabled' => 1, 'sort_order' => $nextOrder, 'image_url' => '', 'label' => '',
          'title' => '', 'link_url' => '', 'button_label' => '詳しく見る'];
    renderFeatureForm('add', $v);
    echo '</div>';
    adminFoot(); exit;
}

/* ── 編集フォーム ───────────────────────────────── */
if ($action === 'edit' && $id) {
    $stmt = db()->prepare('SELECT * FROM melikret_feature WHERE id=?');
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if (!$v) { header('Location: feature.php'); exit; }
    adminHead('バナー編集', 'feature');
    echo '<a class="back" href="feature.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>バナー編集</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    renderFeatureForm('edit', $v, $id);
    echo '<hr style="border:none;border-top:1px solid #e5eff5;margin:28px 0">';
    echo '<h2 style="color:#c0392b;border-color:#f5c6cb">削除</h2>';
    echo '<p class="hint" style="margin-bottom:12px">一時的に隠したいだけなら、削除せず「表示する」のチェックを外してください。</p>';
    echo '<form method="post" onsubmit="return confirm(\'このバナーを削除しますか？\')">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="delete">';
    echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-danger btn-sm">削除する</button>';
    echo '</form>';
    echo '</div>';
    adminFoot(); exit;
}

/* ── 一覧 ────────────────────────────────────────── */
adminHead('TOP売出項目', 'feature');
if ($flash) echo '<div class="alert alert-ok">' . h($flash) . '</div>';
echo '<div class="card">';
echo '<div class="top-bar"><h2 style="margin:0;border:none;padding:0">TOP売出項目（バナー）</h2>';
echo '<a href="feature.php?action=add" class="btn btn-primary btn-sm">＋ 追加</a></div>';
echo '<p class="hint" style="margin-bottom:16px">サイトトップの大きなバナーです。表示順（大きいほど先頭）に並びます。PCでは横並び、スマホでは自動スライドで切り替わります。</p>';

$rows = db()->query('SELECT * FROM melikret_feature ORDER BY sort_order DESC, id ASC')->fetchAll();
if (!$rows) {
    echo '<p style="color:#8a9faa;font-size:.9rem;padding:20px 0">まだバナーがありません</p>';
} else {
    echo '<table><thead><tr><th></th><th>タイトル</th><th>表示</th><th>表示順</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $img = (string)$r['image_url'];
        $src = (strpos($img, 'http') === 0) ? $img : '../' . $img;
        echo '<tr>';
        echo '<td><img src="' . h($src) . '" alt="" style="width:44px;height:60px;object-fit:cover;border-radius:4px;border:1px solid #d0e6ef"></td>';
        echo '<td>' . h(mb_strimwidth(str_replace(["\r\n", "\n"], ' ', $r['title']), 0, 40, '…')) . '</td>';
        echo '<td>' . ((int)$r['enabled'] ? '<span class="badge badge-sale">表示中</span>' : '<span class="badge badge-ended">非表示</span>') . '</td>';
        echo '<td style="color:#5a6f78">' . h((string)$r['sort_order']) . '</td>';
        echo '<td class="actions"><a href="feature.php?action=edit&id=' . $r['id'] . '" class="btn btn-secondary btn-sm">編集</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';
adminFoot();

/* ── フォーム描画ヘルパー ──────────────────────── */
function renderFeatureForm(string $act, array $v, int $id = 0): void {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="' . h($act) . '">';
    if ($id) echo '<input type="hidden" name="id" value="' . $id . '">';

    echo '<div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600"><input type="checkbox" name="enabled" value="1" style="width:auto"' . (!empty($v['enabled']) ? ' checked' : '') . '> 表示する</label></div>';

    echo '<div class="form-row">';
    echo '<div class="form-group"><label>表示順（大きいほど先頭）</label><input type="text" name="sort_order" value="' . h((string)($v['sort_order'] ?? 0)) . '"></div>';
    echo '<div class="form-group"><label>ボタン文言</label><input type="text" name="button_label" value="' . h($v['button_label'] ?? '') . '" placeholder="例: チケット情報へ"></div>';
    echo '</div>';

    echo '<div class="form-group"><label>タイトル <span style="color:#e74c3c">*</span></label><textarea name="title" required placeholder="例: メリクレット&#10;2nd Tour 2026">' . h($v['title'] ?? '') . '</textarea><p class="hint">画像の説明（読み上げ・画像が出ないときの代わりの文字）に使われます。</p></div>';

    echo '<div class="form-group"><label>ラベル（管理用メモ）</label><input type="text" name="label" value="' . h($v['label'] ?? '') . '" placeholder="例: TOUR / ONE MAN"></div>';

    echo '<div class="form-group"><label>画像URL</label><input type="text" name="image_url" value="' . h($v['image_url'] ?? '') . '" placeholder="https://..."><p class="hint">下からアップロードした場合はそちらが優先されます。縦長のフライヤー画像を想定しています。</p></div>';
    $img = trim((string)($v['image_url'] ?? ''));
    if ($img !== '') {
        $imgSrc = (strpos($img, 'http') === 0) ? $img : '../' . $img;
        echo '<div style="margin-bottom:14px"><img src="' . h($imgSrc) . '" alt="" style="max-width:220px;max-height:300px;border-radius:8px;border:1px solid #d0e6ef"></div>';
    }
    echo '<div class="form-group"><label>画像アップロード</label><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;padding:8px 0"><p class="hint">JPEG / PNG / GIF / WebP・6MBまで。</p></div>';

    echo '<div class="form-group"><label>リンクURL <span style="color:#e74c3c">*</span></label><input type="url" name="link_url" value="' . h($v['link_url'] ?? '') . '" required placeholder="https://..."></div>';

    $label = $act === 'add' ? '追加する' : '保存する';
    echo '<button type="submit" class="btn btn-primary">' . $label . '</button>';
    echo '</form>';
}
