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
        /** Slugs cochés = ne pas mettre les adresses en copie cachée (mailto) pour cette catégorie de réponse. */
        'bcc_exclude_response_slugs' => [],
    ];
}

/**
 * Libellés des catégories excluables pour la copie cachée (cases Préférences).
 *
 * @return array<string, string> slug => libellé
 */
function lmd_get_bcc_exclude_response_options() {
    return [
        'pas_pour_nous'   => 'Pas pour nous',
        'peu_interessant' => 'Peu intéressant',
        'a_examiner'      => 'À examiner',
        'moins_25'        => '< 25 €',
        'moins_100'       => '< 100 €',
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

/**
 * Enregistre toutes les préférences (grille, affichage, thèmes, BCC, signature, paliers estimation).
 * Même logique que l’ancien POST du formulaire Préférences.
 *
 * @param array $post Données type $_POST (formulaire Préférences).
 */
function lmd_save_preferences_bulk_from_post(array $post) {
    $grid_keys = array_keys(lmd_get_default_prefs()['grid_criteria']);
    $grid_criteria = [];
    foreach ($grid_keys as $k) {
        $grid_criteria[ $k ] = ! empty($post['grid_criteria'][ $k ]);
    }
    $display_last_n = max(0, min(500, (int) ($post['display_last_n'] ?? 0)));
    $display_include_unanswered = ! empty($post['display_include_unanswered']);
    $display_older_than_days = max(0, min(365, (int) ($post['display_older_than_days'] ?? 0)));
    $excluded = isset($post['excluded_theme']) && is_array($post['excluded_theme']) ? array_map('sanitize_text_field', $post['excluded_theme']) : [];
    $bcc_exclude_options = function_exists('lmd_get_bcc_exclude_response_options') ? lmd_get_bcc_exclude_response_options() : [];
    $bcc_exclude_post = [];
    if (! empty($bcc_exclude_options) && isset($post['bcc_exclude']) && is_array($post['bcc_exclude'])) {
        foreach (array_keys($bcc_exclude_options) as $slug) {
            if (! empty($post['bcc_exclude'][ $slug ])) {
                $bcc_exclude_post[] = $slug;
            }
        }
    }
    lmd_save_prefs([
        'grid_criteria' => $grid_criteria,
        'display_last_n' => $display_last_n,
        'display_include_unanswered' => $display_include_unanswered,
        'display_older_than_days' => $display_older_than_days,
        'excluded_theme_slugs' => $excluded,
        'bcc_exclude_response_slugs' => $bcc_exclude_post,
    ]);
    if (function_exists('lmd_save_cp_settings_for_user')) {
        $cp_current = lmd_get_cp_settings_for_user();
        $sig = isset($post['cp_signature']) ? wp_kses_post(wp_unslash($post['cp_signature'])) : '';
        $c1 = isset($post['cp_copy_email_1']) ? sanitize_email(wp_unslash($post['cp_copy_email_1'])) : '';
        $c2 = isset($post['cp_copy_email_2']) ? sanitize_email(wp_unslash($post['cp_copy_email_2'])) : '';
        lmd_save_cp_settings_for_user($cp_current['email'], $sig, array_filter([$c1, $c2]));
    }
    /*
     * Intervalles d’estimation : option `lmd_custom_categories['estimation']` (site).
     * Le formulaire envoie `lmd_prefs_include_intervals` pour savoir qu’il faut appliquer la section
     * (y compris tableau vide → réinitialise les paliers personnalisés, retour aux défauts).
     */
    if (! empty($post['lmd_prefs_include_intervals']) && function_exists('lmd_save_custom_category_options')) {
        $opts = [];
        if (isset($post['estimation_intervals']) && is_array($post['estimation_intervals'])) {
            foreach ($post['estimation_intervals'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = isset($row['name']) ? wp_strip_all_tags($row['name']) : '';
                $max = isset($row['max']) && $row['max'] !== '' ? floatval($row['max']) : null;
                $min = isset($row['min']) && $row['min'] !== '' ? floatval($row['min']) : null;
                if (! $name || ($max === null && $min === null)) {
                    continue;
                }
                if ($max !== null) {
                    $opts[] = ['slug' => 'moins_' . (int) $max, 'name' => $name, 'max' => $max];
                } else {
                    $opts[] = ['slug' => 'plus_' . (int) $min, 'name' => $name, 'min' => $min];
                }
            }
        }
        lmd_save_custom_category_options('estimation', $opts);
    }
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
