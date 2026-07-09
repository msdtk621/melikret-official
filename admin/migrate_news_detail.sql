-- News詳細機能（見出し・説明・画像・リンク）追加
ALTER TABLE `melikret_news`
  ADD COLUMN `title`       VARCHAR(300)  DEFAULT NULL AFTER `category`,
  ADD COLUMN `description` TEXT          DEFAULT NULL AFTER `body`,
  ADD COLUMN `image_url`   VARCHAR(500)  DEFAULT NULL AFTER `description`,
  ADD COLUMN `link_url`    VARCHAR(1000) DEFAULT NULL AFTER `image_url`,
  ADD COLUMN `link_label`  VARCHAR(100)  DEFAULT NULL AFTER `link_url`;
