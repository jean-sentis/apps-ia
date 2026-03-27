<?php
/**
 * Bac à sable — monosite : données de test pour conso / marge / CSV.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('LMD_Sandbox_Tools') || !LMD_Sandbox_Tools::is_allowed()) {
    wp_die('Réservé à un WordPress en site unique.');
}

$seed_url = admin_url('admin-post.php');
$count_presets = [1, 3, 5, 10];

$notice_seed = !empty($_GET['seeded']) ? (int) $_GET['seeded'] : 0;
$notice_clear = isset($_GET['cleared']) ? (int) $_GET['cleared'] : 0;
$notice_err = isset($_GET['err']) ? rawurldecode((string) $_GET['err']) : '';
?>
<div class="wrap lmd-page">
    <h1>Outils bac à sable</h1>
    <p class="lmd-ui-prose">
        Environnement <strong>monosite</strong> : demandes factices (<code>source = sandbox</code>) et conso API simulée,
        pour valider <strong>Tableau de bord</strong>, <strong>Consommation IA</strong>, <strong>Marge par produit</strong> et les exports CSV.
    </p>

    <?php if ($notice_seed) : ?>
    <div class="notice notice-success is-dismissible"><p><strong><?php echo (int) $notice_seed; ?></strong> analyse(s) simulée(s) — consultez <strong>Marge par produit</strong> et <strong>Consommation IA</strong> pour le mois en cours.</p></div>
    <?php endif; ?>
    <?php if ($notice_clear) : ?>
    <div class="notice notice-info is-dismissible"><p>Suppression : <strong><?php echo (int) $notice_clear; ?></strong> estimation(s) bac à sable.</p></div>
    <?php endif; ?>
    <?php if ($notice_err !== '') : ?>
    <div class="notice notice-error"><p><?php echo esc_html($notice_err); ?></p></div>
    <?php endif; ?>

    <h2 class="lmd-ui-section-title">Simuler des analyses</h2>
    <div class="lmd-ui-panel" style="max-width:480px;">
    <p style="margin-top:0;">Crée des lignes dans <code>lmd_estimations</code> + <code>lmd_api_usage</code> pour le site actuel.</p>
    <form method="post" action="<?php echo esc_url($seed_url); ?>">
        <input type="hidden" name="action" value="lmd_sandbox_seed" />
        <?php wp_nonce_field('lmd_sandbox_seed'); ?>
        <p>
            <label>Nombre d’analyses à simuler
            <select name="lmd_sandbox_count">
                <?php foreach ($count_presets as $c) : ?>
                <option value="<?php echo (int) $c; ?>" <?php selected($c, 3); ?>><?php echo (int) $c; ?></option>
                <?php endforeach; ?>
            </select>
            </label>
        </p>
        <p>
            <button type="submit" class="button button-primary">Générer</button>
        </p>
    </form>
    </div>

    <h2 class="lmd-ui-section-title">Nettoyer les données bac à sable</h2>
    <div class="lmd-ui-panel" style="max-width:480px;">
    <p style="margin-top:0;">Supprime uniquement les estimations créées avec la source <code>sandbox</code> (tags + usages API inclus).</p>
    <form method="post" action="<?php echo esc_url($seed_url); ?>" onsubmit="return confirm('Supprimer toutes les estimations bac à sable ?');">
        <input type="hidden" name="action" value="lmd_sandbox_clear" />
        <?php wp_nonce_field('lmd_sandbox_clear'); ?>
        <p><button type="submit" class="button">Tout supprimer (sandbox)</button></p>
    </form>
    </div>

    <h2 class="lmd-ui-section-title">Raccourcis</h2>
    <div class="lmd-ui-toolbar">
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-product-margin')); ?>">Marge par produit</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-consumption')); ?>">Consommation IA</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-apps-ia')); ?>">Vue d’ensemble</a>
    </div>
</div>
