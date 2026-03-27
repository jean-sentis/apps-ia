<?php
/**
 * Analytics d'activité - Comportement des clients et usage.
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Activity_Analytics {

    private $wpdb;
    private $prefix;
    private $site_id;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'lmd_';
        $this->site_id = get_current_blog_id();
    }

    public function get_service_start_date() {
        return get_option('lmd_service_start_date', '');
    }

    public function set_service_start_date($date) {
        $d = date('Y-m-d', strtotime($date));
        return update_option('lmd_service_start_date', $d);
    }

    /**
     * Stats mensuelles par mois.
     */
    public function get_monthly_stats($year_from = null, $year_to = null) {
        $e = $this->prefix . 'estimations';
        $log = $this->prefix . 'activity_log';
        $cols = $this->wpdb->get_col("DESCRIBE $e");
        $has_ai_launch = in_array('ai_launch_count', $cols, true);
        $has_first_viewed = in_array('first_viewed_at', $cols, true);

        $where = "WHERE site_id = %d";
        $params = [$this->site_id];
        if ($year_from) {
            $where .= " AND created_at >= %s";
            $params[] = $year_from . '-01-01';
        }
        if ($year_to) {
            $where .= " AND created_at <= %s";
            $params[] = $year_to . '-12-31 23:59:59';
        }

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE_FORMAT(created_at, '%%Y-%%m') as month, COUNT(*) as cnt FROM $e $where GROUP BY month ORDER BY month",
            $params
        ));

        $months = [];
        foreach ($rows ?: [] as $r) {
            $months[$r->month] = ['estimations' => (int) $r->cnt];
        }

        if ($this->wpdb->get_var("SHOW TABLES LIKE '$log'") === $log) {
            $log_where = "WHERE site_id = %d";
            $log_params = [$this->site_id];
            if ($year_from) {
                $log_where .= " AND created_at >= %s";
                $log_params[] = $year_from . '-01-01';
            }
            if ($year_to) {
                $log_where .= " AND created_at <= %s";
                $log_params[] = $year_to . '-12-31 23:59:59';
            }
            $log_rows = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT DATE_FORMAT(created_at, '%%Y-%%m') as month, page_type, SUM(duration_seconds) as total_sec FROM $log $log_where GROUP BY month, page_type",
                $log_params
            ));
            foreach ($log_rows ?: [] as $r) {
                if (!isset($months[$r->month])) $months[$r->month] = ['estimations' => 0];
                $key = $r->page_type === 'detail' ? 'detail_seconds' : 'grid_seconds';
                $months[$r->month][$key] = (int) $r->total_sec;
            }
        }

        return $months;
    }

    /**
     * Stats par estimation.
     */
    public function get_per_estimation_stats($month = null) {
        $e = $this->prefix . 'estimations';
        $log = $this->prefix . 'activity_log';
        $cols = $this->wpdb->get_col("DESCRIBE $e");
        $has_ai_launch = in_array('ai_launch_count', $cols, true);
        $has_first_viewed = in_array('first_viewed_at', $cols, true);

        $where = "WHERE site_id = %d";
        $params = [$this->site_id];
        if ($month) {
            $where .= " AND created_at >= %s AND created_at < %s";
            $params[] = $month . '-01 00:00:00';
            $params[] = date('Y-m-d', strtotime($month . '-01 +1 month')) . ' 00:00:00';
        }

        $select = "e.id, e.created_at, e.first_viewed_at, e.status, e.ai_analysis, e.avis1_estimate_low, e.avis1_estimate_high";
        if ($has_ai_launch) $select .= ", e.ai_launch_count";

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT $select FROM $e e $where ORDER BY e.created_at DESC LIMIT 500",
            $params
        ));

        $site_id = $this->site_id;
        $et = $this->wpdb->prefix . 'lmd_estimation_tags';
        $t = $this->wpdb->prefix . 'lmd_tags';

        $result = [];
        foreach ($rows ?: [] as $r) {
            $delay_hours = null;
            if ($has_first_viewed && !empty($r->first_viewed_at) && !empty($r->created_at)) {
                $delay_hours = (strtotime($r->first_viewed_at) - strtotime($r->created_at)) / 3600;
            }

            $detail_sec = 0;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$log'") === $log) {
                $detail_sec = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COALESCE(SUM(duration_seconds), 0) FROM $log WHERE site_id = %d AND page_type = 'detail' AND estimation_id = %d",
                    $site_id, $r->id
                ));
            }

            $ai_follows_price = null;
            $ai_follows_interest = null;
            if (!empty($r->ai_analysis)) {
                $ai = json_decode($r->ai_analysis, true) ?: [];
                $ai_est = function_exists('lmd_get_ai_estimation') ? lmd_get_ai_estimation($ai) : ['low' => null, 'high' => null];
                $ai_interet = trim($ai['interest_level'] ?? $ai['interet'] ?? '');
                if ($ai_est['low'] !== null && $r->avis1_estimate_low !== null) {
                    $diff = abs(($r->avis1_estimate_low + $r->avis1_estimate_high) / 2 - ($ai_est['low'] + ($ai_est['high'] ?? $ai_est['low'])) / 2);
                    $ai_follows_price = $diff < 50 ? 'oui' : ($diff < 200 ? 'proche' : 'non');
                }
                $interet_tag = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT t.slug FROM $et et INNER JOIN $t t ON et.tag_id = t.id WHERE et.estimation_id = %d AND t.type = 'interet'",
                    $r->id
                ));
                if ($ai_interet && $interet_tag) {
                    $norm = function ($s) {
                        $s = strtolower(trim($s));
                        $s = str_replace(['é', 'è', 'ê', 'à', 'â', 'î', 'ï', 'ô', 'û', 'ù', 'ç'], ['e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'c'], $s);
                        return preg_replace('/[^a-z0-9]/', '', $s);
                    };
                    $ai_follows_interest = ($norm($interet_tag) === $norm($ai_interet)) ? 'oui' : 'non';
                }
            }

            $result[] = [
                'id' => $r->id,
                'created_at' => $r->created_at,
                'delay_hours' => $delay_hours,
                'detail_seconds' => $detail_sec,
                'ai_launch_count' => $has_ai_launch ? ($r->ai_launch_count ?? 0) : 0,
                'ai_follows_price' => $ai_follows_price,
                'ai_follows_interest' => $ai_follows_interest,
            ];
        }

        return $result;
    }

    /**
     * Agrégations pour le mois.
     */
    public function get_month_aggregates($month) {
        $stats = $this->get_per_estimation_stats($month);
        $delay_hours = array_filter(array_column($stats, 'delay_hours'), function ($v) { return $v !== null; });
        $detail_sec = array_sum(array_column($stats, 'detail_seconds'));
        $ai_launches = array_sum(array_column($stats, 'ai_launch_count'));
        $follows_price = array_filter(array_column($stats, 'ai_follows_price'));
        $follows_interest = array_filter(array_column($stats, 'ai_follows_interest'));

        $log = $this->prefix . 'activity_log';
        $grid_sec = 0;
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$log'") === $log && $month) {
            $grid_sec = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COALESCE(SUM(duration_seconds), 0) FROM $log WHERE site_id = %d AND page_type = 'grid' AND created_at >= %s AND created_at < %s",
                $this->site_id,
                $month . '-01 00:00:00',
                date('Y-m-d', strtotime($month . '-01 +1 month')) . ' 00:00:00'
            ));
        }

        return [
            'count' => count($stats),
            'delay_avg_hours' => !empty($delay_hours) ? array_sum($delay_hours) / count($delay_hours) : null,
            'detail_total_minutes' => round($detail_sec / 60, 1),
            'grid_total_minutes' => round($grid_sec / 60, 1),
            'ai_launch_total' => $ai_launches,
            'follows_price_oui' => count(array_filter($follows_price, function ($v) { return $v === 'oui'; })),
            'follows_price_total' => count($follows_price),
            'follows_interest_oui' => count(array_filter($follows_interest, function ($v) { return $v === 'oui'; })),
            'follows_interest_total' => count($follows_interest),
        ];
    }

    /**
     * Libellés des fonctionnalités.
     */
    public static function get_feature_labels() {
        return [
            'analyse_ia' => 'Analyse IA',
            'avis1' => 'Avis 1 (estimations)',
            'avis2' => 'Avis 2 (second avis)',
            'reponse' => 'Réponse envoyée',
            'delegation' => 'Délégation',
            'ventes' => 'Création ventes',
            'erreur_ia' => 'Erreur IA signalée',
            'formules' => 'Formules de politesse',
        ];
    }

    /**
     * Usage des fonctionnalités pour un site (ou tous en multisite).
     *
     * @param string|null $month YYYY-MM ou null pour tout
     * @param bool $all_sites En multisite, true = tous les sites
     * @return array ['by_feature' => [...], 'by_site' => [...], 'most_used' => [...], 'least_used' => [...]]
     */
    public function get_feature_usage($month = null, $all_sites = false) {
        $sites = [];
        if ($all_sites && is_multisite()) {
            $site_list = get_sites(['number' => 500]);
            foreach ($site_list as $s) {
                $sites[] = (int) $s->blog_id;
            }
        } else {
            $sites[] = $this->site_id;
        }

        $by_feature = [];
        $by_site = [];
        foreach (array_keys(self::get_feature_labels()) as $k) {
            $by_feature[$k] = 0;
        }

        foreach ($sites as $sid) {
            if (is_multisite() && $sid !== get_current_blog_id()) {
                switch_to_blog($sid);
            }
            $prefix = $this->wpdb->prefix . 'lmd_';
            $e_t = $prefix . 'estimations';
            $t_t = $prefix . 'tags';
            $cp_t = $prefix . 'cp_settings';
            $f_t = $prefix . 'formules';

            $where = 'site_id = %d';
            $params = [$sid];
            if ($month) {
                $where .= ' AND created_at >= %s AND created_at < %s';
                $params[] = $month . '-01 00:00:00';
                $params[] = date('Y-m-d', strtotime($month . '-01 +1 month')) . ' 00:00:00';
            }

            $cols = $this->wpdb->get_col("DESCRIBE $e_t");
            $has_ai = in_array('ai_analysis', $cols, true);
            $has_avis1 = in_array('avis1_estimate_low', $cols, true);
            $has_avis2 = in_array('avis2_estimate_low', $cols, true);
            $has_reponse = in_array('reponse_sent_at', $cols, true);
            $has_delegation = in_array('delegation_email', $cols, true);
            $has_error = in_array('ai_error_reported_at', $cols, true);

            $site_data = [];
            if ($has_ai) {
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $e_t WHERE $where AND status = 'ai_analyzed'",
                    ...$params
                ));
                $site_data['analyse_ia'] = $n;
                $by_feature['analyse_ia'] += $n;
            }
            if ($has_avis1) {
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $e_t WHERE $where AND avis1_estimate_low IS NOT NULL",
                    ...$params
                ));
                $site_data['avis1'] = $n;
                $by_feature['avis1'] += $n;
            }
            if ($has_avis2) {
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $e_t WHERE $where AND avis2_estimate_low IS NOT NULL",
                    ...$params
                ));
                $site_data['avis2'] = $n;
                $by_feature['avis2'] += $n;
            }
            if ($has_reponse) {
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $e_t WHERE $where AND reponse_sent_at IS NOT NULL",
                    ...$params
                ));
                $site_data['reponse'] = $n;
                $by_feature['reponse'] += $n;
            }
            if ($has_delegation) {
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $e_t WHERE $where AND delegation_email IS NOT NULL AND delegation_email != ''",
                    ...$params
                ));
                $site_data['delegation'] = $n;
                $by_feature['delegation'] += $n;
            }
            if ($has_error) {
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $e_t WHERE $where AND ai_error_reported_at IS NOT NULL",
                    ...$params
                ));
                $site_data['erreur_ia'] = $n;
                $by_feature['erreur_ia'] += $n;
            }

            if ($this->wpdb->get_var("SHOW TABLES LIKE '$t_t'") === $t_t) {
                $t_where = 'site_id = %d AND type = %s';
                $t_params = [$sid, 'date_vente'];
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $t_t WHERE $t_where",
                    ...$t_params
                ));
                $site_data['ventes'] = $n;
                $by_feature['ventes'] += $n;
            } else {
                $site_data['ventes'] = 0;
            }

            if ($this->wpdb->get_var("SHOW TABLES LIKE '$f_t'") === $f_t) {
                $f_where = 'site_id = %d';
                $f_params = [$sid];
                if ($month) {
                    $f_cols = $this->wpdb->get_col("DESCRIBE $f_t");
                    if (!empty($f_cols) && in_array('created_at', $f_cols, true)) {
                        $f_where .= ' AND created_at >= %s AND created_at < %s';
                        $f_params[] = $month . '-01 00:00:00';
                        $f_params[] = date('Y-m-d', strtotime($month . '-01 +1 month')) . ' 00:00:00';
                    }
                }
                $n = (int) $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM $f_t WHERE $f_where",
                    ...$f_params
                ));
                $site_data['formules'] = $n;
                $by_feature['formules'] += $n;
            } else {
                $site_data['formules'] = 0;
            }

            $by_site[$sid] = array_merge(['site_name' => get_bloginfo('name') ?: 'Site ' . $sid], $site_data);

            if (is_multisite() && $sid !== get_current_blog_id()) {
                restore_current_blog();
            }
        }

        $sorted = $by_feature;
        arsort($sorted);
        $most_used = array_slice(array_keys($sorted), 0, 5);
        $least_used = array_reverse(array_keys($sorted));
        $least_used = array_slice(array_filter($least_used, function ($k) use ($by_feature) {
            return ($by_feature[$k] ?? 0) === 0;
        }), 0, 5);
        if (empty($least_used)) {
            $least_used = array_slice(array_keys($sorted), -3);
        }

        return [
            'by_feature' => $by_feature,
            'by_site' => $by_site,
            'most_used' => $most_used,
            'least_used' => array_values($least_used),
            'labels' => self::get_feature_labels(),
        ];
    }

    /**
     * État de la signature CP par site : configurée ou non.
     *
     * @param bool $all_sites En multisite, true = tous les sites
     * @return array [['site_id' => int, 'site_name' => string, 'has_signature' => bool, 'users_with_signature' => int], ...]
     */
    public function get_signature_status($all_sites = false) {
        $sites = [];
        if ($all_sites && is_multisite()) {
            $site_list = get_sites(['number' => 500]);
            foreach ($site_list as $s) {
                $sites[] = (int) $s->blog_id;
            }
        } else {
            $sites[] = $this->site_id;
        }

        $result = [];
        foreach ($sites as $sid) {
            if (is_multisite() && $sid !== get_current_blog_id()) {
                switch_to_blog($sid);
            }
            $cp = $this->wpdb->prefix . 'lmd_cp_settings';
            $has_table = $this->wpdb->get_var("SHOW TABLES LIKE '$cp'") === $cp;
            $users_with = 0;
            if ($has_table) {
                $users_with = (int) $this->wpdb->get_var(
                    "SELECT COUNT(*) FROM $cp WHERE site_id = " . (int) $sid . " AND cp_signature IS NOT NULL AND TRIM(cp_signature) != ''"
                );
            }
            $result[] = [
                'site_id' => $sid,
                'site_name' => get_bloginfo('name') ?: 'Site ' . $sid,
                'has_signature' => $users_with > 0,
                'users_with_signature' => $users_with,
            ];
            if (is_multisite() && $sid !== get_current_blog_id()) {
                restore_current_blog();
            }
        }
        return $result;
    }
}
