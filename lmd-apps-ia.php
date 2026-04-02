<?php
/**
 * Plugin Name: LMD Apps IA
 * Plugin URI: https://lemarteaudigital.fr
 * Description: Suite LMD Apps IA (Le Marteau Digital) — premier module : aide à l’estimation, conso et facturation IA. Autres apps (Splitscreen, SEO, fidélisation…) : même suite, extensions séparées.
 * Version: 1.0.51
 * Author: Le Marteau Digital
 * License: GPL-2.0+
 * Text Domain: lmd-apps-ia
 */

if (!defined("WPINC")) {
    die();
}

define("LMD_VERSION", "1.0.51");
define("LMD_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("LMD_PLUGIN_URL", plugin_dir_url(__FILE__));
define("LMD_PLUGIN_BASENAME", plugin_basename(__FILE__));

function lmd_safe_require($file)
{
    $path = LMD_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    error_log("LMD: Fichier manquant - " . $file);
    return false;
}

$files_to_load = [
    "includes/class-lmd-database.php",
    "includes/class-lmd-migration.php",
    "includes/class-lmd-migration-lovable.php",
    "includes/class-lmd-migration-actions.php",
    "includes/class-lmd-migration-remontee.php",
    "includes/class-lmd-api-manager.php",
    "includes/class-lmd-api-usage.php",
    "includes/class-lmd-sandbox-tools.php",
    "includes/class-lmd-pricing.php",
    "includes/class-lmd-quota-manager.php",
    "includes/class-lmd-usage-badge.php",
    "includes/class-lmd-estimation-processor.php",
    "includes/class-lmd-tag-generator.php",
    "includes/class-lmd-archiver.php",
    "includes/class-lmd-pdf-generator.php",
    "includes/class-lmd-email-sender.php",
    "includes/class-lmd-folder-manager.php",
    "includes/class-lmd-frontend-portal-neutral.php",
    "includes/class-lmd-workflow.php",
    "includes/lmd-helpers.php",
    "includes/class-lmd-activity-analytics.php",
    "includes/class-lmd-dashboard-stats.php",
    "includes/class-lmd-full-export-import.php",
    "includes/lmd-category-settings.php",
    "includes/lmd-tag-categories.php",
    "includes/lmd-preferences.php",
    "admin/class-lmd-admin.php",
    "admin/class-lmd-ajax.php",
    "admin/ajax-handlers.php",
    "public/class-lmd-public.php",
    "public/class-lmd-public-form.php",
    "public/class-lmd-delegation-view.php",
];

foreach ($files_to_load as $file) {
    if (!lmd_safe_require($file)) {
        add_action("admin_notices", function () use ($file) {
            echo '<div class="error"><p>LMD Apps IA : fichier manquant — ' .
                esc_html($file) .
                "</p></div>";
        });
        return;
    }
}

function lmd_activate_plugin()
{
    try {
        if (!class_exists("LMD_Database")) {
            wp_die("Erreur: Classe LMD_Database introuvable");
        }
        $db = new LMD_Database();
        if (!method_exists($db, "create_tables")) {
            wp_die("Erreur: Méthode create_tables introuvable");
        }
        $db->create_tables();
        if (get_option("lmd_free_estimations_granted", "") === "") {
            update_option("lmd_free_estimations_granted", 20);
        }
        if (is_main_site() && method_exists($db, "create_parent_access_code")) {
            $db->create_parent_access_code();
        }
        if (!is_main_site() && method_exists($db, "create_child_access_code")) {
            $db->create_child_access_code();
        }
        flush_rewrite_rules();
        if (is_multisite() && class_exists("LMD_Migration_Remontee")) {
            LMD_Migration_Remontee::run();
        }
    } catch (Exception $e) {
        wp_die("Erreur activation: " . $e->getMessage());
    } catch (Error $e) {
        wp_die("Erreur fatale: " . $e->getMessage());
    }
}
register_activation_hook(__FILE__, "lmd_activate_plugin");

function lmd_deactivate_plugin()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, "lmd_deactivate_plugin");

function lmd_init_plugin()
{
    try {
        load_plugin_textdomain(
            "lmd-apps-ia",
            false,
            dirname(LMD_PLUGIN_BASENAME) . "/languages",
        );
        LMD_Migration::run();
        LMD_Migration_Lovable::run();
        if (class_exists("LMD_Migration_Actions")) {
            LMD_Migration_Actions::run();
        }
        if (class_exists("LMD_Migration_Remontee")) {
            LMD_Migration_Remontee::run();
        }
        if (class_exists("LMD_Admin")) {
            new LMD_Admin();
        }
        if (class_exists("LMD_Ajax")) {
            new LMD_Ajax();
        }
        if (class_exists("LMD_Public")) {
            new LMD_Public();
        }
        if (class_exists("LMD_Delegation_View")) {
            LMD_Delegation_View::register();
        }
        add_action(
            "lmd_monthly_consumption_report",
            "lmd_send_monthly_consumption_report",
        );
    } catch (Exception $e) {
        error_log("LMD init error: " . $e->getMessage());
        add_action("admin_notices", function () use ($e) {
            if (current_user_can("manage_options")) {
                echo '<div class="notice notice-error"><p><strong>LMD Apps IA :</strong> Erreur d\'initialisation — ' .
                    esc_html($e->getMessage()) .
                    "</p></div>";
            }
        });
    } catch (Error $e) {
        error_log("LMD init fatal: " . $e->getMessage());
        add_action("admin_notices", function () use ($e) {
            if (current_user_can("manage_options")) {
                echo '<div class="notice notice-error"><p><strong>LMD Apps IA :</strong> Erreur fatale — ' .
                    esc_html($e->getMessage()) .
                    "</p></div>";
            }
        });
    }
}
add_action("plugins_loaded", "lmd_init_plugin", 5);

/**
 * Migration ImgBB : une fois, activer le toggle si une clé était déjà enregistrée (comportement inchangé).
 */
add_action(
    "plugins_loaded",
    function () {
        if (get_option("lmd_imgbb_prefs_v1")) {
            return;
        }
        update_option(
            "lmd_imgbb_enabled",
            (string) get_option("lmd_imgbb_key", "") !== "",
        );
        update_option("lmd_imgbb_prefs_v1", 1);
    },
    4,
);

add_filter("plugin_action_links_" . LMD_PLUGIN_BASENAME, function ($links) {
    $links[] =
        '<a href="' .
        esc_url(admin_url("admin.php?page=lmd-apps-ia")) .
        '">Vue d\'ensemble</a>';
    return $links;
});

function lmd_send_monthly_consumption_report()
{
    $switched_to_parent = false;
    if (is_multisite() && !is_main_site()) {
        switch_to_blog(1);
        $switched_to_parent = true;
    }
    try {
        if (
            !get_option("lmd_consumption_monthly_enabled") ||
            !class_exists("LMD_Api_Usage")
        ) {
            return;
        }
        $email = get_option("lmd_consumption_monthly_email", "");
        $emails = array_filter(
            array_map(
                "sanitize_email",
                array_map("trim", explode(",", $email)),
            ),
        );
        if (empty($emails)) {
            return;
        }
        $month = date("Y-m", strtotime("first day of last month"));
        $usage = new LMD_Api_Usage();
        $clients = $usage->get_all_clients_consumption($month);
        $agg = $usage->get_aggregate_consumption($month);

        $lines = [];
        $lines[] =
            "Client;Site ID;Analyses;SerpAPI (unités);SerpAPI ($);Firecrawl (unités);Firecrawl ($);ImgBB (unités);ImgBB ($);Gemini (unités);Gemini ($);Total ($)";
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
            $lines[] = implode(
                ";",
                array_map(function ($v) {
                    return str_replace(
                        [";", "\r", "\n"],
                        [" ", " ", " "],
                        (string) $v,
                    );
                }, $row),
            );
        }
        $lines[] =
            "TOTAL;;" .
            $agg["analyses_count"] .
            ";" .
            $agg["by_api"]["serpapi"]["units"] .
            ";" .
            $agg["by_api"]["serpapi"]["cost_usd"] .
            ";" .
            $agg["by_api"]["firecrawl"]["units"] .
            ";" .
            $agg["by_api"]["firecrawl"]["cost_usd"] .
            ";" .
            $agg["by_api"]["imgbb"]["units"] .
            ";" .
            $agg["by_api"]["imgbb"]["cost_usd"] .
            ";" .
            $agg["by_api"]["gemini"]["units"] .
            ";" .
            $agg["by_api"]["gemini"]["cost_usd"] .
            ";" .
            $agg["total_usd"];
        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines);

        $subject = sprintf("[LMD Apps IA] Consommation IA %s", $month);
        $body =
            "Rapport de consommation IA pour le mois de " .
            $month .
            ".\n\n" .
            count($clients) .
            " client(s), " .
            $agg["analyses_count"] .
            " analyse(s), " .
            number_format($agg["total_usd"], 4) .
            " $ total.\n\nLe fichier CSV est joint.";
        $filename = "consommation-ia-" . $month . ".csv";
        $tmp = wp_upload_bits($filename, null, $csv);
        if (!empty($tmp["file"])) {
            foreach ($emails as $to) {
                wp_mail(
                    $to,
                    $subject,
                    $body,
                    ["Content-Type: text/plain; charset=UTF-8"],
                    [$tmp["file"]],
                );
            }
            @unlink($tmp["file"]);
        }
    } finally {
        if ($switched_to_parent) {
            restore_current_blog();
        }
    }
}
