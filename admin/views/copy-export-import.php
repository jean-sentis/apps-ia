<?php
/**
 * Vue Copie export/import — Copie exacte pour simulation et étude (site parent uniquement)
 */
if (!defined('ABSPATH')) {
    exit;
}
if (is_multisite() && !is_main_site()) {
    wp_die('Cette page est réservée au site parent.');
}
$exporter = class_exists('LMD_Full_Export_Import') ? new LMD_Full_Export_Import() : null;
$sites = $exporter ? $exporter->get_exportable_sites() : [];
$import_ok = isset($_GET['lmd_import_ok']);
$import_error = isset($_GET['lmd_import_error']) ? sanitize_text_field(wp_unslash($_GET['lmd_import_error'])) : '';
?>
<div class="wrap lmd-page">
    <h1>Copie client — Export / Import</h1>
    <p class="lmd-ui-prose">Copie exacte des données LMD Apps IA pour simulation, étude et enrichissement des fonctionnalités.</p>

    <?php if ($import_ok) : ?>
    <div class="notice notice-success"><p>Import réussi.</p></div>
    <?php endif; ?>
    <?php if ($import_error) : ?>
    <div class="notice notice-error"><p>Erreur import : <?php echo esc_html($import_error); ?></p></div>
    <?php endif; ?>

    <h2 class="lmd-ui-section-title">Exporter (copie du client)</h2>
    <div class="lmd-ui-panel" style="max-width:520px;">
    <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="lmd_export_full_copy" />
        <?php wp_nonce_field('lmd_export_full_copy', '_wpnonce', false); ?>
        <?php if (count($sites) > 1) : ?>
        <p>
            <label>Client (site) à exporter<br />
            <select name="site_id">
                <?php foreach ($sites as $s) : ?>
                <option value="<?php echo (int) $s['id']; ?>"><?php echo esc_html($s['name']); ?> (ID <?php echo (int) $s['id']; ?>)</option>
                <?php endforeach; ?>
            </select>
            </label>
        </p>
        <?php endif; ?>
        <p>
            <label><input type="checkbox" name="no_photos" value="1" /> Exclure les photos (export plus léger)</label>
        </p>
        <p>
            <label><input type="checkbox" name="api_keys" value="1" /> Inclure les clés API (déconseillé pour partage)</label>
        </p>
        <p><button type="submit" class="button button-primary">Télécharger la copie ZIP</button></p>
    </form>
    <p class="description" style="margin-bottom:0;">Le fichier ZIP contient : estimations, tags, paramètres CP, formules, options, et les photos (sauf si exclues).</p>
    </div>

    <h2 class="lmd-ui-section-title">Importer (restaurer une copie)</h2>
    <div class="lmd-ui-panel" style="max-width:520px;">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="lmd_import_full_copy" />
        <?php wp_nonce_field('lmd_import_full_copy', '_wpnonce', false); ?>
        <p>
            <label>Fichier ZIP (export LMD Apps IA)<br />
            <input type="file" name="lmd_import_file" accept=".zip" required />
            </label>
        </p>
        <p>
            <label><input type="checkbox" name="lmd_import_replace" value="1" /> Remplacer les estimations et tags existants (sinon fusionner)</label>
        </p>
        <p><button type="submit" class="button button-primary">Importer</button></p>
    </form>
    <p class="description" style="margin-bottom:0;">Les données seront importées dans le site actuel. Les photos seront copiées dans la médiathèque.</p>
    </div>
</div>
