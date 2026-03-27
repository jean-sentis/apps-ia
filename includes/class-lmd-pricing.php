<?php
/**
 * Gestion de la tarification et des paliers
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Pricing {

    private $wpdb;
    private $prefix;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'lmd_';
    }

    public function ensure_tables_exist() {
        $table = $this->prefix . 'pricing_tiers';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            if (class_exists('LMD_Database')) {
                $db = new LMD_Database();
                $db->ensure_pricing_ready();
            }
        }
    }

    public function get_tiers($site_id = null) {
        $this->ensure_tables_exist();
        $table = $this->prefix . 'pricing_tiers';
        $site_id = $site_id ?? get_current_blog_id();
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $table WHERE site_id = %d ORDER BY min_amount ASC",
                $site_id
            )
        );
    }

    public function get_price_for_amount($amount, $client_email = null, $site_id = null) {
        $this->ensure_tables_exist();
        $site_id = $site_id ?? get_current_blog_id();

        if ($client_email) {
            $override = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT price_per_estimation FROM {$this->prefix}client_pricing_overrides WHERE site_id = %d AND client_email = %s",
                    $site_id,
                    $client_email
                )
            );
            if ($override) {
                return (float) $override->price_per_estimation;
            }
        }

        $tiers = $this->get_tiers($site_id);
        $amount = floatval($amount);
        foreach ($tiers as $tier) {
            $min = (float) $tier->min_amount;
            $max = $tier->max_amount !== null ? (float) $tier->max_amount : PHP_FLOAT_MAX;
            if ($amount >= $min && $amount < $max) {
                return (float) $tier->price_per_estimation;
            }
        }
        return 0;
    }
}
