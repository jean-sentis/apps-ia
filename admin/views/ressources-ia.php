<?php
/**
 * Vue Ressources IA - Consommation simplifiée
 */
if (!defined('ABSPATH')) {
    exit;
}
$usage = class_exists('LMD_Api_Usage') ? new LMD_Api_Usage() : null;
$billing = $usage ? $usage->get_consumption_billing_summary() : null;
$pricing_cfg = class_exists('LMD_Api_Usage') ? LMD_Api_Usage::get_analysis_pricing_tiers() : ['free_granted' => 20, 'tiers' => []];
$is_parent = !is_multisite() || is_main_site();
?>
<div class="wrap lmd-ressources-ia lmd-page">
    <h1>Consommation — LMD Apps IA</h1>

    <?php if ($is_parent) : ?>
    <section class="lmd-ria-section lmd-ria-explanation">
        <h2>Comment fonctionne la recherche</h2>
        <p class="lmd-ria-lead">Le vendeur envoie photo, texte, origine et dimensions. L'IA orchestre des recherches visuelles ciblées et des enrichissements web, puis absorbe les pages utiles avant de décider quoi présenter au commissaire-priseur.</p>
        <div class="lmd-ria-flow-diagram lmd-ria-flow-v2">
            <div class="lmd-ria-flow-grid">
                <div class="lmd-ria-flow-col-vie">
                    <h3 class="lmd-ria-flow-col-title">La vraie vie</h3>
                    <div class="lmd-ria-flow-vendeur">
                        <span class="lmd-ria-flow-icon">📤</span>
                        <span>Vendeur</span>
                        <small>photo, texte, origine, dimensions</small>
                    </div>
                    <div class="lmd-ria-flow-arrow-vert">
                        <span class="lmd-ria-flow-arrow">↓</span>
                        <span class="lmd-ria-flow-arrow-label">demande d'estimation</span>
                    </div>
                    <div class="lmd-ria-flow-commissaire">
                        <span class="lmd-ria-flow-icon">🏛</span>
                        <span>Commissaire-priseur</span>
                    </div>
                </div>
                <div class="lmd-ria-flow-mini-col lmd-ria-flow-mini-1">
                    <div class="lmd-ria-flow-arrow-hz"><span class="lmd-ria-flow-arrow">→</span></div>
                    <div class="lmd-ria-flow-arrow-hz"><span class="lmd-ria-flow-arrow lmd-ria-flow-arrow-retour">←</span></div>
                </div>
                <div class="lmd-ria-flow-col-ias">
                    <h3 class="lmd-ria-flow-col-title">I.A.s différentes</h3>
                    <div class="lmd-ria-flow-immeuble">
                        <div class="lmd-ria-flow-ordinateur-screen">
                            <span class="lmd-ria-flow-badge">AIDE À L'ESTIMATION</span>
                            <div class="lmd-ria-flow-ia-item">
                                <span class="lmd-ria-flow-icon">👁</span>
                                <span>IA vision</span>
                            </div>
                            <div class="lmd-ria-flow-ia-item">
                                <span class="lmd-ria-flow-icon">🧠</span>
                                <span>IA analyse</span>
                            </div>
                            <div class="lmd-ria-flow-ia-item">
                                <span class="lmd-ria-flow-icon">⚖</span>
                                <span>IA arbitrage</span>
                            </div>
                            <div class="lmd-ria-flow-ia-item">
                                <span class="lmd-ria-flow-icon">✓</span>
                                <span>IA décision</span>
                            </div>
                        </div>
                        <div class="lmd-ria-flow-ordinateur-base"></div>
                    </div>
                </div>
                <div class="lmd-ria-flow-mini-col lmd-ria-flow-mini-2">
                    <div class="lmd-ria-flow-arrow-long"><span class="lmd-ria-flow-arrow-label">Requête visuelle ciblée</span><span class="lmd-ria-flow-arrow">→</span></div>
                    <div class="lmd-ria-flow-arrow-coude"><span>↓</span><span>←</span><span class="lmd-ria-flow-arrow-label">IA analyse</span></div>
                    <div class="lmd-ria-flow-arrow-long"><span class="lmd-ria-flow-arrow-label">recherches + enrichissements</span><span class="lmd-ria-flow-arrow">→</span></div>
                    <div class="lmd-ria-flow-arrow-coude"><span>↓</span><span>←</span><span class="lmd-ria-flow-arrow-label">Résultats</span></div>
                    <div class="lmd-ria-flow-arrow-long"><span class="lmd-ria-flow-arrow-label">téléchargement contenus</span><span class="lmd-ria-flow-arrow">→</span></div>
                    <div class="lmd-ria-flow-arrow-coude"><span>↓</span><span>←</span><span class="lmd-ria-flow-arrow-label">IA décision</span></div>
                    <div class="lmd-ria-flow-arrow-long"><span class="lmd-ria-flow-arrow-label">Estimation</span><span class="lmd-ria-flow-arrow">→</span></div>
                </div>
                <div class="lmd-ria-flow-col-web">
                    <div class="lmd-ria-flow-web-cartouche">
                        <span>Le Web</span>
                        <span class="lmd-ria-flow-arrow-inline">→</span>
                    </div>
                    <div class="lmd-ria-flow-node lmd-ria-flow-retour">
                        <span>Correspondances visuelles</span>
                        <small>liées à des textes</small>
                    </div>
                    <div class="lmd-ria-flow-etage">
                        <div class="lmd-ria-flow-node">
                            <span>Autres recherches visuelles</span>
                        </div>
                        <span class="lmd-ria-flow-arrow-plus">+</span>
                        <div class="lmd-ria-flow-node">
                            <span>Enrichissements de sens</span>
                            <small>sur le web</small>
                        </div>
                    </div>
                    <div class="lmd-ria-flow-node lmd-ria-flow-retour">
                        <span>Résultats</span>
                    </div>
                    <div class="lmd-ria-flow-node lmd-ria-flow-absorb">
                        <span class="lmd-ria-flow-icon">📄</span>
                        <span>Pages web</span>
                    </div>
                    <span class="lmd-ria-flow-arrow-plus">+</span>
                    <div class="lmd-ria-flow-node lmd-ria-flow-retour">
                        <span>Contenus absorbés</span>
                    </div>
                    <div class="lmd-ria-flow-aide">
                        <span class="lmd-ria-flow-icon">⚖️</span>
                        <span>Estimation</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="lmd-ria-section lmd-ria-config-tarif">
        <h2>Tarification analyse IA <small>(site parent)</small></h2>
        <form id="lmd-analysis-pricing-form" class="lmd-ria-pricing-form">
            <p>
                <label>Estimations offertes à l'installation <input type="number" name="free_granted" value="<?php echo esc_attr($pricing_cfg['free_granted']); ?>" min="0" step="1" /></label>
            </p>
            <h3>Paliers (à partir de la N-ième analyse)</h3>
            <div class="lmd-ria-tiers-editor">
                <?php
                $tiers = $pricing_cfg['tiers'];
                if (empty($tiers)) $tiers = [['min_paid' => 0, 'price' => 0.50], ['min_paid' => 20, 'price' => 0.33], ['min_paid' => 50, 'price' => 0.25]];
                foreach (array_slice($tiers, 0, 5) as $i => $t) :
                ?>
                <div class="lmd-ria-tier-row">
                    <label>À partir de <input type="number" name="tiers[<?php echo $i; ?>][min_paid]" value="<?php echo esc_attr($t['min_paid']); ?>" min="0" /> analyses</label>
                    <span>→</span>
                    <label><input type="text" name="tiers[<?php echo $i; ?>][price]" value="<?php echo esc_attr(number_format($t['price'], 2, '.', '')); ?>" placeholder="0,50" /> € HT</label>
                </div>
                <?php endforeach; ?>
            </div>
            <p><button type="submit" class="button button-primary">Enregistrer</button></p>
        </form>
    </section>

    <section class="lmd-ria-section lmd-ria-tarif">
        <h2>Tarification actuelle</h2>
        <ul>
            <li><strong><?php echo (int) $pricing_cfg['free_granted']; ?> estimations offertes</strong> à l'installation</li>
            <?php foreach ($pricing_cfg['tiers'] as $t) : ?>
            <li><strong><?php echo number_format($t['price'], 2, ',', ' '); ?> € HT</strong> à partir de <?php echo (int) $t['min_paid']; ?> analyses</li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($billing) : ?>
    <section class="lmd-ria-section lmd-ria-counters">
        <h2>Votre consommation</h2>

        <p class="lmd-ria-period">
            <strong>Période :</strong> du <?php echo esc_html(wp_date('j F Y', strtotime($billing['month_start']))); ?> au <?php echo esc_html(wp_date('j F Y', strtotime($billing['month_end']))); ?>
        </p>

        <div class="lmd-ria-cards">
            <div class="lmd-ria-card">
                <span class="lmd-ria-card-value"><?php echo esc_html((string) $billing['free_used_total']); ?> / <?php echo esc_html((string) $billing['free_granted']); ?></span>
                <span class="lmd-ria-card-label">Gratuites utilisées</span>
            </div>
            <div class="lmd-ria-card">
                <span class="lmd-ria-card-value"><?php echo esc_html((string) $billing['paid_total']); ?></span>
                <span class="lmd-ria-card-label">Facturées (total)</span>
            </div>
            <div class="lmd-ria-card">
                <span class="lmd-ria-card-value"><?php echo esc_html((string) $billing['analyses_this_month']); ?></span>
                <span class="lmd-ria-card-label">Ce mois (<?php echo esc_html($billing['month_label']); ?>)</span>
            </div>
            <div class="lmd-ria-card lmd-ria-card-total">
                <span class="lmd-ria-card-value"><?php echo esc_html(number_format($billing['amount_ht_this_month'], 2, ',', ' ')); ?>&nbsp;€</span>
                <span class="lmd-ria-card-label">Montant HT ce mois</span>
            </div>
        </div>

        <table class="widefat striped lmd-ria-table">
            <thead>
                <tr>
                    <th>Ce mois</th>
                    <th>Gratuites</th>
                    <th>Facturées</th>
                    <th>Montant HT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo esc_html($billing['month_label']); ?></td>
                    <td><?php echo esc_html((string) $billing['free_this_month']); ?></td>
                    <td><?php echo esc_html((string) $billing['paid_this_month']); ?></td>
                    <td><?php echo esc_html(number_format($billing['amount_ht_this_month'], 2, ',', ' ')); ?>&nbsp;€ HT</td>
                </tr>
            </tbody>
        </table>

        <p class="lmd-ria-ht">Tous les montants sont indiqués <strong>HT</strong> (hors taxes).</p>
    </section>

    <?php if ($is_parent) : ?>
    <section class="lmd-ria-section lmd-ria-parent">
        <h2>Pour le site parent</h2>
        <p>Le résumé de consommation est mis à jour automatiquement après chaque analyse. Pour récupérer les données depuis le site parent&nbsp;:</p>
        <pre><code>// Option WordPress
$summary = get_option('lmd_consumption_summary', []);

// Ou via fonction
$summary = $usage->get_consumption_billing_summary();
// $summary contient : free_granted, free_used_total, paid_total, total_analyses,
// month_start, month_end, analyses_this_month, free_this_month, paid_this_month,
// amount_ht_this_month, price_per_paid</code></pre>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!$is_parent && !$billing) : ?>
    <section class="lmd-ria-section lmd-ria-counters">
        <h2>Votre consommation</h2>
        <p>Aucune consommation pour le moment.</p>
    </section>
    <?php endif; ?>
</div>
