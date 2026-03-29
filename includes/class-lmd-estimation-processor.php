<?php
/**
 * Pipeline d'analyse des estimations (logique Lovable)
 * Gemini (analyse) + SerpAPI Google Lens (recherche par image) + Firecrawl (scraping)
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Estimation_Processor {

    private $api;
    private $db;

    public function __construct() {
        $this->api = class_exists('LMD_Api_Manager') ? new LMD_Api_Manager() : null;
        $this->db = class_exists('LMD_Database') ? new LMD_Database() : null;
    }

    public function run_analysis($estimation_id) {
        $est = $this->db ? $this->db->get_estimation($estimation_id) : null;
        if (!$est) {
            return ['success' => false, 'message' => 'Estimation introuvable'];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'lmd_estimations';

        delete_transient('lmd_analysis_error_' . $estimation_id);
        $this->set_progress($estimation_id, 10, 'Préparation...');

        $description = $est->description ?? '';
        $photos = $this->parse_photos($est->photos);

        // 2. SerpAPI Google Lens - recherche par image (URL publique requise)
        $lens_data = [];
        $this->set_progress($estimation_id, 15, 'Chargement image...');
        $image_url_public = $this->get_serpapi_image_url($photos, $estimation_id);
        if ($image_url_public && $this->api && method_exists($this->api, 'call_serpapi_lens')) {
            $this->set_progress($estimation_id, 20, 'Recherche Google Lens...');
            $lens_query = $this->enrich_lens_query($description);
            $lens_data = $this->api->call_serpapi_lens($image_url_public, $lens_query, 'visual_matches');
            $this->log_api_usage('serpapi', 1, $estimation_id);
        } else {
            $this->set_progress($estimation_id, 20, 'Pas de Lens (image locale ou sans clé)');
        }

        // 3. Firecrawl - scraper les URLs des résultats Lens (max 3) pour enrichir
        $scraped_content = '';
        if (!empty($lens_data['visual_matches']) && $this->api && $this->api->get_firecrawl_key()) {
            $this->set_progress($estimation_id, 40, 'Scraping web (Firecrawl)...');
            $urls = array_slice(array_filter(array_column($lens_data['visual_matches'], 'link')), 0, 3);
            $i = 0;
            foreach ($urls as $u) {
                if (filter_var($u, FILTER_VALIDATE_URL)) {
                    $this->set_progress($estimation_id, 40 + (int) (20 * $i / max(1, count($urls))), 'Scraping page ' . ($i + 1) . '/' . count($urls) . '...');
                    $scraped = $this->api->call_firecrawl_scrape($u);
                    if (empty($scraped['error']) && !empty($scraped['content'])) {
                        $scraped_content .= "\n--- Source: $u ---\n" . substr($scraped['content'], 0, 3000);
                    }
                    if (empty($scraped['error'])) {
                        $this->log_api_usage('firecrawl', 1, $estimation_id);
                    }
                    $i++;
                }
            }
        }

        // 4. Gemini - analyse complète
        $this->set_progress($estimation_id, 65, 'Analyse IA (Gemini)...');
        $user_explanation = get_transient('lmd_ai_error_explanation_' . $estimation_id);
        if ($user_explanation) {
            delete_transient('lmd_ai_error_explanation_' . $estimation_id);
        }
        $ai_output = $this->run_gemini_analysis($description, $photos, $lens_data, $scraped_content, $user_explanation ?: '');

        if (!isset($ai_output['error'])) {
            $this->log_api_usage('gemini', 1, $estimation_id);
        }
        if (isset($ai_output['error'])) {
            $err = $ai_output['error'];
            set_transient('lmd_analysis_error_' . $estimation_id, $err, 300);
            $wpdb->update($table, ['status' => 'new'], ['id' => $estimation_id], ['%s'], ['%d']);
            $this->set_progress($estimation_id, 0, 'Erreur');
            return ['success' => false, 'message' => $err];
        }

        $ai_output = $this->enrich_ai_output($ai_output, $lens_data, $scraped_content);

        $this->set_progress($estimation_id, 90, 'Enregistrement...');

        // 5. Enregistrer le résultat (réinitialiser ai_error_reported_at pour afficher les nouvelles réponses comme la première fois)
        $cols = $wpdb->get_col("DESCRIBE $table");
        $up = ['status' => 'ai_analyzed', 'ai_analysis' => wp_json_encode($ai_output)];
        if (in_array('ai_error_reported_at', $cols, true)) {
            $up['ai_error_reported_at'] = null;
        }
        $fmt = array_fill(0, count($up), '%s');
        $wpdb->update($table, $up, ['id' => $estimation_id], $fmt, ['%d']);

        if (function_exists('lmd_update_consumption_summary')) {
            lmd_update_consumption_summary();
        }

        return ['success' => true];
    }

    /**
     * Enrichit la requête Lens pour améliorer les correspondances.
     * - Vins : ajoute "millésime X" si une année est mentionnée.
     * - Figurines/collectibles (Iron Man, Marvel, etc.) : ajoute "figurine statue collectible" pour affiner.
     */
    private function enrich_lens_query($description) {
        $d = trim((string) $description);
        if ($d === '') {
            return null;
        }
        // Vins
        $wine_keywords = ['vin', 'bouteille', 'château', 'domaine', 'cru', 'millésime', 'champagne', 'cognac', 'whisky'];
        foreach ($wine_keywords as $kw) {
            if (stripos($d, $kw) !== false) {
                if (preg_match('/\b(19|20)\d{2}\b/', $d, $m)) {
                    return $d . ' millésime ' . $m[0];
                }
                return $d;
            }
        }
        // Figurines, statuettes, pop culture (Iron Man, Marvel, DC, etc.)
        $collectible_triggers = ['iron man', 'batman', 'superman', 'spider-man', 'marvel', 'dc comics', 'figurine', 'statuette', 'statue', 'sideshow', 'hot toys', 'prime 1', 'kotobukiya', 'bandai', 'funko', 'lego', 'maquette', 'bust'];
        foreach ($collectible_triggers as $kw) {
            if (stripos($d, $kw) !== false) {
                return $d . ' figurine statue collectible';
            }
        }
        return $d;
    }

    private function log_api_usage($api_name, $units, $estimation_id) {
        if (class_exists('LMD_Api_Usage')) {
            $usage = new LMD_Api_Usage();
            $usage->log($api_name, $units, $estimation_id);
        }
    }

    private function set_progress($estimation_id, $percent, $step) {
        set_transient('lmd_analysis_progress_' . $estimation_id, [
            'percent' => min(100, max(0, (int) $percent)),
            'step' => $step,
        ], 300);
    }

    private function parse_photos($photos_raw) {
        if (empty($photos_raw)) {
            return [];
        }
        $decoded = json_decode($photos_raw, true);
        return is_array($decoded) ? $decoded : (is_string($photos_raw) ? [$photos_raw] : []);
    }

    private function get_first_photo_public_url($photos) {
        if (empty($photos)) {
            return null;
        }
        $first = is_array($photos[0]) ? ($photos[0]['url'] ?? $photos[0]['path'] ?? $photos[0]['file'] ?? '') : $photos[0];
        if (!$first || !is_string($first)) {
            return null;
        }
        if (strpos($first, 'http') === 0) {
            return $first;
        }
        $upload = wp_upload_dir();
        $basedir = $upload['basedir'];
        $baseurl = $upload['baseurl'];
        $fullpath = (strpos($first, $basedir) === 0) ? $first : $basedir . '/' . ltrim(str_replace('\\', '/', $first), '/');
        if (file_exists($fullpath)) {
            return str_replace($basedir, $baseurl, $fullpath);
        }
        return $baseurl . '/' . ltrim(str_replace('\\', '/', $first), '/');
    }

    /**
     * Retourne une URL publique pour SerpAPI.
     * En production (URL publique) : utilise l'URL directe, sans ImgBB.
     * En local (localhost) : upload vers ImgBB pour obtenir une URL accessible par SerpAPI.
     */
    private function get_serpapi_image_url($photos, $estimation_id = null) {
        if (empty($photos)) {
            return null;
        }
        $first = is_array($photos[0]) ? ($photos[0]['url'] ?? $photos[0]['path'] ?? $photos[0]['file'] ?? '') : $photos[0];
        if (!$first || !is_string($first)) {
            return null;
        }

        $upload = wp_upload_dir();
        $basedir = $upload['basedir'];
        $baseurl = $upload['baseurl'];

        $url = $this->get_first_photo_public_url($photos);
        if (!$url) {
            return null;
        }

        $url_is_local = (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?/#i', $url)
            || (bool) preg_match('#^https?://[^/]*\.local(domain)?(:\d+)?/#i', $url);

        if (!$url_is_local) {
            return $url;
        }

        $fullpath = null;
        if (strpos($first, 'http') !== 0) {
            $fullpath = (strpos($first, $basedir) === 0) ? $first : $basedir . '/' . ltrim(str_replace('\\', '/', $first), '/');
        }
        if ($fullpath === null && $url && is_string($baseurl) && strpos($url, $baseurl) === 0) {
            $rel = substr($url, strlen($baseurl));
            $fullpath = $basedir . $rel;
        }

        if (($fullpath && file_exists($fullpath)) && $this->api && $this->api->get_imgbb_key()) {
            $imgbb_url = $this->api->upload_to_imgbb($fullpath, false);
            if ($imgbb_url) {
                $this->log_api_usage('imgbb', 1, $estimation_id);
                return $imgbb_url;
            }
        }

        return null;
    }

    private function run_gemini_analysis($description, $photos, $lens_data, $scraped_content, $user_explanation = '') {
        if (!$this->api) {
            return $this->empty_ai_output();
        }

        $prompt = $this->build_gemini_prompt($description, $lens_data, $scraped_content, $user_explanation);
        $images = $this->resolve_images_for_gemini($photos);

        $result = $this->api->call_gemini($prompt, $images);

        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $json = $this->extract_json_from_response($result['text']);
        if (!$json) {
            return ['error' => 'Réponse Gemini invalide (JSON attendu)'];
        }

        return $this->normalize_ai_output($json);
    }

    private function build_gemini_prompt($description, $lens_data, $scraped_content, $user_explanation = '') {
        $lens_summary = '';
        if (!empty($lens_data['visual_matches'])) {
            $items = array_slice($lens_data['visual_matches'], 0, 8);
            $lines = [];
            foreach ($items as $i => $m) {
                $title = $m['title'] ?? '';
                $link = $m['link'] ?? '';
                $lines[] = sprintf('[%d] %s %s', $i, $title, $link ? " — $link" : '');
            }
            $lens_summary = "Résultats Google Lens (similaires visuels) — Tu DOIS fournir un verdict visual_comparisons pour CHAQUE index:\n" . implode("\n", $lines);
        }

        $slugs_interet = 'pas_pour_nous, peu_interessant, a_examiner, interessant, tres_interessant, exceptionnel';
        $est_opts = function_exists('lmd_get_estimation_options_merged') ? lmd_get_estimation_options_merged() : [];
        $slugs_estimation = !empty($est_opts) ? implode(', ', array_map(function ($o) { return $o['slug']; }, $est_opts)) : 'moins_25, moins_100, moins_500, moins_1000, moins_5000, plus_5000';
        $theme_opts = function_exists('lmd_get_theme_vente_options_merged') ? lmd_get_theme_vente_options_merged() : [];
        $slugs_theme = !empty($theme_opts) ? implode(', ', array_map(function ($o) { return $o['slug']; }, $theme_opts)) : 'tableaux_dessins_anciens, art_moderne, art_contemporain, arts_decoratifs_design, mobilier_objets_art, bijoux_joaillerie, mode_maroquinerie_luxe, livres_manuscrits_autographes, vins_spiritueux, vehicules_collection, arts_premiers_civilisations';

        $categories_ref = <<<'CATEGORIES'
**CATÉGORIES DE VENTE (utilise ces slugs pour theme_vente et adapte ton expertise) :**

1. tableaux_dessins_anciens — Tableaux & Dessins anciens (XVe–XIXe) : écoles italienne, flamande, française, espagnole. Portraits, scènes religieuses, mythologiques, paysages, marines. Dessins anciens, sanguines, études. Expertise : attribution, état, provenance, littérature.

2. art_moderne — Art moderne : impressionnisme, post-impressionnisme, fauvisme, cubisme, surréalisme, abstraction. Peintures, sculptures, œuvres sur papier, estampes. Expertise : catalogues raisonnés, certificats, successions, historique d'expositions.

3. art_contemporain — Art contemporain (depuis années 1960) : peinture, photo, installations, art conceptuel, art urbain. Expertise : cote internationale, galeries, foires.

4. arts_decoratifs_design — Arts décoratifs & Design (XVIIIe–XXIe) : Art nouveau, Art déco, design 1950–2000. Luminaires, verrerie, céramique. Expertise : signature, édition, état d'origine.

5. mobilier_objets_art — Mobilier & Objets d'art : commode, bureau, armoire, siège Louis XV/XVI, Empire. Bronzes, pendules, sculptures décoratives, ivoires. Expertise : marqueterie, estampilles, restauration, authenticité.

6. bijoux_joaillerie — Bijoux & Joaillerie : bagues, colliers, bracelets, broches, diamants, pierres précieuses. Montres (Rolex, Patek Philippe). Créations Cartier, Van Cleef. Expertise : qualité des pierres, carats, certificats gemmologiques, signature.

7. mode_maroquinerie_luxe — Mode & Maroquinerie de luxe : sacs Hermès, Chanel, Louis Vuitton. Haute couture, vintage, éditions limitées. Expertise : demande internationale, état de conservation.

8. livres_manuscrits_autographes — Livres, Manuscrits & Autographes : éditions originales, incunables, livres illustrés. Correspondances, manuscrits littéraires. Expertise : rareté, état, reliure, importance historique.

9. vins_spiritueux — Vins & Spiritueux : grands crus, millésimes rares, champagnes, cognacs, whiskies. Expertise : traçabilité, conservation, niveau des bouteilles.

10. vehicules_collection — Véhicules de collection : automobiles anciennes, youngtimers, motos. Expertise : historique, matching numbers, état mécanique, restauration.

11. arts_premiers_civilisations — Arts premiers & Civilisations : objets africains, océaniens, amérindiens. Antiquités grecques, romaines, égyptiennes, asiatiques. Expertise : authenticité, provenance, conformité légale.

12. electromenager_objets_commerce — Électroménager & Objets du commerce : réfrigérateurs, lave-linge, TV, petit électroménager, high-tech. Expertise : date de lancement du modèle (âge max), facture, garantie ou prolongation de garantie.

13. autres — Autres : tout objet ou catégorie ne rentrant pas clairement dans les rubriques ci-dessus. Expertise : contextualiser au mieux (usage, matériaux, époque probable).
CATEGORIES;

        $lovable_full = <<<'LOVABLE'
Expert commissaire-priseur. Analyse croisée : photos → correspondances visuelles → recherche web.

RÈGLES IMPÉRATIVES :
- Ne JAMAIS se paraphraser : chaque information doit apparaître UNE SEULE FOIS, dans le champ le plus approprié. Pas de répétition entre summary, identity, condition, market_results.
- Analyse visuelle INDÉPENDANTE d'abord, puis confronte aux sources web.
- Ignore le titre d'un éventuel "lot similaire" mentionné par le propriétaire.
- Jamais de certitude sur un artiste sauf signature lisible ou sources convergentes.
- SANS correspondances visuelles utilisables (aucune ou toutes "différent") : ne JAMAIS attribuer à un artiste spécifique. Utilise "pourrait évoquer", "à rapprocher de", "style de", "dans la manière de". Pas d'affirmation du type "peinture de X" sans preuve visuelle.
- PRUDENCE ET RESPONSABILITÉ : En cas de doute, utilise des formulations conditionnelles. MAIS quand les preuves sont ÉCRASANTES (correspondance visuelle identique ou même_modèle, site spécialisé confirmant, plusieurs références convergentes), tu DOIS prendre la responsabilité et affirmer l'authenticité : "Il s'agit du modèle X", "Authentique", "Identification certaine". Ne pas rester dans le flou quand tout converge.
- FIABILITÉ (reliability) : Sois SÉVÈRE. Règles strictes :
 • Faible = Aucune source fiable, identification très incertaine, pas de vente comparable trouvée. OU une seule référence de prix. OU prix lu approximativement.
 • Moyenne = Identification probable mais non confirmée par des ventes comparables. OU identification confirmée par une source ET un prix d'adjudication. SI une seule référence de prix → MAX Moyenne.
 • Élevée = Identification certaine avec PLUSIEURS ventes confirmées de la MÊME œuvre/du MÊME modèle, prix convergents, état comparable.
 • RÈGLE ABSOLUE : SANS correspondances visuelles utilisables (aucune correspondance Lens ou toutes "différent"), reliability NE PEUT PAS être "Élevée". Maximum "Moyenne" ou "Faible". Pas de preuve visuelle = pas de fiabilité élevée.
- SI l'état de l'objet diffère significativement de la référence → baisse d'un niveau.

CORRESPONDANCES VISUELLES — RÈGLE LA PLUS CRITIQUE :
- Les correspondances visuelles (Google Lens) identifient souvent CORRECTEMENT l'objet. NE SOUS-ESTIME JAMAIS ses résultats.
- IDÉAL : trouver la similitude exacte (identique / même_modèle). MAIS si aucune correspondance exacte n'existe : INCLUS les références les plus proches possibles (similaire, même famille) plutôt que de ne montrer aucun résultat. Une référence "similaire" ou "proche" vaut mieux qu'une absence totale de repère pour l'estimation.
- ÉTAPE OBLIGATOIRE : 1) Liste TOUTES les identifications trouvées. 2) Pour CHAQUE identification, vérifie si les résultats web la corroborent. 3) Compare avec ta propre analyse visuelle. 4) Présente TOUTES les pistes dans summary avec leur niveau de vraisemblance.
- Si les correspondances montrent "Louis XIV" ET "Charles VII" → tu DOIS mentionner les 2 pistes et expliquer laquelle tu retiens.
- Si tu ignores une piste issue des correspondances SANS l'expliquer, c'est une FAUTE GRAVE.
- Dans matches : pour CHAQUE correspondance, indique le verdict (identique / même_modèle / similaire / différent) avec les détails de comparaison (socle, pose, patine, proportions). Si un détail majeur diffère, le verdict NE PEUT PAS être "identique".
- EXAMEN PRÉCIS OBLIGATOIRE : Tu DOIS t'engager sur chaque verdict. Compare pixel par pixel / détail par détail : forme, proportions, inscriptions, couleurs, usures. Ne pas rester vague. Dans "details" de visual_comparisons : UNE SEULE phrase courte par correspondance (ex: "Socle identique, patine similaire → identique" ou "Proportions différentes → différent"). Pas de paragraphe, pas de répétition.
- VINS / SPIRITUEUX : Si une correspondance montre la MÊME bouteille (étiquette, millésime identiques), le verdict DOIT être "identique" et non "similaire". Ne pas être trop prudent : même château + même millésime = identique.
- Ne JAMAIS citer une référence comme "la même œuvre" si le verdict est "similaire" ou "différent". Au mieux : "modèle similaire".
- Si AUCUNE correspondance n'est "identique", l'estimation est plus incertaine et la fourchette doit être plus large.

DIMENSIONS DU VENDEUR — RÈGLE ANTI-ABSURDITÉ :
- Si le vendeur fournit des DIMENSIONS : pour CHAQUE référence, compare. Écart >20% sur une dimension = référence non comparable (modèle similaire taille différente).
- Si dimensions manquantes, DEMANDE-LES dans questions.
- Ne JAMAIS ignorer un écart de dimensions.

LECTURE CRITIQUE DES SOURCES :
- Pour chaque résultat : MÊME œuvre ? AUTRE œuvre du même artiste ? Objet SIMILAIRE ?
- Priorité aux ventes de la MÊME œuvre ou très comparables. MAIS si aucune vente exacte n'existe : INCLUS les références les plus proches possibles (œuvre similaire, même artiste, même catégorie) dans market_results plutôt que de laisser vide. Une référence "similaire" ou "différent mais proche" donne un repère de prix utile.
- CITE dans market quelles ventes sont comparables et lesquelles ne le sont pas.
- En cas de doute, PRENDS LA FOURCHETTE BASSE.

LECTURE DES PRIX — RÈGLE ANTI-ERREUR ABSOLUE :
- Relis CHAQUE prix CARACTÈRE PAR CARACTÈRE. Erreurs fréquentes : 120 vs 1 200 vs 12 000. Ignorer la devise (HKD ≠ EUR). Confondre estimation et adjudication.
- MÉTHODE : 1) Relis le passage EXACT. 2) Note le montant EXACT. 3) Identifie estimation vs adjudication. 4) Convertis en euros. 5) CITE le prix exact : "Adjugé 320 € chez Rouillac" et non "environ 100-200 €".

COMPARAISON DE L'ÉTAT — RÈGLE CRITIQUE :
- Pour CHAQUE référence : noter l'état de la référence, comparer avec l'objet soumis, ajuster l'estimation.
- Référence BON état + objet ABÎMÉ → estimation SIGNIFICATIVEMENT inférieure (-30% à -70%).
- MENTIONNE dans condition l'état de chaque référence ET celui de l'objet soumis.
- NE JAMAIS utiliser un prix de référence sans préciser l'état de l'objet de référence.

HIÉRARCHIE DE FIABILITÉ DES PRIX :
1. VENTE CONFIRMÉE (« adjugé », « vendu ») → PRIORITÉ ABSOLUE.
2. ESTIMATION MAISON DE VENTE → Fiable mais indicatif.
3. PRIX GALERIE → Moins fiable.
4. MARKETPLACE (eBay, Etsy) → Peu fiable.
5. ARTPRICE / INDEX → Contexte général.

CONVERSION DES DEVISES : 1 HKD ≈ 0.12 €, 1 USD ≈ 0.92 €, 1 GBP ≈ 1.16 €, 1 CHF ≈ 1.04 €, 1 CNY ≈ 0.13 €, 1 JPY ≈ 0.006 €. Toujours donner la fourchette en EUROS.

TRAITEMENT DES NOMS MENTIONNÉS PAR LE PROPRIÉTAIRE : Tu DOIS en tenir compte même si les correspondances ne confirment pas. Ne JAMAIS ignorer silencieusement.

VENTE PUBLIQUE CONFIRMÉE : Si tu trouves une vente confirmée de la MÊME œuvre → MENTIONNE-LE IMMÉDIATEMENT dans summary.

ASSEMBLAGE DE LOTS : Si l'objet est un assemblage de plusieurs lots/produits (ex: lot de vases, ensemble de tableaux, collection d'objets), fournis PLUSIEURS références de prix par produit/lot identifié (minimum 2-3 références par élément) pour renforcer la crédibilité de l'estimation. Une seule référence par produit n'est pas convaincante.

DÉTECTION DE CONTRADICTIONS : Commence par "Sauf erreur, " si tu trouves une contradiction avec le descriptif vendeur.

SITES À PAYWALL : Tu peux recevoir des snippets Google indexés depuis Invaluable, Drouot, Artnet. UTILISE-LES pour ton raisonnement. Ne JAMAIS inclure de lien cliquable vers ces domaines. Cite la source SANS lien : "Adjugé 2 500 € chez Christie's (Invaluable, mars 2024)".

LIENS MORTS INTERDITS — RÈGLE ABSOLUE :
- Ne JAMAIS proposer une URL dont tu ne peux pas garantir qu'elle mène au contenu cité. Pages supprimées, ventes archivées, paywalls, redirections vides = LIEN INTERDIT.
- Si tu n'as pas de contenu scrapé (bloc "--- Source: URL ---") pour une URL, mets url VIDE. Un lien non vérifié est pire qu'aucun lien.
- Domaines souvent morts ou paywall : Invaluable, Artnet, Drouot, auction.fr, etc. → url VIDE, cite dans "notes" ou "source" sans lien.
- Préfère une entrée sans URL avec "notes" (ex: "Snippet Google, page non accessible") plutôt qu'un lien trompeur.

LIENS ET ACCESSIBILITÉ :
- Un lien doit mener EXACTEMENT au contenu cité. Quand on clique, on doit voir ce que tu proposes.
- Si le lien ne mène pas au contenu : mets l'URL vide, inclus le contenu dans "notes" (extrait scrapé) si disponible.

FORMATAGE : LIENS en markdown [texte](url). MONTANTS : séparateur de milliers avec espace "39 000 €". DATES : format français "mars 2024".

IDENTITÉ / BIOGRAPHIE (identity) — RÈGLE STRUCTURANTE :
- INTERDIT : se paraphraser ou répéter sous une autre forme ce qui est déjà dit dans summary, condition ou market_results. Chaque fait une seule fois dans tout le JSON.
- D'ABORD : Ce que c'est au plus près de la vérité, avec justifications (photos, correspondances, éléments observés).
- DEUXIÈME PARTIE du champ identity (après l’identification courte) : selon le cas, dans cet ordre de priorité — (1) Si pertinent : biographie de l’auteur, artiste, artisan, fabricant ou créateur identifié. (2) Sinon : le mouvement artistique, stylistique ou industriel auquel l’objet se rattache ; dans ce cas le cœur du paragraphe porte sur ce mouvement. (3) Sinon : contexte historique, géographique, politique ou économique utile à comprendre l’objet. Ne pas mélanger ces niveaux de façon redondante ; choisir la piste la plus éclairante.
- Toujours inclure quand c’est possible des éléments sur l’auteur ou le producteur : mouvement, carrière, faits notables, contexte de création. Pour les objets de série : fabricant, designer ou licence.
CAS 1 — Œuvre d'art : IDENTITÉ (nature précise, attribution au conditionnel, technique, époque) + justifications visuelles + AUTHENTICITÉ (signatures, poinçons) + BIOGRAPHIE DÉVELOPPÉE (auteur : qui est-il, mouvement, carrière, œuvres notables, contexte). 4-8 phrases au conditionnel.
CAS 2 — Objet usuel : Marque, modèle, spécifications techniques. 2-4 phrases factuelles.
CAS 3 — Objet du commerce (électroménager, high-tech, petit électroménager, etc.) : Marque, modèle, référence. Cherche dans les sources web DEPUIS QUAND ce modèle est fabriqué (date de lancement commercial) pour connaître l'âge maximum possible. Indique dans identity : "Modèle lancé en [année]" ou "Commercialisé à partir de [année]" si trouvé. Dans questions, OBLIGATOIREMENT inclure si pertinent : "Avez-vous la facture d'achat ?" ; "L'objet est-il encore sous garantie ou sous prolongation de garantie ?". Ces éléments influencent fortement la valeur de revente.
CAS 4 — Véhicule d'occasion (auto, moto, utilitaire) : Marque, modèle, version, année de mise en circulation. Dans questions, OBLIGATOIREMENT inclure si pertinent : "Quel est le kilométrage actuel ?" ; "Avez-vous le carnet d'entretien à jour ?" ; "Combien de propriétaires a eu le véhicule ?" ; "Le véhicule a-t-il subi des accidents ou sinistres ?" ; "Avez-vous la facture d'achat et l'historique des révisions ?" ; "Le véhicule est-il encore sous garantie constructeur ou prolongation ?" ; "Avez-vous un contrôle technique favorable de moins de 6 mois ?". Ces éléments sont déterminants pour l'estimation.
CAS 5 — Figurines, statuettes, objets pop culture (Marvel, DC, anime, etc.) : IDENTITÉ (personnage, licence, fabricant : Sideshow, Hot Toys, Prime 1, Kotobukiya, Bandai, etc.) + BIOGRAPHIE DÉVELOPPÉE du créateur/fabricant (sculpteur, designer, studio, historique de la marque, éditions limitées). Qui a conçu cette œuvre ? Quelle est l'histoire du fabricant ? 4-6 phrases.

VÉRIFICATION FINALE OBLIGATOIRE :
A) Identifie la correspondance avec le meilleur verdict. B) Vérifie que summary est cohérent. C) RELIS CHAQUE PRIX. D) Vérifie que l'ÉTAT est mentionné pour chaque référence. E) Vérifie que reliability est cohérent.
LOVABLE;

        $user_explanation_block = '';
        if (!empty($user_explanation)) {
            $user_explanation_block = "\n\n**RETOUR UTILISATEUR (erreur signalée précédemment — tiens-en compte pour cette nouvelle analyse) :**\n" . $user_explanation . "\n";
        }

        return <<<PROMPT
$lovable_full

$categories_ref

## DONNÉES FOURNIES

**Description du client:**
$description

$lens_summary

$scraped_content
$user_explanation_block

## FORMAT DE SORTIE

Réponds UNIQUEMENT en JSON valide, sans markdown. Examine les photos pour signatures/marques/poinçons.

OBLIGATOIRE — visual_comparisons : Pour CHAQUE correspondance Lens listée ci-dessus (index 0, 1, 2...), fournis un verdict : "identique" | "même_modèle" | "similaire" | "différent". On peut avoir plusieurs "différent" si même famille d'objets.

OBLIGATOIRE — market_results : Tableau d'objets avec title, url (vide si lien non accessible), price, source, relevance, notes (optionnel : extrait scrapé ou explication si url inutile). N'inclus une url QUE si elle mène au contenu cité.

OBLIGATOIRE — questions : Tableau de 2-5 questions au propriétaire. FORMULATION : Ne jamais demander frontalement "Quelles ont été les conditions...". Utiliser "Connaissez-vous les conditions de..." ou "Avez-vous des informations sur...".

{
  "summary": "Synthèse 2-4 phrases au conditionnel",
  "recommendation": "Recommandation en une phrase",
  "interet": "un des slugs: $slugs_interet",
  "estimation": "un des slugs: $slugs_estimation",
  "theme_vente": "un des slugs: $slugs_theme OU une nouvelle catégorie si aucune ne convient (ex: horlogerie_collection)",
  "theme_vente_suggested_parent": "si theme_vente est nouveau, slug de la catégorie existante la plus proche (ex: bijoux_joaillerie) ou vide",
  "sub_categories": ["slug1", "slug2"],
  "estimate_low": nombre ou null,
  "estimate_high": nombre ou null,
  "interest_level": "Nom lisible (Très intéressant, À examiner, etc.)",
  "reliability": "Faible|Moyenne|Élevée",
  "identity": "Identité/biographie (règle structurante ci-dessus)",
  "visual_comparisons": [{"match_index": 0, "verdict": "identique|même_modèle|similaire|différent", "details": "..."}],
  "market_results": [{"title": "...", "url": "https://... ou vide", "price": "...", "source": "...", "relevance": "même_œuvre|similaire|différent", "notes": "extrait si url non accessible"}],
  "questions": ["Question 1", "Question 2", "..."],
  "condition": "État objet + comparaison avec références",
  "signatures_marques_poincons": [{"photo_index": 0, "types": ["signature"], "description": "..."}]
}
PROMPT;
    }

    private function resolve_images_for_gemini($photos) {
        $out = [];
        $upload = wp_upload_dir();
        $basedir = $upload['basedir'];

        foreach (array_slice($photos, 0, 5) as $p) {
            $path = is_array($p) ? ($p['path'] ?? $p['file'] ?? reset($p)) : $p;
            if (is_string($path)) {
                if (strpos($path, 'http') === 0) {
                    $out[] = $path;
                } elseif (file_exists($path)) {
                    $out[] = $path;
                } elseif (strpos($path, $basedir) !== 0) {
                    $full = $basedir . '/' . ltrim(str_replace('\\', '/', $path), '/');
                    if (file_exists($full)) {
                        $out[] = $full;
                    }
                }
            }
        }
        return $out;
    }

    private function extract_json_from_response($text) {
        $text = trim($text);
        $text = preg_replace('/^```json\s*/', '', $text);
        $text = preg_replace('/\s*```\s*$/', '', $text);
        return json_decode($text, true);
    }

    private function normalize_ai_output($data) {
        $default = $this->empty_ai_output();
        $visual_comparisons = $this->normalize_visual_comparisons($data['visual_comparisons'] ?? []);
        $market_results = $this->normalize_market_results($data['market_results'] ?? $data['market'] ?? []);
        $questions_arr = $this->normalize_questions($data['questions'] ?? $data['questions_for_owner'] ?? []);

        $out = array_merge($default, array_filter([
            'summary' => $data['summary'] ?? '',
            'recommendation' => $data['recommendation'] ?? '',
            'interet' => $this->sanitize_slug($data['interet'] ?? ''),
            'estimation' => $this->sanitize_slug($data['estimation'] ?? ''),
            'theme_vente' => $this->sanitize_slug($data['theme_vente'] ?? ''),
            'theme_vente_suggested_parent' => $this->sanitize_slug($data['theme_vente_suggested_parent'] ?? ''),
            'sub_categories' => is_array($data['sub_categories'] ?? null) ? array_map([$this, 'sanitize_slug'], $data['sub_categories']) : [],
            'estimate_low' => $this->parse_number($data['estimate_low'] ?? null),
            'estimate_high' => $this->parse_number($data['estimate_high'] ?? null),
            'interest_level' => $data['interest_level'] ?? '',
            'reliability' => $data['reliability'] ?? '',
            'identity' => $this->ensure_string($data['identity'] ?? $data['identity_biography'] ?? ''),
            'visual_comparisons' => $visual_comparisons,
            'market_results' => $market_results,
            'questions' => $questions_arr,
            'condition' => $this->ensure_string($data['condition'] ?? $data['condition_notes'] ?? ''),
            'market' => $this->ensure_string($data['market'] ?? $data['market_insights'] ?? ''),
            'signatures_marques_poincons' => $this->normalize_smp($data['signatures_marques_poincons'] ?? []),
        ]));
        return $out;
    }

    private function normalize_visual_comparisons($v) {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $item) {
            if (!is_array($item)) {
                continue;
            }
            $idx = isset($item['match_index']) ? (int) $item['match_index'] : count($out);
            $verdict = trim((string) ($item['verdict'] ?? ''));
            $verdict_lower = strtolower(preg_replace('/[^a-z0-9àâäéèêëïîôùûü_]/', '', $verdict));
            if (in_array($verdict_lower, ['identique'], true)) {
                $verdict = 'identique';
            } elseif (in_array($verdict_lower, ['mememodele', 'meme_modele'], true)) {
                $verdict = 'même_modèle';
            } elseif (in_array($verdict_lower, ['similaire'], true)) {
                $verdict = 'similaire';
            } elseif (in_array($verdict_lower, ['different'], true)) {
                $verdict = 'différent';
            } else {
                $verdict = 'similaire';
            }
            $out[] = [
                'match_index' => $idx,
                'verdict' => $verdict,
                'details' => trim((string) ($item['details'] ?? '')),
            ];
        }
        return $out;
    }

    private function normalize_market_results($v) {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $item) {
            if (is_array($item) && (!empty($item['title']) || !empty($item['url']) || !empty($item['price']) || !empty($item['notes']))) {
                $out[] = [
                    'title' => trim((string) ($item['title'] ?? '')),
                    'url' => trim((string) ($item['url'] ?? $item['link'] ?? '')),
                    'price' => trim((string) ($item['price'] ?? '')),
                    'source' => trim((string) ($item['source'] ?? '')),
                    'relevance' => trim((string) ($item['relevance'] ?? '')),
                    'notes' => trim((string) ($item['notes'] ?? '')),
                ];
            }
        }
        return $out;
    }

    private function normalize_questions($v) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $item) {
                $s = is_scalar($item) ? $item : ($item['text'] ?? $item['question'] ?? '');
                if (trim((string) $s) !== '') {
                    $out[] = trim((string) $s);
                }
            }
            return $out;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return [];
        }
        $lines = preg_split('/\r?\n/', $s);
        return array_values(array_filter(array_map('trim', $lines)));
    }

    private function ensure_string($v) {
        if (is_array($v)) {
            $parts = [];
            foreach ($v as $item) {
                if (is_array($item)) {
                    $parts[] = implode(' — ', array_filter(array_map(function ($x) {
                        return is_scalar($x) ? (string) $x : '';
                    }, $item)));
                } else {
                    $parts[] = (string) $item;
                }
            }
            return implode("\n", array_filter($parts));
        }
        return (string) $v;
    }

    private function sanitize_slug($s) {
        return preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $s)));
    }

    private function normalize_smp($data) {
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $idx = isset($item['photo_index']) ? (int) $item['photo_index'] : 0;
            $types = is_array($item['types'] ?? null) ? $item['types'] : [];
            $desc = trim((string) ($item['description'] ?? ''));
            if (!empty($types) || $desc !== '') {
                $out[] = [
                    'photo_index' => $idx,
                    'types' => array_values(array_intersect($types, ['signature', 'marque', 'poincon'])),
                    'description' => $desc,
                ];
            }
        }
        return $out;
    }

    private function parse_number($v) {
        if ($v === null || $v === '') {
            return null;
        }
        $clean = preg_replace('/[^\d,.\s]/', '', (string) $v);
        $clean = str_replace(',', '.', $clean);
        return is_numeric($clean) ? floatval($clean) : null;
    }

    private function empty_ai_output() {
        return [
            'summary' => '',
            'recommendation' => '',
            'interet' => '',
            'estimation' => '',
            'theme_vente' => '',
            'theme_vente_suggested_parent' => '',
            'sub_categories' => [],
            'estimate_low' => null,
            'estimate_high' => null,
            'interest_level' => '',
            'reliability' => '',
            'identity' => '',
            'visual_comparisons' => [],
            'market_results' => [],
            'questions' => [],
            'condition' => '',
            'signatures_marques_poincons' => [],
        ];
    }

    /**
     * Enrichit les champs vides avec les données Lens et scrapées.
     * Fusionne visual_matches (Lens) + visual_comparisons (IA) → correspondances.
     */
    private function enrich_ai_output($out, $lens_data, $scraped_content) {
        $vm = array_slice($lens_data['visual_matches'] ?? [], 0, 8);
        $out['visual_matches'] = $vm;

        $verdicts_by_idx = [];
        foreach ($out['visual_comparisons'] ?? [] as $vc) {
            $verdicts_by_idx[(int) ($vc['match_index'] ?? 0)] = [
                'verdict' => $vc['verdict'] ?? 'similaire',
                'details' => $vc['details'] ?? '',
            ];
        }

        $scraped_by_url = [];
        if (!empty($scraped_content) && preg_match_all('/--- Source: ([^\s]+) ---\s*([\s\S]*?)(?=--- Source:|$)/', $scraped_content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $mat) {
                $u = trim($mat[1]);
                $txt = preg_replace('/\s+/', ' ', trim(substr($mat[2], 0, 1500)));
                if (strlen($txt) >= 50) {
                    $scraped_by_url[$u] = $txt . (strlen($mat[2]) > 1500 ? '...' : '');
                }
            }
        }
        $domains_url_interdits = ['invaluable.com', 'artnet.com', 'drouot.com', 'auction.fr', 'liveauctioneers.com', 'bidspirit.com', 'interencheres.com'];
        $url_verified = function ($url) use ($scraped_by_url) {
            $u = trim($url);
            if ($u === '') return false;
            if (isset($scraped_by_url[$u])) return true;
            $u_norm = rtrim($u, '/');
            return isset($scraped_by_url[$u_norm]) || isset($scraped_by_url[$u_norm . '/']);
        };
        $should_empty_url = function ($url) use ($scraped_by_url, $domains_url_interdits, $url_verified) {
            $u = trim($url);
            if ($u === '') return false;
            $url_lower = strtolower($u);
            foreach ($domains_url_interdits as $d) {
                if (strpos($url_lower, $d) !== false) return true;
            }
            return !$url_verified($u);
        };

        $correspondances = [];
        foreach ($vm as $i => $m) {
            $v = $verdicts_by_idx[$i] ?? ['verdict' => 'similaire', 'details' => ''];
            $link = $m['link'] ?? '';
            $notes = '';
            if ($link && isset($scraped_by_url[$link])) {
                $notes = $scraped_by_url[$link];
            }
            if ($should_empty_url($link)) {
                $link = '';
                if (empty($notes)) $notes = 'Lien non vérifié (page potentiellement inaccessible).';
            }
            $correspondances[] = [
                'title' => $m['title'] ?? '',
                'url' => $link,
                'thumbnail' => $m['thumbnail'] ?? $m['image'] ?? '',
                'source' => $m['source'] ?? '',
                'verdict' => $v['verdict'],
                'details' => $v['details'],
                'notes' => $notes,
            ];
        }
        $out['correspondances'] = $correspondances;

        foreach ($out['market_results'] ?? [] as $i => $mr) {
            $u = trim($mr['url'] ?? $mr['link'] ?? '');
            if ($u && $should_empty_url($u)) {
                $out['market_results'][$i]['url'] = '';
                if (empty(trim($mr['notes'] ?? ''))) {
                    $out['market_results'][$i]['notes'] = 'Lien non vérifié (page potentiellement inaccessible).';
                }
            }
        }

        $has_usable_correspondance = false;
        foreach ($correspondances as $c) {
            $v = strtolower($c['verdict'] ?? '');
            if ($v && $v !== 'différent' && $v !== 'different') {
                $has_usable_correspondance = true;
                break;
            }
        }
        if (!$has_usable_correspondance && preg_match('/élevée|elevee|elevée/i', $out['reliability'] ?? '')) {
            $out['reliability'] = 'Moyenne';
        }

        if (empty($out['market_results']) && !empty(trim($scraped_content))) {
            $excerpt = preg_replace('/\s+/', ' ', trim(substr($scraped_content, 0, 2000)));
            if (strlen($excerpt) >= 100) {
                $out['market_results'] = [
                    ['title' => 'Contenu web récupéré', 'url' => '', 'price' => '', 'source' => '', 'relevance' => '', 'notes' => $excerpt . (strlen($scraped_content) > 2000 ? '...' : '')],
                ];
            }
        }
        return $out;
    }
}
