<?php
/**
 * Statistiques tableau de bord — par catégorie, consommation, promotions
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Dashboard_Stats {

    private $wpdb;
    private $prefix;
    private $site_id;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'lmd_';
        $this->site_id = get_current_blog_id();
    }

    /**
     * Stats par catégorie (intérêt, estimation, thème) sur les N derniers mois.
     *
     * @param int $months Nombre de mois (défaut 24)
     * @return array ['interet' => [slug => [month => count]], 'estimation' => ..., 'theme_vente' => ...]
     */
    public function get_stats_by_category($months = 24) {
        $e = $this->prefix . 'estimations';
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';

        $from_date = gmdate('Y-m-01', strtotime("-$months months"));

        $types = ['interet', 'estimation', 'theme_vente'];
        $result = [];

        foreach ($types as $type) {
            $rows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT t.slug, t.name, DATE_FORMAT(e.created_at, '%%Y-%%m') as month, COUNT(*) as cnt
                FROM $e e
                INNER JOIN $et et ON et.estimation_id = e.id
                INNER JOIN $t t ON t.id = et.tag_id AND t.type = %s AND t.site_id = %d
                WHERE e.site_id = %d AND e.created_at >= %s
                GROUP BY t.slug, t.name, month
                ORDER BY month DESC, cnt DESC",
                $type,
                $this->site_id,
                $this->site_id,
                $from_date
            ), ARRAY_A);

            $by_slug = [];
            foreach ($rows ?: [] as $r) {
                $slug = $r['slug'];
                $name = $r['name'];
                $month = $r['month'];
                if (!isset($by_slug[$slug])) {
                    $by_slug[$slug] = ['name' => $name, 'months' => []];
                }
                $by_slug[$slug]['months'][$month] = (int) $r['cnt'];
            }
            $result[$type] = $by_slug;
        }

        return $result;
    }

    /**
     * Vendeurs avec le plus d'estimations récentes (nouveaux gros vendeurs).
     *
     * @param int $limit
     * @param string|null $month Mois YYYY-MM ou null pour tous
     * @return array
     */
    public function get_top_vendeurs($limit = 10, $month = null) {
        $e = $this->prefix . 'estimations';
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';

        $where = "WHERE e.site_id = %d AND t.type = 'vendeur' AND t.site_id = %d";
        $params = [$this->site_id, $this->site_id];

        if ($month) {
            $where .= " AND e.created_at >= %s AND e.created_at < %s";
            $params[] = $month . '-01 00:00:00';
            $params[] = date('Y-m-d', strtotime($month . '-01 +1 month')) . ' 00:00:00';
        }

        $params[] = $limit;

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT t.slug, t.name, COUNT(*) as cnt, MAX(e.created_at) as last_at
            FROM $e e
            INNER JOIN $et et ON et.estimation_id = e.id
            INNER JOIN $t t ON t.id = et.tag_id
            $where
            GROUP BY t.slug, t.name
            ORDER BY cnt DESC
            LIMIT %d",
            ...$params
        ), ARRAY_A);

        return $rows ?: [];
    }

    /**
     * Promotion pour le site actuel (depuis le parent).
     *
     * @return array|null { type: 'ristourne'|'gratuites', amount: int, message: string } ou null
     */
    public static function get_client_promotion() {
        $site_id = get_current_blog_id();
        if (!is_multisite()) {
            $promos = get_option('lmd_client_promotions', []);
            return $promos[1] ?? get_option('lmd_promotion', null);
        }
        switch_to_blog(1);
        $promos = get_option('lmd_client_promotions', []);
        restore_current_blog();
        return $promos[$site_id] ?? null;
    }

    /**
     * URL du logo client (stocké sur le parent).
     *
     * @return string|null
     */
    public static function get_client_logo_url() {
        $site_id = get_current_blog_id();
        if (!is_multisite()) {
            $logos = get_option('lmd_client_logos', []);
            return $logos[1] ?? get_option('lmd_client_logo_url', null);
        }
        switch_to_blog(1);
        $logos = get_option('lmd_client_logos', []);
        restore_current_blog();
        return $logos[$site_id] ?? null;
    }

    /**
     * URL du logo LMD (par défaut dans le plugin).
     *
     * @return string
     */
    public static function get_lmd_logo_url() {
        $custom = get_option('lmd_logo_url', '');
        if ($custom) {
            return $custom;
        }
        $path = LMD_PLUGIN_DIR . 'assets/images/logo-lmd.svg';
        if (file_exists($path)) {
            return LMD_PLUGIN_URL . 'assets/images/logo-lmd.svg';
        }
        return '';
    }
}
