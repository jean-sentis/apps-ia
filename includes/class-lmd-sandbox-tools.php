<?php
/**
 * Outils bac à sable — monosite uniquement (données de test conso / marge).
 *
 * @package LMD_Apps_IA
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Sandbox_Tools {

    public const SOURCE = 'sandbox';

    /**
     * Réservé aux installations non multisite (votre environnement de dev).
     */
    public static function is_allowed() {
        return !is_multisite();
    }

    /**
     * Crée des estimations factices et enregistre une conso API type « une analyse ».
     *
     * @param int $count Nombre d’analyses simulées (1–50).
     * @return array|WP_Error { created: int }
     */
    public static function seed_fake_analyses($count = 3) {
        if (!self::is_allowed()) {
            return new WP_Error('lmd_sandbox', 'Réservé au site monosite.');
        }
        if (!current_user_can('manage_options')) {
            return new WP_Error('lmd_sandbox', 'Droits insuffisants.');
        }
        $count = max(1, min(50, (int) $count));

        global $wpdb;
        $site_id = get_current_blog_id();
        $est = $wpdb->prefix . 'lmd_estimations';

        if (!class_exists('LMD_Api_Usage')) {
            return new WP_Error('lmd_sandbox', 'LMD_Api_Usage indisponible.');
        }
        $usage = new LMD_Api_Usage();
        $usage->ensure_table_exists();

        $created = 0;
        $now = current_time('mysql');
        for ($i = 0; $i < $count; $i++) {
            $ok = $wpdb->insert(
                $est,
                [
                    'site_id' => $site_id,
                    'client_name' => 'BAC À SABLE (test)',
                    'client_email' => 'sandbox+' . wp_generate_password(8, false, false) . '@local.test',
                    'description' => 'Généré par Outils bac à sable — supprimable sans impact métier.',
                    'status' => 'new',
                    'source' => self::SOURCE,
                    'created_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            if (!$ok) {
                continue;
            }
            $eid = (int) $wpdb->insert_id;
            if ($eid < 1) {
                continue;
            }
            $usage->log('serpapi', 1, $eid);
            $usage->log('firecrawl', 3, $eid);
            $usage->log('gemini', 1, $eid);
            $created++;
        }

        if (function_exists('lmd_update_consumption_summary')) {
            lmd_update_consumption_summary();
        }

        return ['created' => $created];
    }

    /**
     * Supprime estimations `source=sandbox` et usages API associés.
     *
     * @return array|WP_Error { deleted: int }
     */
    public static function clear_sandbox_data() {
        if (!self::is_allowed()) {
            return new WP_Error('lmd_sandbox', 'Réservé au site monosite.');
        }
        if (!current_user_can('manage_options')) {
            return new WP_Error('lmd_sandbox', 'Droits insuffisants.');
        }

        global $wpdb;
        $site_id = get_current_blog_id();
        $est = $wpdb->prefix . 'lmd_estimations';
        $et = $wpdb->prefix . 'lmd_estimation_tags';
        $api = $wpdb->prefix . 'lmd_api_usage';

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $est WHERE site_id = %d AND source = %s",
                $site_id,
                self::SOURCE
            )
        );
        if (empty($ids)) {
            if (function_exists('lmd_update_consumption_summary')) {
                lmd_update_consumption_summary();
            }
            return ['deleted' => 0];
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        $ph = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $et WHERE estimation_id IN ($ph)", ...$ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $api WHERE estimation_id IN ($ph)", ...$ids));
        $wpdb->query($wpdb->prepare("DELETE FROM $est WHERE id IN ($ph)", ...$ids));

        $n = count($ids);
        if (function_exists('lmd_update_consumption_summary')) {
            lmd_update_consumption_summary();
        }

        return ['deleted' => $n];
    }
}
