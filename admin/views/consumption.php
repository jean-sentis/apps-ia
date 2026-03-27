<?php
/**
 * Vue Consommation IA - Par client, agrégat, export, envoi mensuel (site parent uniquement)
 */
if (!defined('ABSPATH')) {
    exit;
}
$lmd_suite_embed = !empty($lmd_suite_embed);
if (is_multisite() && !is_main_site()) {
    wp_die('Cette page est réservée au site parent.');
}
$usage = class_exists('LMD_Api_Usage') ? new LMD_Api_Usage() : null;
$month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$all_clients = !is_multisite() || get_current_blog_id() === 1;
$clients = $usage ? $usage->get_all_clients_consumption($month, $all_clients) : [];
$agg = $usage ? $usage->get_aggregate_consumption($month, $all_clients) : ['by_api' => [], 'total_usd' => 0, 'analyses_count' => 0, 'clients_count' => 0];
$labels = $usage ? LMD_Api_Usage::get_api_labels() : [];
$export_url = wp_nonce_url(admin_url('admin-post.php?action=lmd_export_consumption&month=' . $month), 'lmd_export_consumption');

$read_site = is_multisite() ? 1 : get_current_blog_id();
if (is_multisite()) {
    switch_to_blog(1);
}
$monthly_email = get_option('lmd_consumption_monthly_email', '');
$monthly_enabled = (bool) get_option('lmd_consumption_monthly_enabled', false);
if (is_multisite()) {
    restore_current_blog();
}
if (isset($_POST['lmd_save_monthly']) && check_admin_referer('lmd_consumption_monthly', 'lmd_consumption_nonce')) {
    $monthly_email = sanitize_text_field(wp_unslash($_POST['lmd_monthly_email'] ?? ''));
    $monthly_enabled = !empty($_POST['lmd_monthly_enabled']);
    if (is_multisite()) {
        switch_to_blog(1);
    }
    update_option('lmd_consumption_monthly_email', $monthly_email);
    update_option('lmd_consumption_monthly_enabled', $monthly_enabled);
    if ($monthly_enabled && $monthly_email) {
        if (!wp_next_scheduled('lmd_monthly_consumption_report')) {
            wp_schedule_event(strtotime('first day of next month 09:00'), 'monthly', 'lmd_monthly_consumption_report');
        }
    } else {
        wp_clear_scheduled_hook('lmd_monthly_consumption_report');
    }
    if (is_multisite()) {
        restore_current_blog();
    }
    $dest = !empty($lmd_suite_embed)
        ? add_query_arg(['page' => 'lmd-apps-ia', 'hub_tab' => 'consumption', 'monthly_saved' => '1'], admin_url('admin.php'))
        : add_query_arg(['page' => 'lmd-consumption', 'monthly_saved' => '1'], admin_url('admin.php'));
    wp_safe_redirect($dest);
    exit;
}

$consumption_page_url = $lmd_suite_embed ? admin_url('admin.php?page=lmd-apps-ia&hub_tab=consumption') : admin_url('admin.php?page=lmd-consumption');
$monthly_form_action = $lmd_suite_embed ? admin_url('admin.php?page=lmd-apps-ia&hub_tab=consumption') : '';
?>
<?php if (!$lmd_suite_embed) : ?>
<div class="wrap lmd-page">
<?php endif; ?>
    <?php if (isset($_GET['monthly_saved'])) : ?>
    <div class="notice notice-success is-dismissible"><p>Réglages d’envoi mensuel enregistrés.</p></div>
    <?php endif; ?>
    <?php if (!$lmd_suite_embed) : ?>
    <h1>Consommation IA</h1>
    <?php else : ?>
    <h2 class="lmd-ui-section-title">Consommation IA</h2>
    <?php endif; ?>
    <p class="lmd-ui-prose">Synthèse multi-sites et export — même ligne graphique que le tableau de bord et Mes estimations.</p>

    <div class="lmd-ui-toolbar">
        <label>Mois <input type="month" id="lmd-consumption-month" value="<?php echo esc_attr($month); ?>" /></label>
        <a href="<?php echo esc_url($export_url); ?>" class="button button-primary">Exporter CSV</a>
    </div>

    <?php if ($usage && !empty($clients)) : ?>
    <h2 class="lmd-ui-section-title">Par client</h2>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Client</th>
                <th>Site</th>
                <th>Analyses</th>
                <?php foreach ($labels as $api => $label) : ?>
                <th><?php echo esc_html($label); ?> (unités)</th>
                <th><?php echo esc_html($label); ?> ($)</th>
                <?php endforeach; ?>
                <th>Total ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $c) : ?>
            <tr>
                <td><?php echo esc_html($c['site_name']); ?></td>
                <td><?php echo (int) $c['site_id']; ?></td>
                <td><?php echo (int) $c['analyses_count']; ?></td>
                <?php foreach (['serpapi', 'firecrawl', 'imgbb', 'gemini'] as $api) : ?>
                <td><?php echo (int) ($c['by_api'][$api]['units'] ?? 0); ?></td>
                <td><?php echo number_format($c['by_api'][$api]['cost_usd'] ?? 0, 4); ?></td>
                <?php endforeach; ?>
                <td><strong><?php echo number_format($c['total_usd'], 4); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="lmd-table-foot-total">
            <tr>
                <td>TOTAL</td>
                <td></td>
                <td><?php echo (int) $agg['analyses_count']; ?></td>
                <?php foreach (['serpapi', 'firecrawl', 'imgbb', 'gemini'] as $api) : ?>
                <td><?php echo (int) ($agg['by_api'][$api]['units'] ?? 0); ?></td>
                <td><?php echo number_format($agg['by_api'][$api]['cost_usd'] ?? 0, 4); ?></td>
                <?php endforeach; ?>
                <td><?php echo number_format($agg['total_usd'], 4); ?></td>
            </tr>
        </tfoot>
    </table>
    </div>

    <h2 class="lmd-ui-section-title">Tous les clients — agrégat</h2>
    <div class="lmd-ui-panel">
    <p style="margin:0;"><strong><?php echo (int) $agg['clients_count']; ?></strong> client(s) · <strong><?php echo (int) $agg['analyses_count']; ?></strong> analyse(s) · <strong><?php echo number_format($agg['total_usd'], 4); ?> $</strong> dépenses totales</p>
    </div>
    <?php endif; ?>

    <h2 class="lmd-ui-section-title">Envoi mensuel automatique</h2>
    <div class="lmd-ui-panel">
    <form method="post" action="<?php echo $lmd_suite_embed ? esc_url($monthly_form_action) : ''; ?>">
        <?php wp_nonce_field('lmd_consumption_monthly', 'lmd_consumption_nonce'); ?>
        <p>
            <label><input type="checkbox" name="lmd_monthly_enabled" value="1" <?php checked($monthly_enabled); ?> /> Activer l'envoi mensuel</label>
        </p>
        <p>
            <label>Email(s) destinataire(s) (séparés par des virgules) <input type="text" name="lmd_monthly_email" value="<?php echo esc_attr($monthly_email); ?>" class="regular-text" placeholder="admin@exemple.fr" /></label>
        </p>
        <p><button type="submit" name="lmd_save_monthly" class="button button-primary">Enregistrer</button></p>
    </form>
    <p class="description" style="margin-bottom:0;">Le rapport CSV sera envoyé le 1er de chaque mois à 9h.</p>
    </div>
<?php if (!$lmd_suite_embed) : ?>
</div>
<?php endif; ?>
<script>
(function(){
    var el = document.getElementById('lmd-consumption-month');
    if (!el) return;
    var base = <?php echo wp_json_encode($consumption_page_url); ?>;
    el.addEventListener('change', function() {
        var m = this.value;
        if (!m) return;
        var u = base.indexOf('?') >= 0 ? base + '&month=' + encodeURIComponent(m) : base + '?month=' + encodeURIComponent(m);
        window.location.href = u;
    });
})();
</script>
