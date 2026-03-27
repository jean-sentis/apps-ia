<?php
/**
 * Gestion des quotas par site
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Quota_Manager {

    public function get_quota($site_id = null) {
        return 100;
    }

    public function get_used($site_id = null) {
        return 0;
    }
}
