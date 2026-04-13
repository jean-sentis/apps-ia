<?php
/**
 * Synchronisation du calendrier apps-ia avec les CPT de ventes/expertises.
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit;
}

class LMD_Calendar_Sync
{
    const SALE_SYNC_SOURCE = "vente_post";

    private static $did_sync_sales = false;

    /**
     * Synchronise les ventes à venir du CPT vente vers les tags date_vente.
     */
    public static function sync_upcoming_sales_tags()
    {
        if (self::$did_sync_sales) {
            return;
        }
        self::$did_sync_sales = true;

        if (!post_type_exists("vente")) {
            return;
        }

        global $wpdb;
        $site_id = get_current_blog_id();
        $table_tags = $wpdb->prefix . "lmd_tags";
        $table_links = $wpdb->prefix . "lmd_estimation_tags";
        $tag_cols = $wpdb->get_col("DESCRIBE $table_tags");
        if (
            !in_array("sync_source", $tag_cols, true) ||
            !in_array("sync_ref", $tag_cols, true)
        ) {
            return;
        }

        $sales = self::get_upcoming_sales();
        $existing_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, slug, sync_ref FROM $table_tags WHERE site_id = %d AND type = 'date_vente' AND sync_source = %s",
                $site_id,
                self::SALE_SYNC_SOURCE,
            ),
        );

        $existing_by_ref = [];
        foreach ($existing_rows as $row) {
            $existing_by_ref[(string) $row->sync_ref] = $row;
        }

        $active_refs = [];
        foreach ($sales as $sale) {
            $ref = (string) $sale["post_id"];
            $active_refs[$ref] = true;
            $existing = $existing_by_ref[$ref] ?? null;
            $tag_id = $existing ? (int) $existing->id : 0;
            $slug = self::build_unique_sale_slug($sale["date"], $tag_id);

            if ($tag_id > 0) {
                if (
                    (string) $existing->name !== $sale["title"] ||
                    (string) $existing->slug !== $slug
                ) {
                    $wpdb->update(
                        $table_tags,
                        [
                            "name" => $sale["title"],
                            "slug" => $slug,
                        ],
                        ["id" => $tag_id],
                        ["%s", "%s"],
                        ["%d"],
                    );
                }
                continue;
            }

            $wpdb->insert(
                $table_tags,
                [
                    "site_id" => $site_id,
                    "name" => $sale["title"],
                    "type" => "date_vente",
                    "slug" => $slug,
                    "sync_source" => self::SALE_SYNC_SOURCE,
                    "sync_ref" => $ref,
                ],
                ["%d", "%s", "%s", "%s", "%s", "%s"],
            );
        }

        foreach ($existing_rows as $row) {
            $ref = (string) $row->sync_ref;
            if (isset($active_refs[$ref])) {
                continue;
            }

            $tag_id = (int) $row->id;
            $linked_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_links WHERE tag_id = %d",
                    $tag_id,
                ),
            );

            if ($linked_count > 0) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE $table_tags SET sync_source = NULL, sync_ref = NULL WHERE id = %d",
                        $tag_id,
                    ),
                );
                continue;
            }

            $wpdb->delete($table_tags, ["id" => $tag_id], ["%d"]);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_upcoming_expertise_events()
    {
        if (!post_type_exists("journee_dexpertise")) {
            return [];
        }

        $events = [];
        $posts = get_posts([
            "post_type" => "journee_dexpertise",
            "post_status" => "publish",
            "posts_per_page" => 300,
            "orderby" => "meta_value",
            "order" => "ASC",
            "meta_key" => "expertise_date",
            "fields" => "ids",
            "no_found_rows" => true,
            "update_post_meta_cache" => false,
            "update_post_term_cache" => false,
        ]);

        foreach ($posts as $post_id) {
            $raw_date = get_post_meta($post_id, "expertise_date", true);
            $date = self::normalize_event_date($raw_date);
            if ($date === "") {
                continue;
            }

            $events[] = [
                "id" => (int) $post_id,
                "title" => get_the_title($post_id),
                "date" => $date,
                "location" => (string) get_post_meta(
                    $post_id,
                    "expertise_lieu",
                    true,
                ),
            ];
        }

        usort($events, function ($a, $b) {
            $cmp = strcmp((string) $a["date"], (string) $b["date"]);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) $a["title"], (string) $b["title"]);
        });

        return $events;
    }

    private static function get_upcoming_sales()
    {
        $sales = [];
        $posts = get_posts([
            "post_type" => "vente",
            "post_status" => "publish",
            "posts_per_page" => 500,
            "orderby" => "meta_value",
            "order" => "ASC",
            "meta_key" => "vente_date",
            "fields" => "ids",
            "no_found_rows" => true,
            "update_post_meta_cache" => false,
            "update_post_term_cache" => false,
        ]);

        foreach ($posts as $post_id) {
            $raw_date = get_post_meta($post_id, "vente_date", true);
            $date = self::normalize_event_date($raw_date);
            if ($date === "") {
                continue;
            }
            $sales[] = [
                "post_id" => (int) $post_id,
                "title" => get_the_title($post_id),
                "date" => $date,
            ];
        }

        usort($sales, function ($a, $b) {
            $cmp = strcmp((string) $a["date"], (string) $b["date"]);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string) $a["title"], (string) $b["title"]);
        });

        return $sales;
    }

    private static function normalize_event_date($raw_date)
    {
        $raw_date = is_string($raw_date) ? trim($raw_date) : "";
        if ($raw_date === "") {
            return "";
        }

        $timestamp = strtotime($raw_date);
        if (!$timestamp) {
            return "";
        }

        $event_date = wp_date("Y-m-d", $timestamp);
        $today = current_time("Y-m-d");
        if ($event_date < $today) {
            return "";
        }

        return $event_date;
    }

    private static function build_unique_sale_slug($date, $exclude_id = 0)
    {
        global $wpdb;
        $table_tags = $wpdb->prefix . "lmd_tags";
        $site_id = get_current_blog_id();
        $base = substr((string) $date, 0, 10);
        $slug = $base;
        $suffix = 1;

        while (
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_tags WHERE site_id = %d AND type = 'date_vente' AND slug = %s AND id != %d",
                    $site_id,
                    $slug,
                    (int) $exclude_id,
                ),
            )
        ) {
            $suffix++;
            $slug = $base . "-" . $suffix;
        }

        return $slug;
    }
}
