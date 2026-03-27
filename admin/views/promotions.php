<?php
/**
 * Promotions clients — Ristourne € ou X estimations gratuites (site parent uniquement)
 */
if (!defined('ABSPATH')) {
    exit;
}
if (is_multisite() && !is_main_site()) {
    wp_die('Cette page est réservée au site parent.');
}
$saved = false;
if (isset($_POST['lmd_save_promotions']) && check_admin_referer('lmd_promotions', 'lmd_promotions_nonce')) {
    $promos = [];
    $logos = get_option('lmd_client_logos', []) ?: [];
    $raw = isset($_POST['lmd_promotions']) && is_array($_POST['lmd_promotions']) ? $_POST['lmd_promotions'] : [];
    foreach ($raw as $site_id => $p) {
        $site_id = absint($site_id);
        if (!$site_id) continue;
        $type = isset($p['type']) && in_array($p['type'], ['ristourne', 'gratuites'], true) ? $p['type'] : '';
        $amount = isset($p['amount']) ? absint($p['amount']) : 0;
        $message = isset($p['message']) ? sanitize_textarea_field(wp_unslash($p['message'])) : '';
        if ($type && $amount > 0) {
            $promos[$site_id] = ['type' => $type, 'amount' => $amount, 'message' => $message];
        }
        $logo_url = isset($p['logo']) ? esc_url_raw(trim(wp_unslash($p['logo']))) : '';
        if ($logo_url) {
            $logos[$site_id] = $logo_url;
        } elseif (isset($logos[$site_id])) {
            unset($logos[$site_id]);
        }
    }
    update_option('lmd_client_promotions', $promos);
    update_option('lmd_client_logos', $logos);
    $saved = true;
}

$promos = get_option('lmd_client_promotions', []);
$logos = get_option('lmd_client_logos', []);
$sites = [];
if (is_multisite()) {
    $sites = get_sites(['number' => 500, 'orderby' => 'blog_id', 'order' => 'ASC']);
} else {
    $sites = [(object) ['blog_id' => 1, 'blogname' => get_bloginfo('name')]];
}
?>
<div class="wrap lmd-page">
    <h1>Promotions clients</h1>
    <p class="lmd-ui-prose">Ristourne ou estimations gratuites par site — affichage sur le tableau de bord client.</p>
    <?php if ($saved) : ?>
    <div class="notice notice-success is-dismissible"><p>Promotions enregistrées.</p></div>
    <?php endif; ?>

    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <form method="post">
        <?php wp_nonce_field('lmd_promotions', 'lmd_promotions_nonce'); ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Site</th>
                    <th>Logo (URL)</th>
                    <th>Type</th>
                    <th>Montant / Nombre</th>
                    <th>Message (optionnel)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sites as $site) :
                    $sid = (int) $site->blog_id;
                    $name = is_multisite() ? $site->blogname : $site->blogname;
                    if (is_multisite() && $sid > 1) {
                        switch_to_blog($sid);
                        $name = get_bloginfo('name') ?: 'Site ' . $sid;
                        restore_current_blog();
                    }
                    $p = $promos[$sid] ?? ['type' => '', 'amount' => 0, 'message' => ''];
                    $logo = $logos[$sid] ?? '';
                ?>
                <tr>
                    <td><strong><?php echo esc_html($name); ?></strong> <small>(ID <?php echo $sid; ?>)</small></td>
                    <td><input type="url" name="lmd_promotions[<?php echo $sid; ?>][logo]" value="<?php echo esc_attr($logo); ?>" class="regular-text" placeholder="https://..." /></td>
                    <td>
                        <select name="lmd_promotions[<?php echo $sid; ?>][type]">
                            <option value="">—</option>
                            <option value="ristourne" <?php selected($p['type'] ?? '', 'ristourne'); ?>>Ristourne €</option>
                            <option value="gratuites" <?php selected($p['type'] ?? '', 'gratuites'); ?>>Estimations gratuites</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="lmd_promotions[<?php echo $sid; ?>][amount]" value="<?php echo esc_attr($p['amount'] ?? 0); ?>" min="0" step="1" style="width:80px;" />
                        <span class="description"><?php echo ($p['type'] ?? '') === 'ristourne' ? '€' : 'gratuites'; ?></span>
                    </td>
                    <td>
                        <input type="text" name="lmd_promotions[<?php echo $sid; ?>][message]" value="<?php echo esc_attr($p['message'] ?? ''); ?>" class="large-text" placeholder="Ex: Merci pour votre fidélité" />
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="submit" style="padding:16px 20px;margin:0;border-top:1px solid #e5e7eb;"><button type="submit" name="lmd_save_promotions" class="button button-primary">Enregistrer</button></p>
    </form>
    </div>
</div>
