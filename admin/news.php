<?php
require '_inc.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$flash  = '';
$error  = '';
$cats   = ['RELEASE', 'LIVE', 'NEWS', 'EVENT', 'MEDIA', 'OTHER'];

/* ── POST 処理 ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act         = $_POST['_action']    ?? '';
    $date        = trim($_POST['news_date']   ?? '');
    $cat         = trim($_POST['category']    ?? '');
    $body        = trim($_POST['body']        ?? '');
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $linkUrl     = trim($_POST['link_url']    ?? '');
    $linkLabel   = trim($_POST['link_label']  ?? '');

    if ($act === 'add') {
        if (!$date || !$cat || !$body) {
            $error = '全項目を入力してください';
        } else {
            $image = handleImageUpload($error, 'news');
            if (!$error) {
                db()->prepare(
                    'INSERT INTO melikret_news (news_date, category, title, body, description, image_url, link_url, link_label)
                     VALUES (?,?,?,?,?,?,?,?)'
                )->execute([$date, $cat, $title, $body, $description, $image ?? '', $linkUrl, $linkLabel]);
                header('Location: news.php?flash=added'); exit;
            }
        }
    } elseif ($act === 'edit') {
        $eid = (int)($_POST['id'] ?? 0);
        if (!$date || !$cat || !$body) {
            $error = '全項目を入力してください';
        } else {
            $cur = db()->prepare('SELECT image_url FROM melikret_news WHERE id=?');
            $cur->execute([$eid]);
            $oldImage = (string)($cur->fetchColumn() ?: '');

            $newImage = handleImageUpload($error, 'news');
            if (!$error) {
                $image = $oldImage;
                if ($newImage !== null) {
                    deleteImage($oldImage);
                    $image = $newImage;
                } elseif (!empty($_POST['remove_image'])) {
                    deleteImage($oldImage);
                    $image = '';
                }
                db()->prepare(
                    'UPDATE melikret_news SET
                       news_date=?, category=?, title=?, body=?, description=?, image_url=?, link_url=?, link_label=?
                     WHERE id=?'
                )->execute([$date, $cat, $title, $body, $description, $image, $linkUrl, $linkLabel, $eid]);
                header('Location: news.php?flash=updated'); exit;
            }
        }
    } elseif ($act === 'delete') {
        $did = (int)($_POST['id'] ?? 0);
        $cur = db()->prepare('SELECT image_url FROM melikret_news WHERE id=?');
        $cur->execute([$did]);
        deleteImage((string)($cur->fetchColumn() ?: ''));
        db()->prepare('DELETE FROM melikret_news WHERE id=?')->execute([$did]);
        header('Location: news.php?flash=deleted'); exit;
    }
}

$flashMap = ['added' => '追加しました', 'updated' => '更新しました', 'deleted' => '削除しました'];
$flash = $flashMap[$_GET['flash'] ?? ''] ?? '';

/* ── 追加フォーム ───────────────────────────────── */
if ($action === 'add') {
    adminHead('ニュース追加', 'news');
    echo '<a class="back" href="news.php">← 一覧に戻る</a>';
    echo '<div class="card">';
    echo '<h2>ニュース追加</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    $v = ['news_date' => date('Y-m-d'), 'category' => 'RELEASE', 'title' => '', 'body' => '',
          'description' => '', 'image_url' => '', 'link_url' => '', 'link_label' => ''];
    renderNewsForm('add', $v, $cats);
    echo '</div>';
    adminFoot();
    exit;
}

/* ── 編集フォーム ───────────────────────────────── */
if ($action === 'edit' && $id) {
    $stmt = db()->prepare('SELECT * FROM melikret_news WHERE id=?');
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if (!$v) { header('Location: news.php'); exit; }
    adminHead('ニュース編集', 'news');
    echo '<a class="back" href="news.php">← 一覧に戻る</a>';
    echo '<div class="card">';
    echo '<h2>ニュース編集</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    renderNewsForm('edit', $v, $cats, $id);
    echo '<hr style="border:none;border-top:1px solid #e5eff5;margin:28px 0">';
    echo '<h2 style="color:#c0392b;border-color:#f5c6cb">削除</h2>';
    echo '<form method="post" onsubmit="return confirm(\'このニュースを削除しますか？\')">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="delete">';
    echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-danger btn-sm">削除する</button>';
    echo '</form>';
    echo '</div>';
    adminFoot();
    exit;
}

/* ── 一覧 ────────────────────────────────────────── */
adminHead('ニュース管理', 'news');
if ($flash) echo '<div class="alert alert-ok">' . h($flash) . '</div>';
echo '<div class="card">';
echo '<div class="top-bar"><h2 style="margin:0;border:none;padding:0">ニュース一覧</h2>';
echo '<a href="news.php?action=add" class="btn btn-primary btn-sm">＋ 追加</a></div>';

$perPage = 5;
$page    = max(1, (int)($_GET['p'] ?? 1));
$total   = (int)db()->query('SELECT COUNT(*) FROM melikret_news')->fetchColumn();
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min($page, $pages);
$offset  = ($page - 1) * $perPage;
$stmt    = db()->prepare('SELECT * FROM melikret_news ORDER BY news_date DESC, id DESC LIMIT ? OFFSET ?');
$stmt->execute([$perPage, $offset]);
$rows = $stmt->fetchAll();

if (!$rows) {
    echo '<p style="color:#8a9faa;font-size:.9rem;padding:20px 0">まだニュースがありません</p>';
} else {
    echo '<table><thead><tr><th>日付</th><th>カテゴリ</th><th>内容</th><th>詳細</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td style="white-space:nowrap;color:#5a6f78">' . h(date('Y.m.d', strtotime($r['news_date']))) . '</td>';
        echo '<td><span class="badge badge-sale" style="font-size:.72rem">' . h($r['category']) . '</span></td>';
        echo '<td>' . h(mb_strimwidth($r['body'], 0, 60, '…')) . '</td>';
        $hasDetail = trim((string)($r['title'] ?? '')) !== '' || trim((string)($r['description'] ?? '')) !== ''
                  || trim((string)($r['image_url'] ?? '')) !== '' || trim((string)($r['link_url'] ?? '')) !== '';
        echo '<td>' . ($hasDetail ? '<span class="badge badge-perf">あり</span>' : '<span style="color:#c5d2d9;font-size:.8rem">—</span>') . '</td>';
        echo '<td class="actions"><a href="news.php?action=edit&id=' . $r['id'] . '" class="btn btn-secondary btn-sm">編集</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    if ($pages > 1) {
        $prev = $page > 1
            ? '<a href="news.php?p=' . ($page - 1) . '" class="btn btn-secondary btn-sm">← 前へ</a>'
            : '<span class="btn btn-secondary btn-sm pager-dis">← 前へ</span>';
        $next = $page < $pages
            ? '<a href="news.php?p=' . ($page + 1) . '" class="btn btn-secondary btn-sm">次へ →</a>'
            : '<span class="btn btn-secondary btn-sm pager-dis">次へ →</span>';
        echo '<div class="pagination">' . $prev . '<span class="pager-info">' . $page . ' / ' . $pages . '</span>' . $next . '</div>';
    }
}
echo '</div>';
adminFoot();

/* ── フォーム描画ヘルパー ──────────────────────── */
function renderNewsForm(string $act, array $v, array $cats, int $id = 0): void {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="' . h($act) . '">';
    if ($id) echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<div class="form-row">';
    echo '<div class="form-group"><label>日付</label><input type="date" name="news_date" value="' . h($v['news_date']) . '" required></div>';
    echo '<div class="form-group"><label>カテゴリ</label><select name="category">';
    foreach ($cats as $c) {
        $sel = $v['category'] === $c ? ' selected' : '';
        echo '<option value="' . h($c) . '"' . $sel . '>' . h($c) . '</option>';
    }
    echo '</select></div>';
    echo '</div>';
    echo '<div class="form-group"><label>内容（一覧表示文）</label><textarea name="body" required>' . h($v['body']) . '</textarea><p class="hint">NEWSセクション一覧に表示される短い文章です。</p></div>';

    echo '<hr style="border:none;border-top:1px solid #e5eff5;margin:24px 0 20px">';
    echo '<p style="font-size:.8rem;color:#8a9faa;margin-bottom:16px">以下を1つでも入力すると、一覧の内容が詳細画面へのリンクになります（未入力なら詳細画面は表示されません）。</p>';

    echo '<div class="form-group"><label>見出し</label><input type="text" name="title" value="' . h($v['title'] ?? '') . '" placeholder="例: あしたのごちそう Digital Single Release"></div>';

    echo '<div class="form-group"><label>説明</label><textarea name="description" placeholder="詳細画面に表示される紹介文。改行はそのまま反映されます。">' . h($v['description'] ?? '') . '</textarea></div>';

    // 画像（ジャケ写等）
    echo '<div class="form-group"><label>画像添付（ジャケ写など）</label>';
    $img = trim((string)($v['image_url'] ?? ''));
    if ($img !== '') {
        echo '<div style="margin-bottom:10px"><img src="../' . h($img) . '" alt="" style="max-width:240px;max-height:180px;border-radius:8px;border:1px solid #d0e6ef"></div>';
        echo '<label style="display:flex;align-items:center;gap:8px;font-weight:500;color:#c0392b;cursor:pointer"><input type="checkbox" name="remove_image" value="1" style="width:auto"> 現在の画像を削除する</label>';
        echo '<p class="hint">差し替える場合は下から新しい画像を選択してください。</p>';
    }
    echo '<input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;padding:8px 0">';
    echo '<p class="hint">JPEG / PNG / GIF / WebP・6MBまで。詳細画面の先頭に表示されます。</p>';
    echo '</div>';

    echo '<div class="form-row">';
    echo '<div class="form-group"><label>リンクURL（リンクファイア等）</label><input type="url" name="link_url" value="' . h($v['link_url'] ?? '') . '" placeholder="https://melikret.lnk.to/..."></div>';
    echo '<div class="form-group"><label>リンクのボタン文言</label><input type="text" name="link_label" value="' . h($v['link_label'] ?? '') . '" placeholder="例: 配信で聴く →"></div>';
    echo '</div>';

    $label = $act === 'add' ? '追加する' : '保存する';
    echo '<button type="submit" class="btn btn-primary">' . $label . '</button>';
    echo '</form>';
}
