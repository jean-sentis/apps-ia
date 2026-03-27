<?php
/**
 * Vue Liste des estimations - Grille de cartes avec filtres tags
 * Style inspiré des demandes d'estimation (Lovable)
 */
if (!defined('ABSPATH')) {
    exit;
}
$db = new LMD_Database();
$db->ensure_tags_seeded();

$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$grid_cols = isset($_GET['cols']) ? absint($_GET['cols']) : 0;
if (!in_array($grid_cols, [3, 4, 5], true)) {
    $grid_cols = 5;
}
$filter_message = isset($_GET['filter_message']) ? (array) $_GET['filter_message'] : [];
$filter_interet = isset($_GET['filter_interet']) ? (array) $_GET['filter_interet'] : [];
$filter_estimation = isset($_GET['filter_estimation']) ? (array) $_GET['filter_estimation'] : [];
$filter_theme_vente = isset($_GET['filter_theme_vente']) ? (array) $_GET['filter_theme_vente'] : [];
$filter_date_vente = isset($_GET['filter_date_vente']) ? (array) $_GET['filter_date_vente'] : [];
$filter_vendeur = isset($_GET['filter_vendeur']) ? (array) $_GET['filter_vendeur'] : [];
$filter_date_envoi_from = isset($_GET['filter_date_envoi_from']) ? sanitize_text_field($_GET['filter_date_envoi_from']) : '';
$filter_date_envoi_to = isset($_GET['filter_date_envoi_to']) ? sanitize_text_field($_GET['filter_date_envoi_to']) : '';
$filter_message = array_filter(array_map('sanitize_text_field', $filter_message));
$filter_interet = array_filter(array_map('sanitize_text_field', $filter_interet));
$filter_estimation = array_filter(array_map('sanitize_text_field', $filter_estimation));
$filter_theme_vente = array_filter(array_map('sanitize_text_field', $filter_theme_vente));
$filter_date_vente = array_filter(array_map('sanitize_text_field', $filter_date_vente));
$filter_vendeur = array_filter(array_map('sanitize_text_field', $filter_vendeur));

$prefs = function_exists('lmd_get_prefs') ? lmd_get_prefs() : [];
$get_args = [
    'status' => $status,
    'search' => $search,
    'filter_message' => $filter_message,
    'filter_interet' => $filter_interet,
    'filter_estimation' => $filter_estimation,
    'filter_theme_vente' => $filter_theme_vente,
    'filter_date_vente' => $filter_date_vente,
    'filter_vendeur' => $filter_vendeur,
    'filter_date_envoi_from' => $filter_date_envoi_from,
    'filter_date_envoi_to' => $filter_date_envoi_to,
    'limit' => 100,
];
if (!empty($prefs['display_last_n']) || !empty($prefs['display_include_unanswered']) || !empty($prefs['display_older_than_days'])) {
    $get_args['pref_display_last_n'] = (int) ($prefs['display_last_n'] ?? 50);
    $get_args['pref_display_include_unanswered'] = !empty($prefs['display_include_unanswered']);
    $get_args['pref_display_older_than_days'] = (int) ($prefs['display_older_than_days'] ?? 0);
}
if (!empty($prefs['excluded_theme_slugs'])) {
    $get_args['pref_excluded_theme_slugs'] = $prefs['excluded_theme_slugs'];
}
$counts_exchanges = $db->get_estimation_counts_exchanges();
$estimations = $db->get_estimations($get_args);

$categories = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
$opts_message = array_values(array_filter($categories['message']['options'] ?? [], function ($o) {
    return ($o['slug'] ?? '') !== 'en_attente';
}));
$opts_interet = $categories['interet']['options'] ?? [];
$opts_estimation = $categories['estimation']['options'] ?? [];
$opts_theme_vente = $categories['theme_vente']['options'] ?? [];
$opts_date_vente = $db->get_tag_options_for_type('date_vente');
$opts_vendeur = $db->get_tag_options_for_type('vendeur');

function lmd_filter_clear_url($exclude_name) {
    $params = ['page' => 'lmd-estimations-list'];
    $names = ['filter_message','filter_interet','filter_estimation','filter_theme_vente','filter_date_vente','filter_vendeur','filter_date_envoi_from','filter_date_envoi_to'];
    $skip_date = ($exclude_name === 'filter_date_envoi' || $exclude_name === 'filter_date_envoi_from' || $exclude_name === 'filter_date_envoi_to');
    foreach ($names as $n) {
        if ($n === $exclude_name) continue;
        if ($skip_date && ($n === 'filter_date_envoi_from' || $n === 'filter_date_envoi_to')) continue;
        $v = isset($_GET[$n]) ? $_GET[$n] : null;
        if ($v !== null && $v !== '') $params[$n] = $v;
    }
    if (!empty($_GET['s'])) $params['s'] = sanitize_text_field($_GET['s']);
    if (!empty($_GET['status'])) $params['status'] = sanitize_text_field($_GET['status']);
    if (isset($_GET['cols']) && in_array((int) $_GET['cols'], [3, 4, 5], true)) $params['cols'] = (int) $_GET['cols'];
    return admin_url('admin.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

function lmd_grid_cols_url($cols) {
    $params = ['page' => 'lmd-estimations-list', 'cols' => $cols];
    $names = ['filter_message','filter_interet','filter_estimation','filter_theme_vente','filter_date_vente','filter_vendeur','filter_date_envoi_from','filter_date_envoi_to'];
    foreach ($names as $n) {
        $v = isset($_GET[$n]) ? $_GET[$n] : null;
        if ($v !== null && $v !== '') $params[$n] = $v;
    }
    if (!empty($_GET['s'])) $params['s'] = sanitize_text_field($_GET['s']);
    if (!empty($_GET['status'])) $params['status'] = sanitize_text_field($_GET['status']);
    if (isset($_GET['cols']) && in_array((int) $_GET['cols'], [3, 4, 5], true)) $params['cols'] = (int) $_GET['cols'];
    return admin_url('admin.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

function lmd_filter_remove_one_url($name, $slug) {
    $params = ['page' => 'lmd-estimations-list'];
    $names = ['filter_message','filter_interet','filter_estimation','filter_theme_vente','filter_date_vente','filter_vendeur'];
    foreach ($names as $n) {
        $v = isset($_GET[$n]) ? (array) $_GET[$n] : [];
        if ($n === $name) {
            $v = array_values(array_filter($v, function($x) use ($slug) { return $x !== $slug; }));
            if (!empty($v)) $params[$n] = $v;
        } elseif (!empty($v)) {
            $params[$n] = $v;
        }
    }
    if ($name !== 'filter_date_envoi') {
        foreach (['filter_date_envoi_from','filter_date_envoi_to'] as $dn) {
            $dv = isset($_GET[$dn]) ? sanitize_text_field($_GET[$dn]) : '';
            if ($dv !== '') $params[$dn] = $dv;
        }
    }
    if (!empty($_GET['s'])) $params['s'] = sanitize_text_field($_GET['s']);
    if (!empty($_GET['status'])) $params['status'] = sanitize_text_field($_GET['status']);
    if (isset($_GET['cols']) && in_array((int) $_GET['cols'], [3, 4, 5], true)) $params['cols'] = (int) $_GET['cols'];
    return admin_url('admin.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

function lmd_filter_dropdown($name, $label, $opts, $selected, $type, $is_obj = false, $opts_ui = []) {
    $has_selection = count($selected) > 0;
    $colors = function_exists('lmd_get_tag_filter_colors') ? lmd_get_tag_filter_colors($type, $selected[0] ?? '') : ['border' => '#22c55e'];
    $border = $has_selection ? ($colors['border'] ?? '#22c55e') : '#e5e7eb';
    $clear_url = lmd_filter_clear_url($name);
    $panel_min = isset($opts_ui['panel_min_width']) ? (int) $opts_ui['panel_min_width'] : 180;
    $sort_criterion = $opts_ui['sort_criterion'] ?? null;
    ob_start();
    ?>
    <div class="lmd-filter-dropdown" data-name="<?php echo esc_attr($name); ?>" data-panel-min-width="<?php echo esc_attr($panel_min); ?>"<?php echo $sort_criterion ? ' data-sort-criterion="' . esc_attr($sort_criterion) . '"' : ''; ?>>
        <button type="button" class="lmd-filter-dropdown-trigger lmd-filter-trigger-compact" style="border-left-color:<?php echo esc_attr($border); ?>">
            <span class="lmd-filter-dd-name"><?php echo esc_html($label); ?></span>
            <span class="lmd-filter-dd-arrow">▾</span>
        </button>
        <div class="lmd-filter-dropdown-panel">
            <div class="lmd-filter-opt-tous-row">
                <a href="<?php echo esc_url($clear_url); ?>" class="lmd-filter-opt lmd-filter-opt-tous">Tous</a>
                <?php if ($sort_criterion) : ?>
                <span class="lmd-sort-arrows">
                    <button type="button" class="lmd-sort-btn" data-sort-asc="1" title="Tri croissant">↑</button>
                    <button type="button" class="lmd-sort-btn" data-sort-asc="0" title="Tri décroissant">↓</button>
                </span>
                <?php endif; ?>
            </div>
            <?php
            if ($is_obj) {
                foreach ($opts as $o) :
                    $s = $o->slug; $n = $o->name;
                    $c = function_exists('lmd_get_tag_filter_colors') ? lmd_get_tag_filter_colors($type) : ['bg'=>'#f9fafb','text'=>'#374151'];
                    $checked = in_array($s, $selected);
            ?>
            <label class="lmd-filter-opt <?php echo $checked ? 'selected' : ''; ?>"><input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($s); ?>" <?php checked($checked); ?>><span class="lmd-filter-opt-txt" style="--opt-bg:<?php echo esc_attr($c['bg']); ?>;--opt-text:<?php echo esc_attr($c['text']); ?>;--opt-border:<?php echo esc_attr($c['border'] ?? $c['bg']); ?>"><?php echo esc_html($n); ?></span></label>
            <?php endforeach;
            } else {
                foreach ($opts as $o) :
                    $s = $o['slug'] ?? ''; $n = $o['name'] ?? $s;
                    $c = function_exists('lmd_get_tag_filter_colors') ? lmd_get_tag_filter_colors($type, $s) : ['bg'=>'#f9fafb','text'=>'#374151','border'=>'#e5e7eb'];
                    $checked = in_array($s, $selected);
            ?>
            <label class="lmd-filter-opt <?php echo $checked ? 'selected' : ''; ?>"><input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($s); ?>" <?php checked($checked); ?>><span class="lmd-filter-opt-txt" style="--opt-bg:<?php echo esc_attr($c['bg']); ?>;--opt-text:<?php echo esc_attr($c['text']); ?>;--opt-border:<?php echo esc_attr($c['border'] ?? $c['bg']); ?>"><?php echo esc_html($n); ?></span></label>
            <?php endforeach; } ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function lmd_list_photo_url($estimation) {
    if (empty($estimation->photos)) return '';
    $decoded = json_decode($estimation->photos, true);
    $photos = is_array($decoded) ? $decoded : (is_string($estimation->photos) ? [$estimation->photos] : []);
    if (empty($photos)) return '';
    $item = $photos[0];
    $path = is_string($item) ? $item : ($item['url'] ?? $item['file'] ?? $item['path'] ?? '');
    if (!$path || !is_string($path)) return '';
    $upload = wp_upload_dir();
    $basedir = $upload['basedir'];
    $baseurl = $upload['baseurl'];
    if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) return $path;
    $fullpath = strpos($path, $basedir) === 0 ? $path : $basedir . '/' . ltrim(str_replace('\\', '/', $path), '/');
    if (file_exists($fullpath)) return str_replace($basedir, $baseurl, $fullpath);
    return $baseurl . '/' . ltrim(str_replace('\\', '/', $path), '/');
}

?>
<div class="wrap lmd-estimations-list lmd-page" id="lmd-estimations-list-wrap">
<style>
/* Override WordPress admin - styles obligatoires pour le rendu */
#lmd-estimations-list-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; font-size: 14px !important; color: #374151 !important; max-width: 1600px !important; margin: 0 auto !important; padding: 24px !important; box-sizing: border-box !important; }
#lmd-estimations-list-wrap *, #lmd-estimations-list-wrap *::before, #lmd-estimations-list-wrap *::after { box-sizing: inherit !important; }
#lmd-estimations-list-wrap .lmd-header-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 16px !important; margin-bottom: 16px !important; flex-wrap: wrap !important; }
#lmd-estimations-list-wrap .lmd-header-row-not-sticky { position: relative !important; z-index: 1 !important; }
#lmd-estimations-list-wrap .lmd-header-row h1 { font-size: 24px !important; font-weight: 700 !important; margin: 0 !important; color: #111827 !important; }
#lmd-estimations-list-wrap .lmd-search-wrap { position: relative !important; min-width: 200px !important; display: flex !important; align-items: center !important; gap: 8px !important; }
#lmd-estimations-list-wrap .lmd-search-wrap .button { flex-shrink: 0 !important; }
#lmd-estimations-list-wrap .lmd-search-wrap input { padding: 8px 12px 8px 36px !important; border: 1px solid #e5e7eb !important; border-radius: 8px !important; font-size: 13px !important; background: #fff !important; width: 100% !important; }
#lmd-estimations-list-wrap .lmd-search-wrap input:focus { outline: none !important; border-color: #22c55e !important; }
#lmd-estimations-list-wrap .lmd-search-wrap::before { content: "🔍" !important; position: absolute !important; left: 12px !important; top: 50% !important; transform: translateY(-50%) !important; font-size: 14px !important; opacity: 0.6 !important; }
#lmd-estimations-list-wrap .lmd-filter-bar-sticky-wrap { position: relative !important; }
#lmd-estimations-list-wrap .lmd-filter-bar { display: flex !important; align-items: center !important; gap: 0 !important; margin-bottom: 12px !important; padding: 10px 14px !important; background: #f9fafb !important; border: 1px solid #e5e7eb !important; border-radius: 10px !important; flex-wrap: wrap !important; z-index: 100 !important; transition: box-shadow 0.2s !important; }
#lmd-estimations-list-wrap .lmd-filter-bar.lmd-filter-bar-stuck { position: fixed !important; margin: 0 !important; box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important; }
#lmd-estimations-list-wrap .lmd-filter-bar-group { display: flex !important; align-items: center !important; gap: 8px !important; flex-shrink: 0 !important; }
#lmd-estimations-list-wrap .lmd-filter-bar-group + .lmd-filter-bar-group { margin-left: 24px !important; }
#lmd-estimations-list-wrap .lmd-filter-bar-group-tous { display: flex !important; align-items: center !important; gap: 8px !important; flex-shrink: 0 !important; }
#lmd-estimations-list-wrap .lmd-filter-reset { padding: 8px 14px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; color: #6b7280 !important; cursor: pointer !important; white-space: nowrap !important; flex-shrink: 0 !important; text-decoration: none !important; display: inline-flex !important; align-items: center !important; }
#lmd-estimations-list-wrap .lmd-filter-reset:hover { border-color: #22c55e !important; color: #22c55e !important; }
#lmd-estimations-list-wrap .lmd-filter-group { display: flex !important; flex-direction: column !important; gap: 6px !important; min-width: 140px !important; }
#lmd-estimations-list-wrap .lmd-filter-group .lmd-filter-group-label { font-size: 11px !important; font-weight: 600 !important; text-transform: uppercase !important; color: #6b7280 !important; margin-bottom: 2px !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown { position: relative !important; flex-shrink: 0 !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-trigger.lmd-filter-trigger-compact { display: flex !important; align-items: center !important; gap: 6px !important; padding: 8px 12px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-left-width: 4px !important; border-radius: 8px !important; cursor: pointer !important; font-size: 13px !important; white-space: nowrap !important; transition: border-color 0.2s, box-shadow 0.2s !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-trigger.lmd-filter-trigger-compact:hover:not([disabled]) { border-color: #22c55e !important; box-shadow: 0 0 0 2px rgba(34,197,94,0.15) !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-trigger .lmd-filter-dd-name { font-weight: 500 !important; color: #374151 !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-trigger .lmd-filter-dd-arrow { font-size: 10px !important; color: #9ca3af !important; transition: transform 0.2s !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown.open .lmd-filter-dd-arrow { transform: rotate(180deg) !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-panel { display: none !important; position: fixed !important; min-width: 180px !important; max-height: 280px !important; overflow-y: auto !important; padding: 8px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-radius: 8px !important; box-shadow: 0 8px 24px rgba(0,0,0,0.18) !important; z-index: 1000000 !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown.open .lmd-filter-dropdown-panel { display: block !important; }
#lmd-estimations-list-wrap .lmd-filter-opt-tous-row { display: flex !important; align-items: center !important; gap: 8px !important; margin-bottom: 4px !important; }
#lmd-estimations-list-wrap .lmd-filter-opt-tous { padding: 6px 10px !important; font-size: 13px !important; font-weight: 600 !important; color: #6b7280 !important; text-decoration: none !important; border-radius: 6px !important; }
#lmd-estimations-list-wrap .lmd-filter-opt-tous:hover { background: #f3f4f6 !important; color: #374151 !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-panel .lmd-filter-opt { display: flex !important; align-items: center !important; padding: 6px 10px !important; border-radius: 6px !important; font-size: 13px !important; cursor: pointer !important; margin: 0 !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-panel .lmd-filter-opt:hover { background: #f9fafb !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-panel .lmd-filter-opt.selected .lmd-filter-opt-txt { font-weight: 700 !important; background: var(--opt-bg) !important; color: var(--opt-text) !important; border: 1px solid var(--opt-border) !important; padding: 2px 8px !important; border-radius: 4px !important; filter: saturate(0.67) !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-panel input[type="checkbox"] { position: absolute !important; opacity: 0 !important; pointer-events: none !important; width: 0 !important; height: 0 !important; }
#lmd-estimations-list-wrap .lmd-filter-date-panel { min-width: 220px !important; max-height: none !important; }
#lmd-estimations-list-wrap .lmd-filter-date-range { display: flex !important; flex-direction: column !important; gap: 10px !important; padding: 8px 0 !important; }
#lmd-estimations-list-wrap .lmd-filter-date-range label { display: flex !important; align-items: center !important; gap: 8px !important; font-size: 13px !important; }
#lmd-estimations-list-wrap .lmd-filter-date-range label span { min-width: 24px !important; color: #6b7280 !important; }
#lmd-estimations-list-wrap .lmd-filter-date-range input[type="date"] { flex: 1 !important; padding: 6px 10px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; }
#lmd-estimations-list-wrap .lmd-filter-date-range .button { margin-top: 4px !important; }
#lmd-estimations-list-wrap .lmd-selected-tags-row { display: flex !important; flex-wrap: wrap !important; gap: 8px !important; align-items: center !important; padding: 12px 14px !important; margin-bottom: 16px !important; background: #fff !important; border: 1px solid #e5e7eb !important; border-radius: 10px !important; min-height: 44px !important; }
#lmd-estimations-list-wrap .lmd-selected-tag { display: inline-flex !important; align-items: center !important; gap: 4px !important; padding: 6px 10px !important; border-radius: 6px !important; font-size: 13px !important; font-weight: 600 !important; background: var(--st-bg) !important; color: var(--st-text) !important; border: 1px solid var(--st-border) !important; text-decoration: none !important; transition: opacity 0.2s !important; filter: saturate(0.67) !important; }
#lmd-estimations-list-wrap .lmd-selected-tag:hover { opacity: 0.85 !important; }
#lmd-estimations-list-wrap .lmd-selected-tag .lmd-selected-tag-x { font-size: 16px !important; font-weight: 400 !important; opacity: 0.7 !important; }
#lmd-estimations-list-wrap .lmd-grid-cols-selector { display: flex !important; align-items: center !important; gap: 8px !important; flex-shrink: 0 !important; }
#lmd-estimations-list-wrap .lmd-grid-cols-label { font-size: 13px !important; color: #6b7280 !important; font-weight: 500 !important; }
#lmd-estimations-list-wrap .lmd-grid-cols-btn { padding: 8px 14px !important; border: 2px solid #e5e7eb !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 600 !important; color: #6b7280 !important; text-decoration: none !important; transition: border-color 0.2s, background 0.2s, color 0.2s !important; background: #fff !important; cursor: pointer !important; font-family: inherit !important; }
#lmd-estimations-list-wrap .lmd-grid-cols-btn:hover { border-color: #22c55e !important; color: #22c55e !important; }
#lmd-estimations-list-wrap .lmd-grid-cols-btn.active { border-color: #22c55e !important; background: #d1fae5 !important; color: #065f46 !important; }
#lmd-estimations-list-wrap .lmd-filter-dropdown-panel .lmd-sort-arrows { display: flex !important; gap: 4px !important; }
#lmd-estimations-list-wrap .lmd-sort-btn { width: 32px !important; height: 28px !important; padding: 0 !important; border: 2px solid #e5e7eb !important; border-radius: 6px !important; background: #fff !important; font-size: 14px !important; font-weight: 600 !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; color: #6b7280 !important; font-family: inherit !important; }
#lmd-estimations-list-wrap .lmd-sort-btn:hover { border-color: #22c55e !important; color: #22c55e !important; background: #f0fdf4 !important; }
#lmd-estimations-list-wrap .lmd-cards-grid { display: grid !important; gap: 20px !important; }
#lmd-estimations-list-wrap .lmd-cards-grid.cols-5 { grid-template-columns: repeat(5, 1fr) !important; }
#lmd-estimations-list-wrap .lmd-cards-grid.cols-4 { grid-template-columns: repeat(4, 1fr) !important; }
#lmd-estimations-list-wrap .lmd-cards-grid.cols-3 { grid-template-columns: repeat(3, 1fr) !important; }
@media (max-width: 768px) { #lmd-estimations-list-wrap .lmd-cards-grid.cols-5, #lmd-estimations-list-wrap .lmd-cards-grid.cols-4, #lmd-estimations-list-wrap .lmd-cards-grid.cols-3 { grid-template-columns: repeat(2, 1fr) !important; } }
@media (max-width: 500px) { #lmd-estimations-list-wrap .lmd-cards-grid.cols-5, #lmd-estimations-list-wrap .lmd-cards-grid.cols-4, #lmd-estimations-list-wrap .lmd-cards-grid.cols-3 { grid-template-columns: 1fr !important; } }
#lmd-estimations-list-wrap .lmd-card { background: #fff !important; border: 2px solid #e5e7eb !important; border-radius: 12px !important; overflow: visible !important; transition: border-color 0.2s, box-shadow 0.2s !important; text-decoration: none !important; color: inherit !important; display: block !important; position: relative !important; }
#lmd-estimations-list-wrap .lmd-card:hover { border-color: #22c55e !important; box-shadow: 0 4px 12px rgba(34,197,94,0.15) !important; }
#lmd-estimations-list-wrap .lmd-card-img-wrap { position: relative !important; aspect-ratio: 4/3 !important; background: #f3f4f6 !important; overflow: hidden !important; }
#lmd-estimations-list-wrap .lmd-card-overlay-btns { position: absolute !important; top: 8px !important; right: 8px !important; display: flex !important; flex-direction: column !important; gap: 6px !important; z-index: 5 !important; }
#lmd-estimations-list-wrap .lmd-card-drag-handle { flex-shrink: 0 !important; width: 28px !important; height: 28px !important; border: 2px solid transparent !important; border-radius: 6px !important; font-size: 14px !important; cursor: grab !important; display: flex !important; align-items: center !important; justify-content: center !important; background: rgba(255,255,255,0.9) !important; color: #6b7280 !important; line-height: 1 !important; }
#lmd-estimations-list-wrap .lmd-card-drag-handle:hover { background: #fff !important; color: #374151 !important; }
#lmd-estimations-list-wrap .lmd-card-drag-handle:active { cursor: grabbing !important; }
#lmd-estimations-list-wrap .lmd-card-wrapper { display: block !important; }
#lmd-estimations-list-wrap .lmd-card-wrapper.lmd-sortable-chosen { opacity: 1 !important; box-shadow: 0 12px 32px rgba(0,0,0,0.2) !important; z-index: 1000 !important; cursor: grabbing !important; }
#lmd-estimations-list-wrap .lmd-card-wrapper.lmd-sortable-ghost { background: #e5e7eb !important; border: 2px dashed #9ca3af !important; border-radius: 12px !important; min-height: 120px !important; opacity: 0.6 !important; }
#lmd-estimations-list-wrap .lmd-card-ia-btn, #lmd-estimations-list-wrap .lmd-card-trash { flex-shrink: 0 !important; width: 28px !important; height: 28px !important; border: 2px solid transparent !important; border-radius: 6px !important; font-size: 12px !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; opacity: 0.9 !important; transition: opacity 0.2s, background 0.2s, border-color 0.2s !important; }
#lmd-estimations-list-wrap .lmd-card-ia-btn { background: #d1fae5 !important; color: #065f46 !important; font-weight: 600 !important; }
#lmd-estimations-list-wrap .lmd-card-ia-btn:hover { opacity: 1 !important; background: #a7f3d0 !important; }
#lmd-estimations-list-wrap .lmd-card-ia-btn.selected { border: 3px solid #059669 !important; }
#lmd-estimations-list-wrap .lmd-card-ia-btn.lmd-card-ia-btn-disabled { background: #e5e7eb !important; color: #9ca3af !important; cursor: not-allowed !important; opacity: 0.7 !important; }
#lmd-estimations-list-wrap .lmd-card-ia-btn.lmd-card-ia-btn-disabled:hover { background: #e5e7eb !important; opacity: 0.7 !important; }
#lmd-estimations-list-wrap .lmd-card-img { width: 100% !important; height: 100% !important; object-fit: contain !important; object-position: center !important; display: block !important; }
#lmd-estimations-list-wrap .lmd-card-img-placeholder { width: 100% !important; height: 100% !important; display: flex !important; align-items: center !important; justify-content: center !important; color: #9ca3af !important; font-size: 48px !important; }
#lmd-estimations-list-wrap .lmd-card-body { padding: 16px !important; }
#lmd-estimations-list-wrap .lmd-card-title-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 8px !important; margin-bottom: 8px !important; }
#lmd-estimations-list-wrap .lmd-card-title { font-size: 16px !important; font-weight: 600 !important; margin: 0 !important; color: #111827 !important; line-height: 1.3 !important; flex: 1 !important; min-width: 0 !important; }
#lmd-estimations-list-wrap .lmd-card-desc { font-size: 13px !important; color: #6b7280 !important; line-height: 1.5 !important; margin: 0 0 12px !important; display: -webkit-box !important; -webkit-box-orient: vertical !important; overflow: hidden !important; }
#lmd-estimations-list-wrap .lmd-cards-grid.cols-5 .lmd-card-desc { -webkit-line-clamp: 2 !important; }
#lmd-estimations-list-wrap .lmd-cards-grid.cols-4 .lmd-card-desc { -webkit-line-clamp: 3 !important; }
#lmd-estimations-list-wrap .lmd-cards-grid.cols-3 .lmd-card-desc { -webkit-line-clamp: 4 !important; }
#lmd-estimations-list-wrap .lmd-card-meta { display: flex !important; flex-wrap: wrap !important; align-items: center !important; gap: 8px !important; margin-bottom: 12px !important; }
#lmd-estimations-list-wrap .lmd-card-tag { display: inline-flex !important; align-items: center !important; gap: 4px !important; padding: 4px 10px !important; border-radius: 6px !important; font-size: 12px !important; font-weight: 500 !important; background: #f3f4f6 !important; color: #374151 !important; border: 1px solid #e5e7eb !important; filter: saturate(0.67) !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-message { background: #eff6ff !important; color: #1d4ed8 !important; border-color: #bfdbfe !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-vendu { background: #fef2f2 !important; color: #b91c1c !important; border-color: #fecaca !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-interet { background: #fefce8 !important; color: #a16207 !important; border-color: #fef08a !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-source-cp { background: #dbeafe !important; color: #1d4ed8 !important; border-color: #93c5fd !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-source-avis2 { background: #ede9fe !important; color: #6d28d9 !important; border-color: #c4b5fd !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-source-ia { background: #d1fae5 !important; color: #065f46 !important; border-color: #6ee7b7 !important; }
#lmd-estimations-list-wrap .lmd-card-tag.lmd-card-tag-mini { font-size: 10px !important; padding: 2px 6px !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-retard { background: #fef2f2 !important; color: #dc2626 !important; border-color: #fecaca !important; font-weight: 600 !important; }
#lmd-estimations-list-wrap .lmd-card-tag.tag-retard-7j { background: #dc2626 !important; color: #fff !important; border-color: #b91c1c !important; font-weight: 700 !important; }
#lmd-estimations-list-wrap .lmd-card-date { font-size: 12px !important; color: #9ca3af !important; }
#lmd-estimations-list-wrap .lmd-card-trash { background: #e5e7eb !important; color: #374151 !important; font-size: 14px !important; }
#lmd-estimations-list-wrap .lmd-card-trash:hover { opacity: 1 !important; background: #d1d5db !important; }
#lmd-estimations-list-wrap .lmd-card-trash.selected { border: 3px solid #dc2626 !important; }
#lmd-estimations-list-wrap .lmd-card.selected-delete { border-color: #dc2626 !important; box-shadow: 0 4px 12px rgba(220,38,38,0.2) !important; }
#lmd-estimations-list-wrap .lmd-card.selected-analysis { border-color: #22c55e !important; box-shadow: 0 4px 12px rgba(34,197,94,0.2) !important; }
#lmd-estimations-list-wrap .lmd-card.lmd-card-vendu { border: 2px solid #dc2626 !important; background: #fef2f2 !important; }
#lmd-estimations-list-wrap .lmd-card.lmd-card-vendu .lmd-card-img-wrap { background: #fee2e2 !important; }
#lmd-estimations-list-wrap .lmd-card.lmd-card-vendu:hover { border-color: #b91c1c !important; box-shadow: 0 4px 12px rgba(220,38,38,0.2) !important; }
#lmd-estimations-list-wrap .lmd-card.lmd-card-programme { border: 2px solid #3b82f6 !important; background: #eff6ff !important; }
#lmd-estimations-list-wrap .lmd-card.lmd-card-programme .lmd-card-img-wrap { background: #dbeafe !important; }
#lmd-estimations-list-wrap .lmd-card.lmd-card-programme:hover { border-color: #2563eb !important; box-shadow: 0 4px 12px rgba(59,130,246,0.2) !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar { position: fixed !important; bottom: 24px !important; left: 50% !important; transform: translateX(-50%) !important; display: none !important; align-items: center !important; gap: 16px !important; padding: 12px 20px !important; background: #1f2937 !important; color: #fff !important; border-radius: 12px !important; box-shadow: 0 8px 24px rgba(0,0,0,0.3) !important; z-index: 10000 !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar.visible { display: flex !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar-count { font-weight: 600 !important; font-size: 14px !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar .lmd-bulk-btn { padding: 8px 14px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 600 !important; cursor: pointer !important; border: none !important; background: #374151 !important; color: #fff !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar .lmd-bulk-btn:hover { background: #4b5563 !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar .lmd-bulk-btn-delete { background: #dc2626 !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar .lmd-bulk-btn-delete:hover { background: #b91c1c !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar-ia { background: #065f46 !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar-ia .lmd-bulk-btn-analyze { background: #22c55e !important; color: #fff !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar-ia .lmd-bulk-btn-analyze:hover { background: #16a34a !important; }
#lmd-estimations-list-wrap .lmd-bulk-bar-ia.lmd-polling .lmd-bulk-toggle-ia { display: none !important; }
#lmd-estimations-list-wrap .lmd-empty { text-align: center !important; padding: 48px 24px !important; color: #6b7280 !important; font-size: 15px !important; }
#lmd-estimations-list-wrap .button { padding: 8px 16px !important; border-radius: 8px !important; font-size: 13px !important; cursor: pointer !important; }
</style>

<form method="get" action="" class="lmd-filter-form" id="lmd-filter-form">
    <input type="hidden" name="page" value="lmd-estimations-list" />
    <?php if ($status) : ?><input type="hidden" name="status" value="<?php echo esc_attr($status); ?>" /><?php endif; ?>
    <input type="hidden" name="cols" value="<?php echo (int) $grid_cols; ?>" />
    <div class="lmd-header-row lmd-header-row-not-sticky">
        <h1 style="margin:0;font-size:24px;font-weight:700;color:#111827;">Demandes d'estimation</h1>
        <div class="lmd-search-wrap">
            <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Nom, objet, catégorie, email…" />
            <button type="submit" class="button">Rechercher</button>
        </div>
    </div>
    <div class="lmd-filter-bar-sticky-wrap">
    <div class="lmd-filter-bar" id="lmd-filter-bar">
        <div class="lmd-filter-bar-group lmd-filter-bar-group-tous">
            <?php $tous_params = ['page' => 'lmd-estimations-list', 'cols' => $grid_cols]; if ($status) $tous_params['status'] = $status; $tous_url = admin_url('admin.php?' . http_build_query($tous_params, '', '&', PHP_QUERY_RFC3986)); ?>
            <a href="<?php echo esc_url($tous_url); ?>" class="lmd-filter-reset lmd-filter-reset-tous">Tous <?php echo (int) $counts_exchanges['tous']; ?></a>
            <?php if ($counts_exchanges['retard_7j'] > 0) : ?><span class="lmd-card-tag tag-retard"><?php echo (int) $counts_exchanges['retard_7j']; ?> retard &lt; 7j</span><?php endif; ?>
            <?php if ($counts_exchanges['retard_plus'] > 0) : ?><span class="lmd-card-tag tag-retard-7j"><?php echo (int) $counts_exchanges['retard_plus']; ?> retard &gt; 7j</span><?php endif; ?>
            <?php echo lmd_filter_dropdown('filter_message', 'Échanges', $opts_message, $filter_message, 'message', false, !empty($estimations) ? ['sort_criterion' => 'message'] : []); ?>
        </div>
        <div class="lmd-filter-bar-group">
            <?php echo lmd_filter_dropdown('filter_estimation', 'Estimation', $opts_estimation, $filter_estimation, 'estimation', false, !empty($estimations) ? ['panel_min_width' => 130, 'sort_criterion' => 'estimate'] : ['panel_min_width' => 130]); ?>
            <?php echo lmd_filter_dropdown('filter_interet', 'Intérêt', $opts_interet, $filter_interet, 'interet', false, !empty($estimations) ? ['sort_criterion' => 'interet'] : []); ?>
            <?php echo lmd_filter_dropdown('filter_theme_vente', 'Catégorie', $opts_theme_vente, $filter_theme_vente, 'theme_vente', false, !empty($estimations) ? ['panel_min_width' => 260, 'sort_criterion' => 'theme_vente'] : ['panel_min_width' => 260]); ?>
            <?php
            $has_date_envoi = $filter_date_envoi_from !== '' || $filter_date_envoi_to !== '';
            $border_date = $has_date_envoi ? '#22c55e' : '#e5e7eb';
            ?>
            <div class="lmd-filter-dropdown lmd-filter-date-envoi" data-name="filter_date_envoi" data-panel-min-width="220"<?php echo !empty($estimations) ? ' data-sort-criterion="date"' : ''; ?>>
                <button type="button" class="lmd-filter-dropdown-trigger lmd-filter-trigger-compact" style="border-left-color:<?php echo esc_attr($border_date); ?>">
                    <span class="lmd-filter-dd-name">Période envoi</span>
                    <span class="lmd-filter-dd-arrow">▾</span>
                </button>
                <div class="lmd-filter-dropdown-panel lmd-filter-date-panel">
                    <div class="lmd-filter-opt-tous-row">
                        <a href="<?php echo esc_url(lmd_filter_clear_url('filter_date_envoi')); ?>" class="lmd-filter-opt lmd-filter-opt-tous">Tous</a>
                        <?php if (!empty($estimations)) : ?>
                        <span class="lmd-sort-arrows">
                            <button type="button" class="lmd-sort-btn" data-sort-asc="1" title="Tri croissant">↑</button>
                            <button type="button" class="lmd-sort-btn" data-sort-asc="0" title="Tri décroissant">↓</button>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="lmd-filter-date-range">
                        <label><span>Du</span> <input type="date" name="filter_date_envoi_from" value="<?php echo esc_attr($filter_date_envoi_from); ?>" /></label>
                        <label><span>Au</span> <input type="date" name="filter_date_envoi_to" value="<?php echo esc_attr($filter_date_envoi_to); ?>" /></label>
                        <button type="submit" class="button">Appliquer</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="lmd-filter-bar-group">
            <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-ventes-list')); ?>" class="lmd-filter-reset">Vente</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-vendeurs-list')); ?>" class="lmd-filter-reset">Vendeur</a>
        </div>
        <div class="lmd-grid-cols-selector" role="group" aria-label="Nombre de vignettes par ligne" style="margin-left:auto;">
            <span class="lmd-grid-cols-label">Vignettes — Choix</span>
            <?php foreach ([5 => '5', 4 => '4', 3 => '3'] as $n => $label) : ?>
            <button type="button" class="lmd-grid-cols-btn <?php echo $grid_cols === $n ? 'active' : ''; ?>" data-cols="<?php echo (int) $n; ?>"><?php echo esc_html($label); ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    </div>
    <?php
    $all_selected = [];
    $label_map = [
        'filter_message' => ['Échanges', $opts_message, 'message', false],
        'filter_interet' => ['Intérêt', $opts_interet, 'interet', false],
        'filter_estimation' => ['Estimation', $opts_estimation, 'estimation', false],
        'filter_theme_vente' => ['Catégorie', $opts_theme_vente, 'theme_vente', false],
        'filter_date_vente' => ['Vente', $opts_date_vente, 'date_vente', true],
        'filter_vendeur' => ['Vendeur', $opts_vendeur, 'vendeur', true],
    ];
    foreach (['filter_message','filter_interet','filter_estimation','filter_theme_vente','filter_date_vente','filter_vendeur'] as $fn) {
        $sel = isset($$fn) ? (array) $$fn : [];
        if (empty($sel)) continue;
        $info = $label_map[$fn] ?? null;
        if (!$info) continue;
        list($cat_label, $opts, $type, $is_obj) = $info;
        foreach ($sel as $slug) {
            $name = $slug;
            if ($is_obj && !empty($opts)) {
                foreach ($opts as $o) { if (($o->slug ?? '') === $slug) { $name = $o->name ?? $slug; break; } }
            } elseif (!empty($opts)) {
                foreach ($opts as $o) { if (($o['slug'] ?? '') === $slug) { $name = $o['name'] ?? $slug; break; } }
            }
            $c = function_exists('lmd_get_tag_filter_colors') ? lmd_get_tag_filter_colors($type, $slug) : ['bg'=>'#f9fafb','text'=>'#374151','border'=>'#e5e7eb'];
            $all_selected[] = ['name'=>$fn,'slug'=>$slug,'label'=>$name,'colors'=>$c];
        }
    }
    if ($filter_date_envoi_from !== '' || $filter_date_envoi_to !== '') {
        $from_fmt = $filter_date_envoi_from ? date_i18n('d/m/Y', strtotime($filter_date_envoi_from)) : '';
        $to_fmt = $filter_date_envoi_to ? date_i18n('d/m/Y', strtotime($filter_date_envoi_to)) : '';
        $label_date = $from_fmt && $to_fmt ? $from_fmt . ' – ' . $to_fmt : ($from_fmt ? 'À partir du ' . $from_fmt : 'Jusqu\'au ' . $to_fmt);
        $all_selected[] = ['name'=>'filter_date_envoi','slug'=>'','label'=>$label_date,'colors'=>['bg'=>'#f0fdf4','text'=>'#166534','border'=>'#22c55e']];
    }
    ?>
    <?php if (!empty($all_selected)) : ?>
    <div class="lmd-selected-tags-row">
        <?php foreach ($all_selected as $item) :
            $url = lmd_filter_remove_one_url($item['name'], $item['slug']);
            $c = $item['colors'];
        ?>
        <a href="<?php echo esc_url($url); ?>" class="lmd-selected-tag" style="--st-bg:<?php echo esc_attr($c['bg']); ?>;--st-text:<?php echo esc_attr($c['text']); ?>;--st-border:<?php echo esc_attr($c['border'] ?? $c['bg']); ?>"><?php echo esc_html($item['label']); ?> <span class="lmd-selected-tag-x">×</span></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</form>
<script>
(function(){
    var urlParams = new URLSearchParams(window.location.search);
    var colsParam = urlParams.get('cols');
    var storedCols = null;
    try { storedCols = localStorage.getItem('lmd_grid_cols'); } catch(_) {}
    if (!colsParam && storedCols && ['3','4','5'].indexOf(storedCols) >= 0) {
        urlParams.set('cols', storedCols);
        var newUrl = window.location.pathname + '?' + urlParams.toString();
        window.location.replace(newUrl);
        return;
    }
    document.querySelectorAll('.lmd-grid-cols-btn').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            var cols = btn.getAttribute('data-cols');
            if (!cols || ['3','4','5'].indexOf(cols) < 0) return;
            try { localStorage.setItem('lmd_grid_cols', cols); } catch(_) {}
            var url = new URL(window.location.href);
            url.searchParams.set('cols', cols);
            window.location.href = url.toString();
        });
    });
    (function initStickyFilterBar() {
        var bar = document.getElementById('lmd-filter-bar');
        var wrap = document.getElementById('lmd-estimations-list-wrap');
        if (!bar || !wrap) return;
        var stickyWrap = bar.closest('.lmd-filter-bar-sticky-wrap');
        var placeholder = null;
        var adminBarOffset = document.body.classList.contains('admin-bar') ? (window.innerWidth <= 782 ? 46 : 32) : 0;
        function updateSticky() {
            adminBarOffset = document.body.classList.contains('admin-bar') ? (window.innerWidth <= 782 ? 46 : 32) : 0;
            var wrapRect = wrap.getBoundingClientRect();
            var threshold = adminBarOffset || 0;
            var isStuck = bar.classList.contains('lmd-filter-bar-stuck');
            if (isStuck && placeholder) {
                var phTop = placeholder.getBoundingClientRect().top;
                if (phTop > threshold) {
                    bar.classList.remove('lmd-filter-bar-stuck');
                    bar.style.removeProperty('left');
                    bar.style.removeProperty('width');
                    bar.style.removeProperty('top');
                    placeholder.style.height = '0';
                } else {
                    bar.style.setProperty('top', adminBarOffset + 'px', 'important');
                    bar.style.setProperty('left', wrapRect.left + 'px', 'important');
                    bar.style.setProperty('width', wrapRect.width + 'px', 'important');
                    placeholder.style.height = bar.offsetHeight + 'px';
                }
            } else {
                var barRect = bar.getBoundingClientRect();
                if (barRect.top <= threshold) {
                    if (!isStuck) {
                        bar.classList.add('lmd-filter-bar-stuck');
                        if (!placeholder) {
                            placeholder = document.createElement('div');
                            placeholder.style.flexShrink = '0';
                            stickyWrap.insertBefore(placeholder, bar);
                        }
                    }
                    bar.style.setProperty('top', adminBarOffset + 'px', 'important');
                    bar.style.setProperty('left', wrapRect.left + 'px', 'important');
                    bar.style.setProperty('width', wrapRect.width + 'px', 'important');
                    if (placeholder) placeholder.style.height = bar.offsetHeight + 'px';
                }
            }
        }
        window.addEventListener('scroll', updateSticky, { passive: true });
        window.addEventListener('resize', updateSticky);
        updateSticky();
    })();
    var form = document.getElementById('lmd-filter-form');
    form.querySelectorAll('input[type="checkbox"][name^="filter_"]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var lbl = this.closest('label');
            if (lbl) lbl.classList.toggle('selected', this.checked);
            form.submit();
        });
    });
    function positionPanel(dd) {
        var trigger = dd.querySelector('.lmd-filter-dropdown-trigger');
        var panel = dd.querySelector('.lmd-filter-dropdown-panel');
        if (!trigger || !panel) return;
        var r = trigger.getBoundingClientRect();
        var minW = parseInt(dd.getAttribute('data-panel-min-width') || '180', 10);
        panel.style.top = (r.bottom + 4) + 'px';
        panel.style.left = r.left + 'px';
        panel.style.width = Math.max(r.width, minW) + 'px';
    }
    form.querySelectorAll('.lmd-filter-dropdown-trigger:not([disabled])').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var dd = this.closest('.lmd-filter-dropdown');
            form.querySelectorAll('.lmd-filter-dropdown').forEach(function(d){ if(d!==dd) d.classList.remove('open'); });
            dd.classList.toggle('open');
            if (dd.classList.contains('open')) positionPanel(dd);
        });
    });
    document.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('.lmd-filter-dropdown-panel')) return;
        form.querySelectorAll('.lmd-filter-dropdown').forEach(function(d){ d.classList.remove('open'); });
    });
    window.addEventListener('scroll', function() {
        form.querySelectorAll('.lmd-filter-dropdown.open').forEach(function(dd){ positionPanel(dd); });
    }, true);
    window.addEventListener('resize', function() {
        form.querySelectorAll('.lmd-filter-dropdown.open').forEach(function(dd){ positionPanel(dd); });
    });
    var selected = {};
    var selectedForAnalysis = {};

    function updateCardBorders() {
        document.querySelectorAll('.lmd-card[data-id]').forEach(function(card) {
            var id = card.getAttribute('data-id');
            card.classList.remove('selected-delete', 'selected-analysis');
            if (selected[id]) card.classList.add('selected-delete');
            else if (selectedForAnalysis[id]) card.classList.add('selected-analysis');
        });
    }

    function updateBulkBars() {
        var allCards = document.querySelectorAll('.lmd-card[data-id]');
        var allTrash = document.querySelectorAll('.lmd-card-trash');
        var allIa = document.querySelectorAll('.lmd-card-ia-btn');
        var nDel = Object.keys(selected).length;
        var nIa = Object.keys(selectedForAnalysis).length;

        var bar = document.getElementById('lmd-bulk-bar');
        var countEl = document.getElementById('lmd-bulk-count');
        var toggleBtn = document.getElementById('lmd-bulk-toggle');
        if (bar) bar.classList.toggle('visible', nDel > 0);
        if (countEl) countEl.textContent = nDel + ' estimation(s) sélectionnée(s)';
        if (toggleBtn) toggleBtn.textContent = (nDel === allTrash.length) ? 'Tout désélectionner' : 'Tout sélectionner';
        allTrash.forEach(function(btn) {
            var id = btn.getAttribute('data-id');
            btn.classList.toggle('selected', !!selected[id]);
        });

        var barIa = document.getElementById('lmd-bulk-bar-ia');
        var countElIa = document.getElementById('lmd-bulk-count-ia');
        var toggleBtnIa = document.getElementById('lmd-bulk-toggle-ia');
        if (barIa) barIa.classList.toggle('visible', nIa > 0);
        if (countElIa) countElIa.textContent = nIa + ' estimation(s) sélectionnée(s)';
        var allIaSelectable = Array.prototype.filter.call(allIa, function(b) { return b.getAttribute('data-analyzed') !== '1'; });
        if (toggleBtnIa) toggleBtnIa.textContent = (nIa === allIaSelectable.length) ? 'Tout désélectionner' : 'Tout sélectionner';
        allIa.forEach(function(btn) {
            var id = btn.getAttribute('data-id');
            btn.classList.toggle('selected', !!selectedForAnalysis[id]);
        });

        updateCardBorders();
    }

    function attachTrashHandlers() {
        document.querySelectorAll('.lmd-card-trash').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                var id = btn.getAttribute('data-id');
                if (selected[id]) {
                    delete selected[id];
                } else {
                    selectedForAnalysis = {};
                    selected[id] = true;
                }
                updateBulkBars();
            });
        });
    }

    function initSortButtons() {
        var grid = document.getElementById('lmd-cards-grid');
        if (!grid) return;
        document.body.addEventListener('click', function(e) {
            var btn = e.target && e.target.closest ? e.target.closest('.lmd-sort-btn') : null;
            if (!btn) return;
            var dd = btn.closest('.lmd-filter-dropdown[data-sort-criterion]');
            if (!dd || !grid) return;
            e.preventDefault();
            e.stopPropagation();
            var criterion = dd.getAttribute('data-sort-criterion') || 'message';
            var asc = btn.getAttribute('data-sort-asc') === '1';
            applySort(grid, criterion, asc);
        });
        function applySort(grid, criterion, asc) {
            var wrappers = Array.from(grid.querySelectorAll('.lmd-card-wrapper[data-id]'));
            if (wrappers.length === 0) return;
            var mult = asc ? 1 : -1;
            wrappers.sort(function(a, b) {
                var va, vb;
                if (criterion === 'message') {
                    va = parseInt(a.getAttribute('data-sort-message') || '5', 10);
                    vb = parseInt(b.getAttribute('data-sort-message') || '5', 10);
                } else if (criterion === 'estimate') {
                    va = parseFloat(a.getAttribute('data-sort-estimate') || '999999999');
                    vb = parseFloat(b.getAttribute('data-sort-estimate') || '999999999');
                } else if (criterion === 'theme_vente') {
                    va = (a.getAttribute('data-sort-theme') || '').toLowerCase();
                    vb = (b.getAttribute('data-sort-theme') || '').toLowerCase();
                } else if (criterion === 'interet') {
                    va = parseInt(a.getAttribute('data-sort-interet') || '6', 10);
                    vb = parseInt(b.getAttribute('data-sort-interet') || '6', 10);
                } else if (criterion === 'date') {
                    va = parseInt(a.getAttribute('data-sort-date') || '0', 10);
                    vb = parseInt(b.getAttribute('data-sort-date') || '0', 10);
                } else return 0;
                if (va < vb) return -1 * mult;
                if (va > vb) return 1 * mult;
                return 0;
            });
            wrappers.forEach(function(w) { grid.appendChild(w); });
            var ids = [];
            grid.querySelectorAll('.lmd-card-wrapper[data-id]').forEach(function(w) { ids.push(w.getAttribute('data-id')); });
            try { localStorage.setItem('lmd_estimations_grid_order', JSON.stringify(ids)); } catch(_) {}
        }
    }

    function initSortable() {
        var grid = document.getElementById('lmd-cards-grid');
        if (!grid || !grid.querySelector('.lmd-card-wrapper')) return;
        document.querySelectorAll('.lmd-card-drag-handle').forEach(function(handle) {
            handle.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); });
        });
        if (typeof Sortable === 'undefined') return;
        var storageKey = 'lmd_estimations_grid_order';
        var savedOrder = null;
        try { savedOrder = JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch(_) {}
        if (savedOrder && Array.isArray(savedOrder) && savedOrder.length > 0) {
            var wrappers = Array.from(grid.querySelectorAll('.lmd-card-wrapper[data-id]'));
            var idToWrapper = {};
            wrappers.forEach(function(w) { idToWrapper[w.getAttribute('data-id')] = w; });
            var ordered = [];
            savedOrder.forEach(function(id) {
                if (idToWrapper[id]) { ordered.push(idToWrapper[id]); delete idToWrapper[id]; }
            });
            Object.keys(idToWrapper).forEach(function(id) { ordered.push(idToWrapper[id]); });
            ordered.forEach(function(w) { grid.appendChild(w); });
        }
        new Sortable(grid, {
            handle: '.lmd-card-drag-handle',
            animation: 150,
            ghostClass: 'lmd-sortable-ghost',
            chosenClass: 'lmd-sortable-chosen',
            dragClass: 'lmd-sortable-drag',
            onEnd: function() {
                var ids = [];
                grid.querySelectorAll('.lmd-card-wrapper[data-id]').forEach(function(w) { ids.push(w.getAttribute('data-id')); });
                try { localStorage.setItem(storageKey, JSON.stringify(ids)); } catch(_) {}
            }
        });
    }

    function attachIaHandlers() {
        document.querySelectorAll('.lmd-card-ia-btn').forEach(function(btn) {
            if (btn.getAttribute('data-analyzed') === '1') return;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                var id = btn.getAttribute('data-id');
                if (selectedForAnalysis[id]) {
                    delete selectedForAnalysis[id];
                } else {
                    selected = {};
                    selectedForAnalysis[id] = true;
                }
                updateBulkBars();
            });
        });
    }

    function runBulkInit() {
        var toggleBtn = document.getElementById('lmd-bulk-toggle');
        var deleteBtn = document.getElementById('lmd-bulk-delete');
        var toggleBtnIa = document.getElementById('lmd-bulk-toggle-ia');
        var analyzeBtn = document.getElementById('lmd-bulk-analyze');
        attachTrashHandlers();
        attachIaHandlers();
        initSortButtons();
        initSortable();

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                var tr = document.querySelectorAll('.lmd-card-trash');
                if (Object.keys(selected).length === tr.length) {
                    selected = {};
                } else {
                    selectedForAnalysis = {};
                    tr.forEach(function(btn) {
                        selected[btn.getAttribute('data-id')] = true;
                    });
                }
                updateBulkBars();
            });
        }
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var ids = Object.keys(selected);
                if (ids.length === 0) return;
                if (!confirm('Supprimer ' + ids.length + ' estimation(s) ?')) return;
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo esc_url(admin_url('admin-post.php')); ?>';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'lmd_bulk_delete_estimations';
                form.appendChild(input);
                var nonce = document.createElement('input');
                nonce.type = 'hidden';
                nonce.name = '_wpnonce';
                nonce.value = '<?php echo esc_attr(wp_create_nonce('lmd_bulk_delete')); ?>';
                form.appendChild(nonce);
                ids.forEach(function(id) {
                    var i = document.createElement('input');
                    i.type = 'hidden';
                    i.name = 'ids[]';
                    i.value = id;
                    form.appendChild(i);
                });
                document.body.appendChild(form);
                form.submit();
            });
        }

        if (toggleBtnIa) {
            toggleBtnIa.addEventListener('click', function() {
                var iaBtns = Array.prototype.filter.call(document.querySelectorAll('.lmd-card-ia-btn'), function(b) { return b.getAttribute('data-analyzed') !== '1'; });
                if (Object.keys(selectedForAnalysis).length === iaBtns.length) {
                    selectedForAnalysis = {};
                } else {
                    selected = {};
                    iaBtns.forEach(function(btn) {
                        selectedForAnalysis[btn.getAttribute('data-id')] = true;
                    });
                }
                updateBulkBars();
            });
        }
        if (analyzeBtn) {
            analyzeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var ids = Object.keys(selectedForAnalysis);
                if (ids.length === 0) return;
                if (!confirm('Lancer l\'analyse IA pour ' + ids.length + ' estimation(s) ?')) return;
                var nonce = typeof lmdAdmin !== 'undefined' ? lmdAdmin.nonce : '';
                var ajaxurl = typeof lmdAdmin !== 'undefined' ? lmdAdmin.ajaxurl : '<?php echo esc_url(admin_url("admin-ajax.php")); ?>';
                analyzeBtn.disabled = true;
                analyzeBtn.textContent = 'Lancement...';
                var done = 0;
                var total = ids.length;
                function launchNext() {
                    if (done >= total) {
                        selectedForAnalysis = {};
                        updateBulkBars();
                        startPolling(ids);
                        return;
                    }
                    var id = ids[done];
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        done++;
                        launchNext();
                    };
                    xhr.onerror = function() { done++; launchNext(); };
                    xhr.send('action=lmd_launch_analysis&nonce=' + encodeURIComponent(nonce) + '&id=' + id);
                }
                function startPolling(launchedIds) {
                    var pollInterval = 5000;
                    var maxPolls = 120;
                    var pollCount = 0;
                    var barIa = document.getElementById('lmd-bulk-bar-ia');
                    var countElIa = document.getElementById('lmd-bulk-count-ia');
                    if (barIa) { barIa.classList.add('lmd-polling', 'visible'); }
                    if (countElIa) countElIa.textContent = launchedIds.length + ' analyse(s) en cours — la grille se rafraîchira automatiquement';
                    analyzeBtn.textContent = 'En attente...';
                    var pollTimer = setInterval(function() {
                        pollCount++;
                        if (pollCount > maxPolls) {
                            clearInterval(pollTimer);
                            if (barIa) barIa.classList.remove('lmd-polling');
                            analyzeBtn.disabled = false;
                            analyzeBtn.textContent = 'Lancer analyses IA';
                            if (countElIa) countElIa.textContent = '0 estimation(s) sélectionnée(s)';
                            return;
                        }
                        var formData = 'action=lmd_check_analysis_status_batch&nonce=' + encodeURIComponent(nonce);
                        launchedIds.forEach(function(id) {
                            formData += '&ids[]=' + encodeURIComponent(id);
                        });
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', ajaxurl);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            try {
                                var r = JSON.parse(xhr.responseText);
                                if (r.success && r.data && r.data.statuses) {
                                    var anyDone = launchedIds.some(function(id) {
                                        return r.data.statuses[id] === 'ai_analyzed';
                                    });
                                    if (anyDone) {
                                        clearInterval(pollTimer);
                                        if (barIa) barIa.classList.remove('lmd-polling');
                                        analyzeBtn.disabled = false;
                                        analyzeBtn.textContent = 'Lancer analyses IA';
                                        if (countElIa) countElIa.textContent = '0 estimation(s) sélectionnée(s)';
                                        window.location.reload();
                                    } else {
                                        var stillAnalyzing = launchedIds.filter(function(id) {
                                            return r.data.statuses[id] === 'analyzing' || r.data.statuses[id] === 'new';
                                        }).length;
                                        if (stillAnalyzing > 0 && countElIa) {
                                            countElIa.textContent = stillAnalyzing + ' analyse(s) en cours — la grille se rafraîchira automatiquement';
                                        }
                                    }
                                }
                            } catch (err) {}
                        };
                        xhr.send(formData);
                    }, pollInterval);
                }
                launchNext();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runBulkInit);
    } else {
        runBulkInit();
    }
})();
</script>

<?php
$has_filters = !empty($filter_message) || !empty($filter_interet) || !empty($filter_estimation) || !empty($filter_theme_vente) || !empty($filter_date_vente) || !empty($filter_vendeur) || $filter_date_envoi_from !== '' || $filter_date_envoi_to !== '' || $search;
?>
<?php if (empty($estimations)) : ?>
<p class="lmd-empty"><?php echo $has_filters ? 'Aucune estimation ne correspond aux filtres.' : 'Aucune estimation trouvée.'; ?></p>
<?php else : ?>
<div class="lmd-cards-grid cols-<?php echo (int) $grid_cols; ?>" id="lmd-cards-grid">
    <?php foreach ($estimations as $e) :
        $photo_url = lmd_list_photo_url($e);
        $db->sync_message_tag($e);
        $tags = $db->get_estimation_tags($e->id);
        $tags_by_type = [];
        foreach ($tags as $t) {
            $tags_by_type[$t->type] = $t;
        }
        $msg_tag = $tags_by_type['message'] ?? null;
        $interet_tag = $tags_by_type['interet'] ?? null;
        $estimation_tag = $tags_by_type['estimation'] ?? null;
        $theme_tag = $tags_by_type['theme_vente'] ?? null;
        $date_vente_tag = $tags_by_type['date_vente'] ?? null;
        $ai_data = [];
        if (!empty($e->ai_analysis)) {
            $ai_data = json_decode($e->ai_analysis, true) ?: [];
        }
        $ai_theme = $ai_data['theme_vente'] ?? '';
        $ai_sub_cats = $ai_data['sub_categories'] ?? [];
        $ai_estimation_slug = trim($ai_data['estimation'] ?? '');
        $ai_interet_slug = trim($ai_data['interet'] ?? $ai_data['interest_level'] ?? '');
        $ai_est = function_exists('lmd_get_ai_estimation') ? lmd_get_ai_estimation($ai_data) : ['low' => null, 'high' => null];
        $ai_estimate_low = $ai_est['low'];
        $ai_estimate_high = $ai_est['high'];
        $repondu = ($msg_tag && in_array($msg_tag->slug ?? '', ['repondu', 'vendu'], true)) || !empty($e->reponse_sent_at);
        $opened = !empty($e->first_viewed_at);
        $ref_ts = $opened ? strtotime($e->first_viewed_at) : (!empty($e->created_at) ? strtotime($e->created_at) : 0);
        $delay_hours = $ref_ts ? (time() - $ref_ts) / 3600 : 0;
        $show_retard = !$repondu && $delay_hours >= 48;
        $retard_7j = $show_retard && $delay_hours >= 168;
        $delay_days = $show_retard ? max(1, (int) floor($delay_hours / 24)) : 0;
        $has_cp = !empty(trim($e->auctioneer_notes ?? ''))
            || (isset($e->estimate_low) && $e->estimate_low !== null && $e->estimate_low !== '')
            || (isset($e->estimate_high) && $e->estimate_high !== null && $e->estimate_high !== '')
            || (isset($e->prix_reserve) && $e->prix_reserve !== null && $e->prix_reserve !== '');
        $has_avis2 = (isset($e->avis2_estimate_low) && $e->avis2_estimate_low !== null && $e->avis2_estimate_low !== '')
            || (isset($e->avis2_estimate_high) && $e->avis2_estimate_high !== null && $e->avis2_estimate_high !== '')
            || (isset($e->avis2_prix_reserve) && $e->avis2_prix_reserve !== null && $e->avis2_prix_reserve !== '')
            || !empty(trim($e->second_opinion ?? ''));
        $has_ia = !empty(trim($e->ai_analysis ?? ''));
        $has_human_tags = $interet_tag || $estimation_tag || $theme_tag;
        $tag_source = $has_avis2 ? 'avis2' : ($has_cp ? 'cp' : (($has_ia && !$has_human_tags) ? 'ia' : 'default'));
        $tag_source_style = [
            'ia' => 'background:#d1fae5;color:#065f46;border-color:#6ee7b7',
            'cp' => 'background:#dbeafe;color:#1d4ed8;border-color:#93c5fd',
            'avis2' => 'background:#ede9fe;color:#6d28d9;border-color:#c4b5fd',
            'default' => 'background:#f3f4f6;color:#374151;border-color:#e5e7eb',
        ];
        $tag_style = $tag_source_style[$tag_source] ?? $tag_source_style['default'];
        $estimate_low = (isset($e->estimate_low) && $e->estimate_low !== null && $e->estimate_low !== '') ? floatval($e->estimate_low) : null;
        $avis2_estimate_low = (isset($e->avis2_estimate_low) && $e->avis2_estimate_low !== null && $e->avis2_estimate_low !== '') ? floatval($e->avis2_estimate_low) : null;
        $avis2_estimate_high = (isset($e->avis2_estimate_high) && $e->avis2_estimate_high !== null && $e->avis2_estimate_high !== '') ? floatval($e->avis2_estimate_high) : null;
        $estimate_high = (isset($e->estimate_high) && $e->estimate_high !== null && $e->estimate_high !== '') ? floatval($e->estimate_high) : null;
        $created_ts = !empty($e->created_at) ? strtotime($e->created_at) : 0;
        $estimate_display = null;
        $estimate_source = null;
        if ($avis2_estimate_low !== null) {
            $estimate_display = number_format($avis2_estimate_low, 0, ',', ' ') . ' €';
            $estimate_source = 'avis2';
        } elseif ($estimate_low !== null) {
            $estimate_display = number_format($estimate_low, 0, ',', ' ') . ' €';
            $estimate_source = 'cp';
        } elseif ($ai_estimate_low !== null && $has_ia) {
            $estimate_display = number_format($ai_estimate_low, 0, ',', ' ') . ' €';
            $estimate_source = 'ia';
        }
        $created = $created_ts ? date_i18n('j M Y', $created_ts) : '';
        $detail_url = admin_url('admin.php?page=lmd-estimation-detail&id=' . $e->id);
        $client_label = trim(wp_unslash($e->client_name ?? '')) ?: trim(wp_unslash($e->client_email ?? '')) ?: 'Estimation #' . $e->id;
        $desc_words = $grid_cols === 3 ? 30 : ($grid_cols === 4 ? 22 : 15);
        $desc = wp_trim_words(wp_unslash($e->description ?? ''), $desc_words);
        $msg_order = ['nouveau' => 0, 'non_lu' => 1, 'lu_non_repondu' => 2, 'en_retard' => 2, 'repondu' => 3, 'vendu' => 4];
        $sort_message = isset($msg_tag->slug) ? ($msg_order[$msg_tag->slug] ?? 5) : 5;
        $sort_estimate = $avis2_estimate_low !== null ? $avis2_estimate_low : ($estimate_low !== null ? $estimate_low : ($ai_estimate_low !== null && $has_ia ? $ai_estimate_low : 999999999));
        $sort_theme = $theme_tag ? $theme_tag->name : ($ai_theme ? (function_exists('lmd_get_theme_vente_name') ? lmd_get_theme_vente_name($ai_theme) : $ai_theme) : '');
        $interet_order = ['pas_pour_nous' => 0, 'peu_interessant' => 1, 'a_examiner' => 2, 'interessant' => 3, 'tres_interessant' => 4, 'exceptionnel' => 5];
        $sort_interet = $interet_tag ? ($interet_order[$interet_tag->slug] ?? 6) : 6;
        $sort_date = $created_ts ? $created_ts : 0;
    ?>
    <div class="lmd-card-wrapper" data-id="<?php echo (int) $e->id; ?>" data-sort-message="<?php echo (int) $sort_message; ?>" data-sort-estimate="<?php echo esc_attr($sort_estimate); ?>" data-sort-theme="<?php echo esc_attr($sort_theme); ?>" data-sort-interet="<?php echo (int) $sort_interet; ?>" data-sort-date="<?php echo (int) $sort_date; ?>">
    <a href="<?php echo esc_url($detail_url); ?>" class="lmd-card<?php echo ($msg_tag && ($msg_tag->slug ?? '') === 'vendu') ? ' lmd-card-vendu' : ($date_vente_tag ? ' lmd-card-programme' : ''); ?>" data-id="<?php echo (int) $e->id; ?>">
        <div class="lmd-card-img-wrap">
            <?php if ($photo_url) : ?>
            <img src="<?php echo esc_url($photo_url); ?>" alt="" class="lmd-card-img" loading="lazy" />
            <?php else : ?>
            <div class="lmd-card-img-placeholder">🖼</div>
            <?php endif; ?>
            <div class="lmd-card-overlay-btns">
                <button type="button" class="lmd-card-drag-handle" title="Glisser pour réorganiser" aria-label="Déplacer">⋮⋮</button>
                <button type="button" class="lmd-card-ia-btn<?php echo ($e->status ?? '') === 'ai_analyzed' ? ' lmd-card-ia-btn-disabled' : ''; ?>" data-id="<?php echo (int) $e->id; ?>" data-analyzed="<?php echo ($e->status ?? '') === 'ai_analyzed' ? '1' : '0'; ?>" title="<?php echo ($e->status ?? '') === 'ai_analyzed' ? 'Déjà analysé' : 'Sélectionner pour analyse IA'; ?>" aria-label="<?php echo ($e->status ?? '') === 'ai_analyzed' ? 'Déjà analysé' : 'Sélectionner pour analyse'; ?>">IA</button>
                <button type="button" class="lmd-card-trash" data-id="<?php echo (int) $e->id; ?>" title="Sélectionner pour suppression" aria-label="Sélectionner">🗑</button>
            </div>
        </div>
        <div class="lmd-card-body">
            <div class="lmd-card-title-row">
                <h3 class="lmd-card-title"><?php echo esc_html($client_label); ?></h3>
            </div>
            <?php if ($desc) : ?>
            <p class="lmd-card-desc"><?php echo esc_html($desc); ?></p>
            <?php endif; ?>
            <div class="lmd-card-meta">
                <?php
                $show_msg = function_exists('lmd_pref_show_criterion') ? lmd_pref_show_criterion('message') : true;
                if ($show_msg) {
                    if ($show_retard) {
                        $msg_display = $opened ? 'Non répondu' : 'Non lu';
                    } else {
                        $msg_display = $msg_tag ? $msg_tag->name : null;
                    }
                    if ($msg_display) : ?><span class="lmd-card-tag tag-message<?php echo ($msg_tag && ($msg_tag->slug ?? '') === 'vendu') ? ' tag-vendu' : ''; ?>"><?php echo esc_html($msg_display); ?></span><?php endif; ?>
                    <?php if ($show_retard) : ?><span class="lmd-card-tag <?php echo $retard_7j ? 'tag-retard-7j' : 'tag-retard'; ?>"><?php echo $retard_7j ? '⚠ ' . esc_html($delay_days) . 'j' : esc_html($delay_days) . 'j retard'; ?></span><?php endif; ?>
                <?php }
                $show_interet = function_exists('lmd_pref_show_criterion') ? lmd_pref_show_criterion('interet') : true;
                if ($show_interet) {
                    $interet_display = $interet_tag ? $interet_tag->name : ($ai_interet_slug && $has_ia && function_exists('lmd_get_interet_name') ? lmd_get_interet_name($ai_interet_slug) : ($ai_interet_slug && $has_ia ? ucfirst(str_replace('_', ' ', $ai_interet_slug)) : null));
                    if ($interet_display) : ?><span class="lmd-card-tag tag-interet tag-source-<?php echo esc_attr($interet_tag ? $tag_source : 'ia'); ?>"><?php echo esc_html($interet_display); ?></span><?php endif;
                }
                $show_est = function_exists('lmd_pref_show_criterion') ? lmd_pref_show_criterion('estimation') : true;
                if ($show_est) { ?>
                <?php if ($estimate_display !== null) : ?><span class="lmd-card-tag tag-source-<?php echo esc_attr($estimate_source); ?>"><?php echo esc_html($estimate_display); ?></span><?php endif; ?>
                <?php if ($estimation_tag && $estimate_display === null) : ?><span class="lmd-card-tag tag-source-<?php echo esc_attr($tag_source); ?>"><?php echo esc_html($estimation_tag->name); ?></span><?php endif; ?>
                <?php if ($estimate_display === null && !$estimation_tag && $has_ia && $ai_estimation_slug) : ?><span class="lmd-card-tag tag-source-ia"><?php echo esc_html(function_exists('lmd_get_estimation_name') ? lmd_get_estimation_name($ai_estimation_slug) : $ai_estimation_slug); ?></span><?php endif; ?>
                <?php }
                $show_theme = function_exists('lmd_pref_show_criterion') ? lmd_pref_show_criterion('theme') : true;
                if ($show_theme) {
                    $theme_display = null;
                    if (!empty($ai_sub_cats) && $tag_source === 'ia') {
                        $theme_display = function_exists('lmd_get_theme_vente_name') ? lmd_get_theme_vente_name($ai_sub_cats[0]) : $ai_sub_cats[0];
                    } elseif ($theme_tag) {
                        $theme_display = $theme_tag->name;
                    } elseif ($ai_theme && $tag_source === 'ia') {
                        $theme_display = function_exists('lmd_get_theme_vente_name') ? lmd_get_theme_vente_name($ai_theme) : $ai_theme;
                    }
                    if ($theme_display) : ?><span class="lmd-card-tag tag-source-<?php echo esc_attr($theme_tag ? $tag_source : 'ia'); ?>"><?php echo esc_html($theme_display); ?></span><?php endif;
                }
                $show_date = function_exists('lmd_pref_show_criterion') ? lmd_pref_show_criterion('date') : true;
                if ($show_date) : ?><span class="lmd-card-date"><?php echo esc_html($created); ?></span><?php endif; ?>
                <?php
                $show_cp = function_exists('lmd_pref_show_criterion') ? lmd_pref_show_criterion('cp_avis2') : true;
                if ($show_cp) { ?>
                <?php if ($has_cp) : ?><span class="lmd-card-tag lmd-card-tag-mini tag-source-cp">CP</span><?php endif; ?>
                <?php if ($has_avis2) : ?><span class="lmd-card-tag lmd-card-tag-mini tag-source-avis2">2e avis</span><?php endif; ?>
                <?php } ?>
            </div>
        </div>
    </a>
    </div>
            <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="lmd-bulk-bar" id="lmd-bulk-bar">
    <span class="lmd-bulk-bar-count" id="lmd-bulk-count">0 estimation(s) sélectionnée(s)</span>
    <button type="button" class="lmd-bulk-btn" id="lmd-bulk-toggle">Tout sélectionner</button>
    <button type="button" class="lmd-bulk-btn lmd-bulk-btn-delete" id="lmd-bulk-delete">Supprimer</button>
</div>
<div class="lmd-bulk-bar lmd-bulk-bar-ia" id="lmd-bulk-bar-ia">
    <span class="lmd-bulk-bar-count" id="lmd-bulk-count-ia">0 estimation(s) sélectionnée(s)</span>
    <button type="button" class="lmd-bulk-btn" id="lmd-bulk-toggle-ia">Tout sélectionner</button>
    <button type="button" class="lmd-bulk-btn lmd-bulk-btn-analyze" id="lmd-bulk-analyze">Lancer analyses IA</button>
</div>
</div>
