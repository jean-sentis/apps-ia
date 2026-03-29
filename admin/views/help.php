<?php
/**
 * Dossier d'aide — sous-onglets (pas d’ancres)
 */
if (!defined('ABSPATH')) {
    exit;
}
$lmd_help_embed = !empty($lmd_help_embed);
$help_sub = isset($_GET['help_sub']) ? sanitize_key(wp_unslash($_GET['help_sub'])) : 'introduction';
$help_allowed = ['introduction', 'grille', 'fiche', 'analyse-ia'];
if (!in_array($help_sub, $help_allowed, true)) {
    $help_sub = 'introduction';
}

$lmd_help_tab_url = static function ($key) use ($lmd_help_embed) {
    if ($lmd_help_embed && function_exists('lmd_app_estimation_admin_url')) {
        return lmd_app_estimation_admin_url('help', ['help_sub' => $key]);
    }
    return add_query_arg(['page' => 'lmd-help', 'help_sub' => $key], admin_url('admin.php'));
};

$help_tabs = [
    'introduction' => __('Introduction', 'lmd-apps-ia'),
    'grille' => __('Grille d’évaluation', 'lmd-apps-ia'),
    'fiche' => __('Fiche estimation', 'lmd-apps-ia'),
    'analyse-ia' => __('Analyse IA', 'lmd-apps-ia'),
];
?>
<?php if (!$lmd_help_embed) : ?>
<div class="wrap lmd-help lmd-page">
    <h1><?php esc_html_e('Aide', 'lmd-apps-ia'); ?></h1>
<?php else : ?>
<div class="lmd-help lmd-help--embed lmd-page">
<?php endif; ?>

    <nav class="lmd-help-subtabs" aria-label="<?php esc_attr_e('Sections de l’aide', 'lmd-apps-ia'); ?>">
        <?php foreach ($help_tabs as $key => $label) : ?>
        <a class="lmd-help-subtab <?php echo $help_sub === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($lmd_help_tab_url($key)); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="lmd-help-subpanels">
    <?php if ($help_sub === 'introduction') : ?>
    <section class="lmd-help-section" aria-labelledby="lmd-help-h-intro">
        <h2 id="lmd-help-h-intro"><?php esc_html_e('Introduction — Un service pour vous faire gagner du temps', 'lmd-apps-ia'); ?></h2>
        <p><?php esc_html_e('L’aide à l’estimation est conçue pour vous aider à réduire le temps passé à estimer.', 'lmd-apps-ia'); ?></p>
        <p><strong><?php esc_html_e('Deux leviers principaux :', 'lmd-apps-ia'); ?></strong></p>
        <ol>
            <li><strong><?php esc_html_e('L’organisation', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Centralisez vos demandes, ventes, vendeurs et planning. Filtrez par intérêt, estimation, thème. Répondez au vendeur et gardez ces messages sur votre ordinateur si vous le souhaitez.', 'lmd-apps-ia'); ?> <?php esc_html_e('Dans Réglage affichages et réponses vendeurs, sous « Votre réponse au vendeur en copie cachée », vous pouvez indiquer pour quelles catégories de réponse (intérêt, paliers d’estimation) vous ne souhaitez pas recevoir le mail en copie.', 'lmd-apps-ia'); ?> <?php esc_html_e('Suivez l’avancement de chaque dossier.', 'lmd-apps-ia'); ?></li>
            <li><strong><?php esc_html_e('L’assistance IA', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Pour chaque demande, l’IA analyse photos et description, propose une estimation, un niveau d’intérêt et des pistes de recherche. Vous gardez la décision finale, l’IA accélère le travail préparatoire.', 'lmd-apps-ia'); ?></li>
        </ol>
        <p><?php esc_html_e('L’objectif : moins de temps par estimation, plus de clarté dans le suivi.', 'lmd-apps-ia'); ?></p>
    </section>

    <?php elseif ($help_sub === 'grille') : ?>
    <section class="lmd-help-section">
        <h2><?php esc_html_e('Grille d’évaluation — Mes estimations', 'lmd-apps-ia'); ?></h2>
        <p><?php echo wp_kses_post(__('La grille affiche <strong>toutes vos demandes</strong> sous forme de cartes. Elle permet une <strong>lecture rapide de l’avancée</strong> des estimations (tags, statut, IA).', 'lmd-apps-ia')); ?></p>

        <div class="lmd-help-schema">
            <h3><?php esc_html_e('Repérage des zones (schéma numéroté)', 'lmd-apps-ia'); ?></h3>
            <div class="lmd-schema-grid">
                <div class="lmd-schema-item"><span class="lmd-schema-num">1</span> <?php esc_html_e('Barre de filtres (Échanges, Estimation, Intérêt, Catégorie, Période envoi, Vente, Vendeur, Recherche)', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">2</span> <?php esc_html_e('Sélecteur d’affichage (5, 4 ou 3 vignettes par ligne selon votre confort)', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">3</span> <?php esc_html_e('Grille de cartes — chaque carte = une demande', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">4</span> <?php esc_html_e('Analyse IA multi-demande : sélectionnez plusieurs cartes puis cliquez sur « Analyser » pour lancer l’IA sur plusieurs demandes en une fois', 'lmd-apps-ia'); ?></div>
            </div>
        </div>

        <h3><?php esc_html_e('Fonctionnalités de la grille', 'lmd-apps-ia'); ?></h3>
        <ul>
            <li><strong><?php esc_html_e('Vues personnalisées multi-filtre', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Combinez Échanges, Estimation, Intérêt, Catégorie, Période, Vente, Vendeur.', 'lmd-apps-ia'); ?></li>
            <li><strong><?php esc_html_e('Modifier l’affichage', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Choisissez 3, 4 ou 5 vignettes par ligne selon votre confort de vue.', 'lmd-apps-ia'); ?></li>
            <li><strong><?php esc_html_e('Déclencher une analyse IA multi-demande', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Sélectionnez plusieurs cartes avec le bouton IA sur chaque carte, puis cliquez sur « Analyser » dans la barre fixe en bas pour obtenir le complément d’information dès que vous traitez les demandes.', 'lmd-apps-ia'); ?></li>
        </ul>
    </section>

    <?php elseif ($help_sub === 'fiche') : ?>
    <section class="lmd-help-section">
        <h2><?php esc_html_e('Fiche estimation — Vue détail', 'lmd-apps-ia'); ?></h2>
        <p><?php esc_html_e('En cliquant sur une carte (ou « Voir »), vous ouvrez la fiche complète de la demande.', 'lmd-apps-ia'); ?></p>

        <div class="lmd-help-schema">
            <h3><?php esc_html_e('Repérage des zones (schéma numéroté)', 'lmd-apps-ia'); ?></h3>
            <div class="lmd-schema-grid">
                <div class="lmd-schema-item"><span class="lmd-schema-num">1</span> <strong><?php esc_html_e('Vue loupe, multi-photo', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Photo principale en grand, vignettes en dessous. Cliquez sur une vignette pour l’agrandir. Ouverture en plein écran pour zoom.', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">2</span> <strong><?php esc_html_e('Interface bleue (1er avis)', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Prenez des notes, donnez des valeurs (titre, descriptif, dimension, estime basse/haute, prix de réserve). Les tags enregistrent automatiquement vos choix.', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">3</span> <strong><?php esc_html_e('Module bleu « Réponse vendeur »', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Aide à la réponse au vendeur : email du vendeur enregistré, votre signature enregistrée, aide à la rédaction avec formules enregistrées, proposition de questions par l’IA à insérer dans votre réponse.', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">4</span> <strong><?php esc_html_e('Mode 2ème avis (mauve)', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Si besoin de déléguer l’estimation : envoyez à qui vous le souhaitez un lien vers la page pour qu’il fasse son estimation (2ème avis).', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">5</span> <strong><?php esc_html_e('Barre de tags', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('VV/VJ, Message, Intérêt, Estimation, Thème de vente. Cliquez pour modifier. Les propositions IA s’affichent en vert.', 'lmd-apps-ia'); ?></div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">6</span> <strong><?php esc_html_e('Onglets de l’analyse IA', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Identité/Biographie, Correspondances, Résultats marché, État, Questions. Cliquez sur un onglet pour afficher le contenu.', 'lmd-apps-ia'); ?></div>
            </div>
        </div>
    </section>

    <?php elseif ($help_sub === 'analyse-ia') : ?>
    <section class="lmd-help-section">
        <h2><?php esc_html_e('L’analyse de l’IA — Optionnelle mais puissante', 'lmd-apps-ia'); ?></h2>
        <p><?php echo wp_kses_post(__('L’analyse IA est <strong>optionnelle</strong>. Vous pouvez traiter une demande sans jamais la lancer. Mais elle offre cinq services détaillés :', 'lmd-apps-ia')); ?></p>

        <ol class="lmd-help-ia-list">
            <li><strong><?php esc_html_e('Identification et biographie', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Synthèse de ce que l’objet est au plus près de la vérité (analyse des photos, correspondances visuelles), éléments biographiques (auteur, mouvement, époque), authenticité (signatures, poinçons).', 'lmd-apps-ia'); ?></li>
            <li><strong><?php esc_html_e('Correspondances visuelles', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Recherche d’objets similaires via Google Lens. Pour chaque correspondance : verdict (identique, même modèle, similaire, différent) avec détails de comparaison. Les liens mènent au contenu cité ou le contenu scrapé est affiché.', 'lmd-apps-ia'); ?></li>
            <li><strong><?php esc_html_e('Résultats de marché', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('Ventes comparables, prix d’adjudication, estimations. Sources citées et liens vérifiés (ou contenu scrapé si le lien est inaccessible).', 'lmd-apps-ia'); ?></li>
            <li><strong><?php esc_html_e('État et condition', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('État de l’objet, comparaison avec les références de marché pour ajuster l’estimation.', 'lmd-apps-ia'); ?></li>
            <li><strong><?php esc_html_e('Questions au propriétaire', 'lmd-apps-ia'); ?></strong> — <?php esc_html_e('2 à 5 questions pertinentes pour compléter l’information (facture, garantie, dimensions, etc.). Ces questions peuvent être insérées dans votre réponse au vendeur.', 'lmd-apps-ia'); ?></li>
        </ol>

        <p><?php esc_html_e('L’IA propose : fourchette de prix, niveau d’intérêt, fiabilité. Les propositions s’affichent en vert dans la barre de tags. Vous validez, ajustez ou signalez une erreur pour relancer.', 'lmd-apps-ia'); ?></p>
    </section>
    <?php endif; ?>
    </div>
</div>

<style>
.lmd-help-subtabs { display: flex; flex-wrap: wrap; gap: 10px 12px; margin: 0 0 20px; padding: 0; align-items: center; }
.lmd-help-subtab {
    display: inline-flex;
    align-items: center;
    padding: 10px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    background: #fff;
    color: #4b5563;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    line-height: 1.2;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.lmd-help-subtab:hover { background: #f9fafb; border-color: #d1d5db; color: #111827; }
.lmd-help-subtab.is-active {
    background: #ecfdf5;
    border-color: #059669;
    color: #065f46;
    box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.15);
}
.lmd-help-subpanels { margin-top: 0; }
.lmd-help-section { margin-bottom: 0; padding-top: 0; }
.lmd-help-section h2:first-of-type { margin-top: 0; }
.lmd-help-schema { margin: 20px 0; padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; }
.lmd-schema-grid { display: grid; gap: 12px; }
.lmd-schema-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 14px; background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 13px; line-height: 1.45; }
.lmd-schema-num { flex-shrink: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: #3b82f6; color: #fff; font-weight: 700; font-size: 14px; border-radius: 6px; }
.lmd-help-ia-list { counter-reset: ia; list-style: none; padding-left: 0; }
.lmd-help-ia-list li { counter-increment: ia; padding-left: 48px; position: relative; margin-bottom: 16px; }
.lmd-help-ia-list li::before { content: counter(ia); position: absolute; left: 0; width: 28px; height: 28px; line-height: 28px; text-align: center; background: #22c55e; color: #fff; font-weight: 700; font-size: 14px; border-radius: 6px; }
</style>
