CREATE TABLE IF NOT EXISTS `PREFIX_pf_forms` (
    `id_form`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(255) NOT NULL,
    `slug`             VARCHAR(255) NOT NULL,
    `template`         LONGTEXT NOT NULL,
    `custom_css`       TEXT NOT NULL DEFAULT '',
    `success_message`  TEXT NOT NULL DEFAULT '',
    `status`           ENUM('active','draft') NOT NULL DEFAULT 'draft',
    `captcha_provider` ENUM('none','recaptcha_v2','recaptcha_v3','turnstile') NOT NULL DEFAULT 'none',
    `retention_days`   INT(11) UNSIGNED NULL DEFAULT NULL,
    `date_add`         DATETIME NOT NULL,
    `date_upd`         DATETIME NOT NULL,
    PRIMARY KEY (`id_form`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_submissions` (
    `id_submission` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`       INT(11) UNSIGNED NOT NULL,
    `data`          JSON NOT NULL,
    `ip_address`    VARCHAR(45) NOT NULL DEFAULT '',
    `date_add`      DATETIME NOT NULL,
    PRIMARY KEY (`id_submission`),
    KEY `id_form` (`id_form`),
    KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_webhooks` (
    `id_webhook`       INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`          INT(11) UNSIGNED NOT NULL,
    `name`             VARCHAR(255) NOT NULL,
    `url`              TEXT NOT NULL,
    `method`           ENUM('POST','GET','PUT') NOT NULL DEFAULT 'POST',
    `headers`          JSON NOT NULL,
    `field_map`        JSON NULL DEFAULT NULL,
    `retry_count`      TINYINT(3) UNSIGNED NOT NULL DEFAULT 3,
    `timeout_seconds`  TINYINT(3) UNSIGNED NOT NULL DEFAULT 10,
    `active`           TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_webhook`),
    KEY `id_form` (`id_form`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_webhook_log` (
    `id_log`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_webhook`    INT(11) UNSIGNED NOT NULL,
    `id_submission` INT(11) UNSIGNED NOT NULL,
    `attempt`       TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
    `http_status`   SMALLINT(5) NULL DEFAULT NULL,
    `response_body` TEXT NULL DEFAULT NULL,
    `success`       TINYINT(1) NOT NULL DEFAULT 0,
    `date_add`      DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `id_webhook` (`id_webhook`),
    KEY `id_submission` (`id_submission`),
    KEY `webhook_date` (`id_webhook`, `date_add`),
    KEY `date_add` (`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_conditions` (
    `id_condition_group` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`            INT(11) UNSIGNED NOT NULL,
    `target_field`       VARCHAR(255) NOT NULL,
    `action`             ENUM('show','hide') NOT NULL DEFAULT 'show',
    `logic`              ENUM('AND','OR') NOT NULL DEFAULT 'AND',
    `rules`              JSON NOT NULL,
    PRIMARY KEY (`id_condition_group`),
    KEY `id_form` (`id_form`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_email_routes` (
    `id_route`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_form`            INT(11) UNSIGNED NOT NULL,
    `type`               ENUM('admin','confirmation') NOT NULL,
    `enabled`            TINYINT(1) NOT NULL DEFAULT 1,
    `notify_addresses`   JSON NOT NULL,
    `reply_to`           VARCHAR(255) NULL DEFAULT NULL,
    `from_address`       VARCHAR(500) NOT NULL DEFAULT '',
    `additional_headers` TEXT NOT NULL DEFAULT '',
    `subject`            VARCHAR(500) NOT NULL DEFAULT '',
    `body`               LONGTEXT NOT NULL,
    `routing_rules`      JSON NULL DEFAULT NULL,
    PRIMARY KEY (`id_route`),
    KEY `id_form` (`id_form`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_pf_settings` (
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `PREFIX_pf_settings` (`setting_key`, `setting_value`) VALUES
    ('recaptcha_v2_site_key', ''),
    ('recaptcha_v2_secret_key', ''),
    ('recaptcha_v3_site_key', ''),
    ('recaptcha_v3_secret_key', ''),
    ('turnstile_site_key', ''),
    ('turnstile_secret_key', ''),
    ('default_retention_days', '');
