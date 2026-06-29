SET NAMES utf8mb4;

-- 複数チケット（最大5件）を JSON 配列 [{info,url}, ...] で保持するカラム
ALTER TABLE `melikret_live`
  ADD COLUMN `tickets` TEXT NULL AFTER `ticket_info`;
