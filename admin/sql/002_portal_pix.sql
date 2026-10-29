ALTER TABLE customers ADD COLUMN portal_token VARCHAR(40) NULL AFTER lead_id;

UPDATE customers SET portal_token = SHA1(CONCAT(id, RAND(), UUID())) WHERE portal_token IS NULL;

ALTER TABLE customers ADD UNIQUE KEY uq_cust_token (portal_token);

CREATE TABLE pix_charges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  mp_payment_id VARCHAR(40) NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  qr_code TEXT NULL,
  qr_base64 MEDIUMTEXT NULL,
  status ENUM('pendente','pago','expirado') NOT NULL DEFAULT 'pendente',
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pix_mp (mp_payment_id),
  KEY idx_pix_customer (customer_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
