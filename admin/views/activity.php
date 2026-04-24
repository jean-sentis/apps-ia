<?php
/**
 * Vue Activité - Analytics d'usage, fonctionnalités, signature (site parent uniquement)
 */
if (!defined('ABSPATH')) {
    exit;
}
if (is_multisite() && !is_main_site()) {
    wp_die('Cette page est réservée au site parent.');
}
$analytics = new LMD_Activity_Analytics();
$service_start = $analytics->get_service_start_date();
if (isset($_POST['lmd_service_start']) && check_admin_referer('lmd_activity', 'lmd_activity_nonce')) {
    $analytics->set_service_start_date(sanitize_text_field(wp_unslash($_POST['lmd_service_start'])));
    $service_start = $analytics->get_service_start_date();
}
$month_sel = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_sel)) {
    $month_sel = date('Y-m');
}
$monthly = $analytics->get_monthly_stats(substr($service_start, 0, 4), date('Y'));
$per_est = $analytics->get_per_estimation_stats($month_sel);
$agg = $analytics->get_month_aggregates($month_sel);
$all_sites = is_multisite() && get_current_blog_id() === 1;
$feature_usage = $analytics->get_feature_usage($month_sel, $all_sites);
$signature_status = $analytics->get_signature_status($all_sites);
$lmd_activity_embed = !empty($lmd_activity_embed);
?>
<?php if (!$lmd_activity_embed) : ?>
<div class="wrap lmd-page">
    <h1><?php esc_html_e('Activité', 'lmd-apps-ia'); ?></h1>
    <p class="lmd-ui-prose"><?php esc_html_e('Analytics d’usage (direction) — même présentation que Consommation / Marge.', 'lmd-apps-ia'); ?></p>
<?php else : ?>
<div class="lmd-activity lmd-activity--embed lmd-page">
<?php endif; ?>

    <div class="lmd-ui-toolbar">
        <label>Mois <input type="month" id="lmd-activity-month" value="<?php echo esc_attr($month_sel); ?>" /></label>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lmd_export_activity&month=' . $month_sel), 'lmd_export_activity')); ?>" class="button button-primary">Exporter CSV</a>
    </div>

    <h2 class="lmd-ui-section-title">Périmètre du service</h2>
    <div class="lmd-ui-panel">
    <form method="post">
        <?php wp_nonce_field('lmd_activity', 'lmd_activity_nonce'); ?>
        <label>Date de début de service <input type="date" name="lmd_service_start" value="<?php echo esc_attr($service_start); ?>" /></label>
        <button type="submit" class="button">Enregistrer</button>
    </form>
    </div>

    <h2 class="lmd-ui-section-title">Résumé mensuel</h2>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Mois</th>
                <th>Estimations</th>
                <th>Détail (min)</th>
                <th>Grille (min)</th>
                <th>Temps total (min)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($monthly, true) as $m => $d) :
                $detail_min = isset($d['detail_seconds']) ? round($d['detail_seconds'] / 60, 1) : 0;
                $grid_min = isset($d['grid_seconds']) ? round($d['grid_seconds'] / 60, 1) : 0;
                $total_min = $detail_min + $grid_min;
            ?>
            <tr>
                <td><a href="<?php echo esc_url(function_exists('lmd_app_estimation_admin_url') ? add_query_arg(['month' => $m], lmd_app_estimation_admin_url('dashboard', ['dash_sub' => 'activity'])) : admin_url('admin.php?page=lmd-app-estimation&tab=dashboard&dash_sub=activity&month=' . rawurlencode($m))); ?>"><?php echo esc_html($m); ?></a></td>
                <td><?php echo (int) ($d['estimations'] ?? 0); ?></td>
                <td><?php echo $detail_min > 0 ? number_format($detail_min, 1) : '-'; ?></td>
                <td><?php echo $grid_min > 0 ? number_format($grid_min, 1) : '-'; ?></td>
                <td><strong><?php echo number_format($total_min, 1); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h2 class="lmd-ui-section-title">Mois <?php echo esc_html($month_sel); ?></h2>
    <?php
    $detail_min = (float) ($agg['detail_total_minutes'] ?? 0);
    $grid_min = (float) ($agg['grid_total_minutes'] ?? 0);
    $total_min = $detail_min + $grid_min;
    ?>
    <div class="lmd-ui-panel">
    <p style="margin-top:0;">
        <strong>Estimations :</strong> <?php echo (int) $agg['count']; ?> |
        <strong>Temps passé :</strong> <strong><?php echo number_format($total_min, 1); ?> min</strong> |
        <strong>Délai moyen avant traitement :</strong> <?php echo $agg['delay_avg_hours'] !== null ? round($agg['delay_avg_hours'], 1) . ' h' : '-'; ?> |
        <strong>Temps détail :</strong> <?php echo number_format($detail_min, 1); ?> min |
        <strong>Temps grille :</strong> <?php echo number_format($grid_min, 1); ?> min (plafonné 1 min/page) |
        <strong>Appels IA :</strong> <?php echo (int) $agg['ai_launch_total']; ?> |
        <strong>Suivent prix IA :</strong> <?php echo (int) $agg['follows_price_oui']; ?>/<?php echo (int) $agg['follows_price_total']; ?> |
        <strong>Suivent intérêt IA :</strong> <?php echo (int) $agg['follows_interest_oui']; ?>/<?php echo (int) $agg['follows_interest_total']; ?>
    </p>
    </div>

    <h3 class="lmd-ui-subsection">Par estimation</h3>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Créée</th>
                <th>Délai (h)</th>
                <th>Détail (min)</th>
                <th>IA</th>
                <th>Prix</th>
                <th>Intérêt</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($per_est as $row) : ?>
            <tr>
                <td><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-estimation-detail&id=' . $row['id'])); ?>">#<?php echo (int) $row['id']; ?></a></td>
                <td><?php echo esc_html(substr($row['created_at'], 0, 16)); ?></td>
                <td><?php echo $row['delay_hours'] !== null ? round($row['delay_hours'], 1) : '-'; ?></td>
                <td><?php echo round($row['detail_seconds'] / 60, 1); ?></td>
                <td><?php echo (int) $row['ai_launch_count']; ?></td>
                <td><?php echo esc_html($row['ai_follows_price'] ?? '-'); ?></td>
                <td><?php echo esc_html($row['ai_follows_interest'] ?? '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h2 class="lmd-ui-section-title">Fonctionnalités — <?php echo esc_html($month_sel); ?></h2>
    <div class="lmd-ui-panel">
    <p style="margin-top:0;"><strong>Les plus utilisées :</strong>
        <?php
        $labels = $feature_usage['labels'] ?? [];
        $most = array_map(function ($k) use ($labels, $feature_usage) {
            $n = $feature_usage['by_feature'][$k] ?? 0;
            return ($labels[$k] ?? $k) . ' (' . $n . ')';
        }, $feature_usage['most_used'] ?? []);
        echo esc_html(implode(' • ', $most ?: ['—']));
        ?>
    </p>
    <p><strong>Peu ou pas utilisées :</strong>
        <?php
        $least = array_map(function ($k) use ($labels, $feature_usage) {
            $n = $feature_usage['by_feature'][$k] ?? 0;
            return ($labels[$k] ?? $k) . ' (' . $n . ')';
        }, $feature_usage['least_used'] ?? []);
        echo esc_html(implode(' • ', $least ?: ['—']));
        ?>
    </p>
    </div>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;max-width:520px;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Fonctionnalité</th>
                <th>Utilisations</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($feature_usage['by_feature'] ?? []) as $k => $n) : ?>
            <tr>
                <td><?php echo esc_html($feature_usage['labels'][$k] ?? $k); ?></td>
                <td><?php echo (int) $n; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h2 class="lmd-ui-section-title">Signature CP (paramétrage)</h2>
    <p class="lmd-ui-prose" style="margin-bottom:12px;">Clients ayant configuré leur signature email pour les réponses.</p>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;max-width:480px;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Client</th>
                <th>Site</th>
                <th>Signature</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($signature_status as $s) : ?>
            <tr>
                <td><?php echo esc_html($s['site_name']); ?></td>
                <td><?php echo (int) $s['site_id']; ?></td>
                <td>
                    <?php if ($s['has_signature']) : ?>
                        <span class="lmd-badge lmd-badge--success">✓ Configurée</span> (<?php echo (int) $s['users_with_signature']; ?> utilisateur(s))
                    <?php else : ?>
                        <span class="lmd-badge lmd-badge--muted">Non configurée</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<script>
document.getElementById('lmd-activity-month').addEventListener('change', function() {
    var m = this.value;
    if (m) window.location.href = '<?php echo esc_js(function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url('dashboard', ['dash_sub' => 'activity']) : admin_url('admin.php?page=lmd-app-estimation&tab=dashboard&dash_sub=activity')); ?>' + '&month=' + encodeURIComponent(m);
});
</script>

