<?php
require '_inc.php';

if (!empty($_SESSION['admin_ok'])) {
    header('Location: news.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_ok'] = true;
        session_regenerate_id(true);
        header('Location: news.php');
        exit;
    }
    $error = 'パスワードが違います';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ログイン – melikret CMS</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f0f7fa;color:#20313a;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#fff;border-radius:12px;border:1px solid #d0e6ef;padding:40px 48px;width:100%;max-width:380px;box-shadow:0 4px 24px rgba(32,49,58,.10)}
h1{font-size:1.1rem;letter-spacing:.22em;color:#3a6567;margin-bottom:32px;text-align:center;font-weight:700}
label{display:block;font-size:.82rem;font-weight:600;color:#5a6f78;margin-bottom:5px}
input[type=password]{width:100%;padding:10px 14px;border:1px solid #b8d4df;border-radius:6px;font-size:.95rem;margin-bottom:20px;font-family:inherit;transition:border-color .2s}
input[type=password]:focus{outline:none;border-color:#5f9ea0;box-shadow:0 0 0 3px rgba(95,158,160,.14)}
button{width:100%;padding:11px;background:#5f9ea0;color:#fff;border:none;border-radius:6px;font-size:.95rem;font-weight:600;cursor:pointer;letter-spacing:.06em;transition:background .2s}
button:hover{background:#3a6567}
.error{background:#fdecea;border:1px solid #f5c6cb;color:#c0392b;padding:10px 14px;border-radius:6px;margin-bottom:18px;font-size:.88rem}
</style>
</head>
<body>
<div class="box">
  <h1>melikret CMS</h1>
  <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= csrf() ?>">
    <label for="pw">パスワード</label>
    <input type="password" id="pw" name="password" autofocus autocomplete="current-password">
    <button type="submit">ログイン</button>
  </form>
</div>
</body>
</html>
