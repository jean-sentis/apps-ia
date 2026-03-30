<?php
/**
 * Administration - Menus, pages, enqueue
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Admin
{
    public function __construct()
    {
        add_action("admin_init", [$this, "maybe_redirect_legacy_app_pages"], 1);
        add_action("admin_menu", [$this, "add_menu"]);
        add_action("admin_menu", [$this, "remove_parent_only_menu_items"], 999);
        add_action("admin_enqueue_scripts", [$this, "enqueue_assets"]);
        add_action("admin_head", [$this, "menu_icon_scale"]);
        add_filter("admin_body_class", [$this, "admin_body_class_lmd"]);
        add_filter(
            "parent_file",
            [$this, "parent_file_highlight_app_estimation"],
            999,
        );
        add_filter(
            "submenu_file",
            [$this, "submenu_file_highlight_app_estimation"],
            999,
            2,
        );
        add_action("admin_post_lmd_submit_estimation_admin", [
            $this,
            "handle_new_estimation",
        ]);
        add_action("admin_post_lmd_delete_estimation", [
            $this,
            "handle_delete_estimation",
        ]);
        add_action("admin_post_lmd_bulk_delete_estimations", [
            $this,
            "handle_bulk_delete_estimations",
        ]);
        add_action("admin_post_lmd_reset_analysis_for_test", [
            $this,
            "handle_reset_analysis_for_test",
        ]);
        add_action("admin_post_lmd_export_consumption", [
            $this,
            "handle_export_consumption",
        ]);
        add_action("admin_post_lmd_export_product_margin", [
            $this,
            "handle_export_product_margin",
        ]);
        add_action("admin_post_lmd_save_margin_fx", [
            $this,
            "handle_save_margin_fx",
        ]);
        add_action("admin_post_lmd_sandbox_seed", [
            $this,
            "handle_sandbox_seed",
        ]);
        add_action("admin_post_lmd_sandbox_clear", [
            $this,
            "handle_sandbox_clear",
        ]);
        add_action("admin_post_lmd_export_activity", [
            $this,
            "handle_export_activity",
        ]);
        add_action("admin_post_lmd_export_full_copy", [
            $this,
            "handle_export_full_copy",
        ]);
        add_action("admin_post_lmd_import_full_copy", [
            $this,
            "handle_import_full_copy",
        ]);
    }

    /**
     * Anciennes URLs d’écrans « plats » → application à onglets (préserve les paramètres de requête).
     */
    public function maybe_redirect_legacy_app_pages()
    {
        if (
            !is_admin() ||
            !isset($_GET["page"]) ||
            isset($_GET["networkwide"])
        ) {
            return;
        }
        $legacy = [
            "lmd-new-estimation" => "new",
            "lmd-estimations-list" => "list",
            "lmd-ventes-list" => "ventes",
            "lmd-vendeurs-list" => "vendeurs",
            "lmd-preferences" => "preferences",
            "lmd-help" => "help",
        ];
        $p = sanitize_key(wp_unslash($_GET["page"]));
        if ($p === "lmd-activity") {
            $q = array_map("wp_unslash", $_GET);
            $q["page"] = "lmd-app-estimation";
            $q["tab"] = "dashboard";
            $q["dash_sub"] = "stats";
            wp_safe_redirect(add_query_arg($q, admin_url("admin.php")));
            exit();
        }
        if (!isset($legacy[$p])) {
            return;
        }
        $q = array_map("wp_unslash", $_GET);
        $q["page"] = "lmd-app-estimation";
        $q["tab"] = $legacy[$p];
        wp_safe_redirect(add_query_arg($q, admin_url("admin.php")));
        exit();
    }

    public function handle_delete_estimation()
    {
        $id = isset($_GET["id"]) ? absint($_GET["id"]) : 0;
        if (
            !$id ||
            !wp_verify_nonce(
                $_GET["_wpnonce"] ?? "",
                "lmd_delete_estimation_" . $id,
            )
        ) {
            wp_die("Non autorisé");
        }
        if (!current_user_can("manage_options")) {
            wp_die("Non autorisé");
        }
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . "lmd_estimation_tags",
            ["estimation_id" => $id],
            ["%d"],
        );
        $wpdb->delete($wpdb->prefix . "lmd_estimations", ["id" => $id], ["%d"]);
        wp_redirect(admin_url("admin.php?page=lmd-app-estimation&tab=list"));
        exit();
    }

    public function handle_bulk_delete_estimations()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_POST["_wpnonce"] ?? "", "lmd_bulk_delete")
        ) {
            wp_die("Non autorisé");
        }
        $ids =
            isset($_POST["ids"]) && is_array($_POST["ids"])
                ? array_map("absint", $_POST["ids"])
                : [];
        $ids = array_filter($ids);
        if (empty($ids)) {
            wp_redirect(
                admin_url("admin.php?page=lmd-app-estimation&tab=list"),
            );
            exit();
        }
        global $wpdb;
        $et = $wpdb->prefix . "lmd_estimation_tags";
        $e = $wpdb->prefix . "lmd_estimations";
        foreach ($ids as $id) {
            $wpdb->delete($et, ["estimation_id" => $id], ["%d"]);
            $wpdb->delete($e, ["id" => $id], ["%d"]);
        }
        wp_redirect(admin_url("admin.php?page=lmd-app-estimation&tab=list"));
        exit();
    }

    /**
     * Réinitialise pour les tests : ne conserve que les données vendeur.
     * Conserve : dates d'envoi (created_at), coordonnées vendeur, photos, texte (description).
     * Efface : tout le reste (analyse IA, lecture, données CP, tags).
     */
    public function handle_reset_analysis_for_test()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_GET["_wpnonce"] ?? "", "lmd_reset_analysis_test")
        ) {
            wp_die("Non autorisé");
        }
        global $wpdb;
        $table = $wpdb->prefix . "lmd_estimations";
        $et_table = $wpdb->prefix . "lmd_estimation_tags";
        $site_id = get_current_blog_id();
        $cols = $wpdb->get_col("DESCRIBE $table");

        $up = [
            "status" => "new",
            "ai_analysis" => null,
            "auctioneer_notes" => null,
            "second_opinion" => null,
            "estimate_low" => null,
            "estimate_high" => null,
            "prix_reserve" => null,
            "avis1_estimate_low" => null,
            "avis1_estimate_high" => null,
            "avis1_prix_reserve" => null,
            "avis2_estimate_low" => null,
            "avis2_estimate_high" => null,
            "avis2_prix_reserve" => null,
            "auctioneer_decision" => null,
            "delegated_to" => null,
            "delegation_draft" => null,
            "delegation_email" => null,
            "delegation_subject" => null,
            "delegation_body" => null,
            "reponse_subject" => null,
            "reponse_body" => null,
            "reponse_questions_selected" => null,
            "reponse_sent_at" => null,
        ];
        if (in_array("first_viewed_at", $cols, true)) {
            $up["first_viewed_at"] = null;
        }
        foreach (
            ["avis1_titre", "avis1_dimension", "avis2_titre", "avis2_dimension"]
            as $c
        ) {
            if (in_array($c, $cols, true)) {
                $up[$c] = null;
            }
        }
        $up = array_intersect_key($up, array_flip($cols));
        $format = array_fill(0, count($up), "%s");

        $wpdb->update($table, $up, ["site_id" => $site_id], $format, ["%d"]);

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE site_id = %d",
                $site_id,
            ),
        );
        if (!empty($ids)) {
            $placeholders = implode(",", array_fill(0, count($ids), "%d"));
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $et_table WHERE estimation_id IN ($placeholders)",
                    $ids,
                ),
            );
        }

        $count = count($ids);
        wp_redirect(
            add_query_arg(
                "lmd_reset_done",
                $count,
                admin_url("admin.php?page=lmd-app-estimation&tab=help"),
            ),
        );
        exit();
    }

    public function handle_export_consumption()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_GET["_wpnonce"] ?? "", "lmd_export_consumption")
        ) {
            wp_die("Non autorisé");
        }
        if (is_multisite() && !is_main_site()) {
            wp_die("Non autorisé");
        }
        $month = isset($_GET["month"])
            ? sanitize_text_field($_GET["month"])
            : date("Y-m");
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date("Y-m");
        }
        $usage = class_exists("LMD_Api_Usage") ? new LMD_Api_Usage() : null;
        if (!$usage) {
            wp_die("Erreur");
        }
        $all_clients = !is_multisite() || get_current_blog_id() === 1;
        $clients = $usage->get_all_clients_consumption($month, $all_clients);
        $agg = $usage->get_aggregate_consumption($month, $all_clients);
        $labels = LMD_Api_Usage::get_api_labels();

        header("Content-Type: text/csv; charset=utf-8");
        header(
            'Content-Disposition: attachment; filename="consommation-ia-' .
                $month .
                '.csv"',
        );
        $out = fopen("php://output", "w");
        fprintf($out, chr(0xef) . chr(0xbb) . chr(0xbf));
        fputcsv(
            $out,
            [
                "Client",
                "Site ID",
                "Analyses",
                "SerpAPI (unités)",
                'SerpAPI ($)',
                "Firecrawl (unités)",
                'Firecrawl ($)',
                "ImgBB (unités)",
                'ImgBB ($)',
                "Gemini (unités)",
                'Gemini ($)',
                'Total ($)',
            ],
            ";",
        );
        foreach ($clients as $c) {
            $row = [
                $c["site_name"],
                $c["site_id"],
                $c["analyses_count"],
                $c["by_api"]["serpapi"]["units"],
                $c["by_api"]["serpapi"]["cost_usd"],
                $c["by_api"]["firecrawl"]["units"],
                $c["by_api"]["firecrawl"]["cost_usd"],
                $c["by_api"]["imgbb"]["units"],
                $c["by_api"]["imgbb"]["cost_usd"],
                $c["by_api"]["gemini"]["units"],
                $c["by_api"]["gemini"]["cost_usd"],
                $c["total_usd"],
            ];
            fputcsv($out, $row, ";");
        }
        fputcsv(
            $out,
            [
                "TOTAL",
                "",
                $agg["analyses_count"],
                $agg["by_api"]["serpapi"]["units"],
                $agg["by_api"]["serpapi"]["cost_usd"],
                $agg["by_api"]["firecrawl"]["units"],
                $agg["by_api"]["firecrawl"]["cost_usd"],
                $agg["by_api"]["imgbb"]["units"],
                $agg["by_api"]["imgbb"]["cost_usd"],
                $agg["by_api"]["gemini"]["units"],
                $agg["by_api"]["gemini"]["cost_usd"],
                $agg["total_usd"],
            ],
            ";",
        );
        fclose($out);
        exit();
    }

    public function handle_export_activity()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_GET["_wpnonce"] ?? "", "lmd_export_activity")
        ) {
            wp_die("Non autorisé");
        }
        if (is_multisite() && !is_main_site()) {
            wp_die("Non autorisé");
        }
        $month = isset($_GET["month"])
            ? sanitize_text_field($_GET["month"])
            : date("Y-m");
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date("Y-m");
        }
        $analytics = new LMD_Activity_Analytics();
        $all_sites = is_multisite() && get_current_blog_id() === 1;
        $feature_usage = $analytics->get_feature_usage($month, $all_sites);
        $signature_status = $analytics->get_signature_status($all_sites);

        header("Content-Type: text/csv; charset=utf-8");
        header(
            'Content-Disposition: attachment; filename="activite-fonctionnalites-' .
                $month .
                '.csv"',
        );
        $out = fopen("php://output", "w");
        fprintf($out, chr(0xef) . chr(0xbb) . chr(0xbf));

        fputcsv($out, ["=== Fonctionnalités (mois " . $month . ") ==="], ";");
        fputcsv($out, ["Fonctionnalité", "Utilisations"], ";");
        foreach ($feature_usage["by_feature"] ?? [] as $k => $n) {
            fputcsv($out, [$feature_usage["labels"][$k] ?? $k, $n], ";");
        }
        fputcsv($out, [], ";");
        fputcsv($out, ["=== Signature CP ==="], ";");
        fputcsv(
            $out,
            [
                "Client",
                "Site ID",
                "Signature configurée",
                "Utilisateurs avec signature",
            ],
            ";",
        );
        foreach ($signature_status as $s) {
            fputcsv(
                $out,
                [
                    $s["site_name"],
                    $s["site_id"],
                    $s["has_signature"] ? "Oui" : "Non",
                    $s["users_with_signature"],
                ],
                ";",
            );
        }
        fclose($out);
        exit();
    }

    public function handle_export_full_copy()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_GET["_wpnonce"] ?? "", "lmd_export_full_copy")
        ) {
            wp_die("Non autorisé");
        }
        if (is_multisite() && !is_main_site()) {
            wp_die("Non autorisé");
        }
        $site_id = isset($_GET["site_id"]) ? absint($_GET["site_id"]) : null;
        $include_photos = !isset($_GET["no_photos"]);
        $include_api_keys = !empty($_GET["api_keys"]);
        try {
            $exporter = new LMD_Full_Export_Import();
            $zip_path = $exporter->export(
                $site_id ?: null,
                $include_photos,
                $include_api_keys,
            );
            if (!file_exists($zip_path)) {
                wp_die("Erreur lors de la création du fichier");
            }
            $filename = basename($zip_path);
            header("Content-Type: application/zip");
            header(
                'Content-Disposition: attachment; filename="' . $filename . '"',
            );
            header("Content-Length: " . filesize($zip_path));
            readfile($zip_path);
            @unlink($zip_path);
            exit();
        } catch (Exception $e) {
            wp_die("Erreur export : " . esc_html($e->getMessage()));
        }
    }

    public function handle_import_full_copy()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_POST["_wpnonce"] ?? "", "lmd_import_full_copy")
        ) {
            wp_die("Non autorisé");
        }
        if (is_multisite() && !is_main_site()) {
            wp_die("Non autorisé");
        }
        $replace = !empty($_POST["lmd_import_replace"]);
        $file = $_FILES["lmd_import_file"] ?? null;
        if (!$file || !empty($file["error"]) || empty($file["tmp_name"])) {
            wp_redirect(
                add_query_arg(
                    "lmd_import_error",
                    "fichier",
                    admin_url("admin.php?page=lmd-copy-export-import"),
                ),
            );
            exit();
        }
        try {
            $importer = new LMD_Full_Export_Import();
            $stats = $importer->import($file["tmp_name"], $replace);
            wp_redirect(
                add_query_arg(
                    "lmd_import_ok",
                    1,
                    admin_url("admin.php?page=lmd-copy-export-import"),
                ),
            );
            exit();
        } catch (Exception $e) {
            wp_redirect(
                add_query_arg(
                    "lmd_import_error",
                    urlencode($e->getMessage()),
                    admin_url("admin.php?page=lmd-copy-export-import"),
                ),
            );
            exit();
        }
    }

    public function handle_new_estimation()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_POST["_wpnonce"] ?? "", "lmd_new_estimation")
        ) {
            wp_die("Non autorisé");
        }
        require_once ABSPATH . "wp-admin/includes/file.php";
        require_once ABSPATH . "wp-admin/includes/media.php";
        require_once ABSPATH . "wp-admin/includes/image.php";

        $photo_urls = [];
        $files = $_FILES["photos"] ?? null;
        if (!empty($files["name"]) && is_array($files["name"])) {
            foreach ($files["name"] as $i => $name) {
                if (empty($name) || !empty($files["error"][$i])) {
                    continue;
                }
                $file = [
                    "name" => $files["name"][$i],
                    "type" => $files["type"][$i],
                    "tmp_name" => $files["tmp_name"][$i],
                    "error" => $files["error"][$i],
                    "size" => $files["size"][$i],
                ];
                $upload = wp_handle_upload($file, ["test_form" => false]);
                if (!empty($upload["url"])) {
                    $photo_urls[] = $upload["url"];
                }
            }
        }

        global $wpdb;
        $photos_json = !empty($photo_urls) ? wp_json_encode($photo_urls) : null;
        $civility = sanitize_text_field(
            wp_unslash($_POST["client_civility"] ?? ""),
        );
        $code_postal = sanitize_text_field(
            wp_unslash($_POST["client_postal_code"] ?? ""),
        );
        $dimensions = isset($_POST["dimensions"])
            ? sanitize_text_field(wp_unslash($_POST["dimensions"]))
            : "";
        $cols = $wpdb->get_col("DESCRIBE {$wpdb->prefix}lmd_estimations");
        $insert_data = [
            "site_id" => get_current_blog_id(),
            "client_name" => sanitize_text_field(
                wp_unslash($_POST["client_name"] ?? ""),
            ),
            "client_civility" => in_array(
                $civility,
                ["Monsieur", "Madame"],
                true,
            )
                ? $civility
                : null,
            "client_first_name" => sanitize_text_field(
                wp_unslash($_POST["client_first_name"] ?? ""),
            ),
            "client_email" => sanitize_email(
                wp_unslash($_POST["client_email"] ?? ""),
            ),
            "client_phone" => sanitize_text_field(
                wp_unslash($_POST["client_phone"] ?? ""),
            ),
            "client_postal_code" => preg_match('/^[0-9]{5}$/', $code_postal)
                ? $code_postal
                : null,
            "client_commune" =>
                sanitize_text_field(
                    wp_unslash($_POST["client_commune"] ?? ""),
                ) ?:
                null,
            "description" => sanitize_textarea_field(
                wp_unslash($_POST["description"] ?? ""),
            ),
            "photos" => $photos_json,
            "status" => "new",
            "source" => "admin",
        ];
        $insert_fmt = [
            "%d",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
        ];
        if (in_array("dimensions", $cols, true)) {
            $insert_data["dimensions"] = $dimensions ?: null;
            $insert_fmt[] = "%s";
        }
        $wpdb->insert(
            $wpdb->prefix . "lmd_estimations",
            $insert_data,
            $insert_fmt,
        );
        wp_redirect(admin_url("admin.php?page=lmd-app-estimation&tab=list"));
        exit();
    }

    public function add_menu()
    {
        if (is_network_admin()) {
            add_menu_page(
                "LMD Apps IA",
                "LMD Apps IA",
                "manage_network",
                "lmd-remontee",
                [$this, "render_remontee"],
                LMD_PLUGIN_URL . "assets/lmd-logo-menu.png",
                25,
            );
            add_submenu_page(
                "lmd-remontee",
                "Remontée statistique",
                "Remontée",
                "manage_network",
                "lmd-remontee",
                [$this, "render_remontee"],
            );
            return;
        }

        $is_parent = !is_multisite() || get_current_blog_id() === 1;

        add_menu_page(
            "LMD Apps IA",
            "2. LMD Apps IA",
            "manage_options",
            "lmd-apps-ia",
            [$this, "render_hub"],
            LMD_PLUGIN_URL . "assets/lmd-logo-menu.png",
            22,
        );
        add_submenu_page(
            "lmd-apps-ia",
            'Vue d\'ensemble',
            'Vue d\'ensemble',
            "manage_options",
            "lmd-apps-ia",
            [$this, "render_hub"],
        );

        add_submenu_page(
            "lmd-apps-ia",
            'Aide à l\'estimation',
            'Aide à l\'estimation',
            "manage_options",
            "lmd-app-estimation",
            [$this, "render_app_estimation"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Enrichissement SEO",
            "Enrichissement SEO",
            "manage_options",
            "lmd-app-seo",
            [$this, "render_app_seo_placeholder"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Fidéliser client",
            "Fidéliser client",
            "manage_options",
            "lmd-app-fideliser-client",
            [$this, "render_app_fideliser_client_placeholder"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Fidéliser super acheteur",
            "Fidéliser super acheteur",
            "manage_options",
            "lmd-app-fideliser-super-acheteur",
            [$this, "render_app_fideliser_super_acheteur_placeholder"],
        );

        if (post_type_exists("splitscreen")) {
            add_submenu_page(
                "lmd-apps-ia",
                "Splitscreen",
                "Splitscreen",
                "manage_options",
                "lmd-splitscreen-montages",
                [$this, "redirect_to_splitscreen_list"],
            );
        }

        if ($is_parent) {
            // menu_title = null : page accessible via URL / onglets hub, pas de doublon dans le menu latéral.
            add_submenu_page(
                "lmd-apps-ia",
                "Configuration APIs",
                null,
                "manage_options",
                "lmd-api-config",
                [$this, "render_api_config"],
            );
            add_submenu_page(
                "lmd-apps-ia",
                "Consommation IA",
                null,
                "manage_options",
                "lmd-consumption",
                [$this, "render_consumption"],
            );
            add_submenu_page(
                "lmd-apps-ia",
                "Marge par produit",
                null,
                "manage_options",
                "lmd-product-margin",
                [$this, "render_product_margin"],
            );
        }

        if ($is_parent) {
            add_submenu_page(
                "lmd-apps-ia",
                "Promotions clients",
                null,
                "manage_options",
                "lmd-promotions",
                [$this, "render_promotions"],
            );
            add_submenu_page(
                "lmd-apps-ia",
                "Copie export/import",
                null,
                "manage_options",
                "lmd-copy-export-import",
                [$this, "render_copy_export_import"],
            );
        }
        if ($is_parent && !is_multisite()) {
            add_submenu_page(
                "lmd-apps-ia",
                "Outils bac à sable",
                null,
                "manage_options",
                "lmd-sandbox-tools",
                [$this, "render_sandbox_tools"],
            );
        }

        // menu_title = null : page enregistrée et accessible (admin.php?page=…) mais pas d’entrée dans le menu latéral (WP 5.3+).
        add_submenu_page(
            "lmd-apps-ia",
            "Nouvelle demande",
            null,
            "manage_options",
            "lmd-new-estimation",
            [$this, "render_new_estimation"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Mes estimations",
            null,
            "manage_options",
            "lmd-estimations-list",
            [$this, "render_estimations_list"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Détail estimation",
            null,
            "manage_options",
            "lmd-estimation-detail",
            [$this, "render_estimation_detail"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Planning ventes",
            null,
            "manage_options",
            "lmd-ventes-list",
            [$this, "render_ventes_list"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Liste vendeurs",
            null,
            "manage_options",
            "lmd-vendeurs-list",
            [$this, "render_vendeurs_list"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Réglage affichages et réponses vendeurs",
            null,
            "manage_options",
            "lmd-preferences",
            [$this, "render_preferences"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Aide",
            null,
            "manage_options",
            "lmd-help",
            [$this, "render_help"],
        );
        add_submenu_page(
            "lmd-apps-ia",
            "Activité",
            null,
            "manage_options",
            "lmd-activity",
            [$this, "render_activity"],
        );
    }

    /**
     * Retire les entrées réservées au site parent si on est sur un site enfant.
     */
    public function remove_parent_only_menu_items()
    {
        if (!is_multisite() || get_current_blog_id() === 1) {
            return;
        }
        $parent_only = [
            "lmd-activity",
            "lmd-consumption",
            "lmd-product-margin",
            "lmd-api-config",
            "lmd-promotions",
            "lmd-copy-export-import",
        ];
        foreach ($parent_only as $slug) {
            remove_submenu_page("lmd-apps-ia", $slug);
        }
    }

    /** Icône menu : échelle 24×24 px, légèrement plus bas, blanc lumineux. */
    public function menu_icon_scale()
    {
        echo '<style id="lmd-apps-ia-menu-icon">#adminmenu #toplevel_page_lmd-apps-ia .wp-menu-image img,#adminmenu #toplevel_page_lmd-remontee .wp-menu-image img{width:24px!important;height:24px!important;padding:6px 0 2px!important;object-fit:contain;filter:brightness(1.15) contrast(1.1) drop-shadow(0 0 1px rgba(255,255,255,.6))}#adminmenu #toplevel_page_lmd-apps-ia .wp-menu-image,#adminmenu #toplevel_page_lmd-remontee .wp-menu-image{background-size:24px 24px!important;background-position:center 6px!important;filter:brightness(1.15) contrast(1.1) drop-shadow(0 0 1px rgba(255,255,255,.6))!important}</style>';
    }

    /**
     * Classe body pour harmoniser le style (boutons, badges, typo) sur toutes les pages LMD.
     */
    /**
     * Sur le détail d'une estimation, garder le sous-menu « Aide à l'estimation » actif (pas l'entrée cachée « Détail »).
     */
    public function parent_file_highlight_app_estimation($parent_file)
    {
        $page = isset($_GET["page"])
            ? sanitize_key(wp_unslash($_GET["page"]))
            : "";
        if ($page === "lmd-estimation-detail") {
            return "lmd-apps-ia";
        }
        return $parent_file;
    }

    /**
     * @param string|false $submenu_file
     * @param string       $parent_file
     * @return string|false
     */
    public function submenu_file_highlight_app_estimation(
        $submenu_file,
        $parent_file,
    ) {
        if ($parent_file !== "lmd-apps-ia") {
            return $submenu_file;
        }
        $page = isset($_GET["page"])
            ? sanitize_key(wp_unslash($_GET["page"]))
            : "";
        if ($page === "lmd-estimation-detail") {
            return "lmd-app-estimation";
        }
        return $submenu_file;
    }

    public function admin_body_class_lmd($classes)
    {
        if (!is_admin()) {
            return $classes;
        }
        $page = isset($_GET["page"])
            ? sanitize_key(wp_unslash($_GET["page"]))
            : "";
        if ($page !== "" && strpos($page, "lmd-") === 0) {
            $classes .= " lmd-suite-admin";
        }
        return $classes;
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, "lmd-") === false) {
            return;
        }
        wp_enqueue_style(
            "lmd-admin",
            LMD_PLUGIN_URL . "assets/admin-style.css",
            [],
            LMD_VERSION,
        );
        $tab_list = isset($_GET["tab"]) && $_GET["tab"] === "list";
        if (
            strpos($hook, "lmd-estimations-list") !== false ||
            ($hook === "lmd-apps-ia_page_lmd-app-estimation" && $tab_list)
        ) {
            wp_enqueue_script(
                "sortablejs",
                "https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js",
                [],
                "1.15.2",
                true,
            );
        }
        wp_enqueue_script(
            "lmd-admin",
            LMD_PLUGIN_URL . "assets/admin.js",
            ["jquery"],
            LMD_VERSION,
            true,
        );
        wp_enqueue_script(
            "lmd-public",
            LMD_PLUGIN_URL . "assets/public.js",
            ["jquery"],
            LMD_VERSION,
            true,
        );
        wp_localize_script("lmd-admin", "lmdAdmin", [
            "ajaxurl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("lmd_admin"),
        ]);
    }

    public function render_hub()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/hub.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>LMD Apps IA</h1></div>';
        }
    }

    public function redirect_to_splitscreen_list()
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("Non autorisé.", "lmd-apps-ia"));
        }
        wp_safe_redirect(admin_url("edit.php?post_type=splitscreen"));
        exit();
    }

    public function render_app_seo_placeholder()
    {
        $lmd_placeholder_title = __("Enrichissement SEO", "lmd-apps-ia");
        $lmd_placeholder_lead = __(
            "Cette application sera branchée sur la même suite (conso et pilotage par application).",
            "lmd-apps-ia",
        );
        include LMD_PLUGIN_DIR . "admin/views/app-suite-placeholder.php";
    }

    public function render_app_fideliser_client_placeholder()
    {
        $lmd_placeholder_title = __("Fidéliser client", "lmd-apps-ia");
        $lmd_placeholder_lead = __(
            "Espace dédié en préparation dans la roadmap suite.",
            "lmd-apps-ia",
        );
        include LMD_PLUGIN_DIR . "admin/views/app-suite-placeholder.php";
    }

    public function render_app_fideliser_super_acheteur_placeholder()
    {
        $lmd_placeholder_title = __("Fidéliser super acheteur", "lmd-apps-ia");
        $lmd_placeholder_lead = __(
            "Espace dédié en préparation dans la roadmap suite.",
            "lmd-apps-ia",
        );
        include LMD_PLUGIN_DIR . "admin/views/app-suite-placeholder.php";
    }

    public function render_app_estimation()
    {
        $tab = isset($_GET["tab"])
            ? sanitize_key(wp_unslash($_GET["tab"]))
            : "dashboard";
        /* Ancien sous-onglet « Aide » du tableau de bord → onglet principal dédié. */
        if (
            $tab === "dashboard" &&
            isset($_GET["dash_sub"]) &&
            sanitize_key(wp_unslash($_GET["dash_sub"])) === "help"
        ) {
            $qs = [
                "page" => "lmd-app-estimation",
                "tab" => "help",
            ];
            if (!empty($_GET["help_sub"])) {
                $qs["help_sub"] = sanitize_key(wp_unslash($_GET["help_sub"]));
            }
            if (!empty($_GET["month"])) {
                $qs["month"] = sanitize_text_field(wp_unslash($_GET["month"]));
            }
            wp_safe_redirect(add_query_arg($qs, admin_url("admin.php")));
            exit();
        }
        $legacy_to_dash = [
            "preferences" => "prefs",
            "activity" => "stats",
        ];
        if (isset($legacy_to_dash[$tab])) {
            $qs = [
                "page" => "lmd-app-estimation",
                "tab" => "dashboard",
                "dash_sub" => $legacy_to_dash[$tab],
            ];
            if (!empty($_GET["month"])) {
                $qs["month"] = sanitize_text_field(wp_unslash($_GET["month"]));
            }
            if (!empty($_GET["help_sub"])) {
                $qs["help_sub"] = sanitize_key(wp_unslash($_GET["help_sub"]));
            }
            wp_safe_redirect(add_query_arg($qs, admin_url("admin.php")));
            exit();
        }
        $allowed = ["dashboard", "new", "list", "help", "ventes", "vendeurs"];
        if (!in_array($tab, $allowed, true)) {
            $tab = "dashboard";
        }
        include LMD_PLUGIN_DIR . "admin/views/app-estimation-shell.php";
    }

    public function render_dashboard()
    {
        if (class_exists("LMD_Database")) {
            $db = new LMD_Database();
            $db->ensure_pricing_ready();
        }
        $view = LMD_PLUGIN_DIR . "admin/views/dashboard.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>LMD Apps IA</h1><p>Tableau de bord</p></div>';
        }
    }

    public function render_new_estimation()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/new-estimation.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Nouvelle estimation</h1></div>';
        }
    }

    public function render_estimations_list()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/estimations-list-modern.php";
        if (!file_exists($view)) {
            $view = LMD_PLUGIN_DIR . "admin/views/estimations-list.php";
        }
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Mes estimations</h1></div>';
        }
    }

    public function render_estimation_detail()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/estimation-detail.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Détail estimation</h1></div>';
        }
    }

    public function render_ventes_list()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/ventes-list.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Liste des ventes</h1></div>';
        }
    }

    public function render_vendeurs_list()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/vendeurs-list.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Liste des vendeurs</h1></div>';
        }
    }

    public function render_activity()
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("Non autorisé.", "lmd-apps-ia"));
        }
        $url = function_exists("lmd_app_estimation_admin_url")
            ? lmd_app_estimation_admin_url("dashboard", [
                    "dash_sub" => "stats",
                ]) . "#lmd-stats-usage"
            : admin_url(
                "admin.php?page=lmd-app-estimation&tab=dashboard&dash_sub=stats#lmd-stats-usage",
            );
        wp_safe_redirect($url);
        exit();
    }

    public function render_billing()
    {
        if (class_exists("LMD_Pricing")) {
            $p = new LMD_Pricing();
            if (method_exists($p, "ensure_tables_exist")) {
                $p->ensure_tables_exist();
            }
        }
        if (class_exists("LMD_Database")) {
            $db = new LMD_Database();
            $db->ensure_pricing_ready();
        }
        $view = LMD_PLUGIN_DIR . "admin/views/billing.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Facturation</h1></div>';
        }
    }

    public function render_consumption()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/consumption.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Consommation IA</h1></div>';
        }
    }

    public function render_product_margin()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/product-margin.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Marge par produit</h1></div>';
        }
    }

    public function handle_save_margin_fx()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce(
                $_POST["lmd_margin_fx_nonce"] ?? "",
                "lmd_margin_fx",
            )
        ) {
            wp_die("Non autorisé");
        }
        if (is_multisite() && !is_main_site()) {
            wp_die("Non autorisé");
        }
        $fx = isset($_POST["lmd_margin_usd_to_eur"])
            ? (float) str_replace(
                ",",
                ".",
                (string) wp_unslash($_POST["lmd_margin_usd_to_eur"]),
            )
            : 0.92;
        if ($fx <= 0) {
            $fx = 0.92;
        }
        update_option("lmd_margin_usd_to_eur", $fx);
        $month = isset($_POST["redirect_month"])
            ? sanitize_text_field(wp_unslash($_POST["redirect_month"]))
            : gmdate("Y-m");
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = gmdate("Y-m");
        }
        if (!empty($_POST["redirect_lmd_hub"])) {
            wp_safe_redirect(
                add_query_arg(
                    [
                        "page" => "lmd-apps-ia",
                        "hub_tab" => "margin",
                        "month" => $month,
                        "updated" => "1",
                    ],
                    admin_url("admin.php"),
                ),
            );
        } else {
            wp_safe_redirect(
                add_query_arg(
                    [
                        "page" => "lmd-product-margin",
                        "month" => $month,
                        "updated" => "1",
                    ],
                    admin_url("admin.php"),
                ),
            );
        }
        exit();
    }

    public function handle_export_product_margin()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce(
                $_GET["_wpnonce"] ?? "",
                "lmd_export_product_margin",
            )
        ) {
            wp_die("Non autorisé");
        }
        if (is_multisite() && !is_main_site()) {
            wp_die("Non autorisé");
        }
        $month = isset($_GET["month"])
            ? sanitize_text_field($_GET["month"])
            : gmdate("Y-m");
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = gmdate("Y-m");
        }
        if (!class_exists("LMD_Api_Usage")) {
            wp_die("Erreur");
        }
        $usage = new LMD_Api_Usage();
        $all_clients = !is_multisite() || get_current_blog_id() === 1;
        $report = $usage->get_parent_product_margin_report(
            $month,
            $all_clients,
        );
        $p = $report["products"][0] ?? null;

        header("Content-Type: text/csv; charset=utf-8");
        header(
            'Content-Disposition: attachment; filename="marge-produits-' .
                $month .
                '.csv"',
        );
        $out = fopen("php://output", "w");
        fprintf($out, chr(0xef) . chr(0xbb) . chr(0xbf));
        fputcsv(
            $out,
            [
                "Mois",
                "Produit",
                "Analyses",
                "CA HT (EUR)",
                "Cout API (USD)",
                "Cout API (EUR)",
                "Marge (EUR)",
                "Marge %",
                "CA moy / analyse (EUR)",
                "Cout API moy / analyse (EUR)",
                "Prix moy payant (EUR)",
                "Analyses payantes",
            ],
            ";",
        );
        if ($p) {
            fputcsv(
                $out,
                [
                    $month,
                    $p["label"],
                    $p["quantity"],
                    $p["revenue_eur"],
                    $p["cost_usd"],
                    $p["cost_eur"],
                    $p["margin_eur"],
                    $p["margin_pct"] !== null ? $p["margin_pct"] : "",
                    $p["avg_revenue_per_analysis_eur"],
                    $p["avg_cost_per_analysis_eur"],
                    $p["avg_price_paid_estimation_eur"],
                    $p["paid_analyses_total"],
                ],
                ";",
            );
        }
        fputcsv($out, [], ";");
        fputcsv(
            $out,
            [
                "Detail par site",
                "Site ID",
                "Nom",
                "Analyses mois",
                "Payantes mois",
                "CA HT (EUR)",
            ],
            ";",
        );
        foreach ($report["sites"] as $row) {
            fputcsv(
                $out,
                [
                    "",
                    $row["site_id"],
                    $row["site_name"],
                    $row["analyses_month"],
                    $row["paid_month"],
                    $row["revenue_eur"],
                ],
                ";",
            );
        }
        fclose($out);
        exit();
    }

    public function render_ressources_ia()
    {
        if (class_exists("LMD_Api_Usage")) {
            $usage = new LMD_Api_Usage();
            $usage->ensure_table_exists();
        }
        $view = LMD_PLUGIN_DIR . "admin/views/ressources-ia.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Ressources IA</h1></div>';
        }
    }

    public function render_api_config()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/api-config.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Configuration APIs</h1></div>';
        }
    }

    public function render_preferences()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/preferences.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Préférences</h1></div>';
        }
    }

    public function render_help()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/help.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Aide</h1></div>';
        }
    }

    public function render_remontee()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/remontee.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Remontée statistique</h1></div>';
        }
    }

    public function render_promotions()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/promotions.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Promotions clients</h1></div>';
        }
    }

    public function render_copy_export_import()
    {
        $view = LMD_PLUGIN_DIR . "admin/views/copy-export-import.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Copie client</h1></div>';
        }
    }

    public function render_sandbox_tools()
    {
        if (
            !class_exists("LMD_Sandbox_Tools") ||
            !LMD_Sandbox_Tools::is_allowed()
        ) {
            wp_die(
                "Cette page est réservée à un WordPress en site unique (bac à sable).",
            );
        }
        $view = LMD_PLUGIN_DIR . "admin/views/sandbox-tools.php";
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<div class="wrap"><h1>Bac à sable</h1></div>';
        }
    }

    public function handle_sandbox_seed()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_POST["_wpnonce"] ?? "", "lmd_sandbox_seed")
        ) {
            wp_die("Non autorisé");
        }
        if (
            !class_exists("LMD_Sandbox_Tools") ||
            !LMD_Sandbox_Tools::is_allowed()
        ) {
            wp_die("Non autorisé");
        }
        $n = isset($_POST["lmd_sandbox_count"])
            ? (int) $_POST["lmd_sandbox_count"]
            : 3;
        $r = LMD_Sandbox_Tools::seed_fake_analyses($n);
        $created = is_wp_error($r) ? 0 : (int) ($r["created"] ?? 0);
        $err = is_wp_error($r) ? $r->get_error_message() : "";
        $url = add_query_arg(
            [
                "page" => "lmd-sandbox-tools",
                "seeded" => $created,
                "err" => $err ? rawurlencode($err) : "",
            ],
            admin_url("admin.php"),
        );
        wp_safe_redirect($url);
        exit();
    }

    public function handle_sandbox_clear()
    {
        if (
            !current_user_can("manage_options") ||
            !wp_verify_nonce($_POST["_wpnonce"] ?? "", "lmd_sandbox_clear")
        ) {
            wp_die("Non autorisé");
        }
        if (
            !class_exists("LMD_Sandbox_Tools") ||
            !LMD_Sandbox_Tools::is_allowed()
        ) {
            wp_die("Non autorisé");
        }
        $r = LMD_Sandbox_Tools::clear_sandbox_data();
        $deleted = is_wp_error($r) ? 0 : (int) ($r["deleted"] ?? 0);
        $err = is_wp_error($r) ? $r->get_error_message() : "";
        wp_safe_redirect(
            add_query_arg(
                [
                    "page" => "lmd-sandbox-tools",
                    "cleared" => $deleted,
                    "err" => $err ? rawurlencode($err) : "",
                ],
                admin_url("admin.php"),
            ),
        );
        exit();
    }
}
