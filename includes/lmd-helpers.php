<?php
/**
 * Helpers pour le plugin LMD Apps IA
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extrait l'estimation IA (basse et haute) depuis ai_analysis.
 * Utilisable dans la vignette, la vue détail, etc.
 *
 * @param string|array $ai_analysis JSON string ou tableau décodé de ai_analysis
 * @return array ['low' => float|null, 'high' => float|null]
 */
function lmd_get_ai_estimation($ai_analysis) {
    $data = is_array($ai_analysis) ? $ai_analysis : (json_decode($ai_analysis, true) ?: []);
    $low = null;
    $high = null;
    if (isset($data['estimate_low']) && $data['estimate_low'] !== '' && $data['estimate_low'] !== null) {
        $v = preg_replace('/\s+/', '', (string) $data['estimate_low']);
        $v = str_replace([',', "\xc2\xa0"], '.', $v);
        if (is_numeric($v)) $low = floatval($v);
    }
    if (isset($data['estimate_high']) && $data['estimate_high'] !== '' && $data['estimate_high'] !== null) {
        $v = preg_replace('/\s+/', '', (string) $data['estimate_high']);
        $v = str_replace([',', "\xc2\xa0"], '.', $v);
        if (is_numeric($v)) $high = floatval($v);
    }
    if ($low === null && !empty($data['summary'])) {
        $txt = $data['summary'];
        if (preg_match('/(\d[\d\s,\.]*)\s*€|(\d[\d\s,\.]*)\s*euros?|estimation[:\s]+(\d[\d\s,\.]*)/ui', $txt, $m)) {
            $raw = trim($m[1] ?? $m[2] ?? $m[3] ?? '');
            $v = preg_replace('/\s+/', '', $raw);
            $v = str_replace([',', "\xc2\xa0"], '.', $v);
            if (is_numeric($v)) $low = floatval($v);
        }
    }
    return ['low' => $low, 'high' => $high];
}

/**
 * Convertit un montant en slug de catégorie estimation (utilise les paliers personnalisés si définis)
 */
function lmd_amount_to_estimation_slug($amount) {
    if ($amount === null || $amount === '' || !is_numeric(str_replace([' ', ',', "\xc2\xa0"], '', (string) $amount))) {
        return null;
    }
    $v = floatval(str_replace([',', "\xc2\xa0"], '.', preg_replace('/\s+/', '', (string) $amount)));
    $paliers = function_exists('lmd_get_estimation_paliers') ? lmd_get_estimation_paliers() : [
        'moins_25' => ['max' => 25],
        'moins_100' => ['max' => 100],
        'moins_500' => ['max' => 500],
        'moins_1000' => ['max' => 1000],
        'moins_5000' => ['max' => 5000],
        'plus_5000' => ['min' => 5000],
    ];
    $max_paliers = [];
    $min_slug = null;
    foreach ($paliers as $slug => $pal) {
        if (isset($pal['max'])) {
            $max_paliers[$slug] = $pal['max'];
        } elseif (isset($pal['min'])) {
            $min_slug = $slug;
        }
    }
    asort($max_paliers);
    foreach ($max_paliers as $slug => $max) {
        if ($v < $max) return $slug;
    }
    return $min_slug;
}

/**
 * Détermine la source de l'estimation (priorité: CP > 2e avis > IA) et le slug affiché.
 * Quand la sélection est manuelle (tag sans montants), la couleur suit l'onglet actif :
 * 1er avis = bleu, 2ème avis = mauve.
 *
 * @param object $estimation Objet estimation
 * @param array $ai ai_analysis décodé
 * @param object|null $estimation_tag Tag estimation lié
 * @param int $opinion 1 = 1er avis, 2 = 2ème avis (pour sélection manuelle)
 * @return array ['source' => 'cp'|'avis2'|'ia', 'slug' => string, 'name' => string]
 */
function lmd_get_estimation_source($estimation, $ai, $estimation_tag, $opinion = 1) {
    $slug = null;
    $source = '';
    $name = '';
    $est_slug = $estimation_tag ? $estimation_tag->slug : null;
    $est_name = $estimation_tag ? $estimation_tag->name : null;
    $ai_est = function_exists('lmd_get_ai_estimation') ? lmd_get_ai_estimation($ai ?: []) : ['low' => null, 'high' => null];
    $ai_slug = !empty($ai['estimation']) ? trim($ai['estimation']) : null;
    if (!$ai_slug && $ai_est['low'] !== null) {
        $ai_slug = lmd_amount_to_estimation_slug($ai_est['low']);
    }
    $has_cp = ($estimation->avis1_estimate_low ?? null) !== null || ($estimation->avis1_estimate_high ?? null) !== null;
    $has_avis2 = ($estimation->avis2_estimate_low ?? null) !== null || ($estimation->avis2_estimate_high ?? null) !== null;
    if ($has_cp) {
        $amt = $estimation->avis1_estimate_low ?? $estimation->avis1_estimate_high;
        $slug = $est_slug ?: lmd_amount_to_estimation_slug($amt);
        $source = 'cp';
    } elseif ($has_avis2) {
        $amt = $estimation->avis2_estimate_low ?? $estimation->avis2_estimate_high;
        $slug = $est_slug ?: lmd_amount_to_estimation_slug($amt);
        $source = 'avis2';
    } elseif ($ai_slug || $ai_est['low'] !== null) {
        $slug = $ai_slug ?: lmd_amount_to_estimation_slug($ai_est['low']);
        $source = 'ia';
    }
    if ($slug) {
        $name = $est_name ?: (function_exists('lmd_get_estimation_name') ? lmd_get_estimation_name($slug) : $slug);
    }
    return ['source' => $source, 'slug' => $slug, 'name' => $name];
}

/**
 * Échappe un nom de tag pour l'affichage HTML.
 * Décode d'abord les entités (ex: &lt; de sanitize_text_field) pour éviter le double échappement.
 */
function lmd_esc_tag_name($name) {
    if (!is_string($name) || $name === '') {
        return '';
    }
    $decoded = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return esc_html($decoded);
}

/**
 * Formate un montant en euros pour l'affichage
 */
function lmd_format_euro_display($value) {
    if ($value === null || $value === '' || !is_numeric(str_replace([' ', ',', "\xc2\xa0"], '', (string) $value))) {
        return '';
    }
    $clean = preg_replace('/\s+/', '', (string) $value);
    $clean = str_replace(',', '.', $clean);
    $num = floatval($clean);
    return number_format($num, 0, ',', ' ');
}

/**
 * Compresse une image si > 2 Mo (cible 300 Ko - 1 Mo)
 */
function lmd_compress_image($filepath) {
    if (!file_exists($filepath) || filesize($filepath) <= 2 * 1024 * 1024) {
        return true;
    }
    $info = getimagesize($filepath);
    if (!$info) {
        return false;
    }
    $mime = $info['mime'];
    $img = null;
    if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
        $img = imagecreatefromjpeg($filepath);
    } elseif ($mime === 'image/png') {
        $img = imagecreatefrompng($filepath);
    } elseif ($mime === 'image/webp') {
        if (function_exists('imagecreatefromwebp')) {
            $img = imagecreatefromwebp($filepath);
        }
    }
    if (!$img) {
        return false;
    }
    $target_size = 600 * 1024; // 600 Ko
    $quality = 85;
    $tmp = $filepath . '.tmp.' . pathinfo($filepath, PATHINFO_EXTENSION);
    $saved = false;
    while ($quality >= 50 && !$saved) {
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $saved = imagejpeg($img, $tmp, $quality);
        } elseif ($mime === 'image/png') {
            $saved = imagepng($img, $tmp, (int) (9 * (100 - $quality) / 100));
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $saved = imagewebp($img, $tmp, $quality);
        }
        if ($saved && file_exists($tmp) && filesize($tmp) <= $target_size) {
            break;
        }
        if ($saved && file_exists($tmp)) {
            rename($tmp, $filepath);
            imagedestroy($img);
            return true;
        }
        $quality -= 10;
    }
    if ($saved && file_exists($tmp)) {
        rename($tmp, $filepath);
    }
    imagedestroy($img);
    return true;
}

/**
 * Récupère les rapports d'erreur IA pour apprentissage.
 * Utilisé par le plugin Parent pour lister et exporter les erreurs.
 *
 * @param int|null $site_id Site ID (null = site actuel)
 * @param array $args { limit, offset, orderby, order }
 * @return array Liste de rapports
 */
function lmd_get_ai_error_reports($site_id = null, $args = []) {
    global $wpdb;
    $current = get_current_blog_id();
    $target = $site_id ?? $current;
    if ($target !== $current && is_multisite()) {
        switch_to_blog($target);
    }
    $prefix = $wpdb->get_blog_prefix($target);
    $table = $prefix . 'lmd_ai_error_reports';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        if ($target !== $current && is_multisite()) {
            restore_current_blog();
        }
        return [];
    }
    $limit = isset($args['limit']) ? absint($args['limit']) : 100;
    $offset = isset($args['offset']) ? absint($args['offset']) : 0;
    $orderby = in_array($args['orderby'] ?? 'created_at', ['created_at', 'id', 'site_id'], true) ? $args['orderby'] : 'created_at';
    $order = strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE site_id = %d ORDER BY $orderby $order LIMIT %d OFFSET %d",
        $target,
        $limit,
        $offset
    ), ARRAY_A);
    if ($target !== $current && is_multisite()) {
        restore_current_blog();
    }
    return $rows ?: [];
}

/**
 * URL admin pour l’application « Aide à l’estimation » (page à onglets).
 *
 * @param string $tab dashboard|new|list|help|ventes|vendeurs (preferences|activity redirigent vers dashboard&dash_sub=…)
 * @param array  $args Paramètres additionnels : month, id, dash_sub, help_sub, etc.
 */
function lmd_app_estimation_admin_url($tab = 'dashboard', array $args = []) {
    $args = array_merge(['page' => 'lmd-app-estimation', 'tab' => $tab], $args);
    return add_query_arg($args, admin_url('admin.php'));
}
