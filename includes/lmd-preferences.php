<?php
/**
 * Préférences utilisateur pour l'aide à l'estimation
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LMD_PREF_META', 'lmd_estimation_prefs');

function lmd_get_prefs($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    $saved = get_user_meta($user_id, LMD_PREF_META, true);
    if (!is_array($saved)) {
        $saved = [];
    }
    return wp_parse_args($saved, lmd_get_default_prefs());
}

function lmd_get_default_prefs() {
    $categories = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
    $theme_slugs = array_map(function ($o) { return $o['slug'] ?? ''; }, $categories['theme_vente']['options'] ?? []);
    $theme_slugs = array_filter($theme_slugs);
    return [
        'grid_criteria' => [
            'message' => true,
            'interet' => true,
            'estimation' => true,
            'theme' => true,
            'date' => true,
            'cp_avis2' => true,
        ],
        'display_last_n' => 0,
        'display_include_unanswered' => false,
        'display_older_than_days' => 0,
        'excluded_theme_slugs' => [],
    ];
}

function lmd_save_prefs($prefs, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    $defaults = lmd_get_default_prefs();
    $merged = wp_parse_args($prefs, $defaults);
    update_user_meta($user_id, LMD_PREF_META, $merged);
    return $merged;
}

function lmd_pref_show_criterion($key) {
    $prefs = lmd_get_prefs();
    return !empty($prefs['grid_criteria'][$key]);
}

function lmd_get_cp_settings_for_user($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    global $wpdb;
    $site_id = get_current_blog_id();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT cp_email, cp_signature, cp_copy_emails FROM {$wpdb->prefix}lmd_cp_settings WHERE site_id = %d AND user_id = %d",
        $site_id, $user_id
    ));
    $copy_arr = [];
    if ($row && !empty($row->cp_copy_emails)) {
        $copy_arr = array_filter(array_map('trim', explode(',', wp_unslash($row->cp_copy_emails))));
    }
    return [
        'email' => $row->cp_email ?? '',
        'signature' => wp_unslash($row->cp_signature ?? ''),
        'copy_emails' => $copy_arr,
    ];
}

function lmd_save_cp_settings_for_user($email, $signature, $copy_emails, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    $copy_str = is_array($copy_emails) ? implode(', ', array_filter(array_map('sanitize_email', $copy_emails))) : sanitize_text_field($copy_emails);
    global $wpdb;
    $site_id = get_current_blog_id();
    $table = $wpdb->prefix . 'lmd_cp_settings';
    $current = lmd_get_cp_settings_for_user($user_id);
    $email = $email !== '' && $email !== null ? sanitize_email($email) : ($current['email'] ?? '');
    $signature = wp_kses_post(wp_unslash($signature));
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE site_id = %d AND user_id = %d", $site_id, $user_id));
    if ($exists) {
        $wpdb->update($table, ['cp_email' => $email, 'cp_signature' => $signature, 'cp_copy_emails' => $copy_str], ['site_id' => $site_id, 'user_id' => $user_id], ['%s', '%s', '%s'], ['%d', '%d']);
    } else {
        $wpdb->insert($table, ['site_id' => $site_id, 'user_id' => $user_id, 'cp_email' => $email, 'cp_signature' => $signature, 'cp_copy_emails' => $copy_str], ['%d', '%d', '%s', '%s', '%s']);
    }
}
