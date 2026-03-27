<?php
/**
 * Gestion de la base de données - Schéma et migrations
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Database {

    private $wpdb;
    private $charset_collate;
    private $prefix;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->prefix = $wpdb->prefix . 'lmd_';
    }

    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $site_id = get_current_blog_id();

        $sql_estimations = "CREATE TABLE {$this->prefix}estimations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            client_name varchar(255) DEFAULT '',
            client_civility varchar(20) DEFAULT NULL,
            client_first_name varchar(255) DEFAULT NULL,
            client_email varchar(255) DEFAULT '',
            client_phone varchar(100) DEFAULT '',
            client_postal_code varchar(10) DEFAULT NULL,
            client_commune varchar(255) DEFAULT NULL,
            description text,
            status varchar(50) DEFAULT 'new',
            source varchar(50) DEFAULT 'admin',
            photos longtext,
            ai_analysis longtext,
            auctioneer_notes longtext,
            second_opinion longtext,
            estimate_low decimal(12,2) DEFAULT NULL,
            estimate_high decimal(12,2) DEFAULT NULL,
            prix_reserve decimal(12,2) DEFAULT NULL,
            avis1_estimate_low decimal(12,2) DEFAULT NULL,
            avis1_estimate_high decimal(12,2) DEFAULT NULL,
            avis1_prix_reserve decimal(12,2) DEFAULT NULL,
            avis2_estimate_low decimal(12,2) DEFAULT NULL,
            avis2_estimate_high decimal(12,2) DEFAULT NULL,
            avis2_prix_reserve decimal(12,2) DEFAULT NULL,
            auctioneer_decision varchar(50) DEFAULT NULL,
            delegated_to varchar(255) DEFAULT NULL,
            delegation_draft text,
            delegation_email varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $this->charset_collate;";
        dbDelta($sql_estimations);

        $sql_tags = "CREATE TABLE {$this->prefix}tags (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            slug varchar(100) NOT NULL,
            theme_vente_slug varchar(100) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY site_type_slug (site_id, type, slug),
            KEY site_id (site_id),
            KEY type (type)
        ) $this->charset_collate;";
        dbDelta($sql_tags);

        $sql_estimation_tags = "CREATE TABLE {$this->prefix}estimation_tags (
            estimation_id bigint(20) unsigned NOT NULL,
            tag_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (estimation_id, tag_id),
            KEY tag_id (tag_id)
        ) $this->charset_collate;";
        $table_et = $this->prefix . 'estimation_tags';
        // Évite l’erreur SQL « Multiple primary key » si la table existe déjà avec une clé primaire (dbDelta tente parfois un ALTER redondant).
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_et'") !== $table_et) {
            dbDelta($sql_estimation_tags);
        }

        $sql_pricing = "CREATE TABLE {$this->prefix}pricing_tiers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            min_amount decimal(12,2) NOT NULL DEFAULT 0,
            max_amount decimal(12,2) DEFAULT NULL,
            price_per_estimation decimal(10,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY site_id (site_id)
        ) $this->charset_collate;";
        dbDelta($sql_pricing);

        $sql_overrides = "CREATE TABLE {$this->prefix}client_pricing_overrides (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            client_email varchar(255) NOT NULL,
            price_per_estimation decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY site_email (site_id, client_email)
        ) $this->charset_collate;";
        dbDelta($sql_overrides);

        $sql_api_usage = "CREATE TABLE {$this->prefix}api_usage (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            estimation_id bigint(20) unsigned DEFAULT NULL,
            api_name varchar(50) NOT NULL,
            units int(11) NOT NULL DEFAULT 1,
            cost_usd decimal(10,6) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_api_month (site_id, api_name, created_at),
            KEY estimation_id (estimation_id)
        ) $this->charset_collate;";
        dbDelta($sql_api_usage);

        $sql_ai_errors = "CREATE TABLE {$this->prefix}ai_error_reports (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) NOT NULL DEFAULT 0,
            estimation_id bigint(20) unsigned NOT NULL,
            client_name varchar(255) DEFAULT '',
            description longtext,
            ai_summary text,
            ai_estimate_low decimal(12,2) DEFAULT NULL,
            ai_estimate_high decimal(12,2) DEFAULT NULL,
            ai_analysis_json longtext,
            user_explanation text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_created (site_id, created_at)
        ) $this->charset_collate;";
        dbDelta($sql_ai_errors);

        $this->ensure_tags_seeded();
        $this->ensure_pricing_ready();
    }

    public function ensure_tags_seeded() {
        $table = $this->prefix . 'tags';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $this->create_tables();
            return;
        }
        $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count === 0) {
            $this->insert_default_tags();
            return;
        }
        $this->ensure_message_tags_complete();
        $this->ensure_interet_tags_complete();
    }

    private function ensure_interet_tags_complete() {
        $site_id = get_current_blog_id();
        $categories = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
        $opts = $categories['interet']['options'] ?? [];
        foreach ($opts as $opt) {
            $slug = $opt['slug'] ?? '';
            $name = $opt['name'] ?? $slug;
            if (!$slug) continue;
            $exists = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT 1 FROM {$this->prefix}tags WHERE site_id = %d AND type = 'interet' AND slug = %s",
                $site_id, $slug
            ));
            if (!$exists) {
                $this->wpdb->insert($this->prefix . 'tags', [
                    'site_id' => $site_id, 'name' => $name, 'type' => 'interet', 'slug' => $slug
                ], ['%d', '%s', '%s', '%s']);
            }
        }
    }

    private function ensure_message_tags_complete() {
        $site_id = get_current_blog_id();
        $categories = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
        $opts = $categories['message']['options'] ?? [];
        foreach ($opts as $opt) {
            $slug = $opt['slug'] ?? '';
            $name = $opt['name'] ?? $slug;
            if (!$slug) continue;
            $exists = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT 1 FROM {$this->prefix}tags WHERE site_id = %d AND type = 'message' AND slug = %s",
                $site_id, $slug
            ));
            if (!$exists) {
                $this->wpdb->insert($this->prefix . 'tags', [
                    'site_id' => $site_id, 'name' => $name, 'type' => 'message', 'slug' => $slug
                ], ['%d', '%s', '%s', '%s']);
            }
        }
    }

    private function insert_default_tags() {
        $site_id = get_current_blog_id();
        $categories = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
        foreach ($categories as $type => $cat) {
            if (empty($cat['options'])) {
                continue;
            }
            foreach ($cat['options'] as $opt) {
                $slug = $opt['slug'] ?? '';
                $name = $opt['name'] ?? $slug;
                if (!$slug) {
                    continue;
                }
                $this->wpdb->insert(
                    $this->prefix . 'tags',
                    [
                        'site_id' => $site_id,
                        'name' => $name,
                        'type' => $type,
                        'slug' => $slug,
                    ],
                    ['%d', '%s', '%s', '%s']
                );
            }
        }
    }

    public function ensure_pricing_ready() {
        $table_tiers = $this->prefix . 'pricing_tiers';
        $table_overrides = $this->prefix . 'client_pricing_overrides';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table_tiers'") !== $table_tiers) {
            $this->create_tables();
            return;
        }
        $count = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM $table_tiers");
        if ($count > 0) {
            return;
        }
        $this->insert_default_pricing_tiers();
    }

    public function insert_default_pricing_tiers() {
        $site_id = get_current_blog_id();
        $tiers = [
            ['min' => 0, 'max' => 100, 'price' => 5],
            ['min' => 100, 'max' => 500, 'price' => 10],
            ['min' => 500, 'max' => 1000, 'price' => 15],
            ['min' => 1000, 'max' => 5000, 'price' => 25],
            ['min' => 5000, 'max' => null, 'price' => 50],
        ];
        foreach ($tiers as $t) {
            $this->wpdb->insert(
                $this->prefix . 'pricing_tiers',
                [
                    'site_id' => $site_id,
                    'min_amount' => $t['min'],
                    'max_amount' => $t['max'],
                    'price_per_estimation' => $t['price'],
                ],
                ['%d', '%f', '%f', '%f']
            );
        }
    }

    public function create_parent_access_code() {
        // Placeholder - codes d'accès multisite
    }

    public function create_child_access_code() {
        // Placeholder - codes d'accès multisite
    }

    public function get_estimation($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->prefix}estimations WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Rang de l'envoi pour le même expéditeur (1er, 2e, 3e...).
     * Même personne = même client_email (ou client_name si email vide).
     */
    public function get_sender_rank($estimation) {
        $site_id = get_current_blog_id();
        $table = $this->prefix . 'estimations';
        $email = trim((string) ($estimation->client_email ?? ''));
        $name = trim((string) ($estimation->client_name ?? ''));
        $created = $estimation->created_at ?? '';
        $id = (int) ($estimation->id ?? 0);
        if ($email) {
            $rank = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE site_id = %d AND client_email = %s AND (created_at < %s OR (created_at = %s AND id <= %d))",
                $site_id, $email, $created, $created, $id
            ));
        } elseif ($name) {
            $rank = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE site_id = %d AND (client_email = '' OR client_email IS NULL) AND client_name = %s AND (created_at < %s OR (created_at = %s AND id <= %d))",
                $site_id, $name, $created, $created, $id
            ));
        } else {
            return 0;
        }
        return $rank;
    }

    public function get_estimations($args = []) {
        $defaults = [
            'status' => '',
            'search' => '',
            'filter_message' => '',
            'filter_interet' => '',
            'filter_estimation' => '',
            'filter_theme_vente' => '',
            'filter_date_vente' => '',
            'filter_vendeur' => '',
            'filter_date_envoi_from' => '',
            'filter_date_envoi_to' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'pref_display_last_n' => 0,
            'pref_display_include_unanswered' => false,
            'pref_display_older_than_days' => 0,
            'pref_excluded_theme_slugs' => [],
        ];
        $args = wp_parse_args($args, $defaults);
        $site_id = get_current_blog_id();
        $e = $this->prefix . 'estimations';
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';

        $where = "e.site_id = %d";
        $params = [$site_id];

        if (!empty($args['status'])) {
            $where .= " AND e.status = %s";
            $params[] = $args['status'];
        }
        $tag_filters = [
            'message' => $args['filter_message'],
            'interet' => $args['filter_interet'],
            'estimation' => $args['filter_estimation'],
            'theme_vente' => $args['filter_theme_vente'],
            'date_vente' => $args['filter_date_vente'],
            'vendeur' => $args['filter_vendeur'],
        ];
        $estimation_paliers = function_exists('lmd_get_estimation_paliers') ? lmd_get_estimation_paliers() : [
            'moins_25' => ['max' => 25],
            'moins_100' => ['max' => 100],
            'moins_500' => ['max' => 500],
            'moins_1000' => ['max' => 1000],
            'moins_5000' => ['max' => 5000],
            'plus_5000' => ['min' => 5000],
        ];
        foreach ($tag_filters as $type => $val) {
            $slugs = is_array($val) ? array_filter(array_map('sanitize_text_field', $val)) : (empty($val) ? [] : [sanitize_text_field($val)]);
            if (!empty($slugs)) {
                if ($type === 'message' && in_array('en_retard', $slugs, true)) {
                    $other_slugs = array_values(array_diff($slugs, ['en_retard']));
                    $e_cols = $this->wpdb->get_col("DESCRIBE {$e}");
                    $ref_date = in_array('first_viewed_at', $e_cols, true) ? 'COALESCE(e.first_viewed_at, e.created_at)' : 'e.created_at';
                    $where .= " AND (";
                    $where .= " EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = %s AND t2.slug = 'en_retard')";
                    $params[] = $site_id;
                    $params[] = $type;
                    $where .= " OR ($ref_date < DATE_SUB(NOW(), INTERVAL 48 HOUR) AND NOT EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = %s AND t2.slug = 'repondu'))";
                    $params[] = $site_id;
                    $params[] = $type;
                    if (!empty($other_slugs)) {
                        $placeholders = implode(',', array_fill(0, count($other_slugs), '%s'));
                        $where .= " OR EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = %s AND t2.slug IN ($placeholders))";
                        $params[] = $site_id;
                        $params[] = $type;
                        $params = array_merge($params, $other_slugs);
                    }
                    $where .= ")";
                } elseif ($type === 'estimation') {
                    $tag_slugs = [];
                    $numeric_conds = [];
                    foreach ($slugs as $slug) {
                        $pal = $estimation_paliers[$slug] ?? null;
                        if ($pal) {
                            $tag_slugs[] = $slug;
                            if (isset($pal['max'])) {
                                $numeric_conds[] = "(e.estimate_low IS NOT NULL AND e.estimate_low < " . floatval($pal['max']) . ")";
                            } else {
                                $numeric_conds[] = "(e.estimate_low IS NOT NULL AND e.estimate_low >= " . floatval($pal['min']) . ")";
                            }
                        } else {
                            $tag_slugs[] = $slug;
                        }
                    }
                    $where .= " AND (";
                    $parts = [];
                    if (!empty($tag_slugs)) {
                        $placeholders = implode(',', array_fill(0, count($tag_slugs), '%s'));
                        $parts[] = "EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = %s AND t2.slug IN ($placeholders))";
                        $params[] = $site_id;
                        $params[] = $type;
                        $params = array_merge($params, $tag_slugs);
                    }
                    if (!empty($numeric_conds)) {
                        $parts[] = '(' . implode(' OR ', $numeric_conds) . ')';
                    }
                    $where .= implode(' OR ', $parts) . ")";
                } elseif ($type === 'vendeur') {
                    $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
                    $where .= " AND (";
                    $where .= " EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = %s AND t2.slug IN ($placeholders))";
                    $params[] = $site_id;
                    $params[] = $type;
                    $params = array_merge($params, $slugs);
                    $where .= " OR e.client_email IN ($placeholders)";
                    $params = array_merge($params, $slugs);
                    $where .= ")";
                } elseif ($type === 'interet') {
                    $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
                    $where .= " AND (";
                    $where .= " EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = %s AND t2.slug IN ($placeholders))";
                    $params[] = $site_id;
                    $params[] = $type;
                    $params = array_merge($params, $slugs);
                    $e_cols = $this->wpdb->get_col("DESCRIBE {$e}");
                    if (in_array('ai_analysis', $e_cols, true)) {
                        foreach ($slugs as $slug) {
                            $where .= " OR (e.ai_analysis IS NOT NULL AND e.ai_analysis != '' AND e.ai_analysis LIKE %s)";
                            $params[] = '%"interet":"' . $this->wpdb->esc_like($slug) . '"%';
                        }
                    }
                    $where .= ")";
                } else {
                    $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
                    $where .= " AND EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = %s AND t2.slug IN ($placeholders))";
                    $params[] = $site_id;
                    $params[] = $type;
                    $params = array_merge($params, $slugs);
                }
            }
        }
        if (!empty($args['search'])) {
            $like = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $search_parts = [
                'e.client_name LIKE %s',
                'e.client_email LIKE %s',
                'e.description LIKE %s',
            ];
            $search_params = [$like, $like, $like];
            $e_cols = $this->wpdb->get_col("DESCRIBE {$e}");
            if (in_array('client_first_name', $e_cols, true)) {
                $search_parts[] = 'e.client_first_name LIKE %s';
                $search_params[] = $like;
            }
            if (in_array('client_phone', $e_cols, true)) {
                $search_parts[] = 'e.client_phone LIKE %s';
                $search_params[] = $like;
            }
            if (in_array('client_commune', $e_cols, true)) {
                $search_parts[] = 'e.client_commune LIKE %s';
                $search_params[] = $like;
            }
            if (in_array('ai_analysis', $e_cols, true)) {
                $search_parts[] = '(e.ai_analysis IS NOT NULL AND e.ai_analysis != \'\' AND e.ai_analysis LIKE %s)';
                $search_params[] = $like;
            }
            $search_parts[] = "EXISTS (SELECT 1 FROM $et ets INNER JOIN $t ts ON ets.tag_id = ts.id WHERE ets.estimation_id = e.id AND ts.site_id = %d AND (ts.name LIKE %s OR ts.slug LIKE %s))";
            $search_params[] = $site_id;
            $search_params[] = $like;
            $search_params[] = $like;
            $where .= ' AND (' . implode(' OR ', $search_parts) . ')';
            $params = array_merge($params, $search_params);
        }
        $date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['filter_date_envoi_from']) ? $args['filter_date_envoi_from'] : '';
        $date_to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['filter_date_envoi_to']) ? $args['filter_date_envoi_to'] : '';
        if ($date_from && $date_to) {
            $where .= " AND DATE(e.created_at) >= %s AND DATE(e.created_at) <= %s";
            $params[] = $date_from;
            $params[] = $date_to;
        } elseif ($date_from) {
            $where .= " AND DATE(e.created_at) >= %s";
            $params[] = $date_from;
        } elseif ($date_to) {
            $where .= " AND DATE(e.created_at) <= %s";
            $params[] = $date_to;
        }

        $excluded_themes = is_array($args['pref_excluded_theme_slugs']) ? array_filter(array_map('sanitize_text_field', $args['pref_excluded_theme_slugs'])) : [];
        if (!empty($excluded_themes)) {
            $placeholders = implode(',', array_fill(0, count($excluded_themes), '%s'));
            $where .= " AND NOT EXISTS (SELECT 1 FROM $et etx INNER JOIN $t tx ON etx.tag_id = tx.id WHERE etx.estimation_id = e.id AND tx.site_id = %d AND tx.type = 'theme_vente' AND tx.slug IN ($placeholders))";
            $params[] = $site_id;
            $params = array_merge($params, $excluded_themes);
            $e_cols = $this->wpdb->get_col("DESCRIBE {$e}");
            if (in_array('ai_analysis', $e_cols, true)) {
                foreach ($excluded_themes as $ex_slug) {
                    $where .= " AND NOT (e.ai_analysis IS NOT NULL AND e.ai_analysis != '' AND e.ai_analysis LIKE %s)";
                    $params[] = '%"theme_vente":"' . $this->wpdb->esc_like($ex_slug) . '"%';
                }
            }
        }

        $pref_last_n = (int) $args['pref_display_last_n'];
        $pref_unanswered = (bool) $args['pref_display_include_unanswered'];
        $pref_older_days = (int) $args['pref_display_older_than_days'];
        $use_pref_display = ($pref_last_n > 0 || $pref_unanswered || $pref_older_days > 0) && !$date_from && !$date_to;
        if ($use_pref_display) {
            $pref_parts = [];
            if ($pref_last_n > 0) {
                $pref_parts[] = "e.id IN (SELECT id FROM {$e} e2 WHERE e2.site_id = %d ORDER BY e2.created_at DESC LIMIT %d)";
                $params[] = $site_id;
                $params[] = $pref_last_n;
            }
            if ($pref_unanswered) {
                $pref_parts[] = "NOT EXISTS (SELECT 1 FROM $et etp INNER JOIN $t tp ON etp.tag_id = tp.id WHERE etp.estimation_id = e.id AND tp.site_id = %d AND tp.type = 'message' AND tp.slug IN ('repondu', 'vendu'))";
                $params[] = $site_id;
            }
            if ($pref_older_days > 0) {
                $pref_parts[] = "e.created_at < DATE_SUB(NOW(), INTERVAL %d DAY)";
                $params[] = $pref_older_days;
            }
            if (!empty($pref_parts)) {
                $where .= " AND (" . implode(" OR ", $pref_parts) . ")";
            }
        }

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'e.created_at DESC';
        }
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);
        $sql = "SELECT e.* FROM {$e} e WHERE $where ORDER BY $orderby LIMIT $limit OFFSET $offset";
        $sql = $this->wpdb->prepare($sql, $params);
        return $this->wpdb->get_results($sql);
    }

    /**
     * Compteurs Échanges pour la grille de badges (présents, retard <7j, retard >=7j)
     */
    public function get_estimation_counts_exchanges() {
        $site_id = get_current_blog_id();
        $e = $this->prefix . 'estimations';
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';
        $cols = $this->wpdb->get_col("DESCRIBE $e");
        $ref_col = in_array('first_viewed_at', $cols, true) ? 'COALESCE(e.first_viewed_at, e.created_at)' : 'e.created_at';
        $not_repondu = "NOT EXISTS (SELECT 1 FROM $et et2 INNER JOIN $t t2 ON et2.tag_id = t2.id WHERE et2.estimation_id = e.id AND t2.site_id = %d AND t2.type = 'message' AND t2.slug IN ('repondu', 'vendu'))";
        $tous = (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$e} WHERE site_id = %d", $site_id));
        $retard_7j = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$e} e WHERE e.site_id = %d AND $not_repondu AND $ref_col < DATE_SUB(NOW(), INTERVAL 48 HOUR) AND $ref_col >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $site_id, $site_id
        ));
        $retard_plus = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$e} e WHERE e.site_id = %d AND $not_repondu AND $ref_col < DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $site_id, $site_id
        ));
        return ['tous' => $tous, 'retard_7j' => $retard_7j, 'retard_plus' => $retard_plus];
    }

    /**
     * Compteurs pour les filtres (status + tags message + interet)
     */
    public function get_estimation_filter_counts() {
        $site_id = get_current_blog_id();
        $e = $this->prefix . 'estimations';
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';

        $counts = [
            'total' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$e} WHERE site_id = %d", $site_id)),
            'status_new' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$e} WHERE site_id = %d AND status = %s", $site_id, 'new')),
            'status_ai_analyzed' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$e} WHERE site_id = %d AND status = %s", $site_id, 'ai_analyzed')),
        ];

        $tag_types = ['message', 'interet'];
        foreach ($tag_types as $type) {
            $rows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT t.slug, COUNT(*) as cnt FROM {$e} e INNER JOIN {$et} et ON et.estimation_id = e.id INNER JOIN {$t} t ON t.id = et.tag_id WHERE e.site_id = %d AND t.type = %s GROUP BY t.slug",
                $site_id, $type
            ));
            foreach ($rows as $row) {
                $counts['tag_' . $type . '_' . $row->slug] = (int) $row->cnt;
            }
        }
        return $counts;
    }

    /**
     * Options dynamiques pour un type de tag (ex: date_vente, vendeur)
     */
    public function get_tag_options_for_type($type) {
        $site_id = get_current_blog_id();
        $cols = ['id', 'name', 'slug'];
        $tag_cols = $this->wpdb->get_col("DESCRIBE {$this->prefix}tags");
        if (in_array('theme_vente_slug', $tag_cols, true)) {
            $cols[] = 'theme_vente_slug';
        }
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT " . implode(', ', $cols) . " FROM {$this->prefix}tags WHERE site_id = %d AND type = %s ORDER BY name",
            $site_id, $type
        ));
    }

    /**
     * Synchronise le tag message selon l'état : nouveau, non_lu, lu_non_repondu, repondu
     */
    public function sync_message_tag($estimation) {
        $id = (int) ($estimation->id ?? 0);
        if (!$id) return;
        $site_id = get_current_blog_id();
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';
        $msg_tag = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT t.id, t.slug FROM $et et INNER JOIN $t t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.site_id = %d AND t.type = 'message'",
            $id, $site_id
        ));
        if ($msg_tag && ($msg_tag->slug ?? '') === 'vendu') return;
        $repondu = ($msg_tag && ($msg_tag->slug ?? '') === 'repondu') || !empty($estimation->reponse_sent_at);
        if ($repondu) {
            if (!$msg_tag || ($msg_tag->slug ?? '') !== 'repondu') {
                $tag_row = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT id FROM $t WHERE site_id = %d AND type = 'message' AND slug = %s",
                    $site_id, 'repondu'
                ));
                if ($tag_row) {
                    $this->wpdb->query($this->wpdb->prepare(
                        "DELETE et FROM $et et INNER JOIN $t t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.type = 'message'",
                        $id
                    ));
                    $this->wpdb->insert($et, ['estimation_id' => $id, 'tag_id' => $tag_row->id], ['%d', '%d']);
                }
            }
            return;
        }
        $opened = !empty($estimation->first_viewed_at);
        $created_ts = !empty($estimation->created_at) ? strtotime($estimation->created_at) : 0;
        $hours_since = $created_ts ? (time() - $created_ts) / 3600 : 0;
        $wanted = $opened ? 'lu_non_repondu' : ($hours_since < 48 ? 'nouveau' : 'non_lu');
        if ($msg_tag && ($msg_tag->slug ?? '') === $wanted) return;
        $tag_row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT id FROM $t WHERE site_id = %d AND type = 'message' AND slug = %s",
            $site_id, $wanted
        ));
        if (!$tag_row) return;
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE et FROM $et et INNER JOIN $t t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.type = 'message'",
            $id
        ));
        $this->wpdb->insert($et, ['estimation_id' => $id, 'tag_id' => $tag_row->id], ['%d', '%d']);
    }

    /**
     * Tags liés à une estimation (par type)
     */
    public function get_estimation_tags($estimation_id) {
        $site_id = get_current_blog_id();
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT t.type, t.slug, t.name FROM {$this->prefix}estimation_tags et INNER JOIN {$this->prefix}tags t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.site_id = %d",
            $estimation_id, $site_id
        ));
    }
}
