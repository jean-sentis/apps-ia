<?php
/**
 * Réponses AJAX (édition inline, etc.)
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Ajax
{
    public function __construct()
    {
        add_action("wp_ajax_lmd_save_avis_draft", [$this, "save_avis_draft"]);
        add_action("wp_ajax_lmd_save_estimates", [$this, "save_estimates"]);
        add_action("wp_ajax_lmd_set_tag_by_slug", [$this, "set_tag_by_slug"]);
        add_action("wp_ajax_lmd_save_delegation_draft", [
            $this,
            "save_delegation_draft",
        ]);
        add_action("wp_ajax_lmd_get_cp_settings", [$this, "get_cp_settings"]);
        add_action("wp_ajax_lmd_save_cp_settings", [$this, "save_cp_settings"]);
        add_action("wp_ajax_lmd_list_formules", [$this, "list_formules"]);
        add_action("wp_ajax_lmd_save_formule", [$this, "save_formule"]);
        add_action("wp_ajax_lmd_delete_formule", [$this, "delete_formule"]);
        add_action("wp_ajax_lmd_list_delegation_recipients", [
            $this,
            "list_delegation_recipients",
        ]);
        add_action("wp_ajax_lmd_save_delegation_full", [
            $this,
            "save_delegation_full",
        ]);
        add_action("wp_ajax_lmd_send_delegation_email", [
            $this,
            "send_delegation_email",
        ]);
        add_action("wp_ajax_lmd_generate_delegation_token", [
            $this,
            "generate_delegation_token",
        ]);
        add_action("wp_ajax_lmd_save_reponse", [$this, "save_reponse"]);
        add_action("wp_ajax_lmd_send_reponse_email", [
            $this,
            "send_reponse_email",
        ]);
        add_action("wp_ajax_lmd_add_delegation_recipient", [
            $this,
            "add_delegation_recipient",
        ]);
        add_action("wp_ajax_lmd_get_category_settings", [
            $this,
            "get_category_settings",
        ]);
        add_action("wp_ajax_lmd_save_category_settings", [
            $this,
            "save_category_settings",
        ]);
        add_action("wp_ajax_lmd_save_preferences", [$this, "save_preferences"]);
    }

    public function save_preferences()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        if (!function_exists("lmd_save_preferences_bulk_from_post")) {
            wp_send_json_error(["message" => "Préférences indisponibles"]);
        }
        lmd_save_preferences_bulk_from_post(wp_unslash($_POST));
        wp_send_json_success(["saved" => true]);
    }

    public function get_category_settings()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $type = isset($_POST["type"])
            ? sanitize_text_field($_POST["type"])
            : "";
        if (!in_array($type, ["estimation", "theme_vente"], true)) {
            wp_send_json_error(["message" => "Type invalide"]);
        }
        if ($type === "estimation") {
            $options = function_exists("lmd_get_custom_category_options")
                ? lmd_get_custom_category_options("estimation")
                : [];
            if (empty($options)) {
                $options = function_exists("lmd_get_default_estimation_options")
                    ? lmd_get_default_estimation_options()
                    : [];
            }
        } else {
            $options = function_exists("lmd_get_custom_category_options")
                ? lmd_get_custom_category_options("theme_vente")
                : [];
            if (empty($options)) {
                $options = function_exists(
                    "lmd_get_default_theme_vente_options",
                )
                    ? lmd_get_default_theme_vente_options()
                    : [];
            }
        }
        wp_send_json_success(["options" => $options, "type" => $type]);
    }

    public function save_category_settings()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $type = isset($_POST["type"])
            ? sanitize_text_field($_POST["type"])
            : "";
        if (!in_array($type, ["estimation", "theme_vente"], true)) {
            wp_send_json_error(["message" => "Type invalide"]);
        }
        $raw = isset($_POST["options"]) ? $_POST["options"] : [];
        if (!is_array($raw)) {
            wp_send_json_error(["message" => "Options invalides"]);
        }
        $options = [];
        foreach ($raw as $o) {
            if (!is_array($o)) {
                continue;
            }
            $slug = isset($o["slug"]) ? sanitize_title($o["slug"]) : "";
            if (!$slug) {
                continue;
            }
            $slug = preg_replace("/[^a-z0-9_]/", "_", $slug);
            if ($type === "estimation") {
                $options[] = [
                    "slug" => $slug,
                    "name" => isset($o["name"])
                        ? wp_strip_all_tags($o["name"])
                        : $slug,
                    "max" =>
                        isset($o["max"]) && $o["max"] !== ""
                            ? floatval($o["max"])
                            : null,
                    "min" =>
                        isset($o["min"]) && $o["min"] !== ""
                            ? floatval($o["min"])
                            : null,
                ];
            } else {
                $options[] = [
                    "slug" => $slug,
                    "name" => isset($o["name"])
                        ? wp_strip_all_tags($o["name"])
                        : $slug,
                    "parent_slug" => isset($o["parent_slug"])
                        ? sanitize_text_field($o["parent_slug"])
                        : "",
                ];
            }
        }
        if ($type === "estimation") {
            $options = array_filter($options, function ($o) {
                return (isset($o["max"]) && $o["max"] !== null) ||
                    (isset($o["min"]) && $o["min"] !== null);
            });
        }
        if (function_exists("lmd_save_custom_category_options")) {
            lmd_save_custom_category_options($type, $options);
        }
        if ($type === "theme_vente" && !empty($options)) {
            global $wpdb;
            $site_id = get_current_blog_id();
            $t = $wpdb->prefix . "lmd_tags";
            foreach ($options as $o) {
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM $t WHERE site_id = %d AND type = 'theme_vente' AND slug = %s",
                        $site_id,
                        $o["slug"],
                    ),
                );
                if (!$exists) {
                    $wpdb->insert(
                        $t,
                        [
                            "site_id" => $site_id,
                            "name" => $o["name"],
                            "type" => "theme_vente",
                            "slug" => $o["slug"],
                        ],
                        ["%d", "%s", "%s", "%s"],
                    );
                }
            }
        }
        wp_send_json_success(["options" => $options]);
    }

    public function save_avis_draft()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        $opinion = isset($_POST["opinion"]) ? absint($_POST["opinion"]) : 1;
        $text = isset($_POST["text"])
            ? sanitize_textarea_field(wp_unslash($_POST["text"]))
            : "";
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        global $wpdb;
        $col = $opinion === 2 ? "second_opinion" : "auctioneer_notes";
        $wpdb->update(
            $wpdb->prefix . "lmd_estimations",
            [$col => $text],
            ["id" => $id],
            ["%s"],
            ["%d"],
        );
        wp_send_json_success();
    }

    public function save_estimates()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        global $wpdb;
        $data = [];
        if (isset($_POST["avis1_estimate_low"])) {
            $data["avis1_estimate_low"] = floatval(
                str_replace(
                    [" ", ","],
                    ["", "."],
                    $_POST["avis1_estimate_low"],
                ),
            );
        }
        if (isset($_POST["avis1_estimate_high"])) {
            $data["avis1_estimate_high"] = floatval(
                str_replace(
                    [" ", ","],
                    ["", "."],
                    $_POST["avis1_estimate_high"],
                ),
            );
        }
        if (isset($_POST["avis1_prix_reserve"])) {
            $data["avis1_prix_reserve"] = floatval(
                str_replace(
                    [" ", ","],
                    ["", "."],
                    $_POST["avis1_prix_reserve"],
                ),
            );
        }
        if (isset($_POST["avis2_estimate_low"])) {
            $data["avis2_estimate_low"] = floatval(
                str_replace(
                    [" ", ","],
                    ["", "."],
                    $_POST["avis2_estimate_low"],
                ),
            );
        }
        if (isset($_POST["avis2_estimate_high"])) {
            $data["avis2_estimate_high"] = floatval(
                str_replace(
                    [" ", ","],
                    ["", "."],
                    $_POST["avis2_estimate_high"],
                ),
            );
        }
        if (isset($_POST["avis2_prix_reserve"])) {
            $data["avis2_prix_reserve"] = floatval(
                str_replace(
                    [" ", ","],
                    ["", "."],
                    $_POST["avis2_prix_reserve"],
                ),
            );
        }
        if (!empty($data)) {
            $wpdb->update(
                $wpdb->prefix . "lmd_estimations",
                $data,
                ["id" => $id],
                null,
                ["%d"],
            );
        }
        wp_send_json_success();
    }

    public function set_tag_by_slug()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["estimation_id"])
            ? absint($_POST["estimation_id"])
            : 0;
        $slug = isset($_POST["slug"])
            ? sanitize_text_field($_POST["slug"])
            : "";
        $type = isset($_POST["type"])
            ? sanitize_text_field($_POST["type"])
            : "";
        $opinion = isset($_POST["opinion"]) ? absint($_POST["opinion"]) : 1;
        if ($opinion !== 2) {
            $opinion = 1;
        }
        if (!$id || !$type) {
            wp_send_json_error(["message" => "Paramètres manquants"]);
        }
        global $wpdb;
        $site_id = get_current_blog_id();
        $tags_table = $wpdb->prefix . "lmd_tags";
        $et_table = $wpdb->prefix . "lmd_estimation_tags";
        $is_opinion_specific = function_exists(
            "lmd_is_opinion_specific_tag_type",
        )
            ? lmd_is_opinion_specific_tag_type($type)
            : in_array($type, ["interet", "estimation", "theme_vente"], true);
        $other_opinion = $opinion === 2 ? 1 : 2;
        $existing_links = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, t.type, t.slug, et.modified_by_avis
             FROM $et_table et
             INNER JOIN $tags_table t ON et.tag_id = t.id
             WHERE et.estimation_id = %d AND t.type = %s AND t.site_id = %d",
                $id,
                $type,
                $site_id,
            ),
        );
        $current_link = function_exists("lmd_get_linked_tag_by_type")
            ? lmd_get_linked_tag_by_type($existing_links, $type, $opinion)
            : null;
        $other_link =
            $is_opinion_specific &&
            function_exists("lmd_get_linked_tag_by_type")
                ? lmd_get_linked_tag_by_type(
                    $existing_links,
                    $type,
                    $other_opinion,
                )
                : null;
        $current_link_opinion =
            $current_link && function_exists("lmd_get_linked_tag_opinion")
                ? lmd_get_linked_tag_opinion($current_link)
                : null;
        $other_link_opinion =
            $other_link && function_exists("lmd_get_linked_tag_opinion")
                ? lmd_get_linked_tag_opinion($other_link)
                : null;

        if (empty($slug)) {
            if ($is_opinion_specific && $current_link) {
                if ($current_link_opinion === 0) {
                    $wpdb->update(
                        $et_table,
                        ["modified_by_avis" => $other_opinion],
                        ["estimation_id" => $id, "tag_id" => $current_link->id],
                        ["%d"],
                        ["%d", "%d"],
                    );
                } else {
                    $wpdb->delete(
                        $et_table,
                        ["estimation_id" => $id, "tag_id" => $current_link->id],
                        ["%d", "%d"],
                    );
                }
            } else {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE et FROM $et_table et INNER JOIN $tags_table t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.type = %s",
                        $id,
                        $type,
                    ),
                );
            }
            wp_send_json_success(["tag_id" => 0]);
        }

        $tag = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name FROM $tags_table WHERE site_id = %d AND type = %s AND slug = %s",
                $site_id,
                $type,
                $slug,
            ),
        );
        if (!$tag) {
            $cats = function_exists("lmd_get_tag_categories")
                ? lmd_get_tag_categories()
                : [];
            $name = $slug;
            if (isset($cats[$type]["options"])) {
                foreach ($cats[$type]["options"] as $o) {
                    if (($o["slug"] ?? "") === $slug) {
                        $name = $o["name"] ?? $slug;
                        break;
                    }
                }
            }
            $wpdb->insert(
                $tags_table,
                [
                    "site_id" => $site_id,
                    "name" => $name,
                    "type" => $type,
                    "slug" => $slug,
                ],
                ["%d", "%s", "%s", "%s"],
            );
            $tag_id = $wpdb->insert_id;
        } else {
            $tag_id = $tag->id;
        }

        if (!$is_opinion_specific) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE et FROM $et_table et INNER JOIN $tags_table t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.type = %s",
                    $id,
                    $type,
                ),
            );
            $wpdb->insert(
                $et_table,
                [
                    "estimation_id" => $id,
                    "tag_id" => $tag_id,
                    "modified_by_avis" => 0,
                ],
                ["%d", "%d", "%d"],
            );
            wp_send_json_success(["tag_id" => $tag_id]);
        }

        if ($current_link && (int) $current_link->id === (int) $tag_id) {
            if ($current_link_opinion === null && $opinion === 1) {
                $wpdb->update(
                    $et_table,
                    ["modified_by_avis" => 1],
                    ["estimation_id" => $id, "tag_id" => $tag_id],
                    ["%d"],
                    ["%d", "%d"],
                );
            }
            wp_send_json_success(["tag_id" => $tag_id]);
        }

        if ($other_link && (int) $other_link->id === (int) $tag_id) {
            if ($other_link_opinion !== 0) {
                $wpdb->update(
                    $et_table,
                    ["modified_by_avis" => 0],
                    ["estimation_id" => $id, "tag_id" => $tag_id],
                    ["%d"],
                    ["%d", "%d"],
                );
            }
            if ($current_link && (int) $current_link->id !== (int) $tag_id) {
                if ($current_link_opinion === 0) {
                    $wpdb->update(
                        $et_table,
                        ["modified_by_avis" => $other_opinion],
                        ["estimation_id" => $id, "tag_id" => $current_link->id],
                        ["%d"],
                        ["%d", "%d"],
                    );
                } else {
                    $wpdb->delete(
                        $et_table,
                        ["estimation_id" => $id, "tag_id" => $current_link->id],
                        ["%d", "%d"],
                    );
                }
            }
            wp_send_json_success(["tag_id" => $tag_id]);
        }

        if ($current_link) {
            if ($current_link_opinion === 0) {
                $wpdb->update(
                    $et_table,
                    ["modified_by_avis" => $other_opinion],
                    ["estimation_id" => $id, "tag_id" => $current_link->id],
                    ["%d"],
                    ["%d", "%d"],
                );
            } else {
                $wpdb->delete(
                    $et_table,
                    ["estimation_id" => $id, "tag_id" => $current_link->id],
                    ["%d", "%d"],
                );
            }
        }

        $wpdb->insert(
            $et_table,
            [
                "estimation_id" => $id,
                "tag_id" => $tag_id,
                "modified_by_avis" => $opinion,
            ],
            ["%d", "%d", "%d"],
        );
        wp_send_json_success(["tag_id" => $tag_id]);
    }

    public function save_delegation_draft()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        $draft = isset($_POST["draft"])
            ? wp_kses_post(wp_unslash($_POST["draft"]))
            : "";
        $email = isset($_POST["email"]) ? sanitize_email($_POST["email"]) : "";
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . "lmd_estimations",
            ["delegation_draft" => $draft, "delegation_email" => $email],
            ["id" => $id],
            ["%s", "%s"],
            ["%d"],
        );
        wp_send_json_success();
    }

    public function get_cp_settings()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $site_id = get_current_blog_id();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT cp_email, cp_signature, cp_copy_emails FROM {$wpdb->prefix}lmd_cp_settings WHERE site_id = %d AND user_id = %d",
                $site_id,
                $user_id,
            ),
        );
        $copy = [];
        if ($row && !empty($row->cp_copy_emails)) {
            $copy = array_filter(
                array_map(
                    "trim",
                    explode(",", wp_unslash($row->cp_copy_emails)),
                ),
            );
        }
        wp_send_json_success([
            "email" => $row->cp_email ?? "",
            "signature" => wp_unslash($row->cp_signature ?? ""),
            "copy_emails" => implode(", ", $copy),
        ]);
    }

    public function save_cp_settings()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $email = isset($_POST["email"]) ? sanitize_email($_POST["email"]) : "";
        $signature = isset($_POST["signature"])
            ? wp_kses_post(wp_unslash($_POST["signature"]))
            : "";
        $copy = isset($_POST["copy_emails"])
            ? sanitize_text_field(wp_unslash($_POST["copy_emails"]))
            : "";
        global $wpdb;
        $user_id = get_current_user_id();
        $site_id = get_current_blog_id();
        $table = $wpdb->prefix . "lmd_cp_settings";
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE site_id = %d AND user_id = %d",
                $site_id,
                $user_id,
            ),
        );
        if ($exists) {
            $wpdb->update(
                $table,
                [
                    "cp_email" => $email,
                    "cp_signature" => $signature,
                    "cp_copy_emails" => $copy,
                ],
                ["site_id" => $site_id, "user_id" => $user_id],
                ["%s", "%s", "%s"],
                ["%d", "%d"],
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    "site_id" => $site_id,
                    "user_id" => $user_id,
                    "cp_email" => $email,
                    "cp_signature" => $signature,
                    "cp_copy_emails" => $copy,
                ],
                ["%d", "%d", "%s", "%s", "%s"],
            );
        }
        wp_send_json_success();
    }

    public function list_formules()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $site_id = get_current_blog_id();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, content FROM {$wpdb->prefix}lmd_formules WHERE site_id = %d AND user_id = %d ORDER BY name",
                $site_id,
                $user_id,
            ),
        );
        if ($rows) {
            foreach ($rows as $r) {
                $r->content = wp_unslash($r->content ?? "");
            }
        }
        wp_send_json_success(["formules" => $rows ?: []]);
    }

    public function save_formule()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        $name = isset($_POST["name"])
            ? sanitize_text_field(wp_unslash($_POST["name"]))
            : "";
        $content = isset($_POST["content"])
            ? wp_kses_post(wp_unslash($_POST["content"]))
            : "";
        if (!$name) {
            wp_send_json_error(["message" => "Nom requis"]);
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $site_id = get_current_blog_id();
        $table = $wpdb->prefix . "lmd_formules";
        if ($id) {
            $wpdb->update(
                $table,
                ["name" => $name, "content" => $content],
                ["id" => $id, "user_id" => $user_id],
                ["%s", "%s"],
                ["%d", "%d"],
            );
            wp_send_json_success(["id" => $id]);
        } else {
            $wpdb->insert(
                $table,
                [
                    "site_id" => $site_id,
                    "user_id" => $user_id,
                    "name" => $name,
                    "content" => $content,
                ],
                ["%d", "%d", "%s", "%s"],
            );
            wp_send_json_success(["id" => $wpdb->insert_id]);
        }
    }

    public function delete_formule()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}lmd_formules WHERE id = %d AND user_id = %d",
                $id,
                $user_id,
            ),
        );
        wp_send_json_success();
    }

    public function list_delegation_recipients()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        global $wpdb;
        $site_id = get_current_blog_id();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT email FROM {$wpdb->prefix}lmd_delegation_recipients WHERE site_id = %d ORDER BY email",
                $site_id,
            ),
        );
        $emails = $rows ? array_column($rows, "email") : [];
        wp_send_json_success(["emails" => $emails]);
    }

    public function save_delegation_full()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        $email = isset($_POST["email"]) ? sanitize_email($_POST["email"]) : "";
        $subject = isset($_POST["subject"])
            ? sanitize_text_field(wp_unslash($_POST["subject"]))
            : "";
        $body = isset($_POST["body"])
            ? wp_kses_post(wp_unslash($_POST["body"]))
            : "";
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . "lmd_estimations",
            [
                "delegation_email" => $email,
                "delegation_subject" => $subject,
                "delegation_body" => $body,
                "delegation_draft" => $body,
            ],
            ["id" => $id],
            ["%s", "%s", "%s", "%s"],
            ["%d"],
        );
        if ($email) {
            $table = $wpdb->prefix . "lmd_delegation_recipients";
            $site_id = get_current_blog_id();
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE site_id = %d AND email = %s",
                    $site_id,
                    $email,
                ),
            );
            if (!$exists) {
                $wpdb->insert(
                    $table,
                    ["site_id" => $site_id, "email" => $email],
                    ["%d", "%s"],
                );
            }
        }
        wp_send_json_success();
    }

    public function send_delegation_email()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        $email = isset($_POST["email"]) ? sanitize_email($_POST["email"]) : "";
        $subject = isset($_POST["subject"])
            ? sanitize_text_field(wp_unslash($_POST["subject"]))
            : "";
        $body = isset($_POST["body"]) ? wp_unslash($_POST["body"]) : "";
        if (!$email) {
            wp_send_json_error(["message" => "Indiquez le destinataire."]);
        }
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . "lmd_estimations",
            [
                "delegation_email" => $email,
                "delegation_subject" => $subject,
                "delegation_body" => $body,
                "delegation_draft" => $body,
            ],
            ["id" => $id],
            ["%s", "%s", "%s", "%s"],
            ["%d"],
        );
        if ($email) {
            $table = $wpdb->prefix . "lmd_delegation_recipients";
            $site_id = get_current_blog_id();
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table WHERE site_id = %d AND email = %s",
                    $site_id,
                    $email,
                ),
            );
            if (!$exists) {
                $wpdb->insert(
                    $table,
                    ["site_id" => $site_id, "email" => $email],
                    ["%d", "%s"],
                );
            }
        }
        $body_html = nl2br(esc_html($body));
        $body_html = preg_replace_callback(
            '/À cliquer pour rejoindre la demande d\'estimation\s*:\s*(?:<br \/>)?\s*(https?:\/\/[^\s<]+)/i',
            function ($m) {
                return '<a href="' .
                    esc_url($m[1]) .
                    '">à cliquer pour rejoindre la demande d\'estimation</a>';
            },
            $body_html,
        );
        $headers = ["Content-Type: text/html; charset=UTF-8"];
        $sent = wp_mail($email, $subject, $body_html, $headers);
        if ($sent) {
            $sent_at = $this->mark_delegation_as_sent($id);
            wp_send_json_success([
                "message" => "Email envoyé.",
                "sent_at" => $sent_at,
            ]);
        } else {
            wp_send_json_error(["message" => 'Échec de l\'envoi.']);
        }
    }

    private function mark_delegation_as_sent($id, $sent_at = null)
    {
        global $wpdb;
        $sent_at = $sent_at ?: current_time("mysql");
        $wpdb->update(
            $wpdb->prefix . "lmd_estimations",
            ["delegation_sent_at" => $sent_at],
            ["id" => $id],
            ["%s"],
            ["%d"],
        );
        return $sent_at;
    }

    public function generate_delegation_token()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        $email = isset($_POST["email"]) ? sanitize_email($_POST["email"]) : "";
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+30 days"));
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . "lmd_delegation_tokens",
            [
                "estimation_id" => $id,
                "token" => $token,
                "email" => $email ?: "",
                "expires_at" => $expires,
            ],
            ["%d", "%s", "%s", "%s"],
        );
        $public_url = home_url("/?lmd_delegation_token=" . $token);
        $admin_url = admin_url(
            "admin.php?page=lmd-estimation-detail&id=" .
                $id .
                "&lmd_delegation_token=" .
                $token,
        );
        wp_send_json_success([
            "url" => $public_url,
            "admin_url" => $admin_url,
            "token" => $token,
        ]);
    }

    public function save_reponse()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        $subject = isset($_POST["subject"])
            ? sanitize_text_field(wp_unslash($_POST["subject"]))
            : "";
        $body = isset($_POST["body"])
            ? wp_kses_post(wp_unslash($_POST["body"]))
            : "";
        $mark_sent = !empty($_POST["mark_sent"]);
        $questions =
            isset($_POST["questions_selected"]) &&
            is_array($_POST["questions_selected"])
                ? array_map("absint", $_POST["questions_selected"])
                : null;
        $this->save_reponse_data($id, $subject, $body, $questions);
        if ($mark_sent) {
            $sent_at = $this->mark_reponse_as_sent($id);
        }
        wp_send_json_success([
            "sent_at" => $mark_sent ? $sent_at ?? null : null,
        ]);
    }

    private function save_reponse_data($id, $subject, $body, $questions = null)
    {
        global $wpdb;
        $data = ["reponse_subject" => $subject, "reponse_body" => $body];
        if (is_array($questions)) {
            $data["reponse_questions_selected"] = wp_json_encode(
                array_values(array_map("absint", $questions)),
            );
        }
        $wpdb->update(
            $wpdb->prefix . "lmd_estimations",
            $data,
            ["id" => $id],
            null,
            ["%d"],
        );
    }

    private function mark_reponse_as_sent($id, $sent_at = null)
    {
        global $wpdb;
        $sent_at = $sent_at ?: current_time("mysql");
        $wpdb->update(
            $wpdb->prefix . "lmd_estimations",
            ["reponse_sent_at" => $sent_at],
            ["id" => $id],
            ["%s"],
            ["%d"],
        );
        $site_id = get_current_blog_id();
        $tag = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}lmd_tags WHERE site_id = %d AND type = 'message' AND slug = 'repondu'",
                $site_id,
            ),
        );
        if ($tag) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE et FROM {$wpdb->prefix}lmd_estimation_tags et INNER JOIN {$wpdb->prefix}lmd_tags t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.type = 'message'",
                    $id,
                ),
            );
            $wpdb->insert(
                $wpdb->prefix . "lmd_estimation_tags",
                ["estimation_id" => $id, "tag_id" => $tag->id],
                ["%d", "%d"],
            );
        }
        return $sent_at;
    }

    public function send_reponse_email()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        if (!$id) {
            wp_send_json_error(["message" => "ID manquant"]);
        }
        $subject = isset($_POST["subject"])
            ? sanitize_text_field(wp_unslash($_POST["subject"]))
            : "";
        $body = isset($_POST["body"])
            ? wp_kses_post(wp_unslash($_POST["body"]))
            : "";
        $questions =
            isset($_POST["questions_selected"]) &&
            is_array($_POST["questions_selected"])
                ? array_map("absint", $_POST["questions_selected"])
                : null;
        $interet_slug = isset($_POST["interet_slug"])
            ? sanitize_key(wp_unslash($_POST["interet_slug"]))
            : "";
        $estimation_slug = isset($_POST["estimation_slug"])
            ? sanitize_key(wp_unslash($_POST["estimation_slug"]))
            : "";
        global $wpdb;
        $this->save_reponse_data($id, $subject, $body, $questions);

        $estimation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, client_email FROM {$wpdb->prefix}lmd_estimations WHERE id = %d",
                $id,
            ),
        );
        if (!$estimation) {
            wp_send_json_error(["message" => "Estimation introuvable"]);
        }

        $email = sanitize_email($estimation->client_email ?? "");
        if (!$email) {
            wp_send_json_error(["message" => "Email client non disponible"]);
        }

        $cp = function_exists("lmd_get_cp_settings_for_user")
            ? lmd_get_cp_settings_for_user()
            : ["email" => "", "signature" => "", "copy_emails" => []];
        $prefs = function_exists("lmd_get_prefs") ? lmd_get_prefs() : [];
        $excluded =
            isset($prefs["bcc_exclude_response_slugs"]) &&
            is_array($prefs["bcc_exclude_response_slugs"])
                ? array_map(
                    "sanitize_key",
                    $prefs["bcc_exclude_response_slugs"],
                )
                : [];
        $skip_bcc =
            ($interet_slug && in_array($interet_slug, $excluded, true)) ||
            ($estimation_slug && in_array($estimation_slug, $excluded, true));

        $signature_html = !empty($cp["signature"])
            ? wp_kses_post($cp["signature"])
            : "";
        $body_html = wpautop(esc_html($body));
        if ($signature_html !== "") {
            $body_html .=
                '<div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;">' .
                $signature_html .
                "</div>";
        }

        $headers = ["Content-Type: text/html; charset=UTF-8"];
        $cp_email = sanitize_email($cp["email"] ?? "");
        if ($cp_email) {
            $headers[] = "Reply-To: " . $cp_email;
        }
        if (
            !$skip_bcc &&
            !empty($cp["copy_emails"]) &&
            is_array($cp["copy_emails"])
        ) {
            foreach ($cp["copy_emails"] as $bcc_email) {
                $bcc_email = sanitize_email($bcc_email);
                if ($bcc_email && strcasecmp($bcc_email, $email) !== 0) {
                    $headers[] = "Bcc: " . $bcc_email;
                }
            }
        }

        $sent = wp_mail($email, $subject, $body_html, $headers);
        if (!$sent) {
            wp_send_json_error(["message" => "Échec de l'envoi."]);
        }

        $sent_at = $this->mark_reponse_as_sent($id);
        wp_send_json_success([
            "message" => "Email envoyé.",
            "sent_at" => $sent_at,
        ]);
    }

    public function add_delegation_recipient()
    {
        check_ajax_referer("lmd_admin", "nonce");
        if (!function_exists("lmd_user_can_access_estimation_app") || !lmd_user_can_access_estimation_app()) {
            wp_send_json_error(["message" => "Non autorisé"]);
        }
        $email = isset($_POST["email"]) ? sanitize_email($_POST["email"]) : "";
        if (!$email) {
            wp_send_json_success();
        }
        global $wpdb;
        $table = $wpdb->prefix . "lmd_delegation_recipients";
        $site_id = get_current_blog_id();
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE site_id = %d AND email = %s",
                $site_id,
                $email,
            ),
        );
        if (!$exists) {
            $wpdb->insert(
                $table,
                ["site_id" => $site_id, "email" => $email],
                ["%d", "%s"],
            );
        }
        wp_send_json_success();
    }
}

