<?php
require '_inc.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$flash  = '';
$error  = '';

/* YouTube の URL または動画IDから動画IDだけを取り出す */
function extractYoutubeId(string $input): string {
    $input = trim($input);
    if ($input === '') return '';
    if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $input) && !str_contains($input, '/')) {
        return $input; // すでにID
    }
    if (preg_match('#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,20})#', $input, $m)) {
        return $m[1];
    }
    return $input;
}

/* ── POST 処理 ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act       = $_POST['_action']    ?? '';
    $ytInput   = trim($_POST['youtube_id'] ?? '');
    $ytId      = extractYoutubeId($ytInput);
    $title     = trim($_POST['title']      ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);

    if ($act === 'add') {
        if (!$ytId || !$title) {
            $error = 'YouTube動画ID（またはURL）・タイトルは必須です';
        } else {
            db()->prepare('INSERT INTO melikret_movie (youtube_id, title, sort_order) VALUES (?,?,?)')
               ->execute([$ytId, $title, $sortOrder]);
            header('Location: movie.php?flash=added'); exit;
        }
    } elseif ($act === 'edit') {
        $eid = (int)($_POST['id'] ?? 0);
        if (!$ytId || !$title) {
            $error = 'YouTube動画ID（またはURL）・タイトルは必須です';
        } else {
            db()->prepare('UPDATE melikret_movie SET youtube_id=?, title=?, sort_order=? WHERE id=?')
               ->execute([$ytId, $title, $sortOrder, $eid]);
            header('Location: movie.php?flash=updated'); exit;
        }
    } elseif ($act === 'delete') {
        db()->prepare('DELETE FROM melikret_movie WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: movie.php?flash=deleted'); exit;
    }
}

$flashMap = ['added' => '追加しました', 'updated' => '更新しました', 'deleted' => '削除しました'];
$flash = $flashMap[$_GET['flash'] ?? ''] ?? '';

/* ── 追加フォーム ───────────────────────────────── */
if ($action === 'add') {
    adminHead('MOVIE追加', 'movie');
    echo '<a class="back" href="movie.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>MOVIE追加</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    $nextOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM melikret_movie')->fetchColumn();
    $v = ['youtube_id' => '', 'title' => '', 'sort_order' => $nextOrder];
    renderMovieForm('add', $v);
    echo '</div>';
    adminFoot(); exit;
}

/* ── 編集フォーム ───────────────────────────────── */
if ($action === 'edit' && $id) {
    $stmt = db()->prepare('SELECT * FROM melikret_movie WHERE id=?');
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if (!$v) { header('Location: movie.php'); exit; }
    adminHead('MOVIE編集', 'movie');
    echo '<a class="back" href="movie.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>MOVIE編集</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    renderMovieForm('edit', $v, $id);
    echo '<hr style="border:none;border-top:1px solid #e5eff5;margin:28px 0">';
    echo '<h2 style="color:#c0392b;border-color:#f5c6cb">削除</h2>';
    echo '<form method="post" onsubmit="return confirm(\'この動画を削除しますか？\')">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="delete">';
    echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-danger btn-sm">削除する</button>';
    echo '</form>';
    echo '</div>';
    adminFoot(); exit;
}

/* ── 一覧 ────────────────────────────────────────── */
adminHead('MOVIE管理', 'movie');
if ($flash) echo '<div class="alert alert-ok">' . h($flash) . '</div>';
echo '<div class="card">';
echo '<div class="top-bar"><h2 style="margin:0;border:none;padding:0">MOVIE一覧</h2>';
echo '<a href="movie.php?action=add" class="btn btn-primary btn-sm">＋ 追加</a></div>';
echo '<p class="hint" style="margin-bottom:16px">表示順（大きいほど先頭）を指定できます。</p>';

$rows = db()->query('SELECT * FROM melikret_movie ORDER BY sort_order DESC, id DESC')->fetchAll();
if (!$rows) {
    echo '<p style="color:#8a9faa;font-size:.9rem;padding:20px 0">まだ動画がありません</p>';
} else {
    echo '<table><thead><tr><th></th><th>タイトル</th><th>動画ID</th><th>表示順</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td><img src="https://i.ytimg.com/vi/' . h($r['youtube_id']) . '/default.jpg" alt="" style="width:60px;height:44px;object-fit:cover;border-radius:4px;border:1px solid #d0e6ef"></td>';
        echo '<td>' . h(mb_strimwidth($r['title'], 0, 40, '…')) . '</td>';
        echo '<td style="color:#5a6f78;font-size:.85rem">' . h($r['youtube_id']) . '</td>';
        echo '<td style="color:#5a6f78">' . h((string)$r['sort_order']) . '</td>';
        echo '<td class="actions"><a href="movie.php?action=edit&id=' . $r['id'] . '" class="btn btn-secondary btn-sm">編集</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';
adminFoot();

/* ── フォーム描画ヘルパー ──────────────────────── */
function renderMovieForm(string $act, array $v, int $id = 0): void {
    echo '<form method="post">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="' . h($act) . '">';
    if ($id) echo '<input type="hidden" name="id" value="' . $id . '">';

    echo '<div class="form-group"><label>YouTube URL または動画ID <span style="color:#e74c3c">*</span></label><input type="text" name="youtube_id" value="' . h($v['youtube_id']) . '" required placeholder="https://www.youtube.com/watch?v=xxxxxxxxxxx または xxxxxxxxxxx"><p class="hint">動画ページのURLをそのまま貼り付けてOKです。</p></div>';

    echo '<div class="form-group"><label>タイトル <span style="color:#e74c3c">*</span></label><input type="text" name="title" value="' . h($v['title']) . '" required placeholder="例: ネガ・リセット！ - メリクレット (Music Video)"></div>';

    echo '<div class="form-group"><label>表示順（大きいほど先頭）</label><input type="text" name="sort_order" value="' . h((string)($v['sort_order'] ?? 0)) . '"></div>';

    $label = $act === 'add' ? '追加する' : '保存する';
    echo '<button type="submit" class="btn btn-primary">' . $label . '</button>';
    echo '</form>';
}
