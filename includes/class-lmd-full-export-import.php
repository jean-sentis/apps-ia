<?php
/**
 * Export / Import complet — Copie exacte pour simulation et étude
 * Tables, options, photos.
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Full_Export_Import
{
    private $wpdb;
    private $prefix;
    private static $TABLES = [
        "estimations",
        "tags",
        "estimation_tags",
        "pricing_tiers",
        "client_pricing_overrides",
        "api_usage",
        "ai_error_reports",
        "cp_settings",
        "formules",
        "delegation_recipients",
        "delegation_tokens",
        "activity_log",
    ];
    private static $OPTIONS = [
        "lmd_free_estimations_granted",
        "lmd_analysis_pricing_tiers",
        "lmd_service_start_date",
        "lmd_consumption_monthly_email",
        "lmd_consumption_monthly_enabled",
        "lmd_consumption_summary",
        "lmd_custom_categories",
        "lmd_gemini_model",
        "lmd_gemini_image_model",
    ];
    private static $OPTIONS_SENSITIVE = [
        "lmd_gemini_key",
        "lmd_serpapi_key",
        "lmd_firecrawl_key",
        "lmd_imgbb_key",
    ];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . "lmd_";
    }

    /**
     * Exporte tout dans un fichier ZIP.
     *
     * @param int|null $site_id En multisite, site à exporter ; null = site actuel
     * @param bool $include_photos Inclure les fichiers photos
     * @param bool $include_api_keys Inclure les clés API (déconseillé)
     * @return string Chemin du fichier ZIP créé
     */
    public function export(
        $site_id = null,
        $include_photos = true,
        $include_api_keys = false,
    ) {
        $origin_blog_id = get_current_blog_id();
        $site_id = $site_id ?? $origin_blog_id;
        $switched_to_export_site = false;
        $tmp_dir = null;

        try {
            if (is_multisite() && $site_id !== $origin_blog_id) {
                switch_to_blog($site_id);
                $switched_to_export_site = true;
            }

            $prefix = $this->wpdb->prefix . "lmd_";
            $upload_dir = wp_upload_dir();
            $tmp_dir =
                $upload_dir["basedir"] .
                "/lmd-export-" .
                wp_generate_password(8, false);
            wp_mkdir_p($tmp_dir);

            $data = [
                "version" => LMD_VERSION,
                "exported_at" => current_time("mysql"),
                "site_id" => $site_id,
                "site_name" => get_bloginfo("name"),
                "tables" => [],
                "options" => [],
            ];

            foreach (self::$TABLES as $base) {
                $table = $prefix . $base;
                if (
                    $this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table
                ) {
                    continue;
                }
                $rows = $this->wpdb->get_results(
                    "SELECT * FROM $table WHERE site_id = " . (int) $site_id,
                    ARRAY_A,
                );
                if (
                    in_array(
                        $base,
                        ["estimation_tags", "delegation_tokens"],
                        true,
                    )
                ) {
                    $rows = $this->wpdb->get_results(
                        "SELECT * FROM $table",
                        ARRAY_A,
                    );
                }
                if ($base === "delegation_tokens") {
                    $est_ids = array_unique(
                        array_column($rows, "estimation_id"),
                    );
                    $est_in_site = $this->wpdb->get_col(
                        "SELECT id FROM {$prefix}estimations WHERE site_id = " .
                            (int) $site_id,
                    );
                    $est_in_site = array_flip($est_in_site);
                    $rows = array_filter($rows, function ($r) use (
                        $est_in_site,
                    ) {
                        return isset($est_in_site[$r["estimation_id"]]);
                    });
                }
                if ($base === "estimation_tags") {
                    $tag_ids = $this->wpdb->get_col(
                        "SELECT id FROM {$prefix}tags WHERE site_id = " .
                            (int) $site_id,
                    );
                    $est_ids = $this->wpdb->get_col(
                        "SELECT id FROM {$prefix}estimations WHERE site_id = " .
                            (int) $site_id,
                    );
                    $rows = array_filter($rows, function ($r) use (
                        $tag_ids,
                        $est_ids,
                    ) {
                        return in_array($r["tag_id"], $tag_ids) &&
                            in_array($r["estimation_id"], $est_ids);
                    });
                }
                $data["tables"][$base] = $rows;
            }

            foreach (
                array_merge(
                    self::$OPTIONS,
                    $include_api_keys ? self::$OPTIONS_SENSITIVE : [],
                )
                as $opt
            ) {
                $val = get_option($opt, "__NOT_SET__");
                if ($val !== "__NOT_SET__") {
                    $data["options"][$opt] = $val;
                }
            }

            if (is_multisite() && $site_id !== 1) {
                $switched_to_parent = false;
                if (get_current_blog_id() !== 1) {
                    switch_to_blog(1);
                    $switched_to_parent = true;
                }
                try {
                    foreach (
                        [
                            "lmd_consumption_monthly_email",
                            "lmd_consumption_monthly_enabled",
                        ]
                        as $opt
                    ) {
                        $val = get_option($opt, "__NOT_SET__");
                        if ($val !== "__NOT_SET__") {
                            $data["options"][$opt] = $val;
                        }
                    }
                } finally {
                    if ($switched_to_parent) {
                        restore_current_blog();
                    }
                }
            }

            $photo_map = [];
            if ($include_photos && !empty($data["tables"]["estimations"])) {
                $photos_dir = $tmp_dir . "/photos";
                wp_mkdir_p($photos_dir);
                foreach ($data["tables"]["estimations"] as $i => $est) {
                    $photos = [];
                    if (!empty($est["photos"])) {
                        $decoded = json_decode($est["photos"], true);
                        $photos = is_array($decoded)
                            ? $decoded
                            : (is_string($est["photos"])
                                ? [$est["photos"]]
                                : []);
                    }
                    $new_urls = [];
                    foreach ($photos as $idx => $url) {
                        if (!is_string($url)) {
                            $url = is_array($url) ? (reset($url) ?: "") : "";
                        }
                        if (!$url) {
                            continue;
                        }
                        $path = $this->url_to_path($url);
                        if ($path && file_exists($path)) {
                            $ext = pathinfo($path, PATHINFO_EXTENSION) ?: "jpg";
                            $fname =
                                "est_" . $est["id"] . "_" . $idx . "." . $ext;
                            $dest = $photos_dir . "/" . $fname;
                            @copy($path, $dest);
                            $photo_map[$url] = "photos/" . $fname;
                            $new_urls[] = $url;
                        } else {
                            $new_urls[] = $url;
                        }
                    }
                    $data["tables"]["estimations"][$i]["photos"] = !empty(
                        $new_urls
                    )
                        ? wp_json_encode($new_urls)
                        : $est["photos"];
                }
                $data["photo_map"] = $photo_map;
            }

            file_put_contents(
                $tmp_dir . "/data.json",
                wp_json_encode(
                    $data,
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
                ),
            );

            $zip_path =
                $upload_dir["basedir"] .
                "/lmd-copie-client-" .
                date("Y-m-d-His") .
                ".zip";
            $zip = new ZipArchive();
            if (
                $zip->open(
                    $zip_path,
                    ZipArchive::CREATE | ZipArchive::OVERWRITE,
                ) !== true
            ) {
                throw new Exception("Impossible de créer le fichier ZIP");
            }
            $this->add_dir_to_zip($tmp_dir, $zip, strlen($tmp_dir) + 1);
            $zip->close();

            $this->rmdir_recursive($tmp_dir);
            $tmp_dir = null;

            return $zip_path;
        } finally {
            if ($tmp_dir && is_dir($tmp_dir)) {
                $this->rmdir_recursive($tmp_dir);
            }
            if ($switched_to_export_site) {
                restore_current_blog();
            }
        }
    }

    private function url_to_path($url)
    {
        $upload = wp_upload_dir();
        $baseurl = $upload["baseurl"];
        $basedir = $upload["basedir"];
        if (strpos($url, $baseurl) === 0) {
            return $basedir . substr($url, strlen($baseurl));
        }
        if (strpos($url, "/wp-content/uploads/") !== false) {
            return ABSPATH . ltrim(parse_url($url, PHP_URL_PATH), "/");
        }
        return null;
    }

    private function add_dir_to_zip($dir, ZipArchive $zip, $strip_len)
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($files as $f) {
            if (!$f->isDir()) {
                $path = $f->getRealPath();
                $zip->addFile($path, substr($path, $strip_len));
            }
        }
    }

    private function rmdir_recursive($dir)
    {
        $files = array_diff(scandir($dir), [".", ".."]);
        foreach ($files as $f) {
            $path = $dir . "/" . $f;
            is_dir($path) ? $this->rmdir_recursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Importe depuis un fichier ZIP.
     *
     * @param string $zip_path Chemin du ZIP
     * @param bool $replace Remplacer les données existantes (sinon fusionner)
     * @return array ['tables' => int, 'options' => int, 'photos' => int, 'errors' => []]
     */
    public function import($zip_path, $replace = false)
    {
        $upload = wp_upload_dir();
        $tmp_dir =
            $upload["basedir"] .
            "/lmd-import-" .
            wp_generate_password(8, false);
        wp_mkdir_p($tmp_dir);

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            throw new Exception("Fichier ZIP invalide");
        }
        $zip->extractTo($tmp_dir);
        $zip->close();

        $data_file = $tmp_dir . "/data.json";
        if (!file_exists($data_file)) {
            $this->rmdir_recursive($tmp_dir);
            throw new Exception('Fichier data.json manquant dans l\'archive');
        }

        $data = json_decode(file_get_contents($data_file), true);
        if (!is_array($data) || empty($data["tables"])) {
            $this->rmdir_recursive($tmp_dir);
            throw new Exception("Données invalides");
        }

        $site_id = get_current_blog_id();
        $prefix = $this->wpdb->prefix . "lmd_";
        $stats = ["tables" => 0, "options" => 0, "photos" => 0, "errors" => []];
        $photo_map = $data["photo_map"] ?? [];
        $id_map = []; // ancien id -> nouveau id pour estimations et tags

        if ($replace) {
            foreach (["estimation_tags", "estimations", "tags"] as $t) {
                $table = $prefix . $t;
                if (
                    $this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table
                ) {
                    if ($t === "estimation_tags") {
                        $this->wpdb->query(
                            "DELETE et FROM $table et INNER JOIN {$prefix}tags t ON et.tag_id = t.id WHERE t.site_id = " .
                                (int) $site_id,
                        );
                        $this->wpdb->query(
                            "DELETE et FROM $table et INNER JOIN {$prefix}estimations e ON et.estimation_id = e.id WHERE e.site_id = " .
                                (int) $site_id,
                        );
                    } else {
                        $this->wpdb->delete(
                            $table,
                            ["site_id" => $site_id],
                            ["%d"],
                        );
                    }
                }
            }
        }

        $tag_id_map = [];
        if (!empty($data["tables"]["tags"])) {
            foreach ($data["tables"]["tags"] as $row) {
                $old_id = $row["id"] ?? null;
                unset($row["id"]);
                $row["site_id"] = $site_id;
                $this->wpdb->insert($prefix . "tags", $row, null);
                if ($old_id && $this->wpdb->insert_id) {
                    $tag_id_map[$old_id] = $this->wpdb->insert_id;
                }
            }
        }

        $est_id_map = [];
        if (!empty($data["tables"]["estimations"])) {
            foreach ($data["tables"]["estimations"] as $row) {
                $old_id = $row["id"];
                unset($row["id"]);
                $row["site_id"] = $site_id;

                if (!empty($row["photos"]) && !empty($photo_map)) {
                    $photos = json_decode($row["photos"], true);
                    if (is_array($photos)) {
                        $upload_dir = wp_upload_dir();
                        $new_photos = [];
                        foreach ($photos as $url) {
                            $rel = $photo_map[$url] ?? null;
                            if ($rel && file_exists($tmp_dir . "/" . $rel)) {
                                $fname = basename($rel);
                                $dest_path = $upload_dir["path"] . "/" . $fname;
                                $dest_url = $upload_dir["url"] . "/" . $fname;
                                if (@copy($tmp_dir . "/" . $rel, $dest_path)) {
                                    $new_photos[] = $dest_url;
                                    $stats["photos"]++;
                                } else {
                                    $new_photos[] = $url;
                                }
                            } else {
                                $new_photos[] = $url;
                            }
                        }
                        $row["photos"] = wp_json_encode($new_photos);
                    }
                }

                $this->wpdb->insert($prefix . "estimations", $row, null);
                if ($this->wpdb->insert_id) {
                    $est_id_map[$old_id] = $this->wpdb->insert_id;
                }
            }
        }

        if (!empty($data["tables"]["estimation_tags"]) && !empty($est_id_map)) {
            foreach ($data["tables"]["estimation_tags"] as $row) {
                $new_est = $est_id_map[$row["estimation_id"]] ?? null;
                $new_tag = $tag_id_map[$row["tag_id"]] ?? null;
                if ($new_est && $new_tag) {
                    $this->wpdb->replace(
                        $prefix . "estimation_tags",
                        [
                            "estimation_id" => $new_est,
                            "tag_id" => $new_tag,
                        ],
                        ["%d", "%d"],
                    );
                    $stats["tables"]++;
                }
            }
        }

        foreach (
            [
                "pricing_tiers",
                "client_pricing_overrides",
                "cp_settings",
                "formules",
                "delegation_recipients",
            ]
            as $t
        ) {
            if (empty($data["tables"][$t])) {
                continue;
            }
            $table = $prefix . $t;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                continue;
            }
            foreach ($data["tables"][$t] as $row) {
                unset($row["id"]);
                $row["site_id"] = $site_id;
                if ($t === "cp_settings" && isset($row["user_id"])) {
                    $row["user_id"] = get_current_user_id();
                }
                if ($t === "formules" && isset($row["user_id"])) {
                    $row["user_id"] = get_current_user_id();
                }
                $this->wpdb->insert($table, $row, null);
                $stats["tables"]++;
            }
        }

        foreach (["api_usage", "ai_error_reports", "activity_log"] as $t) {
            if (empty($data["tables"][$t])) {
                continue;
            }
            $table = $prefix . $t;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                continue;
            }
            foreach ($data["tables"][$t] as $row) {
                unset($row["id"]);
                $row["site_id"] = $site_id;
                if (
                    isset($row["estimation_id"]) &&
                    isset($est_id_map[$row["estimation_id"]])
                ) {
                    $row["estimation_id"] = $est_id_map[$row["estimation_id"]];
                }
                $this->wpdb->insert($table, $row, null);
                $stats["tables"]++;
            }
        }

        if (
            !empty($data["tables"]["delegation_tokens"]) &&
            !empty($est_id_map)
        ) {
            $table = $prefix . "delegation_tokens";
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                foreach ($data["tables"]["delegation_tokens"] as $row) {
                    $new_est = $est_id_map[$row["estimation_id"]] ?? null;
                    if (!$new_est) {
                        continue;
                    }
                    unset($row["id"]);
                    $row["estimation_id"] = $new_est;
                    $row["token"] = wp_generate_password(32, false);
                    $this->wpdb->insert($table, $row, null);
                    $stats["tables"]++;
                }
            }
        }

        foreach ($data["options"] ?? [] as $opt => $val) {
            update_option($opt, $val);
            $stats["options"]++;
        }

        $this->rmdir_recursive($tmp_dir);
        return $stats;
    }

    /**
     * Liste des sites disponibles pour l'export (multisite).
     */
    public function get_exportable_sites()
    {
        if (!is_multisite()) {
            return [
                ["id" => get_current_blog_id(), "name" => get_bloginfo("name")],
            ];
        }
        $sites = [];
        foreach (get_sites(["number" => 500]) as $s) {
            $sites[] = [
                "id" => (int) $s->blog_id,
                "name" => get_blog_option(
                    $s->blog_id,
                    "blogname",
                    "Site " . $s->blog_id,
                ),
            ];
        }
        return $sites;
    }
}
