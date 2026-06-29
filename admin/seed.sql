SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `melikret_news` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `news_date`  DATE        NOT NULL,
  `category`   VARCHAR(50) NOT NULL DEFAULT 'NEWS',
  `body`       TEXT        NOT NULL,
  `created_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `melikret_live` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `event_date`  DATE        NOT NULL,
  `event_name`  VARCHAR(500) NOT NULL,
  `venue`       VARCHAR(500) NOT NULL,
  `city`        VARCHAR(200) DEFAULT NULL,
  `ticket_url`  VARCHAR(1000) DEFAULT NULL,
  `status`      ENUM('on_sale','sold_out','performance','ended') NOT NULL DEFAULT 'on_sale',
  `created_at`  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `melikret_news` (`news_date`, `category`, `body`) VALUES
('2026-05-13', 'RELEASE', '18th digital single「ネガ・リセット！」を各種配信サービスにてリリースしました。'),
('2026-02-04', 'RELEASE', '1st mini album「1ヨクトの眠り姫」(全8曲) をリリースしました。'),
('2026-01-01', 'LIVE',    '「メリクレット 2nd Tour 2026」全国ツアー開催決定。チケット一般発売中。'),
('2025-12-17', 'RELEASE', '「イヴと凍花の国」(札幌ホワイトイルミネーション コラボ) を配信開始しました。');

INSERT INTO `melikret_live` (`event_date`, `event_name`, `venue`, `city`, `ticket_url`, `status`) VALUES
('2026-06-27', 'MiMiNOKOROCK FES JAPAN in 吉祥寺 2026', '吉祥寺CLUB SEATA', '東京', '', 'on_sale'),
('2026-07-04', '見放題大阪2026', 'BIGCAT', '大阪', '', 'on_sale'),
('2026-07-05', '見放題名古屋2026', 'ダイアモンドホール', '愛知', '', 'on_sale'),
('2026-07-19', 'JOIN ALIVE 2026', 'いわみざわ公園', '北海道', '', 'performance'),
('2026-07-24', 'Parallel Echoes vol.1 (Supported by Date fm SOUND GENIC)', 'LIVE HOUSE enn 2nd', '', '', 'on_sale'),
('2026-08-01', 'ORCALAND TOUR 2026「マジで魔法をかけにいく」', '札幌 近松', '北海道', '', 'on_sale'),
('2026-08-02', '明くる夜の羊 2026 RELEASE TOUR', '札幌 近松', '北海道', '', 'on_sale'),
('2026-09-22', 'TOKYO CALLING 2026', '下北沢シャングリラ', '東京', '', 'on_sale'),
('2026-09-26', 'メリクレット 2nd Tour 2026', 'PLANT', '', '', 'on_sale'),
('2026-10-10', 'メリクレット 2nd Tour 2026', 'PLANT', '', '', 'on_sale'),
('2026-10-25', 'メリクレット 2nd Tour 2026', 'LIVE HOUSE enn 3rd', '', '', 'on_sale'),
('2026-11-03', 'メリクレット 2nd Tour 2026', 'sound space RIZIN''', '香川', '', 'on_sale'),
('2026-11-08', 'メリクレット 2nd Tour 2026', 'LIVE SQUARE 2nd LINE', '', '', 'on_sale'),
('2026-11-23', 'メリクレット 2nd Tour 2026', 'LIVE HOUSE OP''s', '福岡', '', 'on_sale'),
('2026-12-06', 'メリクレット 2nd Tour 2026', 'Veats Shibuya', '東京', '', 'on_sale');
