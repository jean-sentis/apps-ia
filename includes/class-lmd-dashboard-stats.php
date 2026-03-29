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
     * Statistiques par catégorie sur une plage de dates (ou tout l’historique si les deux null).
     * Retourne pour chaque slug une clé « total » (effectif sur la période).
     *
     * @param string|null $date_from YYYY-MM-DD ou null
     * @param string|null $date_to   YYYY-MM-DD ou null
     */
    public function get_stats_by_category_for_range($date_from = null, $date_to = null) {
        $e = $this->prefix . 'estimations';
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';

        $types = ['interet', 'estimation', 'theme_vente'];
        $result = [];

        foreach ($types as $type) {
            $where = "e.site_id = %d";
            $wp = [$this->site_id];
            if ($date_from) {
                $where .= " AND e.created_at >= %s";
                $wp[] = $date_from . ' 00:00:00';
            }
            if ($date_to) {
                $where .= " AND e.created_at <= %s";
                $wp[] = $date_to . ' 23:59:59';
            }
            $params = array_merge([$type, $this->site_id], $wp);

            $sql = "SELECT t.slug, t.name, COUNT(*) as cnt
                FROM $e e
                INNER JOIN $et et ON et.estimation_id = e.id
                INNER JOIN $t t ON t.id = et.tag_id AND t.type = %s AND t.site_id = %d
                WHERE $where
                GROUP BY t.slug, t.name
                ORDER BY cnt DESC";

            $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);

            $by_slug = [];
            foreach ($rows ?: [] as $r) {
                $slug = $r['slug'];
                $by_slug[$slug] = [
                    'name' => $r['name'],
                    'months' => [],
                    'total' => (int) $r['cnt'],
                ];
            }
            $result[$type] = $by_slug;
        }

        return $result;
    }

    /**
     * Slugs d’intérêt comptés comme « avis favorable » (étude favorable).
     *
     * @return string[]
     */
    public static function get_favorable_interet_slugs() {
        return ['a_examiner', 'interessant', 'tres_interessant', 'exceptionnel'];
    }

    /**
     * KPI « étude » : demandes / avis favorable (incl. à examiner) / lots déposés.
     *
     * @return array{total:int,depose:int,favorable:int,pct_depose_sur_favorable:?float,pct_depose_sur_total:?float,pct_favorable_sur_total:?float}
     */
    public function get_kpi_etude_lots($date_from = null, $date_to = null) {
        $e = $this->prefix . 'estimations';
        $et = $this->prefix . 'estimation_tags';
        $t = $this->prefix . 'tags';

        $where = 'e.site_id = %d';
        $params = [$this->site_id];
        if ($date_from) {
            $where .= ' AND e.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $where .= ' AND e.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $total = (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM $e e WHERE $where", ...$params));

        $fav_slugs = self::get_favorable_interet_slugs();
        $placeholders = implode(',', array_fill(0, count($fav_slugs), '%s'));
        $params_f = array_merge($fav_slugs, [$this->site_id], $params);

        $sql_fav = "SELECT COUNT(DISTINCT e.id) FROM $e e
            INNER JOIN $et et ON et.estimation_id = e.id
            INNER JOIN $t t ON t.id = et.tag_id AND t.type = 'interet' AND t.slug IN ($placeholders) AND t.site_id = %d
            WHERE $where";
        $favorable = (int) $this->wpdb->get_var($this->wpdb->prepare($sql_fav, ...$params_f));

        $params_d = array_merge(['depose', $this->site_id], $params);
        $sql_dep = "SELECT COUNT(DISTINCT e.id) FROM $e e
            INNER JOIN $et et ON et.estimation_id = e.id
            INNER JOIN $t t ON t.id = et.tag_id AND t.type = 'message' AND t.slug = %s AND t.site_id = %d
            WHERE $where";
        $depose = (int) $this->wpdb->get_var($this->wpdb->prepare($sql_dep, ...$params_d));

        $pct_df = ($favorable > 0) ? round(100 * $depose / $favorable, 1) : null;
        $pct_dt = ($total > 0) ? round(100 * $depose / $total, 1) : null;
        $pct_ft = ($total > 0) ? round(100 * $favorable / $total, 1) : null;

        return [
            'total' => $total,
            'depose' => $depose,
            'favorable' => $favorable,
            'pct_depose_sur_favorable' => $pct_df,
            'pct_depose_sur_total' => $pct_dt,
            'pct_favorable_sur_total' => $pct_ft,
        ];
    }

    /**
     * Délais de réponse (réponse vendeur enregistrée) et répartition dépôt / délai pour avis favorables.
     *
     * @return array{
     *   avg_reply_sec_all:?float,
     *   avg_reply_sec_favorable:?float,
     *   avg_reply_display_all:?string,
     *   avg_reply_display_favorable:?string,
     *   n_with_reply:int,
     *   n_favorable_with_reply:int,
     *   favorable_depose_by_delay: array<string, array{label:string,n_fav:int,n_depose:int,pct:?float}>
     * }
     */
    public function get_kpi_response_metrics($date_from = null, $date_to = null) {
        $e = $this->prefix . 'estimations';
        $empty_buckets = [
            'd0_2' => ['label' => '≤ 2 jours', 'n_fav' => 0, 'n_depose' => 0, 'pct' => null],
            'd2_4' => ['label' => '> 2 j. et ≤ 4 j.', 'n_fav' => 0, 'n_depose' => 0, 'pct' => null],
            'd4_7' => ['label' => '> 4 j. et ≤ 7 j.', 'n_fav' => 0, 'n_depose' => 0, 'pct' => null],
            'd7_14' => ['label' => '> 7 j. et ≤ 14 j.', 'n_fav' => 0, 'n_depose' => 0, 'pct' => null],
            'd14p' => ['label' => '> 14 jours', 'n_fav' => 0, 'n_depose' => 0, 'pct' => null],
        ];

        $cols = $this->wpdb->get_col("DESCRIBE {$e}");
        if (!in_array('reponse_sent_at', $cols, true)) {
            return [
                'avg_reply_sec_all' => null,
                'avg_reply_sec_favorable' => null,
                'avg_reply_display_all' => null,
                'avg_reply_display_favorable' => null,
                'n_with_reply' => 0,
                'n_favorable_with_reply' => 0,
                'favorable_depose_by_delay' => $empty_buckets,
            ];
        }

        $where = 'e.site_id = %d AND e.reponse_sent_at IS NOT NULL AND e.reponse_sent_at >= e.created_at';
        $params = [$this->site_id];
        if ($date_from) {
            $where .= ' AND e.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if ($date_to) {
            $where .= ' AND e.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $avg_sec_all = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, e.created_at, e.reponse_sent_at)) FROM $e e WHERE $where",
            ...$params
        ));
        $n_reply = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM $e e WHERE $where",
            ...$params
        ));

        $fav_slugs = self::get_favorable_interet_slugs();
        $placeholders = implode(',', array_fill(0, count($fav_slugs), '%s'));
        $params_f = array_merge($fav_slugs, [$this->site_id], $params);

        $sql_fav_avg = "SELECT AVG(sub.delay_sec) FROM (
                SELECT e.id, MAX(TIMESTAMPDIFF(SECOND, e.created_at, e.reponse_sent_at)) AS delay_sec
                FROM $e e
                INNER JOIN {$this->prefix}estimation_tags et ON et.estimation_id = e.id
                INNER JOIN {$this->prefix}tags t ON t.id = et.tag_id AND t.type = 'interet' AND t.slug IN ($placeholders) AND t.site_id = %d
                WHERE $where
                GROUP BY e.id
            ) sub";
        $avg_sec_fav = $this->wpdb->get_var($this->wpdb->prepare($sql_fav_avg, ...$params_f));

        $sql_fav_rows = "SELECT e.id,
                (TIMESTAMPDIFF(HOUR, e.created_at, e.reponse_sent_at) / 24.0) AS delay_days,
                (SELECT COUNT(*) FROM {$this->prefix}estimation_tags et2
                    INNER JOIN {$this->prefix}tags t2 ON t2.id = et2.tag_id AND t2.type = 'message' AND t2.slug = 'depose' AND t2.site_id = %d
                    WHERE et2.estimation_id = e.id) AS depose_cnt
            FROM $e e
            WHERE $where
            AND EXISTS (
                SELECT 1 FROM {$this->prefix}estimation_tags et3
                INNER JOIN {$this->prefix}tags t3 ON t3.id = et3.tag_id AND t3.type = 'interet' AND t3.slug IN ($placeholders) AND t3.site_id = %d
                WHERE et3.estimation_id = e.id
            )";
        $params_rows = array_merge([$this->site_id], $params, $fav_slugs, [$this->site_id]);
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql_fav_rows, ...$params_rows));

        $buckets = $empty_buckets;
        $n_fav_reply = 0;
        foreach ($rows ?: [] as $row) {
            $d = isset($row->delay_days) ? (float) $row->delay_days : 0.0;
            $has_depose = !empty($row->depose_cnt);
            $n_fav_reply++;
            if ($d <= 2) {
                $k = 'd0_2';
            } elseif ($d <= 4) {
                $k = 'd2_4';
            } elseif ($d <= 7) {
                $k = 'd4_7';
            } elseif ($d <= 14) {
                $k = 'd7_14';
            } else {
                $k = 'd14p';
            }
            $buckets[$k]['n_fav']++;
            if ($has_depose) {
                $buckets[$k]['n_depose']++;
            }
        }
        foreach ($buckets as $bk => $bv) {
            $nf = (int) $bv['n_fav'];
            $buckets[$bk]['pct'] = $nf > 0 ? round(100 * (int) $bv['n_depose'] / $nf, 1) : null;
        }

        return [
            'avg_reply_sec_all' => $avg_sec_all !== null ? (float) $avg_sec_all : null,
            'avg_reply_sec_favorable' => $avg_sec_fav !== null ? (float) $avg_sec_fav : null,
            'avg_reply_display_all' => self::format_avg_delay_seconds($avg_sec_all !== null ? (float) $avg_sec_all : null),
            'avg_reply_display_favorable' => self::format_avg_delay_seconds($avg_sec_fav !== null ? (float) $avg_sec_fav : null),
            'n_with_reply' => $n_reply,
            'n_favorable_with_reply' => $n_fav_reply,
            'favorable_depose_by_delay' => $buckets,
        ];
    }

    /**
     * Affichage lisible d’une durée moyenne (secondes) en jours / heures / minutes.
     */
    public static function format_avg_delay_seconds($sec) {
        if ($sec === null || $sec === '') {
            return null;
        }
        $sec = max(0, (float) $sec);
        $d = (int) floor($sec / 86400);
        $h = (int) floor(fmod($sec, 86400) / 3600);
        $m = (int) floor(fmod($sec, 3600) / 60);
        if ($d > 0) {
            return sprintf('%d j. %d h', $d, $h);
        }
        if ($h > 0) {
            return sprintf('%d h %d min', $h, $m);
        }
        return sprintf('%d min', max(0, $m));
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
