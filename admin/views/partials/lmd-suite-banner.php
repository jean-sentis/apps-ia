<?php
/**
 * Bandeau noir — logo LMD (comme le menu WP, plus grand) + titre centré.
 *
 * Variables attendues avant include :
 * @var string $lmd_suite_banner_title   Titre principal (obligatoire).
 * @var string $lmd_suite_banner_subtitle Sous-titre optionnel.
 */
if (!defined('ABSPATH')) {
    exit;
}
$lmd_suite_banner_title = isset($lmd_suite_banner_title) ? (string) $lmd_suite_banner_title : 'LMD Apps IA';
$lmd_suite_banner_subtitle = isset($lmd_suite_banner_subtitle) ? (string) $lmd_suite_banner_subtitle : '';
$lmd_suite_logo = LMD_PLUGIN_URL . 'assets/lmd-logo-menu.png';
?>
<div class="lmd-suite-app-banner" role="banner">
    <div class="lmd-suite-app-banner__inner">
        <img src="<?php echo esc_url($lmd_suite_logo); ?>" alt="" class="lmd-suite-app-banner__logo" width="52" height="52" decoding="async" />
        <div class="lmd-suite-app-banner__titles">
            <h1 class="lmd-suite-app-banner__title"><?php echo esc_html($lmd_suite_banner_title); ?></h1>
            <?php if ($lmd_suite_banner_subtitle !== '') : ?>
            <p class="lmd-suite-app-banner__subtitle"><?php echo esc_html($lmd_suite_banner_subtitle); ?></p>
            <?php endif; ?>
        </div>
        <span class="lmd-suite-app-banner__spacer" aria-hidden="true"></span>
    </div>
</div>
