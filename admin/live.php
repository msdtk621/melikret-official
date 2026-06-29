<?php
require '_inc.php';
requireLogin();

const MAX_TICKETS = 5;

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$flash  = '';
$error  = '';

/* ── 画像アップロード処理 ───────────────────────────
   成功: 'uploads/xxx.jpg' を返す / ファイル未選択: null / 失敗: null + $error 設定 */
function handleImageUpload(?string &$error): ?string {
    if (empty($_FILES['image']['name']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // ファイル未選択
    }
    $f = $_FILES['image'];
    if ($f['error'] !== UPLOAD_ERR_OK) { $error = '画像のアップロードに失敗しました（コード: ' . $f['error'] . '）'; return null; }
    if ($f['size'] > 6 * 1024 * 1024)  { $error = '画像は6MB以下にしてください'; return null; }

    $info = @getimagesize($f['tmp_name']);
    if ($info === false) { $error = '画像ファイルとして認識できませんでした'; return null; }
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $ext = $extMap[$info['mime']] ?? null;
    if (!$ext) { $error = '対応形式は JPEG / PNG / GIF / WebP です'; return null; }

    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $error = 'uploads ディレクトリを作成できませんでした'; return null; }
    $name = 'live_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) { $error = '画像の保存に失敗しました'; return null; }
    return 'uploads/' . $name;
}

/* サーバー上の画像ファイルを削除（uploads/ 配下のみ許可） */
function deleteImage(?string $path): void {
    if (!$path || strpos($path, 'uploads/') !== 0) return;
    $full = __DIR__ . '/../' . $path;
    if (is_file($full)) @unlink($full);
}

/* POST のチケット配列を [{info,url}, ...]（最大5件・両方空はスキップ）に整形 */
function collectTickets(): array {
    $infos = $_POST['ticket_info'] ?? [];
    $urls  = $_POST['ticket_url']  ?? [];
    if (!is_array($infos)) $infos = [$infos];
    if (!is_array($urls))  $urls  = [$urls];
    $tickets = [];
    $n = max(count($infos), count($urls));
    for ($i = 0; $i < $n && count($tickets) < MAX_TICKETS; $i++) {
        $info = trim((string)($infos[$i] ?? ''));
        $url  = trim((string)($urls[$i]  ?? ''));
        if ($info === '' && $url === '') continue;
        $tickets[] = ['info' => $info, 'url' => $url];
    }
    return $tickets;
}

/* 既存レコードからチケット行の配列を作る（旧 ticket_info/ticket_url からのフォールバック付き） */
function ticketRowsFrom(array $v): array {
    $rows = [];
    if (!empty($v['tickets'])) {
        $decoded = json_decode((string)$v['tickets'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $t) {
                $rows[] = ['info' => (string)($t['info'] ?? ''), 'url' => (string)($t['url'] ?? '')];
            }
        }
    }
    if (!$rows) { // 旧フィールドから移行
        $li = trim((string)($v['ticket_info'] ?? ''));
        $lu = trim((string)($v['ticket_url']  ?? ''));
        if ($li !== '' || $lu !== '') $rows[] = ['info' => $li, 'url' => $lu];
    }
    if (!$rows) $rows[] = ['info' => '', 'url' => '']; // 空1行
    return $rows;
}

/* ── POST 処理 ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act         = $_POST['_action']     ?? '';
    $date        = trim($_POST['event_date']  ?? '');
    $name        = trim($_POST['event_name']  ?? '');
    $description = trim($_POST['description']  ?? '');
    $venue       = trim($_POST['venue']        ?? '');
    $city        = trim($_POST['city']         ?? '');
    $open_time   = trim($_POST['open_time']    ?? '');
    $start_time  = trim($_POST['start_time']   ?? '');
    $notes       = trim($_POST['notes']        ?? '');
    $tickets     = collectTickets();
    $ticketsJson = $tickets ? json_encode($tickets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

    if ($act === 'add') {
        if (!$date || !$name || !$venue) {
            $error = '日付・公演名・会場は必須です';
        } else {
            $image = handleImageUpload($error);
            if (!$error) {
                db()->prepare(
                    'INSERT INTO melikret_live
                       (event_date, event_name, description, venue, city, open_time, start_time, tickets, notes, image_url)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                )->execute([$date, $name, $description, $venue, $city, $open_time, $start_time, $ticketsJson, $notes, $image ?? '']);
                header('Location: live.php?flash=added'); exit;
            }
        }
    } elseif ($act === 'edit') {
        $eid = (int)($_POST['id'] ?? 0);
        if (!$date || !$name || !$venue) {
            $error = '日付・公演名・会場は必須です';
        } else {
            // 既存画像を取得
            $cur = db()->prepare('SELECT image_url FROM melikret_live WHERE id=?');
            $cur->execute([$eid]);
            $oldImage = (string)($cur->fetchColumn() ?: '');

            $newImage = handleImageUpload($error);
            if (!$error) {
                $image = $oldImage;
                if ($newImage !== null) {            // 新しい画像をアップロード → 差し替え
                    deleteImage($oldImage);
                    $image = $newImage;
                } elseif (!empty($_POST['remove_image'])) { // 画像を削除にチェック
                    deleteImage($oldImage);
                    $image = '';
                }
                // tickets に一本化し、旧 ticket_url/ticket_info はクリア
                db()->prepare(
                    'UPDATE melikret_live SET
                       event_date=?, event_name=?, description=?, venue=?, city=?,
                       open_time=?, start_time=?, tickets=?, ticket_url=NULL, ticket_info=NULL, notes=?, image_url=?
                     WHERE id=?'
                )->execute([$date, $name, $description, $venue, $city, $open_time, $start_time, $ticketsJson, $notes, $image, $eid]);
                header('Location: live.php?flash=updated'); exit;
            }
        }
    } elseif ($act === 'delete') {
        $did = (int)($_POST['id'] ?? 0);
        $cur = db()->prepare('SELECT image_url FROM melikret_live WHERE id=?');
        $cur->execute([$did]);
        deleteImage((string)($cur->fetchColumn() ?: ''));
        db()->prepare('DELETE FROM melikret_live WHERE id=?')->execute([$did]);
        header('Location: live.php?flash=deleted'); exit;
    }
}

$flashMap = ['added' => '追加しました', 'updated' => '更新しました', 'deleted' => '削除しました'];
$flash = $flashMap[$_GET['flash'] ?? ''] ?? '';

/* ── 追加フォーム ───────────────────────────────── */
if ($action === 'add') {
    adminHead('ライブ追加', 'live');
    echo '<a class="back" href="live.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>ライブ追加</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    $v = ['event_date' => date('Y-m-d'), 'event_name' => '', 'description' => '', 'venue' => '', 'city' => '',
          'open_time' => '', 'start_time' => '', 'tickets' => '', 'notes' => '', 'image_url' => ''];
    renderLiveForm('add', $v);
    echo '</div>';
    adminFoot(); exit;
}

/* ── 編集フォーム ───────────────────────────────── */
if ($action === 'edit' && $id) {
    $stmt = db()->prepare('SELECT * FROM melikret_live WHERE id=?');
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if (!$v) { header('Location: live.php'); exit; }
    adminHead('ライブ編集', 'live');
    echo '<a class="back" href="live.php">← 一覧に戻る</a>';
    echo '<div class="card"><h2>ライブ編集</h2>';
    if ($error) echo '<div class="alert alert-ng">' . h($error) . '</div>';
    renderLiveForm('edit', $v, $id);
    echo '<hr style="border:none;border-top:1px solid #e5eff5;margin:28px 0">';
    echo '<h2 style="color:#c0392b;border-color:#f5c6cb">削除</h2>';
    echo '<form method="post" onsubmit="return confirm(\'このライブを削除しますか？\')">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="delete">';
    echo '<input type="hidden" name="id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-danger btn-sm">削除する</button>';
    echo '</form>';
    echo '</div>';
    adminFoot(); exit;
}

/* ── 一覧 ────────────────────────────────────────── */
adminHead('ライブ管理', 'live');
if ($flash) echo '<div class="alert alert-ok">' . h($flash) . '</div>';
echo '<div class="card">';
echo '<div class="top-bar"><h2 style="margin:0;border:none;padding:0">ライブ一覧</h2>';
echo '<a href="live.php?action=add" class="btn btn-primary btn-sm">＋ 追加</a></div>';

$rows = db()->query('SELECT * FROM melikret_live ORDER BY event_date ASC, id ASC')->fetchAll();
if (!$rows) {
    echo '<p style="color:#8a9faa;font-size:.9rem;padding:20px 0">まだライブがありません</p>';
} else {
    echo '<table><thead><tr><th>日付</th><th>公演名</th><th>会場</th><th>チケット</th><th>詳細</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td style="white-space:nowrap;color:#5a6f78">' . h(date('Y.m.d', strtotime($r['event_date']))) . '</td>';
        echo '<td>' . h(mb_strimwidth($r['event_name'], 0, 36, '…')) . '</td>';
        $place = $r['venue'] . ($r['city'] ? ' — ' . $r['city'] : '');
        echo '<td style="color:#5a6f78;font-size:.85rem">' . h($place) . '</td>';

        // チケット件数
        $tk = ticketRowsFrom($r);
        $tkCount = 0;
        foreach ($tk as $t) { if (trim($t['info']) !== '' || trim($t['url']) !== '') $tkCount++; }
        echo '<td>' . ($tkCount ? '<span class="badge badge-sale">' . $tkCount . '件</span>' : '<span style="color:#c5d2d9;font-size:.8rem">—</span>') . '</td>';

        $hasDetail = trim((string)($r['description'] ?? '')) !== '' || $tkCount > 0
                  || trim((string)($r['notes'] ?? '')) !== '' || trim((string)($r['image_url'] ?? '')) !== ''
                  || trim((string)($r['open_time'] ?? '')) !== '' || trim((string)($r['start_time'] ?? '')) !== '';
        echo '<td>' . ($hasDetail ? '<span class="badge badge-perf">あり</span>' : '<span style="color:#c5d2d9;font-size:.8rem">—</span>') . '</td>';
        echo '<td class="actions"><a href="live.php?action=edit&id=' . $r['id'] . '" class="btn btn-secondary btn-sm">編集</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';
adminFoot();

/* ── 時刻プルダウンの option（15分刻み・現在値を選択） ── */
function timeOptions(string $selected): string {
    $opts = [];
    for ($h = 0; $h < 24; $h++) {
        for ($m = 0; $m < 60; $m += 15) {
            $opts[] = sprintf('%02d:%02d', $h, $m);
        }
    }
    $out = '<option value="">（未設定）</option>';
    // 既存値が15分刻みに無い場合（旧データ等）はそのまま選択肢に追加
    if ($selected !== '' && !in_array($selected, $opts, true)) {
        $out .= '<option value="' . h($selected) . '" selected>' . h($selected) . '</option>';
    }
    foreach ($opts as $t) {
        $sel = ($t === $selected) ? ' selected' : '';
        $out .= '<option value="' . $t . '"' . $sel . '>' . $t . '</option>';
    }
    return $out;
}

/* ── チケット1行の HTML ────────────────────────── */
function ticketRowHtml(string $info = '', string $url = ''): string {
    return '<div class="ticket-row">'
        . '<input type="text" name="ticket_info[]" class="ticket-info-input" value="' . h($info) . '">'
        . '<input type="url" name="ticket_url[]" class="ticket-url-input" value="' . h($url) . '" placeholder="https://...">'
        . '<button type="button" class="ticket-del" title="削除">×</button>'
        . '</div>';
}

/* ── フォーム描画ヘルパー ──────────────────────── */
function renderLiveForm(string $act, array $v, int $id = 0): void {
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
    echo '<input type="hidden" name="_action" value="' . h($act) . '">';
    if ($id) echo '<input type="hidden" name="id" value="' . $id . '">';

    echo '<div class="form-group"><label>日付 <span style="color:#e74c3c">*</span></label><input type="date" name="event_date" value="' . h($v['event_date']) . '" required></div>';

    echo '<div class="form-group"><label>公演名（タイトル） <span style="color:#e74c3c">*</span></label><input type="text" name="event_name" value="' . h($v['event_name']) . '" required placeholder="例: nest30th → Anniversary “Dannie May × リュックと添い寝ごはん”"></div>';

    echo '<div class="form-group"><label>説明</label><textarea name="description" placeholder="例: Spotify O-nest30周年企画2マンライブ出演決定">' . h($v['description'] ?? '') . '</textarea><p class="hint">詳細ページに表示される紹介文。改行はそのまま反映されます。</p></div>';

    echo '<div class="form-row">';
    echo '<div class="form-group"><label>会場 <span style="color:#e74c3c">*</span></label><input type="text" name="venue" value="' . h($v['venue']) . '" required placeholder="例: Spotify O-nest"></div>';
    echo '<div class="form-group"><label>都市・都道府県</label><input type="text" name="city" value="' . h($v['city']) . '" placeholder="例: 東京"></div>';
    echo '</div>';

    echo '<div class="form-row">';
    echo '<div class="form-group"><label>OPEN 時刻</label><select name="open_time">' . timeOptions(trim((string)($v['open_time'] ?? ''))) . '</select></div>';
    echo '<div class="form-group"><label>START 時刻</label><select name="start_time">' . timeOptions(trim((string)($v['start_time'] ?? ''))) . '</select></div>';
    echo '</div>';

    // チケット（複数・最大5件）
    echo '<div class="form-group"><label>チケット（最大' . MAX_TICKETS . '件）</label>';
    echo '<div class="ticket-head"><span>チケット名</span><span>購入URL</span></div>';
    echo '<div id="ticketList">';
    foreach (ticketRowsFrom($v) as $row) {
        echo ticketRowHtml($row['info'], $row['url']);
    }
    echo '</div>';
    echo '<button type="button" class="btn btn-secondary btn-sm" id="ticketAdd">＋ チケットを追加</button>';
    echo '<p class="hint">プラスボタンで最大' . MAX_TICKETS . '件まで追加できます。詳細ページに一覧表示されます。</p>';
    echo '</div>';

    echo '<div class="form-group"><label>備考</label><textarea name="notes" placeholder="例: 一般発売日：3月20日(金)">' . h($v['notes'] ?? '') . '</textarea></div>';

    // 画像
    echo '<div class="form-group"><label>画像添付</label>';
    $img = trim((string)($v['image_url'] ?? ''));
    if ($img !== '') {
        echo '<div style="margin-bottom:10px"><img src="../' . h($img) . '" alt="" style="max-width:240px;max-height:180px;border-radius:8px;border:1px solid #d0e6ef"></div>';
        echo '<label style="display:flex;align-items:center;gap:8px;font-weight:500;color:#c0392b;cursor:pointer"><input type="checkbox" name="remove_image" value="1" style="width:auto"> 現在の画像を削除する</label>';
        echo '<p class="hint">差し替える場合は下から新しい画像を選択してください。</p>';
    }
    echo '<input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;padding:8px 0">';
    echo '<p class="hint">JPEG / PNG / GIF / WebP・6MBまで。詳細ページの先頭に表示されます。</p>';
    echo '</div>';

    $label = $act === 'add' ? '追加する' : '保存する';
    echo '<button type="submit" class="btn btn-primary">' . $label . '</button>';
    echo '</form>';

    // チケット行の追加・削除スクリプト（nowdoc: PHP は中の $ を展開しない）
    $max = MAX_TICKETS;
    echo <<<HTML
<script>
(function(){
  var list = document.getElementById('ticketList');
  var addBtn = document.getElementById('ticketAdd');
  var MAX = {$max};
  function rowHtml(){
    return '<div class="ticket-row">'
      + '<input type="text" name="ticket_info[]" class="ticket-info-input">'
      + '<input type="url" name="ticket_url[]" class="ticket-url-input" placeholder="https://...">'
      + '<button type="button" class="ticket-del" title="削除">×</button>'
      + '</div>';
  }
  function refresh(){
    addBtn.style.display = list.querySelectorAll('.ticket-row').length >= MAX ? 'none' : '';
  }
  addBtn.addEventListener('click', function(){
    if (list.querySelectorAll('.ticket-row').length >= MAX) return;
    list.insertAdjacentHTML('beforeend', rowHtml());
    refresh();
  });
  list.addEventListener('click', function(e){
    if (!e.target.classList.contains('ticket-del')) return;
    var rows = list.querySelectorAll('.ticket-row');
    if (rows.length > 1) {
      e.target.closest('.ticket-row').remove();
    } else {
      e.target.closest('.ticket-row').querySelectorAll('input').forEach(function(i){ i.value=''; });
    }
    refresh();
  });
  refresh();
})();
</script>
HTML;
}
