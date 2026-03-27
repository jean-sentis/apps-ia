<?php
/**
 * Handlers AJAX dédiés (analyse, lancement, etc.)
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_lmd_launch_analysis', 'lmd_ajax_launch_analysis');
add_action('wp_ajax_lmd_run_analysis_background', 'lmd_ajax_run_analysis_background');
add_action('wp_ajax_nopriv_lmd_run_analysis_background', 'lmd_ajax_run_analysis_background');
add_action('wp_ajax_lmd_save_avis1_estimates', 'lmd_ajax_save_avis1_estimates');
add_action('wp_ajax_lmd_save_avis2_estimates', 'lmd_ajax_save_avis2_estimates');
add_action('wp_ajax_lmd_check_analysis_status', 'lmd_ajax_check_analysis_status');
add_action('wp_ajax_lmd_check_analysis_status_batch', 'lmd_ajax_check_analysis_status_batch');
add_action('wp_ajax_lmd_create_vente', 'lmd_ajax_create_vente');
add_action('wp_ajax_lmd_update_vente', 'lmd_ajax_update_vente');
add_action('wp_ajax_lmd_list_ventes', 'lmd_ajax_list_ventes');
add_action('wp_ajax_lmd_report_ai_error', 'lmd_ajax_report_ai_error');
add_action('wp_ajax_lmd_save_analysis_pricing', 'lmd_ajax_save_analysis_pricing');
add_action('wp_ajax_lmd_log_activity', 'lmd_ajax_log_activity');
add_action('wp_ajax_lmd_save_lot_number', 'lmd_ajax_save_lot_number');

function lmd_ajax_save_lot_number() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $id = isset($_POST['estimation_id']) ? absint($_POST['estimation_id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'ID manquant']);
    }
    $lot = isset($_POST['lot_number']) ? preg_replace('/\D/', '', wp_unslash($_POST['lot_number'])) : '';
    if ($lot !== '') {
        $n = (int) $lot;
        if ($n < 1 || $n > 999) $lot = '';
        else $lot = str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'lmd_estimations';
    $cols = $wpdb->get_col("DESCRIBE $table");
    if (!in_array('lot_number', $cols, true)) {
        wp_send_json_success();
        return;
    }
    $wpdb->update($table, ['lot_number' => $lot ?: null], ['id' => $id], ['%s'], ['%d']);
    wp_send_json_success();
}

function lmd_ajax_launch_analysis() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'ID manquant']);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'lmd_estimations';
    $cols = $wpdb->get_col("DESCRIBE $table");
    $up = ['status' => 'analyzing'];
    if (in_array('first_viewed_at', $cols, true)) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT first_viewed_at FROM $table WHERE id = %d", $id));
        if ($row && empty($row->first_viewed_at)) {
            $up['first_viewed_at'] = current_time('mysql');
        }
    }
    if (in_array('ai_launch_count', $cols, true)) {
        $wpdb->query($wpdb->prepare("UPDATE $table SET ai_launch_count = COALESCE(ai_launch_count, 0) + 1 WHERE id = %d", $id));
    }
    $fmt = array_fill(0, count($up), '%s');
    $wpdb->update($table, $up, ['id' => $id], $fmt, ['%d']);
    set_transient('lmd_analysis_progress_' . $id, ['percent' => 5, 'step' => 'Démarrage...'], 300);

    @set_time_limit(300);
    $processor = class_exists('LMD_Estimation_Processor') ? new LMD_Estimation_Processor() : null;

    $run_analysis = function () use ($processor, $id, $wpdb) {
        if (!$processor) {
            $wpdb->update($wpdb->prefix . 'lmd_estimations', ['status' => 'new'], ['id' => $id], ['%s'], ['%d']);
            return;
        }
        try {
            $result = $processor->run_analysis($id);
            if (isset($result['success']) && !$result['success'] && !empty($result['message'])) {
                set_transient('lmd_analysis_error_' . $id, $result['message'], 300);
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (function_exists('error_log')) {
                error_log('LMD analyse erreur: ' . $msg . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            $wpdb->update($wpdb->prefix . 'lmd_estimations', ['status' => 'new'], ['id' => $id], ['%s'], ['%d']);
            set_transient('lmd_analysis_error_' . $id, 'Erreur: ' . $msg, 300);
        }
    };

    if (function_exists('fastcgi_finish_request')) {
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        echo wp_json_encode(['success' => true, 'data' => ['message' => 'Analyse lancée']]);
        fastcgi_finish_request();
        @ignore_user_abort(true);
        $run_analysis();
        delete_transient('lmd_analysis_progress_' . $id);
    } else {
        $run_analysis();
        delete_transient('lmd_analysis_progress_' . $id);
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        wp_send_json_success(['message' => 'Analyse terminée']);
    }
    exit;
}

function lmd_ajax_run_analysis_background() {
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
    if (!$id || !$token) {
        status_header(403);
        exit;
    }
    $stored = get_transient('lmd_analysis_auth_' . $id);
    if ($stored !== $token) {
        status_header(403);
        exit;
    }
    delete_transient('lmd_analysis_auth_' . $id);
    $processor = class_exists('LMD_Estimation_Processor') ? new LMD_Estimation_Processor() : null;
    if (!$processor) {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'lmd_estimations', ['status' => 'new'], ['id' => $id], ['%s'], ['%d']);
        delete_transient('lmd_analysis_progress_' . $id);
        exit;
    }
    $processor->run_analysis($id);
    delete_transient('lmd_analysis_progress_' . $id);
    exit;
}

function lmd_ajax_save_avis1_estimates() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'ID manquant']);
    }
    global $wpdb;
    $data = [
        'avis1_estimate_low' => isset($_POST['estimate_low']) ? floatval(str_replace([' ', ','], ['', '.'], (string) $_POST['estimate_low'])) : null,
        'avis1_estimate_high' => isset($_POST['estimate_high']) ? floatval(str_replace([' ', ','], ['', '.'], (string) $_POST['estimate_high'])) : null,
        'avis1_prix_reserve' => isset($_POST['prix_reserve']) ? floatval(str_replace([' ', ','], ['', '.'], (string) $_POST['prix_reserve'])) : null,
    ];
    if (isset($_POST['avis_text'])) {
        $data['auctioneer_notes'] = sanitize_textarea_field(wp_unslash($_POST['avis_text']));
    }
    if (isset($_POST['avis_titre'])) {
        $data['avis1_titre'] = sanitize_text_field(wp_unslash($_POST['avis_titre']));
    }
    if (isset($_POST['avis_dimension'])) {
        $data['avis1_dimension'] = sanitize_text_field(wp_unslash($_POST['avis_dimension']));
    }
    $wpdb->update($wpdb->prefix . 'lmd_estimations', $data, ['id' => $id], null, ['%d']);
    $tag_display = lmd_ajax_get_estimation_tag_display($id, 1);
    wp_send_json_success($tag_display ? ['tag_estimation' => $tag_display] : []);
}

function lmd_ajax_save_avis2_estimates() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'ID manquant']);
    }
    global $wpdb;
    $data = [
        'avis2_estimate_low' => isset($_POST['estimate_low']) ? floatval(str_replace([' ', ','], ['', '.'], (string) $_POST['estimate_low'])) : null,
        'avis2_estimate_high' => isset($_POST['estimate_high']) ? floatval(str_replace([' ', ','], ['', '.'], (string) $_POST['estimate_high'])) : null,
        'avis2_prix_reserve' => isset($_POST['prix_reserve']) ? floatval(str_replace([' ', ','], ['', '.'], (string) $_POST['prix_reserve'])) : null,
    ];
    if (isset($_POST['avis_text'])) {
        $data['second_opinion'] = sanitize_textarea_field(wp_unslash($_POST['avis_text']));
    }
    if (isset($_POST['avis_titre'])) {
        $data['avis2_titre'] = sanitize_text_field(wp_unslash($_POST['avis_titre']));
    }
    if (isset($_POST['avis_dimension'])) {
        $data['avis2_dimension'] = sanitize_text_field(wp_unslash($_POST['avis_dimension']));
    }
    $wpdb->update($wpdb->prefix . 'lmd_estimations', $data, ['id' => $id], null, ['%d']);
    $tag_display = lmd_ajax_get_estimation_tag_display($id, 2);
    wp_send_json_success($tag_display ? ['tag_estimation' => $tag_display] : []);
}

function lmd_ajax_get_estimation_tag_display($id, $opinion) {
    global $wpdb;
    $estimation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}lmd_estimations WHERE id = %d",
        $id
    ));
    if (!$estimation) return null;
    $site_id = get_current_blog_id();
    $linked_tags = $wpdb->get_results($wpdb->prepare(
        "SELECT t.id, t.name, t.type, t.slug, et.modified_by_avis FROM {$wpdb->prefix}lmd_estimation_tags et INNER JOIN {$wpdb->prefix}lmd_tags t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.site_id = %d",
        $id,
        $site_id
    ));
    $tags_by_type = [];
    foreach ((array) $linked_tags as $t) {
        $tags_by_type[$t->type] = $t;
    }
    $estimation_tag = $tags_by_type['estimation'] ?? null;
    $ai = !empty($estimation->ai_analysis) ? (json_decode($estimation->ai_analysis, true) ?: []) : [];
    $estimation_source = function_exists('lmd_get_estimation_source') ? lmd_get_estimation_source($estimation, $ai, $estimation_tag, $opinion) : ['source' => '', 'slug' => null, 'name' => ''];
    if (!$estimation_source['slug']) return null;
    $label = 'Estimation';
    $name = $estimation_source['name'] ?: $label;
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $source = $estimation_source['source'];
    $colors = function_exists('lmd_get_tag_filter_colors') ? lmd_get_tag_filter_colors('estimation', $estimation_source['slug'], $source) : ['border' => '#e5e7eb'];
    $border_color = $colors['border'] ?? '#e5e7eb';
    $source_class = $source ? ' ed-tag-source-' . $source : '';
    return [
        'slug' => $estimation_source['slug'],
        'name' => $name,
        'label' => $label,
        'border_color' => $border_color,
        'source_class' => $source_class,
    ];
}

function lmd_ajax_check_analysis_status() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'ID manquant']);
    }
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}lmd_estimations WHERE id = %d",
        $id
    ));
    if (!$row) {
        wp_send_json_error(['message' => 'Estimation introuvable']);
    }
    if ($row->status === 'ai_analyzed') {
        wp_send_json_success(['status' => 'ai_analyzed', 'percent' => 100, 'step' => 'Terminé']);
    }
    $error = get_transient('lmd_analysis_error_' . $id);
    if ($row->status === 'new' && is_string($error) && $error !== '') {
        delete_transient('lmd_analysis_error_' . $id);
        wp_send_json_success(['status' => 'error', 'message' => $error]);
    }
    $progress = get_transient('lmd_analysis_progress_' . $id);
    if (is_array($progress)) {
        wp_send_json_success([
            'status' => 'analyzing',
            'percent' => (int) ($progress['percent'] ?? 0),
            'step' => (string) ($progress['step'] ?? ''),
        ]);
    }
    wp_send_json_success(['status' => $row->status, 'percent' => 10, 'step' => 'En attente...']);
}

function lmd_ajax_check_analysis_status_batch() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $ids = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : [];
    $ids = array_filter(array_slice($ids, 0, 50));
    if (empty($ids)) {
        wp_send_json_success(['statuses' => []]);
    }
    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, status FROM {$wpdb->prefix}lmd_estimations WHERE id IN ($placeholders)",
        ...$ids
    ));
    $statuses = [];
    foreach ($rows as $r) {
        $statuses[(string) $r->id] = $r->status;
    }
    foreach ($ids as $id) {
        if (!isset($statuses[(string) $id])) {
            $statuses[(string) $id] = 'new';
        }
    }
    wp_send_json_success(['statuses' => $statuses]);
}

function lmd_ajax_create_vente() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $theme_vente_slug = isset($_POST['theme_vente_slug']) ? sanitize_text_field($_POST['theme_vente_slug']) : '';
    if (!$name || !$date) {
        wp_send_json_error(['message' => 'Nom et date requis']);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => 'Date invalide (YYYY-MM-DD)']);
    }
    global $wpdb;
    $site_id = get_current_blog_id();
    $t = $wpdb->prefix . 'lmd_tags';
    $slug = $date;
    $n = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE site_id = %d AND type = 'date_vente' AND slug = %s", $site_id, $slug))) {
        $slug = $date . '-' . (++$n);
    }
    $cols = ['site_id' => $site_id, 'name' => $name, 'type' => 'date_vente', 'slug' => $slug];
    $fmt = ['%d', '%s', '%s', '%s'];
    $tag_cols = $wpdb->get_col("DESCRIBE $t");
    if (in_array('theme_vente_slug', $tag_cols, true) && $theme_vente_slug !== '') {
        $cols['theme_vente_slug'] = $theme_vente_slug;
        $fmt[] = '%s';
    }
    $wpdb->insert($t, $cols, $fmt);
    $tag_id = $wpdb->insert_id;
    if (!$tag_id) {
        wp_send_json_error(['message' => 'Erreur création']);
    }
    wp_send_json_success(['tag_id' => $tag_id, 'slug' => $slug, 'name' => $name]);
}

function lmd_ajax_update_vente() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $tag_id = isset($_POST['tag_id']) ? absint($_POST['tag_id']) : 0;
    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    if (!$tag_id) {
        wp_send_json_error(['message' => 'Tag manquant']);
    }
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        wp_send_json_error(['message' => 'Date invalide']);
    }
    global $wpdb;
    $site_id = get_current_blog_id();
    $t = $wpdb->prefix . 'lmd_tags';
    $new_slug = $date;
    $n = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE site_id = %d AND type = 'date_vente' AND slug = %s AND id != %d", $site_id, $new_slug, $tag_id))) {
        $new_slug = $date . '-' . (++$n);
    }
    $up = ['slug' => $new_slug];
    if ($name !== '') $up['name'] = $name;
    $wpdb->update($t, $up, ['id' => $tag_id, 'site_id' => $site_id, 'type' => 'date_vente'], null, ['%d', '%d', '%s']);
    wp_send_json_success(['slug' => $new_slug]);
}

function lmd_ajax_list_ventes() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $db = new LMD_Database();
    $opts = $db->get_tag_options_for_type('date_vente');
    $list = [];
    foreach ($opts as $o) {
        $item = ['id' => (int) $o->id, 'slug' => $o->slug, 'name' => $o->name];
        if (!empty($o->theme_vente_slug)) {
            $item['theme_vente_slug'] = $o->theme_vente_slug;
            $item['theme_vente_name'] = function_exists('lmd_get_theme_vente_name') ? lmd_get_theme_vente_name($o->theme_vente_slug) : $o->theme_vente_slug;
        }
        $list[] = $item;
    }
    wp_send_json_success(['ventes' => $list]);
}

/**
 * Signalement d'erreur IA : enregistre le signalement et notifie les administrateurs multisite.
 */
function lmd_ajax_report_ai_error() {
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'ID manquant']);
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'lmd_report_ai_' . $id)) {
        wp_send_json_error(['message' => 'Nonce invalide']);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'lmd_estimations';
    $estimation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$estimation) {
        wp_send_json_error(['message' => 'Estimation introuvable']);
    }
    $cols = $wpdb->get_col("DESCRIBE $table");
    if (!in_array('ai_error_reported_at', $cols, true)) {
        wp_send_json_error(['message' => 'Colonne manquante']);
    }
    if (!empty($estimation->ai_error_reported_at)) {
        wp_send_json_success(['already' => true]);
    }

    $user_explanation = isset($_POST['user_explanation']) ? sanitize_textarea_field(wp_unslash($_POST['user_explanation'])) : '';

    $ai = !empty($estimation->ai_analysis) ? (json_decode($estimation->ai_analysis, true) ?: []) : [];
    $ai_est = function_exists('lmd_get_ai_estimation') ? lmd_get_ai_estimation($ai) : ['low' => null, 'high' => null];

    $err_table = $wpdb->prefix . 'lmd_ai_error_reports';
    $err_cols = $wpdb->get_var("SHOW TABLES LIKE '$err_table'") === $err_table ? $wpdb->get_col("DESCRIBE $err_table") : [];
    if ($wpdb->get_var("SHOW TABLES LIKE '$err_table'") === $err_table) {
        $insert_data = [
            'site_id' => get_current_blog_id(),
            'estimation_id' => $id,
            'client_name' => trim($estimation->client_name ?? '') ?: trim($estimation->client_email ?? ''),
            'description' => $estimation->description ?? '',
            'ai_summary' => trim($ai['summary'] ?? ''),
            'ai_estimate_low' => $ai_est['low'],
            'ai_estimate_high' => $ai_est['high'],
            'ai_analysis_json' => $estimation->ai_analysis,
        ];
        $insert_fmt = ['%d', '%d', '%s', '%s', '%s', '%f', '%f', '%s'];
        if (in_array('user_explanation', $err_cols, true)) {
            $insert_data['user_explanation'] = $user_explanation;
            $insert_fmt[] = '%s';
        }
        $wpdb->insert($err_table, $insert_data, $insert_fmt);
    }

    if (is_multisite()) {
        $site_id_origin = get_current_blog_id();
        $site_name = get_bloginfo('name') ?: 'Site ' . $site_id_origin;
        switch_to_blog(1);
        $remontee_table = $wpdb->prefix . 'lmd_remontee_ai_errors';
        if ($wpdb->get_var("SHOW TABLES LIKE '$remontee_table'") === $remontee_table) {
            $wpdb->insert($remontee_table, [
                'site_id_origin' => $site_id_origin,
                'site_name' => $site_name,
                'estimation_id' => $id,
                'client_name' => trim($estimation->client_name ?? '') ?: trim($estimation->client_email ?? ''),
                'description' => $estimation->description ?? '',
                'ai_summary' => trim($ai['summary'] ?? ''),
                'ai_estimate_low' => $ai_est['low'],
                'ai_estimate_high' => $ai_est['high'],
                'ai_analysis_json' => $estimation->ai_analysis,
                'user_explanation' => $user_explanation,
            ], ['%d', '%s', '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s']);
        }
        restore_current_blog();
    }

    $up = [
        'ai_error_reported_at' => current_time('mysql'),
        'ai_analysis' => null,
        'status' => 'new',
    ];
    $wpdb->update($table, $up, ['id' => $id], ['%s', '%s', '%s'], ['%d']);

    if (!empty($user_explanation)) {
        set_transient('lmd_ai_error_explanation_' . $id, $user_explanation, 3600);
    }

    $et_table = $wpdb->prefix . 'lmd_estimation_tags';
    $t_table = $wpdb->prefix . 'lmd_tags';
    $site_id = get_current_blog_id();
    $wpdb->query($wpdb->prepare(
        "DELETE et FROM $et_table et INNER JOIN $t_table t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.site_id = %d AND t.type IN ('interet', 'estimation', 'theme_vente')",
        $id,
        $site_id
    ));
    $detail_url = admin_url('admin.php?page=lmd-estimation-detail&id=' . $id);
    $client = trim($estimation->client_name ?? '') ?: $estimation->client_email ?? '?';
    $ai_summary = trim($ai['summary'] ?? '');
    $ai_est = function_exists('lmd_get_ai_estimation') ? lmd_get_ai_estimation($ai) : ['low' => null, 'high' => null];
    $ai_est_str = ($ai_est['low'] !== null) ? number_format($ai_est['low'], 0, ',', ' ') . ' – ' . number_format($ai_est['high'] ?? $ai_est['low'], 0, ',', ' ') . ' €' : '-';

    $body = "Un utilisateur a signalé que l'IA se trompe complètement sur ce lot.\n\n";
    if (!empty($user_explanation)) {
        $body .= "Explication de l'utilisateur :\n" . $user_explanation . "\n\n";
    }
    $body .= "Estimation #" . $id . " – " . $client . "\n";
    $body .= "Lien : " . $detail_url . "\n\n";
    $body .= "Résumé IA : " . ($ai_summary ?: '-') . "\n";
    $body .= "Estimation IA : " . $ai_est_str . "\n";

    $emails = [];
    $admins = get_users(['role' => 'administrator', 'fields' => ['user_email']]);
    foreach ($admins as $u) {
        $emails[] = $u->user_email;
    }
    if (is_multisite()) {
        $super_admins = get_super_admins();
        foreach ($super_admins as $login) {
            $u = get_user_by('login', $login);
            if ($u && $u->user_email && !in_array($u->user_email, $emails, true)) {
                $emails[] = $u->user_email;
            }
        }
    }
    $emails = array_unique(array_filter($emails));
    $subject = '[LMD Apps IA] Erreur IA signalée – Estimation #' . $id;
    foreach ($emails as $to) {
        wp_mail($to, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
    }

    wp_send_json_success(['ok' => true]);
}

function lmd_ajax_save_analysis_pricing() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options') || !is_main_site()) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $free = isset($_POST['free_granted']) ? max(0, (int) $_POST['free_granted']) : 20;
    $tiers_raw = isset($_POST['tiers']) && is_array($_POST['tiers']) ? $_POST['tiers'] : [];
    $tiers = [];
    foreach (array_slice($tiers_raw, 0, 5) as $t) {
        $min = isset($t['min_paid']) ? max(0, (int) $t['min_paid']) : 0;
        $price = isset($t['price']) ? max(0, (float) str_replace(',', '.', $t['price'])) : 0.50;
        $tiers[] = ['min_paid' => $min, 'price' => $price];
    }
    usort($tiers, function ($a, $b) { return $a['min_paid'] - $b['min_paid']; });
    if (empty($tiers)) {
        $tiers = [['min_paid' => 0, 'price' => 0.50], ['min_paid' => 20, 'price' => 0.33], ['min_paid' => 50, 'price' => 0.25]];
    }
    update_option('lmd_free_estimations_granted', $free);
    update_option('lmd_analysis_pricing_tiers', $tiers);
    wp_send_json_success(['message' => 'Tarification enregistrée']);
}

function lmd_ajax_log_activity() {
    check_ajax_referer('lmd_admin', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Non autorisé']);
    }
    $page_type = isset($_POST['page_type']) ? sanitize_key($_POST['page_type']) : '';
    $estimation_id = isset($_POST['estimation_id']) ? absint($_POST['estimation_id']) : null;
    $duration = isset($_POST['duration']) ? min(60, max(0, (int) $_POST['duration'])) : 0;
    if (!in_array($page_type, ['detail', 'grid'], true)) {
        wp_send_json_error(['message' => 'Type invalide']);
    }
    global $wpdb;
    $table = $wpdb->prefix . 'lmd_activity_log';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        wp_send_json_success();
        return;
    }
    $wpdb->insert($table, [
        'site_id' => get_current_blog_id(),
        'page_type' => $page_type,
        'estimation_id' => ($page_type === 'detail' && $estimation_id) ? $estimation_id : null,
        'duration_seconds' => $duration,
    ], ['%d', '%s', '%d', '%d']);
    wp_send_json_success();
}
