<?php
/**
 * Vue Remontée statistique — Site parent uniquement
 * Données des clients remontées : consommation, activité, fonctionnalités, signature, erreurs IA
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!is_multisite()) {
    echo '<div class="wrap lmd-page"><p>Cette page est réservée au multisite (site parent).</p></div>';
    return;
}

$month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$usage = class_exists('LMD_Api_Usage') ? new LMD_Api_Usage() : null;
$analytics = class_exists('LMD_Activity_Analytics') ? new LMD_Activity_Analytics() : null;

$clients_consumption = $usage ? $usage->get_all_clients_consumption($month, true) : [];
$agg_consumption = $usage ? $usage->get_aggregate_consumption($month, true) : ['by_api' => [], 'total_usd' => 0, 'analyses_count' => 0, 'clients_count' => 0];
$labels_api = $usage ? LMD_Api_Usage::get_api_labels() : [];

$feature_usage = $analytics ? $analytics->get_feature_usage($month, true) : [];
$signature_status = $analytics ? $analytics->get_signature_status(true) : [];

global $wpdb;
$remontee_table = $wpdb->prefix . 'lmd_remontee_ai_errors';
$ai_errors = [];
if ($wpdb->get_var("SHOW TABLES LIKE '$remontee_table'") === $remontee_table) {
    $ai_errors = $wpdb->get_results("SELECT * FROM $remontee_table ORDER BY created_at DESC LIMIT 200", ARRAY_A);
}
?>
<div class="wrap lmd-page">
    <h1>Remontée statistique</h1>
    <p class="lmd-ui-prose">Données remontées des sites clients vers le parent — exploitation par client et agrégat global.</p>
    <p class="description">Le site parent (site 1) dispose aussi de LMD Apps IA comme un client : utilisez-le pour tester les fonctionnalités, vérifier les instruments et essayer de nouvelles fonctionnalités hors bac à sable.</p>

    <div class="lmd-ui-toolbar">
        <label>Mois <input type="month" id="lmd-remontee-month" value="<?php echo esc_attr($month); ?>" /></label>
    </div>

    <h2 class="lmd-ui-section-title">Consommation IA — <?php echo esc_html($month); ?></h2>
    <?php if ($usage && !empty($clients_consumption)) : ?>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Client</th>
                <th>Site</th>
                <th>Analyses</th>
                <?php foreach ($labels_api as $api => $label) : ?>
                <th><?php echo esc_html($label); ?> (unités)</th>
                <th><?php echo esc_html($label); ?> ($)</th>
                <?php endforeach; ?>
                <th>Total ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients_consumption as $c) : ?>
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
                <td><?php echo (int) $agg_consumption['analyses_count']; ?></td>
                <?php foreach (['serpapi', 'firecrawl', 'imgbb', 'gemini'] as $api) : ?>
                <td><?php echo (int) ($agg_consumption['by_api'][$api]['units'] ?? 0); ?></td>
                <td><?php echo number_format($agg_consumption['by_api'][$api]['cost_usd'] ?? 0, 4); ?></td>
                <?php endforeach; ?>
                <td><?php echo number_format($agg_consumption['total_usd'], 4); ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
    <p><strong><?php echo (int) $agg_consumption['clients_count']; ?></strong> client(s) | <strong><?php echo number_format($agg_consumption['total_usd'], 4); ?> $</strong> dépenses totales</p>
    <?php else : ?>
    <div class="lmd-ui-panel"><p style="margin:0;">Aucune donnée.</p></div>
    <?php endif; ?>

    <h2 class="lmd-ui-section-title">Fonctionnalités — <?php echo esc_html($month); ?></h2>
    <?php if (!empty($feature_usage['by_feature'])) : ?>
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
                <th>Utilisations (tous clients)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($feature_usage['by_feature'] ?? [] as $k => $n) : ?>
            <tr>
                <td><?php echo esc_html($feature_usage['labels'][$k] ?? $k); ?></td>
                <td><?php echo (int) $n; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else : ?>
    <div class="lmd-ui-panel"><p style="margin:0;">Aucune donnée.</p></div>
    <?php endif; ?>

    <h2 class="lmd-ui-section-title">Signature CP (paramétrage)</h2>
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
                        <span class="lmd-badge lmd-badge--success">✓ Configurée</span>
                    <?php else : ?>
                        <span class="lmd-badge lmd-badge--muted">Non configurée</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <h2 class="lmd-ui-section-title">Erreurs IA signalées par les clients</h2>
    <p class="lmd-ui-prose" style="margin-bottom:12px;">Détail de ce que chaque client considère comme une erreur de l'IA.</p>
    <?php if (!empty($ai_errors)) : ?>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Client (site)</th>
                <th>Estimation</th>
                <th>Vendeur</th>
                <th>Résumé IA</th>
                <th>Estimation IA</th>
                <th>Explication utilisateur</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ai_errors as $err) : ?>
            <tr>
                <td><?php echo esc_html(substr($err['created_at'] ?? '', 0, 16)); ?></td>
                <td><?php echo esc_html($err['site_name'] ?? 'Site ' . $err['site_id_origin']); ?> (<?php echo (int) $err['site_id_origin']; ?>)</td>
                <td>
                    <?php
                    $admin_url = get_admin_url($err['site_id_origin'], 'admin.php?page=lmd-estimation-detail&id=' . $err['estimation_id']);
                    echo '<a href="' . esc_url($admin_url) . '" target="_blank">#' . (int) $err['estimation_id'] . '</a>';
                    ?>
                </td>
                <td><?php echo esc_html($err['client_name'] ?? ''); ?></td>
                <td style="max-width:200px;"><?php echo esc_html(wp_trim_words($err['ai_summary'] ?? '', 15)); ?></td>
                <td>
                    <?php
                    $lo = $err['ai_estimate_low'] ?? null;
                    $hi = $err['ai_estimate_high'] ?? null;
                    echo $lo !== null ? number_format($lo, 0, ',', ' ') . ' – ' . number_format($hi ?? $lo, 0, ',', ' ') . ' €' : '—';
                    ?>
                </td>
                <td style="max-width:200px;"><?php echo esc_html(wp_trim_words($err['user_explanation'] ?? '', 10)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else : ?>
    <div class="lmd-ui-panel"><p style="margin:0;">Aucune erreur signalée.</p></div>
    <?php endif; ?>
</div>
<script>
document.getElementById('lmd-remontee-month').addEventListener('change', function() {
    var m = this.value;
    if (m) window.location.href = '?page=lmd-remontee&month=' + m;
});
</script>
