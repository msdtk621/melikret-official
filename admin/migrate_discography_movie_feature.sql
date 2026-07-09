-- ディスコグラフィー
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MOVIE
CREATE TABLE IF NOT EXISTS `melikret_movie` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `youtube_id`  VARCHAR(50)   NOT NULL,
  `title`       VARCHAR(300)  NOT NULL,
  `sort_order`  INT           NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TOP売出項目（ツアーバナー等・単一レコード運用）
CREATE TABLE IF NOT EXISTS `melikret_feature` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `enabled`      TINYINT(1)    NOT NULL DEFAULT 1,
  `image_url`    VARCHAR(1000) NOT NULL,
  `label`        VARCHAR(100)  DEFAULT NULL,
  `title`        VARCHAR(500)  NOT NULL,
  `link_url`     VARCHAR(1000) NOT NULL,
  `button_label` VARCHAR(100)  NOT NULL DEFAULT 'ツアー詳細へ',
  `updated_at`   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
