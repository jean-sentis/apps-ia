<?php
/**
 * Vue Configuration APIs
 */
if (!defined('ABSPATH')) {
    exit;
}
$lmd_suite_embed = !empty($lmd_suite_embed);
$lmd_api_saved = false;
if (isset($_POST['lmd_save_api']) && check_admin_referer('lmd_api_config')) {
    update_option('lmd_gemini_key', sanitize_text_field($_POST['lmd_gemini_key'] ?? ''));
    update_option('lmd_gemini_model', sanitize_text_field($_POST['lmd_gemini_model'] ?? 'gemini-2.5-pro'));
    update_option('lmd_gemini_image_model', sanitize_text_field($_POST['lmd_gemini_image_model'] ?? ''));
    update_option('lmd_serpapi_key', sanitize_text_field($_POST['lmd_serpapi_key'] ?? ''));
    update_option('lmd_firecrawl_key', sanitize_text_field($_POST['lmd_firecrawl_key'] ?? ''));
    update_option('lmd_imgbb_key', sanitize_text_field($_POST['lmd_imgbb_key'] ?? ''));
    update_option('lmd_imgbb_enabled', !empty($_POST['lmd_imgbb_enabled']));
    $lmd_api_saved = true;
}
$gemini = get_option('lmd_gemini_key', '');
$gemini_model = get_option('lmd_gemini_model', 'gemini-2.5-pro');
$gemini_image_model = get_option('lmd_gemini_image_model', '');
$serp = get_option('lmd_serpapi_key', '');
$firecrawl = get_option('lmd_firecrawl_key', '');
$imgbb = get_option('lmd_imgbb_key', '');
$imgbb_enabled = (bool) get_option('lmd_imgbb_enabled', false);

$env_type = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

$form_action = $lmd_suite_embed ? admin_url('admin.php?page=lmd-apps-ia&hub_tab=apis') : '';
?>
<?php if (!$lmd_suite_embed) : ?>
<div class="wrap lmd-page">
<?php endif; ?>
    <?php if ($lmd_api_saved) : ?>
    <div class="notice notice-success is-dismissible"><p>Configuration enregistrée.</p></div>
    <?php endif; ?>
    <?php if (!$lmd_suite_embed) : ?>
    <h1>Configuration APIs</h1>
    <?php else : ?>
    <h2 class="lmd-ui-section-title">Configuration des APIs</h2>
    <?php endif; ?>
    <p class="lmd-ui-prose">Clés et modèles utilisés par les applications LMD Apps IA (aide à l’estimation, montages Splitscreen, etc.).</p>
    <div class="lmd-ui-panel lmd-api-config-panel">
    <form method="post" action="<?php echo $lmd_suite_embed ? esc_url($form_action) : ''; ?>" class="lmd-api-config-form">
        <?php wp_nonce_field('lmd_api_config'); ?>

        <div class="lmd-api-config-field">
            <span class="lmd-api-config-field-label"><?php esc_html_e('Clé Gemini (Google AI)', 'lmd-apps-ia'); ?></span>
            <div class="lmd-api-config-field-row">
                <input type="password" name="lmd_gemini_key" id="lmd_gemini_key" value="<?php echo esc_attr($gemini); ?>" class="regular-text lmd-api-config-input" autocomplete="off" />
                <span class="lmd-api-config-field-links">
                    <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener"><?php esc_html_e('Obtenir une clé API Gemini', 'lmd-apps-ia'); ?></a>
                </span>
            </div>
        </div>

        <div class="lmd-api-config-field">
            <span class="lmd-api-config-field-label" id="lmd-label-gemini-model"><?php esc_html_e('Modèle Gemini', 'lmd-apps-ia'); ?></span>
            <div class="lmd-api-config-field-row">
                <input type="text" name="lmd_gemini_model" id="lmd_gemini_model" value="<?php echo esc_attr($gemini_model); ?>" class="regular-text lmd-api-config-input" placeholder="gemini-2.5-pro" aria-labelledby="lmd-label-gemini-model" />
                <span class="lmd-api-config-field-links">
                    <?php esc_html_e('Ex. gemini-2.5-pro, gemini-3.1-pro-preview — analyse / texte', 'lmd-apps-ia'); ?> —
                    <a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener"><?php esc_html_e('Liste des modèles', 'lmd-apps-ia'); ?></a>
                </span>
            </div>
        </div>

        <div class="lmd-api-config-field">
            <span class="lmd-api-config-field-label" id="lmd-label-gemini-image-model"><?php esc_html_e('Modèle Gemini (images / Splitscreen)', 'lmd-apps-ia'); ?></span>
            <div class="lmd-api-config-field-row">
                <input type="text" name="lmd_gemini_image_model" id="lmd_gemini_image_model" value="<?php echo esc_attr($gemini_image_model); ?>" class="regular-text lmd-api-config-input" placeholder="gemini-2.5-flash-image" aria-labelledby="lmd-label-gemini-image-model" />
                <span class="lmd-api-config-field-links">
                    <?php esc_html_e('Génération de montages Splitscreen uniquement. Laisser vide = défaut interne (gemini-2.5-flash-image). Ne pas recopier ici le « Modèle Gemini » texte (gemini-2.5-pro, etc.). Ex. preview : gemini-3.1-flash-image-preview.', 'lmd-apps-ia'); ?> —
                    <a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener"><?php esc_html_e('Liste des modèles', 'lmd-apps-ia'); ?></a>
                </span>
            </div>
        </div>

        <div class="lmd-api-config-field">
            <span class="lmd-api-config-field-label"><?php esc_html_e('Clé SerpAPI (Google Lens)', 'lmd-apps-ia'); ?></span>
            <div class="lmd-api-config-field-row">
                <input type="password" name="lmd_serpapi_key" id="lmd_serpapi_key" value="<?php echo esc_attr($serp); ?>" class="regular-text lmd-api-config-input" autocomplete="off" />
                <span class="lmd-api-config-field-links">
                    <?php esc_html_e('Recherche par image type Google Lens', 'lmd-apps-ia'); ?> —
                    <a href="https://serpapi.com/manage-api-key" target="_blank" rel="noopener"><?php esc_html_e('Obtenir une clé SerpAPI', 'lmd-apps-ia'); ?></a>
                </span>
            </div>
        </div>

        <div class="lmd-api-config-field">
            <span class="lmd-api-config-field-label"><?php esc_html_e('Clé Firecrawl', 'lmd-apps-ia'); ?></span>
            <div class="lmd-api-config-field-row">
                <input type="password" name="lmd_firecrawl_key" id="lmd_firecrawl_key" value="<?php echo esc_attr($firecrawl); ?>" class="regular-text lmd-api-config-input" autocomplete="off" />
                <span class="lmd-api-config-field-links">
                    <?php esc_html_e('Scraping web pour enrichir l’analyse', 'lmd-apps-ia'); ?> —
                    <a href="https://www.firecrawl.dev/app/api-keys" target="_blank" rel="noopener"><?php esc_html_e('Obtenir une clé Firecrawl', 'lmd-apps-ia'); ?></a>
                </span>
            </div>
        </div>

        <div class="lmd-api-config-field lmd-api-config-field--imgbb">
            <span class="lmd-api-config-field-label"><?php esc_html_e('ImgBB (images locales → URL publique)', 'lmd-apps-ia'); ?></span>
            <p class="lmd-api-config-field-hint">
                <?php
                printf(
                    /* translators: %s: WP environment type (local, production, etc.) */
                    esc_html__('Utile quand SerpAPI ne peut pas lire un fichier local. Environnement WordPress actuel : %s. En production, laissez désactivé sauf besoin ponctuel.', 'lmd-apps-ia'),
                    '<strong>' . esc_html($env_type) . '</strong>'
                );
                ?>
            </p>
            <p class="lmd-api-config-toggle">
                <label>
                    <input type="checkbox" name="lmd_imgbb_enabled" value="1" <?php checked($imgbb_enabled); ?> />
                    <?php esc_html_e('Activer l’upload ImgBB (recommandé surtout en local / préproduction)', 'lmd-apps-ia'); ?>
                </label>
            </p>
            <div class="lmd-api-config-field-row">
                <input type="password" name="lmd_imgbb_key" id="lmd_imgbb_key" value="<?php echo esc_attr($imgbb); ?>" class="regular-text lmd-api-config-input" autocomplete="off" />
                <span class="lmd-api-config-field-links">
                    <a href="https://api.imgbb.com/" target="_blank" rel="noopener"><?php esc_html_e('Obtenir une clé ImgBB', 'lmd-apps-ia'); ?></a>
                </span>
            </div>
        </div>

        <p class="submit"><input type="submit" name="lmd_save_api" class="button button-primary" value="<?php esc_attr_e('Enregistrer', 'lmd-apps-ia'); ?>" /></p>
    </form>
    </div>
<?php if (!$lmd_suite_embed) : ?>
</div>
<?php endif; ?>
<script>
(function(){
    var cb = document.querySelector('input[name="lmd_imgbb_enabled"]');
    var key = document.getElementById('lmd_imgbb_key');
    if (!cb || !key) return;
    function sync() {
        key.readOnly = !cb.checked;
        key.classList.toggle('lmd-api-config-input-muted', !cb.checked);
    }
    cb.addEventListener('change', sync);
    sync();
})();
</script>
