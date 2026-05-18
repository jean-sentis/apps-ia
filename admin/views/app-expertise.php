<?php
/**
 * App admin - Expertise IA.
 *
 * @var array $lmd_expertise_settings
 * @var array $lmd_expertise_stats
 */
if (!defined("ABSPATH")) {
    exit();
}

$lmd_expertise_settings = isset($lmd_expertise_settings) && is_array($lmd_expertise_settings)
    ? $lmd_expertise_settings
    : (function_exists("lmd_get_expertise_settings") ? lmd_get_expertise_settings() : ["enabled" => false]);
$lmd_expertise_stats = isset($lmd_expertise_stats) && is_array($lmd_expertise_stats)
    ? $lmd_expertise_stats
    : [];
$lmd_expertise_enabled = !empty($lmd_expertise_settings["enabled"]);
$recent_lots = is_array($lmd_expertise_stats["recent"] ?? null) ? $lmd_expertise_stats["recent"] : [];
$meta_keys = is_array($lmd_expertise_stats["meta_keys"] ?? null) ? $lmd_expertise_stats["meta_keys"] : [
    "generated_at" => "_lmd_expertise_generated_at",
    "model" => "_lmd_expertise_model",
];
?>
<div class="wrap lmd-page lmd-app-shell--seo lmd-expertise-app">
    <div class="lmd-suite-app-banner" role="banner">
        <div class="lmd-suite-app-banner__inner">
            <img src="<?php echo esc_url(LMD_PLUGIN_URL . "assets/lmd-logo-menu.png"); ?>" alt="" class="lmd-suite-app-banner__logo" width="52" height="52" decoding="async" />
            <div class="lmd-suite-app-banner__titles">
                <h1 class="lmd-suite-app-banner__title"><?php esc_html_e("Expertise IA", "lmd-apps-ia"); ?></h1>
                <p class="lmd-suite-app-banner__subtitle"><?php esc_html_e("Avis IA public généré à la demande pour les pages lot.", "lmd-apps-ia"); ?></p>
            </div>
            <span class="lmd-suite-app-banner__spacer" aria-hidden="true"></span>
        </div>
    </div>

    <?php if (isset($_GET["expertise_saved"])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e("Réglage Expertise IA enregistré.", "lmd-apps-ia"); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET["expertise_purged"])) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e("Analyse Expertise IA purgée pour ce lot.", "lmd-apps-ia"); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET["expertise_purge_error"])) : ?>
    <div class="notice notice-error is-dismissible"><p><?php esc_html_e("Impossible de purger l’analyse Expertise IA pour ce lot.", "lmd-apps-ia"); ?></p></div>
    <?php endif; ?>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e("Activation du service", "lmd-apps-ia"); ?></h2>
        <p class="lmd-ui-prose"><?php esc_html_e("Expertise IA affiche sur les pages lot un avis généré à la demande avec l'IA. La première demande analyse les photos et les données du lot, puis le résultat est stocké dans les metas du lot pour être resservi sans nouvelle consommation IA.", "lmd-apps-ia"); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
            <input type="hidden" name="action" value="lmd_save_expertise_settings" />
            <input type="hidden" name="enabled" value="0" />
            <?php wp_nonce_field("lmd_save_expertise_settings"); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e("Statut du service", "lmd-apps-ia"); ?></th>
                        <td>
                            <div class="form-check form-switch lmd-seo-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="lmd-expertise-enabled" name="enabled" value="1" <?php checked($lmd_expertise_enabled); ?> />
                                <label class="form-check-label lmd-seo-switch-copy" for="lmd-expertise-enabled">
                                    <span class="lmd-seo-switch-title"><?php esc_html_e("Activer le service d’analyse IA sur les lots de ce site", "lmd-apps-ia"); ?></span>
                                    
                                </label>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button class="button button-primary" type="submit"><?php esc_html_e("Enregistrer les réglages", "lmd-apps-ia"); ?></button>
            </p>
        </form>
    </div>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e("Statistiques d’utilisation", "lmd-apps-ia"); ?></h2>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;max-width:900px;">
            <div style="padding:16px;border:1px solid #e5e7eb;background:#fff;">
                <span class="description"><?php esc_html_e("Service", "lmd-apps-ia"); ?></span>
                <p style="margin:6px 0 0;font-size:22px;font-weight:700;"><?php echo esc_html($lmd_expertise_enabled ? __("Actif", "lmd-apps-ia") : __("Inactif", "lmd-apps-ia")); ?></p>
            </div>
            <div style="padding:16px;border:1px solid #e5e7eb;background:#fff;">
                <span class="description"><?php esc_html_e("Analyses ce mois", "lmd-apps-ia"); ?></span>
                <p style="margin:6px 0 0;font-size:22px;font-weight:700;"><?php echo (int) ($lmd_expertise_stats["month_done"] ?? 0); ?></p>
            </div>
            <div style="padding:16px;border:1px solid #e5e7eb;background:#fff;">
                <span class="description"><?php esc_html_e("Analyses stockées", "lmd-apps-ia"); ?></span>
                <p style="margin:6px 0 0;font-size:22px;font-weight:700;"><?php echo (int) ($lmd_expertise_stats["total_done"] ?? 0); ?></p>
            </div>
        </div>
    </div>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e("Derniers lots expertisés", "lmd-apps-ia"); ?></h2>
        <?php if (!empty($recent_lots)) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e("Lot", "lmd-apps-ia"); ?></th>
                    <th><?php esc_html_e("Date d’analyse", "lmd-apps-ia"); ?></th>
                    <th><?php esc_html_e("Modèle", "lmd-apps-ia"); ?></th>
                    <th><?php esc_html_e("Actions", "lmd-apps-ia"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_lots as $lot) : ?>
                <?php
                $lot_id = (int) $lot->ID;
                $generated_at = (string) get_post_meta($lot_id, $meta_keys["generated_at"] ?? "_lmd_expertise_generated_at", true);
                $model = (string) get_post_meta($lot_id, $meta_keys["model"] ?? "_lmd_expertise_model", true);
                ?>
                <tr>
                    <td><strong><?php echo esc_html(get_the_title($lot_id)); ?></strong><br /><span class="description">#<?php echo (int) $lot_id; ?></span></td>
                    <td><?php echo esc_html($generated_at ?: "—"); ?></td>
                    <td><?php echo esc_html($model ?: "—"); ?></td>
                    <td>
                        <a href="<?php echo esc_url(get_edit_post_link($lot_id)); ?>"><?php esc_html_e("Éditer", "lmd-apps-ia"); ?></a>
                        <?php if (get_permalink($lot_id)) : ?>
                            · <a href="<?php echo esc_url(get_permalink($lot_id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e("Voir", "lmd-apps-ia"); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p class="description"><?php esc_html_e("Aucun lot expertisé pour le moment.", "lmd-apps-ia"); ?></p>
        <?php endif; ?>
    </div>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e("Outils de test", "lmd-apps-ia"); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
            <input type="hidden" name="action" value="lmd_purge_expertise_lot" />
            <?php wp_nonce_field("lmd_purge_expertise_lot"); ?>
            <label>
                <?php esc_html_e("ID du lot à purger", "lmd-apps-ia"); ?><br />
                <input type="number" min="1" step="1" name="lot_id" class="regular-text" />
            </label>
            <button class="button" type="submit"><?php esc_html_e("Purger l’analyse du lot", "lmd-apps-ia"); ?></button>
        </form>
    </div>
</div>
