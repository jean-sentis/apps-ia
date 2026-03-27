<?php
/**
 * Marge par produit — site parent (testable : CA HT vs coût API, export CSV).
 */
if (!defined('ABSPATH')) {
    exit;
}
$lmd_suite_embed = !empty($lmd_suite_embed);
if (is_multisite() && !is_main_site()) {
    wp_die('Cette page est réservée au site parent.');
}

$usage = class_exists('LMD_Api_Usage') ? new LMD_Api_Usage() : null;
$month = isset($_GET['month']) ? sanitize_text_field(wp_unslash($_GET['month'])) : gmdate('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = gmdate('Y-m');
}

$fx = (float) get_option('lmd_margin_usd_to_eur', 0.92);

$all_clients = !is_multisite() || get_current_blog_id() === 1;
$report = $usage ? $usage->get_parent_product_margin_report($month, $all_clients) : null;
$export_url = wp_nonce_url(
    admin_url('admin-post.php?action=lmd_export_product_margin&month=' . rawurlencode($month)),
    'lmd_export_product_margin'
);
$product = $report && !empty($report['products'][0]) ? $report['products'][0] : null;
?>
<?php if (!$lmd_suite_embed) : ?>
<div class="wrap lmd-page">
<?php endif; ?>
    <?php if (isset($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible"><p>Réglage enregistré.</p></div>
    <?php endif; ?>
    <?php if (!$lmd_suite_embed) : ?>
    <h1>Marge par produit</h1>
    <?php else : ?>
    <h2 class="lmd-ui-section-title">Marge par produit</h2>
    <?php endif; ?>
    <p class="lmd-ui-prose">
        Synthèse pour la <strong>direction</strong> : chiffre d’affaires HT facturable (paliers d’estimation sur tous les sites)
        face au <strong>coût API</strong> enregistré pour la période. Conversion USD → € via le coefficient ci-dessous (ajustez selon votre taux de change).
    </p>

    <div class="lmd-ui-toolbar">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:inline-flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0;">
        <input type="hidden" name="page" value="<?php echo $lmd_suite_embed ? 'lmd-apps-ia' : 'lmd-product-margin'; ?>" />
        <?php if ($lmd_suite_embed) : ?>
        <input type="hidden" name="hub_tab" value="margin" />
        <?php endif; ?>
        <label>Mois <input type="month" name="month" value="<?php echo esc_attr($month); ?>" /></label>
        <button type="submit" class="button">Afficher</button>
        <?php if ($usage) : ?>
        <a href="<?php echo esc_url($export_url); ?>" class="button button-primary">Exporter CSV (compta)</a>
        <?php endif; ?>
        </form>
    </div>

    <div class="lmd-ui-panel">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="lmd_save_margin_fx" />
        <?php wp_nonce_field('lmd_margin_fx', 'lmd_margin_fx_nonce'); ?>
        <input type="hidden" name="redirect_month" value="<?php echo esc_attr($month); ?>" />
        <?php if ($lmd_suite_embed) : ?>
        <input type="hidden" name="redirect_lmd_hub" value="1" />
        <?php endif; ?>
        <label>
            Coefficient USD → € (1 $ US × ce nombre = €)
            <input type="text" name="lmd_margin_usd_to_eur" value="<?php echo esc_attr((string) $fx); ?>" style="width:6em;" />
        </label>
        <button type="submit" class="button">Enregistrer</button>
    </form>
    </div>

    <?php if ($report && $product) : ?>
    <h2 class="lmd-ui-section-title">Résumé — <?php echo esc_html($month); ?></h2>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;max-width:920px;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Produit</th>
                <th>Analyses (mois)</th>
                <th>CA HT (€)</th>
                <th>Coût API ($)</th>
                <th>Coût API (€)</th>
                <th>Marge (€)</th>
                <th>Marge %</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo esc_html($product['label']); ?></td>
                <td><?php echo (int) $product['quantity']; ?></td>
                <td><?php echo esc_html(number_format($product['revenue_eur'], 2, ',', ' ')); ?></td>
                <td><?php echo esc_html(number_format($product['cost_usd'], 4, ',', ' ')); ?></td>
                <td><?php echo esc_html(number_format($product['cost_eur'], 2, ',', ' ')); ?></td>
                <td><strong><?php echo esc_html(number_format($product['margin_eur'], 2, ',', ' ')); ?></strong></td>
                <td><?php echo $product['margin_pct'] !== null ? esc_html((string) $product['margin_pct']) . ' %' : '—'; ?></td>
            </tr>
        </tbody>
    </table>
    </div>

    <div class="lmd-ui-panel">
    <h3 style="margin-top:0;">Indicateurs (réel sur la période)</h3>
    <ul style="list-style:disc;margin-left:1.25em;">
        <li>CA moyen par analyse (toutes analyses du mois) : <strong><?php echo esc_html(number_format($product['avg_revenue_per_analysis_eur'], 4, ',', ' ')); ?> €</strong></li>
        <li>Coût API moyen par analyse : <strong><?php echo esc_html(number_format($product['avg_cost_per_analysis_eur'], 4, ',', ' ')); ?> €</strong></li>
        <li>Prix moyen sur les analyses <em>payantes</em> du mois : <strong><?php echo $product['paid_analyses_total'] > 0 ? esc_html(number_format($product['avg_price_paid_estimation_eur'], 4, ',', ' ')) . ' €' : '—'; ?></strong> (<?php echo (int) $product['paid_analyses_total']; ?> payante(s))</li>
    </ul>
    </div>

    <?php if (!empty($report['sites']) && count($report['sites']) > 1) : ?>
    <h2 class="lmd-ui-section-title">Détail par site</h2>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Site</th>
                <th>ID</th>
                <th>Analyses (mois)</th>
                <th>Payantes (mois)</th>
                <th>CA HT (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report['sites'] as $row) : ?>
            <tr>
                <td><?php echo esc_html($row['site_name']); ?></td>
                <td><?php echo (int) $row['site_id']; ?></td>
                <td><?php echo (int) $row['analyses_month']; ?></td>
                <td><?php echo (int) $row['paid_month']; ?></td>
                <td><?php echo esc_html(number_format($row['revenue_eur'], 2, ',', ' ')); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <p>Aucune donnée de marge à afficher (plugin usage ou table API manquante).</p>
    <?php endif; ?>
<?php if (!$lmd_suite_embed) : ?>
</div>
<?php endif; ?>
