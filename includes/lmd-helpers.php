<?php
/**
 * Helpers pour le plugin LMD Apps IA
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Extrait l'estimation IA (basse et haute) depuis ai_analysis.
 * Utilisable dans la vignette, la vue détail, etc.
 *
 * @param string|array $ai_analysis JSON string ou tableau décodé de ai_analysis
 * @return array ['low' => float|null, 'high' => float|null]
 */
function lmd_get_ai_estimation($ai_analysis)
{
    $data = is_array($ai_analysis)
        ? $ai_analysis
        : (json_decode($ai_analysis, true) ?:
        []);
    $low = null;
    $high = null;
    if (
        isset($data["estimate_low"]) &&
        $data["estimate_low"] !== "" &&
        $data["estimate_low"] !== null
    ) {
        $v = preg_replace("/\s+/", "", (string) $data["estimate_low"]);
        $v = str_replace([",", "\xc2\xa0"], ".", $v);
        if (is_numeric($v)) {
            $low = floatval($v);
        }
    }
    if (
        isset($data["estimate_high"]) &&
        $data["estimate_high"] !== "" &&
        $data["estimate_high"] !== null
    ) {
        $v = preg_replace("/\s+/", "", (string) $data["estimate_high"]);
        $v = str_replace([",", "\xc2\xa0"], ".", $v);
        if (is_numeric($v)) {
            $high = floatval($v);
        }
    }
    if ($low === null && !empty($data["summary"])) {
        $txt = $data["summary"];
        if (
            preg_match(
                "/(\d[\d\s,\.]*)\s*€|(\d[\d\s,\.]*)\s*euros?|estimation[:\s]+(\d[\d\s,\.]*)/ui",
                $txt,
                $m,
            )
        ) {
            $raw = trim($m[1] ?? ($m[2] ?? ($m[3] ?? "")));
            $v = preg_replace("/\s+/", "", $raw);
            $v = str_replace([",", "\xc2\xa0"], ".", $v);
            if (is_numeric($v)) {
                $low = floatval($v);
            }
        }
    }
    return ["low" => $low, "high" => $high];
}

/**
 * Convertit un montant en slug de catégorie estimation (utilise les paliers personnalisés si définis)
 */
function lmd_amount_to_estimation_slug($amount)
{
    if (
        $amount === null ||
        $amount === "" ||
        !is_numeric(str_replace([" ", ",", "\xc2\xa0"], "", (string) $amount))
    ) {
        return null;
    }
    $v = floatval(
        str_replace(
            [",", "\xc2\xa0"],
            ".",
            preg_replace("/\s+/", "", (string) $amount),
        ),
    );
    $paliers = function_exists("lmd_get_estimation_paliers")
        ? lmd_get_estimation_paliers()
        : [
            "moins_25" => ["max" => 25],
            "moins_100" => ["max" => 100],
            "moins_500" => ["max" => 500],
            "moins_1000" => ["max" => 1000],
            "moins_5000" => ["max" => 5000],
            "plus_5000" => ["min" => 5000],
        ];
    $max_paliers = [];
    $min_slug = null;
    foreach ($paliers as $slug => $pal) {
        if (isset($pal["max"])) {
            $max_paliers[$slug] = $pal["max"];
        } elseif (isset($pal["min"])) {
            $min_slug = $slug;
        }
    }
    asort($max_paliers);
    foreach ($max_paliers as $slug => $max) {
        if ($v < $max) {
            return $slug;
        }
    }
    return $min_slug;
}

/**
 * Types de tags gérés séparément pour l'avis 1 et l'avis 2.
 *
 * @param string $type Type de tag.
 * @return bool
 */
function lmd_is_opinion_specific_tag_type($type)
{
    return in_array(
        (string) $type,
        ["interet", "estimation", "theme_vente"],
        true,
    );
}

/**
 * Normalise la valeur stockée dans modified_by_avis.
 * Retourne:
 * - 1 ou 2 pour un avis explicite
 * - 0 pour un tag partagé entre les deux avis
 * - null pour les anciennes lignes non migrées
 *
 * @param object $tag Ligne de tag liée à une estimation.
 * @return int|null
 */
function lmd_get_linked_tag_opinion($tag)
{
    if (
        !is_object($tag) ||
        !isset($tag->modified_by_avis) ||
        $tag->modified_by_avis === null ||
        $tag->modified_by_avis === ""
    ) {
        return null;
    }
    $value = (int) $tag->modified_by_avis;
    if ($value === 2) {
        return 2;
    }
    if ($value === 1) {
        return 1;
    }
    return 0;
}

/**
 * Retourne le tag lié à utiliser pour un type donné.
 *
 * @param array       $linked_tags Tags liés à l'estimation.
 * @param string      $type Type de tag recherché.
 * @param int|null    $opinion 1, 2 ou null pour un affichage générique.
 * @return object|null
 */
function lmd_get_linked_tag_by_type(
    $linked_tags,
    $type,
    $opinion = null,
    $fallback_to_other_opinion = false,
) {
    $matches = [];
    foreach ((array) $linked_tags as $tag) {
        if (
            !is_object($tag) ||
            !isset($tag->type) ||
            (string) $tag->type !== (string) $type
        ) {
            continue;
        }
        $matches[] = $tag;
    }
    if (empty($matches)) {
        return null;
    }
    if (!lmd_is_opinion_specific_tag_type($type)) {
        return $matches[0];
    }

    $priority = [];
    if ($opinion === null || $opinion === "") {
        $priority = [1, 0, null, 2];
    } else {
        if ((int) $opinion === 2) {
            $priority = $fallback_to_other_opinion ? [2, 0, 1, null] : [2, 0];
        } else {
            $priority = $fallback_to_other_opinion
                ? [1, 0, null, 2]
                : [1, null, 0];
        }
    }

    foreach ($priority as $wanted) {
        foreach ($matches as $tag) {
            $tag_opinion = lmd_get_linked_tag_opinion($tag);
            if ($tag_opinion === $wanted) {
                return $tag;
            }
        }
    }

    return null;
}

/**
 * Construit une map [type => tag] en tenant compte de l'avis actif.
 *
 * @param array    $linked_tags Tags liés à l'estimation.
 * @param int|null $opinion 1, 2 ou null pour un affichage générique.
 * @return array
 */
function lmd_build_tags_by_type(
    $linked_tags,
    $opinion = null,
    $fallback_to_other_opinion = false,
) {
    $types = [];
    foreach ((array) $linked_tags as $tag) {
        if (!is_object($tag) || empty($tag->type)) {
            continue;
        }
        $types[(string) $tag->type] = true;
    }

    $tags_by_type = [];
    foreach (array_keys($types) as $type) {
        $selected = lmd_get_linked_tag_by_type(
            $linked_tags,
            $type,
            $opinion,
            $fallback_to_other_opinion,
        );
        if ($selected) {
            $tags_by_type[$type] = $selected;
        }
    }

    return $tags_by_type;
}

/**
 * Détermine la source visuelle d'un tag selon son avis d'origine.
 *
 * @param object $tag Tag lié.
 * @param int    $opinion Avis actuellement affiché.
 * @return string
 */
function lmd_get_tag_source_for_display($tag, $opinion = 1)
{
    $tag_opinion = lmd_get_linked_tag_opinion($tag);
    if ($tag_opinion === 2) {
        return "avis2";
    }
    if ($tag_opinion === 1 || $tag_opinion === null) {
        return "cp";
    }
    return (int) $opinion === 2 ? "avis2" : "cp";
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
function lmd_get_estimation_source(
    $estimation,
    $ai,
    $estimation_tag,
    $opinion = 1,
) {
    $opinion = (int) $opinion === 2 ? 2 : 1;
    $slug = null;
    $source = "";
    $name = "";
    $est_slug = $estimation_tag ? $estimation_tag->slug : null;
    $est_name = $estimation_tag ? $estimation_tag->name : null;
    $tag_opinion = $estimation_tag
        ? lmd_get_linked_tag_opinion($estimation_tag)
        : null;
    $tag_is_current =
        $estimation_tag &&
        ($tag_opinion === 0 ||
            ($opinion === 1 && $tag_opinion === null) ||
            $tag_opinion === $opinion);
    $tag_is_other =
        $estimation_tag &&
        !$tag_is_current &&
        in_array($tag_opinion, [1, 2], true);
    $ai_est = function_exists("lmd_get_ai_estimation")
        ? lmd_get_ai_estimation($ai ?: [])
        : ["low" => null, "high" => null];
    $ai_slug = !empty($ai["estimation"]) ? trim($ai["estimation"]) : null;
    if (!$ai_slug && $ai_est["low"] !== null) {
        $ai_slug = lmd_amount_to_estimation_slug($ai_est["low"]);
    }
    $current_low =
        $opinion === 2
            ? $estimation->avis2_estimate_low ?? null
            : $estimation->avis1_estimate_low ?? null;
    $current_high =
        $opinion === 2
            ? $estimation->avis2_estimate_high ?? null
            : $estimation->avis1_estimate_high ?? null;
    $current_source = $opinion === 2 ? "avis2" : "cp";
    $other_low =
        $opinion === 2
            ? $estimation->avis1_estimate_low ?? null
            : $estimation->avis2_estimate_low ?? null;
    $other_high =
        $opinion === 2
            ? $estimation->avis1_estimate_high ?? null
            : $estimation->avis2_estimate_high ?? null;
    $other_source = $opinion === 2 ? "cp" : "avis2";
    $has_current =
        ($current_low !== null && $current_low !== "") ||
        ($current_high !== null && $current_high !== "");
    $has_other =
        ($other_low !== null && $other_low !== "") ||
        ($other_high !== null && $other_high !== "");

    if ($has_current) {
        $amt = $current_low ?? $current_high;
        $slug =
            $tag_is_current && $est_slug
                ? $est_slug
                : lmd_amount_to_estimation_slug($amt);
        $source = $current_source;
    } elseif ($tag_is_current && $est_slug) {
        $slug = $est_slug;
        $source = $current_source;
    } elseif ($has_other) {
        $amt = $other_low ?? $other_high;
        $slug =
            $tag_is_other && $est_slug
                ? $est_slug
                : lmd_amount_to_estimation_slug($amt);
        $source = $other_source;
    } elseif ($tag_is_other && $est_slug) {
        $slug = $est_slug;
        $source = $other_source;
    } elseif ($ai_slug || $ai_est["low"] !== null) {
        $slug = $ai_slug ?: lmd_amount_to_estimation_slug($ai_est["low"]);
        $source = "ia";
    }
    if ($slug) {
        $name =
            $est_name ?:
            (function_exists("lmd_get_estimation_name")
                ? lmd_get_estimation_name($slug)
                : $slug);
    }
    return ["source" => $source, "slug" => $slug, "name" => $name];
}

/**
 * Échappe un nom de tag pour l'affichage HTML.
 * Décode d'abord les entités (ex: &lt; de sanitize_text_field) pour éviter le double échappement.
 */
function lmd_esc_tag_name($name)
{
    if (!is_string($name) || $name === "") {
        return "";
    }
    $decoded = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, "UTF-8");
    return esc_html($decoded);
}

/**
 * Formate un montant en euros pour l'affichage
 */
function lmd_format_euro_display($value)
{
    if (
        $value === null ||
        $value === "" ||
        !is_numeric(str_replace([" ", ",", "\xc2\xa0"], "", (string) $value))
    ) {
        return "";
    }
    $clean = preg_replace("/\s+/", "", (string) $value);
    $clean = str_replace(",", ".", $clean);
    $num = floatval($clean);
    return number_format($num, 0, ",", " ");
}

/**
 * Compresse une image si > 2 Mo (cible 300 Ko - 1 Mo)
 */
function lmd_compress_image($filepath)
{
    if (!file_exists($filepath) || filesize($filepath) <= 2 * 1024 * 1024) {
        return true;
    }
    $info = getimagesize($filepath);
    if (!$info) {
        return false;
    }
    $mime = $info["mime"];
    $img = null;
    if ($mime === "image/jpeg" || $mime === "image/jpg") {
        $img = imagecreatefromjpeg($filepath);
    } elseif ($mime === "image/png") {
        $img = imagecreatefrompng($filepath);
    } elseif ($mime === "image/webp") {
        if (function_exists("imagecreatefromwebp")) {
            $img = imagecreatefromwebp($filepath);
        }
    }
    if (!$img) {
        return false;
    }
    $target_size = 600 * 1024; // 600 Ko
    $quality = 85;
    $tmp = $filepath . ".tmp." . pathinfo($filepath, PATHINFO_EXTENSION);
    $saved = false;
    while ($quality >= 50 && !$saved) {
        if ($mime === "image/jpeg" || $mime === "image/jpg") {
            $saved = imagejpeg($img, $tmp, $quality);
        } elseif ($mime === "image/png") {
            $saved = imagepng($img, $tmp, (int) ((9 * (100 - $quality)) / 100));
        } elseif ($mime === "image/webp" && function_exists("imagewebp")) {
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
function lmd_get_ai_error_reports($site_id = null, $args = [])
{
    global $wpdb;
    $current = get_current_blog_id();
    $target = $site_id ?? $current;
    if ($target !== $current && is_multisite()) {
        switch_to_blog($target);
    }
    $prefix = $wpdb->get_blog_prefix($target);
    $table = $prefix . "lmd_ai_error_reports";
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        if ($target !== $current && is_multisite()) {
            restore_current_blog();
        }
        return [];
    }
    $limit = isset($args["limit"]) ? absint($args["limit"]) : 100;
    $offset = isset($args["offset"]) ? absint($args["offset"]) : 0;
    $orderby = in_array(
        $args["orderby"] ?? "created_at",
        ["created_at", "id", "site_id"],
        true,
    )
        ? $args["orderby"]
        : "created_at";
    $order = strtoupper($args["order"] ?? "DESC") === "ASC" ? "ASC" : "DESC";
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE site_id = %d ORDER BY $orderby $order LIMIT %d OFFSET %d",
            $target,
            $limit,
            $offset,
        ),
        ARRAY_A,
    );
    if ($target !== $current && is_multisite()) {
        restore_current_blog();
    }
    return $rows ?: [];
}

/**
 * URL admin pour l’application « Aide à l’estimation » (page à onglets).
 *
 * @param string $tab list|new|dashboard|help|ventes|vendeurs (preferences|activity redirigent vers dashboard&dash_sub=…)
 * @param array  $args Paramètres additionnels : month, id, dash_sub, help_sub, etc.
 */
function lmd_app_estimation_admin_url($tab = "list", array $args = [])
{
    $args = array_merge(["page" => "lmd-app-estimation", "tab" => $tab], $args);
    return add_query_arg($args, admin_url("admin.php"));
}
