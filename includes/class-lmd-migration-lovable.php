<?php
/**
 * Migrations Lovable - colonnes avis1/avis2, delegation_draft, etc.
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Migration_Lovable {

    public static function run() {
        global $wpdb;
        $table = $wpdb->prefix . 'lmd_estimations';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        $cols = $wpdb->get_col("DESCRIBE $table");
        $to_add = [
            'avis1_estimate_low' => "ALTER TABLE $table ADD COLUMN avis1_estimate_low decimal(12,2) DEFAULT NULL",
            'avis1_estimate_high' => "ALTER TABLE $table ADD COLUMN avis1_estimate_high decimal(12,2) DEFAULT NULL",
            'avis1_prix_reserve' => "ALTER TABLE $table ADD COLUMN avis1_prix_reserve decimal(12,2) DEFAULT NULL",
            'avis2_estimate_low' => "ALTER TABLE $table ADD COLUMN avis2_estimate_low decimal(12,2) DEFAULT NULL",
            'avis2_estimate_high' => "ALTER TABLE $table ADD COLUMN avis2_estimate_high decimal(12,2) DEFAULT NULL",
            'avis2_prix_reserve' => "ALTER TABLE $table ADD COLUMN avis2_prix_reserve decimal(12,2) DEFAULT NULL",
            'avis1_titre' => "ALTER TABLE $table ADD COLUMN avis1_titre varchar(255) DEFAULT NULL",
            'avis1_dimension' => "ALTER TABLE $table ADD COLUMN avis1_dimension varchar(255) DEFAULT NULL",
            'avis2_titre' => "ALTER TABLE $table ADD COLUMN avis2_titre varchar(255) DEFAULT NULL",
            'avis2_dimension' => "ALTER TABLE $table ADD COLUMN avis2_dimension varchar(255) DEFAULT NULL",
            'delegation_draft' => "ALTER TABLE $table ADD COLUMN delegation_draft text",
            'delegation_email' => "ALTER TABLE $table ADD COLUMN delegation_email varchar(255) DEFAULT NULL",
            'client_civility' => "ALTER TABLE $table ADD COLUMN client_civility varchar(20) DEFAULT NULL",
            'client_first_name' => "ALTER TABLE $table ADD COLUMN client_first_name varchar(255) DEFAULT NULL",
            'client_postal_code' => "ALTER TABLE $table ADD COLUMN client_postal_code varchar(10) DEFAULT NULL",
            'client_commune' => "ALTER TABLE $table ADD COLUMN client_commune varchar(255) DEFAULT NULL",
            'reponse_subject' => "ALTER TABLE $table ADD COLUMN reponse_subject varchar(500) DEFAULT NULL",
            'reponse_body' => "ALTER TABLE $table ADD COLUMN reponse_body longtext",
            'delegation_subject' => "ALTER TABLE $table ADD COLUMN delegation_subject varchar(500) DEFAULT NULL",
            'delegation_body' => "ALTER TABLE $table ADD COLUMN delegation_body longtext",
            'reponse_sent_at' => "ALTER TABLE $table ADD COLUMN reponse_sent_at datetime DEFAULT NULL",
            'reponse_questions_selected' => "ALTER TABLE $table ADD COLUMN reponse_questions_selected longtext",
            'first_viewed_at' => "ALTER TABLE $table ADD COLUMN first_viewed_at datetime DEFAULT NULL",
            'ai_error_reported_at' => "ALTER TABLE $table ADD COLUMN ai_error_reported_at datetime DEFAULT NULL",
        ];
        foreach ($to_add as $col => $sql) {
            if (!in_array($col, $cols, true)) {
                $wpdb->query($sql);
            }
        }

        $et_table = $wpdb->prefix . 'lmd_estimation_tags';
        if ($wpdb->get_var("SHOW TABLES LIKE '$et_table'") === $et_table) {
            $et_cols = $wpdb->get_col("DESCRIBE $et_table");
            if (!in_array('modified_by_avis', $et_cols, true)) {
                $wpdb->query("ALTER TABLE $et_table ADD COLUMN modified_by_avis tinyint(1) DEFAULT NULL");
            }
        }

        $err_table = $wpdb->prefix . 'lmd_ai_error_reports';
        if ($wpdb->get_var("SHOW TABLES LIKE '$err_table'") === $err_table) {
            $err_cols = $wpdb->get_col("DESCRIBE $err_table");
            if (!in_array('user_explanation', $err_cols, true)) {
                $wpdb->query("ALTER TABLE $err_table ADD COLUMN user_explanation text");
            }
        }

        $tags_table = $wpdb->prefix . 'lmd_tags';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tags_table'") === $tags_table) {
            $tag_cols = $wpdb->get_col("DESCRIBE $tags_table");
            if (!in_array('theme_vente_slug', $tag_cols, true)) {
                $wpdb->query("ALTER TABLE $tags_table ADD COLUMN theme_vente_slug varchar(100) DEFAULT NULL");
            }
        }

        $e_cols = $wpdb->get_col("DESCRIBE $table");
        if (!in_array('ai_launch_count', $e_cols, true)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN ai_launch_count int(11) DEFAULT 0");
        }
        if (!in_array('lot_number', $e_cols, true)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN lot_number varchar(10) DEFAULT NULL");
        }
        if (!in_array('dimensions', $e_cols, true)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN dimensions varchar(255) DEFAULT NULL");
        }

        $log_table = $wpdb->prefix . 'lmd_activity_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$log_table'") !== $log_table) {
            $charset = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE $log_table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) NOT NULL DEFAULT 0,
                page_type varchar(20) NOT NULL,
                estimation_id bigint(20) unsigned DEFAULT NULL,
                duration_seconds int(11) NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY site_created (site_id, created_at),
                KEY estimation_id (estimation_id)
            ) $charset");
        }

        if (get_option('lmd_service_start_date', '') === '') {
            $first = $wpdb->get_var("SELECT MIN(created_at) FROM $table WHERE site_id = " . (int) get_current_blog_id());
            update_option('lmd_service_start_date', $first ? substr($first, 0, 10) : current_time('Y-m-d'));
        }
    }
}
