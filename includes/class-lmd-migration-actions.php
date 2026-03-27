<?php
/**
 * Migration colonne 3 Actions - CP settings, formules, délégation
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Migration_Actions {

    public static function run() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'lmd_';

        $sql_cp = "CREATE TABLE IF NOT EXISTS {$prefix}cp_settings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            cp_email varchar(255) DEFAULT NULL,
            cp_signature longtext,
            cp_copy_emails longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY site_user (site_id, user_id)
        ) $charset;";

        $sql_formules = "CREATE TABLE IF NOT EXISTS {$prefix}formules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            content longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_user (site_id, user_id)
        ) $charset;";

        $sql_recipients = "CREATE TABLE IF NOT EXISTS {$prefix}delegation_recipients (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL,
            email varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_email (site_id, email)
        ) $charset;";

        $sql_tokens = "CREATE TABLE IF NOT EXISTS {$prefix}delegation_tokens (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            estimation_id bigint(20) unsigned NOT NULL,
            token varchar(64) NOT NULL,
            email varchar(255) NOT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY estimation_id (estimation_id)
        ) $charset;";

        $sql_ai_errors = "CREATE TABLE IF NOT EXISTS {$prefix}ai_error_reports (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            estimation_id bigint(20) unsigned NOT NULL,
            client_name varchar(255) DEFAULT '',
            description longtext,
            ai_summary text,
            ai_estimate_low decimal(12,2) DEFAULT NULL,
            ai_estimate_high decimal(12,2) DEFAULT NULL,
            ai_analysis_json longtext,
            user_explanation text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_created (site_id, created_at)
        ) $charset;";

        $sql_api_usage = "CREATE TABLE IF NOT EXISTS {$prefix}api_usage (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            estimation_id bigint(20) unsigned DEFAULT NULL,
            api_name varchar(50) NOT NULL,
            units int(11) NOT NULL DEFAULT 1,
            cost_usd decimal(10,6) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_api_month (site_id, api_name, created_at),
            KEY estimation_id (estimation_id)
        ) $charset;";

        if (get_option('lmd_free_estimations_granted', '') === '') {
            update_option('lmd_free_estimations_granted', 20);
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_cp);
        dbDelta($sql_formules);
        dbDelta($sql_recipients);
        dbDelta($sql_tokens);
        dbDelta($sql_ai_errors);
        dbDelta($sql_api_usage);
    }
}
