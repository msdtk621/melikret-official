-- TOP売出項目を複数件運用にする（表示順を追加）。2026-11-11 本番適用済み
ALTER TABLE `melikret_feature`
  ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `enabled`;
