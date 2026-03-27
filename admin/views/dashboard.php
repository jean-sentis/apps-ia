<?php
/**
 * Vue Tableau de bord — Cartouches en grille
 * Logos, menu ancres, stats, consommation, promotion
 */
if (!defined('ABSPATH')) {
    exit;
}
$db = new LMD_Database();
$db->ensure_tags_seeded();
$db->ensure_pricing_ready();

$stats = class_exists('LMD_Dashboard_Stats') ? new LMD_Dashboard_Stats() : null;
$category_stats = $stats ? $stats->get_stats_by_category(24) : ['interet' => [], 'estimation' => [], 'theme_vente' => []];
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
$type_labels = ['interet' => 'Intérêt', 'estimation' => 'Estimation', 'theme_vente' => 'Thème'];
$is_parent = !is_multisite() || get_current_blog_id() === 1;
$lmd_inner_shell = !empty($lmd_inner_shell);
?>
<?php if (!$lmd_inner_shell) : ?>
<div class="wrap lmd-dashboard lmd-page">
<?php else : ?>
<div class="lmd-dashboard lmd-dashboard--inner">
<?php endif; ?>
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
        <p class="lmd-dashboard-tagline"><?php echo $lmd_inner_shell ? 'Application Aide à l’estimation — Le Marteau Digital' : 'Solution LMD Apps IA par Le Marteau Digital'; ?></p>
    </header>

    <nav class="lmd-dashboard-nav">
        <a href="#cartes">Cartouches</a>
        <a href="#stats-categories">Statistiques</a>
        <?php if (!$lmd_inner_shell && $is_parent) : ?><a href="#consumption">Consommation IA</a><?php endif; ?>
        <?php if (!$lmd_inner_shell && $is_parent && !is_multisite()) : ?><a href="#bac-a-sable">Bac à sable</a><?php endif; ?>
        <?php if ($promotion) : ?><a href="#promotion">Offre</a><?php endif; ?>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('new') : admin_url('admin.php?page=lmd-new-estimation')); ?>">Nouvelle demande</a>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('list') : admin_url('admin.php?page=lmd-estimations-list')); ?>">Mes estimations</a>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('ventes') : admin_url('admin.php?page=lmd-ventes-list')); ?>">Planning ventes</a>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('vendeurs') : admin_url('admin.php?page=lmd-vendeurs-list')); ?>">Vendeurs</a>
        <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('help') : admin_url('admin.php?page=lmd-help')); ?>">Aide</a>
    </nav>

    <div id="cartes" class="lmd-dashboard-grid">
        <div class="lmd-dashboard-card lmd-card-actions">
            <h3>Accès rapide</h3>
            <div class="lmd-card-links">
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('new') : admin_url('admin.php?page=lmd-new-estimation')); ?>" class="button button-primary">Nouvelle demande</a>
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('list') : admin_url('admin.php?page=lmd-estimations-list')); ?>" class="button">Mes estimations</a>
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('ventes') : admin_url('admin.php?page=lmd-ventes-list')); ?>" class="button">Planning ventes</a>
                <a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('vendeurs') : admin_url('admin.php?page=lmd-vendeurs-list')); ?>" class="button">Vendeurs</a>
                <?php if (!$lmd_inner_shell && $is_parent) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-consumption')); ?>" class="button">Consommation IA</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-product-margin')); ?>" class="button">Marge par produit</a>
                <?php endif; ?>
                <?php if (!$lmd_inner_shell && $is_parent && !is_multisite()) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-sandbox-tools')); ?>" class="button">Outils bac à sable</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="lmd-dashboard-card lmd-card-summary">
            <h3>Résumé <?php echo esc_html(wp_date('F Y', strtotime($month . '-01'))); ?></h3>
            <p class="lmd-card-big"><?php echo (int) $total_ce_mois; ?></p>
            <p class="lmd-card-label">demandes ce mois</p>
            <p><label>Mois <input type="month" id="lmd-dashboard-month" value="<?php echo esc_attr($month); ?>" /></label></p>
        </div>

        <?php if (!$lmd_inner_shell && $is_parent) : ?>
        <div class="lmd-dashboard-card lmd-card-consumption" id="consumption">
            <h3>Consommation IA</h3>
            <?php if (!empty($consumption)) : ?>
            <p class="lmd-card-big"><?php echo esc_html(number_format($consumption['amount_ht_this_month'] ?? 0, 2, ',', ' ')); ?> €</p>
            <p class="lmd-card-label">HT ce mois</p>
            <p><?php echo (int) ($consumption['analyses_this_month'] ?? 0); ?> analyses (dont <?php echo (int) ($consumption['free_this_month'] ?? 0); ?> gratuites)</p>
            <?php else : ?>
            <p>Aucune consommation ce mois.</p>
            <?php endif; ?>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-consumption')); ?>">Détail & export</a>
                · <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-product-margin')); ?>">Marge par produit</a></p>
        </div>
        <?php endif; ?>

        <?php if (!$lmd_inner_shell && $is_parent && !is_multisite()) : ?>
        <div class="lmd-dashboard-card lmd-card-sandbox" id="bac-a-sable">
            <h3>Bac à sable</h3>
            <p class="lmd-card-label">Générez des données de test (sans multisite) pour valider marge, facturation et CSV.</p>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-sandbox-tools')); ?>" class="button button-primary">Outils bac à sable</a></p>
        </div>
        <?php endif; ?>

        <?php if ($promotion) : ?>
        <div class="lmd-dashboard-card lmd-card-promo" id="promotion">
            <h3>Offre spéciale</h3>
            <?php if ($promotion['type'] === 'ristourne' && !empty($promotion['amount'])) : ?>
            <p class="lmd-card-badge">−<?php echo esc_html(number_format($promotion['amount'], 0, ',', ' ')); ?> €</p>
            <?php elseif ($promotion['type'] === 'gratuites' && !empty($promotion['amount'])) : ?>
            <p class="lmd-card-badge"><?php echo (int) $promotion['amount']; ?> gratuites</p>
            <?php endif; ?>
            <?php if (!empty($promotion['message'])) : ?>
            <p><?php echo esc_html($promotion['message']); ?></p>
            <?php elseif ($promotion['type'] === 'gratuites') : ?>
            <p>Les <?php echo (int) $promotion['amount']; ?> prochaines estimations gratuites.</p>
            <?php elseif ($promotion['type'] === 'ristourne') : ?>
            <p>Sur la prochaine facture.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($top_vendeurs)) : ?>
        <div class="lmd-dashboard-card lmd-card-vendeurs">
            <h3>Gros vendeurs (<?php echo esc_html(wp_date('F Y', strtotime($month . '-01'))); ?>)</h3>
            <ul class="lmd-card-list">
            <?php foreach (array_slice($top_vendeurs, 0, 5) as $v) : ?>
                <li><a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? add_query_arg(['filter_vendeur' => [$v['slug']]], lmd_app_estimation_admin_url('list')) : admin_url('admin.php?page=lmd-estimations-list&filter_vendeur[]=' . urlencode($v['slug']))); ?>"><?php echo esc_html($v['name']); ?></a> <span><?php echo (int) $v['cnt']; ?></span></li>
            <?php endforeach; ?>
            </ul>
            <p><a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('vendeurs') : admin_url('admin.php?page=lmd-vendeurs-list')); ?>">Voir tous</a></p>
        </div>
        <?php endif; ?>
    </div>

    <section id="stats-categories" class="lmd-dashboard-section">
        <h2>Statistiques par catégorie</h2>
        <div class="lmd-dashboard-stats-grid">
            <?php foreach (['interet', 'estimation', 'theme_vente'] as $type) :
                $data = $category_stats[$type] ?? [];
                if (empty($data)) continue;
            ?>
            <div class="lmd-stats-card">
                <h3><?php echo esc_html($type_labels[$type]); ?></h3>
                <table class="widefat striped">
                    <thead><tr><th>Catégorie</th><th><?php echo esc_html($month); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($data as $slug => $info) :
                        $cnt = $info['months'][$month] ?? 0;
                        if ($cnt === 0 && empty(array_filter($info['months']))) continue;
                    ?>
                    <tr>
                        <td><?php echo esc_html($info['name']); ?></td>
                        <td><?php echo (int) $cnt; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
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

.lmd-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; margin-bottom: 32px; }
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
.lmd-card-promo { background: #f0fdf4; border-color: #86efac; }

.lmd-dashboard-section { margin-bottom: 32px; }
.lmd-dashboard-section h2 { margin: 0 0 16px; padding-top: 24px; border-top: 1px solid #e5e7eb; }
.lmd-dashboard-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.lmd-stats-card { min-width: 0; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; }
.lmd-stats-card h3 { margin: 0 0 12px; font-size: 13px; }
.lmd-stats-card table { margin: 0; font-size: 13px; }
</style>

<script>
document.getElementById('lmd-dashboard-month')?.addEventListener('change', function() {
    var m = this.value;
    if (!m) return;
    var base = '<?php echo esc_js(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('dashboard') : admin_url('admin.php?page=lmd-apps-ia')); ?>';
    window.location.href = base + '&month=' + encodeURIComponent(m);
});
</script>
