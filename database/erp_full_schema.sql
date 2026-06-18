SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL DEFAULT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `start_date` DATE NULL DEFAULT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `status` VARCHAR(255) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(255) NOT NULL,
  `last_name` VARCHAR(255) NOT NULL,
  `national_code` VARCHAR(255) NULL DEFAULT NULL,
  `phone` VARCHAR(255) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `position` VARCHAR(255) NOT NULL,
  `salary` DECIMAL(15,2) NULL DEFAULT NULL,
  `hourly_rate` DECIMAL(15,2) NULL DEFAULT NULL,
  `address` TEXT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_national_code_unique` (`national_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('income','expense') NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `description` TEXT NULL,
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `attachment` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `financial_transactions_project_id_foreign` (`project_id`),
  CONSTRAINT `financial_transactions_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `work_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `hours` DECIMAL(8,2) NOT NULL,
  `description` TEXT NULL,
  `hourly_rate` DECIMAL(15,2) NOT NULL,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_logs_employee_id_foreign` (`employee_id`),
  KEY `work_logs_project_id_foreign` (`project_id`),
  CONSTRAINT `work_logs_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_logs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `salaries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `year` INT NOT NULL,
  `month` INT NOT NULL,
  `total_hours` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `hourly_rate` DECIMAL(15,2) NOT NULL,
  `base_salary` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `overtime_hours` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `overtime_rate` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `overtime_salary` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `bonus` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `deduction` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `net_salary` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `advance_payment` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `final_salary` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `status` ENUM('draft','calculated','paid','partial') NOT NULL DEFAULT 'draft',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salaries_employee_id_year_month_unique` (`employee_id`,`year`,`month`),
  CONSTRAINT `salaries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('cash','bank','card') NOT NULL DEFAULT 'cash',
  `reference_number` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_salary_id_foreign` (`salary_id`),
  KEY `payments_employee_id_foreign` (`employee_id`),
  CONSTRAINT `payments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('debit','credit') NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `transaction_date` DATE NOT NULL,
  `description` TEXT NOT NULL,
  `reference_type` ENUM('salary','payment','advance','bonus','deduction','other') NOT NULL DEFAULT 'other',
  `reference_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_transactions_employee_id_foreign` (`employee_id`),
  KEY `employee_transactions_type_index` (`type`),
  KEY `employee_transactions_transaction_date_index` (`transaction_date`),
  KEY `employee_transactions_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  CONSTRAINT `employee_transactions_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `group` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_key_unique` (`key`),
  KEY `permissions_group_index` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permission_role` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_user` (
  `role_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`user_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_employee_access` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `can_view_work_logs` TINYINT(1) NOT NULL DEFAULT 0,
  `can_manage_work_logs` TINYINT(1) NOT NULL DEFAULT 0,
  `can_view_salaries` TINYINT(1) NOT NULL DEFAULT 0,
  `can_manage_salaries` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_employee_access_user_id_employee_id_unique` (`user_id`,`employee_id`),
  KEY `user_employee_access_employee_id_foreign` (`employee_id`),
  CONSTRAINT `user_employee_access_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_employee_access_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `parties` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NOT NULL,
  `detail_code` VARCHAR(255) NOT NULL,
  `kind` ENUM('person','company') NOT NULL DEFAULT 'person',
  `name` VARCHAR(255) NOT NULL,
  `economic_code` VARCHAR(255) NULL,
  `national_id` VARCHAR(255) NULL,
  `phone` VARCHAR(255) NULL,
  `mobile` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `postal_code` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parties_code_unique` (`code`),
  UNIQUE KEY `parties_detail_code_unique` (`detail_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `party_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `party_party_type` (
  `party_id` BIGINT UNSIGNED NOT NULL,
  `party_type_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`party_id`,`party_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `measurement_units` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `measurement_units_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(255) NOT NULL,
  `type` ENUM('product','service') NOT NULL DEFAULT 'product',
  `name` VARCHAR(255) NOT NULL,
  `measurement_unit_id` BIGINT UNSIGNED NULL,
  `category` VARCHAR(255) NULL,
  `sale_price` DECIMAL(15,2) NULL,
  `purchase_price` DECIMAL(15,2) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `items_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fiscal_years` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `jalali_year` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `closed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fiscal_years_jalali_year_unique` (`jalali_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chart_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NULL,
  `level` ENUM('group','ledger','subsidiary','detail') NOT NULL,
  `code` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `nature` ENUM('debit','credit','neutral') NOT NULL DEFAULT 'neutral',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_accounts_code_unique` (`code`),
  KEY `chart_accounts_parent_id_index` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounting_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` BIGINT UNSIGNED NULL,
  `number` VARCHAR(255) NOT NULL,
  `document_date` DATE NOT NULL,
  `type` ENUM('manual','sale_invoice','purchase_invoice','payment','receipt','inventory','closing') NOT NULL DEFAULT 'manual',
  `status` ENUM('draft','posted','void') NOT NULL DEFAULT 'draft',
  `source_type` VARCHAR(255) NULL,
  `source_id` BIGINT UNSIGNED NULL,
  `description` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `posted_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounting_documents_number_unique` (`number`),
  KEY `accounting_documents_source_index` (`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounting_document_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accounting_document_id` BIGINT UNSIGNED NOT NULL,
  `chart_account_id` BIGINT UNSIGNED NOT NULL,
  `party_id` BIGINT UNSIGNED NULL,
  `description` TEXT NULL,
  `debit` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `credit` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` BIGINT UNSIGNED NULL,
  `direction` ENUM('sale','purchase') NOT NULL,
  `document_type` ENUM('proforma','invoice') NOT NULL DEFAULT 'invoice',
  `number` VARCHAR(255) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `party_id` BIGINT UNSIGNED NOT NULL,
  `warehouse_id` BIGINT UNSIGNED NULL,
  `converted_from_id` BIGINT UNSIGNED NULL,
  `status` ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft',
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `tax_rate` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `description` TEXT NULL,
  `accounting_document_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `confirmed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_number_unique` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NULL,
  `quantity` DECIMAL(15,3) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `tax_rate` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fiscal_year_id` BIGINT UNSIGNED NULL,
  `number` VARCHAR(255) NOT NULL,
  `type` ENUM('receipt','issue','transfer') NOT NULL,
  `document_date` DATE NOT NULL,
  `document_time` TIME NULL,
  `warehouse_id` BIGINT UNSIGNED NULL,
  `target_warehouse_id` BIGINT UNSIGNED NULL,
  `source_type` VARCHAR(255) NULL,
  `source_id` BIGINT UNSIGNED NULL,
  `entry_mode` ENUM('manual','automatic') NOT NULL DEFAULT 'manual',
  `status` ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'draft',
  `description` TEXT NULL,
  `accounting_document_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `confirmed_by` BIGINT UNSIGNED NULL,
  `confirmed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_documents_number_unique` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_document_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_document_id` BIGINT UNSIGNED NOT NULL,
  `item_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(15,3) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `numbering_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_key` VARCHAR(255) NOT NULL,
  `prefix` VARCHAR(255) NULL,
  `next_number` INT UNSIGNED NOT NULL DEFAULT 1,
  `padding` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numbering_settings_document_key_unique` (`document_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(255) NULL,
  `registration_number` VARCHAR(255) NULL,
  `economic_code` VARCHAR(255) NULL,
  `national_id` VARCHAR(255) NULL,
  `postal_code` VARCHAR(255) NULL,
  `phone` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `default_vat_rate` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `party_types` (`name`, `title`, `created_at`, `updated_at`) VALUES
('customer', 'ظ…ط´طھط±غŒ', NOW(), NOW()),
('vendor', 'ظپط±ظˆط´ظ†ط¯ظ‡', NOW(), NOW()),
('colleague', 'ظ‡ظ…ع©ط§ط±', NOW(), NOW()),
('marketer', 'ط¨ط§ط²ط§ط±غŒط§ط¨', NOW(), NOW()),
('contractor', 'ظ¾غŒظ…ط§ظ†ع©ط§ط±', NOW(), NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `updated_at` = NOW();

SET @invoice_lines_tax_rate_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'invoice_lines'
    AND COLUMN_NAME = 'tax_rate'
);
SET @invoice_lines_tax_rate_sql := IF(
  @invoice_lines_tax_rate_exists = 0,
  'ALTER TABLE `invoice_lines` ADD COLUMN `tax_rate` DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER `discount_amount`',
  'SELECT 1'
);
PREPARE invoice_lines_tax_rate_stmt FROM @invoice_lines_tax_rate_sql;
EXECUTE invoice_lines_tax_rate_stmt;
DEALLOCATE PREPARE invoice_lines_tax_rate_stmt;

ALTER TABLE `items` MODIFY `sale_price` DECIMAL(15,2) NULL;
ALTER TABLE `items` MODIFY `purchase_price` DECIMAL(15,2) NULL;

SET @invoices_warehouse_id_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'invoices'
    AND COLUMN_NAME = 'warehouse_id'
);
SET @invoices_warehouse_id_sql := IF(
  @invoices_warehouse_id_exists = 0,
  'ALTER TABLE `invoices` ADD COLUMN `warehouse_id` BIGINT UNSIGNED NULL AFTER `party_id`',
  'SELECT 1'
);
PREPARE invoices_warehouse_id_stmt FROM @invoices_warehouse_id_sql;
EXECUTE invoices_warehouse_id_stmt;
DEALLOCATE PREPARE invoices_warehouse_id_stmt;

SET @inventory_documents_document_time_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'inventory_documents'
    AND COLUMN_NAME = 'document_time'
);
SET @inventory_documents_document_time_sql := IF(
  @inventory_documents_document_time_exists = 0,
  'ALTER TABLE `inventory_documents` ADD COLUMN `document_time` TIME NULL AFTER `document_date`',
  'SELECT 1'
);
PREPARE inventory_documents_document_time_stmt FROM @inventory_documents_document_time_sql;
EXECUTE inventory_documents_document_time_stmt;
DEALLOCATE PREPARE inventory_documents_document_time_stmt;

SET @inventory_documents_entry_mode_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'inventory_documents'
    AND COLUMN_NAME = 'entry_mode'
);
SET @inventory_documents_entry_mode_sql := IF(
  @inventory_documents_entry_mode_exists = 0,
  'ALTER TABLE `inventory_documents` ADD COLUMN `entry_mode` ENUM(''manual'',''automatic'') NOT NULL DEFAULT ''manual'' AFTER `source_id`',
  'SELECT 1'
);
PREPARE inventory_documents_entry_mode_stmt FROM @inventory_documents_entry_mode_sql;
EXECUTE inventory_documents_entry_mode_stmt;
DEALLOCATE PREPARE inventory_documents_entry_mode_stmt;

SET @inventory_documents_confirmed_by_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'inventory_documents'
    AND COLUMN_NAME = 'confirmed_by'
);
SET @inventory_documents_confirmed_by_sql := IF(
  @inventory_documents_confirmed_by_exists = 0,
  'ALTER TABLE `inventory_documents` ADD COLUMN `confirmed_by` BIGINT UNSIGNED NULL AFTER `created_by`',
  'SELECT 1'
);
PREPARE inventory_documents_confirmed_by_stmt FROM @inventory_documents_confirmed_by_sql;
EXECUTE inventory_documents_confirmed_by_stmt;
DEALLOCATE PREPARE inventory_documents_confirmed_by_stmt;


SET @company_registration_number_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'company_settings'
    AND COLUMN_NAME = 'registration_number'
);
SET @company_registration_number_sql := IF(
  @company_registration_number_exists = 0,
  'ALTER TABLE `company_settings` ADD COLUMN `registration_number` VARCHAR(255) NULL AFTER `company_name`',
  'SELECT 1'
);
PREPARE company_registration_number_stmt FROM @company_registration_number_sql;
EXECUTE company_registration_number_stmt;
DEALLOCATE PREPARE company_registration_number_stmt;

SET @company_postal_code_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'company_settings'
    AND COLUMN_NAME = 'postal_code'
);
SET @company_postal_code_sql := IF(
  @company_postal_code_exists = 0,
  'ALTER TABLE `company_settings` ADD COLUMN `postal_code` VARCHAR(255) NULL AFTER `national_id`',
  'SELECT 1'
);
PREPARE company_postal_code_stmt FROM @company_postal_code_sql;
EXECUTE company_postal_code_stmt;
DEALLOCATE PREPARE company_postal_code_stmt;

INSERT INTO `measurement_units` (`code`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
('PCS', 'ط¹ط¯ط¯', 1, NOW(), NOW()),
('KG', 'ع©غŒظ„ظˆع¯ط±ظ…', 1, NOW(), NOW()),
('M', 'ظ…طھط±', 1, NOW(), NOW()),
('HOUR', 'ط³ط§ط¹طھ', 1, NOW(), NOW()),
('SERVICE', 'ط®ط¯ظ…طھ', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = VALUES(`is_active`), `updated_at` = NOW();

INSERT INTO `numbering_settings` (`document_key`, `prefix`, `next_number`, `padding`, `created_at`, `updated_at`) VALUES
('party', 'P-', 1, 5, NOW(), NOW()),
('party_detail', 'D-', 1, 5, NOW(), NOW()),
('item', 'I-', 1, 5, NOW(), NOW()),
('sale_proforma', 'SP-', 1, 5, NOW(), NOW()),
('sale_invoice', 'SI-', 1, 5, NOW(), NOW()),
('purchase_invoice', 'PI-', 1, 5, NOW(), NOW()),
('inventory_receipt', 'IR-', 1, 5, NOW(), NOW()),
('inventory_issue', 'II-', 1, 5, NOW(), NOW()),
('inventory_transfer', 'IT-', 1, 5, NOW(), NOW()),
('accounting_document', 'ACC-', 1, 5, NOW(), NOW())
ON DUPLICATE KEY UPDATE `prefix` = VALUES(`prefix`), `updated_at` = NOW();

INSERT INTO `chart_accounts` (`code`, `title`, `level`, `nature`, `is_active`, `is_system`, `created_at`, `updated_at`) VALUES
('1', 'ط¯ط§ط±ط§غŒغŒâ€Œظ‡ط§', 'group', 'debit', 1, 1, NOW(), NOW()),
('11', 'ط¯ط§ط±ط§غŒغŒâ€Œظ‡ط§غŒ ط¬ط§ط±غŒ', 'ledger', 'debit', 1, 1, NOW(), NOW()),
('1101', 'ط­ط³ط§ط¨â€Œظ‡ط§غŒ ط¯ط±غŒط§ظپطھظ†غŒ طھط¬ط§ط±غŒ', 'subsidiary', 'debit', 1, 1, NOW(), NOW()),
('1102', 'ط§ط¹طھط¨ط§ط± ظ…ط§ظ„غŒط§طھ ط§ط±ط²ط´ ط§ظپط²ظˆط¯ظ‡ ط®ط±غŒط¯', 'subsidiary', 'debit', 1, 1, NOW(), NOW()),
('1103', 'ظ…ظˆط¬ظˆط¯غŒ ع©ط§ظ„ط§', 'subsidiary', 'debit', 1, 1, NOW(), NOW()),
('12', 'ظ†ظ‚ط¯ ظˆ ط¨ط§ظ†ع©', 'ledger', 'debit', 1, 1, NOW(), NOW()),
('1201', 'طµظ†ط¯ظˆظ‚', 'subsidiary', 'debit', 1, 1, NOW(), NOW()),
('1202', 'ط¨ط§ظ†ع©', 'subsidiary', 'debit', 1, 1, NOW(), NOW()),
('2', 'ط¨ط¯ظ‡غŒâ€Œظ‡ط§', 'group', 'credit', 1, 1, NOW(), NOW()),
('21', 'ط¨ط¯ظ‡غŒâ€Œظ‡ط§غŒ ط¬ط§ط±غŒ', 'ledger', 'credit', 1, 1, NOW(), NOW()),
('2101', 'ط­ط³ط§ط¨â€Œظ‡ط§غŒ ظ¾ط±ط¯ط§ط®طھظ†غŒ طھط¬ط§ط±غŒ', 'subsidiary', 'credit', 1, 1, NOW(), NOW()),
('2102', 'ظ…ط§ظ„غŒط§طھ ط§ط±ط²ط´ ط§ظپط²ظˆط¯ظ‡ ظپط±ظˆط´', 'subsidiary', 'credit', 1, 1, NOW(), NOW()),
('3', 'ط­ظ‚ظˆظ‚ ظ…ط§ظ„ع©ط§ظ†ظ‡', 'group', 'credit', 1, 1, NOW(), NOW()),
('31', 'ط³ط±ظ…ط§غŒظ‡ ظˆ ط³ظˆط¯ ط§ظ†ط¨ط§ط´طھظ‡', 'ledger', 'credit', 1, 1, NOW(), NOW()),
('3101', 'ط³ط±ظ…ط§غŒظ‡', 'subsidiary', 'credit', 1, 1, NOW(), NOW()),
('4', 'ط¯ط±ط¢ظ…ط¯ظ‡ط§', 'group', 'credit', 1, 1, NOW(), NOW()),
('41', 'ط¯ط±ط¢ظ…ط¯ ط¹ظ…ظ„غŒط§طھغŒ', 'ledger', 'credit', 1, 1, NOW(), NOW()),
('4101', 'ظپط±ظˆط´ ع©ط§ظ„ط§ ظˆ ط®ط¯ظ…ط§طھ', 'subsidiary', 'credit', 1, 1, NOW(), NOW()),
('5', 'ظ‡ط²غŒظ†ظ‡â€Œظ‡ط§ ظˆ ط¨ظ‡ط§غŒ طھظ…ط§ظ… ط´ط¯ظ‡', 'group', 'debit', 1, 1, NOW(), NOW()),
('51', 'ط®ط±غŒط¯ ظˆ ط¨ظ‡ط§غŒ طھظ…ط§ظ… ط´ط¯ظ‡', 'ledger', 'debit', 1, 1, NOW(), NOW()),
('5101', 'ط®ط±غŒط¯ ع©ط§ظ„ط§ ظˆ ط®ط¯ظ…ط§طھ', 'subsidiary', 'debit', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `level` = VALUES(`level`), `nature` = VALUES(`nature`), `updated_at` = NOW();

SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=0;

INSERT INTO `permissions` (`key`, `title`, `group`, `created_at`, `updated_at`) VALUES
('users.manage', 'ظ…ط¯غŒط±غŒطھ ع©ط§ط±ط¨ط±ط§ظ† ظˆ ط¯ط³طھط±ط³غŒâ€Œظ‡ط§', 'ظ…ط¯غŒط±غŒطھ', NOW(), NOW()),
('base-info.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ط§ط·ظ„ط§ط¹ط§طھ ظ¾ط§غŒظ‡', 'ط§ط·ظ„ط§ط¹ط§طھ ظ¾ط§غŒظ‡', NOW(), NOW()),
('base-info.manage', 'ظ…ط¯غŒط±غŒطھ ط§ط·ظ„ط§ط¹ط§طھ ظ¾ط§غŒظ‡', 'ط§ط·ظ„ط§ط¹ط§طھ ظ¾ط§غŒظ‡', NOW(), NOW()),
('commerce.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ط¨ط§ط²ط±ع¯ط§ظ†غŒ', 'ط¨ط§ط²ط±ع¯ط§ظ†غŒ', NOW(), NOW()),
('commerce.manage', 'ظ…ط¯غŒط±غŒطھ ط¨ط§ط²ط±ع¯ط§ظ†غŒ', 'ط¨ط§ط²ط±ع¯ط§ظ†غŒ', NOW(), NOW()),
('accounting.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ط­ط³ط§ط¨ط¯ط§ط±غŒ', 'ظ…ط§ظ„غŒ', NOW(), NOW()),
('accounting.manage', 'ظ…ط¯غŒط±غŒطھ ط­ط³ط§ط¨ط¯ط§ط±غŒ', 'ظ…ط§ظ„غŒ', NOW(), NOW()),
('inventory.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ط§ظ†ط¨ط§ط± ظ¾غŒط´ط±ظپطھظ‡', 'ط§ظ†ط¨ط§ط±', NOW(), NOW()),
('inventory.manage', 'ظ…ط¯غŒط±غŒطھ ط§ظ†ط¨ط§ط± ظ¾غŒط´ط±ظپطھظ‡', 'ط§ظ†ط¨ط§ط±', NOW(), NOW()),
('settings.manage', 'ظ…ط¯غŒط±غŒطھ طھظ†ط¸غŒظ…ط§طھ ط´ط±ع©طھ ظˆ ط´ظ…ط§ط±ظ‡â€Œع¯ط°ط§ط±غŒ', 'طھظ†ط¸غŒظ…ط§طھ', NOW(), NOW()),
('fiscal-years.manage', 'ظ…ط¯غŒط±غŒطھ ظˆ ط¨ط³طھظ† ط³ط§ظ„ ظ…ط§ظ„غŒ', 'ظ…ط§ظ„غŒ', NOW(), NOW()),
('projects.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ظ¾ط±ظˆعکظ‡â€Œظ‡ط§', 'ظ¾ط±ظˆعکظ‡â€Œظ‡ط§', NOW(), NOW()),
('projects.manage', 'ظ…ط¯غŒط±غŒطھ ظ¾ط±ظˆعکظ‡â€Œظ‡ط§', 'ظ¾ط±ظˆعکظ‡â€Œظ‡ط§', NOW(), NOW()),
('employees.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ظ¾ط±ط³ظ†ظ„', 'ظ¾ط±ط³ظ†ظ„', NOW(), NOW()),
('employees.manage', 'ظ…ط¯غŒط±غŒطھ ظ¾ط±ط³ظ†ظ„', 'ظ¾ط±ط³ظ†ظ„', NOW(), NOW()),
('worklogs.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ع©ط§ط±ع©ط±ط¯', 'ع©ط§ط±ع©ط±ط¯', NOW(), NOW()),
('worklogs.manage', 'ظ…ط¯غŒط±غŒطھ ع©ط§ط±ع©ط±ط¯', 'ع©ط§ط±ع©ط±ط¯', NOW(), NOW()),
('salaries.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ط­ظ‚ظˆظ‚', 'ط­ظ‚ظˆظ‚', NOW(), NOW()),
('salaries.manage', 'ظ…ط¯غŒط±غŒطھ ط­ظ‚ظˆظ‚ ظˆ ظ¾ط±ط¯ط§ط®طھâ€Œظ‡ط§', 'ط­ظ‚ظˆظ‚', NOW(), NOW()),
('warehouse.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ط§ظ†ط¨ط§ط±', 'ط§ظ†ط¨ط§ط±', NOW(), NOW()),
('warehouse.manage', 'ظ…ط¯غŒط±غŒطھ ط§ظ†ط¨ط§ط±', 'ط§ظ†ط¨ط§ط±', NOW(), NOW()),
('financial.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ظ…ط§ظ„غŒ', 'ظ…ط§ظ„غŒ', NOW(), NOW()),
('financial.manage', 'ظ…ط¯غŒط±غŒطھ ظ…ط§ظ„غŒ', 'ظ…ط§ظ„غŒ', NOW(), NOW()),
('reports.view', 'ظ…ط´ط§ظ‡ط¯ظ‡ ع¯ط²ط§ط±ط´â€Œظ‡ط§', 'ع¯ط²ط§ط±ط´â€Œظ‡ط§', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `group` = VALUES(`group`),
  `updated_at` = NOW();

INSERT INTO `roles` (`name`, `title`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
('admin', 'ظ…ط¯غŒط± ط³غŒط³طھظ…', 'ط¯ط³طھط±ط³غŒ ع©ط§ظ…ظ„ ط¨ظ‡ ظ‡ظ…ظ‡ ط¨ط®ط´â€Œظ‡ط§', 1, NOW(), NOW()),
('commercial_manager', 'ظ…ط¯غŒط± ط¨ط§ط²ط±ع¯ط§ظ†غŒ', 'ظ…ط¯غŒط±غŒطھ ط§ط´ط®ط§طµطŒ ع©ط§ظ„ط§ظ‡ط§ ظˆ ظپط§ع©طھظˆط±ظ‡ط§غŒ ط®ط±غŒط¯ ظˆ ظپط±ظˆط´', 0, NOW(), NOW()),
('accounting_manager', 'ظ…ط¯غŒط± ظ…ط§ظ„غŒ', 'ظ…ط¯غŒط±غŒطھ ط­ط³ط§ط¨ط¯ط§ط±غŒطŒ ع©ط¯غŒظ†ع¯طŒ ط§ط³ظ†ط§ط¯ ظˆ ط³ط§ظ„ ظ…ط§ظ„غŒ', 0, NOW(), NOW()),
('hr_manager', 'ظ…ط¯غŒط± ظ…ظ†ط§ط¨ط¹ ط§ظ†ط³ط§ظ†غŒ', 'ظ…ط¯غŒط±غŒطھ ظ¾ط±ط³ظ†ظ„ ظˆ ع©ط§ط±ع©ط±ط¯', 0, NOW(), NOW()),
('payroll_viewer', 'ظ…ط´ط§ظ‡ط¯ظ‡â€Œع¯ط± ط­ظ‚ظˆظ‚', 'ظ…ط´ط§ظ‡ط¯ظ‡ ط­ظ‚ظˆظ‚ ع©ط§ط±ظ…ظ†ط¯ظ‡ط§غŒ ظ…ط¬ط§ط²', 0, NOW(), NOW()),
('worklog_manager', 'ظ…ط¯غŒط± ع©ط§ط±ع©ط±ط¯', 'ط«ط¨طھ ظˆ ظ…ط¯غŒط±غŒطھ ع©ط§ط±ع©ط±ط¯ ع©ط§ط±ظ…ظ†ط¯ظ‡ط§غŒ ظ…ط¬ط§ط²', 0, NOW(), NOW()),
('warehouse_manager', 'ظ…ط¯غŒط± ط§ظ†ط¨ط§ط±', 'ظ…ط¯غŒط±غŒطھ ط§ظ†ط¨ط§ط±', 0, NOW(), NOW()),
('accountant', 'ط­ط³ط§ط¨ط¯ط§ط±', 'ظ…ط¯غŒط±غŒطھ ظ…ط§ظ„غŒ ظˆ ظ…ط´ط§ظ‡ط¯ظ‡ ط­ظ‚ظˆظ‚', 0, NOW(), NOW()),
('report_viewer', 'ظ…ط´ط§ظ‡ط¯ظ‡â€Œع¯ط± ع¯ط²ط§ط±ط´â€Œظ‡ط§', 'ظ…ط´ط§ظ‡ط¯ظ‡ ع¯ط²ط§ط±ط´â€Œظ‡ط§غŒ ظ…ط¯غŒط±غŒطھغŒ', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `is_system` = VALUES(`is_system`),
  `updated_at` = NOW();

DELETE FROM `permission_role`
WHERE `role_id` IN (
  SELECT `id` FROM `roles`
  WHERE `name` IN ('admin', 'commercial_manager', 'accounting_manager', 'hr_manager', 'payroll_viewer', 'worklog_manager', 'warehouse_manager', 'accountant', 'report_viewer')
);

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'admin';

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'commercial_manager'
WHERE p.`key` IN ('base-info.view', 'base-info.manage', 'commerce.view', 'commerce.manage', 'inventory.view');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'accounting_manager'
WHERE p.`key` IN ('accounting.view', 'accounting.manage', 'financial.view', 'financial.manage', 'reports.view', 'fiscal-years.manage');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'hr_manager'
WHERE p.`key` IN ('employees.view', 'employees.manage', 'worklogs.view', 'worklogs.manage');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'payroll_viewer'
WHERE p.`key` IN ('salaries.view');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'worklog_manager'
WHERE p.`key` IN ('worklogs.view', 'worklogs.manage');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'warehouse_manager'
WHERE p.`key` IN ('warehouse.view', 'warehouse.manage', 'inventory.view', 'inventory.manage');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'accountant'
WHERE p.`key` IN ('accounting.view', 'accounting.manage', 'financial.view', 'financial.manage', 'salaries.view');

INSERT IGNORE INTO `permission_role` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
JOIN `roles` r ON r.`name` = 'report_viewer'
WHERE p.`key` IN ('reports.view');

INSERT IGNORE INTO `role_user` (`role_id`, `user_id`)
SELECT r.`id`, u.`id`
FROM `roles` r
JOIN `users` u
WHERE r.`name` = 'admin'
  AND u.`id` = (SELECT MIN(`id`) FROM `users`)
  AND NOT EXISTS (SELECT 1 FROM `role_user` ru WHERE ru.`user_id` = u.`id`);

INSERT IGNORE INTO `role_user` (`role_id`, `user_id`)
SELECT r.`id`, u.`id`
FROM `roles` r
JOIN `users` u
WHERE r.`name` = 'admin'
  AND u.`email` = 'mahdi@aale.ir';

SET FOREIGN_KEY_CHECKS=1;
