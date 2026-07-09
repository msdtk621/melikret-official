<?php
require '_inc.php';
requireLogin();

$done = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── テーブル作成 ───────────────────────────────
    $sqls = [
        'ニューステーブル作成' => "
            CREATE TABLE IF NOT EXISTS `melikret_news` (
              `id`         INT AUTO_INCREMENT PRIMARY KEY,
              `news_date`  DATE        NOT NULL,
              `category`   VARCHAR(50) NOT NULL DEFAULT 'NEWS',
              `title`      VARCHAR(300) DEFAULT NULL,
              `body`       TEXT        NOT NULL,
              `description` TEXT       DEFAULT NULL,
              `image_url`  VARCHAR(500) DEFAULT NULL,
              `link_url`   VARCHAR(1000) DEFAULT NULL,
              `link_label` VARCHAR(100) DEFAULT NULL,
              `created_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'ライブテーブル作成' => "
            CREATE TABLE IF NOT EXISTS `melikret_live` (
              `id`          INT AUTO_INCREMENT PRIMARY KEY,
              `event_date`  DATE        NOT NULL,
              `event_name`  VARCHAR(500) NOT NULL,
              `description` TEXT         DEFAULT NULL,
              `venue`       VARCHAR(500) NOT NULL,
              `city`        VARCHAR(200) DEFAULT NULL,
              `open_time`   VARCHAR(50)  DEFAULT NULL,
              `start_time`  VARCHAR(50)  DEFAULT NULL,
              `ticket_url`  VARCHAR(1000) DEFAULT NULL,
              `ticket_info` TEXT         DEFAULT NULL,
              `tickets`     TEXT         DEFAULT NULL,
              `notes`       TEXT         DEFAULT NULL,
              `image_url`   VARCHAR(500) DEFAULT NULL,
              `status`      ENUM('on_sale','sold_out','performance','ended') NOT NULL DEFAULT 'on_sale',
              `created_at`  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
              `updated_at`  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'ディスコグラフィーテーブル作成' => "
            CREATE TABLE IF NOT EXISTS `melikret_discography` (
              `id`           INT AUTO_INCREMENT PRIMARY KEY,
              `release_date` DATE          NOT NULL,
              `title`        VARCHAR(300)  NOT NULL,
              `type`         VARCHAR(100)  NOT NULL DEFAULT 'Digital Single',
              `jacket_url`   VARCHAR(1000) NOT NULL,
              `link_url`     VARCHAR(1000) NOT NULL,
              `sort_order`   INT           NOT NULL DEFAULT 0,
              `created_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
              `updated_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'MOVIEテーブル作成' => "
            CREATE TABLE IF NOT EXISTS `melikret_movie` (
              `id`          INT AUTO_INCREMENT PRIMARY KEY,
              `youtube_id`  VARCHAR(50)   NOT NULL,
              `title`       VARCHAR(300)  NOT NULL,
              `sort_order`  INT           NOT NULL DEFAULT 0,
              `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
              `updated_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'TOP売出項目テーブル作成' => "
            CREATE TABLE IF NOT EXISTS `melikret_feature` (
              `id`           INT AUTO_INCREMENT PRIMARY KEY,
              `enabled`      TINYINT(1)    NOT NULL DEFAULT 1,
              `image_url`    VARCHAR(1000) NOT NULL,
              `label`        VARCHAR(100)  DEFAULT NULL,
              `title`        VARCHAR(500)  NOT NULL,
              `link_url`     VARCHAR(1000) NOT NULL,
              `button_label` VARCHAR(100)  NOT NULL DEFAULT 'ツアー詳細へ',
              `updated_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
    ];

    foreach ($sqls as $label => $sql) {
        try {
            db()->exec($sql);
            $done[] = $label . ' — OK';
        } catch (PDOException $e) {
            $errors[] = $label . ' — エラー: ' . $e->getMessage();
        }
    }

    // ── ニュース初期データ（既存があれば skip） ─────
    if (isset($_POST['seed_news'])) {
        $cnt = (int)db()->query('SELECT COUNT(*) FROM melikret_news')->fetchColumn();
        if ($cnt === 0) {
            $newsData = [
                ['2026-05-13', 'RELEASE', '18th digital single「ネガ・リセット！」を各種配信サービスにてリリースしました。'],
                ['2026-02-04', 'RELEASE', '1st mini album「1ヨクトの眠り姫」(全8曲) をリリースしました。'],
                ['2026-01-01', 'LIVE',    '「メリクレット 2nd Tour 2026」全国ツアー開催決定。チケット一般発売中。'],
                ['2025-12-17', 'RELEASE', '「イヴと凍花の国」(札幌ホワイトイルミネーション コラボ) を配信開始しました。'],
            ];
            $st = db()->prepare('INSERT INTO melikret_news (news_date, category, body) VALUES (?,?,?)');
            foreach ($newsData as $row) $st->execute($row);
            $done[] = 'ニュース初期データ 4件 を投入しました';
        } else {
            $done[] = 'ニュースはすでにデータがあるためスキップ';
        }
    }

    // ── ライブ初期データ ────────────────────────────
    if (isset($_POST['seed_live'])) {
        $cnt = (int)db()->query('SELECT COUNT(*) FROM melikret_live')->fetchColumn();
        if ($cnt === 0) {
            $liveData = [
                ['2026-06-27', 'MiMiNOKOROCK FES JAPAN in 吉祥寺 2026', '吉祥寺CLUB SEATA', '東京',   '', 'on_sale'],
                ['2026-07-04', '見放題大阪2026',                           'BIGCAT',            '大阪',   '', 'on_sale'],
                ['2026-07-05', '見放題名古屋2026',                         'ダイアモンドホール', '愛知',  '', 'on_sale'],
                ['2026-07-19', 'JOIN ALIVE 2026',                          'いわみざわ公園',    '北海道', '', 'performance'],
                ['2026-07-24', 'Parallel Echoes vol.1 (Supported by Date fm SOUND GENIC)', 'LIVE HOUSE enn 2nd', '', '', 'on_sale'],
                ['2026-08-01', 'ORCALAND TOUR 2026「マジで魔法をかけにいく」', '札幌 近松', '北海道', '', 'on_sale'],
                ['2026-08-02', '明くる夜の羊 2026 RELEASE TOUR',            '札幌 近松',         '北海道', '', 'on_sale'],
                ['2026-09-22', 'TOKYO CALLING 2026',                        '下北沢シャングリラ', '東京', '', 'on_sale'],
                ['2026-09-26', 'メリクレット 2nd Tour 2026',                 'PLANT',             '',      '', 'on_sale'],
                ['2026-10-10', 'メリクレット 2nd Tour 2026',                 'PLANT',             '',      '', 'on_sale'],
                ['2026-10-25', 'メリクレット 2nd Tour 2026',                 'LIVE HOUSE enn 3rd', '',     '', 'on_sale'],
                ['2026-11-03', 'メリクレット 2nd Tour 2026',                 'sound space RIZIN\'', '香川', '', 'on_sale'],
                ['2026-11-08', 'メリクレット 2nd Tour 2026',                 'LIVE SQUARE 2nd LINE', '',   '', 'on_sale'],
                ['2026-11-23', 'メリクレット 2nd Tour 2026',                 'LIVE HOUSE OP\'s',  '福岡', '', 'on_sale'],
                ['2026-12-06', 'メリクレット 2nd Tour 2026',                 'Veats Shibuya',     '東京', '', 'on_sale'],
            ];
            $st = db()->prepare('INSERT INTO melikret_live (event_date, event_name, venue, city, ticket_url, status) VALUES (?,?,?,?,?,?)');
            foreach ($liveData as $row) $st->execute($row);
            $done[] = 'ライブ初期データ ' . count($liveData) . '件 を投入しました';
        } else {
            $done[] = 'ライブはすでにデータがあるためスキップ';
        }
    }

    // ── ディスコグラフィー初期データ ────────────────
    if (isset($_POST['seed_disco'])) {
        $cnt = (int)db()->query('SELECT COUNT(*) FROM melikret_discography')->fetchColumn();
        if ($cnt === 0) {
            $discoData = [
                ['2026-07-08', 'あしたのごちそう', 'Digital Single',
                 'https://is1-ssl.mzstatic.com/image/thumb/Music221/v4/b5/8b/00/b58b00f6-d748-c7cd-937e-67b7a22e7786/bigup14473388.jpg/600x600bb.jpg',
                 'https://melikret.lnk.to/TommorowontheTable', 70],
                ['2026-05-13', 'ネガ・リセット！', 'Digital Single',
                 'https://is1-ssl.mzstatic.com/image/thumb/Music221/v4/5e/34/64/5e346450-2a16-5d37-e15a-48501cdda069/ticket.vyzqjdod.jpg/600x600bb.jpg',
                 'https://melikret.lnk.to/NegaReset', 60],
                ['2026-02-04', '1ヨクトの眠り姫', '1st mini album',
                 'https://is1-ssl.mzstatic.com/image/thumb/Music211/v4/36/ad/d6/36add669-e470-3213-d933-1e7a3be55e6f/bigup14304664.jpg/600x600bb.jpg',
                 'https://melikret.lnk.to/1stminialbum', 50],
                ['2025-12-17', 'イヴと凍花の国', 'Digital Single',
                 'https://is1-ssl.mzstatic.com/image/thumb/Music221/v4/e7/c1/fd/e7c1fd57-3838-cf1d-81d5-75153a11c1ac/bigup14254698.jpg/600x600bb.jpg',
                 'https://melikret.lnk.to/EveandtheLandofFrozenFlower', 40],
                ['2025-10-29', '偽物', 'Digital Single',
                 'https://is1-ssl.mzstatic.com/image/thumb/Music221/v4/69/58/e9/6958e98d-4c9d-2b03-f87a-1ceb48baf6e3/bigup14208777.jpg/600x600bb.jpg',
                 'https://melikret.lnk.to/Imitation', 30],
                ['2025-07-30', '透水', 'Digital Single',
                 'https://is1-ssl.mzstatic.com/image/thumb/Music211/v4/0e/3f/0d/0e3f0d92-debe-65d6-456b-ba5bdfc3f55e/bigup14118823.jpg/600x600bb.jpg',
                 'https://melikret.lnk.to/Tousui', 20],
                ['2025-06-25', 'メリーバッドエンド', 'Digital Single',
                 'https://is1-ssl.mzstatic.com/image/thumb/Music211/v4/3d/33/ba/3d33bab6-7f7c-b3cb-ff23-4ef9851f6b26/bigup14092476.jpg/600x600bb.jpg',
                 'https://melikret.lnk.to/MerryBadEnd', 10],
            ];
            $st = db()->prepare('INSERT INTO melikret_discography (release_date, title, type, jacket_url, link_url, sort_order) VALUES (?,?,?,?,?,?)');
            foreach ($discoData as $row) $st->execute($row);
            $done[] = 'ディスコグラフィー初期データ ' . count($discoData) . '件 を投入しました';
        } else {
            $done[] = 'ディスコグラフィーはすでにデータがあるためスキップ';
        }
    }

    // ── MOVIE初期データ ──────────────────────────────
    if (isset($_POST['seed_movie'])) {
        $cnt = (int)db()->query('SELECT COUNT(*) FROM melikret_movie')->fetchColumn();
        if ($cnt === 0) {
            $movieData = [
                ['U1-YQfccF8Q', 'ネガ・リセット！ - メリクレット (Music Video)', 20],
                ['0q0IOK8r7h8', 'セミロング - メリクレット (Music Video)', 10],
            ];
            $st = db()->prepare('INSERT INTO melikret_movie (youtube_id, title, sort_order) VALUES (?,?,?)');
            foreach ($movieData as $row) $st->execute($row);
            $done[] = 'MOVIE初期データ ' . count($movieData) . '件 を投入しました';
        } else {
            $done[] = 'MOVIEはすでにデータがあるためスキップ';
        }
    }

    // ── TOP売出項目 初期データ ────────────────────────
    if (isset($_POST['seed_feature'])) {
        $cnt = (int)db()->query('SELECT COUNT(*) FROM melikret_feature')->fetchColumn();
        if ($cnt === 0) {
            db()->prepare(
                'INSERT INTO melikret_feature (enabled, image_url, label, title, link_url, button_label) VALUES (?,?,?,?,?,?)'
            )->execute([1, 'images/tour.jpg', 'TOUR', "メリクレット\n2nd Tour 2026", 'https://melikret.lnk.to/2ndLiveTour2026', 'ツアー詳細へ']);
            $done[] = 'TOP売出項目 初期データを投入しました';
        } else {
            $done[] = 'TOP売出項目はすでにデータがあるためスキップ';
        }
    }
}

adminHead('セットアップ', '');
echo '<div class="card"><h2>初期セットアップ</h2>';
echo '<p style="font-size:.9rem;color:#5a6f78;margin-bottom:20px">テーブル作成と初期データの投入を行います。<strong>初回のみ</strong>実行してください。</p>';

if ($done || $errors) {
    foreach ($done   as $m) echo '<div class="alert alert-ok">' . h($m) . '</div>';
    foreach ($errors as $m) echo '<div class="alert alert-ng">' . h($m) . '</div>';
}

echo '<form method="post">';
echo '<input type="hidden" name="_csrf" value="' . csrf() . '">';
echo '<div class="form-group" style="display:flex;gap:16px;align-items:center">';
echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="seed_news" checked> ニュース初期データを投入する</label>';
echo '</div>';
echo '<div class="form-group" style="display:flex;gap:16px;align-items:center">';
echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="seed_live" checked> ライブ初期データを投入する（現在のHP掲載分）</label>';
echo '</div>';
echo '<div class="form-group" style="display:flex;gap:16px;align-items:center">';
echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="seed_disco" checked> ディスコグラフィー初期データを投入する（現在のHP掲載分）</label>';
echo '</div>';
echo '<div class="form-group" style="display:flex;gap:16px;align-items:center">';
echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="seed_movie" checked> MOVIE初期データを投入する（現在のHP掲載分）</label>';
echo '</div>';
echo '<div class="form-group" style="display:flex;gap:16px;align-items:center">';
echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="seed_feature" checked> TOP売出項目 初期データを投入する（現在のツアーバナー）</label>';
echo '</div>';
echo '<button type="submit" class="btn btn-primary">セットアップ実行</button>';
echo '</form>';
echo '</div>';
adminFoot();
