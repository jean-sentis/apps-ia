<?php
/**
 * Dossier d'aide — Guide complet
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap lmd-help lmd-page">
    <h1>Aide — LMD Apps IA</h1>

    <nav class="lmd-help-nav">
        <a href="#bienvenue">Bienvenue</a>
        <a href="#introduction">Introduction</a>
        <a href="#grille">Grille d'évaluation</a>
        <a href="#fiche">Fiche estimation</a>
        <a href="#analyse-ia">Analyse IA</a>
        <a href="#reference">Référence</a>
    </nav>

    <section id="bienvenue" class="lmd-help-section">
        <h2>Bienvenue</h2>
        <p>Bienvenue dans LMD Apps IA.</p>
        <p>Ce module vous accompagne pour traiter vos demandes d'estimation d'objets d'art et de collection. Il combine l'intelligence artificielle et l'organisation de vos dossiers pour vous faire gagner du temps.</p>
        <p>Utilisez le menu ci-dessus pour naviguer dans ce guide.</p>
    </section>

    <section id="introduction" class="lmd-help-section">
        <h2>Introduction — Un service pour vous faire gagner du temps</h2>
        <p>LMD Apps IA est conçu pour réduire le temps passé sur chaque demande d'estimation.</p>
        <p><strong>Deux leviers principaux :</strong></p>
        <ol>
            <li><strong>L'organisation</strong> — Centralisez vos demandes, ventes, vendeurs et planning. Filtrez par intérêt, estimation, thème. Suivez l'avancement de chaque dossier.</li>
            <li><strong>L'assistance IA</strong> — Pour chaque demande, l'IA analyse photos et description, propose une estimation, un niveau d'intérêt et des pistes de recherche. Vous gardez la décision finale, l'IA accélère le travail préparatoire.</li>
        </ol>
        <p>L'objectif : moins de temps par estimation, plus de clarté dans le suivi.</p>
    </section>

    <section id="grille" class="lmd-help-section">
        <h2>Grille d'évaluation — Mes estimations</h2>
        <p>La grille affiche <strong>toutes vos demandes</strong> sous forme de cartes. Elle permet une <strong>lecture rapide de l'avancée</strong> des estimations (tags, statut, IA).</p>

        <div class="lmd-help-schema">
            <h3>Repérage des zones (schéma numéroté)</h3>
            <div class="lmd-schema-grid">
                <div class="lmd-schema-item"><span class="lmd-schema-num">1</span> Barre de filtres (Échanges, Estimation, Intérêt, Catégorie, Période envoi, Vente, Vendeur, Recherche)</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">2</span> Sélecteur d'affichage (5, 4 ou 3 vignettes par ligne selon votre confort)</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">3</span> Grille de cartes — chaque carte = une demande</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">4</span> Analyse IA multi-demande : sélectionnez plusieurs cartes puis cliquez sur « Analyser » pour lancer l'IA sur plusieurs demandes en une fois</div>
            </div>
        </div>

        <h3>Fonctionnalités de la grille</h3>
        <ul>
            <li><strong>Vues personnalisées multi-filtre</strong> — Combinez Échanges, Estimation, Intérêt, Catégorie, Période, Vente, Vendeur.</li>
            <li><strong>Modifier l'affichage</strong> — Choisissez 3, 4 ou 5 vignettes par ligne selon votre confort de vue.</li>
            <li><strong>Déclencher une analyse IA multi-demande</strong> — Sélectionnez plusieurs cartes avec le bouton IA sur chaque carte, puis cliquez sur « Analyser » dans la barre fixe en bas pour obtenir le complément d'information dès que vous traitez les demandes.</li>
        </ul>
    </section>

    <section id="fiche" class="lmd-help-section">
        <h2>Fiche estimation — Vue détail</h2>
        <p>En cliquant sur une carte (ou « Voir »), vous ouvrez la fiche complète de la demande.</p>

        <div class="lmd-help-schema">
            <h3>Repérage des zones (schéma numéroté)</h3>
            <div class="lmd-schema-grid">
                <div class="lmd-schema-item"><span class="lmd-schema-num">1</span> <strong>Vue loupe, multi-photo</strong> — Photo principale en grand, vignettes en dessous. Cliquez sur une vignette pour l'agrandir. Ouverture en plein écran pour zoom.</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">2</span> <strong>Interface bleue (1er avis)</strong> — Prenez des notes, donnez des valeurs (titre, descriptif, dimension, estime basse/haute, prix de réserve). Les tags enregistrent automatiquement vos choix.</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">3</span> <strong>Module bleu « Réponse vendeur »</strong> — Aide à la réponse au vendeur : email du vendeur enregistré, votre signature enregistrée, aide à la rédaction avec formules enregistrées, proposition de questions par l'IA à insérer dans votre réponse.</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">4</span> <strong>Mode 2ème avis (mauve)</strong> — Si besoin de déléguer l'estimation : envoyez à qui vous le souhaitez un lien vers la page pour qu'il fasse son estimation (2ème avis).</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">5</span> <strong>Barre de tags</strong> — VV/VJ, Message, Intérêt, Estimation, Thème de vente. Cliquez pour modifier. Les propositions IA s'affichent en vert.</div>
                <div class="lmd-schema-item"><span class="lmd-schema-num">6</span> <strong>Onglets de l'analyse IA</strong> — Identité/Biographie, Correspondances, Résultats marché, État, Questions. Cliquez sur un onglet pour afficher le contenu.</div>
            </div>
        </div>
    </section>

    <section id="analyse-ia" class="lmd-help-section">
        <h2>L'analyse de l'IA — Optionnelle mais puissante</h2>
        <p>L'analyse IA est <strong>optionnelle</strong>. Vous pouvez traiter une demande sans jamais la lancer. Mais elle offre cinq services détaillés :</p>

        <ol class="lmd-help-ia-list">
            <li><strong>Identification et biographie</strong> — Synthèse de ce que l'objet est au plus près de la vérité (analyse des photos, correspondances visuelles), éléments biographiques (auteur, mouvement, époque), authenticité (signatures, poinçons).</li>
            <li><strong>Correspondances visuelles</strong> — Recherche d'objets similaires via Google Lens. Pour chaque correspondance : verdict (identique, même modèle, similaire, différent) avec détails de comparaison. Les liens mènent au contenu cité ou le contenu scrapé est affiché.</li>
            <li><strong>Résultats de marché</strong> — Ventes comparables, prix d'adjudication, estimations. Sources citées et liens vérifiés (ou contenu scrapé si le lien est inaccessible).</li>
            <li><strong>État et condition</strong> — État de l'objet, comparaison avec les références de marché pour ajuster l'estimation.</li>
            <li><strong>Questions au propriétaire</strong> — 2 à 5 questions pertinentes pour compléter l'information (facture, garantie, dimensions, etc.). Ces questions peuvent être insérées dans votre réponse au vendeur.</li>
        </ol>

        <p>L'IA propose : fourchette de prix, niveau d'intérêt, fiabilité. Les propositions s'affichent en vert dans la barre de tags. Vous validez, ajustez ou signalez une erreur pour relancer.</p>
    </section>

    <section id="reference" class="lmd-help-section">
        <h2>Référence — Shortcodes et intégration</h2>
        <p><strong>Intégrer le formulaire sur votre site :</strong></p>
        <ul>
            <li><code>[lmd_formulaire_estimation]</code> — Formulaire complet de demande d'estimation</li>
            <li><code>[lmd_demande_estimation]</code> — Alias du formulaire</li>
            <li><code>[lmd_demande_estimation style="contact"]</code> — Formulaire compact pour page contact</li>
        </ul>
        <p>Placez le shortcode sur une page (ex. « Demande d'estimation » ou « Contact »). Les visiteurs pourront envoyer photos et description ; les demandes apparaîtront dans « Mes estimations ».</p>
    </section>
</div>

<style>
.lmd-help-nav { margin: 20px 0 32px; display: flex; flex-wrap: wrap; gap: 10px; }
.lmd-help-nav a { padding: 8px 14px; background: #f3f4f6; border-radius: 6px; text-decoration: none; color: #374151; font-size: 13px; }
.lmd-help-nav a:hover { background: #e5e7eb; }
.lmd-help-section { margin-bottom: 40px; padding-top: 16px; }
.lmd-help-section h2 { margin-top: 0; padding-top: 24px; border-top: 1px solid #e5e7eb; }
.lmd-help-section h2:first-of-type { border-top: none; padding-top: 0; }
.lmd-help-schema { margin: 20px 0; padding: 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; }
.lmd-schema-grid { display: grid; gap: 12px; }
.lmd-schema-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 14px; background: #fff; border-radius: 8px; border: 1px solid #e5e7eb; }
.lmd-schema-num { flex-shrink: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: #3b82f6; color: #fff; font-weight: 700; font-size: 14px; border-radius: 6px; }
.lmd-help-ia-list { counter-reset: ia; list-style: none; padding-left: 0; }
.lmd-help-ia-list li { counter-increment: ia; padding-left: 48px; position: relative; margin-bottom: 16px; }
.lmd-help-ia-list li::before { content: counter(ia); position: absolute; left: 0; width: 28px; height: 28px; line-height: 28px; text-align: center; background: #22c55e; color: #fff; font-weight: 700; font-size: 14px; border-radius: 6px; }
</style>
