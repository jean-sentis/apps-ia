<?php
/**
 * Vue Facturation
 */
if (!defined('ABSPATH')) {
    exit;
}
$pricing = new LMD_Pricing();
$tiers = $pricing->get_tiers();
?>
<div class="wrap lmd-page">
    <h1>Facturation LMD Apps IA</h1>
    <p class="lmd-ui-prose">Paliers d’estimation facturés — lecture seule (configuration en base).</p>
    <h2 class="lmd-ui-section-title">Paliers tarifaires</h2>
    <?php if (!empty($tiers)) : ?>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;max-width:560px;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Min (€)</th>
                <th>Max (€)</th>
                <th>Prix / estimation (€)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tiers as $t) : ?>
            <tr>
                <td><?php echo esc_html(number_format($t->min_amount, 0, ',', ' ')); ?></td>
                <td><?php echo $t->max_amount !== null ? esc_html(number_format($t->max_amount, 0, ',', ' ')) : '∞'; ?></td>
                <td><?php echo esc_html(number_format($t->price_per_estimation, 2, ',', ' ')); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else : ?>
    <div class="lmd-ui-panel"><p style="margin:0;">Aucun palier configuré. Désactivez puis réactivez le plugin pour créer les paliers par défaut.</p></div>
    <?php endif; ?>
</div>
