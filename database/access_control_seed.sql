SET FOREIGN_KEY_CHECKS=0;

INSERT INTO `permissions` (`key`, `title`, `group`, `created_at`, `updated_at`) VALUES
('users.manage', 'مدیریت کاربران و دسترسی‌ها', 'مدیریت', NOW(), NOW()),
('base-info.view', 'مشاهده اطلاعات پایه', 'اطلاعات پایه', NOW(), NOW()),
('base-info.manage', 'مدیریت اطلاعات پایه', 'اطلاعات پایه', NOW(), NOW()),
('commerce.view', 'مشاهده بازرگانی', 'بازرگانی', NOW(), NOW()),
('commerce.manage', 'مدیریت بازرگانی', 'بازرگانی', NOW(), NOW()),
('accounting.view', 'مشاهده حسابداری', 'مالی', NOW(), NOW()),
('accounting.manage', 'مدیریت حسابداری', 'مالی', NOW(), NOW()),
('inventory.view', 'مشاهده انبار پیشرفته', 'انبار', NOW(), NOW()),
('inventory.manage', 'مدیریت انبار پیشرفته', 'انبار', NOW(), NOW()),
('settings.manage', 'مدیریت تنظیمات شرکت و شماره‌گذاری', 'تنظیمات', NOW(), NOW()),
('fiscal-years.manage', 'مدیریت و بستن سال مالی', 'مالی', NOW(), NOW()),
('projects.view', 'مشاهده پروژه‌ها', 'پروژه‌ها', NOW(), NOW()),
('projects.manage', 'مدیریت پروژه‌ها', 'پروژه‌ها', NOW(), NOW()),
('employees.view', 'مشاهده پرسنل', 'پرسنل', NOW(), NOW()),
('employees.manage', 'مدیریت پرسنل', 'پرسنل', NOW(), NOW()),
('worklogs.view', 'مشاهده کارکرد', 'کارکرد', NOW(), NOW()),
('worklogs.manage', 'مدیریت کارکرد', 'کارکرد', NOW(), NOW()),
('salaries.view', 'مشاهده حقوق', 'حقوق', NOW(), NOW()),
('salaries.manage', 'مدیریت حقوق و پرداخت‌ها', 'حقوق', NOW(), NOW()),
('warehouse.view', 'مشاهده انبار', 'انبار', NOW(), NOW()),
('warehouse.manage', 'مدیریت انبار', 'انبار', NOW(), NOW()),
('financial.view', 'مشاهده مالی', 'مالی', NOW(), NOW()),
('financial.manage', 'مدیریت مالی', 'مالی', NOW(), NOW()),
('reports.view', 'مشاهده گزارش‌ها', 'گزارش‌ها', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `group` = VALUES(`group`),
  `updated_at` = NOW();

INSERT INTO `roles` (`name`, `title`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
('admin', 'مدیر سیستم', 'دسترسی کامل به همه بخش‌ها', 1, NOW(), NOW()),
('commercial_manager', 'مدیر بازرگانی', 'مدیریت اشخاص، کالاها و فاکتورهای خرید و فروش', 0, NOW(), NOW()),
('accounting_manager', 'مدیر مالی', 'مدیریت حسابداری، کدینگ، اسناد و سال مالی', 0, NOW(), NOW()),
('hr_manager', 'مدیر منابع انسانی', 'مدیریت پرسنل و کارکرد', 0, NOW(), NOW()),
('payroll_viewer', 'مشاهده‌گر حقوق', 'مشاهده حقوق کارمندهای مجاز', 0, NOW(), NOW()),
('worklog_manager', 'مدیر کارکرد', 'ثبت و مدیریت کارکرد کارمندهای مجاز', 0, NOW(), NOW()),
('warehouse_manager', 'مدیر انبار', 'مدیریت انبار', 0, NOW(), NOW()),
('accountant', 'حسابدار', 'مدیریت مالی و مشاهده حقوق', 0, NOW(), NOW()),
('report_viewer', 'مشاهده‌گر گزارش‌ها', 'مشاهده گزارش‌های مدیریتی', 0, NOW(), NOW())
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
