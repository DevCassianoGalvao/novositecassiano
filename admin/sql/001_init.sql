CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(20) NOT NULL,
  name VARCHAR(60) NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_plan_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  whatsapp VARCHAR(20) NOT NULL,
  email VARCHAR(160) NOT NULL,
  plan_slug VARCHAR(20) NOT NULL,
  status ENUM('novo','contato','negociando','fechado','perdido') NOT NULL DEFAULT 'novo',
  source_page VARCHAR(300) NULL,
  utm TEXT NULL,
  notes TEXT NULL,
  customer_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_lead_status (status),
  KEY idx_lead_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  business VARCHAR(120) NULL,
  email VARCHAR(160) NOT NULL,
  whatsapp VARCHAR(20) NOT NULL,
  site_url VARCHAR(255) NULL,
  plan_id INT UNSIGNED NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  status ENUM('pendente','ativo','atraso','suspenso','cancelado') NOT NULL DEFAULT 'pendente',
  billing_mode ENUM('mercadopago','manual') NOT NULL DEFAULT 'mercadopago',
  lead_id INT UNSIGNED NULL,
  mp_preapproval_id VARCHAR(64) NULL,
  mp_init_point VARCHAR(500) NULL,
  mp_status VARCHAR(20) NULL,
  mp_charged_qty INT UNSIGNED NOT NULL DEFAULT 0,
  next_due_date DATE NULL,
  started_at DATETIME NULL,
  suspended_at DATETIME NULL,
  canceled_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_cust_mp (mp_preapproval_id),
  KEY idx_cust_status (status),
  KEY idx_cust_due (next_due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  mp_ref VARCHAR(64) NULL,
  amount_cents INT UNSIGNED NOT NULL,
  status ENUM('pago','falhou','pendente','estornado') NOT NULL,
  method VARCHAR(20) NOT NULL DEFAULT 'mercadopago',
  reference_date DATE NULL,
  paid_at DATETIME NULL,
  note VARCHAR(200) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pay_ref (mp_ref),
  KEY idx_pay_customer (customer_id),
  KEY idx_pay_paid (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  kind VARCHAR(30) NOT NULL,
  ref_date DATE NOT NULL,
  to_email VARCHAR(160) NOT NULL,
  ok TINYINT(1) NOT NULL DEFAULT 0,
  error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_notif (customer_id, kind, ref_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NULL,
  lead_id INT UNSIGNED NULL,
  kind VARCHAR(30) NOT NULL,
  message VARCHAR(300) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_act_customer (customer_id),
  KEY idx_act_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(160) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_login_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webhook_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic VARCHAR(60) NULL,
  data_id VARCHAR(64) NULL,
  result VARCHAR(20) NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_wh_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO plans (slug, name, price_cents, active, sort, created_at) VALUES
  ('essencial', 'Plano Essencial', 6700, 1, 1, NOW()),
  ('completo',  'Plano Completo',  9700, 1, 2, NOW());

INSERT INTO settings (k, v, updated_at) VALUES
  ('tolerance_days', '1', NOW()),
  ('reminder_days', '7,3,0', NOW()),
  ('admin_whatsapp', '5522997560717', NOW()),
  ('notify_customer', '1', NOW()),
  ('notify_admin', '1', NOW());
