<?php
/**
 * Migrations générales
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Migration {

    public static function run() {
        $done = get_option('lmd_migration_v1', false);
        if ($done) {
            return;
        }
        if (class_exists('LMD_Database')) {
            $db = new LMD_Database();
            $db->create_tables();
        }
        update_option('lmd_migration_v1', true);
    }
}
