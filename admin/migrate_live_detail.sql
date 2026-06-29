SET NAMES utf8mb4;

ALTER TABLE `melikret_live`
  ADD COLUMN `description` TEXT         NULL AFTER `event_name`,
  ADD COLUMN `open_time`   VARCHAR(50)  NULL AFTER `city`,
  ADD COLUMN `start_time`  VARCHAR(50)  NULL AFTER `open_time`,
  ADD COLUMN `ticket_info` TEXT         NULL AFTER `ticket_url`,
  ADD COLUMN `notes`       TEXT         NULL AFTER `ticket_info`,
  ADD COLUMN `image_url`   VARCHAR(500) NULL AFTER `notes`;
