<?php
/**
 * Vue Détail estimation - Version complète
 */
if (!defined("ABSPATH")) {
    exit();
}
$id = isset($_GET["id"]) ? absint($_GET["id"]) : 0;
if (!$id) {
    echo '<div class="wrap lmd-page"><p>Estimation introuvable.</p></div>';
    return;
}
$db = new LMD_Database();
$db->ensure_tags_seeded();
$estimation = $db->get_estimation($id);
if (!$estimation) {
    echo '<div class="wrap lmd-page"><p>Estimation introuvable.</p></div>';
    return;
}
if (current_user_can("manage_options")) {
    global $wpdb;
    $table = $wpdb->prefix . "lmd_estimations";
    $cols = $wpdb->get_col("DESCRIBE $table");
    if (
        in_array("first_viewed_at", $cols, true) &&
        empty($estimation->first_viewed_at)
    ) {
        $wpdb->update(
            $table,
            ["first_viewed_at" => current_time("mysql")],
            ["id" => $id],
            ["%s"],
            ["%d"],
        );
        $estimation->first_viewed_at = current_time("mysql");
    }
}
$db->sync_message_tag($estimation);
$opinion = isset($_GET["opinion"]) ? absint($_GET["opinion"]) : 1;
if ($opinion !== 2) {
    $opinion = 1;
}
$col3_action = isset($_GET["col3"]) ? sanitize_key($_GET["col3"]) : "reponse";
if ($col3_action !== "deleguer") {
    $col3_action = "reponse";
}
$opinion1 = (string) ($estimation->auctioneer_notes ?? "");
$opinion2 = (string) ($estimation->second_opinion ?? "");
$est_low =
    $opinion === 1
        ? $estimation->avis1_estimate_low ?? $estimation->estimate_low
        : $estimation->avis2_estimate_low ?? null;
$est_high =
    $opinion === 1
        ? $estimation->avis1_estimate_high ?? $estimation->estimate_high
        : $estimation->avis2_estimate_high ?? null;
$est_reserve =
    $opinion === 1
        ? $estimation->avis1_prix_reserve ?? $estimation->prix_reserve
        : $estimation->avis2_prix_reserve ?? null;
$ai = [];
if (!empty($estimation->ai_analysis)) {
    $ai = json_decode($estimation->ai_analysis, true) ?: [];
}
$has_ai =
    !empty($ai) &&
    (($estimation->status ?? "") === "ai_analyzed" ||
        ($estimation->status ?? "") === "analyzing");
$is_analyzing = ($estimation->status ?? "") === "analyzing";
$categories = function_exists("lmd_get_tag_categories")
    ? lmd_get_tag_categories()
    : [];
$INTEREST_LEVELS = $categories["interet"]["options"] ?? [];
global $wpdb;
$site_id = get_current_blog_id();
$linked_tags = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT t.id, t.name, t.type, t.slug, et.modified_by_avis FROM {$wpdb->prefix}lmd_estimation_tags et INNER JOIN {$wpdb->prefix}lmd_tags t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.site_id = %d",
        $id,
        $site_id,
    ),
);
$tags_by_type = function_exists("lmd_build_tags_by_type")
    ? lmd_build_tags_by_type($linked_tags, $opinion, true)
    : [];
$all_tags = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}lmd_tags WHERE site_id = %d ORDER BY type, name",
        $site_id,
    ),
);
$tags_by_cat = [];
foreach ($all_tags as $t) {
    $tags_by_cat[$t->type][] = $t;
}
$photos = [];
if (!empty($estimation->photos)) {
    $decoded = json_decode($estimation->photos, true);
    $photos = is_array($decoded)
        ? $decoded
        : (is_string($estimation->photos)
            ? [$estimation->photos]
            : []);
}

function lmd_ed_photo_url($path)
{
    if (is_array($path)) {
        $path = reset($path);
    }
    if (!$path || !is_string($path)) {
        return "";
    }
    $upload = wp_upload_dir();
    $basedir = $upload["basedir"];
    $baseurl = $upload["baseurl"];
    if (strpos($path, "http") === 0 || strpos($path, "//") === 0) {
        return $path;
    }
    $fullpath = $path;
    if (!file_exists($path) && strpos($path, $basedir) !== 0) {
        $fullpath = $basedir . "/" . ltrim(str_replace("\\", "/", $path), "/");
    }
    if (file_exists($fullpath)) {
        return str_replace($basedir, $baseurl, $fullpath);
    }
    if (strpos($path, $basedir) === 0) {
        return str_replace($basedir, $baseurl, $path);
    }
    return $baseurl . "/" . ltrim(str_replace("\\", "/", $path), "/");
}
$object_description = wp_unslash($estimation->description ?? "");
$created_date = !empty($estimation->created_at)
    ? date_i18n(get_option("date_format"), strtotime($estimation->created_at))
    : "";
$sender_name =
    trim(wp_unslash($estimation->client_name ?? "")) ?:
    trim(wp_unslash($estimation->client_email ?? "")) ?:
    "-";
$sender_rank = $db ? $db->get_sender_rank($estimation) : 0;
$sender_label =
    $sender_rank > 1 ? $sender_name . " (" . $sender_rank . ")" : $sender_name;
$delete_url = wp_nonce_url(
    admin_url("admin-post.php?action=lmd_delete_estimation&id=" . $id),
    "lmd_delete_estimation_" . $id,
);
$interet_slug = isset($tags_by_type["interet"])
    ? $tags_by_type["interet"]->slug
    : (trim($ai["interet"] ?? "") ?:
    "");
$estimation_slug = isset($tags_by_type["estimation"])
    ? $tags_by_type["estimation"]->slug
    : (trim($ai["estimation"] ?? "") ?:
    "");
$cp_for_mailto = function_exists("lmd_get_cp_settings_for_user")
    ? lmd_get_cp_settings_for_user()
    : ["copy_emails" => []];
$prefs_for_mailto = function_exists("lmd_get_prefs") ? lmd_get_prefs() : [];
$bcc_exclude_slugs_mail =
    isset($prefs_for_mailto["bcc_exclude_response_slugs"]) &&
    is_array($prefs_for_mailto["bcc_exclude_response_slugs"])
        ? $prefs_for_mailto["bcc_exclude_response_slugs"]
        : [];
$theme_slug = isset($tags_by_type["theme_vente"])
    ? $tags_by_type["theme_vente"]->slug
    : (trim($ai["theme_vente"] ?? "") ?:
    "");
$theme_suggested_parent = trim($ai["theme_vente_suggested_parent"] ?? "") ?: "";
$theme_opts_slugs = array_map(function ($o) {
    return is_object($o) ? $o->slug : $o["slug"] ?? "";
}, $categories["theme_vente"]["options"] ?? []);
$theme_is_new_from_ai =
    $theme_slug &&
    $has_ai &&
    !empty($ai["theme_vente"]) &&
    !in_array($theme_slug, $theme_opts_slugs);
$cp_has_theme =
    isset($tags_by_type["theme_vente"]) && $tags_by_type["theme_vente"];
$cp_has_interet = isset($tags_by_type["interet"]) && $tags_by_type["interet"];
$cp_has_estimation =
    (isset($tags_by_type["estimation"]) && $tags_by_type["estimation"]) ||
    (isset($estimation->avis1_estimate_low) &&
        $estimation->avis1_estimate_low !== null &&
        $estimation->avis1_estimate_low !== "") ||
    (isset($estimation->avis2_estimate_low) &&
        $estimation->avis2_estimate_low !== null &&
        $estimation->avis2_estimate_low !== "");
$ai_summary = $has_ai ? trim($ai["summary"] ?? "") : "";
$ai_summary_first = $ai_summary
    ? preg_replace('/^([^.!?]+[.!?]?).*$/s', '$1', $ai_summary)
    : "";
$ai_summary_rest =
    $ai_summary && $ai_summary_first
        ? trim(substr($ai_summary, strlen($ai_summary_first)))
        : $ai_summary;
?>
<div class="wrap ed-wrap lmd-estimation-detail lmd-page" id="ed-wrap-<?php echo (int) $id; ?>" data-id="<?php echo (int) $id; ?>" data-opinion="<?php echo (int) $opinion; ?>" data-status="<?php echo esc_attr(
    $estimation->status ?? "",
); ?>">
<style>
/* Styles Lovable - palette #e5e7eb, #10b981, #22c55e, #1f2937 */
.lmd-estimation-detail { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; font-size: 13px !important; line-height: 1.5 !important; color: #374151 !important; max-width: 1600px !important; margin: 0 auto !important; padding: 24px !important; box-sizing: border-box !important; }
.lmd-estimation-detail *, .lmd-estimation-detail *::before, .lmd-estimation-detail *::after { box-sizing: inherit !important; }
/* Surcharge admin WP sur formulaires */
.lmd-estimation-detail select, .lmd-estimation-detail .ed-interest-select { appearance: auto !important; -webkit-appearance: menulist !important; padding: 8px 12px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; background: #fff !important; min-width: 140px !important; }
.lmd-estimation-detail textarea, .lmd-estimation-detail .ed-notes { padding: 10px 12px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; font-family: inherit !important; background: #fff !important; }
.lmd-estimation-detail input[type="text"], .lmd-estimation-detail .ed-estimate-field input { padding: 6px 10px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; background: #fff !important; width: 100px !important; }
.lmd-estimation-detail .button-primary, .lmd-estimation-detail .lmd-save-avis { padding: 8px 16px !important; background: #1f2937 !important; color: #fff !important; border: none !important; border-radius: 6px !important; font-weight: 600 !important; font-size: 13px !important; cursor: pointer !important; }
.lmd-estimation-detail .button-primary:hover, .lmd-estimation-detail .lmd-save-avis:hover { background: #111827 !important; }
.lmd-estimation-detail .ed-header-actions .button { background: #f3f4f6 !important; color: #374151 !important; border: 1px solid #e5e7eb !important; }
.lmd-estimation-detail .ed-header-actions .button:hover { background: #e5e7eb !important; }
/* Header Lovable */
.lmd-estimation-detail .ed-header { display: flex !important; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; }
.lmd-estimation-detail .ed-header.ed-header-centered { justify-content: center !important; width: 100% !important; }
.lmd-estimation-detail .ed-header.ed-header-centered .ed-header-title { text-align: center !important; width: 100% !important; }
.lmd-estimation-detail .ed-header-left { display: flex !important; align-items: center; gap: 12px; }
.lmd-estimation-detail .ed-header-title { font-size: 18px !important; font-weight: 600 !important; margin: 0 !important; color: #374151 !important; }
.lmd-estimation-detail .ed-header-title .ed-header-phone { color: #3b82f6 !important; text-decoration: none !important; font-weight: 500 !important; }
.lmd-estimation-detail .ed-header-title .ed-header-phone:hover { text-decoration: underline !important; }
.lmd-estimation-detail .ed-header-title .ed-header-email { color: #6b7280 !important; font-weight: 500 !important; }
.lmd-estimation-detail .ed-header-with-back { display: flex !important; align-items: center !important; gap: 12px !important; }
.lmd-estimation-detail .ed-header-with-back .ed-back-a { flex-shrink: 0 !important; padding: 6px 12px !important; background: #f3f4f6 !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; text-decoration: none !important; color: #374151 !important; font-size: 13px !important; }
.lmd-estimation-detail .ed-header-with-back .ed-back-a:hover { background: #e5e7eb !important; }
.lmd-estimation-detail .ed-header-with-back .ed-header-title { flex: 1 !important; text-align: center !important; }
.lmd-estimation-detail .ed-header-actions { display: flex !important; gap: 8px; }
/* Tags bar - style page demandes (dropdowns horizontaux, badges larges) */
.lmd-estimation-detail .ed-tags-bar { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; margin: 16px 0 !important; padding: 10px 14px !important; background: #f9fafb !important; border: 1px solid #e5e7eb !important; border-radius: 10px !important; flex-wrap: wrap !important; overflow: visible !important; }
.lmd-estimation-detail .ed-tags-bar-left,
.lmd-estimation-detail .ed-tags-bar-center,
.lmd-estimation-detail .ed-tags-bar-right { display: grid !important; gap: 8px !important; min-width: 0 !important; align-items: stretch !important; }
.lmd-estimation-detail .ed-tags-bar-left { flex: 1.1 1 12% !important; grid-template-columns: minmax(0, 1fr) !important; }
.lmd-estimation-detail .ed-tags-bar-center { flex: 2.8 1 42% !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
.lmd-estimation-detail .ed-tags-bar-right { flex: 2.2 1 32% !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; margin-left: 0 !important; }
.lmd-estimation-detail .ed-tag-supprimer { margin-left: 0 !important; flex: 0 0 auto !important; align-self: stretch !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 8px 14px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-radius: 8px !important; font-size: 13px !important; color: #dc2626 !important; text-decoration: none !important; font-weight: 500 !important; cursor: pointer !important; white-space: nowrap !important; }
.lmd-estimation-detail .ed-tag-supprimer:hover { border-color: #dc2626 !important; background: #fef2f2 !important; }
.lmd-estimation-detail .ed-tag-wrapper[data-type="vente"] .ed-tag-btn { min-width: 0 !important; max-width: none !important; }
.lmd-estimation-detail .ed-tags-bar-right .ed-tag-wrapper[data-type="vente"] { align-self: center !important; }
.lmd-estimation-detail .ed-tag-vente-case { display: flex !important; align-items: center !important; gap: 4px !important; width: 100% !important; min-width: 0 !important; padding: 4px 6px 4px 4px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-left-width: 4px !important; border-radius: 8px !important; }
.lmd-estimation-detail .ed-tag-vente-case .ed-tag-btn { flex: 1 1 auto !important; width: auto !important; margin: 0 !important; border: none !important; border-radius: 6px !important; padding: 6px 10px !important; min-width: 0 !important; max-width: none !important; }
.lmd-estimation-detail .ed-tag-vente-case .ed-lot-number { flex: 0 0 4ch !important; width: 4ch !important; padding: 4px 0 !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 12px !important; text-align: center !important; margin: 0 !important; }
.lmd-estimation-detail .ed-tag-wrapper[data-type="vendeur"] .ed-tag-btn,
.lmd-estimation-detail .ed-tag-wrapper[data-type="vendeur"] .ed-tag-btn.ed-tag-source-form { background: #fff !important; border-color: #e5e7eb !important; border-left-color: #9ca3af !important; color: #111827 !important; filter: none !important; }
.lmd-estimation-detail .ed-tag-wrapper[data-type="vendeur"] .ed-tag-btn:hover,
.lmd-estimation-detail .ed-tag-wrapper[data-type="vendeur"] .ed-tag-btn.ed-tag-source-form:hover { border-color: #9ca3af !important; box-shadow: 0 0 0 2px rgba(156,163,175,0.2) !important; }
.lmd-estimation-detail .ed-tag-wrapper { position: relative !important; min-width: 0 !important; width: 100% !important; }
.lmd-estimation-detail .ed-tag-btn { position: relative; display: flex !important; align-items: center !important; gap: 6px !important; width: 100% !important; padding: 8px 14px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-left-width: 4px !important; border-radius: 8px !important; font-size: 13px !important; cursor: pointer !important; min-width: 0 !important; max-width: none !important; font-family: inherit !important; box-shadow: none !important; color: #374151 !important; font-weight: 500 !important; transition: border-color 0.2s, box-shadow 0.2s !important; }
.lmd-estimation-detail .ed-tag-btn:hover { border-color: #9ca3af !important; box-shadow: 0 0 0 2px rgba(156,163,175,0.2) !important; }
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-ia,
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-form { background: #f0fdf4 !important; border-color: #22c55e !important; border-left-color: #22c55e !important; color: #15803d !important; filter: saturate(0.67) !important; }
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-ia:hover,
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-form:hover { border-color: #16a34a !important; box-shadow: 0 0 0 2px rgba(34,197,94,0.25) !important; }
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-cp { background: #eff6ff !important; border-color: #3b82f6 !important; border-left-color: #3b82f6 !important; color: #1d4ed8 !important; filter: saturate(0.67) !important; }
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-cp:hover { border-color: #2563eb !important; box-shadow: 0 0 0 2px rgba(59,130,246,0.25) !important; }
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-avis2 { background: #f5f3ff !important; border-color: #8b5cf6 !important; border-left-color: #8b5cf6 !important; color: #6d28d9 !important; filter: saturate(0.67) !important; }
.lmd-estimation-detail .ed-tag-btn.ed-tag-source-avis2:hover { border-color: #7c3aed !important; box-shadow: 0 0 0 2px rgba(139,92,246,0.25) !important; }
.lmd-estimation-detail .ed-tag-btn .ed-tag-label { flex: 1 !important; min-width: 0 !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
.lmd-estimation-detail .ed-tag-btn .ed-tag-arrow { font-size: 10px !important; color: #9ca3af !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-tag-wrapper.open .ed-tag-arrow { transform: rotate(180deg) !important; }
.lmd-estimation-detail .ed-tag-dd { position: fixed !important; min-width: 200px !important; max-height: 280px !important; overflow-y: auto !important; padding: 8px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-radius: 8px !important; box-shadow: 0 8px 24px rgba(0,0,0,0.18) !important; z-index: 1000000 !important; display: none !important; }
.lmd-estimation-detail .ed-tag-dd-vente { max-height: none !important; overflow-y: visible !important; }
.lmd-estimation-detail .ed-tag-wrapper.open .ed-tag-dd { display: block !important; }
.lmd-estimation-detail .ed-tag-dd-item { padding: 8px 12px !important; cursor: pointer !important; font-size: 13px !important; border-radius: 6px !important; filter: saturate(0.67) !important; }
.lmd-estimation-detail .ed-tag-dd-item:hover { background: #f9fafb !important; }
.lmd-estimation-detail .ed-tag-dd-item.selected { background: #f3f4f6 !important; color: #374151 !important; font-weight: 600 !important; }
.lmd-estimation-detail .ed-tag-dd-header-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 8px !important; margin-bottom: 4px !important; padding-bottom: 4px !important; border-bottom: 1px solid #e5e7eb !important; }
.lmd-estimation-detail .ed-tag-dd-header-row .ed-tag-dd-item { flex: 1 !important; margin: 0 !important; border: none !important; }
.lmd-estimation-detail .ed-tag-dd-create-theme { padding: 8px 12px !important; cursor: pointer !important; font-size: 12px !important; background: #f0fdf4 !important; border: 1px dashed #22c55e !important; border-radius: 6px !important; margin-top: 6px !important; filter: saturate(0.67) !important; }
.lmd-estimation-detail .ed-tag-dd-create-theme:hover { background: #dcfce7 !important; }
.lmd-estimation-detail .ed-tag-dd-create-theme .ed-tag-dd-create-parent { color: #6b7280 !important; font-size: 11px !important; }
.lmd-estimation-detail .ed-tag-dd-gear { background: none !important; border: none !important; padding: 4px 6px !important; cursor: pointer !important; font-size: 26px !important; color: #3b82f6 !important; border-radius: 4px !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-tag-dd-gear:hover { color: #1d4ed8 !important; background: #eff6ff !important; }
@media (max-width: 1440px) {
    .lmd-estimation-detail .ed-tags-bar-left { flex-basis: 18% !important; }
    .lmd-estimation-detail .ed-tags-bar-center { flex-basis: 100% !important; order: 2 !important; }
    .lmd-estimation-detail .ed-tags-bar-right { flex-basis: 100% !important; order: 3 !important; }
    .lmd-estimation-detail .ed-tag-supprimer { order: 4 !important; }
}
@media (max-width: 1180px) {
    .lmd-estimation-detail .ed-tags-bar-center,
    .lmd-estimation-detail .ed-tags-bar-right { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    .lmd-estimation-detail .ed-tags-bar-right .ed-tag-wrapper[data-type="vendeur"] { grid-column: 1 / -1 !important; }
}
@media (max-width: 920px) {
    .lmd-estimation-detail .ed-tags-bar-left,
    .lmd-estimation-detail .ed-tags-bar-center,
    .lmd-estimation-detail .ed-tags-bar-right { flex-basis: 100% !important; grid-template-columns: minmax(0, 1fr) !important; }
    .lmd-estimation-detail .ed-tag-supprimer { width: 100% !important; }
}
/* Modal personnalisation catégories - #ed-category-modal est en dehors du wrap, donc ciblé directement */
#ed-category-modal { display: none !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; background: rgba(0,0,0,0.4) !important; z-index: 1000001 !important; align-items: center !important; justify-content: center !important; }
#ed-category-modal.open { display: flex !important; }
#ed-category-modal .ed-cat-modal-inner { background: #fff; border-radius: 12px; box-shadow: 0 16px 48px rgba(0,0,0,0.2); max-width: 520px; width: 90%; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
#ed-category-modal .ed-cat-modal-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 600; font-size: 16px; }
#ed-category-modal .ed-cat-modal-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
#ed-category-modal .ed-cat-modal-footer { padding: 12px 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end; }
#ed-category-modal .ed-cat-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
#ed-category-modal .ed-cat-row input { flex: 1; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; }
#ed-category-modal .ed-cat-row .ed-cat-max { width: 80px; flex: none; }
#ed-category-modal .ed-cat-row .ed-cat-min { width: 80px; flex: none; }
#ed-category-modal .ed-cat-row .ed-cat-del { background: none; border: none; color: #dc2626; cursor: pointer; padding: 4px; font-size: 12px; }
#ed-category-modal .ed-cat-add { margin-top: 12px; padding: 8px 14px; background: #f3f4f6; border: 1px dashed #e5e7eb; border-radius: 6px; cursor: pointer; font-size: 13px; color: #6b7280; }
#ed-category-modal .ed-cat-add:hover { background: #e5e7eb; color: #374151; }
/* Date de vente - calendrier - fenêtre agrandie pour meilleure lisibilité */
.lmd-estimation-detail .ed-tag-dd-vente { min-width: 480px !important; max-width: 540px !important; padding: 16px !important; }
.lmd-estimation-detail .ed-vente-month-nav { display: flex !important; align-items: center !important; justify-content: space-between !important; margin-bottom: 12px !important; }
.lmd-estimation-detail .ed-vente-month-nav button { padding: 6px 14px !important; background: #f3f4f6 !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; cursor: pointer !important; font-size: 15px !important; }
.lmd-estimation-detail .ed-vente-month-nav button:hover { background: #e5e7eb !important; }
.lmd-estimation-detail .ed-vente-month-label { font-weight: 600 !important; font-size: 15px !important; }
.lmd-estimation-detail .ed-vente-calendar-grid { display: grid !important; grid-template-columns: repeat(7, 1fr) !important; gap: 4px !important; margin-bottom: 14px !important; font-size: 13px !important; }
.lmd-estimation-detail .ed-vente-calendar-grid .ed-vente-dow { text-align: center !important; color: #9ca3af !important; padding: 6px 0 !important; font-weight: 600 !important; }
.lmd-estimation-detail .ed-vente-calendar-grid .ed-vente-day { padding: 10px !important; text-align: center !important; border-radius: 6px !important; cursor: pointer !important; }
.lmd-estimation-detail .ed-vente-calendar-grid .ed-vente-day:hover { background: #f3f4f6 !important; }
.lmd-estimation-detail .ed-vente-calendar-grid .ed-vente-day.has-vente { background: #eef2ff !important; color: #4f46e5 !important; font-weight: 600 !important; }
.lmd-estimation-detail .ed-vente-calendar-grid .ed-vente-day.has-hotel-prep { box-shadow: inset 0 0 0 2px #10b981 !important; background: #ecfdf5 !important; color: #065f46 !important; font-weight: 600 !important; }
.lmd-estimation-detail .ed-vente-calendar-grid .ed-vente-day.other-month { color: #d1d5db !important; }
.lmd-estimation-detail .ed-vente-list { max-height: 220px !important; overflow-y: auto !important; margin-bottom: 14px !important; border: 1px solid #e5e7eb !important; border-radius: 8px !important; padding: 8px !important; }
.lmd-estimation-detail .ed-vente-list-item { display: flex !important; align-items: center !important; gap: 10px !important; padding: 8px 10px !important; border-radius: 6px !important; margin-bottom: 6px !important; }
.lmd-estimation-detail .ed-vente-list-item:hover { background: #f9fafb !important; }
.lmd-estimation-detail .ed-vente-list-item input[type="text"] { flex: 1 !important; min-width: 0 !important; padding: 6px 10px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; }
.lmd-estimation-detail .ed-vente-list-item input[type="date"] { width: 130px !important; padding: 6px 8px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 12px !important; }
.lmd-estimation-detail .ed-vente-cat-badge { font-size: 10px !important; color: #6b7280 !important; background: #f3f4f6 !important; padding: 2px 6px !important; border-radius: 4px !important; max-width: 120px !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
.lmd-estimation-detail .ed-vente-create { display: flex !important; flex-direction: column !important; gap: 10px !important; padding-top: 12px !important; border-top: 1px solid #e5e7eb !important; }
.lmd-estimation-detail .ed-vente-create-row { display: flex !important; gap: 8px !important; align-items: center !important; flex-wrap: wrap !important; }
.lmd-estimation-detail .ed-vente-create input { padding: 8px 12px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; }
.lmd-estimation-detail .ed-vente-create .ed-vente-name { flex: 1 !important; min-width: 140px !important; }
.lmd-estimation-detail .ed-vente-create .ed-vente-category { width: 100% !important; padding: 8px 12px !important; font-size: 13px !important; }
.lmd-estimation-detail .ed-vente-create .ed-vente-add { padding: 8px 16px !important; background: #22c55e !important; color: #fff !important; border: none !important; border-radius: 6px !important; font-size: 13px !important; font-weight: 600 !important; cursor: pointer !important; align-self: flex-start !important; }
/* Grille 3 colonnes - même largeur et hauteur (+12%) */
.lmd-estimation-detail .ed-grid { display: grid !important; grid-template-columns: 1fr 1fr 1fr !important; gap: 0 !important; min-height: 538px !important; margin-top: 16px !important; align-items: stretch !important; }
@media (max-width: 1024px) { .lmd-estimation-detail .ed-grid { grid-template-columns: 1fr !important; min-height: auto !important; } }
.lmd-estimation-detail .ed-col { border: 2px solid #e5e7eb !important; padding: 16px !important; background: #fff !important; overflow: hidden; display: flex !important; flex-direction: column !important; }
.lmd-estimation-detail .ed-col:first-child { border-radius: 12px 0 0 12px !important; border-right-width: 1px !important; }
.lmd-estimation-detail .ed-col:nth-child(2) { border-left-width: 1px !important; border-right-width: 1px !important; padding: 0 !important; }
.lmd-estimation-detail .ed-col:nth-child(2).has-open { border-color: #22c55e !important; }
.lmd-estimation-detail .ed-col:last-child { border-radius: 0 12px 12px 0 !important; border-left-width: 1px !important; }
.lmd-estimation-detail .ed-col.ed-actions.has-open { border-color: #22c55e !important; }
.lmd-estimation-detail .ed-col-photos { padding: 16px !important; }
/* Photo principale (1ère) en grand */
.lmd-estimation-detail .ed-photo-main { width: 100%; max-height: 340px; object-fit: contain; border-radius: 12px; cursor: pointer; border: 2px solid #e5e7eb; display: block; background: #f9fafb; margin-bottom: 12px; }
.lmd-estimation-detail .ed-photo-main:hover { border-color: #22c55e; }
/* Grille des miniatures */
.lmd-estimation-detail .ed-photos-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(48px, 1fr)) !important; gap: 4px; }
.lmd-estimation-detail .ed-photo-thumb-wrap { position: relative; }
.lmd-estimation-detail .ed-photo-thumb { width: 100%; aspect-ratio: 1; object-fit: contain; border-radius: 8px; cursor: pointer; border: 2px solid transparent; display: block; background: #f9fafb; }
.lmd-estimation-detail .ed-photo-smp-badge { position: absolute; bottom: 2px; right: 2px; background: #22c55e; color: #fff; font-size: 10px; width: 16px; height: 16px; border-radius: 4px; display: flex; align-items: center; justify-content: center; line-height: 1; }
.lmd-estimation-detail .ed-photo-thumb:hover { border-color: #22c55e; }
.lmd-estimation-detail .ed-photo-thumb.active { border-color: #22c55e; }
.lmd-estimation-detail .ed-description { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 13px; line-height: 1.6; color: #374151; }
.lmd-estimation-detail .ed-description h4 { font-size: 10px; text-transform: uppercase; color: #9ca3af; margin: 0 0 8px; font-weight: 600; letter-spacing: 0.05em; }
/* Onglets 1er avis / 2ème avis - symétriques (1er gauche, 2ème droite) */
.lmd-estimation-detail .ed-avis-tabs-row { display: flex !important; justify-content: space-between !important; margin-bottom: -2px; flex-shrink: 0; width: 100% !important; }
.lmd-estimation-detail .ed-avis-tabs-row .ed-avis-tab { padding: 8px 20px !important; background: #f3f4f6 !important; border: 2px solid #e5e7eb !important; border-bottom: none !important; border-radius: 8px 8px 0 0 !important; cursor: pointer; font-size: 12px !important; font-weight: 700 !important; text-transform: uppercase; font-family: inherit !important; box-shadow: none !important; color: #374151 !important; }
.lmd-estimation-detail .ed-avis-tabs-row .ed-avis-tab:hover { color: #1f2937 !important; background: #e5e7eb !important; }
.lmd-estimation-detail .ed-avis-tabs-row .ed-avis-tab.ed-tab-blue.open { color: #1d4ed8 !important; background: #dbeafe !important; border: 2px solid #3b82f6 !important; border-bottom: none !important; border-top: 3px solid #3b82f6 !important; margin-bottom: -2px !important; z-index: 2 !important; }
.lmd-estimation-detail .ed-avis-tabs-row .ed-avis-tab.ed-tab-violet.open { color: #6d28d9 !important; background: #ede9fe !important; border: 2px solid #8b5cf6 !important; border-bottom: none !important; border-top: 3px solid #8b5cf6 !important; margin-bottom: -2px !important; z-index: 2 !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche { border: 2px solid #e5e7eb !important; border-top: 2px solid #e5e7eb !important; border-radius: 0 0 8px 8px !important; padding: 8px 12px !important; background: #fff !important; flex: 1 !important; display: flex !important; flex-direction: column !important; min-height: 0 !important; width: 100% !important; gap: 8px !important; margin-top: -2px !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche.ed-cartouche-blue.open { border-color: #3b82f6 !important; border-top: 2px solid #3b82f6 !important; background: #dbeafe !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche.ed-cartouche-violet.open { border-color: #8b5cf6 !important; border-top: 2px solid #8b5cf6 !important; background: #ede9fe !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche:not(.open) { display: none !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche .ed-avis-titre { font-weight: 600 !important; font-size: 13px !important; line-height: 1.3 !important; min-height: 2.6em !important; max-height: 2.6em !important; overflow: hidden !important; display: -webkit-box !important; -webkit-line-clamp: 2 !important; -webkit-box-orient: vertical !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche.ed-cartouche-violet .ed-avis-titre,
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche.ed-cartouche-violet .ed-avis-titre-input { min-height: 2.4em !important; max-height: 2.4em !important; -webkit-line-clamp: 2 !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche .ed-avis-titre-input { width: 100% !important; padding: 6px 10px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; font-weight: 600 !important; resize: none !important; min-height: 2.6em !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche .ed-notes { flex: 1 !important; min-height: 60px !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche .ed-avis-dimension { font-size: 12px !important; color: #6b7280 !important; padding: 6px 10px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; width: 100% !important; background: #fff !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche .ed-estimate-row { flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-col-avis .ed-avis-cartouche .ed-estimate-field input::placeholder { color: #9ca3af !important; }
/* Tabs colonne 2 - legacy */
.lmd-estimation-detail .ed-tabs { display: flex !important; align-items: stretch; border-bottom: 2px solid #e5e7eb; margin-bottom: -2px; min-height: 44px; }
.lmd-estimation-detail .ed-tab-cartouche { border: 2px solid #e5e7eb !important; border-top: none !important; border-radius: 0 0 12px 12px !important; padding: 16px !important; background: #fff !important; margin-top: -2px !important; flex: 1 !important; display: flex !important; flex-direction: column !important; min-height: 0 !important; }
.lmd-estimation-detail .ed-tab-cartouche.has-open { border-color: #22c55e !important; border-top: 2px solid #22c55e !important; }
.lmd-estimation-detail .ed-accent-blue { border-color: #22c55e !important; }
.lmd-estimation-detail .ed-accent-violet { border-color: #22c55e !important; }
.lmd-estimation-detail .ed-avis-panel { display: flex !important; flex-direction: column !important; flex: 1 !important; min-height: 0 !important; }
.lmd-estimation-detail .ed-notes { width: 100%; min-height: 80px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; resize: none; font-family: inherit; }
.lmd-estimation-detail .ed-avis-panel .ed-notes { flex: 1 !important; min-height: 80px !important; }
.lmd-estimation-detail .ed-estimate-row { display: flex !important; gap: 12px; flex-wrap: wrap; margin-top: 8px; flex-shrink: 0; width: 100% !important; }
.lmd-estimation-detail .ed-estimate-field { flex: 1 !important; min-width: 80px; }
.lmd-estimation-detail .ed-estimate-input-wrap { display: flex !important; align-items: center !important; gap: 4px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; background: #fff !important; }
.lmd-estimation-detail .ed-estimate-input-wrap input { flex: 1 !important; min-width: 0 !important; width: auto !important; padding: 6px 10px !important; border: none !important; background: transparent !important; border-radius: 0 !important; }
.lmd-estimation-detail .ed-euro-suffix { flex-shrink: 0 !important; padding-right: 10px !important; font-size: 13px !important; color: #6b7280 !important; font-weight: 500 !important; }
/* Colonne 3 ACTIONS - même style que colonne 2 */
.lmd-estimation-detail .ed-col-actions { padding: 0 !important; display: flex !important; flex-direction: column !important; }
.lmd-estimation-detail .ed-actions-tabs-row { display: flex !important; justify-content: space-between !important; align-items: stretch !important; margin-bottom: -2px !important; flex-shrink: 0 !important; width: 100% !important; }
.lmd-estimation-detail .ed-actions-tabs-row .ed-actions-tab { padding: 8px 20px !important; background: #f3f4f6 !important; border: 2px solid #e5e7eb !important; border-bottom: none !important; border-radius: 8px 8px 0 0 !important; cursor: pointer; font-size: 12px !important; font-weight: 700 !important; text-transform: uppercase; font-family: inherit !important; box-shadow: none !important; color: #374151 !important; flex: 0 0 auto !important; text-align: center !important; white-space: nowrap !important; }
.lmd-estimation-detail .ed-actions-tabs-row .ed-actions-tab:hover { color: #1f2937 !important; background: #e5e7eb !important; }
.lmd-estimation-detail .ed-actions-tabs-row .ed-actions-tab.ed-tab-blue.open { color: #1d4ed8 !important; background: #dbeafe !important; border: 2px solid #3b82f6 !important; border-bottom: none !important; border-top: 3px solid #3b82f6 !important; margin-bottom: -2px !important; z-index: 2 !important; }
.lmd-estimation-detail .ed-actions-tabs-row .ed-actions-tab.ed-tab-violet.open { color: #6d28d9 !important; background: #ede9fe !important; border: 2px solid #8b5cf6 !important; border-bottom: none !important; border-top: 3px solid #8b5cf6 !important; margin-bottom: -2px !important; z-index: 2 !important; }
.lmd-estimation-detail .ed-actions-center { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; flex: 1 !important; min-width: 80px !important; }
.lmd-estimation-detail .ed-actions-center .ed-icon-btn { background: none !important; border: none !important; padding: 4px !important; cursor: pointer !important; font-size: 38px !important; color: #3b82f6 !important; text-decoration: none !important; line-height: 1 !important; transition: color 0.2s, background 0.2s !important; }
.lmd-estimation-detail .ed-actions-center .ed-icon-btn:hover { color: #1d4ed8 !important; background: #eff6ff !important; }
.lmd-estimation-detail .ed-actions-center .ed-icon-btn.ed-cp-settings-btn { font-size: 36px !important; }
.lmd-estimation-detail .ed-action-panel { display: flex !important; flex-direction: column !important; flex: 1 !important; min-height: 0 !important; gap: 8px !important; }
.lmd-estimation-detail .ed-action-cartouche-reponse,
.lmd-estimation-detail .ed-action-cartouche-deleguer { display: none !important; flex-direction: column !important; flex: 1 !important; min-height: 0 !important; padding: 8px 12px !important; gap: 8px !important; }
.lmd-estimation-detail .ed-action-cartouche-reponse.ed-active,
.lmd-estimation-detail .ed-action-cartouche-deleguer.ed-active { display: flex !important; }
.lmd-estimation-detail .ed-actions-cartouche .ed-email-objet { width: 100% !important; padding: 6px 10px !important; margin-bottom: 0 !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-actions-cartouche .ed-email-corps { width: 100% !important; min-height: 120px !important; flex: 1 !important; padding: 10px !important; margin-bottom: 0 !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; resize: vertical !important; }
.lmd-estimation-detail .ed-reponse-preview-wrap { flex-shrink: 0 !important; margin-top: 10px !important; padding: 12px !important; background: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; font-size: 12px !important; color: #334155 !important; }
.lmd-estimation-detail .ed-reponse-preview-title { font-weight: 700 !important; font-size: 13px !important; margin: 0 0 6px !important; color: #0f172a !important; }
.lmd-estimation-detail .ed-reponse-preview-hint { margin: 0 0 10px !important; line-height: 1.45 !important; color: #64748b !important; font-size: 11px !important; }
.lmd-estimation-detail .ed-reponse-preview-label { font-weight: 600 !important; margin: 0 0 6px !important; font-size: 11px !important; text-transform: uppercase !important; letter-spacing: 0.02em !important; color: #475569 !important; }
.lmd-estimation-detail .ed-reponse-preview-plain { margin: 0 0 12px !important; padding: 10px 12px !important; background: #fff !important; border: 1px solid #e2e8f0 !important; border-radius: 6px !important; font-family: ui-monospace, monospace !important; font-size: 12px !important; line-height: 1.45 !important; white-space: pre-wrap !important; word-break: break-word !important; max-height: 220px !important; overflow-y: auto !important; }
.lmd-estimation-detail .ed-reponse-preview-sig-label { font-weight: 600 !important; margin: 0 0 6px !important; font-size: 11px !important; color: #475569 !important; }
.lmd-estimation-detail .ed-reponse-preview-sig { padding: 10px 12px !important; background: #fff !important; border: 1px dashed #cbd5e1 !important; border-radius: 6px !important; font-size: 13px !important; line-height: 1.5 !important; max-height: 180px !important; overflow-y: auto !important; }
.lmd-estimation-detail .ed-reponse-preview-sig img { max-width: 100% !important; height: auto !important; }
.lmd-estimation-detail .ed-actions-send-row { display: flex !important; justify-content: space-between !important; gap: 12px !important; margin: 8px 0 !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-actions-send-row .ed-send-btn { padding: 10px 20px !important; font-weight: 600 !important; border-radius: 8px !important; cursor: pointer !important; font-size: 13px !important; }
.lmd-estimation-detail .ed-actions-send-row .ed-send-btn-left { background: #3b82f6 !important; color: #fff !important; border: none !important; }
.lmd-estimation-detail .ed-actions-send-row .ed-send-btn-right { background: #8b5cf6 !important; color: #fff !important; border: none !important; }
.lmd-estimation-detail .ed-fq-zone { flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-fq-zone:not(.ed-fq-expanded) .ed-fq-cartouche { display: none !important; }
.lmd-estimation-detail .ed-fq-zone.ed-fq-expanded .ed-actions-send-row { display: none !important; }
.lmd-estimation-detail .ed-fq-tabs-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 12px !important; margin-bottom: -2px !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-fq-tabs-left { display: flex !important; align-items: center !important; gap: 12px !important; }
.lmd-estimation-detail .ed-fq-tab { padding: 6px 14px !important; background: #f3f4f6 !important; border: 2px solid #e5e7eb !important; border-bottom: none !important; border-radius: 6px 6px 0 0 !important; cursor: pointer !important; font-size: 11px !important; font-weight: 600 !important; color: #374151 !important; }
.lmd-estimation-detail .ed-fq-tab.open { background: #fff !important; border-color: #e5e7eb !important; margin-bottom: -2px !important; z-index: 1 !important; }
.lmd-estimation-detail .ed-fq-tab .ed-fq-gear { padding: 2px 6px !important; margin-left: 4px !important; background: none !important; border: none !important; cursor: pointer !important; font-size: 34px !important; color: #3b82f6 !important; border-radius: 4px !important; transition: color 0.2s, background 0.2s !important; vertical-align: middle !important; line-height: 1 !important; }
.lmd-estimation-detail .ed-fq-tab .ed-fq-gear:hover { color: #3b82f6 !important; background: #dbeafe !important; }
.lmd-estimation-detail .ed-questions-ok { padding: 4px 12px !important; font-size: 11px !important; border-radius: 6px !important; }
.lmd-estimation-detail .ed-questions-ok:disabled { opacity: 0.5 !important; cursor: not-allowed !important; }
.lmd-estimation-detail .ed-questions-ok:not(:disabled) { cursor: pointer !important; opacity: 1 !important; }
.lmd-estimation-detail .ed-fq-cartouche { border: 1px solid #e5e7eb !important; border-radius: 0 0 8px 8px !important; padding: 10px !important; background: #f9fafb !important; max-height: 200px !important; overflow-y: auto !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-delegation-cartouche { border: 1px solid #e5e7eb !important; border-radius: 0 0 8px 8px !important; padding: 10px !important; background: #f5f3ff !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-fq-cartouche .ed-question-ia-item { padding: 8px 10px !important; margin-bottom: 6px !important; background: #fff !important; border: 2px solid #e5e7eb !important; border-radius: 6px !important; cursor: pointer !important; font-size: 12px !important; }
.lmd-estimation-detail .ed-fq-cartouche .ed-question-ia-item.selected { border-color: #3b82f6 !important; background: #dbeafe !important; }
.lmd-estimation-detail .ed-formules-row { display: flex !important; align-items: center !important; gap: 8px !important; width: 100% !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-formules-row select { flex: 1 !important; min-width: 0 !important; padding: 6px 10px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; }
.lmd-estimation-detail .ed-formules-row .ed-icon-btn { background: none !important; border: none !important; padding: 4px !important; cursor: pointer !important; font-size: 20px !important; color: #6b7280 !important; }
.lmd-estimation-detail .ed-formules-row .ed-icon-btn:hover { color: #3b82f6 !important; }
.lmd-estimation-detail .ed-actions-cartouche { border: 2px solid #e5e7eb !important; border-top: 2px solid #e5e7eb !important; border-radius: 0 0 8px 8px !important; padding: 0 !important; background: #fff !important; flex: 1 !important; display: flex !important; flex-direction: column !important; min-height: 0 !important; width: 100% !important; margin-top: -2px !important; overflow: hidden !important; }
.lmd-estimation-detail .ed-modal { position: fixed !important; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center; cursor: pointer; }
.lmd-estimation-detail .ed-modal.ed-modal-open { display: flex !important; }
.lmd-estimation-detail .ed-modal:not(.ed-modal-open) { display: none !important; }
.lmd-estimation-detail .ed-modal .ed-modal-inner { cursor: default; }
.lmd-estimation-detail .ed-modal-inner { background: #fff; padding: 24px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
.lmd-estimation-detail .ed-formules-modal-inner { max-width: 620px !important; }
.lmd-estimation-detail .ed-formules-list { margin: 20px 0; max-height: 200px; overflow-y: auto; }
.lmd-estimation-detail .ed-formules-list .ed-formule-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; margin-bottom: 8px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
.lmd-estimation-detail .ed-formules-list .ed-formule-item .ed-formule-actions { display: flex; gap: 8px; }
.lmd-estimation-detail .ed-formules-form { display: none !important; }
.lmd-estimation-detail .ed-formules-form.ed-formules-form-visible { display: block !important; }
.lmd-estimation-detail .ed-modal-inner h3 { margin-top: 0; }
.lmd-estimation-detail .ed-modal-inner p { margin: 12px 0; }
.lmd-estimation-detail .ed-actions-cartouche.ed-cartouche-blue.open { border-color: #3b82f6 !important; border-top: 2px solid #3b82f6 !important; background: #dbeafe !important; }
.lmd-estimation-detail .ed-actions-cartouche.ed-cartouche-violet.open { border-color: #8b5cf6 !important; border-top: 2px solid #8b5cf6 !important; background: #ede9fe !important; }
/* Zone signalement erreur IA - sous colonne 3, au-dessus du badge Questions */
.lmd-estimation-detail .ed-ai-error-zone,
.ed-ai-error-zone { position: relative; margin-top: -8px; margin-bottom: -24px; min-height: 60px; display: flex; align-items: center; justify-content: flex-end; padding-right: 2%; z-index: 999999; }
.lmd-estimation-detail .ed-ai-error-octagon,
.ed-ai-error-octagon { display: inline-flex; align-items: center; justify-content: center; padding: 14px 24px; background: #faf8f8; color: #8b6b6b; border: 2px solid #e5dcdc; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; font-family: inherit; transition: background 0.2s, border-color 0.2s; transform: translate(63px, 55px) rotate(10deg); box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
.lmd-estimation-detail .ed-ai-error-octagon:hover,
.ed-ai-error-octagon:hover { background: #f5f0f0; border-color: #d4b8b8; }
/* Overlay confirmation - effet premium */
.ed-ai-feedback-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); z-index: 999999; display: flex; align-items: center; justify-content: center; opacity: 0; animation: ed-ai-feedback-fade 0.35s ease forwards; }
.ed-ai-feedback-card { background: #fff; border-radius: 16px; padding: 48px 56px; text-align: center; box-shadow: 0 24px 80px rgba(0,0,0,0.2); transform: scale(0.9); animation: ed-ai-feedback-scale 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s forwards; max-width: 360px; }
.ed-ai-explain-card { max-width: 520px; text-align: left; }
.ed-ai-explain-card .ed-ai-feedback-title { text-align: center; }
.ed-ai-explain-card .ed-ai-explain-divider { height: 1px; background: #e5e7eb; margin: 20px 0 0; }
.ed-ai-explain-card .ed-ai-explain-oui-non { display: flex; gap: 24px; margin-top: 12px; }
.ed-ai-explain-card .ed-ai-explain-option { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: #374151; }
.ed-ai-explain-card .ed-ai-explain-option input { cursor: pointer; }
.ed-ai-explain-card .ed-ai-explain-textarea { width: 100%; min-height: 80px; margin: 12px 0 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; resize: vertical; }
.ed-ai-explain-card .ed-ai-explain-actions { display: flex; justify-content: center; gap: 12px; margin-top: 16px; }
.ed-ai-confirm-card .ed-ai-confirm-cancel { background: #6b7280 !important; }
.ed-ai-confirm-card .ed-ai-confirm-cancel:hover { background: #4b5563 !important; }
.ed-ai-feedback-icon { display: inline-flex; align-items: center; justify-content: center; width: 72px; height: 72px; margin: 0 auto 20px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff; font-size: 36px; font-weight: 700; border-radius: 50%; box-shadow: 0 8px 24px rgba(34,197,94,0.4); animation: ed-ai-feedback-pulse 1.5s ease infinite; }
.ed-ai-feedback-title { font-size: 20px; font-weight: 600; color: #111827; margin-bottom: 8px; letter-spacing: -0.02em; }
.ed-ai-feedback-sub { font-size: 14px; color: #6b7280; line-height: 1.5; }
@keyframes ed-ai-feedback-fade { to { opacity: 1; } }
@keyframes ed-ai-feedback-scale { to { transform: scale(1); } }
@keyframes ed-ai-feedback-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
.lmd-estimation-detail .ed-ai-error-thanks,
.ed-ai-error-thanks { font-size: 13px; color: #166534; font-style: italic; text-align: right; padding: 8px 0; line-height: 1.5; }

/* Section AI Lovable - pleine largeur sous la grille */
.lmd-estimation-detail .ed-ai-section { margin-top: 2px; width: 100%; }
.lmd-estimation-detail .ed-ai-section.ed-ai-fullwidth { border: 2px solid #e5e7eb; border-radius: 12px; padding: 14px 20px 20px 20px; background: #f9fafb; }
.lmd-estimation-detail .ed-ai-btn-slot { display: inline-block !important; width: auto !important; max-width: none !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-ai-btn-slot .ed-ai-btn { width: auto !important; min-width: 0 !important; }
.lmd-estimation-detail .ed-ai-btn { padding: 12px 24px !important; font-size: 15px !important; font-weight: 600 !important; background: #22c55e !important; color: #fff !important; border: none !important; border-radius: 8px !important; cursor: pointer !important; font-family: inherit !important; box-shadow: none !important; }
.lmd-estimation-detail .ed-ai-btn-large { padding: 10px 16px !important; font-size: 14px !important; width: fit-content !important; min-width: auto !important; max-width: none !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; flex-shrink: 0 !important; flex: 0 0 auto !important; white-space: nowrap !important; }
.lmd-estimation-detail .ed-ai-badge-wrap .ed-ai-btn,
.lmd-estimation-detail #ed-ai-launch-btn,
.lmd-estimation-detail #ed-ai-launch-btn-2 { width: fit-content !important; flex: 0 0 auto !important; max-width: none !important; }
.lmd-estimation-detail .ed-ai-btn:hover { background: #16a34a; }
.lmd-estimation-detail .ed-ai-btn:disabled { opacity: 0.7; cursor: not-allowed; }
.lmd-estimation-detail .ed-ai-head-row { display: flex !important; justify-content: center !important; align-items: center !important; gap: 16px !important; margin-top: 16px !important; margin-bottom: 0 !important; flex-wrap: wrap !important; }
.lmd-estimation-detail .ed-ai-head-row.ed-ai-main-line { display: flex !important; justify-content: center !important; align-items: center !important; gap: 12px !important; flex-wrap: wrap !important; margin-bottom: 8px !important; }
.lmd-estimation-detail .ed-ai-main-line .ed-ai-badge-wrap { display: inline-flex !important; align-items: center !important; gap: 8px !important; width: fit-content !important; max-width: none !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-ai-main-line.ed-ai-has-thanks { position: relative; }
.lmd-estimation-detail .ed-ai-error-thanks-inline { font-size: 13px; color: #166534; font-style: italic; position: absolute; left: calc(50% + 510px); }
.lmd-estimation-detail .ed-ai-result-badges { display: flex !important; align-items: center !important; gap: 8px !important; flex-wrap: wrap !important; flex-shrink: 0 !important; }
.lmd-estimation-detail .ed-ai-badge { padding: 6px 12px !important; background: #e5e7eb !important; border-radius: 6px !important; font-size: 12px !important; font-weight: 600 !important; color: #374151 !important; }
.lmd-estimation-detail .ed-ai-title-badge { font-size: 14px !important; font-weight: 700 !important; text-transform: uppercase !important; padding: 8px 16px !important; background: transparent !important; color: #6b7280 !important; flex-shrink: 0 !important; width: fit-content !important; }
.lmd-estimation-detail .ed-ai-title-badge.ed-ai-badge-green { background: #22c55e !important; color: #fff !important; border-radius: 8px !important; }
.lmd-estimation-detail .ed-ai-tabs-wrapper { display: flex !important; flex-direction: column !important; width: 100% !important; }
.lmd-estimation-detail .ed-ai-chrome-tab.completed { background: #f0fdf4 !important; border-color: #22c55e !important; }
.lmd-estimation-detail .ed-ai-chrome-tab[data-field="matches"].completed .ed-tab-count { background: #22c55e !important; color: #fff !important; }
.lmd-estimation-detail .ed-ai-progress-wrap { max-width: 280px; }
.lmd-estimation-detail .ed-ai-progress-bar { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
.lmd-estimation-detail .ed-ai-progress-fill { height: 100%; background: #22c55e; border-radius: 4px; transition: width 0.3s ease; }
.lmd-estimation-detail .ed-ai-progress-text { font-size: 12px; color: #6b7280; margin-top: 4px; }
.lmd-estimation-detail .ed-ai-slot-badges { grid-column: span 6; display: flex !important; gap: 8px; align-items: center; flex-wrap: wrap; }
.lmd-estimation-detail .ed-ai-tabs-row { display: grid !important; grid-template-columns: repeat(28, 1fr) !important; gap: 4px !important; margin-top: 8px !important; width: 100% !important; }
.lmd-estimation-detail .ed-ai-chrome-tab { padding: 8px 12px !important; background: #fff !important; border: 1px solid #e5e7eb !important; border-bottom: none !important; border-radius: 8px 8px 0 0 !important; border-top: 3px solid #22c55e !important; cursor: pointer; font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase !important; display: flex !important; align-items: center; justify-content: center; gap: 4px !important; font-family: inherit !important; flex-wrap: nowrap !important; }
.lmd-estimation-detail .ed-ai-chrome-tab.open { background: #fff !important; border: 2px solid #22c55e !important; border-bottom: none !important; margin-bottom: -2px !important; z-index: 2 !important; }
.lmd-estimation-detail .ed-ai-chrome-tab.completed .ed-ai-tab-check { color: #22c55e; }
.lmd-estimation-detail .ed-ai-cartouche { display: none !important; border: 2px solid #22c55e; border-top: 2px solid #22c55e !important; border-radius: 0 0 8px 8px; padding: 16px; background: #fff; min-height: 120px; margin-top: -2px; width: 100% !important; }
.lmd-estimation-detail .ed-ai-cartouche.open { display: block !important; border-color: #22c55e; }
.lmd-estimation-detail .ed-ai-panel { font-size: 14px !important; line-height: 1.6 !important; color: #374151 !important; }
.lmd-estimation-detail .ed-ai-panel .ed-ai-inline-link { display: inline !important; word-break: break-word !important; overflow-wrap: break-word !important; }
.lmd-estimation-detail .ed-ai-tab-check { width: 18px; height: 18px; flex-shrink: 0; }
.lmd-estimation-detail .ed-tab-count { font-size: 10px; padding: 2px 6px; font-weight: 600; min-width: 18px; background: #e5e7eb; border-radius: 4px; flex-shrink: 0; white-space: nowrap; }
@media (max-width: 768px) { .lmd-estimation-detail .ed-tab-count { font-size: 9px; padding: 1px 4px; min-width: 16px; } }
.lmd-estimation-detail .ed-ai-panel { display: none; padding: 12px 0; }
.lmd-estimation-detail .ed-ai-panel.open { display: block; }
.lmd-estimation-detail .ed-action-panel textarea, .lmd-estimation-detail .large-text { padding: 10px 12px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; font-family: inherit !important; background: #fff !important; width: 100% !important; min-height: 120px !important; }
.lmd-estimation-detail .ed-action-panel input[type="email"], .lmd-estimation-detail .regular-text { padding: 8px 12px !important; border: 1px solid #e5e7eb !important; border-radius: 6px !important; font-size: 13px !important; background: #fff !important; }
#ed-description-viewer { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999998; display: none; align-items: center; justify-content: center; padding: 40px; }
#ed-description-viewer.open { display: flex !important; }
#ed-description-viewer .ed-description-viewer-inner { background: #fff; border-radius: 12px; padding: 24px; max-width: 500px; width: 100%; max-height: 85vh; box-shadow: 0 8px 32px rgba(0,0,0,0.2); position: relative; }
#ed-description-viewer-close { position: absolute; top: 12px; right: 16px; color: #6b7280; font-size: 24px; cursor: pointer; }
#ed-description-viewer-close:hover { color: #374151; }
.ed-description-voir-suite { margin-top: 8px; padding: 4px 0; background: none; border: none; color: #3b82f6; font-size: 12px; cursor: pointer; text-decoration: underline; }
.ed-description-voir-suite:hover { color: #1d4ed8; }
#ed-photo-viewer { position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 999999; display: none; align-items: center; justify-content: center; flex-direction: column; }
#ed-photo-viewer.open { display: flex; }
#ed-photo-viewer-inner { overflow: auto; flex: 1; display: flex; align-items: center; justify-content: center; width: 100%; padding: 60px 80px; }
#ed-photo-viewer-img { max-width: 100%; max-height: 85vh; object-fit: contain; cursor: grab; transition: transform 0.2s; }
#ed-photo-viewer-img.dragging { cursor: grabbing; }
#ed-photo-viewer-close { position: absolute; top: 16px; right: 20px; color: #fff; font-size: 28px; cursor: pointer; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
#ed-photo-viewer-close:hover { background: rgba(255,255,255,0.15); }
#ed-photo-viewer-toolbar { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; align-items: center; background: rgba(0,0,0,0.6); padding: 10px 16px; border-radius: 12px; }
#ed-photo-viewer-toolbar button { width: 40px; height: 40px; border: none; background: rgba(255,255,255,0.2); color: #fff; border-radius: 8px; cursor: pointer; font-size: 18px; }
#ed-photo-viewer-toolbar button:hover { background: rgba(255,255,255,0.35); }
#ed-photo-viewer-prev, #ed-photo-viewer-next { position: absolute; top: 50%; transform: translateY(-50%); width: 48px; height: 48px; border: none; background: rgba(255,255,255,0.2); color: #fff; border-radius: 50%; cursor: pointer; font-size: 24px; }
#ed-photo-viewer-prev { left: 16px; }
#ed-photo-viewer-next { right: 16px; }
#ed-photo-viewer-prev:hover, #ed-photo-viewer-next:hover { background: rgba(255,255,255,0.35); }
#ed-photo-viewer.single-photo #ed-photo-viewer-prev,
#ed-photo-viewer.single-photo #ed-photo-viewer-next { display: none; }
#ed-photo-viewer-counter { color: #fff; font-size: 14px; margin: 0 8px; }
@keyframes lmd-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
/* Correspondances style Lovable */
.lmd-estimation-detail .ed-ai-section-title { font-size: 12px !important; text-transform: uppercase !important; color: #6b7280 !important; margin: 0 0 12px !important; font-weight: 600 !important; letter-spacing: 0.05em !important; }
.lmd-estimation-detail .ed-correspondances-seller-photos { display: flex !important; gap: 16px !important; flex-wrap: wrap !important; margin-bottom: 20px !important; }
.lmd-estimation-detail .ed-corresp-seller-thumb { display: block !important; border: 2px solid #e5e7eb !important; border-radius: 8px !important; overflow: hidden !important; flex: 0 0 auto !important; }
.lmd-estimation-detail .ed-corresp-seller-thumb img { width: 180px !important; height: 180px !important; object-fit: contain !important; display: block !important; background: #f9fafb !important; }
.lmd-estimation-detail .ed-correspondances-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)) !important; gap: 20px !important; align-items: start !important; }
.lmd-estimation-detail .ed-corresp-item { position: relative !important; display: flex !important; flex-direction: column !important; border: 2px solid #e5e7eb !important; border-radius: 8px !important; overflow: visible !important; background: #f9fafb !important; }
.lmd-estimation-detail .ed-corresp-num { position: absolute !important; top: 6px !important; left: 6px !important; width: 22px !important; height: 22px !important; background: #6b7280 !important; color: #fff !important; border-radius: 50% !important; font-size: 11px !important; font-weight: 700 !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 2 !important; }
.lmd-estimation-detail .ed-corresp-link { display: block !important; flex-shrink: 0 !important; aspect-ratio: 1 !important; background: #f9fafb !important; min-height: 180px !important; overflow: hidden !important; border-radius: 6px 6px 0 0 !important; }
.lmd-estimation-detail .ed-corresp-link--disabled { cursor: default !important; pointer-events: none !important; }
.lmd-estimation-detail .ed-corresp-link img { width: 100% !important; height: 100% !important; object-fit: contain !important; display: block !important; }
.lmd-estimation-detail .ed-corresp-placeholder { display: flex !important; align-items: center !important; justify-content: center !important; padding: 12px !important; font-size: 11px !important; color: #6b7280 !important; text-align: center !important; min-height: 100px !important; }
.lmd-estimation-detail .ed-corresp-verdict { position: absolute !important; top: 6px !important; right: 6px !important; padding: 2px 8px !important; font-size: 10px !important; font-weight: 700 !important; border-radius: 4px !important; z-index: 2 !important; }
.lmd-estimation-detail .ed-corresp-verdict-identique { background: #22c55e !important; color: #fff !important; }
.lmd-estimation-detail .ed-corresp-verdict-similaire,
.lmd-estimation-detail .ed-corresp-verdict-meme-modele { background: #3b82f6 !important; color: #fff !important; }
.lmd-estimation-detail .ed-corresp-verdict-different { background: #dc2626 !important; color: #fff !important; }
.lmd-estimation-detail .ed-corresp-content { flex: 1 !important; min-width: 0 !important; display: flex !important; flex-direction: column !important; padding: 8px !important; gap: 4px !important; }
.lmd-estimation-detail .ed-corresp-details { margin: 0 !important; padding: 0 !important; font-size: 11px !important; color: #6b7280 !important; line-height: 1.4 !important; overflow-wrap: break-word !important; word-break: break-word !important; white-space: normal !important; }
.lmd-estimation-detail .ed-corresp-url { display: block !important; flex-shrink: 0 !important; padding: 6px 8px !important; font-size: 10px !important; color: #6b7280 !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; border-top: 1px solid #e5e7eb !important; }
.lmd-estimation-detail .ed-corresp-notes { margin: 4px 0 0 !important; padding: 6px 8px !important; font-size: 10px !important; color: #374151 !important; background: #f3f4f6 !important; border-radius: 4px !important; max-height: 80px !important; overflow-y: auto !important; line-height: 1.4 !important; }
.lmd-estimation-detail .ed-market-results-list { display: flex !important; flex-direction: column !important; gap: 12px !important; }
.lmd-estimation-detail .ed-market-item { padding: 12px !important; border: 1px solid #e5e7eb !important; border-radius: 8px !important; background: #f9fafb !important; }
.lmd-estimation-detail .ed-market-item a { font-weight: 600 !important; color: #374151 !important; }
.lmd-estimation-detail .ed-market-price { display: inline-block !important; margin-left: 8px !important; font-weight: 600 !important; color: #22c55e !important; }
.lmd-estimation-detail .ed-market-source { display: inline-block !important; margin-left: 8px !important; font-size: 12px !important; color: #6b7280 !important; }
.lmd-estimation-detail .ed-questions-list { margin: 0 !important; padding-left: 20px !important; }
.lmd-estimation-detail .ed-questions-list li { margin-bottom: 8px !important; }
.lmd-estimation-detail .ed-condition-smp-list { margin: 0 0 12px !important; padding-left: 20px !important; }
.lmd-estimation-detail .ed-header-badge { display: inline-block !important; margin-left: 12px !important; padding: 4px 12px !important; font-size: 12px !important; font-weight: 600 !important; border-radius: 6px !important; vertical-align: middle !important; }
.lmd-estimation-detail .ed-header-badge-non-repondu { background: #fef3c7 !important; color: #92400e !important; border: 1px solid #fcd34d !important; }
.lmd-estimation-detail .ed-header-badge-retard { background: #fef2f2 !important; color: #dc2626 !important; border: 1px solid #fecaca !important; }
.lmd-estimation-detail .ed-header-badge-retard-7j { background: #dc2626 !important; color: #fff !important; border: 1px solid #b91c1c !important; }
</style>

<?php
$first = trim(wp_unslash($estimation->client_first_name ?? ""));
$last = trim($estimation->client_last_name ?? "");
$display_name = trim(wp_unslash($estimation->client_name ?? ""));
if ($first || $last) {
    $civ = trim($estimation->client_civility ?? "");
    $display_name = trim(($civ ? $civ . " " : "") . $first . " " . $last);
}
if (!$display_name) {
    $display_name = trim($estimation->client_email ?? "") ?: "-";
}
$ville = trim($estimation->client_commune ?? "");
$nom_et_ville = esc_html($display_name ?: "-");
if ($ville) {
    $nom_et_ville .= " (" . esc_html($ville) . ")";
}
$title_parts = [
    sprintf(
        "Demande d'estimation N°%d de %s",
        max(1, $sender_rank),
        $nom_et_ville,
    ),
];
if ($created_date) {
    $title_parts[] = esc_html($created_date);
}
if (!empty(trim($estimation->client_phone ?? ""))) {
    $title_parts[] =
        '<a href="tel:' .
        esc_attr(preg_replace("/\s+/", "", $estimation->client_phone)) .
        '" class="ed-header-phone">' .
        esc_html($estimation->client_phone) .
        "</a>";
}
if (!empty(trim($estimation->client_email ?? ""))) {
    $title_parts[] =
        '<span class="ed-header-email">' .
        esc_html($estimation->client_email) .
        "</span>";
}
$title_html = implode(" — ", $title_parts);
?>
<?php  ?>
<div class="ed-header ed-header-with-back">
    <a href="<?php echo esc_url(
        function_exists("lmd_app_estimation_admin_url")
            ? lmd_app_estimation_admin_url("list")
            : admin_url("admin.php?page=lmd-estimations-list"),
    ); ?>" class="ed-back-a">&larr; Retour</a>
    <h1 class="ed-header-title"><?php echo $title_html; ?></h1>
</div>

<?php if (!empty($categories)): ?>
<?php
$tag_order = [
    "vente",
    "message",
    "interet",
    "estimation",
    "theme_vente",
    "date_vente",
    "vendeur",
];
$estimation_source = function_exists("lmd_get_estimation_source")
    ? lmd_get_estimation_source(
        $estimation,
        $ai,
        $tags_by_type["estimation"] ?? null,
        $opinion,
    )
    : ["source" => "", "slug" => null, "name" => ""];
function lmd_ed_tag_display(
    $type,
    $current,
    $label,
    $opts,
    $has_ai,
    $estimation_slug,
    $theme_slug,
    $interet_slug,
    $estimation_source,
    $opinion,
    $estimation = null,
) {
    $current_slug = $current ? $current->slug : "";
    $current_name = $current ? $current->name : $label;
    $source = "";
    if ($type === "vendeur" && !$current && $estimation) {
        $form_name =
            trim(wp_unslash($estimation->client_name ?? "")) ?:
            trim(wp_unslash($estimation->client_email ?? ""));
        if ($form_name) {
            $current_name = $form_name;
            $source = "form";
        }
    }
    if (!$current && $has_ai) {
        if ($type === "interet" && $interet_slug) {
            $current_slug = $interet_slug;
            $current_name = function_exists("lmd_get_interet_name")
                ? lmd_get_interet_name($interet_slug)
                : $interet_slug;
            $source = "ia";
        } elseif ($type === "estimation" && $estimation_source["slug"]) {
            $current_slug = $estimation_source["slug"];
            $current_name = $estimation_source["name"];
            $source = $estimation_source["source"];
        } elseif ($type === "theme_vente" && $theme_slug) {
            $current_slug = $theme_slug;
            $current_name = function_exists("lmd_get_theme_vente_name")
                ? lmd_get_theme_vente_name($theme_slug)
                : $theme_slug;
            $source = "ia";
        }
    }
    if ($type === "estimation" && $estimation_source["slug"]) {
        $current_slug = $estimation_source["slug"];
        $current_name = $estimation_source["name"];
        $source = $estimation_source["source"];
    }
    if ($current && !$source) {
        $source = $type === "vendeur" ? "form" : "";
        if ($source === "" && $type !== "vendeur") {
            if (
                function_exists("lmd_is_opinion_specific_tag_type") &&
                lmd_is_opinion_specific_tag_type($type) &&
                function_exists("lmd_get_tag_source_for_display")
            ) {
                $source = lmd_get_tag_source_for_display($current, $opinion);
            } else {
                $source = $opinion === 2 ? "avis2" : "cp";
            }
        }
    }
    if ($type === "message" && $estimation) {
        $m_repondu =
            ($current && ($current->slug ?? "") === "repondu") ||
            !empty($estimation->reponse_sent_at);
        $m_opened = !empty($estimation->first_viewed_at);
        $m_ref = $m_opened
            ? strtotime($estimation->first_viewed_at)
            : (!empty($estimation->created_at)
                ? strtotime($estimation->created_at)
                : 0);
        $m_hours = $m_ref ? (time() - $m_ref) / 3600 : 0;
        if (!$m_repondu && $m_hours >= 48) {
            $m_days = max(1, (int) floor($m_hours / 24));
            $current_name =
                ($m_opened ? "Non répondu" : "Non lu") . " (" . $m_days . "j)";
        }
    }
    $colors = function_exists("lmd_get_tag_filter_colors")
        ? lmd_get_tag_filter_colors($type, $current_slug, $source)
        : ["border" => "#e5e7eb"];
    $border_color =
        $current_slug || ($type === "vendeur" && $source === "form")
            ? $colors["border"] ?? "#e5e7eb"
            : "#e5e7eb";
    $source_class =
        ($current_slug || ($type === "vendeur" && $source === "form")) &&
        $source
            ? " ed-tag-source-" . $source
            : "";
    return compact(
        "current_slug",
        "current_name",
        "border_color",
        "source_class",
    );
}
?>
<div class="ed-tags-bar" id="ed-tags-bar" data-opinion="<?php echo (int) $opinion; ?>">
    <div class="ed-tags-bar-left">
<?php
$opts_order = [
    "interet" => true,
    "estimation" => true,
    "message" => true,
    "vente" => true,
    "theme_vente" => true,
];
$tags_left = ["message"];
foreach ($tags_left as $type):

    if (!isset($categories[$type])) {
        continue;
    }
    $cat = $categories[$type];
    $current = $tags_by_type[$type] ?? null;
    $opts =
        !empty($opts_order[$type]) && !empty($cat["options"])
            ? $cat["options"]
            : $tags_by_cat[$type] ?? [];
    $label = $cat["label"] ?? $type;
    $d = lmd_ed_tag_display(
        $type,
        $current,
        $label,
        $opts,
        $has_ai,
        $estimation_slug,
        $theme_slug,
        $interet_slug,
        $estimation_source,
        $opinion,
        $estimation,
    );
    ?>
    <div class="ed-tag-wrapper" data-type="<?php echo esc_attr(
        $type,
    ); ?>" data-opinion="<?php echo (int) $opinion; ?>">
        <button type="button" class="ed-tag-btn<?php echo esc_attr(
            $d["source_class"] ?? "",
        ); ?>" data-estimation-id="<?php echo (int) $id; ?>" data-type="<?php echo esc_attr(
    $type,
); ?>" data-slug="<?php echo esc_attr(
    $d["current_slug"],
); ?>" style="border-left-color:<?php echo esc_attr(
    $d["border_color"],
); ?> !important;">
            <span class="ed-tag-label"><?php echo function_exists(
                "lmd_esc_tag_name",
            )
                ? lmd_esc_tag_name(
                    $d["current_slug"] || $d["current_name"] !== $label
                        ? $d["current_name"]
                        : $label,
                )
                : esc_html(
                    $d["current_slug"] || $d["current_name"] !== $label
                        ? $d["current_name"]
                        : $label,
                ); ?></span>
            <span class="ed-tag-arrow">▾</span>
        </button>
        <div class="ed-tag-dd" data-type="<?php echo esc_attr($type); ?>">
            <div class="ed-tag-dd-item <?php echo !$d["current_slug"]
                ? "selected"
                : ""; ?>" data-slug="" data-border="#e5e7eb"><?php echo esc_html(
    $label,
); ?></div>
            <?php foreach ($opts as $o):

                $oslug = is_object($o) ? $o->slug : $o["slug"] ?? "";
                $oname = is_object($o) ? $o->name : $o["name"] ?? $oslug;
                $sel = $d["current_slug"] === $oslug;
                $item_border = $sel ? $d["border_color"] : "#e5e7eb";
                ?>
            <div class="ed-tag-dd-item <?php echo $sel
                ? "selected"
                : ""; ?>" data-slug="<?php echo esc_attr(
    $oslug,
); ?>" data-border="<?php echo esc_attr(
    $item_border,
); ?>"><?php echo function_exists("lmd_esc_tag_name")
    ? lmd_esc_tag_name($oname)
    : esc_html($oname); ?></div>
            <?php
            endforeach; ?>
        </div>
    </div>
<?php
endforeach;
?>
    </div>
    <div class="ed-tags-bar-center">
<?php
$tags_center = ["interet", "estimation", "theme_vente"];
foreach ($tags_center as $type):

    if (!isset($categories[$type])) {
        continue;
    }
    $cat = $categories[$type];
    $current = $tags_by_type[$type] ?? null;
    $opts =
        !empty($opts_order[$type]) && !empty($cat["options"])
            ? $cat["options"]
            : $tags_by_cat[$type] ?? [];
    $label = $type === "vente" ? "VV / VJ" : $cat["label"] ?? $type;
    $d = lmd_ed_tag_display(
        $type,
        $current,
        $label,
        $opts,
        $has_ai,
        $estimation_slug,
        $theme_slug,
        $interet_slug,
        $estimation_source,
        $opinion,
        $estimation,
    );
    ?>
    <div class="ed-tag-wrapper" data-type="<?php echo esc_attr(
        $type,
    ); ?>" data-opinion="<?php echo (int) $opinion; ?>">
        <button type="button" class="ed-tag-btn<?php echo esc_attr(
            $d["source_class"] ?? "",
        ); ?>" data-estimation-id="<?php echo (int) $id; ?>" data-type="<?php echo esc_attr(
    $type,
); ?>" data-slug="<?php echo esc_attr(
    $d["current_slug"],
); ?>" style="border-left-color:<?php echo esc_attr(
    $d["border_color"],
); ?> !important;">
            <span class="ed-tag-label"><?php echo function_exists(
                "lmd_esc_tag_name",
            )
                ? lmd_esc_tag_name(
                    $d["current_slug"] || $d["current_name"] !== $label
                        ? $d["current_name"]
                        : $label,
                )
                : esc_html(
                    $d["current_slug"] || $d["current_name"] !== $label
                        ? $d["current_name"]
                        : $label,
                ); ?></span>
            <span class="ed-tag-arrow">▾</span>
        </button>
        <div class="ed-tag-dd" data-type="<?php echo esc_attr($type); ?>">
            <?php if (in_array($type, ["estimation", "theme_vente"], true)): ?>
            <div class="ed-tag-dd-header-row">
                <div class="ed-tag-dd-item <?php echo !$d["current_slug"]
                    ? "selected"
                    : ""; ?>" data-slug="" data-border="#e5e7eb"><?php echo esc_html(
    $label,
); ?></div>
                <button type="button" class="ed-tag-dd-gear" data-type="<?php echo esc_attr(
                    $type,
                ); ?>" title="Personnaliser les catégories">⚙</button>
            </div>
            <?php else: ?>
            <div class="ed-tag-dd-item <?php echo !$d["current_slug"]
                ? "selected"
                : ""; ?>" data-slug="" data-border="#e5e7eb"><?php echo esc_html(
    $label,
); ?></div>
            <?php endif; ?>
            <?php foreach ($opts as $o):

                $oslug = is_object($o) ? $o->slug : $o["slug"] ?? "";
                $oname = is_object($o) ? $o->name : $o["name"] ?? $oslug;
                $sel = $d["current_slug"] === $oslug;
                $item_border = $sel ? $d["border_color"] : "#e5e7eb";
                ?>
            <div class="ed-tag-dd-item <?php echo $sel
                ? "selected"
                : ""; ?>" data-slug="<?php echo esc_attr(
    $oslug,
); ?>" data-border="<?php echo esc_attr(
    $item_border,
); ?>"><?php echo function_exists("lmd_esc_tag_name")
    ? lmd_esc_tag_name($oname)
    : esc_html($oname); ?></div>
            <?php
            endforeach; ?>
            <?php if (
                $type === "theme_vente" &&
                !empty($theme_is_new_from_ai) &&
                $theme_slug
            ): ?>
            <div class="ed-tag-dd-create-theme" data-slug="<?php echo esc_attr(
                $theme_slug,
            ); ?>" data-parent="<?php echo esc_attr(
    $theme_suggested_parent,
); ?>" data-name="<?php echo esc_attr(
    ucwords(str_replace("_", " ", $theme_slug)),
); ?>">
                <span class="ed-tag-dd-create-label">Créer « <?php echo esc_html(
                    ucwords(str_replace("_", " ", $theme_slug)),
                ); ?> »</span>
                <?php if (
                    $theme_suggested_parent &&
                    function_exists("lmd_get_theme_vente_name")
                ): ?>
                <span class="ed-tag-dd-create-parent">(rattacher à <?php echo esc_html(
                    lmd_get_theme_vente_name($theme_suggested_parent),
                ); ?>)</span>
        <?php endif; ?>
    </div>
            <?php endif; ?>
    </div>
</div>
<?php
endforeach;
?>
    </div>
    <div class="ed-tags-bar-right">
<?php
$tags_right = ["date_vente", "vente", "vendeur"];
foreach ($tags_right as $type):

    if (!isset($categories[$type])) {
        continue;
    }
    $cat = $categories[$type];
    $current = $tags_by_type[$type] ?? null;
    $opts =
        !empty($opts_order[$type]) && !empty($cat["options"])
            ? $cat["options"]
            : $tags_by_cat[$type] ?? [];
    $label = $type === "vente" ? "VV / VJ" : $cat["label"] ?? $type;
    $d = lmd_ed_tag_display(
        $type,
        $current,
        $label,
        $opts,
        $has_ai,
        $estimation_slug,
        $theme_slug,
        $interet_slug,
        $estimation_source,
        $opinion,
        $estimation,
    );
    ?>
    <div class="ed-tag-wrapper" data-type="<?php echo esc_attr(
        $type,
    ); ?>" data-opinion="<?php echo (int) $opinion; ?>">
        <?php if ($type === "vente"):

            $vente_slug = $d["current_slug"] ?? "";
            $show_lot = in_array(
                $vente_slug,
                ["volontaire", "judiciaire"],
                true,
            );
            $lot_val = isset($estimation->lot_number)
                ? preg_replace("/\D/", "", (string) $estimation->lot_number)
                : "";
            $lot_display =
                $lot_val !== "" ? str_pad($lot_val, 3, "0", STR_PAD_LEFT) : "";
            ?>
        <div class="ed-tag-vente-case<?php echo in_array(
            $vente_slug,
            ["volontaire", "judiciaire"],
            true,
        )
            ? " ed-tag-vente-has-lot"
            : ""; ?>" style="<?php echo in_array(
    $vente_slug,
    ["volontaire", "judiciaire"],
    true,
)
    ? "border-left-color:" .
        esc_attr($d["border_color"] ?? "#e5e7eb") .
        " !important;"
    : ""; ?>">
            <button type="button" class="ed-tag-btn<?php echo esc_attr(
                $d["source_class"] ?? "",
            ); ?>" data-estimation-id="<?php echo (int) $id; ?>" data-type="<?php echo esc_attr(
    $type,
); ?>" data-slug="<?php echo esc_attr(
    $d["current_slug"],
); ?>" style="border-left-color:<?php echo esc_attr(
    $d["border_color"],
); ?> !important;">
                <span class="ed-tag-label"><?php echo function_exists(
                    "lmd_esc_tag_name",
                )
                    ? lmd_esc_tag_name(
                        $d["current_slug"] || $d["current_name"] !== $label
                            ? $d["current_name"]
                            : $label,
                    )
                    : esc_html(
                        $d["current_slug"] || $d["current_name"] !== $label
                            ? $d["current_name"]
                            : $label,
                    ); ?></span>
                <span class="ed-tag-arrow">▾</span>
            </button>
            <input type="text" class="ed-lot-number" id="ed-lot-number" data-estimation-id="<?php echo (int) $id; ?>" placeholder="Lot" maxlength="3" pattern="[0-9]{1,3}" title="001 à 999" value="<?php echo esc_attr(
    $lot_display,
); ?>" style="<?php echo $show_lot ? "" : "display:none;"; ?>" />
        </div>
        <?php
        else:
             ?>
        <button type="button" class="ed-tag-btn<?php echo esc_attr(
            $d["source_class"] ?? "",
        ); ?>" data-estimation-id="<?php echo (int) $id; ?>" data-type="<?php echo esc_attr(
    $type,
); ?>" data-slug="<?php echo esc_attr(
    $d["current_slug"],
); ?>" style="border-left-color:<?php echo esc_attr(
    $d["border_color"],
); ?> !important;">
            <span class="ed-tag-label"><?php echo function_exists(
                "lmd_esc_tag_name",
            )
                ? lmd_esc_tag_name(
                    $d["current_slug"] || $d["current_name"] !== $label
                        ? $d["current_name"]
                        : $label,
                )
                : esc_html(
                    $d["current_slug"] || $d["current_name"] !== $label
                        ? $d["current_name"]
                        : $label,
                ); ?></span>
            <span class="ed-tag-arrow">▾</span>
        </button>
        <?php
        endif; ?>
        <div class="ed-tag-dd <?php echo $type === "date_vente"
            ? "ed-tag-dd-vente"
            : ""; ?>" data-type="<?php echo esc_attr($type); ?>">
            <?php if ($type === "date_vente"): ?>
            <div class="ed-vente-calendar-panel">
                <div class="ed-vente-month-nav">
                    <button type="button" class="ed-vente-prev">‹</button>
                    <span class="ed-vente-month-label"></span>
                    <button type="button" class="ed-vente-next">›</button>
                </div>
                <div class="ed-vente-calendar-grid"></div>
                <div class="ed-vente-list"></div>
                <div class="ed-vente-create">
                    <div class="ed-vente-create-row">
                        <input type="text" class="ed-vente-name" placeholder="Nom de la vente" />
                        <input type="date" class="ed-vente-date" value="<?php echo esc_attr(
                            date("Y-m-d"),
                        ); ?>" />
                        <button type="button" class="ed-vente-add">Créer</button>
                    </div>
                    <label class="ed-vente-category-label" style="font-size:12px;color:#6b7280;font-weight:600;">Dans quelle catégorie ?</label>
                    <select class="ed-vente-category">
                        <option value="">— Choisir une catégorie —</option>
                        <?php
                        $theme_opts = function_exists(
                            "lmd_get_theme_vente_options_merged",
                        )
                            ? lmd_get_theme_vente_options_merged()
                            : [];
                        foreach ($theme_opts as $t):
                            $tslug = $t["slug"] ?? "";
                            $tname = $t["name"] ?? $tslug;
                            if ($tslug): ?><option value="<?php echo esc_attr(
    $tslug,
); ?>"><?php echo esc_html($tname); ?></option><?php endif;
                        endforeach;
                        ?>
                    </select>
                </div>
            </div>
            <?php else: ?>
            <div class="ed-tag-dd-item <?php echo !$d["current_slug"]
                ? "selected"
                : ""; ?>" data-slug="" data-border="#e5e7eb"><?php echo esc_html(
    $label,
); ?></div>
            <?php foreach ($opts as $o):

                $oslug = is_object($o) ? $o->slug : $o["slug"] ?? "";
                $oname = is_object($o) ? $o->name : $o["name"] ?? $oslug;
                $sel = $d["current_slug"] === $oslug;
                $item_border = $sel ? $d["border_color"] : "#e5e7eb";
                ?>
            <div class="ed-tag-dd-item <?php echo $sel
                ? "selected"
                : ""; ?>" data-slug="<?php echo esc_attr(
    $oslug,
); ?>" data-border="<?php echo esc_attr(
    $item_border,
); ?>"><?php echo function_exists("lmd_esc_tag_name")
    ? lmd_esc_tag_name($oname)
    : esc_html($oname); ?></div>
            <?php
            endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
endforeach;
?>
    </div>
    <a href="<?php echo esc_url(
        $delete_url,
    ); ?>" class="ed-tag-supprimer" onclick="return confirm('Supprimer cette estimation ?');">Supprimer</a>
</div>
<?php endif; ?>

<div class="ed-grid">
    <div class="ed-col ed-col-photos">
        <?php
        $has_smp =
            $has_ai && array_key_exists("signatures_marques_poincons", $ai);
        $smp = $has_smp ? $ai["signatures_marques_poincons"] ?? [] : [];
        if (!empty($photos)):

            $first_url = null;
            foreach ($photos as $idx => $item) {
                $raw = is_string($item)
                    ? $item
                    : $item["url"] ?? ($item["file"] ?? ($item["path"] ?? ""));
                if (!$raw) {
                    continue;
                }
                $u = lmd_ed_photo_url($raw);
                if ($u) {
                    $first_url = $u;
                    break;
                }
            }
            ?>
        <?php if ($first_url): ?>
        <img src="<?php echo esc_url(
            $first_url,
        ); ?>" alt="Photo principale" class="ed-photo-main" id="ed-photo-main" data-url="<?php echo esc_url(
    $first_url,
); ?>">
        <?php endif; ?>
        <div class="ed-photos-grid" id="ed-photos-grid">
            <?php foreach ($photos as $idx => $item):

                $raw = is_string($item)
                    ? $item
                    : $item["url"] ?? ($item["file"] ?? ($item["path"] ?? ""));
                if (!$raw) {
                    continue;
                }
                $img_url = lmd_ed_photo_url($raw);
                if (!$img_url) {
                    continue;
                }
                ?>
            <div class="ed-photo-thumb-wrap">
            <img src="<?php echo esc_url(
                $img_url,
            ); ?>" alt="Photo <?php echo (int) ($idx +
    1); ?>" class="ed-photo-thumb <?php echo $idx === 0
    ? "active"
    : ""; ?>" data-url="<?php echo esc_url(
    $img_url,
); ?>" data-index="<?php echo (int) $idx; ?>">
            </div>
            <?php
            endforeach; ?>
        </div>
        <?php
        else:
             ?>
        <p style="color: #666;">Aucune photo</p>
        <?php
        endif;
        ?>
        <div class="ed-description">
            <?php
            $desc_full = $object_description ?: "-";
            $desc_short_len = 200;
            $desc_is_long = strlen($desc_full) > $desc_short_len;
            $desc_display = $desc_is_long
                ? substr($desc_full, 0, $desc_short_len) . "…"
                : $desc_full;
            ?>
            <p class="ed-description-text" style="margin: 0;"><?php echo nl2br(
                esc_html($desc_display),
            ); ?></p>
            <?php if ($desc_is_long): ?>
            <button type="button" class="ed-description-voir-suite">(voir la suite)</button>
            <div id="ed-description-full-raw" style="display:none;"><?php echo esc_html(
                $desc_full,
            ); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ed-col ed-col-avis">
        <div class="ed-avis-tabs-row">
            <div class="ed-avis-tab ed-tab-blue <?php echo $opinion === 1
                ? "open"
                : ""; ?>" data-opinion="1">1er Avis</div>
            <div class="ed-avis-tab ed-tab-violet <?php echo $opinion === 2
                ? "open"
                : ""; ?>" data-opinion="2">2ème Avis</div>
            </div>
        <div class="ed-avis-cartouche ed-cartouche-blue <?php echo $opinion ===
        1
            ? "open"
            : ""; ?>">
            <textarea class="ed-avis-titre-input" id="avis-titre-1" rows="2" placeholder="Titre" maxlength="200"><?php echo esc_textarea(
                wp_unslash($estimation->avis1_titre ?? ""),
            ); ?></textarea>
            <textarea class="ed-notes" id="textarea-avis-1" rows="4" placeholder="Descriptif..."><?php echo esc_textarea(
                wp_unslash($opinion1),
            ); ?></textarea>
            <input type="text" class="ed-avis-dimension" id="avis-dimension-1" placeholder="Dimension" value="<?php echo esc_attr(
                $estimation->avis1_dimension ?? "",
            ); ?>" />
                <div class="ed-estimate-row">
                    <div class="ed-estimate-field">
                    <span class="ed-estimate-input-wrap">
                        <input type="text" id="estimate-low-1" placeholder="Estime basse" value="<?php echo esc_attr(
                            function_exists("lmd_format_euro_display")
                                ? lmd_format_euro_display(
                                    $estimation->avis1_estimate_low ?? null,
                                )
                                : $estimation->avis1_estimate_low ?? "",
                        ); ?>" />
                        <span class="ed-euro-suffix">€</span>
                    </span>
                    </div>
                    <div class="ed-estimate-field">
                    <span class="ed-estimate-input-wrap">
                        <input type="text" id="prix-reserve-1" placeholder="Prix réserve" value="<?php echo esc_attr(
                            function_exists("lmd_format_euro_display")
                                ? lmd_format_euro_display(
                                    $estimation->avis1_prix_reserve ?? null,
                                )
                                : $estimation->avis1_prix_reserve ?? "",
                        ); ?>" />
                        <span class="ed-euro-suffix">€</span>
                    </span>
                    </div>
                    <div class="ed-estimate-field">
                    <span class="ed-estimate-input-wrap">
                        <input type="text" id="estimate-high-1" placeholder="Estime haute" value="<?php echo esc_attr(
                            function_exists("lmd_format_euro_display")
                                ? lmd_format_euro_display(
                                    $estimation->avis1_estimate_high ?? null,
                                )
                                : $estimation->avis1_estimate_high ?? "",
                        ); ?>" />
                        <span class="ed-euro-suffix">€</span>
                    </span>
                    </div>
                </div>
            </div>
        <div class="ed-avis-cartouche ed-cartouche-violet <?php echo $opinion ===
        2
            ? "open"
            : ""; ?>">
            <textarea class="ed-avis-titre-input" id="avis-titre-2" rows="2" placeholder="Titre" maxlength="200"><?php echo esc_textarea(
                wp_unslash($estimation->avis2_titre ?? ""),
            ); ?></textarea>
            <textarea class="ed-notes" id="textarea-avis-2" rows="4" placeholder="Descriptif..."><?php echo esc_textarea(
                wp_unslash($opinion2),
            ); ?></textarea>
            <input type="text" class="ed-avis-dimension" id="avis-dimension-2" placeholder="Dimension" value="<?php echo esc_attr(
                $estimation->avis2_dimension ?? "",
            ); ?>" />
                <div class="ed-estimate-row">
                    <div class="ed-estimate-field">
                    <span class="ed-estimate-input-wrap">
                        <input type="text" id="estimate-low-2" placeholder="Estime basse" value="<?php echo esc_attr(
                            function_exists("lmd_format_euro_display")
                                ? lmd_format_euro_display(
                                    $estimation->avis2_estimate_low ?? null,
                                )
                                : $estimation->avis2_estimate_low ?? "",
                        ); ?>" />
                        <span class="ed-euro-suffix">€</span>
                    </span>
                    </div>
                    <div class="ed-estimate-field">
                    <span class="ed-estimate-input-wrap">
                        <input type="text" id="prix-reserve-2" placeholder="Prix réserve" value="<?php echo esc_attr(
                            function_exists("lmd_format_euro_display")
                                ? lmd_format_euro_display(
                                    $estimation->avis2_prix_reserve ?? null,
                                )
                                : $estimation->avis2_prix_reserve ?? "",
                        ); ?>" />
                        <span class="ed-euro-suffix">€</span>
                    </span>
                    </div>
                    <div class="ed-estimate-field">
                    <span class="ed-estimate-input-wrap">
                        <input type="text" id="estimate-high-2" placeholder="Estime haute" value="<?php echo esc_attr(
                            function_exists("lmd_format_euro_display")
                                ? lmd_format_euro_display(
                                    $estimation->avis2_estimate_high ?? null,
                                )
                                : $estimation->avis2_estimate_high ?? "",
                        ); ?>" />
                        <span class="ed-euro-suffix">€</span>
                    </span>
                    </div>
                </div>
            </div>
    </div>

    <?php
    $default_reponse_subject = sprintf(
        'Réponse à votre demande d\'estimation du %s',
        $created_date ?: date_i18n(get_option("date_format")),
    );
    $reponse_subject = !empty($estimation->reponse_subject)
        ? $estimation->reponse_subject
        : $default_reponse_subject;
    $client_display = trim(
        wp_unslash($estimation->client_civility ?? "") .
            " " .
            wp_unslash($estimation->client_first_name ?? "") .
            " " .
            wp_unslash($estimation->client_name ?? ""),
    );
    if (!$client_display) {
        $client_display =
            wp_unslash($estimation->client_name ?? "") ?:
            wp_unslash($estimation->client_email ?? "");
    }
    $reponse_body_default = "Bonjour " . trim($client_display) . ",\n\n";
    $reponse_sent_at = !empty($estimation->reponse_sent_at)
        ? $estimation->reponse_sent_at
        : null;
    $reponse_questions_selected = !empty(
        $estimation->reponse_questions_selected
    )
        ? json_decode($estimation->reponse_questions_selected, true)
        : [];
    $reponse_questions_selected = is_array($reponse_questions_selected)
        ? $reponse_questions_selected
        : [];
    $ai_questions_col3 = $ai["questions"] ?? [];
    $ai_questions_col3 = is_array($ai_questions_col3) ? $ai_questions_col3 : [];
    ?>
    <div class="ed-col ed-col-actions ed-actions has-open" id="ed-col-actions">
        <div class="ed-actions-tabs-row">
            <div class="ed-actions-tab ed-tab-blue <?php echo $col3_action ===
            "reponse"
                ? "open"
                : ""; ?>" data-action="reponse">Réponse vendeur</div>
            <div class="ed-actions-center">
                <a href="mailto:<?php echo esc_attr(
                    $estimation->client_email ?? "",
                ); ?>" class="ed-icon-btn" title="Envoyer un email">✉</a>
                <button type="button" class="ed-icon-btn ed-cp-settings-btn" title="Paramètres">⚙</button>
            </div>
            <div class="ed-actions-tab ed-tab-violet <?php echo $col3_action ===
            "deleguer"
                ? "open"
                : ""; ?>" data-action="deleguer">Déléguer Estimation</div>
        </div>
        <div class="ed-actions-cartouche ed-cartouche-<?php echo $col3_action ===
        "deleguer"
            ? "violet"
            : "blue"; ?> open" id="ed-actions-cartouche">
            <div class="ed-action-cartouche-reponse <?php echo $col3_action ===
            "reponse"
                ? "ed-active"
                : ""; ?>" id="action-cartouche-reponse" data-action="reponse">
                <div class="ed-action-panel" id="action-panel-reponse">
                    <input type="text" class="ed-email-objet" id="reponse-objet-<?php echo (int) $id; ?>" placeholder="Objet" value="<?php echo esc_attr(
    wp_unslash($reponse_subject),
); ?>" />
                    <textarea class="ed-email-corps" id="reponse-corps-<?php echo (int) $id; ?>" placeholder="Corps du message"><?php echo esc_textarea(
    wp_unslash($estimation->reponse_body ?? $reponse_body_default),
); ?></textarea>
                    <?php if (!$reponse_sent_at): ?>
                    <div class="ed-reponse-preview-wrap" id="ed-reponse-preview-wrap-<?php echo (int) $id; ?>">
                        <div class="ed-reponse-preview-title"><?php echo esc_html__(
                            "Aperçu — message pour le vendeur",
                            "lmd-apps-ia",
                        ); ?></div>
                        <p class="ed-reponse-preview-hint"><?php echo esc_html__(
                            "Le bouton Envoi ouvre votre messagerie avec un message en texte brut (compatible tous les clients). Les liens et les logos (images) ne restent pas en HTML dans ce message : chaque lien est recopié sous la forme libellé + adresse, chaque image sous la forme légende ou [Image] + adresse du fichier, pour que tout soit cliquable ou recopiable depuis le texte.",
                            "lmd-apps-ia",
                        ); ?></p>
                        <div class="ed-reponse-preview-label"><?php echo esc_html__(
                            "Texte exact envoyé (corps + signature)",
                            "lmd-apps-ia",
                        ); ?></div>
                        <pre class="ed-reponse-preview-plain" id="ed-reponse-preview-plain-<?php echo (int) $id; ?>" aria-live="polite"></pre>
                        <div class="ed-reponse-preview-sig-label" id="ed-reponse-preview-sig-label-<?php echo (int) $id; ?>"><?php echo esc_html__(
    "Rendu HTML de la signature (aperçu)",
    "lmd-apps-ia",
); ?></div>
                        <div class="ed-reponse-preview-sig" id="ed-reponse-preview-sig-<?php echo (int) $id; ?>"></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($reponse_sent_at): ?>
                    <div class="ed-courrier-parti" style="margin-top: 8px; padding: 12px; background: #f0fdf4; border: 1px solid #22c55e; border-radius: 8px; font-weight: 700; text-transform: uppercase; font-size: 12px; color: #166534;">
                        Ce courrier est parti le <?php echo esc_html(
                            date_i18n("d/m/Y", strtotime($reponse_sent_at)),
                        ); ?> à <?php echo esc_html(
     date_i18n("H:i", strtotime($reponse_sent_at)),
 ); ?>
                    </div>
                    <?php else: ?>
                    <div class="ed-fq-zone">
                        <div class="ed-fq-tabs-row">
                            <div class="ed-fq-tabs-left">
                                <div class="ed-fq-tab" data-fq="questions">Questions de l'IA</div>
                                <?php if (!empty($ai_questions_col3)): ?>
                                <button type="button" class="button ed-questions-ok" title="Enregistrer la sélection" <?php echo empty(
                                    $reponse_questions_selected
                                )
                                    ? "disabled"
                                    : ""; ?>>OK</button>
                                <?php endif; ?>
                            </div>
                            <div class="ed-fq-tab open" data-fq="formules">Formules enregistrées <span class="ed-fq-gear ed-formules-settings-btn" title="Enregistrer, modifier, supprimer des formules">⚙</span></div>
                        </div>
                        <div class="ed-fq-cartouche">
                            <div class="ed-fq-panel" data-fq="formules">
                                <select id="reponse-formule-<?php echo (int) $id; ?>" class="ed-formule-select" style="width:100%;"><option value="">Choisir une formule</option></select>
                            </div>
                            <div class="ed-fq-panel" data-fq="questions" style="display:none;">
                                <?php if (!empty($ai_questions_col3)): ?>
                                <div class="ed-questions-ia-list">
                                    <?php foreach (
                                        $ai_questions_col3
                                        as $qi => $q
                                    ):

                                        $q = trim((string) $q);
                                        if ($q === "") {
                                            continue;
                                        }
                                        $sel = in_array(
                                            $qi,
                                            $reponse_questions_selected,
                                            true,
                                        );
                                        ?>
                                    <div class="ed-question-ia-item <?php echo $sel
                                        ? "selected"
                                        : ""; ?>" data-idx="<?php echo (int) $qi; ?>"><?php echo esc_html(
    $q,
); ?></div>
                                    <?php
                                    endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p style="margin:0;color:#6b7280;">Aucune question (lancez l'analyse IA)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ed-actions-send-row">
                            <button type="button" class="ed-send-btn ed-send-btn-left lmd-send-reponse" data-id="<?php echo (int) $id; ?>" data-email="<?php echo esc_attr(
    $estimation->client_email ?? "",
); ?>">Envoi</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ed-action-cartouche-deleguer <?php echo $col3_action ===
            "deleguer"
                ? "ed-active"
                : ""; ?>" id="action-cartouche-deleguer" data-action="deleguer">
                <div class="ed-action-panel" id="action-panel-deleguer">
                    <input type="email" class="ed-email-objet" id="delegation-email-<?php echo (int) $id; ?>" placeholder="Destinataire (enregistré automatiquement)" value="<?php echo esc_attr(
    $estimation->delegation_email ?? "",
); ?>" list="delegation-recipients-list-<?php echo (int) $id; ?>" />
                    <datalist id="delegation-recipients-list-<?php echo (int) $id; ?>"></datalist>
                    <input type="text" class="ed-email-objet" id="delegation-objet-<?php echo (int) $id; ?>" placeholder="Objet" value="<?php echo esc_attr(
    wp_unslash($estimation->delegation_subject ?? ""),
); ?>" />
                    <textarea class="ed-email-corps" id="delegation-corps-<?php echo (int) $id; ?>" placeholder="Message ou instructions..."><?php echo esc_textarea(
    wp_unslash(
        $estimation->delegation_body ?? ($estimation->delegation_draft ?? ""),
    ),
); ?></textarea>
                    <div class="ed-actions-send-row">
                        <button type="button" class="ed-send-btn ed-send-btn-left lmd-generate-delegation-link" data-id="<?php echo (int) $id; ?>">Générer lien d'accès</button>
                        <button type="button" class="ed-send-btn ed-send-btn-right lmd-send-delegation" data-id="<?php echo (int) $id; ?>">Envoi</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Paramètres CP -->
    <div id="ed-cp-settings-modal" class="ed-modal">
        <div class="ed-modal-inner">
            <h3>Paramètres commissaire-priseur</h3>
            <p><label>Votre adresse email <input type="email" id="cp-settings-email" class="regular-text" style="width:100%;" /></label></p>
            <p><label>Signature (HTML autorisé, images via médiathèque) <textarea id="cp-settings-signature" rows="6" class="large-text" style="width:100%;"></textarea></label></p>
            <p><label>Copie des envois vers (emails séparés par virgule) <input type="text" id="cp-settings-copy" class="regular-text" style="width:100%;" placeholder="email1@exemple.fr, email2@exemple.fr" /></label></p>
            <p><button type="button" class="button button-primary lmd-save-cp-settings">Enregistrer</button> <button type="button" class="button" id="ed-cp-settings-close-btn">Fermer</button></p>
        </div>
            </div>

    <!-- Modal Formules -->
    <div id="ed-formules-modal" class="ed-modal">
        <div class="ed-modal-inner ed-formules-modal-inner">
            <h3>Formules enregistrées</h3>
            <div id="ed-formules-list" class="ed-formules-list"></div>
            <p><button type="button" class="button lmd-add-formule-btn" id="ed-formules-add-btn">Enregistrer une formule</button></p>
            <div id="ed-formules-form" class="ed-formules-form">
                <input type="hidden" id="formule-edit-id" value="" />
                <p><label>Nom <input type="text" id="formule-new-name" class="regular-text" style="width:100%;" placeholder="Ex: Politesse" /></label></p>
                <p><label>Contenu <textarea id="formule-new-content" rows="10" class="large-text" style="width:100%;min-height:180px;" placeholder="Texte ou phrase..."></textarea></label></p>
                <p><button type="button" class="button button-primary lmd-save-formule-btn">Enregistrer</button> <button type="button" class="button lmd-cancel-formule-btn">Annuler</button></p>
            </div>
            <p style="margin-top: 20px;"><button type="button" class="button" id="ed-formules-close-btn">Fermer</button></p>
        </div>
    </div>
</div>

<?php if ($has_ai && empty($estimation->ai_error_reported_at)): ?>
<div class="ed-ai-error-zone" id="ed-ai-error-zone">
    <button type="button" class="ed-ai-error-octagon" id="ed-ai-error-btn" data-id="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr(
    wp_create_nonce("lmd_report_ai_" . $id),
); ?>">
        <span class="ed-ai-error-octagon-text">L'IA se trompe ?</span>
    </button>
    </div>
<?php endif; ?>

<!-- Section estimation IA - pleine largeur (une seule ligne, pas de doublon) -->
<div class="ed-ai-section ed-ai-fullwidth" id="ed-ai-section">
    <?php
    $ai_est =
        $has_ai && function_exists("lmd_get_ai_estimation")
            ? lmd_get_ai_estimation($ai)
            : ["low" => null, "high" => null];
    $ai_est_slug = $has_ai ? trim($ai["estimation"] ?? "") : "";
    ?>
    <div class="ed-ai-full" id="ed-ai-full" style="display: block;">
        <div class="ed-ai-head-row ed-ai-main-line<?php echo !empty(
            $estimation->ai_error_reported_at
        )
            ? " ed-ai-has-thanks"
            : ""; ?>">
            <div class="ed-ai-badge-wrap">
                <span class="ed-ai-btn-slot" style="display:inline-block;width:1px;min-width:fit-content;"><button type="button" class="ed-ai-btn ed-ai-btn-large" id="ed-ai-launch-btn-2" data-id="<?php echo (int) $id; ?>" <?php echo $is_analyzing
    ? " disabled"
    : ""; ?>>
                    <span class="ed-ai-btn-text">AIDE À L'ESTIMATION</span>
                    <span class="ed-ai-btn-progress" id="ed-ai-btn-progress" style="display: <?php echo $is_analyzing
                        ? "inline-flex"
                        : "none"; ?>; align-items: center; gap: 6px;">
                        <span class="ed-ai-spinner" style="display:inline-block;width:16px;height:16px;animation:lmd-spin 1s linear infinite;">⟳</span>
                        <span class="ed-ai-pct" id="ed-ai-pct">0%</span>
                    </span>
                </button></span>
            </div>
            <?php if (!empty($estimation->ai_error_reported_at)): ?>
            <span class="ed-ai-error-thanks-inline">Merci pour votre contribution.</span>
            <?php elseif ($has_ai): ?>
            <div class="ed-ai-result-badges">
                <?php if ($ai_est["low"] !== null || $ai_est_slug): ?>
                <span class="ed-ai-badge">Estimation: <?php if (
                    $ai_est["low"] !== null
                ) {
                    echo esc_html(
                        number_format($ai_est["low"], 0, ",", " ") .
                            ($ai_est["high"] !== null
                                ? " – " .
                                    number_format($ai_est["high"], 0, ",", " ")
                                : "") .
                            " €",
                    );
                } else {
                    echo esc_html(
                        function_exists("lmd_get_estimation_name")
                            ? lmd_get_estimation_name($ai_est_slug)
                            : $ai_est_slug,
                    );
                } ?></span>
                <?php endif; ?>
                <?php if (!empty($ai["interest_level"])): ?>
                <span class="ed-ai-badge">Intérêt: <?php echo esc_html(
                    $ai["interest_level"],
                ); ?></span>
                <?php endif; ?>
                <?php if (!empty($ai["reliability"])): ?>
                <?php
                $rel = $ai["reliability"];
                $rel_5 =
                    is_numeric($rel) && $rel >= 1 && $rel <= 5
                        ? (int) $rel
                        : (stripos($rel, "faible") !== false
                            ? 1
                            : (stripos($rel, "moyenne") !== false
                                ? 3
                                : (stripos($rel, "élevée") !== false ||
                                stripos($rel, "elevee") !== false
                                    ? 5
                                    : null)));
                ?>
                <span class="ed-ai-badge">Fiabilité: <?php echo esc_html(
                    $rel_5 !== null ? $rel_5 . "/5" : $rel,
                ); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $show_tabs_cartouche = $has_ai; /* Onglets + cartouche uniquement après résultats (évite la ligne grise avant lancement) */
        $ai_identity = $ai["identity"] ?? ($ai["biography"] ?? "");
        $ai_condition = $ai["condition"] ?? ($ai["etat"] ?? "");
        $correspondances_raw = $ai["correspondances"] ?? [];
        $correspondances = array_slice($correspondances_raw, 0, 8);
        $market_results = $ai["market_results"] ?? [];
        $ai_questions = $ai["questions"] ?? [];
        $ai_market_legacy = $ai["market"] ?? "";
        $lmd_markdown_links = function ($s) {
            if (!is_string($s) || trim($s) === "") {
                return $s;
            }
            return preg_replace_callback(
                "/\[([^\]]*)\]\((https?:\/\/[^\)\s]+)\)/",
                function ($m) {
                    $url = filter_var($m[2], FILTER_VALIDATE_URL) ? $m[2] : "";
                    if (!$url) {
                        return esc_html($m[0]);
                    }
                    return '<a href="' .
                        esc_url($url) .
                        '" target="_blank" rel="noopener" class="ed-ai-inline-link">' .
                        esc_html($m[1]) .
                        "</a>";
                },
                esc_html($s),
            );
        };
        $lmd_ai_format = function ($v) {
            if (is_array($v)) {
                $out = [];
                foreach ($v as $i => $item) {
                    if (is_array($item)) {
                        $out[] = implode(
                            " — ",
                            array_filter(
                                array_map(function ($x) {
                                    return is_scalar($x) ? (string) $x : "";
                                }, $item),
                            ),
                        );
                    } else {
                        $out[] = (string) $item;
                    }
                }
                return implode("\n", array_filter($out)) ?: "-";
            }
            $s = trim((string) $v);
            return $s !== "" ? $s : "-";
        };
        $cnt_identity = $ai_summary || $ai_identity ? 1 : 0;
        $cnt_matches = is_array($correspondances) ? count($correspondances) : 0;
        $cnt_market = is_array($market_results)
            ? count($market_results)
            : (trim($ai_market_legacy)
                ? 1
                : 0);
        $cnt_condition = trim($ai_condition) ? 1 : 0;
        $cnt_questions = is_array($ai_questions) ? count($ai_questions) : 0;
        ?>
        <div class="ed-ai-tabs-wrapper" id="ed-ai-tabs-wrapper" style="display: <?php echo $show_tabs_cartouche
            ? "flex"
            : "none"; ?>;">
        <!-- Ligne 5 onglets -->
        <div class="ed-ai-tabs-row">
            <div style="grid-column: span 1;"></div>
            <div class="ed-ai-chrome-tab <?php echo $cnt_identity
                ? "completed"
                : ""; ?>" data-field="identity" id="ed-tab-identity" style="grid-column: span 5;">
                <span class="ed-ai-tab-check"><?php echo $cnt_identity
                    ? "✓"
                    : "○"; ?></span>
                IDENTITÉ / BIOGRAPHIE <?php echo $cnt_identity > 1
                    ? '<span class="ed-tab-count">' .
                        (int) $cnt_identity .
                        "</span>"
                    : ""; ?>
            </div>
            <div style="grid-column: span 1;"></div>
            <div class="ed-ai-chrome-tab <?php echo $cnt_matches
                ? "completed"
                : ""; ?>" data-field="matches" id="ed-tab-matches" style="grid-column: span 5;">
                <span class="ed-ai-tab-check"><?php echo $cnt_matches
                    ? "✓"
                    : "○"; ?></span>
                CORRESPONDANCES <?php echo $cnt_matches > 0
                    ? '<span class="ed-tab-count">' .
                        (int) $cnt_matches .
                        "</span>"
                    : ""; ?>
            </div>
            <div style="grid-column: span 1;"></div>
            <div class="ed-ai-chrome-tab <?php echo $cnt_market
                ? "completed"
                : ""; ?>" data-field="market" id="ed-tab-market" style="grid-column: span 5;">
                <span class="ed-ai-tab-check"><?php echo $cnt_market
                    ? "✓"
                    : "○"; ?></span>
                RÉSULTATS MARCHÉ <?php echo $cnt_market > 0
                    ? '<span class="ed-tab-count">' .
                        (int) $cnt_market .
                        "</span>"
                    : ""; ?>
            </div>
            <div style="grid-column: span 1;"></div>
            <div class="ed-ai-chrome-tab <?php echo $cnt_condition
                ? "completed"
                : ""; ?>" data-field="condition" id="ed-tab-condition" style="grid-column: span 3;">
                <span class="ed-ai-tab-check"><?php echo $cnt_condition
                    ? "✓"
                    : "○"; ?></span> ÉTAT
            </div>
            <div style="grid-column: span 1;"></div>
            <div class="ed-ai-chrome-tab <?php echo $cnt_questions
                ? "completed"
                : ""; ?>" data-field="questions" id="ed-tab-questions" style="grid-column: span 4;">
                <span class="ed-ai-tab-check"><?php echo $cnt_questions
                    ? "✓"
                    : "○"; ?></span>
                QUESTIONS <?php echo $cnt_questions > 0
                    ? '<span class="ed-tab-count">' .
                        (int) $cnt_questions .
                        "</span>"
                    : ""; ?>
            </div>
            <div style="grid-column: span 1;"></div>
        </div>
        <div class="ed-ai-cartouche" id="ed-ai-cartouche">
            <div class="ed-ai-panel" data-field="identity" id="ai-panel-identity"><?php
            if ($ai_summary_first) {
                echo "<strong>" . esc_html($ai_summary_first) . "</strong>";
                if ($ai_summary_rest) {
                    echo " " . nl2br($lmd_markdown_links($ai_summary_rest));
                }
                echo "<br><br>";
            }
            $identity_text = $lmd_ai_format($ai_identity);
            if ($identity_text !== "-") {
                echo nl2br($lmd_markdown_links($identity_text));
            }
            ?></div>
            <div class="ed-ai-panel" data-field="matches" id="ai-panel-matches"><?php if (
                !empty($correspondances)
            ):
                echo '<h4 class="ed-ai-section-title">Objet à expertiser</h4>';
                echo '<div class="ed-correspondances-seller-photos">';
                foreach (array_slice($photos, 0, 4) as $idx => $item):

                    $raw = is_string($item)
                        ? $item
                        : $item["url"] ??
                            ($item["file"] ?? ($item["path"] ?? ""));
                    if (!$raw) {
                        continue;
                    }
                    $img_url = lmd_ed_photo_url($raw);
                    if (!$img_url) {
                        continue;
                    }
                    ?><a href="<?php echo esc_url(
    $img_url,
); ?>" target="_blank" rel="noopener" class="ed-corresp-seller-thumb"><img src="<?php echo esc_url(
    $img_url,
); ?>" alt="Photo vendeur <?php echo (int) ($idx + 1); ?>"></a><?php
                endforeach;
                echo "</div>";
                echo '<h4 class="ed-ai-section-title">Correspondances trouvées (' .
                    count($correspondances) .
                    ")</h4>";
                echo '<div class="ed-correspondances-grid">';
                foreach ($correspondances as $i => $c):

                    $thumb = $c["thumbnail"] ?? ($c["url"] ?? "");
                    $url = $c["url"] ?? "";
                    if (
                        $url === "" &&
                        !empty($ai["visual_matches"][$i]["link"])
                    ) {
                        $url = (string) $ai["visual_matches"][$i]["link"];
                    }
                    $title = $c["title"] ?? "";
                    $verdict = $c["verdict"] ?? "similaire";
                    $details = trim($c["details"] ?? "");
                    $notes = trim($c["notes"] ?? "");
                    $verdict_label =
                        $verdict === "identique"
                            ? "Identique"
                            : ($verdict === "même_modèle"
                                ? "Même modèle"
                                : ($verdict === "différent"
                                    ? "Différent"
                                    : "Similaire"));
                    $verdict_class =
                        $verdict === "identique"
                            ? "identique"
                            : ($verdict === "différent"
                                ? "different"
                                : ($verdict === "même_modèle"
                                    ? "meme-modele"
                                    : "similaire"));
                    $has_click_url = is_string($url) && trim($url) !== "";
                    ?><div class="ed-corresp-item">
                        <span class="ed-corresp-num"><?php echo (int) ($i +
                            1); ?></span>
                        <?php if ($has_click_url): ?>
                        <a href="<?php echo esc_url(
                            $url,
                        ); ?>" target="_blank" rel="noopener" class="ed-corresp-link" title="<?php echo esc_attr(
    $title,
); ?>">
                        <?php else: ?>
                        <div class="ed-corresp-link ed-corresp-link--disabled" title="<?php echo esc_attr(
                            $title ?: "Lien indisponible",
                        ); ?>">
                        <?php endif; ?>
                            <?php if ($thumb): ?><img src="<?php echo esc_url(
    $thumb,
); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='block';"><span class="ed-corresp-placeholder" style="display:none;"><?php echo esc_html(
    substr($title, 0, 20),
); ?></span><?php else: ?><span class="ed-corresp-placeholder"><?php echo esc_html(
    substr($title, 0, 30),
); ?></span><?php endif; ?>
                        <?php if ($has_click_url): ?>
                        </a>
                        <?php else: ?>
                        </div>
                        <?php endif; ?>
                        <span class="ed-corresp-verdict ed-corresp-verdict-<?php echo esc_attr(
                            $verdict_class,
                        ); ?>" <?php echo $details
    ? ' title="' . esc_attr($details) . '"'
    : ""; ?>><?php echo $verdict === "identique"
    ? "✓"
    : ($verdict === "différent"
        ? "✗"
        : "~"); ?> <?php echo esc_html($verdict_label); ?></span>
                        <div class="ed-corresp-content">
                            <?php if (
                                $details
                            ): ?><p class="ed-corresp-details"><?php echo nl2br(
    esc_html($details),
); ?></p><?php endif; ?>
                            <?php if ($url): ?><a href="<?php echo esc_url(
    $url,
); ?>" target="_blank" rel="noopener" class="ed-corresp-url"><?php echo esc_html(
    parse_url($url, PHP_URL_HOST) ?: $url,
); ?></a><?php endif; ?>
                            <?php if (
                                $notes
                            ): ?><p class="ed-corresp-notes" title="Contenu scrapé (lien possiblement inaccessible)"><?php echo nl2br(
    esc_html($notes),
); ?></p><?php endif; ?>
        </div>
                    </div><?php
                endforeach;
                echo "</div>";
            else:
                $legacy_matches = $ai["matches"] ?? "";
                echo nl2br(
                    esc_html(
                        $lmd_ai_format($legacy_matches) !== "-"
                            ? $lmd_ai_format($legacy_matches)
                            : "Aucune correspondance trouvée.",
                    ),
                );
            endif; ?></div>
            <div class="ed-ai-panel" data-field="market" id="ai-panel-market"><?php if (
                !empty($market_results)
            ):
                echo '<div class="ed-market-results-list">';
                foreach ($market_results as $i => $mr):

                    $title = $mr["title"] ?? "";
                    $url = $mr["url"] ?? ($mr["link"] ?? "");
                    $price = $mr["price"] ?? "";
                    $source = $mr["source"] ?? "";
                    $relevance = $mr["relevance"] ?? "";
                    $notes = $mr["notes"] ?? "";
                    ?><div class="ed-market-item">
                        <?php if ($title || $url):
                            if ($url): ?><a href="<?php echo esc_url(
    $url,
); ?>" target="_blank" rel="noopener"><?php echo esc_html(
    $title ?: parse_url($url, PHP_URL_HOST),
); ?></a><?php else: ?><span class="ed-market-title"><?php echo esc_html(
    $title ?: "Référence",
); ?></span><?php endif;
                        endif; ?>
                        <?php if (
                            $price
                        ): ?><span class="ed-market-price"><?php echo esc_html(
    $price,
); ?></span><?php endif; ?>
                        <?php if (
                            $source
                        ): ?><span class="ed-market-source"><?php echo esc_html(
    $source,
); ?></span><?php endif; ?>
                        <?php if (
                            $relevance
                        ): ?><span class="ed-market-relevance"><?php echo esc_html(
    $relevance,
); ?></span><?php endif; ?>
                        <?php if (
                            $notes
                        ): ?><p class="ed-market-notes"><?php echo nl2br(
    $lmd_markdown_links($notes),
); ?></p><?php endif; ?>
                    </div><?php
                endforeach;
                echo "</div>";
            else:
                echo nl2br(
                    $lmd_markdown_links(
                        $lmd_ai_format($ai_market_legacy) !== "-"
                            ? $lmd_ai_format($ai_market_legacy)
                            : "Aucun résultat marché.",
                    ),
                );
            endif; ?></div>
            <div class="ed-ai-panel" data-field="condition" id="ai-panel-condition"><?php
            if ($has_smp && !empty($smp)):
                echo '<h4 class="ed-ai-section-title">Signatures / Marques / Poinçons (analyse des photos)</h4>';
                echo '<ul class="ed-condition-smp-list">';
                foreach ($smp as $item):
                    $idx = (int) ($item["photo_index"] ?? 0);
                    $types = $item["types"] ?? [];
                    $desc = esc_html($item["description"] ?? "");
                    $labels = array_map(function ($t) {
                        return $t === "signature"
                            ? "Signature"
                            : ($t === "marque"
                                ? "Marque"
                                : ($t === "poincon"
                                    ? "Poinçon"
                                    : $t));
                    }, $types);
                    $type_str = !empty($labels)
                        ? " [" . implode(", ", $labels) . "]"
                        : "";
                    echo "<li><strong>Photo " .
                        (int) ($idx + 1) .
                        "</strong>" .
                        ($type_str ? " " . esc_html($type_str) : "") .
                        ": " .
                        $desc .
                        "</li>";
                endforeach;
                echo "</ul>";
                if (trim($ai_condition)) {
                    echo '<h4 class="ed-ai-section-title" style="margin-top:16px;">État général</h4>';
                }
            endif;
            echo nl2br(
                esc_html(
                    $lmd_ai_format($ai_condition) !== "-"
                        ? $lmd_ai_format($ai_condition)
                        : ($has_smp && !empty($smp)
                            ? ""
                            : "-"),
                ),
            );
            ?></div>
            <div class="ed-ai-panel" data-field="questions" id="ai-panel-questions"><?php if (
                !empty($ai_questions)
            ):
                echo '<ul class="ed-questions-list">';
                foreach ($ai_questions as $q):
                    $q = trim((string) $q);
                    if ($q !== "") {
                        echo "<li>" . esc_html($q) . "</li>";
                    }
                endforeach;
                echo "</ul>";
            else:
                echo nl2br(
                    esc_html(
                        $lmd_ai_format($ai_questions) !== "-"
                            ? $lmd_ai_format($ai_questions)
                            : "Aucune question.",
                    ),
                );
            endif; ?></div>
    </div>
        </div>
    </div>
</div>

</div>

<div id="ed-description-viewer" style="display: none;">
    <span id="ed-description-viewer-close" title="Fermer">&times;</span>
    <div class="ed-description-viewer-inner">
        <h4 style="margin: 0 0 12px; font-size: 12px; text-transform: uppercase; color: #9ca3af;">Description objet</h4>
        <div id="ed-description-viewer-content" style="overflow-y: auto; max-height: 70vh; padding-right: 8px; font-size: 14px; line-height: 1.6; color: #374151;"></div>
    </div>
</div>
<div id="ed-photo-viewer">
    <span id="ed-photo-viewer-close" title="Fermer">&times;</span>
    <button type="button" id="ed-photo-viewer-prev" title="Photo précédente">&lsaquo;</button>
    <button type="button" id="ed-photo-viewer-next" title="Photo suivante">&rsaquo;</button>
    <div id="ed-photo-viewer-inner">
        <img src="" alt="" id="ed-photo-viewer-img">
    </div>
    <div id="ed-photo-viewer-toolbar">
        <button type="button" id="ed-photo-zoom-out" title="Zoom arrière">−</button>
        <span id="ed-photo-viewer-counter">1 / 1</span>
        <button type="button" id="ed-photo-zoom-in" title="Zoom avant">+</button>
        <button type="button" id="ed-photo-zoom-reset" title="Réinitialiser">100%</button>
    </div>
</div>

<div id="ed-category-modal" class="lmd-estimation-detail">
    <div class="ed-cat-modal-inner">
        <div class="ed-cat-modal-header" id="ed-cat-modal-title">Personnaliser les catégories</div>
        <div class="ed-cat-modal-body" id="ed-cat-modal-body"></div>
        <div class="ed-cat-modal-footer">
            <button type="button" class="button" id="ed-cat-modal-cancel">Annuler</button>
            <button type="button" class="button button-primary" id="ed-cat-modal-save">Enregistrer</button>
        </div>
    </div>
</div>

<script>
var lmdEdMailto = <?php echo wp_json_encode([
    "copyEmails" => array_values(
        array_filter($cp_for_mailto["copy_emails"] ?? []),
    ),
    "bccExcludeSlugs" => array_values($bcc_exclude_slugs_mail),
    "interetSlug" => (string) $interet_slug,
    "estimationSlug" => (string) $estimation_slug,
    "signatureHtml" => (string) ($cp_for_mailto["signature"] ?? ""),
]); ?>;
(function($){
    var ajaxurl = typeof lmdAdmin !== 'undefined' ? lmdAdmin.ajaxurl : '<?php echo esc_js(
        admin_url("admin-ajax.php"),
    ); ?>';
    var nonce = typeof lmdAdmin !== 'undefined' ? lmdAdmin.nonce : '<?php echo wp_create_nonce(
        "lmd_admin",
    ); ?>';
    var estId = <?php echo (int) $id; ?>;
    var hasAi = <?php echo $has_ai ? "true" : "false"; ?>;
    var currentOpinion = <?php echo (int) $opinion; ?>;
    var themeOptsForModal = <?php echo json_encode(
        function_exists("lmd_get_theme_vente_options_merged")
            ? array_map(function ($o) {
                return ["slug" => $o["slug"], "name" => $o["name"]];
            }, lmd_get_theme_vente_options_merged())
            : [],
    ); ?>;

    function lmdShowMessage(title, message, onClose) {
        var $overlay = $('<div class="ed-ai-feedback-overlay"><div class="ed-ai-feedback-card"><div class="ed-ai-feedback-title">' + (title || 'Information').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div><div class="ed-ai-feedback-sub">' + (message || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div><div class="ed-ai-explain-actions" style="margin-top:20px;"><button type="button" class="ed-ai-btn ed-ai-msg-ok">OK</button></div></div></div>');
        $('body').append($overlay);
        $overlay.find('.ed-ai-msg-ok').on('click', function(){
            $overlay.fadeOut(150, function(){ $(this).remove(); if (typeof onClose === 'function') onClose(); });
        });
        $overlay.on('click', function(ev){ if (ev.target === this) { $overlay.fadeOut(150, function(){ $(this).remove(); if (typeof onClose === 'function') onClose(); }); } });
    }

    function lmd_set_tag_by_slug(estimationId, type, slug) {
        var op = parseInt($('#ed-tags-bar').data('opinion'), 10) || 1;
        $.post(ajaxurl, {
            action: 'lmd_set_tag_by_slug',
            nonce: nonce,
            estimation_id: estimationId,
            type: type,
            slug: slug || '',
            opinion: op
        });
    }

    function positionTagPanel($wrapper) {
        var $btn = $wrapper.find('.ed-tag-btn'), $dd = $wrapper.find('.ed-tag-dd');
        if (!$btn.length || !$dd.length) return;
        var r = $btn[0].getBoundingClientRect();
        $dd.css({ top: (r.bottom + 4) + 'px', left: r.left + 'px', minWidth: Math.max(r.width, 200) + 'px' });
    }
    $('#ed-tags-bar').on('click', '.ed-tag-btn', function(e){
        e.stopPropagation();
        var $btn = $(this), $wrapper = $btn.closest('.ed-tag-wrapper'), $dd = $wrapper.find('.ed-tag-dd');
        $('#ed-tags-bar .ed-tag-wrapper').not($wrapper).removeClass('open');
        $wrapper.toggleClass('open');
        if ($wrapper.hasClass('open')) positionTagPanel($wrapper);
    });
    $(document).on('click', '.ed-tag-dd-item', function(){
        var slug = $(this).data('slug'), border = $(this).data('border') || '#e5e7eb';
        var $wrapper = $(this).closest('.ed-tag-wrapper'), type = $wrapper.data('type');
        var $btn = $wrapper.find('.ed-tag-btn');
        var op = parseInt($('#ed-tags-bar').data('opinion') || $wrapper.data('opinion'), 10) || 1;
        if (type === 'estimation') {
            var low = $.trim($('#estimate-low-' + op).val() || ''), high = $.trim($('#estimate-high-' + op).val() || '');
            if (!low && !high) {
                $wrapper.removeClass('open');
                return;
            }
        }
        $btn.removeClass('ed-tag-source-ia ed-tag-source-form ed-tag-source-cp ed-tag-source-avis2');
        if (slug) {
            var srcClass = (type === 'vendeur') ? 'ed-tag-source-form' : (op === 2 ? 'ed-tag-source-avis2' : 'ed-tag-source-cp');
            $btn.addClass(srcClass);
            border = (type === 'vendeur') ? '#22c55e' : (op === 2 ? '#8b5cf6' : '#3b82f6');
        }
        $wrapper.find('.ed-tag-dd-item').removeClass('selected');
        $(this).addClass('selected');
        $btn.find('.ed-tag-label').text($.trim($(this).text()));
        $btn.data('slug', slug).css('border-left-color', border);
        $wrapper.removeClass('open');
        lmd_set_tag_by_slug($btn.data('estimation-id'), type, slug);
        if (type === 'vente') {
            var $lot = $('#ed-lot-number');
            if ($lot.length) $lot.toggle(slug === 'volontaire' || slug === 'judiciaire');
            var $case = $wrapper.find('.ed-tag-vente-case');
            if ($case.length) $case.css('border-left-color', (slug === 'volontaire' || slug === 'judiciaire') ? border : '#e5e7eb');
        }
    });
    $(document).on('click', function(e){
        if (!$(e.target).closest('.ed-tag-wrapper').length) $('#ed-tags-bar .ed-tag-wrapper').removeClass('open');
    });

    $('#ed-ai-error-btn').on('click', function(){
        var $btn = $(this);
        if ($btn.prop('disabled')) return;
        var id = $btn.data('id');
        var reportNonce = $btn.data('nonce');
        $btn.prop('disabled', true);
        var submitted = false;
        function doSubmit(wantsExplain, explanation) {
            if (submitted) return;
            submitted = true;
            var expl = (wantsExplain && explanation) ? $.trim(explanation) : '';
            $.post(ajaxurl, { action: 'lmd_report_ai_error', id: id, nonce: reportNonce, user_explanation: expl }).done(function(r){
                if (r.success) {
                    $explainOverlay.remove();
                    $btn.prop('disabled', false);
                    location.reload();
                } else {
                    submitted = false;
                    $btn.prop('disabled', false);
                }
            }).fail(function(){ submitted = false; $btn.prop('disabled', false); });
        }
        var $explainOverlay = $('<div class="ed-ai-feedback-overlay"><div class="ed-ai-feedback-card ed-ai-explain-card" style="max-width:420px;"><div class="ed-ai-feedback-icon">✓</div><div class="ed-ai-feedback-title">Merci de votre collaboration</div><div class="ed-ai-feedback-sub">Si vous souhaitez nous expliquer son erreur, l\'IA sera meilleure au prochain lancement.</div><div class="ed-ai-explain-divider"></div><div class="ed-ai-explain-actions" style="margin-top:20px;"><button type="button" class="ed-ai-btn ed-ai-pas-le-temps">Pas le temps</button><button type="button" class="ed-ai-btn ed-ai-explain-ok">OK</button></div></div></div>');
        $('body').append($explainOverlay);

        $explainOverlay.find('.ed-ai-pas-le-temps').on('click', function(){
            if (submitted) return;
            submitted = true;
            $.post(ajaxurl, { action: 'lmd_report_ai_error', id: id, nonce: reportNonce, user_explanation: '' }).done(function(r){
                if (r.success) {
                    $explainOverlay.remove();
                    $btn.prop('disabled', false);
                    location.reload();
                } else {
                    submitted = false;
                }
            }).fail(function(){ submitted = false; });
        });

        $explainOverlay.find('.ed-ai-explain-ok').on('click', function(){
            $explainOverlay.find('.ed-ai-explain-card').html('<div class="ed-ai-feedback-title" style="font-size:16px;margin-top:0;">Explication de l\'erreur</div><div class="ed-ai-explain-textarea-wrap"><textarea class="ed-ai-explain-textarea" placeholder="Ex: L\'IA affiche une fiabilité élevée sans aucune correspondance visuelle, ou l\'estimation ne correspond pas à mes informations..."></textarea></div><div class="ed-ai-explain-actions" style="margin-top:20px;"><button type="button" class="ed-ai-btn ed-ai-explain-send">Envoyer et continuer</button></div>');
            $explainOverlay.find('.ed-ai-explain-send').on('click', function(){
                doSubmit(true, $explainOverlay.find('.ed-ai-explain-textarea').val());
            });
        });
    });

    $('#ed-tags-bar').on('click', '.ed-tag-dd-create-theme', function(e){
        e.stopPropagation();
        var slug = $(this).data('slug'), name = $(this).data('name'), parent = $(this).data('parent') || '';
        $(this).closest('.ed-tag-wrapper').removeClass('open');
        $('#ed-category-modal').data('type', 'theme_vente').addClass('open');
        $('#ed-cat-modal-title').text('Créer cette catégorie thème');
        var sel = '<select class="ed-cat-parent"><option value="">—</option>';
        themeOptsForModal.forEach(function(t){ sel += '<option value="'+t.slug+'"'+(t.slug===parent?' selected':'')+'>'+t.name+'</option>'; });
        sel += '</select>';
        $('#ed-cat-modal-body').html('<div class="ed-cat-row"><input type="hidden" class="ed-cat-slug" value="'+slug.replace(/"/g,'&quot;')+'"><input type="text" class="ed-cat-name" placeholder="Nom du thème" value="'+(name||'').replace(/"/g,'&quot;')+'">'+sel+'<button type="button" class="ed-cat-del">×</button></div><button type="button" class="ed-cat-add">+ Ajouter un thème</button>');
    });

    $('#ed-tags-bar').on('click', '.ed-tag-dd-gear', function(e){
        e.stopPropagation();
        var type = $(this).data('type');
        $(this).closest('.ed-tag-wrapper').removeClass('open');
        $('#ed-category-modal').data('type', type).addClass('open');
        $('#ed-cat-modal-title').text(type === 'estimation' ? 'Personnaliser les intervalles d\'estimation' : 'Personnaliser les thèmes de vente');
        $.post(ajaxurl, { action: 'lmd_get_category_settings', nonce: nonce, type: type }).done(function(r){
            if (r.success && r.data && r.data.options) {
                var opts = r.data.options;
                var html = '';
                if (type === 'estimation') {
                    opts.forEach(function(o, i){
                        var maxVal = (o.max !== undefined && o.max !== null && o.max !== '') ? o.max : '';
                        var minVal = (o.min !== undefined && o.min !== null && o.min !== '') ? o.min : '';
                        html += '<div class="ed-cat-row" data-idx="'+i+'"><input type="hidden" class="ed-cat-slug" value="'+(o.slug||'').replace(/"/g,'&quot;')+'"><input type="text" class="ed-cat-name" placeholder="Libellé" value="'+(o.name||'').replace(/"/g,'&quot;')+'"><input type="number" class="ed-cat-max" placeholder="Max €" value="'+maxVal+'" min="0" step="1"><span style="color:#9ca3af;font-size:12px;">ou</span><input type="number" class="ed-cat-min" placeholder="Min €" value="'+minVal+'" min="0" step="1"><button type="button" class="ed-cat-del">×</button></div>';
                    });
                    html += '<button type="button" class="ed-cat-add">+ Ajouter un intervalle</button>';
                } else {
                    opts.forEach(function(o, i){
                        var parent = (o.parent_slug || '');
                        var sel = '<select class="ed-cat-parent"><option value="">—</option>';
                        themeOptsForModal.forEach(function(t){ if (t.slug !== o.slug) sel += '<option value="'+t.slug+'"'+(t.slug===parent?' selected':'')+'>'+t.name+'</option>'; });
                        sel += '</select>';
                        html += '<div class="ed-cat-row" data-idx="'+i+'"><input type="hidden" class="ed-cat-slug" value="'+(o.slug||'').replace(/"/g,'&quot;')+'"><input type="text" class="ed-cat-name" placeholder="Nom du thème" value="'+(o.name||'').replace(/"/g,'&quot;')+'">'+sel+'<button type="button" class="ed-cat-del">×</button></div>';
                    });
                    html += '<button type="button" class="ed-cat-add">+ Ajouter un thème</button>';
                }
                $('#ed-cat-modal-body').html(html);
            }
        });
    });

    $('#ed-cat-modal-body').on('click', '.ed-cat-del', function(){ $(this).closest('.ed-cat-row').remove(); });
    $('#ed-cat-modal-body').on('click', '.ed-cat-add', function(){
        var type = $('#ed-category-modal').data('type');
        if (type === 'estimation') {
            $('#ed-cat-modal-body').find('.ed-cat-add').before('<div class="ed-cat-row"><input type="hidden" class="ed-cat-slug" value=""><input type="text" class="ed-cat-name" placeholder="Libellé"><input type="number" class="ed-cat-max" placeholder="Max €" min="0" step="1"><span style="color:#9ca3af;font-size:12px;">ou</span><input type="number" class="ed-cat-min" placeholder="Min €" min="0" step="1"><button type="button" class="ed-cat-del">×</button></div>');
        } else {
            var sel = '<select class="ed-cat-parent"><option value="">—</option>';
            themeOptsForModal.forEach(function(t){ sel += '<option value="'+t.slug+'">'+t.name+'</option>'; });
            sel += '</select>';
            $('#ed-cat-modal-body').find('.ed-cat-add').before('<div class="ed-cat-row"><input type="hidden" class="ed-cat-slug" value=""><input type="text" class="ed-cat-name" placeholder="Nom du thème">'+sel+'<button type="button" class="ed-cat-del">×</button></div>');
        }
    });
    $('#ed-cat-modal-cancel').on('click', function(){ $('#ed-category-modal').removeClass('open'); });
    $('#ed-category-modal').on('click', function(e){ if (e.target === this) $(this).removeClass('open'); });
    $('#ed-cat-modal-save').on('click', function(){
        var type = $('#ed-category-modal').data('type');
        var $rows = $('#ed-cat-modal-body .ed-cat-row');
        var opts = [];
        $rows.each(function(){
            var $r = $(this);
            var name = $r.find('.ed-cat-name').val();
            var slug = $r.find('.ed-cat-slug').val();
            if (!slug) slug = name ? name.toLowerCase().replace(/[^a-z0-9\u00e0-\u00ff]+/g, '_').replace(/_+/g,'_').replace(/^_|_$/g,'') : '';
            if (!slug) return;
            if (type === 'estimation') {
                var max = $r.find('.ed-cat-max').val();
                var min = $r.find('.ed-cat-min').val();
                if (max !== '' || min !== '') opts.push({ slug: slug, name: name, max: max || null, min: min || null });
            } else {
                var parent = $r.find('.ed-cat-parent').val() || '';
                opts.push({ slug: slug, name: name, parent_slug: parent });
            }
        });
        $.post(ajaxurl, { action: 'lmd_save_category_settings', nonce: nonce, type: type, options: opts }).done(function(r){
            if (r.success) { $('#ed-category-modal').removeClass('open'); location.reload(); }
        });
    });

    /* Date de vente - calendrier */
    var venteCalMonth = new Date().getMonth(), venteCalYear = new Date().getFullYear(), venteList = [], hotelList = [];
    var monthsFr = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    function loadVentes(cb) {
        $.post(ajaxurl, { action: 'lmd_list_ventes', nonce: nonce }).done(function(r){
            if (r.success && r.data && r.data.ventes) venteList = r.data.ventes;
            hotelList = (r.success && r.data && r.data.hotel_calendar) ? r.data.hotel_calendar : [];
            if (cb) cb();
        });
    }
    function slugToDate(slug) { var m = slug.match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[0] : null; }
    function renderVenteCalendar($panel) {
        var first = new Date(venteCalYear, venteCalMonth, 1);
        var last = new Date(venteCalYear, venteCalMonth + 1, 0);
        var startDay = first.getDay() || 7;
        var days = last.getDate();
        var prevMonth = new Date(venteCalYear, venteCalMonth - 1);
        var prevDays = new Date(prevMonth.getFullYear(), prevMonth.getMonth() + 1, 0).getDate();
        $panel.find('.ed-vente-month-label').text(monthsFr[venteCalMonth] + ' ' + venteCalYear);
        var html = '<div class="ed-vente-dow">L</div><div class="ed-vente-dow">M</div><div class="ed-vente-dow">M</div><div class="ed-vente-dow">J</div><div class="ed-vente-dow">V</div><div class="ed-vente-dow">S</div><div class="ed-vente-dow">D</div>';
        for (var i = 1; i < startDay; i++) html += '<div class="ed-vente-day other-month">' + (prevDays - startDay + i + 1) + '</div>';
        for (var d = 1; d <= days; d++) {
            var dt = venteCalYear + '-' + String(venteCalMonth + 1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            var hasV = venteList.some(function(v){ return slugToDate(v.slug) === dt; });
            var hotelH = hotelList.filter(function(h){ return h.date === dt; });
            var hasHotel = hotelH.length > 0;
            var ht = hasHotel ? hotelH.map(function(h){ return (h.title || '').replace(/"/g,'&quot;'); }).join(' · ') : '';
            html += '<div class="ed-vente-day' + (hasV ? ' has-vente' : '') + (hasHotel ? ' has-hotel-prep' : '') + '" data-date="' + dt + '" title="' + (hasHotel ? ('Calendrier hôtel — ' + ht) : '') + '">' + d + '</div>';
        }
        var remaining = 42 - (startDay - 1 + days);
        for (var j = 1; j <= remaining; j++) html += '<div class="ed-vente-day other-month">' + j + '</div>';
        $panel.find('.ed-vente-calendar-grid').html(html);
        var monthVentes = venteList.filter(function(v){ var d = slugToDate(v.slug); return d && d.startsWith(venteCalYear + '-' + String(venteCalMonth + 1).padStart(2,'0')); });
        var listHtml = '';
        monthVentes.forEach(function(v){
            var d = slugToDate(v.slug) || '';
            var catBadge = (v.theme_vente_name || v.theme_vente_slug) ? '<span class="ed-vente-cat-badge" title="' + (v.theme_vente_name || v.theme_vente_slug || '').replace(/"/g,'&quot;') + '">' + (v.theme_vente_name || v.theme_vente_slug || '').substring(0, 20) + '</span>' : '';
            listHtml += '<div class="ed-vente-list-item" data-id="' + v.id + '" data-slug="' + v.slug + '"><label><input type="checkbox" class="ed-vente-select" ' + (v.slug === ($('#ed-tags-bar .ed-tag-wrapper[data-type="date_vente"] .ed-tag-btn').data('slug') || '') ? 'checked' : '') + '></label><input type="text" class="ed-vente-item-name" value="' + (v.name || '').replace(/"/g,'&quot;') + '"><input type="date" class="ed-vente-item-date" value="' + d + '">' + catBadge + '</div>';
        });
        var monthHotels = hotelList.filter(function(h){ return h.date && h.date.indexOf(venteCalYear + '-' + String(venteCalMonth + 1).padStart(2,'0')) === 0; });
        if (monthHotels.length) {
            listHtml += '<div class="ed-vente-hotel-block" style="margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb;"><strong style="font-size:12px;color:#047857;">Ventes hôtel (calendrier officiel)</strong>';
            monthHotels.forEach(function(h){
                listHtml += '<div class="ed-vente-hotel-row" style="font-size:12px;color:#065f46;padding:6px 0;">' + (h.title || '').replace(/</g,'&lt;') + ' <span style="color:#6b7280;">(' + h.date + ')</span></div>';
            });
            listHtml += '</div>';
        }
        $panel.find('.ed-vente-list').html(listHtml || '<div class="ed-vente-list-empty" style="padding:8px;color:#9ca3af;font-size:12px;">Aucune vente ce mois</div>');
    }
    $('#ed-tags-bar').on('click', '.ed-tag-wrapper[data-type="date_vente"] .ed-tag-btn', function(e){
        var $wrapper = $(this).closest('.ed-tag-wrapper');
        if ($wrapper.hasClass('open')) {
            var $panel = $wrapper.find('.ed-vente-calendar-panel');
            if ($panel.length) loadVentes(function(){ renderVenteCalendar($panel); });
        }
    });
    $('#ed-tags-bar').on('click', '.ed-vente-prev', function(){ venteCalMonth--; if (venteCalMonth < 0) { venteCalMonth = 11; venteCalYear--; } renderVenteCalendar($(this).closest('.ed-vente-calendar-panel')); });
    $('#ed-tags-bar').on('click', '.ed-vente-next', function(){ venteCalMonth++; if (venteCalMonth > 11) { venteCalMonth = 0; venteCalYear++; } renderVenteCalendar($(this).closest('.ed-vente-calendar-panel')); });
    $('#ed-tags-bar').on('click', '.ed-vente-day:not(.other-month)', function(){
        var dt = $(this).data('date');
        var v = venteList.find(function(x){ return slugToDate(x.slug) === dt; });
        if (v) { lmd_set_tag_by_slug(estId, 'date_vente', v.slug); var $w = $('#ed-tags-bar .ed-tag-wrapper[data-type="date_vente"]'); $w.find('.ed-tag-label').text(v.name); $w.find('.ed-tag-btn').data('slug', v.slug).css('border-left-color', '#c7d2fe'); $w.removeClass('open'); }
    });
    $('#ed-tags-bar').on('change', '.ed-vente-select', function(){
        var $item = $(this).closest('.ed-vente-list-item'), slug = $item.data('slug'), name = $item.find('.ed-vente-item-name').val();
        if ($(this).is(':checked')) { lmd_set_tag_by_slug(estId, 'date_vente', slug); var $w = $('#ed-tags-bar .ed-tag-wrapper[data-type="date_vente"]'); $w.find('.ed-tag-label').text(name); $w.find('.ed-tag-btn').data('slug', slug).css('border-left-color', '#c7d2fe'); }
        else { lmd_set_tag_by_slug(estId, 'date_vente', ''); }
    });
    $('#ed-tags-bar').on('blur', '.ed-vente-item-name, .ed-vente-item-date', function(){
        var $item = $(this).closest('.ed-vente-list-item'), id = $item.data('id'), name = $item.find('.ed-vente-item-name').val(), date = $item.find('.ed-vente-item-date').val();
        if (!id || !date) return;
        $.post(ajaxurl, { action: 'lmd_update_vente', nonce: nonce, tag_id: id, name: name, date: date }).done(function(r){
            if (r.success) loadVentes(function(){ var $p = $item.closest('.ed-vente-calendar-panel'); if ($p.length) renderVenteCalendar($p); });
        });
    });
    $('#ed-tags-bar').on('click', '.ed-vente-add', function(){
        var $create = $(this).closest('.ed-vente-create'), name = $create.find('.ed-vente-name').val(), date = $create.find('.ed-vente-date').val(), themeSlug = $create.find('.ed-vente-category').val();
        if (!name || !date) return;
        $.post(ajaxurl, { action: 'lmd_create_vente', nonce: nonce, name: name, date: date, theme_vente_slug: themeSlug || '' }).done(function(r){
            if (r.success) { venteList.push({ id: r.data.tag_id, slug: r.data.slug, name: r.data.name, theme_vente_slug: themeSlug || null }); $create.find('.ed-vente-name, .ed-vente-date').val(''); $create.find('.ed-vente-category').val(''); loadVentes(function(){ renderVenteCalendar($create.closest('.ed-vente-calendar-panel')); }); }
        });
    });

    $('.ed-avis-tab[data-opinion]').on('click', function(){
        var op = $(this).data('opinion');
        if (op === currentOpinion) return;
        var col3Action = $('.ed-actions-tabs-row .ed-actions-tab.open').data('action') || 'reponse';
        window.location.href = '?page=lmd-estimation-detail&id=' + estId + '&opinion=' + op + '&col3=' + col3Action;
    });


    function saveAvis(opinion) {
        var text = $('#textarea-avis-' + opinion).val();
        var titre = $('#avis-titre-' + opinion).val();
        var dimension = $('#avis-dimension-' + opinion).val();
        var low = $('#estimate-low-' + opinion).val().replace(/\s/g,'').replace(',','.');
        var high = $('#estimate-high-' + opinion).val().replace(/\s/g,'').replace(',','.');
        var reserve = $('#prix-reserve-' + opinion).val().replace(/\s/g,'').replace(',','.');
        var action = opinion === 1 ? 'lmd_save_avis1_estimates' : 'lmd_save_avis2_estimates';
        $.post(ajaxurl, {
            action: action,
            nonce: nonce,
            id: estId,
            avis_text: text,
            avis_titre: titre,
            avis_dimension: dimension,
            estimate_low: low,
            estimate_high: high,
            prix_reserve: reserve
        }).done(function(r) {
            if (r && r.success && r.data && r.data.tag_estimation) {
                var t = r.data.tag_estimation;
                var $w = $('#ed-tags-bar .ed-tag-wrapper[data-type="estimation"]');
                if ($w.length) {
                    var displayName = (t.slug || t.name !== t.label) ? t.name : t.label;
                    $w.find('.ed-tag-label').text(displayName);
                    $w.find('.ed-tag-btn').data('slug', t.slug).attr('style', 'border-left-color:' + (t.border_color || '#e5e7eb') + ' !important;').removeClass('ed-tag-source-ia ed-tag-source-cp ed-tag-source-avis2').addClass(t.source_class || '');
                }
            }
        });
    }
    var saveAvisDebounce = {};
    [1, 2].forEach(function(opinion) {
        saveAvisDebounce[opinion] = (function(){
            var t;
            return function() {
                clearTimeout(t);
                t = setTimeout(function(){ saveAvis(opinion); }, 600);
            };
        })();
    });
    $('#avis-titre-1, #textarea-avis-1, #avis-dimension-1, #estimate-low-1, #estimate-high-1, #prix-reserve-1').on('blur input', function(){ saveAvisDebounce[1](); });
    $('#avis-titre-2, #textarea-avis-2, #avis-dimension-2, #estimate-low-2, #estimate-high-2, #prix-reserve-2').on('blur input', function(){ saveAvisDebounce[2](); });

    var saveLotDebounce;
    $('#ed-lot-number').on('blur input', function(){
        var $in = $(this);
        clearTimeout(saveLotDebounce);
        saveLotDebounce = setTimeout(function(){
            var val = $in.val().replace(/\D/g, '').slice(0, 3);
            if (val !== '') val = String(parseInt(val, 10)).padStart(3, '0');
            $.post(ajaxurl, { action: 'lmd_save_lot_number', nonce: nonce, estimation_id: $in.data('estimation-id'), lot_number: val || '' });
        }, 400);
    });

    function updateTabProgress(pct) {
        var $tabs = $('.ed-ai-chrome-tab');
        var fields = ['identity', 'matches', 'market', 'condition', 'questions'];
        var thresholds = [20, 40, 60, 80, 100];
        fields.forEach(function(f, i) {
            if (pct >= thresholds[i]) {
                $tabs.filter('[data-field="' + f + '"]').addClass('completed').find('.ed-ai-tab-check').text('✓');
            }
        });
    }

    function startAnalysis(e) {
        e.preventDefault();
        var $btn = $('#ed-ai-launch-btn, #ed-ai-launch-btn-2').filter(':visible').first();
        if (!$btn.length) return;
        var id = $btn.data('id');
        if (hasAi) {
            var $overlay = $('<div class="ed-ai-feedback-overlay ed-ai-confirm-overlay"><div class="ed-ai-feedback-card ed-ai-confirm-card"><div class="ed-ai-feedback-title">Relancer l\'analyse</div><div class="ed-ai-feedback-sub">Les résultats actuels seront remplacés. Continuer ?</div><div class="ed-ai-explain-actions" style="margin-top:20px;"><button type="button" class="ed-ai-btn ed-ai-confirm-cancel">Annuler</button><button type="button" class="ed-ai-btn ed-ai-confirm-ok">Continuer</button></div></div></div>');
            $('body').append($overlay);
            $overlay.find('.ed-ai-confirm-ok').on('click', function(){ $overlay.fadeOut(150, function(){ $(this).remove(); }); doStartAnalysis(false); });
            $overlay.find('.ed-ai-confirm-cancel').on('click', function(){ $overlay.fadeOut(150, function(){ $(this).remove(); }); });
            $overlay.on('click', function(ev){ if (ev.target === this) { $overlay.fadeOut(150, function(){ $(this).remove(); }); } });
            return;
        }
        doStartAnalysis(false);
    }
    function doStartAnalysis(skipConfirm) {
        var $btn = $('#ed-ai-launch-btn, #ed-ai-launch-btn-2').filter(':visible').first();
        if (!$btn.length) return;
        var id = $btn.data('id');
        $btn.prop('disabled', true);
        if (!$('#ed-ai-full').length) {
            location.reload();
            return;
        }
        $('#ed-ai-launch-btn-2').prop('disabled', true);
        $('#ed-ai-btn-progress').show().find('#ed-ai-pct').text('0%');
        // Lancer le polling immédiatement (pour voir la progression même en mode synchrone MAMP)
        pollAnalysis(id, $('#ed-ai-launch-btn-2'));
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'lmd_launch_analysis', nonce: nonce, id: id },
            timeout: 180000,
            success: function(r){
                if (r && !r.success) {
                    $btn.prop('disabled', false);
                    $('#ed-ai-btn-progress').hide();
                    lmdShowMessage('Erreur', r.data && r.data.message ? r.data.message : 'Une erreur est survenue.');
                }
            },
            error: function(xhr, status, err){
                // Ne pas alerter immédiatement : l'analyse peut continuer côté serveur (ex: timeout AJAX à 65% alors que Gemini termine).
                // Le polling détectera le succès ou l'échec. On laisse le polling gérer.
            }
        });
    }

    function pollAnalysis(id, $btn) {
        var attempts = 0, maxAttempts = 120;
        var interval = setInterval(function(){
            attempts++;
            $.post(ajaxurl, { action: 'lmd_check_analysis_status', nonce: nonce, id: id }).done(function(r){
                if (r.success && r.data && r.data.status === 'ai_analyzed') {
                    clearInterval(interval);
                    $('#ed-ai-pct').text('100%');
                    updateTabProgress(100);
                    $('#ed-ai-btn-progress').hide();
                    $btn.prop('disabled', false);
                    setTimeout(function(){
                        var nextUrl = new URL(window.location.href);
                        nextUrl.searchParams.set('ai_tab', 'identity');
                        window.location.href = nextUrl.toString();
                    }, 500);
                } else if (r.success && r.data && r.data.status === 'error') {
                    clearInterval(interval);
                    $('#ed-ai-btn-progress').hide();
                    $btn.prop('disabled', false);
                    lmdShowMessage('Erreur d\'analyse', r.data.message || 'Erreur inconnue', function(){ location.reload(); });
                } else if (r.success && r.data) {
                    var pct = Math.min(100, parseInt(r.data.percent, 10) || 0);
                    $('#ed-ai-pct').text(pct + '%');
                    updateTabProgress(pct);
                }
            });
            if (attempts >= maxAttempts) {
                clearInterval(interval);
                $('#ed-ai-btn-progress').hide();
                $btn.prop('disabled', false);
                lmdShowMessage('Délai dépassé', 'L\'analyse a pris trop de temps. Vérifiez les clés API (Gemini, SerpAPI, ImgBB) et la configuration.', function(){ location.reload(); });
            }
        }, 1500);
    }

    $('#ed-ai-launch-btn, #ed-ai-launch-btn-2').on('click', function(e){ startAnalysis(e); });

    <?php if ($is_analyzing): ?>
    (function startPollingOnLoad(){
        var id = estId;
        var $btn = $('#ed-ai-launch-btn-2');
        pollAnalysis(id, $btn);
    })();
    <?php endif; ?>

    function setChromeTab(field, forceOpen) {
        var $cartouche = $('#ed-ai-cartouche'), $panels = $cartouche.find('.ed-ai-panel'), $tabs = $('.ed-ai-chrome-tab');
        var $active = $tabs.filter('[data-field="' + field + '"]');
        if (!$active.length) return;
        var wasOpen = $active.hasClass('open');
        var shouldOpen = forceOpen === true ? true : !wasOpen;
        $tabs.removeClass('open');
        $panels.removeClass('open');
        if (shouldOpen) {
            $active.addClass('open');
            $panels.filter('[data-field="' + field + '"]').addClass('open');
        }
        $cartouche.toggleClass('open', $tabs.filter('.open').length > 0);
    }
    function toggleChromeTab(field) {
        setChromeTab(field, false);
    }
    $('.ed-ai-chrome-tab').on('click', function(){ toggleChromeTab($(this).data('field')); });
    (function openAiTabFromQuery(){
        var params = new URLSearchParams(window.location.search);
        var aiTab = params.get('ai_tab');
        if (!aiTab) return;
        setChromeTab(aiTab, true);
        params.delete('ai_tab');
        var cleaned = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        window.history.replaceState({}, document.title, cleaned);
    })();

    (function(){
        var photoUrls = [];
        $('.ed-photo-thumb').each(function(){ photoUrls.push($(this).data('url')); });
        var currentIndex = 0, zoom = 1, panX = 0, panY = 0, isDragging = false, startX, startY;

        function openViewer(index) {
            if (!photoUrls.length) return;
            currentIndex = Math.max(0, Math.min(index, photoUrls.length - 1));
            zoom = 1; panX = 0; panY = 0;
            $('#ed-photo-viewer-img').attr('src', photoUrls[currentIndex]).css({ transform: 'scale(1) translate(0,0)' });
            $('#ed-photo-viewer-counter').text((currentIndex + 1) + ' / ' + photoUrls.length);
            $('#ed-photo-viewer').toggleClass('single-photo', photoUrls.length <= 1).addClass('open');
            $('.ed-photo-thumb').removeClass('active').eq(currentIndex).addClass('active');
            if ($('#ed-photo-main').length && currentIndex === 0) {
                $('#ed-photo-main').attr('src', photoUrls[0]);
            }
        }
        function updateZoom() {
            $('#ed-photo-viewer-img').css('transform', 'scale(' + zoom + ') translate(' + panX + 'px,' + panY + 'px)');
            $('#ed-photo-zoom-reset').text(Math.round(zoom * 100) + '%');
        }
        function showPhoto(idx) {
            if (idx < 0 || idx >= photoUrls.length) return;
            currentIndex = idx;
            $('#ed-photo-viewer-img').attr('src', photoUrls[currentIndex]);
            zoom = 1; panX = 0; panY = 0; updateZoom();
            $('#ed-photo-viewer-counter').text((currentIndex + 1) + ' / ' + photoUrls.length);
            $('.ed-photo-thumb').removeClass('active').eq(currentIndex).addClass('active');
        }

        $('.ed-photo-main, .ed-photo-thumb').on('click', function(){
            var idx = $(this).data('index');
            if (idx === undefined) idx = 0;
            openViewer(idx);
        });
        $('#ed-photo-viewer-close').on('click', function(){ $('#ed-photo-viewer').removeClass('open'); });
        $('.ed-description-voir-suite').on('click', function(){
            var full = $('#ed-description-full-raw').text() || '';
            var esc = function(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };
            $('#ed-description-viewer-content').html(esc(full).replace(/\n/g, '<br>'));
            $('#ed-description-viewer').addClass('open');
        });
        $('#ed-description-viewer-close').on('click', function(){ $('#ed-description-viewer').removeClass('open'); });
        $('#ed-description-viewer').on('click', function(e){ if (e.target === this) $(this).removeClass('open'); });
        $('#ed-photo-viewer').on('click', function(e){ if (e.target === this) $(this).removeClass('open'); });
        $('#ed-photo-viewer-prev').on('click', function(e){ e.stopPropagation(); showPhoto(currentIndex - 1); });
        $('#ed-photo-viewer-next').on('click', function(e){ e.stopPropagation(); showPhoto(currentIndex + 1); });
        $('#ed-photo-zoom-in').on('click', function(e){ e.stopPropagation(); zoom = Math.min(4, zoom + 0.25); updateZoom(); });
        $('#ed-photo-zoom-out').on('click', function(e){ e.stopPropagation(); zoom = Math.max(0.5, zoom - 0.25); updateZoom(); });
        $('#ed-photo-zoom-reset').on('click', function(e){ e.stopPropagation(); zoom = 1; panX = 0; panY = 0; updateZoom(); });

        $(document).on('keydown', function(e){
            if (e.key === 'Escape') {
                if ($('#ed-description-viewer').hasClass('open')) { $('#ed-description-viewer').removeClass('open'); return; }
                if ($('#ed-photo-viewer').hasClass('open')) { $('#ed-photo-viewer').removeClass('open'); return; }
            }
            if (!$('#ed-photo-viewer').hasClass('open')) return;
            if (e.key === 'ArrowLeft') { showPhoto(currentIndex - 1); e.preventDefault(); }
            if (e.key === 'ArrowRight') { showPhoto(currentIndex + 1); e.preventDefault(); }
        });

        $('#ed-photo-viewer-inner')[0].addEventListener('wheel', function(e){
            if (!$('#ed-photo-viewer').hasClass('open')) return;
            e.preventDefault();
            zoom += e.deltaY > 0 ? -0.15 : 0.15;
            zoom = Math.max(0.5, Math.min(4, zoom));
            updateZoom();
        }, { passive: false });

        $('#ed-photo-viewer-img').on('mousedown', function(e){
            if (e.which !== 1) return;
            isDragging = true; startX = e.pageX - panX; startY = e.pageY - panY;
            $(this).addClass('dragging');
        });
        $(document).on('mousemove', function(e){
            if (!isDragging) return;
            panX = e.pageX - startX; panY = e.pageY - startY;
            updateZoom();
        });
        $(document).on('mouseup', function(){ isDragging = false; $('#ed-photo-viewer-img').removeClass('dragging'); });
    })();

    $('.ed-actions-tabs-row .ed-actions-tab').on('click', function(){
        var action = $(this).data('action');
        if (!action) return;
        $('.ed-actions-tabs-row .ed-actions-tab').removeClass('open');
        $(this).addClass('open');
        var $cartouche = $('#ed-actions-cartouche');
        var $col = $('#ed-col-actions');
        $cartouche.removeClass('ed-cartouche-blue ed-cartouche-violet').addClass('open');
        if (action === 'reponse') $cartouche.addClass('ed-cartouche-blue');
        else $cartouche.addClass('ed-cartouche-violet');
        $col.addClass('has-open');
        $('.ed-action-cartouche-reponse, .ed-action-cartouche-deleguer').removeClass('ed-active');
        $('#action-cartouche-' + action).addClass('ed-active');
    });
    $(document).on('click', '.ed-formules-settings-btn', function(e){
        e.stopPropagation();
        $('#ed-formules-modal').addClass('ed-modal-open');
        $('#ed-formules-form').removeClass('ed-formules-form-visible');
        loadFormules();
    });
    function updateQuestionsOkState() {
        var $zone = $('#action-cartouche-reponse .ed-fq-zone');
        var n = $zone.find('.ed-question-ia-item.selected').length;
        var $ok = $zone.find('.ed-questions-ok');
        if (!$ok.length) return;
        if (n > 0) {
            $ok.prop('disabled', false);
        } else {
            $ok.prop('disabled', true);
        }
    }
    $(document).on('click', '.ed-question-ia-item', function(){
        $(this).toggleClass('selected');
        updateQuestionsOkState();
    });
    $(document).on('click', '.ed-questions-ok', function(){
        if ($(this).prop('disabled')) return;
        var $zone = $(this).closest('.ed-fq-zone');
        var selected = [];
        var texts = [];
        $zone.find('.ed-question-ia-item.selected').each(function(){
            var idx = parseInt($(this).data('idx'), 10);
            selected.push(idx);
            texts.push($(this).text().trim());
        });
        var $corps = $('#reponse-corps-'+estId);
        var body = $corps.val();
        if (texts.length > 0) {
            var toAdd = (body && !/^\s*$/.test(body) ? '\n\n' : '') + texts.map(function(t){ return '- ' + t; }).join('\n');
            $corps.val(body + toAdd).trigger('input');
        }
        var subj = $('#reponse-objet-'+estId).val();
        body = $corps.val();
        $.post(ajaxurl, { action: 'lmd_save_reponse', nonce: nonce, id: estId, subject: subj, body: body, questions_selected: selected }).done(function(){});
        $zone.removeClass('ed-fq-expanded');
    });

    $('.ed-cp-settings-btn').on('click', function(){
        $('#ed-cp-settings-modal').addClass('ed-modal-open');
        $.post(ajaxurl, { action: 'lmd_get_cp_settings', nonce: nonce }).done(function(r){
            if (r.success && r.data) {
                $('#cp-settings-email').val(r.data.email || '');
                $('#cp-settings-signature').val(r.data.signature || '');
                $('#cp-settings-copy').val(r.data.copy_emails || '');
            }
        });
    });
    $('#ed-cp-settings-modal').on('click', function(e){ if (e.target.id === 'ed-cp-settings-modal') $(this).removeClass('ed-modal-open'); });
    $(document).on('click', '#ed-cp-settings-close-btn', function(e){ e.preventDefault(); e.stopPropagation(); $('#ed-cp-settings-modal').removeClass('ed-modal-open'); });
    $('.lmd-save-cp-settings').on('click', function(){
        $.post(ajaxurl, { action: 'lmd_save_cp_settings', nonce: nonce, email: $('#cp-settings-email').val(), signature: $('#cp-settings-signature').val(), copy_emails: $('#cp-settings-copy').val() })
            .done(function(r){
                if (r.success) {
                    if (typeof lmdEdMailto !== 'undefined') {
                        lmdEdMailto.signatureHtml = $('#cp-settings-signature').val() || '';
                    }
                    if (typeof updateEdReponsePreview === 'function') updateEdReponsePreview();
                    alert('Paramètres enregistrés');
                    $('#ed-cp-settings-modal').removeClass('ed-modal-open');
                } else {
                    alert(r.data && r.data.message || 'Erreur');
                }
            })
            .fail(function(){ alert('Erreur'); });
    });

    var formulesCache = [];
    function refreshFormulesUi(formules) {
        formulesCache = formules || [];
        var $sel = $('.ed-formule-select');
        $sel.find('option:not(:first)').remove();
        formulesCache.forEach(function(f){
            $sel.append($('<option>').val(f.id).data('content', f.content || '').text(f.name));
        });
        var html = '';
        formulesCache.forEach(function(f){
            html += '<div class="ed-formule-item" data-id="'+f.id+'"><strong>'+f.name+'</strong><span class="ed-formule-actions"><button type="button" class="button ed-edit-formule" data-id="'+f.id+'">Modifier</button><button type="button" class="button ed-delete-formule" data-id="'+f.id+'">Suppr.</button></span></div>';
        });
        $('#ed-formules-list').html(html || '<p class="ed-formules-empty">Aucune formule enregistrée.</p>');
    }
    function loadFormules() {
        $.post(ajaxurl, { action: 'lmd_list_formules', nonce: nonce }).done(function(r){
            if (r.success && r.data && r.data.formules) {
                refreshFormulesUi(r.data.formules);
            }
        });
    }
    function closeFormulesModal() {
        $('#ed-formules-modal').removeClass('ed-modal-open');
        $('#ed-formules-form').removeClass('ed-formules-form-visible');
        $('#formule-edit-id').val('');
        $('#formule-new-name').val('');
        $('#formule-new-content').val('');
    }
    $(document).on('click', '.ed-formules-settings-btn', function(e){
        e.stopPropagation();
        $('#ed-formules-modal').addClass('ed-modal-open');
        $('#ed-formules-form').removeClass('ed-formules-form-visible');
        $('#ed-formules-add-btn').show();
        loadFormules();
    });
    $(document).on('click', '.ed-fq-tab', function(e){
        if ($(e.target).closest('.ed-fq-gear, .ed-formules-settings-btn').length) return;
        var $tab = $(this);
        var fq = $tab.data('fq');
        var $zone = $tab.closest('.ed-fq-zone');
        $tab.siblings('.ed-fq-tab').removeClass('open');
        $tab.addClass('open');
        $zone.addClass('ed-fq-expanded');
        var $cartouche = $zone.find('.ed-fq-cartouche');
        $cartouche.find('.ed-fq-panel').hide();
        $cartouche.find('.ed-fq-panel[data-fq="'+fq+'"]').show();
    });
    $('#ed-formules-modal').on('click', function(e){
        if (e.target && e.target.id === 'ed-formules-modal') closeFormulesModal();
    });
    $(document).on('click', '#ed-formules-close-btn', function(e){ e.preventDefault(); e.stopPropagation(); closeFormulesModal(); });
    $(document).on('click', '.ed-formules-modal-inner', function(e){ e.stopPropagation(); });
    $('.lmd-add-formule-btn').on('click', function(){
        $('#formule-edit-id').val('');
        $('#formule-new-name').val('');
        $('#formule-new-content').val('');
        $('#ed-formules-form').addClass('ed-formules-form-visible');
        $('#formule-new-name').focus();
    });
    $('.lmd-cancel-formule-btn').on('click', function(){
        $('#ed-formules-form').removeClass('ed-formules-form-visible');
        $('#formule-edit-id').val('');
        $('#formule-new-name').val('');
        $('#formule-new-content').val('');
    });
    $('.lmd-save-formule-btn').on('click', function(){
        var id = $('#formule-edit-id').val(), name = $('#formule-new-name').val(), content = $('#formule-new-content').val();
        if (!name) { alert('Nom requis'); return; }
        var data = { action: 'lmd_save_formule', nonce: nonce, name: name, content: content };
        if (id) data.id = id;
        $.post(ajaxurl, data).done(function(r){
            if (r.success) {
                loadFormules();
                $('#ed-formules-form').removeClass('ed-formules-form-visible');
                $('#formule-edit-id').val('');
                $('#formule-new-name').val('');
                $('#formule-new-content').val('');
            }
        });
    });
    $(document).on('click', '.ed-edit-formule', function(){
        var id = $(this).data('id');
        var f = formulesCache.filter(function(x){ return x.id == id; })[0];
        if (f) {
            $('#formule-edit-id').val(f.id);
            $('#formule-new-name').val(f.name);
            $('#formule-new-content').val(f.content || '');
            $('#ed-formules-form').addClass('ed-formules-form-visible');
        }
    });
    $(document).on('click', '.ed-delete-formule', function(){
        var id = $(this).data('id');
        if (confirm('Supprimer cette formule ?')) {
            $.post(ajaxurl, { action: 'lmd_delete_formule', nonce: nonce, id: id }).done(function(){
                loadFormules();
            });
        }
    });

    $(document).on('change', '.ed-formule-select', function(){
        var opt = $(this).find('option:selected'), content = opt.data('content');
        if (content) {
            var $corps = $(this).closest('.ed-action-panel').find('.ed-email-corps');
            $corps.val($corps.val() + content).trigger('input');
        }
        $(this).closest('.ed-fq-zone').removeClass('ed-fq-expanded');
    });

    var estId = $('#ed-wrap-<?php echo (int) $id; ?>').data('id');
    $('#delegation-email-'+estId).on('blur', function(){
        var em = $(this).val();
        if (em && em.indexOf('@') > 0) {
            $.post(ajaxurl, { action: 'lmd_add_delegation_recipient', nonce: nonce, email: em });
        }
    });
    $.post(ajaxurl, { action: 'lmd_list_delegation_recipients', nonce: nonce }).done(function(r){
        if (r.success && r.data && r.data.emails) {
            var $datalist = $('#delegation-recipients-list-'+estId);
            $datalist.empty();
            r.data.emails.forEach(function(e){ $datalist.append($('<option>').val(e)); });
        }
    });
    loadFormules();

    /** Signature HTML → texte pour mailto : conserve les URL des liens et des images. */
    function lmdHtmlSignatureToPlainText(html) {
        if (!html || typeof html !== 'string') return '';
        var d = document.createElement('div');
        d.innerHTML = html;
        /* Images d'abord : sinon un <a><img></a> ne garderait que l'URL du lien. */
        var imgs = d.querySelectorAll('img[src]');
        for (var j = 0; j < imgs.length; j++) {
            var img = imgs[j];
            var src = (img.getAttribute('src') || '').trim();
            var alt = (img.getAttribute('alt') || img.getAttribute('title') || '').trim();
            var txt = src ? (alt ? alt + ' — ' + src : '[Image] ' + src) : alt;
            img.replaceWith(document.createTextNode(txt));
        }
        var links = d.querySelectorAll('a[href]');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            var href = (a.getAttribute('href') || '').trim();
            var label = (a.textContent || a.innerText || '').replace(/\s+/g, ' ').trim();
            var txt = href ? (label ? label + ' — ' + href : href) : label;
            a.replaceWith(document.createTextNode(txt));
        }
        return (d.textContent || d.innerText || '').replace(/\u00a0/g, ' ').replace(/\n{3,}/g, '\n\n').trim();
    }
    function lmdBuildMailtoBodyFromReponse() {
        var bodyPlain = $('#reponse-corps-'+estId).val() || '';
        var sigHtml = (typeof lmdEdMailto !== 'undefined' && lmdEdMailto.signatureHtml) ? lmdEdMailto.signatureHtml : '';
        var sigPlain = lmdHtmlSignatureToPlainText(sigHtml);
        if (!sigPlain) return bodyPlain;
        if (!bodyPlain) return sigPlain;
        return bodyPlain.replace(/\s+$/, '') + '\n\n—\n' + sigPlain;
    }
    function updateEdReponsePreview() {
        var $plain = $('#ed-reponse-preview-plain-'+estId);
        if (!$plain.length) return;
        $plain.text(lmdBuildMailtoBodyFromReponse());
        var sigHtml = (typeof lmdEdMailto !== 'undefined' && lmdEdMailto.signatureHtml) ? lmdEdMailto.signatureHtml : '';
        var $sig = $('#ed-reponse-preview-sig-'+estId);
        var $lbl = $('#ed-reponse-preview-sig-label-'+estId);
        if (sigHtml && $sig.length) {
            $sig.html(sigHtml).show();
            $lbl.show();
        } else if ($sig.length) {
            $sig.empty().hide();
            $lbl.hide();
        }
    }
    $('#reponse-corps-'+estId).on('input', updateEdReponsePreview);
    updateEdReponsePreview();

    $('.lmd-send-reponse').on('click', function(){
        var id = $(this).data('id'), email = $(this).data('email'), sujet = $('#reponse-objet-'+id).val(), corps = $('#reponse-corps-'+id).val();
        var corpsPourMailto = (typeof lmdBuildMailtoBodyFromReponse === 'function') ? lmdBuildMailtoBodyFromReponse() : corps;
        var questionsSelected = [];
        $('#action-panel-reponse .ed-question-ia-item.selected').each(function(){ questionsSelected.push(parseInt($(this).data('idx'), 10)); });
        $.post(ajaxurl, { action: 'lmd_save_reponse', nonce: nonce, id: id, subject: sujet, body: corps, mark_sent: 1, questions_selected: questionsSelected }).done(function(r){
            if (r.success && r.data && r.data.sent_at) {
                var d = new Date(r.data.sent_at);
                var dateStr = d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
                var timeStr = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                var $msg = $('<div class="ed-courrier-parti" style="margin-top: 8px; padding: 12px; background: #f0fdf4; border: 1px solid #22c55e; border-radius: 8px; font-weight: 700; text-transform: uppercase; font-size: 12px; color: #166534;">Ce courrier est parti le '+dateStr+' à '+timeStr+'</div>');
                $('#action-panel-reponse .ed-fq-zone').replaceWith($msg);
                if (typeof lmd_set_tag_by_slug === 'function') lmd_set_tag_by_slug(id, 'message', 'repondu');
                var $btn = $('.ed-tag-btn[data-type="message"]');
                if ($btn.length) $btn.find('.ed-tag-label').text('Répondu');
            }
        });
        if (email) {
            var q = ['subject=' + encodeURIComponent(sujet), 'body=' + encodeURIComponent(corpsPourMailto)];
            if (typeof lmdEdMailto !== 'undefined' && lmdEdMailto.copyEmails && lmdEdMailto.copyEmails.length) {
                var ex = lmdEdMailto.bccExcludeSlugs || [];
                var skipBcc = false;
                if (lmdEdMailto.interetSlug && ex.indexOf(lmdEdMailto.interetSlug) !== -1) skipBcc = true;
                if (lmdEdMailto.estimationSlug && ex.indexOf(lmdEdMailto.estimationSlug) !== -1) skipBcc = true;
                if (!skipBcc) q.push('bcc=' + encodeURIComponent(lmdEdMailto.copyEmails.join(',')));
            }
            window.location.href = 'mailto:' + encodeURIComponent(email) + '?' + q.join('&');
        } else alert('Email client non disponible');
    });

    $('.lmd-send-delegation').on('click', function(){
        var id = $(this).data('id'), email = $('#delegation-email-'+id).val(), subject = $('#delegation-objet-'+id).val(), body = $('#delegation-corps-'+id).val();
        if (!email) { alert('Indiquez le destinataire.'); return; }
        $.post(ajaxurl, { action: 'lmd_send_delegation_email', nonce: nonce, id: id, email: email, subject: subject, body: body })
            .done(function(r){ if (r.success) alert(r.data && r.data.message ? r.data.message : 'Email envoyé.'); else alert(r.data && r.data.message ? r.data.message : 'Échec de l\'envoi.'); })
            .fail(function(){ alert('Échec de l\'envoi.'); });
    });

    $('.lmd-generate-delegation-link').on('click', function(){
        var id = $(this).data('id'), email = $('#delegation-email-'+id).val();
        $.post(ajaxurl, { action: 'lmd_generate_delegation_token', nonce: nonce, id: id, email: email || '' }).done(function(r){
            if (r.success && r.data && r.data.url) {
                var url = r.data.url;
                var $corps = $('#delegation-corps-'+id);
                var txt = $corps.val();
                var ajout = "\n\nÀ cliquer pour rejoindre la demande d'estimation :\n" + url;
                $corps.val(txt + ajout);
            }
        });
    });
})(jQuery);
</script>
