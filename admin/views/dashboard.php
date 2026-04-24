<?php
/**
 * Vue Tableau de bord — Cartouches en grille
 * En shell app : sous-onglets (Réglage affichages et réponses vendeurs, Statistiques, Pilotage réseau si parent). L’aide est un onglet principal de l’app (à côté Mes estimations, etc.).
 */
if (!defined('ABSPATH')) {
    exit;
}
$db = new LMD_Database();
$db->ensure_tags_seeded();
$db->ensure_pricing_ready();

$stats = class_exists('LMD_Dashboard_Stats') ? new LMD_Dashboard_Stats() : null;
$top_vendeurs = $stats ? $stats->get_top_vendeurs(10, date('Y-m')) : [];
$promotion = $stats ? LMD_Dashboard_Stats::get_client_promotion() : null;
$client_logo = $stats ? LMD_Dashboard_Stats::get_client_logo_url() : null;
$lmd_logo = $stats ? LMD_Dashboard_Stats::get_lmd_logo_url() : '';

$month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$consumption = [];
if (function_exists('lmd_get_consumption_summary')) {
    $consumption = lmd_get_consumption_summary(get_current_blog_id());
    if (empty($consumption) && class_exists('LMD_Api_Usage')) {
        $u = new LMD_Api_Usage();
        $consumption = $u->get_consumption_billing_summary(get_current_blog_id());
    }
}

global $wpdb;
$e = $wpdb->prefix . 'lmd_estimations';
$month_start = $month . '-01 00:00:00';
$month_end = date('Y-m-t', strtotime($month . '-01')) . ' 23:59:59';
$total_ce_mois = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $e WHERE site_id = %d AND created_at >= %s AND created_at <= %s",
    get_current_blog_id(),
    $month_start,
    $month_end
));
$is_parent = !is_multisite() || get_current_blog_id() === 1;
$can_access_network_pilotage = $is_parent && current_user_can('manage_options');
$lmd_inner_shell = !empty($lmd_inner_shell);
$hub_url = admin_url('admin.php?page=lmd-apps-ia');

$stats_from = isset($_GET['stats_from']) ? sanitize_text_field(wp_unslash($_GET['stats_from'])) : '';
$stats_to = isset($_GET['stats_to']) ? sanitize_text_field(wp_unslash($_GET['stats_to'])) : '';
if ($stats_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $stats_from)) {
    $stats_from = '';
}
if ($stats_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $stats_to)) {
    $stats_to = '';
}

$stats_all = isset($_GET['stats_all']) && (string) $_GET['stats_all'] === '1';
$stats_default_from = gmdate('Y') . '-01-01';
$stats_default_to = gmdate('Y-m-d');
if ($stats_all) {
    $stats_date_from = null;
    $stats_date_to = null;
} elseif ($stats_from !== '' && $stats_to !== '') {
    $stats_date_from = $stats_from;
    $stats_date_to = $stats_to;
} else {
    $stats_date_from = $stats_default_from;
    $stats_date_to = $stats_default_to;
}

$stats_kpi = null;
$stats_kpi_response = null;
if ($stats) {
    $dash_sub_early = $lmd_inner_shell && isset($_GET['dash_sub']) ? sanitize_key(wp_unslash($_GET['dash_sub'])) : '';
    if ($lmd_inner_shell && $dash_sub_early === 'stats') {
        $stats_kpi = $stats->get_kpi_etude_lots($stats_date_from, $stats_date_to);
        $stats_kpi_response = $stats->get_kpi_response_metrics($stats_date_from, $stats_date_to);
    }
}

$stats_usage_parent = null;
if ($stats && $lmd_inner_shell && isset($_GET['dash_sub']) && sanitize_key(wp_unslash($_GET['dash_sub'])) === 'stats' && $is_parent && (!is_multisite() || get_current_blog_id() === 1) && class_exists('LMD_Activity_Analytics')) {
    $act = new LMD_Activity_Analytics();
    $all_sites_usage = is_multisite() && get_current_blog_id() === 1;
    $month_prev_usage = date('Y-m', strtotime($month . '-01 -1 month'));
    $stats_usage_parent = [
        'agg' => $act->get_month_aggregates($month),
        'agg_prev' => $act->get_month_aggregates($month_prev_usage),
        'features' => $act->get_feature_usage($month, $all_sites_usage),
        'features_prev' => $act->get_feature_usage($month_prev_usage, $all_sites_usage),
    ];
}

$dash_sub = 'prefs';
$lmd_dashboard_sub_url = static function ($sub) {
    return '#';
};
if ($lmd_inner_shell) {
    $dash_sub = isset($_GET['dash_sub']) ? sanitize_key(wp_unslash($_GET['dash_sub'])) : 'prefs';
    $dash_allowed = ['prefs', 'stats'];
    if ($can_access_network_pilotage) {
        $dash_allowed[] = 'pilotage';
    }
    if (!in_array($dash_sub, $dash_allowed, true)) {
        $dash_sub = 'prefs';
    }
    $lmd_dashboard_sub_url = static function ($sub) use ($month, $stats_all, $stats_from, $stats_to, $stats_default_from, $stats_default_to) {
        $args = ['dash_sub' => $sub, 'month' => $month];
        if ($sub === 'stats') {
            if ($stats_all) {
                $args['stats_all'] = '1';
            } else {
                $args['stats_from'] = $stats_from !== '' ? $stats_from : $stats_default_from;
                $args['stats_to'] = $stats_to !== '' ? $stats_to : $stats_default_to;
            }
        }
        if (function_exists('lmd_app_estimation_admin_url')) {
            return lmd_app_estimation_admin_url('dashboard', $args);
        }
        return add_query_arg(
            array_merge(['page' => 'lmd-app-estimation', 'tab' => 'dashboard'], $args),
            admin_url('admin.php')
        );
    };
}

$lmd_notification_saved = false;
$lmd_notification_emails = function_exists('lmd_get_new_estimation_notification_emails_string') ? lmd_get_new_estimation_notification_emails_string() : '';
if (
    $lmd_inner_shell &&
    $dash_sub === 'prefs' &&
    isset($_POST['lmd_save_notification_settings']) &&
    check_admin_referer('lmd_save_notification_settings')
) {
    if (function_exists('lmd_save_new_estimation_notification_emails')) {
        lmd_save_new_estimation_notification_emails($_POST['lmd_new_estimation_notification_emails'] ?? '');
        $lmd_notification_emails = function_exists('lmd_get_new_estimation_notification_emails_string') ? lmd_get_new_estimation_notification_emails_string() : '';
        $lmd_notification_saved = true;
    }
}

$lmd_help_url_standalone = function_exists('lmd_app_estimation_admin_url')
    ? lmd_app_estimation_admin_url('help')
    : admin_url('admin.php?page=lmd-app-estimation&tab=help');
?>
<?php if (!$lmd_inner_shell) : ?>
<div class="wrap lmd-dashboard lmd-page">
<?php else : ?>
<div class="lmd-dashboard lmd-dashboard--inner">
<?php endif; ?>
    <?php if (!$lmd_inner_shell) : ?>
    <header class="lmd-dashboard-header">
        <div class="lmd-dashboard-logos">
            <?php if ($client_logo) : ?>
            <img src="<?php echo esc_url($client_logo); ?>" alt="Logo client" class="lmd-dashboard-logo-client" />
            <?php endif; ?>
            <?php if ($lmd_logo) : ?>
            <a href="https://lemarteaudigital.fr" target="_blank" rel="noopener" class="lmd-dashboard-logo-lmd">
                <img src="<?php echo esc_url($lmd_logo); ?>" alt="Le Marteau Digital" />
            </a>
            <?php endif; ?>
        </div>
        <p class="lmd-dashboard-tagline">Solution LMD Apps IA par Le Marteau Digital</p>
    </header>
    <?php endif; ?>

    <?php if (!$lmd_inner_shell) : ?>
    <nav class="lmd-dashboard-nav" aria-label="<?php esc_attr_e('Raccourcis tableau de bord', 'lmd-apps-ia'); ?>">
        <a href="#cartes"><?php esc_html_e('Cartouches', 'lmd-apps-ia'); ?></a>
        <a href="#lmd-stats-etude"><?php esc_html_e('Statistiques', 'lmd-apps-ia'); ?></a>
        <?php if ($is_parent) : ?><a href="#consumption"><?php esc_html_e('Consommation IA', 'lmd-apps-ia'); ?></a><?php endif; ?>
        <?php if ($is_parent && !is_multisite()) : ?><a href="#bac-a-sable"><?php esc_html_e('Bac à sable', 'lmd-apps-ia'); ?></a><?php endif; ?>
        <?php if ($promotion) : ?><a href="#promotion"><?php esc_html_e('Offre', 'lmd-apps-ia'); ?></a><?php endif; ?>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('new') : admin_url('admin.php?page=lmd-app-estimation&tab=new')); ?>"><?php esc_html_e('Nouvelle demande', 'lmd-apps-ia'); ?></a>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('list') : admin_url('admin.php?page=lmd-app-estimation&tab=list')); ?>"><?php esc_html_e('Mes estimations', 'lmd-apps-ia'); ?></a>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('ventes') : admin_url('admin.php?page=lmd-app-estimation&tab=ventes')); ?>"><?php esc_html_e('Planning ventes', 'lmd-apps-ia'); ?></a>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('vendeurs') : admin_url('admin.php?page=lmd-app-estimation&tab=vendeurs')); ?>"><?php esc_html_e('Liste vendeurs', 'lmd-apps-ia'); ?></a>
        <a href="<?php echo esc_url($lmd_help_url_standalone); ?>"><?php esc_html_e('Aide', 'lmd-apps-ia'); ?></a>
    </nav>

    <div id="cartes" class="lmd-dashboard-grid">
        <div class="lmd-dashboard-card lmd-card-actions">
            <h3><?php esc_html_e('Accès rapide', 'lmd-apps-ia'); ?></h3>
            <div class="lmd-card-links">
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('new') : admin_url('admin.php?page=lmd-app-estimation&tab=new')); ?>" class="button button-primary"><?php esc_html_e('Nouvelle demande', 'lmd-apps-ia'); ?></a>
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('list') : admin_url('admin.php?page=lmd-app-estimation&tab=list')); ?>" class="button"><?php esc_html_e('Mes estimations', 'lmd-apps-ia'); ?></a>
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('ventes') : admin_url('admin.php?page=lmd-app-estimation&tab=ventes')); ?>" class="button"><?php esc_html_e('Planning ventes', 'lmd-apps-ia'); ?></a>
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('vendeurs') : admin_url('admin.php?page=lmd-app-estimation&tab=vendeurs')); ?>" class="button"><?php esc_html_e('Liste vendeurs', 'lmd-apps-ia'); ?></a>
                <?php if ($is_parent) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-consumption')); ?>" class="button"><?php esc_html_e('Consommation IA', 'lmd-apps-ia'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-product-margin')); ?>" class="button"><?php esc_html_e('Marge par produit', 'lmd-apps-ia'); ?></a>
                <?php endif; ?>
                <?php if ($is_parent && !is_multisite()) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-sandbox-tools')); ?>" class="button"><?php esc_html_e('Outils bac à sable', 'lmd-apps-ia'); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="lmd-dashboard-card lmd-card-summary">
            <h3><?php echo esc_html(sprintf(__('Résumé %s', 'lmd-apps-ia'), wp_date('F Y', strtotime($month . '-01')))); ?></h3>
            <p class="lmd-card-big"><?php echo (int) $total_ce_mois; ?></p>
            <p class="lmd-card-label"><?php esc_html_e('demandes ce mois', 'lmd-apps-ia'); ?></p>
            <p><label><?php esc_html_e('Mois', 'lmd-apps-ia'); ?> <input type="month" id="lmd-dashboard-month" value="<?php echo esc_attr($month); ?>" /></label></p>
        </div>

        <?php if ($is_parent) : ?>
        <div class="lmd-dashboard-card lmd-card-consumption" id="consumption">
            <h3><?php esc_html_e('Consommation IA', 'lmd-apps-ia'); ?></h3>
            <?php if (!empty($consumption)) : ?>
            <p class="lmd-card-big"><?php echo esc_html(number_format($consumption['amount_ht_this_month'] ?? 0, 2, ',', ' ')); ?> €</p>
            <p class="lmd-card-label"><?php esc_html_e('HT ce mois', 'lmd-apps-ia'); ?></p>
            <p><?php echo esc_html(sprintf(
                __('Analyses : %1$d — dont %2$d gratuites.', 'lmd-apps-ia'),
                (int) ($consumption['analyses_this_month'] ?? 0),
                (int) ($consumption['free_this_month'] ?? 0)
            )); ?></p>
            <?php else : ?>
            <p><?php esc_html_e('Aucune consommation ce mois.', 'lmd-apps-ia'); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-consumption')); ?>"><?php esc_html_e('Détail & export', 'lmd-apps-ia'); ?></a>
                · <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-product-margin')); ?>"><?php esc_html_e('Marge par produit', 'lmd-apps-ia'); ?></a></p>
        </div>
        <?php endif; ?>

        <?php if ($is_parent && !is_multisite()) : ?>
        <div class="lmd-dashboard-card lmd-card-sandbox" id="bac-a-sable">
            <h3><?php esc_html_e('Bac à sable', 'lmd-apps-ia'); ?></h3>
            <p class="lmd-card-label"><?php esc_html_e('Générez des données de test (sans multisite) pour valider marge, facturation et CSV.', 'lmd-apps-ia'); ?></p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-sandbox-tools')); ?>" class="button button-primary"><?php esc_html_e('Outils bac à sable', 'lmd-apps-ia'); ?></a></p>
        </div>
        <?php endif; ?>

        <?php if ($promotion) : ?>
        <div class="lmd-dashboard-card lmd-card-promo" id="promotion">
            <h3><?php esc_html_e('Offre spéciale', 'lmd-apps-ia'); ?></h3>
            <?php if ($promotion['type'] === 'ristourne' && !empty($promotion['amount'])) : ?>
            <p class="lmd-card-badge">−<?php echo esc_html(number_format($promotion['amount'], 0, ',', ' ')); ?> €</p>
            <?php elseif ($promotion['type'] === 'gratuites' && !empty($promotion['amount'])) : ?>
            <p class="lmd-card-badge"><?php echo (int) $promotion['amount']; ?> <?php esc_html_e('gratuites', 'lmd-apps-ia'); ?></p>
            <?php endif; ?>
            <?php if (!empty($promotion['message'])) : ?>
            <p><?php echo esc_html($promotion['message']); ?></p>
            <?php elseif ($promotion['type'] === 'gratuites') : ?>
            <p><?php echo esc_html(sprintf(__('Les %d prochaines estimations gratuites.', 'lmd-apps-ia'), (int) $promotion['amount'])); ?></p>
            <?php elseif ($promotion['type'] === 'ristourne') : ?>
            <p><?php esc_html_e('Sur la prochaine facture.', 'lmd-apps-ia'); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($top_vendeurs)) : ?>
        <div class="lmd-dashboard-card lmd-card-vendeurs">
            <h3><?php echo esc_html(sprintf(__('Gros vendeurs (%s)', 'lmd-apps-ia'), wp_date('F Y', strtotime($month . '-01')))); ?></h3>
            <ul class="lmd-card-list">
            <?php foreach (array_slice($top_vendeurs, 0, 5) as $v) : ?>
                <li><a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? add_query_arg(['filter_vendeur' => [$v['slug']]], lmd_app_estimation_admin_url('list')) : admin_url('admin.php?page=lmd-app-estimation&tab=list&filter_vendeur[]=' . urlencode($v['slug']))); ?>"><?php echo esc_html($v['name']); ?></a> <span><?php echo (int) $v['cnt']; ?></span></li>
            <?php endforeach; ?>
            </ul>
            <p><a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('vendeurs') : admin_url('admin.php?page=lmd-app-estimation&tab=vendeurs')); ?>"><?php esc_html_e('Voir tous', 'lmd-apps-ia'); ?></a></p>
        </div>
        <?php endif; ?>
    </div>

    <?php else : ?>

    <nav class="lmd-dashboard-subtabs" id="lmd-dashboard-subtabs" aria-label="<?php esc_attr_e('Sections du tableau de bord', 'lmd-apps-ia'); ?>">
        <a class="lmd-dashboard-subtab <?php echo $dash_sub === 'prefs' ? 'is-active' : ''; ?>" href="<?php echo esc_url($lmd_dashboard_sub_url('prefs')); ?>"><?php esc_html_e('Réglage affichages et réponses vendeurs', 'lmd-apps-ia'); ?></a>
        <a class="lmd-dashboard-subtab <?php echo $dash_sub === 'stats' ? 'is-active' : ''; ?>" href="<?php echo esc_url($lmd_dashboard_sub_url('stats')); ?>"><?php esc_html_e('Statistiques', 'lmd-apps-ia'); ?></a>
        <?php if ($can_access_network_pilotage) : ?>
        <a class="lmd-dashboard-subtab <?php echo $dash_sub === 'pilotage' ? 'is-active' : ''; ?>" href="<?php echo esc_url($lmd_dashboard_sub_url('pilotage')); ?>"><?php esc_html_e('Pilotage réseau', 'lmd-apps-ia'); ?></a>
          <?php endif; ?>
    </nav>

    <div class="lmd-dashboard-dash-gutter">
    <?php if ($dash_sub === 'prefs') : ?>
        <?php
        $lmd_prefs_embed = true;
        include LMD_PLUGIN_DIR . 'admin/views/preferences.php';
        ?>
        <section class="lmd-dashboard-notifications">
            <h2 class="lmd-ui-section-title"><?php esc_html_e('Notifications', 'lmd-apps-ia'); ?></h2>
            <p class="description"><?php esc_html_e('Indiquez ici une ou plusieurs adresses email qui recevront une notification pour chaque nouvelle demande envoyee depuis le formulaire public de ce site. Separez les adresses par des virgules.', 'lmd-apps-ia'); ?></p>
            <?php if ($lmd_notification_saved) : ?>
            <div class="notice notice-success"><p><?php esc_html_e('Destinataires des notifications enregistres.', 'lmd-apps-ia'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('dashboard', ['dash_sub' => 'prefs']) : admin_url('admin.php?page=lmd-app-estimation&tab=dashboard&dash_sub=prefs')); ?>" class="lmd-dashboard-notifications-form">
                <?php wp_nonce_field('lmd_save_notification_settings'); ?>
                <input type="hidden" name="lmd_save_notification_settings" value="1" />
                <p>
                    <label for="lmd-new-estimation-notification-emails"><strong><?php esc_html_e('Emails destinataires', 'lmd-apps-ia'); ?></strong></label><br />
                    <input type="text" id="lmd-new-estimation-notification-emails" name="lmd_new_estimation_notification_emails" value="<?php echo esc_attr($lmd_notification_emails); ?>" class="regular-text lmd-dashboard-notifications-input" placeholder="cp1@maison.fr, cp2@maison.fr" />
                </p>
                <p class="description"><?php esc_html_e('Exemple : cp@maison.fr, assistant@maison.fr. Ce reglage est propre a ce site enfant.', 'lmd-apps-ia'); ?></p>
                <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Enregistrer', 'lmd-apps-ia'); ?></button></p>
            </form>
        </section>
    <?php elseif ($dash_sub === 'stats') : ?>
    <?php
    $stats_dialog_from = $stats_all ? $stats_default_from : ($stats_from !== '' ? $stats_from : $stats_default_from);
    $stats_dialog_to = $stats_all ? $stats_default_to : ($stats_to !== '' ? $stats_to : $stats_default_to);
    $stats_period_oneline = $stats_all
        ? esc_html__('Historique complet', 'lmd-apps-ia')
        : esc_html(sprintf(
            __('Du %1$s au %2$s', 'lmd-apps-ia'),
            $stats_date_from !== null ? $stats_date_from : $stats_default_from,
            $stats_date_to !== null ? $stats_date_to : $stats_default_to
        ));
    $lmd_render_stat_cartouche = static function ($title, $value_html, $extra_class = '') use ($stats_period_oneline) {
        if (strpos($value_html, '<') === false) {
            $value_html = '<span class="lmd-stat-cartouche-value-main">' . $value_html . '</span>';
        }
        ?>
        <article class="lmd-stat-cartouche <?php echo esc_attr($extra_class); ?>">
            <h3 class="lmd-stat-cartouche-title"><?php echo esc_html($title); ?></h3>
            <div class="lmd-stat-cartouche-body lmd-stat-cartouche-body--row">
                <button type="button" class="button button-small lmd-stats-open-period lmd-stats-cal-btn lmd-stats-cal-btn--period" aria-haspopup="dialog" aria-controls="lmd-stats-period-dialog" title="<?php esc_attr_e('Modifier la période', 'lmd-apps-ia'); ?>">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e('Période', 'lmd-apps-ia'); ?></span>
                </button>
                <span class="lmd-stat-cartouche-period-inline">
                    <span class="lmd-stat-period-range"><?php echo $stats_period_oneline; ?></span>
                </span>
                <span class="lmd-stat-cartouche-value"><?php echo $value_html; ?></span>
            </div>
        </article>
        <?php
    };
    $stats_apply_url_all = function_exists('lmd_app_estimation_admin_url')
        ? lmd_app_estimation_admin_url('dashboard', ['dash_sub' => 'stats', 'stats_all' => '1', 'month' => $month])
        : add_query_arg(['page' => 'lmd-app-estimation', 'tab' => 'dashboard', 'dash_sub' => 'stats', 'stats_all' => '1', 'month' => $month], admin_url('admin.php'));
    ?>
    <dialog id="lmd-stats-period-dialog" class="lmd-stats-period-dialog">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="lmd-stats-period-form-dialog" id="lmd-stats-period-form">
            <input type="hidden" name="page" value="lmd-app-estimation" />
            <input type="hidden" name="tab" value="dashboard" />
            <input type="hidden" name="dash_sub" value="stats" />
            <input type="hidden" name="month" value="<?php echo esc_attr($month); ?>" />
            <p class="lmd-stats-dialog-title"><?php esc_html_e('Période des statistiques', 'lmd-apps-ia'); ?></p>
            <p class="lmd-stats-dialog-dates">
                <label><?php esc_html_e('Du', 'lmd-apps-ia'); ?> <input type="date" name="stats_from" id="lmd-stats-dialog-from" value="<?php echo esc_attr($stats_dialog_from); ?>" /></label>
                <label><?php esc_html_e('au', 'lmd-apps-ia'); ?> <input type="date" name="stats_to" id="lmd-stats-dialog-to" value="<?php echo esc_attr($stats_dialog_to); ?>" /></label>
            </p>
            <p class="lmd-stats-dialog-allhist">
                <label><input type="checkbox" name="stats_all" id="lmd-stats-dialog-allhist" value="1" <?php checked($stats_all); ?> /> <?php esc_html_e('Tout l’historique', 'lmd-apps-ia'); ?></label>
            </p>
            <p class="lmd-stats-dialog-actions">
                <button type="submit" class="button button-primary" id="lmd-stats-apply-btn"><?php esc_html_e('Appliquer', 'lmd-apps-ia'); ?></button>
                <a class="button" href="<?php echo esc_url($stats_apply_url_all); ?>"><?php esc_html_e('Tout l’historique', 'lmd-apps-ia'); ?></a>
                <button type="button" class="button" id="lmd-stats-close-period"><?php esc_html_e('Fermer', 'lmd-apps-ia'); ?></button>
            </p>
        </form>
    </dialog>

    <?php if (is_array($stats_kpi)) : ?>
    <section class="lmd-stats-section-block" id="lmd-stats-etude">
    <h2 class="lmd-stats-section-title"><?php esc_html_e('INTÉRETS DE L’ÉTUDE ET LOTS DÉPOSÉS', 'lmd-apps-ia'); ?></h2>
    <div class="lmd-stats-cartouches-grid">
        <?php
        $lmd_render_stat_cartouche(__('Demandes d’estimation', 'lmd-apps-ia'), esc_html((string) (int) $stats_kpi['total']));
        $lmd_render_stat_cartouche(__('AVIS FAVORABLE', 'lmd-apps-ia'), esc_html((string) (int) $stats_kpi['favorable']));
        $lmd_render_stat_cartouche(__('Lots déposés à l’étude', 'lmd-apps-ia'), esc_html((string) (int) $stats_kpi['depose']));
        $lmd_render_stat_cartouche(
            __('Pourcentage favorable / demandes', 'lmd-apps-ia'),
            $stats_kpi['pct_favorable_sur_total'] !== null ? esc_html((string) $stats_kpi['pct_favorable_sur_total']) . ' %' : '—',
            'lmd-stat-cartouche--pct'
        );
        $lmd_render_stat_cartouche(
            __('Pourcentage déposés / favorable', 'lmd-apps-ia'),
            $stats_kpi['pct_depose_sur_favorable'] !== null ? esc_html((string) $stats_kpi['pct_depose_sur_favorable']) . ' %' : '—',
            'lmd-stat-cartouche--pct'
        );
        $lmd_render_stat_cartouche(
            __('Pourcentage déposés / demandes', 'lmd-apps-ia'),
            $stats_kpi['pct_depose_sur_total'] !== null ? esc_html((string) $stats_kpi['pct_depose_sur_total']) . ' %' : '—',
            'lmd-stat-cartouche--pct'
        );
        ?>
    </div>
    </section>
    <?php endif; ?>

    <?php if (is_array($stats_kpi_response)) : ?>
    <section class="lmd-stats-section-block" id="lmd-stats-delais">
    <h2 class="lmd-stats-section-title lmd-stats-section-title--delais"><?php esc_html_e('DÉLAIS DE RÉPONSE ET LOTS DÉPOSÉS', 'lmd-apps-ia'); ?></h2>
    <div class="lmd-stats-cartouches-grid lmd-stats-cartouches-grid--delais">
        <?php
        $del1 = $stats_kpi_response['avg_reply_display_all'] ? esc_html($stats_kpi_response['avg_reply_display_all']) : '—';
        $lmd_render_stat_cartouche(
            __('Délai moyen d’envoi de la réponse', 'lmd-apps-ia'),
            '<span class="lmd-stat-cartouche-value-main">' . $del1 . '</span>'
        );
        $del2 = $stats_kpi_response['avg_reply_display_favorable'] ? esc_html($stats_kpi_response['avg_reply_display_favorable']) : '—';
        $lmd_render_stat_cartouche(
            __('Délai moyen si avis favorable', 'lmd-apps-ia'),
            '<span class="lmd-stat-cartouche-value-main">' . $del2 . '</span>'
        );
        ?>
    </div>

    <h3 class="lmd-stats-subsection-title lmd-stats-subsection-title--with-cal">
        <span class="lmd-stats-subsection-title-text"><?php esc_html_e('Pourcentage de lots déposés à l’étude selon le délai de réponse au vendeur', 'lmd-apps-ia'); ?></span>
        <button type="button" class="button button-small lmd-stats-open-period lmd-stats-cal-btn" aria-haspopup="dialog" aria-controls="lmd-stats-period-dialog" title="<?php esc_attr_e('Modifier la période', 'lmd-apps-ia'); ?>"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span></button>
    </h3>
    <div class="lmd-stats-cartouches-grid lmd-stats-cartouches-grid--buckets-5">
        <?php foreach ($stats_kpi_response['favorable_depose_by_delay'] as $b) :
            $n_show = (int) $b['n_depose'];
            $pct_disp = $b['pct'] !== null ? esc_html((string) $b['pct']) . ' %' : '—';
            ?>
        <article class="lmd-stat-cartouche lmd-stat-cartouche--bucket-mini">
            <h4 class="lmd-stat-bucket-mini-title"><?php echo esc_html($b['label']); ?></h4>
            <p class="lmd-stat-bucket-mini-n"><?php echo (int) $n_show; ?></p>
            <p class="lmd-stat-bucket-mini-pct"><?php echo $pct_disp; ?></p>
        </article>
        <?php endforeach; ?>
    </div>
    </section>
    <?php endif; ?>

    <?php if ($stats_usage_parent && !empty($stats_usage_parent['agg'])) : ?>
    <section class="lmd-dashboard-section lmd-stats-usage-network" id="lmd-stats-usage" aria-labelledby="lmd-stats-usage-h">
        <?php
        $agg_u = $stats_usage_parent['agg'];
        $agg_prev = $stats_usage_parent['agg_prev'] ?? [];
        $fe = $stats_usage_parent['features']['by_feature'] ?? [];
        $fe_prev = $stats_usage_parent['features_prev']['by_feature'] ?? [];
        $det = (float) ($agg_u['detail_total_minutes'] ?? 0);
        $gr = (float) ($agg_u['grid_total_minutes'] ?? 0);
        $tot_min = $det + $gr;
        $det_p = (float) ($agg_prev['detail_total_minutes'] ?? 0);
        $gr_p = (float) ($agg_prev['grid_total_minutes'] ?? 0);
        $tot_min_prev = $det_p + $gr_p;
        $lmd_usage_num_trend = static function ($cur, $prev) {
            $c = (float) $cur;
            $p = (float) $prev;
            if (abs($c - $p) < 1e-6) {
                return '<span class="lmd-trend lmd-trend--flat" aria-hidden="true"></span>';
            }
            if ($c > $p) {
                return '<span class="lmd-trend lmd-trend--up" title="' . esc_attr__('Hausse par rapport au mois précédent', 'lmd-apps-ia') . '"><span class="lmd-trend-arrow" aria-hidden="true">↗</span></span>';
            }
            return '<span class="lmd-trend lmd-trend--down" title="' . esc_attr__('Baisse par rapport au mois précédent', 'lmd-apps-ia') . '"><span class="lmd-trend-arrow" aria-hidden="true">↘</span></span>';
        };
        $lmd_usage_month_hidden = ['page' => 'lmd-app-estimation', 'tab' => 'dashboard', 'dash_sub' => 'stats'];
        if ($stats_all) {
            $lmd_usage_month_hidden['stats_all'] = '1';
        } else {
            $lmd_usage_month_hidden['stats_from'] = $stats_from !== '' ? $stats_from : $stats_default_from;
            $lmd_usage_month_hidden['stats_to'] = $stats_to !== '' ? $stats_to : $stats_default_to;
        }
        $lmd_usage_has_features = !empty($stats_usage_parent['features']);
        ?>
        <h2 class="lmd-stats-usage-main-title" id="lmd-stats-usage-h">
            <span class="lmd-stats-usage-main-title-text"><?php esc_html_e('Usage du réseau du mois de', 'lmd-apps-ia'); ?></span>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="lmd-stats-usage-month-form lmd-stats-usage-month-form--title">
                <?php foreach ($lmd_usage_month_hidden as $hk => $hv) : ?>
                <input type="hidden" name="<?php echo esc_attr($hk); ?>" value="<?php echo esc_attr($hv); ?>" />
                <?php endforeach; ?>
                <label class="screen-reader-text" for="lmd-stats-usage-month-select"><?php esc_html_e('Mois affiché', 'lmd-apps-ia'); ?></label>
                <select name="month" id="lmd-stats-usage-month-select" class="lmd-stats-usage-month-select" onchange="this.form.submit()">
                    <?php
                    for ($mi = 0; $mi < 24; $mi++) {
                        $ts = strtotime($month . '-01 -' . $mi . ' months');
                        $ym = gmdate('Y-m', $ts);
                        $lab = wp_date('F Y', $ts);
                        ?>
                    <option value="<?php echo esc_attr($ym); ?>" <?php selected($month, $ym); ?>><?php echo esc_html($lab); ?></option>
                    <?php } ?>
                </select>
            </form>
        </h2>
        <div class="lmd-stats-usage-layout<?php echo $lmd_usage_has_features ? '' : ' lmd-stats-usage-layout--solo'; ?>">
            <div class="lmd-stats-usage-col lmd-stats-usage-col--third lmd-stats-usage-col--bubbles">
                <div class="lmd-stats-usage-bubbles lmd-stats-usage-bubbles--round">
                    <div class="lmd-stats-usage-bubble">
                        <span class="lmd-stats-usage-bubble-label"><?php esc_html_e('Temps passé', 'lmd-apps-ia'); ?></span>
                        <span class="lmd-stats-usage-bubble-trend"><?php echo $lmd_usage_num_trend($tot_min, $tot_min_prev); ?></span>
                        <span class="lmd-stats-usage-bubble-num"><?php echo esc_html(number_format($tot_min, 1, ',', ' ')); ?></span>
                        <span class="lmd-stats-usage-bubble-unit"><?php esc_html_e('min', 'lmd-apps-ia'); ?></span>
                    </div>
                    <div class="lmd-stats-usage-bubble">
                        <span class="lmd-stats-usage-bubble-label"><?php esc_html_e('Demandes', 'lmd-apps-ia'); ?></span>
                        <span class="lmd-stats-usage-bubble-trend"><?php echo $lmd_usage_num_trend((int) ($agg_u['count'] ?? 0), (int) ($agg_prev['count'] ?? 0)); ?></span>
                        <span class="lmd-stats-usage-bubble-num"><?php echo (int) ($agg_u['count'] ?? 0); ?></span>
                    </div>
                    <div class="lmd-stats-usage-bubble">
                        <span class="lmd-stats-usage-bubble-label"><?php esc_html_e('Réponses', 'lmd-apps-ia'); ?></span>
                        <span class="lmd-stats-usage-bubble-trend"><?php echo $lmd_usage_num_trend((int) ($fe['reponse'] ?? 0), (int) ($fe_prev['reponse'] ?? 0)); ?></span>
                        <span class="lmd-stats-usage-bubble-num"><?php echo (int) ($fe['reponse'] ?? 0); ?></span>
                    </div>
                    <div class="lmd-stats-usage-bubble">
                        <span class="lmd-stats-usage-bubble-label"><?php esc_html_e('Analyse IA', 'lmd-apps-ia'); ?></span>
                        <span class="lmd-stats-usage-bubble-trend"><?php echo $lmd_usage_num_trend((int) ($fe['analyse_ia'] ?? 0), (int) ($fe_prev['analyse_ia'] ?? 0)); ?></span>
                        <span class="lmd-stats-usage-bubble-num"><?php echo (int) ($fe['analyse_ia'] ?? 0); ?></span>
                        <span class="lmd-stats-usage-bubble-unit"><?php esc_html_e('lots', 'lmd-apps-ia'); ?></span>
                    </div>
                </div>
            </div>
            <?php if (!empty($stats_usage_parent['features'])) :
                $fu = $stats_usage_parent['features'];
                $labels = $fu['labels'] ?? [];
                ?>
            <div class="lmd-stats-usage-col lmd-stats-usage-col--third lmd-stats-usage-col--least">
                <h3 class="lmd-stats-usage-side-title"><?php esc_html_e('Ne sert pas souvent', 'lmd-apps-ia'); ?></h3>
                <ul class="lmd-stats-usage-side-list">
                    <?php foreach ($fu['least_used'] ?? [] as $k) :
                        $n = $fu['by_feature'][$k] ?? 0;
                        ?>
                    <li><?php echo esc_html(($labels[$k] ?? $k) . ' (' . $n . ')'); ?></li>
                    <?php endforeach; ?>
                    <?php if (empty($fu['least_used'])) : ?><li>—</li><?php endif; ?>
                </ul>
            </div>
            <div class="lmd-stats-usage-col lmd-stats-usage-col--third lmd-stats-usage-col--most">
                <h3 class="lmd-stats-usage-side-title"><?php esc_html_e('Les plus utilisés', 'lmd-apps-ia'); ?></h3>
                <ul class="lmd-stats-usage-side-list">
                    <?php foreach ($fu['most_used'] ?? [] as $k) :
                        $n = $fu['by_feature'][$k] ?? 0;
                        ?>
                    <li><?php echo esc_html(($labels[$k] ?? $k) . ' (' . $n . ')'); ?></li>
                    <?php endforeach; ?>
                    <?php if (empty($fu['most_used'])) : ?><li>—</li><?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php elseif ($dash_sub === 'pilotage' && $can_access_network_pilotage) : ?>
        <div class="lmd-dashboard-grid lmd-dashboard-grid--pilotage">
            <div class="lmd-dashboard-card lmd-card-consumption" id="consumption-inner">
                <h3><?php esc_html_e('Consommation IA', 'lmd-apps-ia'); ?></h3>
                <?php if (!empty($consumption)) : ?>
                <p class="lmd-card-big"><?php echo esc_html(number_format($consumption['amount_ht_this_month'] ?? 0, 2, ',', ' ')); ?> €</p>
                <p class="lmd-card-label"><?php esc_html_e('HT ce mois', 'lmd-apps-ia'); ?></p>
                <p><?php echo esc_html(sprintf(
                    __('Analyses : %1$d — dont %2$d gratuites.', 'lmd-apps-ia'),
                    (int) ($consumption['analyses_this_month'] ?? 0),
                    (int) ($consumption['free_this_month'] ?? 0)
                )); ?></p>
                <?php else : ?>
                <p><?php esc_html_e('Aucune consommation ce mois.', 'lmd-apps-ia'); ?></p>
                <?php endif; ?>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-apps-ia&hub_tab=consumption')); ?>" class="button"><?php esc_html_e('Détail & export', 'lmd-apps-ia'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-apps-ia&hub_tab=margin')); ?>" class="button"><?php esc_html_e('Marge par produit', 'lmd-apps-ia'); ?></a></p>
            </div>
            <?php if (!is_multisite()) : ?>
            <div class="lmd-dashboard-card lmd-card-sandbox" id="bac-a-sable-inner">
                <h3><?php esc_html_e('Bac à sable', 'lmd-apps-ia'); ?></h3>
                <p class="lmd-card-label"><?php esc_html_e('Données de test pour valider marge et facturation.', 'lmd-apps-ia'); ?></p>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-sandbox-tools')); ?>" class="button button-primary"><?php esc_html_e('Outils bac à sable', 'lmd-apps-ia'); ?></a></p>
            </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <?php
        $lmd_prefs_embed = true;
        include LMD_PLUGIN_DIR . 'admin/views/preferences.php';
        ?>
    <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

<style>
.lmd-dashboard-header { margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb; }
.lmd-dashboard-logos { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
.lmd-dashboard-logo-client { max-height: 48px; max-width: 180px; object-fit: contain; }
.lmd-dashboard-logo-lmd img { max-height: 36px; width: auto; }
.lmd-dashboard-tagline { margin: 8px 0 0; font-size: 13px; color: #6b7280; }
.lmd-dashboard-nav { margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 10px; }
.lmd-dashboard-nav a { padding: 8px 14px; background: #f3f4f6; border-radius: 6px; text-decoration: none; color: #374151; font-size: 13px; }
.lmd-dashboard-nav a:hover { background: #e5e7eb; }

.lmd-dashboard-subtabs { display: flex; flex-wrap: wrap; gap: 10px 12px; margin: 0 0 16px; padding: 0; align-items: center; }
.lmd-dashboard-subtab {
    display: inline-flex;
    align-items: center;
    padding: 10px 18px;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    background: #fff;
    color: #4b5563;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
    line-height: 1.2;
}
.lmd-dashboard-subtab:hover { background: #f9fafb; border-color: #d1d5db; color: #111827; }
.lmd-dashboard-subtab.is-active {
    background: #ecfdf5;
    border-color: #059669;
    color: #065f46;
    box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2);
}
.lmd-dashboard-dash-gutter {
    margin-top: 0;
    padding: 16px;
    background: #f0fdf4;
    border-radius: 12px;
    border: 1px solid #bbf7d0;
}
.lmd-dashboard-dash-gutter .lmd-stats-card {
    background: #fff;
    border-color: #e5e7eb;
}

.lmd-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; margin-bottom: 32px; }
.lmd-dashboard-grid--pilotage { margin-bottom: 0; }
.lmd-dashboard-card { min-width: 0; padding: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.lmd-dashboard-card h3 { margin: 0 0 12px; font-size: 14px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
.lmd-dashboard-card .lmd-card-big { font-size: 28px; font-weight: 700; color: #111827; margin: 0 0 4px; }
.lmd-dashboard-card .lmd-card-label { font-size: 12px; color: #6b7280; margin: 0 0 12px; }
.lmd-dashboard-card .lmd-card-badge { font-size: 22px; font-weight: 700; color: #22c55e; margin: 0 0 8px; }
.lmd-dashboard-card .lmd-card-links { display: flex; flex-direction: column; gap: 8px; }
.lmd-dashboard-card .lmd-card-links .button { width: 100%; text-align: center; }
.lmd-dashboard-card .lmd-card-list { margin: 0; padding-left: 18px; }
.lmd-dashboard-card .lmd-card-list li { margin-bottom: 4px; }
.lmd-dashboard-card .lmd-card-list span { color: #6b7280; font-size: 12px; margin-left: 4px; }
.lmd-dashboard:not(.lmd-dashboard--inner) .lmd-card-promo { background: #f0fdf4; border-color: #86efac; }
.lmd-dashboard-dash-gutter .lmd-card-promo { background: #fff; border-color: #86efac; }

.lmd-dashboard-section { margin-bottom: 32px; }
.lmd-dashboard-section h2 { margin: 0 0 16px; padding-top: 0; border-top: none; font-size: 18px; color: #111827; }
.lmd-dashboard-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.lmd-stats-card { min-width: 0; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
.lmd-stats-card h3 { margin: 0 0 12px; font-size: 13px; }
.lmd-stats-card table { margin: 0; font-size: 13px; }
.lmd-stats-cat-table th.lmd-stats-cat-n,
.lmd-stats-cat-table td.lmd-stats-cat-n { width: 3.2em; text-align: right; white-space: nowrap; }
.lmd-stats-cat-table th.lmd-stats-cat-p,
.lmd-stats-cat-table td.lmd-stats-cat-p { min-width: 7em; text-align: right; font-size: 12px; color: #64748b; white-space: nowrap; }

.lmd-stats-period-dialog { border: 1px solid #e5e7eb; border-radius: 12px; padding: 0; max-width: 420px; }
.lmd-stats-period-dialog::backdrop { background: rgba(15, 23, 42, 0.35); }
.lmd-stats-period-form-dialog { padding: 20px; margin: 0; }
.lmd-stats-dialog-title { margin: 0 0 8px; font-size: 16px; font-weight: 600; }
.lmd-stats-dialog-dates { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin: 12px 0; }
.lmd-stats-dialog-actions { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0 0; }

.lmd-stats-open-period .dashicons { line-height: 1; vertical-align: middle; }
.lmd-stats-section-block { margin-bottom: 8px; }
.lmd-stats-section-title { font-size: 15px; letter-spacing: 0.04em; margin: 24px 0 16px; color: #0f172a; font-weight: 800; text-align: center; }
.lmd-stats-section-title--delais { margin-top: 36px; }
.lmd-stats-subsection-title { font-size: 14px; font-weight: 700; text-align: center; margin: 28px 0 14px; color: #334155; line-height: 1.35; }
.lmd-stats-subsection-title--with-cal { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px 14px; }
.lmd-stats-subsection-title-text { flex: 1 1 280px; text-align: center; min-width: 0; }
.lmd-stats-cartouches-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px; }
.lmd-stats-cartouches-grid--delais { grid-template-columns: repeat(2, minmax(0, 1fr)); max-width: 900px; margin-left: auto; margin-right: auto; }
@media (max-width: 960px) { .lmd-stats-cartouches-grid { grid-template-columns: 1fr; } .lmd-stats-cartouches-grid--delais { grid-template-columns: 1fr; } }
.lmd-stats-cartouches-grid--buckets-5 {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 128px));
    gap: 20px 22px;
    margin: 0 auto 24px;
    justify-content: center;
    width: 100%;
    max-width: 820px;
    padding: 4px 8px;
    box-sizing: border-box;
}
@media (max-width: 1200px) {
    .lmd-stats-cartouches-grid--buckets-5 {
        grid-template-columns: repeat(3, minmax(0, 128px));
        gap: 20px 22px;
        max-width: 480px;
    }
}
@media (max-width: 720px) {
    .lmd-stats-cartouches-grid--buckets-5 {
        grid-template-columns: repeat(2, minmax(0, 128px));
        max-width: 320px;
    }
}
@media (max-width: 480px) {
    .lmd-stats-cartouches-grid--buckets-5 {
        grid-template-columns: minmax(0, 128px);
        max-width: none;
    }
}
.lmd-stat-cartouche { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px 22px 20px; display: flex; flex-direction: column; min-height: 0; box-sizing: border-box; }
.lmd-stat-cartouche--pct { background: #f8fafc; }
.lmd-stat-cartouche--pct .lmd-stat-cartouche-value-main { color: #059669; font-size: 22px; }
.lmd-stat-cartouche-title { margin: 0 0 14px; font-size: 13px; font-weight: 700; color: #334155; text-align: center; line-height: 1.25; }
.lmd-stat-cartouche-body--row { display: flex; flex-direction: row; align-items: center; justify-content: flex-start; gap: 12px 14px; flex-wrap: nowrap; min-height: 44px; padding: 0 2px; }
.lmd-stat-cartouche-period-inline { display: inline-flex; align-items: center; gap: 10px; flex: 1 1 auto; min-width: 0; font-size: 12px; color: #475569; }
.lmd-stat-period-range { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lmd-stats-cal-btn { padding: 5px 7px !important; min-height: 0 !important; line-height: 1 !important; flex-shrink: 0; }
.lmd-stats-cal-btn--period { flex-shrink: 0; }
.lmd-stats-cal-btn .dashicons { width: 20px; height: 20px; font-size: 20px; }
.lmd-stat-cartouche-body--row .lmd-stat-cartouche-value { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; flex: 0 0 auto; margin-left: auto; text-align: right; min-width: 0; }
.lmd-stat-cartouche-value-main { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.1; }
.lmd-stat-cartouche--bucket-mini { padding: 16px 14px; text-align: center; }
.lmd-stat-bucket-mini-title { margin: 0 0 10px; font-size: 12px; font-weight: 700; color: #334155; line-height: 1.35; }
.lmd-stat-bucket-mini-n { margin: 0; font-size: 21px; font-weight: 800; color: #0f172a; line-height: 1.15; }
.lmd-stat-bucket-mini-pct { margin: 8px 0 0; font-size: 15px; font-weight: 700; color: #059669; }
.lmd-stats-dialog-allhist { margin: 12px 0 0; font-size: 13px; }
#lmd-stats-apply-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.lmd-stats-usage-network { padding-top: 2rem; margin-top: 1.25rem; border-top: 1px solid #e2e8f0; }
.lmd-stats-usage-main-title {
    width: 100%;
    margin: 0 0 20px;
    padding: 0 12px;
    box-sizing: border-box;
    text-align: center;
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 0.02em;
    line-height: 1.4;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px 14px;
}
.lmd-stats-usage-main-title-text { flex: 0 1 auto; }
.lmd-stats-usage-month-form--title { display: inline-flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 8px; margin: 0; }
.lmd-stats-usage-month-select { padding: 8px 14px; font-size: 14px; min-width: 200px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; }
.lmd-stats-usage-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.72fr) minmax(0, 0.72fr);
    gap: 24px 28px;
    align-items: center;
    justify-items: center;
    margin-top: 4px;
}
.lmd-stats-usage-layout--solo { grid-template-columns: 1fr; max-width: 320px; margin-left: auto; margin-right: auto; }
@media (max-width: 1100px) {
    .lmd-stats-usage-layout { grid-template-columns: 1fr; justify-items: stretch; }
    .lmd-stats-usage-layout--solo { max-width: none; }
    .lmd-stats-usage-col--third:not(.lmd-stats-usage-col--bubbles) { max-width: none; }
}
.lmd-stats-usage-col--third { min-width: 0; }
.lmd-stats-usage-col--third:not(.lmd-stats-usage-col--bubbles) {
    padding: 16px 14px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    max-width: 260px;
    width: 100%;
    text-align: center;
    justify-self: center;
    align-self: center;
    box-sizing: border-box;
}
.lmd-stats-usage-col--bubbles { display: flex; justify-content: center; padding: 0; background: transparent; border: none; justify-self: center; width: 100%; max-width: 320px; }
.lmd-stats-usage-bubbles--round {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px 24px;
    width: 100%;
    max-width: 300px;
    justify-items: center;
    align-items: center;
}
.lmd-stats-usage-bubbles--round .lmd-stats-usage-bubble {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    width: 100%;
    max-width: 132px;
    aspect-ratio: 1;
    margin: 0 auto;
    padding: 10px 7px;
    box-sizing: border-box;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.lmd-stats-usage-bubble-label { font-size: 10px; font-weight: 700; color: #64748b; line-height: 1.2; margin-bottom: 4px; max-width: 100%; }
.lmd-stats-usage-bubbles--round .lmd-stats-usage-bubble-label { font-size: 11px; font-weight: 800; letter-spacing: 0.02em; }
.lmd-stats-usage-bubble-trend { flex-shrink: 0; line-height: 1; }
.lmd-stats-usage-bubbles--round .lmd-trend-arrow { font-size: 22px; }
.lmd-stats-usage-bubble-num { font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.1; }
.lmd-stats-usage-bubbles--round .lmd-stats-usage-bubble-num { font-size: 21px; }
.lmd-stats-usage-bubble-unit { font-size: 9px; font-weight: 600; color: #94a3b8; margin-top: 2px; }
.lmd-stats-usage-bubbles--round .lmd-stats-usage-bubble-unit { font-size: 11px; font-weight: 700; }
.lmd-stats-usage-side-title { margin: 0 0 13px; font-size: 17px; font-weight: 800; color: #0f172a; text-align: center; line-height: 1.28; letter-spacing: 0.02em; }
.lmd-stats-usage-col--least .lmd-stats-usage-side-title { color: #991b1b; }
.lmd-stats-usage-col--most .lmd-stats-usage-side-title { color: #065f46; }
.lmd-stats-usage-side-list { margin: 0; padding: 0; list-style: none; font-size: 12px; line-height: 1.55; color: #475569; text-align: center; font-weight: 500; }
.lmd-stats-usage-side-list li { margin-bottom: 9px; }
.lmd-trend { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.lmd-trend-arrow { display: inline-block; font-size: 18px; line-height: 1; font-weight: 700; }
.lmd-trend--up .lmd-trend-arrow { color: #059669; }
.lmd-trend--down .lmd-trend-arrow { color: #dc2626; }
.lmd-trend--flat { width: 10px; height: 2px; background: #cbd5e1; border-radius: 1px; }
.lmd-dashboard-notifications { margin-top: 32px; padding-top: 24px; border-top: 1px solid #d1d5db; }
.lmd-dashboard-notifications-form { max-width: 720px; }
.lmd-dashboard-notifications-input { width: 100%; max-width: 100%; }
.lmd-dashboard-notifications { margin-top: 32px; padding-top: 24px; border-top: 1px solid #d1d5db; }
.lmd-dashboard-notifications-form { max-width: 720px; }
.lmd-dashboard-notifications-input { width: 100%; max-width: 100%; }
</style>

<script>
(function() {
    var monthEl = document.getElementById('lmd-dashboard-month');
    if (monthEl) {
        monthEl.addEventListener('change', function() {
            var m = this.value;
            if (!m) return;
            var base = <?php echo wp_json_encode(
                $lmd_inner_shell && function_exists('lmd_app_estimation_admin_url')
                    ? lmd_app_estimation_admin_url('dashboard', ['dash_sub' => $dash_sub])
                    : (function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('dashboard') : admin_url('admin.php?page=lmd-apps-ia'))
            ); ?>;
            window.location.href = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'month=' + encodeURIComponent(m);
        });
    }
    var dlg = document.getElementById('lmd-stats-period-dialog');
    var closeBtn = document.getElementById('lmd-stats-close-period');
    if (dlg && typeof dlg.showModal === 'function') {
        document.querySelectorAll('.lmd-stats-open-period').forEach(function(btn) {
            btn.addEventListener('click', function() { dlg.showModal(); });
        });
    }
    if (closeBtn && dlg) {
        closeBtn.addEventListener('click', function() { dlg.close(); });
    }
    var periodForm = document.getElementById('lmd-stats-period-form');
    var dFrom = document.getElementById('lmd-stats-dialog-from');
    var dTo = document.getElementById('lmd-stats-dialog-to');
    var dAll = document.getElementById('lmd-stats-dialog-allhist');
    var applyBtn = document.getElementById('lmd-stats-apply-btn');
    if (periodForm && dFrom && dTo && dAll && applyBtn) {
        function lmdStatsSyncApply() {
            var dis = dAll.checked;
            dFrom.disabled = dis;
            dTo.disabled = dis;
            var ok = dis || (dFrom.value && dTo.value && dFrom.value <= dTo.value);
            applyBtn.disabled = !ok;
        }
        dAll.addEventListener('change', lmdStatsSyncApply);
        dFrom.addEventListener('input', lmdStatsSyncApply);
        dTo.addEventListener('input', lmdStatsSyncApply);
        periodForm.addEventListener('submit', function() {
            if (dAll.checked) {
                dFrom.removeAttribute('name');
                dTo.removeAttribute('name');
            }
        });
        lmdStatsSyncApply();
    }
})();
</script>



