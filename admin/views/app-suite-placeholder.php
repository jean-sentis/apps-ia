<?php
/**
 * Écran placeholder pour une application de la suite (roadmap).
 *
 * @var string $lmd_placeholder_title
 * @var string $lmd_placeholder_lead
 */
if (!defined('ABSPATH')) {
    exit;
}
$lmd_placeholder_title = isset($lmd_placeholder_title) ? (string) $lmd_placeholder_title : 'Application';
$lmd_placeholder_lead = isset($lmd_placeholder_lead) ? (string) $lmd_placeholder_lead : '';
?>
<div class="wrap lmd-page lmd-suite-placeholder">
    <h1><?php echo esc_html($lmd_placeholder_title); ?></h1>
    <div class="lmd-ui-panel">
        <p class="lmd-ui-prose" style="margin:0;"><?php echo esc_html($lmd_placeholder_lead); ?></p>
        <p style="margin:16px 0 0;"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lmd-apps-ia')); ?>"><?php esc_html_e('Retour à la vue d’ensemble', 'lmd-apps-ia'); ?></a></p>
    </div>
</div>
