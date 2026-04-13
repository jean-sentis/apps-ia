<?php
/**
 * Vue publique pour délégation (accès par token, sans login)
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Delegation_View
{
    public static function register()
    {
        add_shortcode("lmd_delegation_view", [__CLASS__, "render_shortcode"]);
        add_action("template_redirect", [__CLASS__, "maybe_serve_delegation"]);
    }

    public static function maybe_serve_delegation()
    {
        $token = self::get_request_token();
        if (!$token) {
            return;
        }
        $estimation = self::get_estimation_by_token($token);
        if (!$estimation) {
            status_header(404);
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Lien invalide</title></head><body><p>Lien invalide ou expiré.</p></body></html>';
            exit();
        }
        $feedback = self::maybe_handle_submission($token, $estimation);
        self::render_delegation_page($estimation, [
            "standalone" => true,
            "token" => $token,
            "feedback" => $feedback,
        ]);
        exit();
    }

    private static function get_estimation_by_token($token)
    {
        global $wpdb;
        $table = $wpdb->prefix . "lmd_delegation_tokens";
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT estimation_id, expires_at FROM $table WHERE token = %s",
                $token,
            ),
        );
        if (
            !$row ||
            ($row->expires_at && strtotime($row->expires_at) < time())
        ) {
            return null;
        }
        $db = new LMD_Database();
        return $db->get_estimation((int) $row->estimation_id);
    }

    private static function get_request_token()
    {
        if (!empty($_GET["lmd_delegation_token"])) {
            return sanitize_text_field(
                wp_unslash($_GET["lmd_delegation_token"]),
            );
        }
        if (!empty($_GET["token"])) {
            return sanitize_text_field(wp_unslash($_GET["token"]));
        }
        return "";
    }

    public static function render_shortcode($atts = [])
    {
        $token = self::get_request_token();
        if (!$token) {
            return "<p>Utilisez le lien reçu par email.</p>";
        }
        $estimation = self::get_estimation_by_token($token);
        if (!$estimation) {
            return "<p>Lien invalide ou expiré.</p>";
        }
        $feedback = self::maybe_handle_submission($token, $estimation);
        ob_start();
        self::render_delegation_page($estimation, [
            "standalone" => false,
            "token" => $token,
            "feedback" => $feedback,
        ]);
        return ob_get_clean();
    }

    private static function maybe_handle_submission($token, $estimation)
    {
        if (
            ($_SERVER["REQUEST_METHOD"] ?? "") !== "POST" ||
            empty($_POST["lmd_delegation_submit"])
        ) {
            return null;
        }

        $posted_token = isset($_POST["lmd_delegation_token"])
            ? sanitize_text_field(wp_unslash($_POST["lmd_delegation_token"]))
            : "";
        if (
            !$posted_token ||
            !hash_equals((string) $token, (string) $posted_token)
        ) {
            return [
                "type" => "error",
                "message" => "Jeton invalide. Merci d’utiliser le lien reçu.",
            ];
        }

        $interest_slug = isset($_POST["avis_interet"])
            ? sanitize_text_field(wp_unslash($_POST["avis_interet"]))
            : "";
        $interest_options = self::get_interest_options();
        $interest_slugs = array_values(
            array_filter(
                array_map(
                    static function ($option) {
                        return (string) ($option["slug"] ?? "");
                    },
                    $interest_options,
                ),
            ),
        );
        if (
            $interest_slug !== "" &&
            !in_array($interest_slug, $interest_slugs, true)
        ) {
            return [
                "type" => "error",
                "message" => "Le niveau d’intérêt sélectionné est invalide.",
            ];
        }

        global $wpdb;
        $table = $wpdb->prefix . "lmd_estimations";
        $data = [
            "second_opinion" => sanitize_textarea_field(
                wp_unslash($_POST["avis_text"] ?? ""),
            ),
            "avis2_titre" => sanitize_text_field(
                wp_unslash($_POST["avis_titre"] ?? ""),
            ),
            "avis2_estimate_low" => self::parse_decimal(
                $_POST["estimate_low"] ?? null,
            ),
            "avis2_prix_reserve" => self::parse_decimal(
                $_POST["prix_reserve"] ?? null,
            ),
            "avis2_estimate_high" => self::parse_decimal(
                $_POST["estimate_high"] ?? null,
            ),
        ];

        $updated = $wpdb->update(
            $table,
            $data,
            ["id" => (int) $estimation->id],
            null,
            ["%d"],
        );
        if ($updated === false) {
            return [
                "type" => "error",
                "message" => "L'avis n'a pas pu être enregistré.",
            ];
        }

        if (
            $interest_slug !== "" &&
            !self::save_opinion_tag_selection(
                (int) $estimation->id,
                "interet",
                $interest_slug,
                2,
            )
        ) {
            return [
                "type" => "error",
                "message" => "L’avis a été enregistré, mais le niveau d’intérêt n’a pas pu être mis à jour.",
            ];
        }

        foreach ($data as $key => $value) {
            $estimation->$key = $value;
        }

        return [
            "type" => "success",
            "message" => "L'avis externe a bien été enregistré.",
        ];
    }

    private static function get_interest_options()
    {
        $categories = function_exists("lmd_get_tag_categories")
            ? lmd_get_tag_categories()
            : [];
        $options = $categories["interet"]["options"] ?? [];
        return is_array($options) ? $options : [];
    }

    private static function save_opinion_tag_selection(
        $estimation_id,
        $type,
        $slug,
        $opinion = 2,
    ) {
        global $wpdb;

        $estimation_id = (int) $estimation_id;
        $type = (string) $type;
        $slug = (string) $slug;
        $opinion = (int) $opinion === 2 ? 2 : 1;
        if (!$estimation_id || $type === "" || $slug === "") {
            return false;
        }

        $site_id = get_current_blog_id();
        $tags_table = $wpdb->prefix . "lmd_tags";
        $et_table = $wpdb->prefix . "lmd_estimation_tags";
        $is_opinion_specific = function_exists("lmd_is_opinion_specific_tag_type")
            ? lmd_is_opinion_specific_tag_type($type)
            : in_array($type, ["interet", "estimation", "theme_vente"], true);
        $other_opinion = $opinion === 2 ? 1 : 2;

        $existing_links = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.id, t.type, t.slug, t.name, et.modified_by_avis
                 FROM $et_table et
                 INNER JOIN $tags_table t ON et.tag_id = t.id
                 WHERE et.estimation_id = %d AND t.type = %s AND t.site_id = %d",
                $estimation_id,
                $type,
                $site_id,
            ),
        );

        $current_link = function_exists("lmd_get_linked_tag_by_type")
            ? lmd_get_linked_tag_by_type($existing_links, $type, $opinion)
            : null;
        $other_link =
            $is_opinion_specific && function_exists("lmd_get_linked_tag_by_type")
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

        $tag = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name FROM $tags_table WHERE site_id = %d AND type = %s AND slug = %s",
                $site_id,
                $type,
                $slug,
            ),
        );
        if (!$tag) {
            $categories = function_exists("lmd_get_tag_categories")
                ? lmd_get_tag_categories()
                : [];
            $name = $slug;
            if (isset($categories[$type]["options"])) {
                foreach ($categories[$type]["options"] as $option) {
                    if (($option["slug"] ?? "") === $slug) {
                        $name = $option["name"] ?? $slug;
                        break;
                    }
                }
            }
            $inserted = $wpdb->insert(
                $tags_table,
                [
                    "site_id" => $site_id,
                    "name" => $name,
                    "type" => $type,
                    "slug" => $slug,
                ],
                ["%d", "%s", "%s", "%s"],
            );
            if ($inserted === false) {
                return false;
            }
            $tag_id = (int) $wpdb->insert_id;
        } else {
            $tag_id = (int) $tag->id;
        }

        if (!$is_opinion_specific) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE et FROM $et_table et INNER JOIN $tags_table t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.type = %s",
                    $estimation_id,
                    $type,
                ),
            );
            return false !== $wpdb->insert(
                $et_table,
                [
                    "estimation_id" => $estimation_id,
                    "tag_id" => $tag_id,
                    "modified_by_avis" => 0,
                ],
                ["%d", "%d", "%d"],
            );
        }

        if ($current_link && (int) $current_link->id === $tag_id) {
            if ($current_link_opinion === null && $opinion === 1) {
                return false !== $wpdb->update(
                    $et_table,
                    ["modified_by_avis" => 1],
                    ["estimation_id" => $estimation_id, "tag_id" => $tag_id],
                    ["%d"],
                    ["%d", "%d"],
                );
            }
            return true;
        }

        if ($other_link && (int) $other_link->id === $tag_id) {
            if ($other_link_opinion !== 0) {
                $wpdb->update(
                    $et_table,
                    ["modified_by_avis" => 0],
                    ["estimation_id" => $estimation_id, "tag_id" => $tag_id],
                    ["%d"],
                    ["%d", "%d"],
                );
            }
            if ($current_link && (int) $current_link->id !== $tag_id) {
                if ($current_link_opinion === 0) {
                    $wpdb->update(
                        $et_table,
                        ["modified_by_avis" => $other_opinion],
                        ["estimation_id" => $estimation_id, "tag_id" => $current_link->id],
                        ["%d"],
                        ["%d", "%d"],
                    );
                } else {
                    $wpdb->delete(
                        $et_table,
                        ["estimation_id" => $estimation_id, "tag_id" => $current_link->id],
                        ["%d", "%d"],
                    );
                }
            }
            return true;
        }

        if ($current_link) {
            if ($current_link_opinion === 0) {
                $wpdb->update(
                    $et_table,
                    ["modified_by_avis" => $other_opinion],
                    ["estimation_id" => $estimation_id, "tag_id" => $current_link->id],
                    ["%d"],
                    ["%d", "%d"],
                );
            } else {
                $wpdb->delete(
                    $et_table,
                    ["estimation_id" => $estimation_id, "tag_id" => $current_link->id],
                    ["%d", "%d"],
                );
            }
        }

        return false !== $wpdb->insert(
            $et_table,
            [
                "estimation_id" => $estimation_id,
                "tag_id" => $tag_id,
                "modified_by_avis" => $opinion,
            ],
            ["%d", "%d", "%d"],
        );
    }

    private static function parse_decimal($value)
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === "") {
            return null;
        }
        $normalized = str_replace([" ", ","], ["", "."], $raw);
        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private static function format_decimal($value)
    {
        if ($value === null || $value === "") {
            return "";
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        $number = (float) $value;
        if (abs($number - floor($number)) < 0.00001) {
            return (string) (int) $number;
        }
        return rtrim(rtrim(number_format($number, 2, ".", ""), "0"), ".");
    }

    private static function build_request_title($estimation)
    {
        $parts = [];
        $first = trim((string) ($estimation->client_first_name ?? ""));
        $last = trim((string) ($estimation->client_name ?? ""));
        $identity = trim($first . " " . $last);
        if ($identity === "") {
            $identity = trim((string) ($estimation->client_email ?? ""));
        }
        $city = trim((string) ($estimation->client_commune ?? ""));
        $suffix =
            $identity !== ""
                ? $identity
                : "demandeur #" . (int) $estimation->id;
        if ($city !== "") {
            $suffix .= " (" . $city . ")";
        }
        $parts[] = "Demande d'estimation de " . $suffix;
        if (!empty($estimation->created_at)) {
            $parts[] = wp_date(
                get_option("date_format"),
                strtotime((string) $estimation->created_at),
            );
        }
        return implode(" — ", array_filter($parts));
    }

    private static function get_delegation_styles()
    {
        return <<<'CSS'
                    :root {
                        --lmd-bg: #f3f6fb;
                        --lmd-panel: #ffffff;
                        --lmd-border: #dbe4f0;
                        --lmd-border-strong: #bfd0e4;
                        --lmd-text: #1f2937;
                        --lmd-muted: #64748b;
                        --lmd-primary: #2563eb;
                        --lmd-primary-soft: #eff6ff;
                        --lmd-success: #15803d;
                        --lmd-success-soft: #f0fdf4;
                        --lmd-danger: #b91c1c;
                        --lmd-danger-soft: #fef2f2;
                        --lmd-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
                    }
                    * { box-sizing: border-box; }
            .lmd-delegation-shell,
            .lmd-delegation-shortcode {
                color: var(--lmd-text);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                line-height: 1.5;
            }
            .lmd-delegation-shortcode {
                background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
                padding: 1px 0;
            }
                    .lmd-delegation-shell {
                        max-width: 1440px;
                        margin: 0 auto;
                        padding: 40px 24px 56px;
                    }
                    .lmd-delegation-header {
                        margin-bottom: 24px;
                    }
                    .lmd-delegation-kicker {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 6px 12px;
                        border-radius: 999px;
                        background: #e0ecff;
                        color: #1d4ed8;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: .04em;
                        text-transform: uppercase;
                    }
                    .lmd-delegation-header h1 {
                        margin: 14px 0 8px;
                        font-size: clamp(28px, 4vw, 40px);
                        line-height: 1.1;
                        letter-spacing: -0.03em;
                    }
                    .lmd-delegation-header p {
                        margin: 0;
                        color: var(--lmd-muted);
                        font-size: 15px;
                    }
                    .lmd-delegation-notice {
                        margin-bottom: 18px;
                        padding: 14px 16px;
                        border-radius: 14px;
                        border: 1px solid var(--lmd-border);
                        font-size: 14px;
                        font-weight: 600;
                    }
                    .lmd-delegation-notice--success {
                        background: var(--lmd-success-soft);
                        border-color: #bbf7d0;
                        color: var(--lmd-success);
                    }
                    .lmd-delegation-notice--error {
                        background: var(--lmd-danger-soft);
                        border-color: #fecaca;
                        color: var(--lmd-danger);
                    }
                    .lmd-delegation-grid {
                        display: grid;
                        grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
                        gap: 22px;
                        align-items: start;
                    }
                    .lmd-delegation-panel {
                        background: var(--lmd-panel);
                        border: 1px solid var(--lmd-border);
                        border-radius: 24px;
                        box-shadow: var(--lmd-shadow);
                        overflow: hidden;
                    }
                    .lmd-delegation-panel-head {
                        padding: 18px 22px 16px;
                        border-bottom: 1px solid var(--lmd-border);
                        background: linear-gradient(180deg, #f9fbff 0%, #f3f7fd 100%);
                    }
                    .lmd-delegation-panel-eyebrow {
                        display: block;
                        color: var(--lmd-primary);
                        font-size: 11px;
                        font-weight: 800;
                        letter-spacing: .08em;
                        text-transform: uppercase;
                        margin-bottom: 8px;
                    }
                    .lmd-delegation-panel-head h2 {
                        margin: 0;
                        font-size: 20px;
                        line-height: 1.2;
                    }
                    .lmd-delegation-request-title {
                        padding: 18px 22px 0;
                        color: var(--lmd-muted);
                        font-size: 14px;
                        font-weight: 600;
                    }
                    .lmd-delegation-photos {
                        padding: 18px 22px 12px;
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                        gap: 14px;
                    }
                    .lmd-delegation-photo {
                        display: block;
                        position: relative;
                        background: #f8fafc;
                        border: 1px solid var(--lmd-border);
                        border-radius: 18px;
                        overflow: hidden;
                        min-height: 180px;
                        cursor: zoom-in;
                        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
                    }
                    .lmd-delegation-photo:hover {
                        transform: translateY(-2px);
                        border-color: #bfdbfe;
                        box-shadow: 0 16px 28px rgba(37, 99, 235, 0.10);
                    }
                    .lmd-delegation-photo img {
                        width: 100%;
                        height: 100%;
                        max-height: 320px;
                        object-fit: contain;
                        display: block;
                        background: #fff;
                    }
                    .lmd-delegation-viewer {
                        position: fixed;
                        inset: 0;
                        z-index: 99999;
                        display: none;
                        align-items: center;
                        justify-content: center;
                        padding: 28px;
                        background: rgba(15, 23, 42, 0.82);
                        backdrop-filter: blur(3px);
                    }
                    .lmd-delegation-viewer.open {
                        display: flex;
                    }
                    .lmd-delegation-viewer-dialog {
                        position: relative;
                        width: min(1120px, 100%);
                        max-height: min(90vh, 920px);
                        display: grid;
                        gap: 16px;
                    }
                    .lmd-delegation-viewer-stage {
                        position: relative;
                        min-height: min(72vh, 760px);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 56px 88px;
                        border-radius: 28px;
                        background: rgba(15, 23, 42, 0.92);
                        box-shadow: 0 28px 64px rgba(15, 23, 42, 0.34);
                        overflow: hidden;
                    }
                    .lmd-delegation-viewer-image {
                        width: 100%;
                        max-width: 100%;
                        max-height: min(72vh, 760px);
                        object-fit: contain;
                        display: block;
                        user-select: none;
                    }
                    .lmd-delegation-viewer-close,
                    .lmd-delegation-viewer-nav {
                        position: absolute;
                        border: none;
                        color: #fff;
                        cursor: pointer;
                        background: rgba(15, 23, 42, 0.68);
                        backdrop-filter: blur(8px);
                        transition: background .18s ease, transform .18s ease, opacity .18s ease;
                    }
                    .lmd-delegation-viewer-close:hover,
                    .lmd-delegation-viewer-nav:hover {
                        background: rgba(30, 41, 59, 0.92);
                        transform: translateY(-1px);
                    }
                    .lmd-delegation-viewer-close {
                        top: 18px;
                        right: 18px;
                        width: 42px;
                        height: 42px;
                        border-radius: 999px;
                        font-size: 26px;
                        line-height: 1;
                    }
                    .lmd-delegation-viewer-nav {
                        top: 50%;
                        transform: translateY(-50%);
                        width: 52px;
                        height: 52px;
                        border-radius: 999px;
                        font-size: 30px;
                        line-height: 1;
                    }
                    .lmd-delegation-viewer-nav:hover {
                        transform: translateY(-50%) translateY(-1px);
                    }
                    .lmd-delegation-viewer-nav[disabled] {
                        opacity: .35;
                        cursor: default;
                        pointer-events: none;
                    }
                    .lmd-delegation-viewer-prev {
                        left: 18px;
                    }
                    .lmd-delegation-viewer-next {
                        right: 18px;
                    }
                    .lmd-delegation-viewer-meta {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        color: #e2e8f0;
                        font-size: 14px;
                    }
                    .lmd-delegation-viewer-counter {
                        font-weight: 700;
                        letter-spacing: .04em;
                    }
                    .lmd-delegation-viewer-hint {
                        color: #cbd5e1;
                    }
                    .lmd-delegation-description {
                        padding: 10px 22px 24px;
                    }
                    .lmd-delegation-description h3 {
                        margin: 0 0 10px;
                        font-size: 13px;
                        color: var(--lmd-muted);
                        text-transform: uppercase;
                        letter-spacing: .08em;
                    }
                    .lmd-delegation-prose,
                    .lmd-delegation-empty {
                        margin: 0;
                        padding: 18px 18px 20px;
                        border-radius: 18px;
                        background: #f8fafc;
                        border: 1px solid var(--lmd-border);
                        font-size: 15px;
                    }
                    .lmd-delegation-empty {
                        color: var(--lmd-muted);
                    }
                    .lmd-delegation-form {
                        padding: 22px;
                        display: grid;
                        gap: 18px;
                    }
                    .lmd-delegation-field {
                        display: grid;
                        gap: 8px;
                    }
                    .lmd-delegation-label {
                        font-size: 12px;
                        font-weight: 800;
                        color: var(--lmd-muted);
                        letter-spacing: .08em;
                        text-transform: uppercase;
                    }
                    .lmd-delegation-field input,
                    .lmd-delegation-field select,
                    .lmd-delegation-field textarea {
                        width: 100%;
                        border: 1px solid var(--lmd-border-strong);
                        border-radius: 14px;
                        background: #fff;
                        color: var(--lmd-text);
                        font: inherit;
                        padding: 14px 16px;
                        outline: none;
                        transition: border-color .2s ease, box-shadow .2s ease;
                    }
                    .lmd-delegation-field input:focus,
                    .lmd-delegation-field select:focus,
                    .lmd-delegation-field textarea:focus {
                        border-color: #93c5fd;
                        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
                    }
                    .lmd-delegation-field textarea {
                        min-height: 280px;
                        resize: vertical;
                    }
                    .lmd-delegation-estimates {
                        display: grid;
                        grid-template-columns: repeat(3, minmax(0, 1fr));
                        gap: 14px;
                    }
                    .lmd-delegation-input-wrap {
                        position: relative;
                    }
                    .lmd-delegation-input-wrap input {
                        padding-right: 38px;
                    }
                    .lmd-delegation-currency {
                        position: absolute;
                        top: 50%;
                        right: 14px;
                        transform: translateY(-50%);
                        color: var(--lmd-muted);
                        font-size: 13px;
                        font-weight: 700;
                    }
                    .lmd-delegation-actions {
                        display: flex;
                        justify-content: flex-end;
                        padding-top: 6px;
                    }
                    .lmd-delegation-actions button {
                        border: none;
                        border-radius: 14px;
                        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                        color: #fff;
                        font: inherit;
                        font-weight: 700;
                        padding: 14px 20px;
                        cursor: pointer;
                        box-shadow: 0 12px 24px rgba(37, 99, 235, 0.22);
                    }
                    .lmd-delegation-actions button:hover {
                        filter: brightness(1.03);
                    }
                    @media (max-width: 980px) {
                        .lmd-delegation-grid {
                            grid-template-columns: 1fr;
                        }
                        .lmd-delegation-estimates {
                            grid-template-columns: 1fr;
                        }
                    }
                    @media (max-width: 640px) {
                        .lmd-delegation-shell {
                            padding: 24px 14px 40px;
                        }
                        .lmd-delegation-panel-head,
                        .lmd-delegation-form,
                        .lmd-delegation-request-title,
                        .lmd-delegation-description,
                        .lmd-delegation-photos {
                            padding-left: 16px;
                            padding-right: 16px;
                        }
                    }
        CSS;
    }

    private static function get_delegation_script()
    {
        return <<<'JS'
                    (function () {
                        var viewer = document.getElementById('lmd-delegation-viewer');
                        if (!viewer) {
                            return;
                        }
                        var triggers = Array.prototype.slice.call(document.querySelectorAll('.lmd-delegation-photo[data-photo-index]'));
                        if (!triggers.length) {
                            return;
                        }
                        var stage = viewer.querySelector('.lmd-delegation-viewer-stage');
                        var image = viewer.querySelector('.lmd-delegation-viewer-image');
                        var counter = viewer.querySelector('.lmd-delegation-viewer-counter');
                        var prev = viewer.querySelector('.lmd-delegation-viewer-prev');
                        var next = viewer.querySelector('.lmd-delegation-viewer-next');
                        var close = viewer.querySelector('.lmd-delegation-viewer-close');
                        var urls = triggers.map(function (node) {
                            return node.getAttribute('href') || node.getAttribute('data-photo-url') || '';
                        }).filter(Boolean);
                        if (!urls.length) {
                            return;
                        }
                        var currentIndex = 0;

                        function updateCounter() {
                            if (counter) {
                                counter.textContent = (currentIndex + 1) + ' / ' + urls.length;
                            }
                            if (prev) {
                                prev.disabled = currentIndex <= 0;
                            }
                            if (next) {
                                next.disabled = currentIndex >= urls.length - 1;
                            }
                        }

                        function show(index) {
                            if (index < 0 || index >= urls.length) {
                                return;
                            }
                            currentIndex = index;
                            image.src = urls[currentIndex];
                            updateCounter();
                        }

                        function open(index) {
                            show(index);
                            viewer.classList.add('open');
                            document.body.style.overflow = 'hidden';
                        }

                        function closeViewer() {
                            viewer.classList.remove('open');
                            document.body.style.overflow = '';
                        }

                        triggers.forEach(function (trigger, index) {
                            trigger.addEventListener('click', function (event) {
                                event.preventDefault();
                                open(index);
                            });
                        });

                        if (close) {
                            close.addEventListener('click', function () {
                                closeViewer();
                            });
                        }
                        if (prev) {
                            prev.addEventListener('click', function (event) {
                                event.stopPropagation();
                                show(currentIndex - 1);
                            });
                        }
                        if (next) {
                            next.addEventListener('click', function (event) {
                                event.stopPropagation();
                                show(currentIndex + 1);
                            });
                        }
                        viewer.addEventListener('click', function (event) {
                            if (event.target === viewer) {
                                closeViewer();
                            }
                        });
                        if (stage) {
                            stage.addEventListener('click', function (event) {
                                event.stopPropagation();
                            });
                        }
                        document.addEventListener('keydown', function (event) {
                            if (!viewer.classList.contains('open')) {
                                return;
                            }
                            if (event.key === 'Escape') {
                                closeViewer();
                                return;
                            }
                            if (event.key === 'ArrowLeft') {
                                event.preventDefault();
                                show(currentIndex - 1);
                            }
                            if (event.key === 'ArrowRight') {
                                event.preventDefault();
                                show(currentIndex + 1);
                            }
                        });
                    })();
        JS;
    }

    private static function render_delegation_page($estimation, $args = [])
    {
        $args = wp_parse_args($args, [
            "standalone" => true,
            "token" => "",
            "feedback" => null,
        ]);
        $id = (int) $estimation->id;
        $request_title = self::build_request_title($estimation);
        $feedback = is_array($args["feedback"] ?? null)
            ? $args["feedback"]
            : null;
        $token = (string) ($args["token"] ?? "");
        $photos = [];
        if (!empty($estimation->photos)) {
            $decoded = json_decode($estimation->photos, true);
            $photos = is_array($decoded)
                ? $decoded
                : (is_string($estimation->photos)
                    ? [$estimation->photos]
                    : []);
        }
        $upload = wp_upload_dir();
        $baseurl = $upload["baseurl"];
        $basedir = $upload["basedir"];
        $photo_url_fn = function ($path) use ($baseurl, $basedir) {
            if (is_array($path)) {
                $path = reset($path);
            }
            if (!$path || !is_string($path)) {
                return "";
            }
            if (strpos($path, "http") === 0 || strpos($path, "//") === 0) {
                return $path;
            }
            $full =
                strpos($path, $basedir) === 0
                    ? $path
                    : $basedir .
                        "/" .
                        ltrim(str_replace("\\", "/", $path), "/");
            return file_exists($full)
                ? str_replace($basedir, $baseurl, $full)
                : $baseurl . "/" . ltrim(str_replace("\\", "/", $path), "/");
        };
        $form_values = [
            "avis_titre" => "",
            "avis_text" => "",
            "estimate_low" => "",
            "prix_reserve" => "",
            "estimate_high" => "",
            "avis_interet" => "",
        ];
        if (
            ($_SERVER["REQUEST_METHOD"] ?? "") === "POST" &&
            !empty($_POST["lmd_delegation_submit"])
        ) {
            $form_values = [
                "avis_titre" => sanitize_text_field(
                    wp_unslash($_POST["avis_titre"] ?? ""),
                ),
                "avis_text" => sanitize_textarea_field(
                    wp_unslash($_POST["avis_text"] ?? ""),
                ),
                "estimate_low" => self::format_decimal(
                    self::parse_decimal($_POST["estimate_low"] ?? null),
                ),
                "prix_reserve" => self::format_decimal(
                    self::parse_decimal($_POST["prix_reserve"] ?? null),
                ),
                "estimate_high" => self::format_decimal(
                    self::parse_decimal($_POST["estimate_high"] ?? null),
                ),
                "avis_interet" => sanitize_text_field(
                    wp_unslash($_POST["avis_interet"] ?? ""),
                ),
            ];
        }
        $interest_options = self::get_interest_options();
        $page_html = function () use (
            $id,
            $request_title,
            $feedback,
            $photos,
            $photo_url_fn,
            $estimation,
            $token,
            $form_values,
            $interest_options,
        ) {
            ?>
        <div class="lmd-delegation-shell">
            <header class="lmd-delegation-header">
                <div class="lmd-delegation-kicker">Deuxième avis externe</div>
                <h1><?php echo esc_html($request_title); ?></h1>
                <p>Consultez la demande puis saisissez votre avis directement dans ce formulaire.</p>
            </header>

            <?php if (!empty($feedback["message"])): ?>
            <div class="lmd-delegation-notice lmd-delegation-notice--<?php echo esc_attr(
                $feedback["type"] ?? "info",
            ); ?>">
                <?php echo esc_html($feedback["message"]); ?>
            </div>
            <?php endif; ?>

            <div class="lmd-delegation-grid">
                <section class="lmd-delegation-panel lmd-delegation-panel--request">
                    <div class="lmd-delegation-panel-head">
                        <span class="lmd-delegation-panel-eyebrow">Demande</span>
                        <h2>Objet à expertiser</h2>
                    </div>
                    <div class="lmd-delegation-request-title">Estimation #<?php echo (int) $id; ?></div>
                    <?php if (!empty($photos)): ?>
                    <div class="lmd-delegation-photos">
                        <?php $photo_index = 0; ?>
                        <?php foreach ($photos as $p):

                            $url = $photo_url_fn($p);
                            if (!$url) {
                                continue;
                            }
                            ?>
                        <a href="<?php echo esc_url(
                            $url,
                        ); ?>" target="_blank" rel="noopener" class="lmd-delegation-photo" data-photo-index="<?php echo (int) $photo_index; ?>" data-photo-url="<?php echo esc_url(
    $url,
); ?>" aria-label="Ouvrir l'image en grand">
                            <img src="<?php echo esc_url($url); ?>" alt="" />
                        </a>
                        <?php
                            $photo_index++;
                        endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="lmd-delegation-description">
                        <h3>Description</h3>
                        <?php if (!empty($estimation->description)): ?>
                        <div class="lmd-delegation-prose"><?php echo nl2br(
                            esc_html(wp_unslash($estimation->description)),
                        ); ?></div>
                        <?php else: ?>
                        <p class="lmd-delegation-empty">Aucune description fournie.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="lmd-delegation-panel lmd-delegation-panel--form">
                    <div class="lmd-delegation-panel-head">
                        <span class="lmd-delegation-panel-eyebrow">Avis externe</span>
                        <h2>Formulaire d’expertise</h2>
                    </div>
                    <form method="post" class="lmd-delegation-form">
                        <input type="hidden" name="lmd_delegation_submit" value="1" />
                        <input type="hidden" name="lmd_delegation_token" value="<?php echo esc_attr(
                            $token,
                        ); ?>" />

                        <label class="lmd-delegation-field">
                            <span class="lmd-delegation-label">Titre de l’avis</span>
                            <input
                                type="text"
                                name="avis_titre"
                                value="<?php echo esc_attr(
                                    $form_values["avis_titre"],
                                ); ?>"
                                placeholder="Ex. Fauteuil de réalisateur pliable"
                            />
                        </label>

                        <label class="lmd-delegation-field">
                            <span class="lmd-delegation-label">Avis</span>
                            <textarea
                                name="avis_text"
                                rows="12"
                                placeholder="Saisissez ici votre analyse, vos remarques et vos réserves éventuelles."
                            ><?php echo esc_textarea(
                                $form_values["avis_text"],
                            ); ?></textarea>
                        </label>

                        <label class="lmd-delegation-field">
                            <span class="lmd-delegation-label">Intérêt</span>
                            <select name="avis_interet">
                                <option value="">Choisir un niveau d’intérêt</option>
                                <?php foreach ($interest_options as $option):
                                    $option_slug = (string) ($option["slug"] ?? "");
                                    $option_name = (string) ($option["name"] ?? $option_slug);
                                    if ($option_slug === "") {
                                        continue;
                                    }
                                    ?>
                                <option value="<?php echo esc_attr($option_slug); ?>" <?php selected(
    $form_values["avis_interet"],
    $option_slug,
); ?>><?php echo esc_html($option_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="lmd-delegation-estimates">
                            <label class="lmd-delegation-field">
                                <span class="lmd-delegation-label">Estimation basse</span>
                                <div class="lmd-delegation-input-wrap">
                                    <input type="text" inputmode="decimal" name="estimate_low" value="<?php echo esc_attr(
                                        $form_values["estimate_low"],
                                    ); ?>" placeholder="0" />
                                    <span class="lmd-delegation-currency">€</span>
                                </div>
                            </label>

                            <label class="lmd-delegation-field">
                                <span class="lmd-delegation-label">Prix de réserve</span>
                                <div class="lmd-delegation-input-wrap">
                                    <input type="text" inputmode="decimal" name="prix_reserve" value="<?php echo esc_attr(
                                        $form_values["prix_reserve"],
                                    ); ?>" placeholder="0" />
                                    <span class="lmd-delegation-currency">€</span>
                                </div>
                            </label>

                            <label class="lmd-delegation-field">
                                <span class="lmd-delegation-label">Estimation haute</span>
                                <div class="lmd-delegation-input-wrap">
                                    <input type="text" inputmode="decimal" name="estimate_high" value="<?php echo esc_attr(
                                        $form_values["estimate_high"],
                                    ); ?>" placeholder="0" />
                                    <span class="lmd-delegation-currency">€</span>
                                </div>
                            </label>
                        </div>

                        <div class="lmd-delegation-actions">
                            <button type="submit">Enregistrer l’avis</button>
                        </div>
                    </form>
                </section>
            </div>

            <?php if (!empty($photos)): ?>
            <div class="lmd-delegation-viewer" id="lmd-delegation-viewer" aria-hidden="true">
                <div class="lmd-delegation-viewer-dialog" role="dialog" aria-modal="true" aria-label="Visionneuse des photos">
                    <div class="lmd-delegation-viewer-stage">
                        <button type="button" class="lmd-delegation-viewer-close" aria-label="Fermer">&times;</button>
                        <button type="button" class="lmd-delegation-viewer-nav lmd-delegation-viewer-prev" aria-label="Image précédente">&#8249;</button>
                        <img class="lmd-delegation-viewer-image" src="" alt="" />
                        <button type="button" class="lmd-delegation-viewer-nav lmd-delegation-viewer-next" aria-label="Image suivante">&#8250;</button>
                    </div>
                    <div class="lmd-delegation-viewer-meta">
                        <div class="lmd-delegation-viewer-counter"></div>
                        <div class="lmd-delegation-viewer-hint">Cliquez hors de l'image ou appuyez sur Echap pour fermer.</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
            <?php
        };
        ?>
        <?php if (!empty($args["standalone"])): ?>
        <!DOCTYPE html>
        <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html(
            $request_title,
        ); ?></title>
        <style>body{margin:0;background:linear-gradient(180deg,#f8fbff 0%,#eef4fb 100%);}</style>
        <style><?php echo self::get_delegation_styles(); ?></style>
        </head><body>
        <?php $page_html(); ?>
        <script><?php echo self::get_delegation_script(); ?></script>
        </body></html>
        <?php else: ?>
        <div class="lmd-delegation-shortcode">
            <style><?php echo self::get_delegation_styles(); ?></style>
            <?php $page_html(); ?>
            <script><?php echo self::get_delegation_script(); ?></script>
        </div>
        <?php endif; ?>
        <?php
    }
}
