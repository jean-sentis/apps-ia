<?php
/**
 * Migration Remontée — Table centrale sur le site parent pour les erreurs IA
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Migration_Remontee {

    public static function run() {
        if (!is_multisite()) {
            return;
        }
        $current = get_current_blog_id();
        switch_to_blog(1);
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'lmd_';
        $table = $prefix . 'remontee_ai_errors';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            restore_current_blog();
            return;
        }
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id_origin bigint(20) NOT NULL,
            site_name varchar(255) DEFAULT '',
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
            KEY site_created (site_id_origin, created_at)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        restore_current_blog();
    }
}
