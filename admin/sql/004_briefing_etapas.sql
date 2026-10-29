ALTER TABLE briefings
  ADD COLUMN status ENUM('rascunho','enviado') NOT NULL DEFAULT 'rascunho' AFTER customer_id,
  ADD COLUMN step TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER status,
  ADD COLUMN submitted_at DATETIME NULL AFTER step;

CREATE TABLE briefing_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  field VARCHAR(40) NOT NULL,
  orig_name VARCHAR(200) NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY k_bf_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
