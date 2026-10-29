ALTER TABLE customers ADD COLUMN cycle_months TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER price_cents;
ALTER TABLE leads ADD COLUMN cycle_months TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER plan_slug;
INSERT IGNORE INTO settings (k, v, updated_at) VALUES ('disc_3', '10', NOW()), ('disc_6', '15', NOW()), ('disc_12', '20', NOW());
