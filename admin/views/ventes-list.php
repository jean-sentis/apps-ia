<?php
/**
 * Planning ventes : dates liées aux estimations
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
        $v->id,
        $site_id
    ));
    $date = preg_match('/^(\d{4}-\d{2}-\d{2})/', $v->slug, $m) ? $m[1] : $v->slug;
    $ventes_with_count[] = (object) ['id' => $v->id, 'name' => $v->name, 'slug' => $v->slug, 'date' => $date, 'count' => $cnt];
}
usort($ventes_with_count, function ($a, $b) {
    return strcmp($b->date, $a->date);
});

$app_base = admin_url('admin.php?page=lmd-app-estimation');
$list_url = $app_base . '&tab=list';
$nonce = wp_create_nonce('lmd_admin');
?>
<div class="lmd-page lmd-ventes-planning-wrap">
    <h1><?php esc_html_e('Planning ventes', 'lmd-apps-ia'); ?></h1>

    <div class="lmd-ui-panel" style="margin-bottom:1.25rem;">
        <h2 style="margin-top:0;"><?php esc_html_e('Ajouter une vente (date de vente pour les estimations)', 'lmd-apps-ia'); ?></h2>
        <p class="description" style="margin-top:0;"><?php esc_html_e('Crée une date de vente utilisable sur les fiches estimation (uniquement côté aide à l’estimation).', 'lmd-apps-ia'); ?></p>
        <p class="lmd-vente-add-row">
            <label><?php esc_html_e('Intitulé', 'lmd-apps-ia'); ?><br>
                <input type="text" id="lmd-vente-new-name" class="regular-text" placeholder="<?php esc_attr_e('Ex. Vente du 12 juin', 'lmd-apps-ia'); ?>" />
            </label>
            <label><?php esc_html_e('Date', 'lmd-apps-ia'); ?><br>
                <input type="date" id="lmd-vente-new-date" class="lmd-vente-add-date" />
            </label>
            <button type="button" class="button button-primary lmd-vente-add-btn" id="lmd-vente-create-btn"><?php esc_html_e('Ajouter la vente', 'lmd-apps-ia'); ?></button>
        </p>
        <p id="lmd-vente-create-msg" class="description" style="min-height:1.25em;"></p>
    </div>

    <h2><?php esc_html_e('Ventes liées aux estimations', 'lmd-apps-ia'); ?></h2>
    <?php if (empty($ventes_with_count)) : ?>
    <div class="lmd-ui-panel"><p style="margin:0;"><?php esc_html_e('Aucune vente enregistrée.', 'lmd-apps-ia'); ?></p></div>
    <?php else : ?>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Nom', 'lmd-apps-ia'); ?></th>
                <th><?php esc_html_e('Date', 'lmd-apps-ia'); ?></th>
                <th><?php esc_html_e('Estimations liées', 'lmd-apps-ia'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ventes_with_count as $v) : ?>
            <tr>
                <td><?php echo esc_html($v->name); ?></td>
                <td><?php echo esc_html($v->date); ?></td>
                <td><a href="<?php echo esc_url($list_url . '&filter_date_vente[]=' . rawurlencode($v->slug)); ?>"><?php echo (int) $v->count; ?></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<style>
.lmd-ventes-planning-wrap .lmd-vente-add-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: flex-end;
}
.lmd-ventes-planning-wrap .lmd-vente-add-row .lmd-vente-add-date {
    height: 30px;
    max-height: 30px;
    box-sizing: border-box;
    line-height: 1.2;
}
.lmd-ventes-planning-wrap .lmd-vente-add-row .lmd-vente-add-btn.button {
    font-size: 13px;
    line-height: 1.2;
    padding: 0 12px;
    min-height: 0;
    height: 30px;
    max-height: 30px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>
<script>
(function($){
    var nonce = <?php echo wp_json_encode($nonce); ?>;
    $('#lmd-vente-create-btn').on('click', function(){
        var name = ($('#lmd-vente-new-name').val() || '').trim();
        var date = $('#lmd-vente-new-date').val();
        var $msg = $('#lmd-vente-create-msg');
        $msg.text('');
        if (!name || !date) { $msg.text(<?php echo wp_json_encode(__('Nom et date requis.', 'lmd-apps-ia')); ?>); return; }
        $.post(ajaxurl, { action: 'lmd_create_vente', nonce: nonce, name: name, date: date }).done(function(r){
            if (r.success) { location.reload(); }
            else { $msg.text((r.data && r.data.message) ? r.data.message : 'Erreur'); }
        });
    });
})(jQuery);
</script>
