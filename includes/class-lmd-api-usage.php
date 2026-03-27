<?php
/**
 * Suivi de l'usage des APIs IA (SerpAPI, Firecrawl, ImgBB, Gemini)
 * Comptage par requête, par mois, coûts estimés, paliers tarifaires
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Api_Usage {

    private $wpdb;
    private $prefix;

    /** Paliers tarifaires par API (volume → prix unitaire en $) */
    private static $PRICING_TIERS = [
        'serpapi' => [
            ['min' => 0, 'max' => 250, 'price' => 0],
            ['min' => 251, 'max' => 1000, 'price' => 0.025],
            ['min' => 1001, 'max' => 5000, 'price' => 0.015],
            ['min' => 5001, 'max' => 15000, 'price' => 0.01],
            ['min' => 15001, 'max' => 30000, 'price' => 0.009],
            ['min' => 30001, 'max' => null, 'price' => 0.008],
        ],
        'firecrawl' => [
            ['min' => 0, 'max' => 500, 'price' => 0],
            ['min' => 501, 'max' => 3000, 'price' => 0.0053],
            ['min' => 3001, 'max' => 100000, 'price' => 0.00083],
            ['min' => 100001, 'max' => 500000, 'price' => 0.00066],
            ['min' => 500001, 'max' => null, 'price' => 0.00035],
        ],
        'imgbb' => [
            ['min' => 0, 'max' => 999999, 'price' => 0],
        ],
        'gemini' => [
            ['min' => 0, 'max' => 1500, 'price' => 0.04],
            ['min' => 1501, 'max' => 10000, 'price' => 0.035],
            ['min' => 10001, 'max' => 100000, 'price' => 0.03],
            ['min' => 100001, 'max' => null, 'price' => 0.025],
        ],
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'lmd_';
    }

    /**
     * Crée la table de suivi si nécessaire.
     */
    public function ensure_table_exists() {
        $table = $this->prefix . 'api_usage';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
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
        ) $charset;";
        dbDelta($sql);
    }

    /**
     * Enregistre un usage API.
     *
     * @param string $api_name serpapi|firecrawl|imgbb|gemini
     * @param int $units Nombre d'unités (1 requête SerpAPI, N pages Firecrawl, etc.)
     * @param int|null $estimation_id ID de l'estimation concernée
     */
    public function log($api_name, $units = 1, $estimation_id = null) {
        $this->ensure_table_exists();
        $api_name = $this->sanitize_api_name($api_name);
        if (!$api_name) {
            return;
        }
        $site_id = get_current_blog_id();
        $cost = $this->estimate_cost_for_units($api_name, $units, $site_id);

        $this->wpdb->insert(
            $this->prefix . 'api_usage',
            [
                'site_id' => $site_id,
                'estimation_id' => $estimation_id,
                'api_name' => $api_name,
                'units' => max(1, (int) $units),
                'cost_usd' => $cost,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%d', '%f', '%s']
        );
    }

    private function sanitize_api_name($name) {
        $allowed = ['serpapi', 'firecrawl', 'imgbb', 'gemini'];
        $name = strtolower(trim((string) $name));
        return in_array($name, $allowed, true) ? $name : '';
    }

    /**
     * Estime le coût pour N unités en fonction du volume mensuel actuel.
     */
    private function estimate_cost_for_units($api_name, $units, $site_id) {
        $monthly = $this->get_monthly_totals_by_api($site_id);
        $current = (int) ($monthly[$api_name]['units'] ?? 0);
        $tiers = self::$PRICING_TIERS[$api_name] ?? [];
        if (empty($tiers)) {
            return null;
        }
        $price_per_unit = $this->get_price_for_volume($api_name, $current + $units);
        return $price_per_unit !== null ? round($price_per_unit * $units, 6) : null;
    }

    /**
     * Prix unitaire pour un volume donné (selon palier).
     */
    public function get_price_for_volume($api_name, $monthly_volume) {
        $tiers = self::$PRICING_TIERS[$api_name] ?? [];
        foreach ($tiers as $t) {
            if ($monthly_volume >= $t['min'] && ($t['max'] === null || $monthly_volume <= $t['max'])) {
                return $t['price'];
            }
        }
        return !empty($tiers) ? end($tiers)['price'] : null;
    }

    /**
     * Prochain palier (seuil à partir duquel le prix unitaire change).
     */
    public function get_next_tier_threshold($api_name, $current_volume) {
        $tiers = self::$PRICING_TIERS[$api_name] ?? [];
        $current_price = $this->get_price_for_volume($api_name, $current_volume);
        foreach ($tiers as $t) {
            if ($t['min'] > $current_volume) {
                return ['min' => $t['min'], 'price' => $t['price']];
            }
        }
        return null;
    }

    /**
     * Totaux du mois en cours par API.
     */
    public function get_monthly_totals_by_api($site_id = null) {
        $this->ensure_table_exists();
        $site_id = $site_id ?? get_current_blog_id();
        $table = $this->prefix . 'api_usage';
        $month_start = gmdate('Y-m-01 00:00:00');

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT api_name, SUM(units) as units, SUM(cost_usd) as cost_usd FROM $table WHERE site_id = %d AND created_at >= %s GROUP BY api_name",
            $site_id,
            $month_start
        ), OBJECT_K);

        $out = [];
        foreach (['serpapi', 'firecrawl', 'imgbb', 'gemini'] as $api) {
            $r = $rows[$api] ?? null;
            $out[$api] = [
                'units' => (int) ($r->units ?? 0),
                'cost_usd' => (float) ($r->cost_usd ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Nombre de requêtes (analyses) ce mois.
     */
    public function get_monthly_request_count($site_id = null) {
        $this->ensure_table_exists();
        $site_id = $site_id ?? get_current_blog_id();
        $table = $this->prefix . 'api_usage';
        $month_start = gmdate('Y-m-01 00:00:00');

        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT estimation_id) FROM $table WHERE site_id = %d AND created_at >= %s AND estimation_id IS NOT NULL",
            $site_id,
            $month_start
        ));
    }

    /**
     * Dernières requêtes avec détail par API (pour affichage "dernière requête").
     */
    public function get_last_request_detail($site_id = null) {
        $this->ensure_table_exists();
        $site_id = $site_id ?? get_current_blog_id();
        $table = $this->prefix . 'api_usage';

        $last_est_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT estimation_id FROM $table WHERE site_id = %d AND estimation_id IS NOT NULL ORDER BY created_at DESC LIMIT 1",
            $site_id
        ));
        if (!$last_est_id) {
            return null;
        }

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT api_name, units, cost_usd, created_at FROM $table WHERE site_id = %d AND estimation_id = %d ORDER BY api_name",
            $site_id,
            $last_est_id
        ));

        $total_cost = 0;
        $by_api = [];
        foreach ($rows as $r) {
            $api = $r->api_name;
            $u = (int) $r->units;
            $c = (float) ($r->cost_usd ?? 0);
            if (isset($by_api[$api])) {
                $by_api[$api]['units'] += $u;
                $by_api[$api]['cost_usd'] += $c;
            } else {
                $by_api[$api] = ['units' => $u, 'cost_usd' => $c];
            }
            $total_cost += $c;
        }
        return [
            'estimation_id' => (int) $last_est_id,
            'by_api' => $by_api,
            'total_cost_usd' => round($total_cost, 4),
            'created_at' => $rows[0]->created_at ?? null,
        ];
    }

    /**
     * Prix d'une requête (analyse complète) selon le volume mensuel.
     * Une requête = 1 SerpAPI + 3 Firecrawl + 1 Gemini (ImgBB gratuit).
     *
     * @return array [['min' => int, 'max' => int|null, 'label' => string, 'price_usd' => float], ...]
     */
    public static function get_request_price_tiers() {
        $tiers = [
            ['min' => 0, 'max' => 500, 'label' => 'Moins de 500 requêtes/mois'],
            ['min' => 501, 'max' => 1000, 'label' => '501 à 1 000 requêtes/mois'],
            ['min' => 1001, 'max' => 3000, 'label' => '1 001 à 3 000 requêtes/mois'],
            ['min' => 3001, 'max' => 5000, 'label' => '3 001 à 5 000 requêtes/mois'],
            ['min' => 5001, 'max' => null, 'label' => 'Au-delà de 5 000 requêtes/mois'],
        ];
        $usage = new self();
        foreach ($tiers as &$t) {
            $n = $t['max'] !== null ? (int) (($t['min'] + $t['max']) / 2) : 7500;
            $serp_vol = $n;
            $fire_vol = $n * 3;
            $gemini_vol = $n;
            $serp = $usage->get_price_for_volume('serpapi', $serp_vol) ?? 0;
            $fire = $usage->get_price_for_volume('firecrawl', $fire_vol) ?? 0;
            $gemini = $usage->get_price_for_volume('gemini', $gemini_vol) ?? 0;
            $t['price_usd'] = round($serp + (3 * $fire) + $gemini, 4);
        }
        return $tiers;
    }

    /**
     * Consommation par API pour un site et une période.
     *
     * @param int $site_id
     * @param string $month_start Y-m-d H:i:s
     * @param string $month_end Y-m-d H:i:s
     * @return array ['by_api' => [...], 'total_usd' => float, 'analyses_count' => int]
     */
    public function get_consumption_for_period($site_id, $month_start, $month_end) {
        $current = get_current_blog_id();
        if (is_multisite() && $site_id !== $current) {
            switch_to_blog($site_id);
        }
        $this->ensure_table_exists();
        $table = $this->prefix . 'api_usage';
        $sid = is_multisite() ? $site_id : get_current_blog_id();

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT api_name, SUM(units) as units, SUM(cost_usd) as cost_usd FROM $table WHERE site_id = %d AND created_at >= %s AND created_at <= %s GROUP BY api_name",
            $sid, $month_start, $month_end
        ), OBJECT_K);

        $by_api = [];
        $total_usd = 0;
        foreach (['serpapi', 'firecrawl', 'imgbb', 'gemini'] as $api) {
            $r = $rows[$api] ?? null;
            $u = (int) ($r->units ?? 0);
            $c = (float) ($r->cost_usd ?? 0);
            $by_api[$api] = ['units' => $u, 'cost_usd' => $c];
            $total_usd += $c;
        }

        $analyses_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT estimation_id) FROM $table WHERE site_id = %d AND created_at >= %s AND created_at <= %s AND estimation_id IS NOT NULL",
            $sid, $month_start, $month_end
        ));

        if (is_multisite() && $site_id !== $current) {
            restore_current_blog();
        }

        return ['by_api' => $by_api, 'total_usd' => round($total_usd, 4), 'analyses_count' => $analyses_count];
    }

    /**
     * Consommation de tous les clients (sites) pour un mois.
     * En single-site, retourne uniquement le site actuel.
     * En multisite, retourne tous les sites (sauf si $current_only=true pour un site enfant).
     *
     * @param string $month YYYY-MM
     * @param bool $all_clients En multisite, false = uniquement le site actuel
     * @return array [['site_id' => int, 'site_name' => string, 'by_api' => [...], 'total_usd' => float, 'analyses_count' => int], ...]
     */
    public function get_all_clients_consumption($month, $all_clients = true) {
        $month_start = $month . '-01 00:00:00';
        $month_end = date('Y-m-t 23:59:59', strtotime($month . '-01'));
        $out = [];

        if (is_multisite() && $all_clients) {
            $sites = get_sites(['number' => 500, 'orderby' => 'blog_id', 'order' => 'ASC']);
            foreach ($sites as $site) {
                $site_id = (int) $site->blog_id;
                $data = $this->get_consumption_for_period($site_id, $month_start, $month_end);
                $site_name = get_blog_option($site_id, 'blogname', 'Site ' . $site_id) ?: 'Site ' . $site_id;
                $out[] = array_merge(
                    ['site_id' => $site_id, 'site_name' => $site_name],
                    $data
                );
            }
        } else {
            $site_id = get_current_blog_id();
            $data = $this->get_consumption_for_period($site_id, $month_start, $month_end);
            $out[] = array_merge(
                ['site_id' => $site_id, 'site_name' => get_bloginfo('name') ?: 'Site ' . $site_id],
                $data
            );
        }

        return $out;
    }

    /**
     * Agrégat tous clients pour un mois.
     *
     * @param string $month YYYY-MM
     * @param bool $all_clients En multisite, false = uniquement le site actuel
     */
    public function get_aggregate_consumption($month, $all_clients = true) {
        $clients = $this->get_all_clients_consumption($month, $all_clients);
        $by_api = ['serpapi' => ['units' => 0, 'cost_usd' => 0], 'firecrawl' => ['units' => 0, 'cost_usd' => 0], 'imgbb' => ['units' => 0, 'cost_usd' => 0], 'gemini' => ['units' => 0, 'cost_usd' => 0]];
        $total_usd = 0;
        $analyses_count = 0;
        foreach ($clients as $c) {
            foreach ($c['by_api'] as $api => $d) {
                $by_api[$api]['units'] += $d['units'];
                $by_api[$api]['cost_usd'] += $d['cost_usd'];
            }
            $total_usd += $c['total_usd'];
            $analyses_count += $c['analyses_count'];
        }
        return ['by_api' => $by_api, 'total_usd' => round($total_usd, 4), 'analyses_count' => $analyses_count, 'clients_count' => count($clients)];
    }

    /**
     * Libellés des APIs.
     */
    public static function get_api_labels() {
        return [
            'serpapi' => 'SerpAPI (Google Lens)',
            'firecrawl' => 'Firecrawl',
            'imgbb' => 'ImgBB',
            'gemini' => 'Gemini',
        ];
    }

    /**
     * Retourne les paliers pour affichage.
     */
    public static function get_pricing_tiers() {
        return self::$PRICING_TIERS;
    }

    /**
     * Récupère les paliers tarifaires de l'analyse IA (configurés sur le site parent).
     *
     * @param int|null $site_id
     * @return array ['free_granted' => int, 'tiers' => [['min_paid' => int, 'price' => float], ...]]
     */
    public static function get_analysis_pricing_tiers($site_id = null) {
        $read_site = $site_id ?? get_current_blog_id();
        if (is_multisite() && !is_main_site($read_site)) {
            switch_to_blog(1);
        }
        $free = (int) get_option('lmd_free_estimations_granted', 20);
        $tiers_raw = get_option('lmd_analysis_pricing_tiers', []);
        if (is_multisite() && !is_main_site($read_site)) {
            restore_current_blog();
        }
        $tiers = [];
        if (!empty($tiers_raw) && is_array($tiers_raw)) {
            foreach ($tiers_raw as $t) {
                $min = isset($t['min_paid']) ? (int) $t['min_paid'] : 0;
                $price = isset($t['price']) ? (float) $t['price'] : 0.50;
                $tiers[] = ['min_paid' => $min, 'price' => $price];
            }
            usort($tiers, function ($a, $b) { return $a['min_paid'] - $b['min_paid']; });
        }
        if (empty($tiers)) {
            $tiers = [
                ['min_paid' => 0, 'price' => 0.50],
                ['min_paid' => 20, 'price' => 0.33],
                ['min_paid' => 50, 'price' => 0.25],
            ];
        }
        return ['free_granted' => $free, 'tiers' => $tiers];
    }

    /**
     * Prix unitaire pour la N-ième analyse payante (N = 0, 1, 2...).
     */
    public static function get_price_for_paid_rank($paid_rank, $site_id = null) {
        $cfg = self::get_analysis_pricing_tiers($site_id);
        $tiers = $cfg['tiers'];
        $price = 0.50;
        foreach ($tiers as $t) {
            if ($paid_rank >= $t['min_paid']) {
                $price = $t['price'];
            }
        }
        return $price;
    }

    /**
     * Résumé de consommation simplifié : gratuites + paliers tarifaires configurables.
     * Comptage gratuit / payant, période mensuelle, montant HT.
     *
     * @param int|null $site_id Site à interroger (multisite).
     * @param string|null $month_ym Mois cible `Y-m` (ex. 2025-03). Null = mois civil courant (GMT).
     * @return array
     */
    public function get_consumption_billing_summary($site_id = null, $month_ym = null) {
        $current_site = get_current_blog_id();
        $target_site = $site_id ?? $current_site;
        if ($target_site !== $current_site && is_multisite()) {
            switch_to_blog($target_site);
        }
        $this->ensure_table_exists();
        $site_id = $target_site;
        $table = $this->prefix . 'api_usage';
        $cfg = self::get_analysis_pricing_tiers($site_id);
        $free_granted = $cfg['free_granted'];

        if ($month_ym === null) {
            $month_ym = gmdate('Y-m');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $month_ym)) {
            $month_ym = gmdate('Y-m');
        }
        $month_start = $month_ym . '-01 00:00:00';
        $month_end = date('Y-m-t 23:59:59', strtotime($month_ym . '-01'));

        $total_analyses = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT estimation_id) FROM $table WHERE site_id = %d AND estimation_id IS NOT NULL",
            $site_id
        ));

        $analyses_before_month = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT estimation_id) FROM $table WHERE site_id = %d AND estimation_id IS NOT NULL AND created_at < %s",
            $site_id,
            $month_start
        ));

        $analyses_this_month = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT estimation_id) FROM $table WHERE site_id = %d AND estimation_id IS NOT NULL AND created_at >= %s AND created_at <= %s",
            $site_id,
            $month_start,
            $month_end
        ));

        $free_used_total = min($free_granted, $total_analyses);
        $paid_total = max(0, $total_analyses - $free_granted);

        $free_before_month = min($free_granted, $analyses_before_month);
        $free_remaining_for_month = max(0, $free_granted - $free_before_month);
        $free_this_month = min($free_remaining_for_month, $analyses_this_month);
        $paid_this_month = max(0, $analyses_this_month - $free_this_month);
        $paid_before_month = max(0, $analyses_before_month - $free_before_month);

        $amount_ht_this_month = 0;
        for ($i = 0; $i < $paid_this_month; $i++) {
            $rank = $paid_before_month + $i;
            $amount_ht_this_month += self::get_price_for_paid_rank($rank, $site_id);
        }
        $amount_ht_this_month = round($amount_ht_this_month, 2);
        $price_per_paid = $paid_this_month > 0 ? round($amount_ht_this_month / $paid_this_month, 2) : self::get_price_for_paid_rank(0, $site_id);

        $summary = [
            'free_granted' => $free_granted,
            'free_used_total' => $free_used_total,
            'paid_total' => $paid_total,
            'total_analyses' => $total_analyses,
            'month_start' => $month_start,
            'month_end' => $month_end,
            'month_ym' => $month_ym,
            'month_label' => wp_date('F Y', strtotime($month_ym . '-01')),
            'analyses_this_month' => $analyses_this_month,
            'free_this_month' => $free_this_month,
            'paid_this_month' => $paid_this_month,
            'amount_ht_this_month' => $amount_ht_this_month,
            'price_per_paid' => $price_per_paid,
            'pricing_tiers' => self::get_analysis_pricing_tiers($site_id),
        ];

        if ($month_ym === gmdate('Y-m')) {
            update_option('lmd_consumption_summary', $summary);
        }
        if ($target_site !== $current_site && is_multisite()) {
            restore_current_blog();
        }
        return $summary;
    }

    /**
     * Rapport marge par produit (site parent / direction) pour un mois donné.
     * Produit « Aide à l'estimation » : CA HT (paliers + gratuits) agrégé, coût API réel ($ → €).
     *
     * @param string $month_ym Format Y-m.
     * @param bool   $all_clients Multisite : tous les sites ou seulement le courant.
     * @return array
     */
    public function get_parent_product_margin_report($month_ym, $all_clients = true) {
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $month_ym)) {
            $month_ym = gmdate('Y-m');
        }
        $fx = (float) get_option('lmd_margin_usd_to_eur', 0.92);
        if ($fx <= 0) {
            $fx = 0.92;
        }

        $agg = $this->get_aggregate_consumption($month_ym, $all_clients);
        $cost_usd = (float) $agg['total_usd'];
        $qty = (int) $agg['analyses_count'];

        $revenue_eur = 0.0;
        $paid_sum = 0;
        $sites_detail = [];

        if (is_multisite() && $all_clients) {
            $sites = get_sites(['number' => 500, 'orderby' => 'blog_id', 'order' => 'ASC']);
            foreach ($sites as $site) {
                $sid = (int) $site->blog_id;
                switch_to_blog($sid);
                $u = new self();
                $s = $u->get_consumption_billing_summary($sid, $month_ym);
                restore_current_blog();
                $revenue_eur += (float) $s['amount_ht_this_month'];
                $paid_sum += (int) $s['paid_this_month'];
                $sites_detail[] = [
                    'site_id' => $sid,
                    'site_name' => get_blog_option($sid, 'blogname', '') ?: ('Site ' . $sid),
                    'revenue_eur' => round((float) $s['amount_ht_this_month'], 2),
                    'analyses_month' => (int) $s['analyses_this_month'],
                    'paid_month' => (int) $s['paid_this_month'],
                ];
            }
        } else {
            $sid = get_current_blog_id();
            $s = $this->get_consumption_billing_summary($sid, $month_ym);
            $revenue_eur = (float) $s['amount_ht_this_month'];
            $paid_sum = (int) $s['paid_this_month'];
            $sites_detail[] = [
                'site_id' => $sid,
                'site_name' => get_bloginfo('name') ?: ('Site ' . $sid),
                'revenue_eur' => round((float) $s['amount_ht_this_month'], 2),
                'analyses_month' => (int) $s['analyses_this_month'],
                'paid_month' => (int) $s['paid_this_month'],
            ];
        }

        $revenue_eur = round($revenue_eur, 2);
        $cost_eur = round($cost_usd * $fx, 2);
        $margin_eur = round($revenue_eur - $cost_eur, 2);
        $margin_pct = $revenue_eur > 0 ? round(100 * $margin_eur / $revenue_eur, 1) : null;

        $avg_cost_per_analysis_eur = $qty > 0 ? round($cost_eur / $qty, 4) : 0.0;
        $avg_price_per_paid_eur = $paid_sum > 0 ? round($revenue_eur / $paid_sum, 4) : 0.0;
        $avg_revenue_per_analysis_eur = $qty > 0 ? round($revenue_eur / $qty, 4) : 0.0;

        return [
            'month_ym' => $month_ym,
            'fx_usd_to_eur' => $fx,
            'products' => [
                [
                    'id' => 'estimation',
                    'label' => 'Aide à l\'estimation',
                    'quantity' => $qty,
                    'revenue_eur' => $revenue_eur,
                    'cost_usd' => round($cost_usd, 4),
                    'cost_eur' => $cost_eur,
                    'margin_eur' => $margin_eur,
                    'margin_pct' => $margin_pct,
                    'avg_revenue_per_analysis_eur' => $avg_revenue_per_analysis_eur,
                    'avg_cost_per_analysis_eur' => $avg_cost_per_analysis_eur,
                    'avg_price_paid_estimation_eur' => $avg_price_per_paid_eur,
                    'paid_analyses_total' => $paid_sum,
                ],
            ],
            'totals' => [
                'revenue_eur' => $revenue_eur,
                'cost_eur' => $cost_eur,
                'margin_eur' => $margin_eur,
            ],
            'sites' => $sites_detail,
        ];
    }
}

/**
 * Met à jour le résumé de consommation (pour le site parent).
 * Appelé après chaque analyse réussie.
 */
function lmd_update_consumption_summary() {
    if (class_exists('LMD_Api_Usage')) {
        $u = new LMD_Api_Usage();
        $u->get_consumption_billing_summary();
    }
}

/**
 * Retourne le résumé de consommation pour le site parent.
 * Données mises à jour après chaque analyse et à l'affichage de la page Ressources IA.
 *
 * @param int|null $site_id En multisite, ID du site à interroger
 * @return array
 */
function lmd_get_consumption_summary($site_id = null) {
    if (!class_exists('LMD_Api_Usage')) {
        return [];
    }
    $u = new LMD_Api_Usage();
    return $u->get_consumption_billing_summary($site_id);
}
