<?php
/**
 * Vue d'ensemble — bandeau noir + onglets outils (parent) + applications.
 * Pas d'onglet « Vue d'ensemble » : l'accueil par défaut affiche directement le texte + grille apps.
 */
if (!defined('ABSPATH')) {
    exit;
}
$is_parent = !is_multisite() || get_current_blog_id() === 1;

$hub_tab = isset($_GET['hub_tab']) ? sanitize_key(wp_unslash($_GET['hub_tab'])) : '';
$hub_tabs_tools = ['apis', 'consumption', 'margin'];
if ($hub_tab !== '' && !in_array($hub_tab, $hub_tabs_tools, true)) {
    $hub_tab = '';
}
$hub_tools_open = in_array($hub_tab, $hub_tabs_tools, true);

$lmd_suite_banner_title = 'Mes applications utilisant des modèles d\'I.A.';
$lmd_suite_banner_subtitle = 'Le Marteau Digital — LMD Apps IA';

$hub_url = admin_url('admin.php?page=lmd-apps-ia');
$splitscreen_active = post_type_exists('splitscreen');
?>
<div class="wrap lmd-suite-hub lmd-page lmd-suite-hub-page">
    <?php
    require LMD_PLUGIN_DIR . 'admin/views/partials/lmd-suite-banner.php';
    ?>

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

    <section class="lmd-suite-hub-section" aria-labelledby="lmd-hub-apps">
        <h2 id="lmd-hub-apps" class="lmd-suite-hub-h2">Applications</h2>
        <div class="lmd-suite-hub-grid">
            <article class="lmd-suite-hub-card lmd-suite-hub-card--app">
                <div class="lmd-suite-hub-card-icon dashicons dashicons-visibility" aria-hidden="true"></div>
                <h3>Aide à l’estimation</h3>
                <p>Demandes, analyses IA, planning ventes, vendeurs, préférences.</p>
                <a class="button button-primary" href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('dashboard') : admin_url('admin.php?page=lmd-app-estimation')); ?>"><?php esc_html_e('Ouvrir l’application', 'lmd-apps-ia'); ?></a>
            </article>
            <?php if ($splitscreen_active) : ?>
            <article class="lmd-suite-hub-card lmd-suite-hub-card--app">
                <div class="lmd-suite-hub-card-icon dashicons dashicons-format-gallery" aria-hidden="true"></div>
                <h3>Splitscreen</h3>
                <p>Montages 1440×650, photos, cartouches, génération IA, shortcode.</p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=splitscreen')); ?>"><?php esc_html_e('Ouvrir Splitscreen', 'lmd-apps-ia'); ?></a>
            </article>
            <?php else : ?>
            <article class="lmd-suite-hub-card lmd-suite-hub-card--soon">
                <h3>Splitscreen</h3>
                <p class="lmd-suite-hub-soon"><?php esc_html_e('Activez le plugin Splitscreen pour composer les montages ici.', 'lmd-apps-ia'); ?></p>
                <span class="button disabled" aria-disabled="true"><?php esc_html_e('Plugin requis', 'lmd-apps-ia'); ?></span>
            </article>
            <?php endif; ?>
            <article class="lmd-suite-hub-card lmd-suite-hub-card--soon">
                <h3>SEO</h3>
                <p class="lmd-suite-hub-soon"><?php esc_html_e('Espace dédié dans la roadmap suite.', 'lmd-apps-ia'); ?></p>
                <span class="button disabled" aria-disabled="true"><?php esc_html_e('Bientôt', 'lmd-apps-ia'); ?></span>
            </article>
        </div>
    </section>

    <?php if ($is_parent && !is_multisite()) : ?>
    <section class="lmd-suite-hub-section" aria-label="<?php esc_attr_e('Outils direction', 'lmd-apps-ia'); ?>">
        <h2 class="lmd-suite-hub-h2"><?php esc_html_e('Direction (monosite)', 'lmd-apps-ia'); ?></h2>
        <div class="lmd-ui-panel" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-sandbox-tools')); ?>"><?php esc_html_e('Outils bac à sable', 'lmd-apps-ia'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-promotions')); ?>"><?php esc_html_e('Promotions', 'lmd-apps-ia'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-copy-export-import')); ?>"><?php esc_html_e('Copie client', 'lmd-apps-ia'); ?></a>
            <a class="button" href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('activity') : admin_url('admin.php?page=lmd-activity')); ?>"><?php esc_html_e('Activité', 'lmd-apps-ia'); ?></a>
        </div>
    </section>
    <?php endif; ?>
</div>
