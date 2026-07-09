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
    $act        = $_POST['_action']     ?? '';
    $date       = trim($_POST['release_date'] ?? '');
    $title      = trim($_POST['title']        ?? '');
    $type       = trim($_POST['type']         ?? '');
    $jacketUrl  = trim($_POST['jacket_url']   ?? '');
    $linkUrl    = trim($_POST['link_url']     ?? '');
    $sortOrder  = (int)($_POST['sort_order']  ?? 0);

    if ($act === 'add') {
        if (!$date || !$title || !$linkUrl) {
            $error = '発売日・タイトル・リンクURLは必須です';
        } else {
            $uploaded = handleImageUpload($error, 'disco');
            if (!$error) {
                $jacket = $uploaded ?? $jacketUrl;
                if (!$jacket) {
                    $error = 'ジャケット画像（URLまたはアップロード）を指定してください';
                } else {
                    db()->prepare(
                        'INSERT INTO melikret_discography (release_date, title, type, jacket_url, link_url, sort_order)
                         VALUES (?,?,?,?,?,?)'
                    )->execute([$date, $title, $type ?: 'Digital Single', $jacket, $linkUrl, $sortOrder]);
                    header('Location: discography.php?flash=added'); exit;
                }
            }
        }
    } elseif ($act === 'edit') {
        $eid = (int)($_POST['id'] ?? 0);
        if (!$date || !$title || !$linkUrl) {
            $error = '発売日・タイトル・リンクURLは必須です';
        } else {
            $cur = db()->prepare('SELECT jacket_url FROM melikret_discography WHERE id=?');
            $cur->execute([$eid]);
            $oldJacket = (string)($cur->fetchColumn() ?: '');

            $uploaded = handleImageUpload($error, 'disco');
            if (!$error) {
                $jacket = $oldJacket;
                if ($uploaded !== null) {
                    if (strpos($oldJacket, 'uploads/') === 0) deleteImage($oldJacket);
                    $jacket = $uploaded;
                } elseif ($jacketUrl !== '' && $jacketUrl !== $oldJacket) {
                    if (strpos($oldJacket, 'uploads/') === 0) deleteImage($oldJacket);
                    $jacket = $jacketUrl;
                }
                db()->prepare(
                    'UPDATE melikret_discography SET
                       release_date=?, title=?, type=?, jacket_url=?, link_url=?, sort_order=?
                     WHERE id=?'
                )->execute([$date, $title, $type ?: 'Digital Single', $jacket, $linkUrl, $sortOrder, $eid]);
                header('Location: discography.php?flash=updated'); exit;
            }
        }
    } elseif ($act === 'delete') {
        $did = (int)($_POST['id'] ?? 0);
        $cur = db()->prepare('SELECT jacket_url FROM melikret_discography WHERE id=?');
        $cur->execute([$did]);
        $j = (string)($cur->fetchColumn() ?: '');
        if (strpos($j, 'uploads/') === 0) deleteImage($j);
        db()->prepare('DELETE FROM melikret_discography WHERE id=?')->execute([$did]);
        header('Location: discography.php?flash=deleted'); exit;
    }
}

$flashMap = ['added' => '追加しました', 'updated' => '更新しました', 'deleted' => '削除しました'];
$flash = $flashMap[$_GET['flash'] ?? ''] ?? '';

/* ── 追加フォーム ───────────────────────────────── */
if ($action === 'add') {
    adminHead('作品追加', 'discography');
    echo '<a class="back" href="discography.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>作品追加</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    $nextOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM melikret_discography')->fetchColumn();
    $v = ['release_date' => date('Y-m-d'), 'title' => '', 'type' => 'Digital Single',
          'jacket_url' => '', 'link_url' => '', 'sort_order' => $nextOrder];
    renderDiscoForm('add', $v);
    echo '</div>';
    adminFoot(); exit;
}

/* ── 編集フォーム ───────────────────────────────── */
if ($action === 'edit' && $id) {
    $stmt = db()->prepare('SELECT * FROM melikret_discography WHERE id=?');
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if (!$v) { header('Location: discography.php'); exit; }
    adminHead('作品編集', 'discography');
    echo '<a class="back" href="discography.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>作品編集</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    renderDiscoForm('edit', $v, $id);
    echo '<hr style="border:none;border-top:1px solid #e5eff5;margin:28px 0">';
    echo '<h2 style="color:#c0392b;border-color:#f5c6cb">削除</h2>';
    echo '<form method="post" onsubmit="return confirm(\'この作品を削除しますか？\')">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="delete">';
    echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-danger btn-sm">削除する</button>';
    echo '</form>';
    echo '</div>';
    adminFoot(); exit;
}

/* ── 一覧 ────────────────────────────────────────── */
adminHead('ディスコグラフィー管理', 'discography');
if ($flash) echo '<div class="alert alert-ok">' . h($flash) . '</div>';
echo '<div class="card">';
echo '<div class="top-bar"><h2 style="margin:0;border:none;padding:0">作品一覧</h2>';
echo '<a href="discography.php?action=add" class="btn btn-primary btn-sm">＋ 追加</a></div>';
echo '<p class="hint" style="margin-bottom:16px">表示順（大きいほど先頭）を指定できます。フロント表示は表示順→発売日の降順です。</p>';

$rows = db()->query('SELECT * FROM melikret_discography ORDER BY sort_order DESC, release_date DESC, id DESC')->fetchAll();
if (!$rows) {
    echo '<p style="color:#8a9faa;font-size:.9rem;padding:20px 0">まだ作品がありません</p>';
} else {
    echo '<table><thead><tr><th></th><th>発売日</th><th>タイトル</th><th>形態</th><th>表示順</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td><img src="' . h($r['jacket_url']) . '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;border:1px solid #d0e6ef"></td>';
        echo '<td style="white-space:nowrap;color:#5a6f78">' . h(date('Y.m.d', strtotime($r['release_date']))) . '</td>';
        echo '<td>' . h(mb_strimwidth($r['title'], 0, 36, '…')) . '</td>';
        echo '<td style="color:#5a6f78;font-size:.85rem">' . h($r['type']) . '</td>';
        echo '<td style="color:#5a6f78">' . h((string)$r['sort_order']) . '</td>';
        echo '<td class="actions"><a href="discography.php?action=edit&id=' . $r['id'] . '" class="btn btn-secondary btn-sm">編集</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';
adminFoot();

/* ── フォーム描画ヘルパー ──────────────────────── */
function renderDiscoForm(string $act, array $v, int $id = 0): void {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="' . h($act) . '">';
    if ($id) echo '<input type="hidden" name="id" value="' . $id . '">';

    echo '<div class="form-row">';
    echo '<div class="form-group"><label>発売日 <span style="color:#e74c3c">*</span></label><input type="date" name="release_date" value="' . h($v['release_date']) . '" required></div>';
    echo '<div class="form-group"><label>表示順（大きいほど先頭）</label><input type="text" name="sort_order" value="' . h((string)($v['sort_order'] ?? 0)) . '"></div>';
    echo '</div>';

    echo '<div class="form-group"><label>タイトル <span style="color:#e74c3c">*</span></label><input type="text" name="title" value="' . h($v['title']) . '" required placeholder="例: あしたのごちそう"></div>';

    echo '<div class="form-group"><label>形態</label><input type="text" name="type" value="' . h($v['type']) . '" placeholder="例: Digital Single / 1st mini album"></div>';

    echo '<div class="form-group"><label>ジャケット画像URL</label><input type="url" name="jacket_url" value="' . h($v['jacket_url'] ?? '') . '" placeholder="https://... （Apple Music等のアートワークURL）"><p class="hint">下からアップロードした場合はそちらが優先されます。</p></div>';
    $img = trim((string)($v['jacket_url'] ?? ''));
    if ($img !== '') {
        echo '<div style="margin-bottom:14px"><img src="' . h($img) . '" alt="" style="max-width:160px;max-height:160px;border-radius:8px;border:1px solid #d0e6ef"></div>';
    }
    echo '<div class="form-group"><label>ジャケット画像アップロード</label><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;padding:8px 0"><p class="hint">JPEG / PNG / GIF / WebP・6MBまで。</p></div>';

    echo '<div class="form-group"><label>リンクURL（リンクファイア） <span style="color:#e74c3c">*</span></label><input type="url" name="link_url" value="' . h($v['link_url']) . '" required placeholder="https://melikret.lnk.to/..."></div>';

    $label = $act === 'add' ? '追加する' : '保存する';
    echo '<button type="submit" class="btn btn-primary">' . $label . '</button>';
    echo '</form>';
}
