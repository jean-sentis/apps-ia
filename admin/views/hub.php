<?php
/**
 * Vue d'ensemble — bandeau noir + onglets outils (parent) + applications.
 * Pas d'onglet « Vue d'ensemble » : l'accueil par défaut affiche directement le texte + grille apps.
 */
if (!defined('ABSPATH')) {
    exit;
}

$is_parent = !is_multisite() || get_current_blog_id() === 1;
$lmd_hub_is_admin = current_user_can('manage_options');
$lmd_hub_apps_only = !$lmd_hub_is_admin;

$hub_tab = isset($_GET['hub_tab']) ? sanitize_key(wp_unslash($_GET['hub_tab'])) : '';
$hub_tabs_tools = ['apis', 'consumption', 'margin'];
if ($hub_tab !== '' && !in_array($hub_tab, $hub_tabs_tools, true)) {
    $hub_tab = '';
}
$hub_tools_open = in_array($hub_tab, $hub_tabs_tools, true);

$lmd_suite_banner_title = 'Mes applications utilisant des modèles d\'I.A.';
$lmd_suite_banner_subtitle = 'Le Marteau Digital — LMD Apps IA';

$hub_url = admin_url('admin.php?page=lmd-apps-ia');

$hub_feature_usage = null;
if ($lmd_hub_is_admin && $is_parent && class_exists('LMD_Activity_Analytics')) {
    $hub_act = new LMD_Activity_Analytics();
    $hub_feature_usage = $hub_act->get_feature_usage(date('Y-m'), is_multisite() && get_current_blog_id() === 1);
}
?>
<div class="wrap lmd-suite-hub lmd-page lmd-suite-hub-page">
    <?php if (!$lmd_hub_apps_only) : ?>
    <?php require LMD_PLUGIN_DIR . 'admin/views/partials/lmd-suite-banner.php'; ?>

    <?php if ($is_parent) : ?>
    <?php /* Même principe que l’admin raisonnée (lmd-ic-tabs + lmd-ic-tab-cartouche) : le liseré englobe la cartouche ; seul l’onglet actif s’y raccorde (masque le trait du haut sur sa largeur). */ ?>
    <div class="lmd-suite-hub-tools-col <?php echo $hub_tools_open ? 'lmd-suite-hub-tools-col--has-open' : ''; ?>">
        <nav class="lmd-suite-hub-tabs lmd-suite-hub-tabs--liseret" aria-label="<?php esc_attr_e('Configuration et pilotage réseau', 'lmd-apps-ia'); ?>">
            <a class="lmd-suite-hub-tab <?php echo $hub_tab === 'apis' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('hub_tab', 'apis', $hub_url)); ?>"><?php esc_html_e('Configuration des APIs', 'lmd-apps-ia'); ?></a>
            <a class="lmd-suite-hub-tab <?php echo $hub_tab === 'consumption' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('hub_tab', 'consumption', $hub_url)); ?>"><?php esc_html_e('Consommation IA', 'lmd-apps-ia'); ?></a>
            <a class="lmd-suite-hub-tab <?php echo $hub_tab === 'margin' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('hub_tab', 'margin', $hub_url)); ?>"><?php esc_html_e('Marge par produit', 'lmd-apps-ia'); ?></a>
        </nav>

        <?php if (!$hub_tools_open) : ?>
        <div class="lmd-suite-hub-tab-cartouche lmd-suite-hub-tab-cartouche--neutral">
            <div class="lmd-suite-hub-intro">
                <p class="lmd-ui-prose" style="margin:0;">Les réglages <strong>réseau</strong> (clés, agrégats, marge) sont dans les onglets ci-dessus. Chaque <strong>application</strong> a son propre espace dans le menu.</p>
            </div>
        </div>
        <?php else : ?>
        <div class="lmd-suite-hub-tab-cartouche lmd-suite-hub-tab-cartouche--open lmd-suite-hub-tab-cartouche--<?php echo esc_attr($hub_tab); ?>">
            <?php
            $lmd_suite_embed = true;
            if ($hub_tab === 'apis') {
                include LMD_PLUGIN_DIR . 'admin/views/api-config.php';
            } elseif ($hub_tab === 'consumption') {
                include LMD_PLUGIN_DIR . 'admin/views/consumption.php';
            } else {
                include LMD_PLUGIN_DIR . 'admin/views/product-margin.php';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
    <?php else : ?>
    <div class="lmd-ui-panel lmd-suite-hub-intro">
        <p class="lmd-ui-prose" style="margin:0;">Sur ce site, la configuration des APIs et les agrégats réseau sont gérés sur le <strong>site parent</strong>. Utilisez les applications ci-dessous.</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <section class="lmd-suite-hub-section" aria-labelledby="lmd-hub-apps">
        <h2 id="lmd-hub-apps" class="lmd-suite-hub-h2">Applications</h2>
        <div class="lmd-suite-hub-grid">
            <article class="lmd-suite-hub-card lmd-suite-hub-card--app">
                <div class="lmd-suite-hub-card-icon dashicons dashicons-visibility" aria-hidden="true"></div>
                <h3>Aide à l’estimation</h3>
                <p>Demandes, analyses IA, planning ventes, vendeurs, préférences.</p>
                <a class="button button-primary" href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('list') : admin_url('admin.php?page=lmd-app-estimation')); ?>"><?php esc_html_e('Ouvrir l’application', 'lmd-apps-ia'); ?></a>
            </article>
            <article class="lmd-suite-hub-card lmd-suite-hub-card--app">
                <div class="lmd-suite-hub-card-icon dashicons dashicons-chart-line" aria-hidden="true"></div>
                <h3>Enrichissement SEO</h3>
                <p><?php esc_html_e('Réglages d’éligibilité et de génération SEO pour les CPT lot.', 'lmd-apps-ia'); ?></p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=lmd-app-seo')); ?>"><?php esc_html_e('Ouvrir l’application', 'lmd-apps-ia'); ?></a>
            </article>
        </div>
    </section>

    <?php if (!$lmd_hub_apps_only && $is_parent && !empty($hub_feature_usage)) : ?>
    <section class="lmd-suite-hub-section" aria-labelledby="lmd-hub-usage-features">
        <h2 id="lmd-hub-usage-features" class="lmd-suite-hub-h2"><?php esc_html_e('Usage des fonctionnalités (mois en cours, réseau)', 'lmd-apps-ia'); ?></h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:960px;">
            <div style="padding:16px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;">
                <h3 style="margin:0 0 8px;font-size:14px;color:#991b1b;"><?php esc_html_e('Peu ou pas utilisées', 'lmd-apps-ia'); ?></h3>
                <p style="margin:0;font-size:13px;line-height:1.45;color:#7f1d1d;"><?php
                $labels = $hub_feature_usage['labels'] ?? [];
                $least = array_map(function ($k) use ($labels, $hub_feature_usage) {
                    $n = $hub_feature_usage['by_feature'][$k] ?? 0;
                    return ($labels[$k] ?? $k) . ' (' . $n . ')';
                }, $hub_feature_usage['least_used'] ?? []);
                echo esc_html(implode(' · ', $least ?: ['—']));
                ?></p>
            </div>
            <div style="padding:16px;border-radius:10px;background:#ecfdf5;border:1px solid #6ee7b7;">
                <h3 style="margin:0 0 8px;font-size:14px;color:#065f46;"><?php esc_html_e('Très utilisées par les clients', 'lmd-apps-ia'); ?></h3>
                <p style="margin:0;font-size:13px;line-height:1.45;color:#064e3b;"><?php
                $most = array_map(function ($k) use ($labels, $hub_feature_usage) {
                    $n = $hub_feature_usage['by_feature'][$k] ?? 0;
                    return ($labels[$k] ?? $k) . ' (' . $n . ')';
                }, $hub_feature_usage['most_used'] ?? []);
                echo esc_html(implode(' · ', $most ?: ['—']));
                ?></p>
            </div>
        </div>
        <p style="margin:12px 0 0;font-size:13px;"><a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('dashboard', ['dash_sub' => 'stats']) . '#lmd-stats-usage' : admin_url('admin.php?page=lmd-app-estimation&tab=dashboard&dash_sub=stats#lmd-stats-usage')); ?>"><?php esc_html_e('Détail dans l’app Aide à l’estimation → Statistiques', 'lmd-apps-ia'); ?></a></p>
    </section>
    <?php endif; ?>

    <?php if (!$lmd_hub_apps_only && $is_parent && !is_multisite()) : ?>
    <section class="lmd-suite-hub-section" aria-label="<?php esc_attr_e('Outils direction', 'lmd-apps-ia'); ?>">
        <h2 class="lmd-suite-hub-h2"><?php esc_html_e('Direction (monosite)', 'lmd-apps-ia'); ?></h2>
        <div class="lmd-ui-panel" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-sandbox-tools')); ?>"><?php esc_html_e('Outils bac à sable', 'lmd-apps-ia'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-promotions')); ?>"><?php esc_html_e('Promotions', 'lmd-apps-ia'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-copy-export-import')); ?>"><?php esc_html_e('Copie client', 'lmd-apps-ia'); ?></a>
            <a class="button" href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('dashboard', ['dash_sub' => 'stats']) . '#lmd-stats-usage' : admin_url('admin.php?page=lmd-app-estimation&tab=dashboard&dash_sub=stats#lmd-stats-usage')); ?>"><?php esc_html_e('Statistiques d’usage (réseau)', 'lmd-apps-ia'); ?></a>
        </div>
    </section>
    <?php endif; ?>
</div>
