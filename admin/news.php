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
    $act  = $_POST['_action'] ?? '';
    $date = trim($_POST['news_date'] ?? '');
    $cat  = trim($_POST['category']  ?? '');
    $body = trim($_POST['body']      ?? '');

    if ($act === 'add') {
        if (!$date || !$cat || !$body) {
            $error = '全項目を入力してください';
        } else {
            db()->prepare('INSERT INTO melikret_news (news_date, category, body) VALUES (?,?,?)')
               ->execute([$date, $cat, $body]);
            header('Location: news.php?flash=added'); exit;
        }
    } elseif ($act === 'edit') {
        $eid = (int)($_POST['id'] ?? 0);
        if (!$date || !$cat || !$body) {
            $error = '全項目を入力してください';
        } else {
            db()->prepare('UPDATE melikret_news SET news_date=?, category=?, body=? WHERE id=?')
               ->execute([$date, $cat, $body, $eid]);
            header('Location: news.php?flash=updated'); exit;
        }
    } elseif ($act === 'delete') {
        db()->prepare('DELETE FROM melikret_news WHERE id=?')
           ->execute([(int)($_POST['id'] ?? 0)]);
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
    $v = ['news_date' => date('Y-m-d'), 'category' => 'RELEASE', 'body' => ''];
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
    echo '<table><thead><tr><th>日付</th><th>カテゴリ</th><th>内容</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td style="white-space:nowrap;color:#5a6f78">' . h(date('Y.m.d', strtotime($r['news_date']))) . '</td>';
        echo '<td><span class="badge badge-sale" style="font-size:.72rem">' . h($r['category']) . '</span></td>';
        echo '<td>' . h(mb_strimwidth($r['body'], 0, 60, '…')) . '</td>';
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
    echo '<form method="post">';
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
    echo '<div class="form-group"><label>内容</label><textarea name="body" required>' . h($v['body']) . '</textarea></div>';
    $label = $act === 'add' ? '追加する' : '保存する';
    echo '<button type="submit" class="btn btn-primary">' . $label . '</button>';
    echo '</form>';
}
