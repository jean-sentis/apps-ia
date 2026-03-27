<?php
/**
 * Liste des ventes (date_vente)
 */
if (!defined('ABSPATH')) {
    exit;
}
$db = new LMD_Database();
$ventes = $db->get_tag_options_for_type('date_vente');
global $wpdb;
$et = $wpdb->prefix . 'lmd_estimation_tags';
$t = $wpdb->prefix . 'lmd_tags';
$site_id = get_current_blog_id();
$ventes_with_count = [];
foreach ($ventes as $v) {
    $cnt = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $et et INNER JOIN $t t ON et.tag_id = t.id WHERE t.id = %d AND t.site_id = %d",
        $v->id, $site_id
    ));
    $date = preg_match('/^(\d{4}-\d{2}-\d{2})/', $v->slug, $m) ? $m[1] : $v->slug;
    $ventes_with_count[] = (object) ['id' => $v->id, 'name' => $v->name, 'slug' => $v->slug, 'date' => $date, 'count' => $cnt];
}
usort($ventes_with_count, function ($a, $b) {
    return strcmp($b->date, $a->date);
});
?>
<div class="wrap lmd-page">
    <h1>Planning ventes</h1>
    <p class="lmd-ui-prose">Dates de vente liées aux estimations — même ligne graphique que Mes estimations.</p>
    <div class="lmd-ui-toolbar">
        <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-estimations-list')); ?>" class="button">&larr; Retour aux estimations</a>
    </div>
    <?php if (empty($ventes_with_count)) : ?>
    <div class="lmd-ui-panel"><p style="margin:0;">Aucune vente enregistrée.</p></div>
    <?php else : ?>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Date</th>
                <th>Estimations liées</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ventes_with_count as $v) : ?>
            <tr>
                <td><?php echo esc_html($v->name); ?></td>
                <td><?php echo esc_html($v->date); ?></td>
                <td><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-estimations-list&filter_date_vente[]=' . urlencode($v->slug))); ?>"><?php echo (int) $v->count; ?></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
