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
    $liveId      = (int)($_POST['live_id']     ?? 0) ?: null;
    $imageUrlTxt = trim($_POST['image_url']    ?? '');

    // 押したときに開くもの：ライブを選んだらその詳細ポップアップ、選ばなければリンクURL
    // ライブを選んだときは link_url に「#live-<ライブID>」を入れる（DBの列は増やさない）
    $saveLink    = $liveId !== null ? '#live-' . $liveId : $linkUrl;
    $targetError = '';
    if ($liveId !== null) {
        $chk = db()->prepare('SELECT COUNT(*) FROM melikret_live WHERE id=?');
        $chk->execute([$liveId]);
        if (!(int)$chk->fetchColumn()) $targetError = '選んだライブが見つかりません。もう一度選び直してください';
    } elseif ($linkUrl === '') {
        $targetError = 'リンクURLを入力するか、詳細を開くライブを選んでください';
    }

    if ($act === 'add') {
        if (!$title) {
            $error = 'タイトルは必須です';
        } elseif ($targetError) {
            $error = $targetError;
        } else {
            $uploaded = handleImageUpload($error, 'feature');
            if (!$error) {
                $image = $uploaded ?? $imageUrlTxt;
                if (!$image) {
                    $error = '画像（URLまたはアップロード）を指定してください';
                } else {
                    db()->prepare(
                        'INSERT INTO melikret_feature (enabled, sort_order, image_url, label, title, link_url)
                         VALUES (?,?,?,?,?,?)'
                    )->execute([$enabled, $sortOrder, $image, $label, $title, $saveLink]);
                    header('Location: feature.php?flash=added'); exit;
                }
            }
        }
    } elseif ($act === 'edit') {
        $eid = (int)($_POST['id'] ?? 0);
        if (!$title) {
            $error = 'タイトルは必須です';
        } elseif ($targetError) {
            $error = $targetError;
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
                        'UPDATE melikret_feature SET enabled=?, sort_order=?, image_url=?, label=?, title=?, link_url=?
                         WHERE id=?'
                    )->execute([$enabled, $sortOrder, $image, $label, $title, $saveLink, $eid]);
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
          'title' => '', 'link_url' => '', 'live_id' => null];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 入力エラーで戻ったときは、入れた内容を残す
        $v = ['enabled' => $enabled, 'sort_order' => $sortOrder, 'image_url' => $imageUrlTxt, 'label' => $label,
              'title' => $title, 'link_url' => $linkUrl, 'live_id' => $liveId];
    }
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
    $liveNames = [];
    foreach (db()->query('SELECT id, event_date, event_name FROM melikret_live')->fetchAll() as $lv) {
        $liveNames[(int)$lv['id']] = date('Y.m.d', strtotime($lv['event_date'])) . ' ' . $lv['event_name'];
    }
    echo '<table><thead><tr><th></th><th>タイトル</th><th>押したとき</th><th>表示</th><th>表示順</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $img = (string)$r['image_url'];
        $src = (strpos($img, 'http') === 0) ? $img : '../' . $img;
        echo '<tr>';
        echo '<td><img src="' . h($src) . '" alt="" style="width:44px;height:60px;object-fit:cover;border-radius:4px;border:1px solid #d0e6ef"></td>';
        echo '<td>' . h(mb_strimwidth(str_replace(["\r\n", "\n"], ' ', $r['title']), 0, 40, '…')) . '</td>';
        $rLive = featureLiveId((string)$r['link_url']);
        if ($rLive !== null) {
            $ln = $liveNames[$rLive] ?? null;
            $target = $ln !== null ? 'LIVE詳細：' . mb_strimwidth($ln, 0, 34, '…') : 'LIVE詳細（ライブが見つかりません）';
        } else {
            $target = 'リンク：' . mb_strimwidth((string)$r['link_url'], 0, 34, '…');
        }
        echo '<td style="color:#5a6f78;font-size:.85rem">' . h($target) . '</td>';
        echo '<td>' . ((int)$r['enabled'] ? '<span class="badge badge-sale">表示中</span>' : '<span class="badge badge-ended">非表示</span>') . '</td>';
        echo '<td style="color:#5a6f78">' . h((string)$r['sort_order']) . '</td>';
        echo '<td class="actions"><a href="feature.php?action=edit&id=' . $r['id'] . '" class="btn btn-secondary btn-sm">編集</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';
adminFoot();

/* link_url が「#live-<ライブID>」ならそのIDを返す（バナーを押したらライブの詳細を開く設定） */
function featureLiveId(string $link): ?int {
    return preg_match('/^#live-(\d+)$/', $link, $m) ? (int)$m[1] : null;
}

/* ── フォーム描画ヘルパー ──────────────────────── */
function renderFeatureForm(string $act, array $v, int $id = 0): void {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="' . h($act) . '">';
    if ($id) echo '<input type="hidden" name="id" value="' . $id . '">';

    echo '<div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600"><input type="checkbox" name="enabled" value="1" style="width:auto"' . (!empty($v['enabled']) ? ' checked' : '') . '> 表示する</label></div>';

    echo '<div class="form-row">';
    echo '<div class="form-group"><label>表示順（大きいほど先頭）</label><input type="text" name="sort_order" value="' . h((string)($v['sort_order'] ?? 0)) . '"></div>';
    echo '<div class="form-group"><label>ラベル（管理用メモ）</label><input type="text" name="label" value="' . h($v['label'] ?? '') . '" placeholder="例: TOUR / ONE MAN"></div>';
    echo '</div>';

    echo '<div class="form-group"><label>タイトル <span style="color:#e74c3c">*</span></label><textarea name="title" required placeholder="例: メリクレット&#10;2nd Tour 2026">' . h($v['title'] ?? '') . '</textarea><p class="hint">画像の説明（読み上げ・画像が出ないときの代わりの文字）に使われます。</p></div>';

    echo '<div class="form-group"><label>画像URL</label><input type="text" name="image_url" value="' . h($v['image_url'] ?? '') . '" placeholder="https://..."><p class="hint">下からアップロードした場合はそちらが優先されます。縦長のフライヤー画像を想定しています。</p></div>';
    $img = trim((string)($v['image_url'] ?? ''));
    if ($img !== '') {
        $imgSrc = (strpos($img, 'http') === 0) ? $img : '../' . $img;
        echo '<div style="margin-bottom:14px"><img src="' . h($imgSrc) . '" alt="" style="max-width:220px;max-height:300px;border-radius:8px;border:1px solid #d0e6ef"></div>';
    }
    echo '<div class="form-group"><label>画像アップロード</label><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;padding:8px 0"><p class="hint">JPEG / PNG / GIF / WebP・6MBまで。</p></div>';

    // 押したときに開くもの（ライブの詳細ポップアップ／リンクURL）
    $linkVal = (string)($v['link_url'] ?? '');
    $curLive = (int)($v['live_id'] ?? featureLiveId($linkVal) ?? 0);
    if (featureLiveId($linkVal) !== null) $linkVal = ''; // 「#live-○」は入力欄に出さない
    $lives = db()->query('SELECT id, event_date, event_name, venue FROM melikret_live ORDER BY event_date DESC, id DESC')->fetchAll();
    echo '<div class="form-group"><label>押したときに開くもの</label><select name="live_id" id="featureLiveSelect">';
    echo '<option value="">リンクURLを開く（別タブ）</option>';
    foreach ($lives as $lv) {
        $lid = (int)$lv['id'];
        $text = 'ライブの詳細：' . date('Y.m.d', strtotime($lv['event_date'])) . '　' . $lv['event_name'] . '（' . $lv['venue'] . '）';
        echo '<option value="' . $lid . '"' . ($lid === $curLive ? ' selected' : '') . '>' . h(mb_strimwidth($text, 0, 90, '…')) . '</option>';
    }
    echo '</select><p class="hint">ライブを選ぶと、LIVE欄の「詳細 →」と同じポップアップが開きます。このときリンクURLは使いません。</p></div>';

    echo '<div class="form-group" id="featureLinkGroup"><label>リンクURL</label><input type="url" name="link_url" value="' . h($linkVal) . '" placeholder="https://..."><p class="hint">「リンクURLを開く」のときに必要です。</p></div>';
    echo '<script>(function(){var s=document.getElementById("featureLiveSelect"),g=document.getElementById("featureLinkGroup");'
       . 'function u(){g.style.opacity=s.value?".45":"";}s.addEventListener("change",u);u();})();</script>';

    $label = $act === 'add' ? '追加する' : '保存する';
    echo '<button type="submit" class="btn btn-primary">' . $label . '</button>';
    echo '</form>';
}
