# LMD Apps IA — Documentation

Plugin WordPress pour la gestion des demandes d'estimation d'objets d'art, assistée par IA.

---

## 1. Prérequis pour bonne installation

- **WordPress** 5.0 ou supérieur
- **PHP** 7.4 ou supérieur
- **Extensions PHP** : `json`, `curl` (ou équivalent pour `wp_remote_*`)
- **Droits** : l'administrateur doit pouvoir activer des plugins et accéder à l'administration

Pour l'aide à l'estimation par IA :
- **Clés API** (à configurer après installation) :
  - **Gemini** (Google AI) — obligatoire pour l'analyse
  - **SerpAPI** (Google Lens) — recommandé pour la recherche par image
  - **Firecrawl** — recommandé pour enrichir l'analyse via le web
  - **ImgBB** — optionnel, uniquement en local (localhost). En production, les URLs d'images sont utilisées directement.

---

## 2. Installation

1. **Télécharger** le dossier du plugin `lmd-apps-ia`
2. **Zipper** le dossier (le ZIP doit contenir directement le dossier `lmd-apps-ia/` avec ses fichiers)
3. **Sur le site WordPress** : Extensions → Ajouter → Téléverser le plugin → Choisir le fichier ZIP → Installer
4. **Activer** le plugin « LMD Apps IA »

À l'activation, le plugin crée automatiquement :
- Les tables de base de données
- Les tags par défaut (intérêt, estimation, thème de vente, etc.)
- Les paliers de facturation par défaut

---

## 3. Démarrage

1. **Configurer les APIs** : LMD Apps IA → Config APIs  
   Renseigner les clés Gemini, SerpAPI, Firecrawl et ImgBB selon vos besoins.

2. **Ajouter le formulaire sur le site** : créer une page et insérer le shortcode :
   ```
   [lmd_formulaire_estimation]
   ```
   ou
   ```
   [lmd_demande_estimation]
   ```

3. **Créer une estimation manuellement** (avec photos) : LMD Apps IA → Nouvelle estimation

4. **Consulter les demandes** : LMD Apps IA → Mes estimations

---

## 4. Toutes les fonctionnalités

### 4-1. Les formulaires d'estimation

**Formulaire public (shortcode)**  
- `[lmd_formulaire_estimation]` — formulaire complet avec « Comment ça marche »
- `[lmd_demande_estimation]` — alias
- `[lmd_demande_estimation style="contact"]` — formulaire compact pour page contact

Champs : photos, civilité, prénom, nom, code postal, commune (auto-complétée via API geo.api.gouv.fr), email, téléphone, description.

**Formulaire admin**  
LMD Apps IA → Nouvelle estimation : formulaire complet avec upload de photos multiples. Les photos sont enregistrées et utilisées pour l'analyse IA. Pour les demandes nécessitant une analyse par image, privilégier ce formulaire.

---

### 4-2. Arrivée sur la grille — fonctionnalités de la grille

#### 4-2-1. La grille

- **Vue en cartes** : chaque demande apparaît sous forme de carte avec photo, description courte, tags et métadonnées
- **Nombre de vignettes** : choix entre 3, 4 ou 5 cartes par ligne (boutons en haut à droite)
- **Clic sur une carte** : ouvre la page de détail de l'estimation
- **Boutons sur les cartes** :
  - **IA** (icône) : lancer l'analyse IA sur ce lot (grisé si déjà analysé)
  - **Poubelle** : sélection pour suppression en masse
- **Barre d'actions en masse** (en bas) :
  - Sélection multiple → « Lancer analyses IA » pour analyser plusieurs lots
  - Sélection multiple → « Supprimer » pour supprimer en masse

#### 4-2-2. Les tags et les filtres

**Tags affichés sur les cartes**  
- **Échanges** : Non lu, Lu mais non répondu, En retard, Répondu
- **Intérêt** : Pas pour nous, Peu intéressant, À examiner, Intéressant, Très intéressant, Exceptionnel
- **Estimation** : paliers (< 25 €, < 100 €, etc.)
- **Catégorie** : thème de vente (Tableaux & Dessins anciens, Art moderne, etc.)
- **Vente** : date de vente (créée par le CP)
- **Vendeur** : identifié par email

**Barre de filtres**  
- Bloc 1 : Tous, retards (< 7j, > 7j), Échanges
- Bloc 2 : Estimation, Intérêt, Catégorie
- Bloc 3 : Vente, Vendeur
- Recherche textuelle (nom, email, description)

Les tags sélectionnés s'affichent sous la barre ; clic sur × pour retirer un filtre.

---

### 4-3. La page d'estimation

Page de détail d'une demande (LMD Apps IA → clic sur une carte).

#### 4-3-1. 1er avis en bleu et réponse directe au vendeur

- **Onglet « 1er Avis »** (bleu) : titre, descriptif, dimension, fourchette d'estimation (min/max), prix de réserve
- **Réponse au vendeur** : onglet « Réponse » dans la colonne de droite
  - **Questions pour le courrier** : sélection des questions IA à inclure
  - **Formules enregistrées** : modèles de texte réutilisables (⚙ pour gérer)
  - **Objet** et **Corps** du mail
  - **Paramétrage** (⚙) : email du CP, signature (HTML autorisé), adresses en copie
  - Bouton **Envoi** pour envoyer l'email au vendeur

#### 4-3-2. Demande de délégation et lien qui invite à l'expertise

- **Onglet « Déléguer »** (violet) : déléguer l'estimation à un expert
  - Champ **Destinataire** (email, avec suggestions des destinataires enregistrés)
  - **Objet** et **Message**
  - **Générer lien d'accès** : crée un lien unique (`?lmd_delegation_token=xxx`) que l'expert peut ouvrir sans se connecter
  - Le lien affiche photos, description et permet à l'expert de consulter le lot
  - **Envoi** : envoie l'email avec le lien au destinataire

#### 4-3-3. Avis de l'expert prioritaire

- **Onglet « 2ème Avis »** (violet) : avis de l'expert délégué
- Même structure que le 1er avis : titre, descriptif, dimension, fourchette, prix de réserve
- Les tags peuvent indiquer la source (IA, CP, avis 2) avec des couleurs distinctes

---

### 4-5. Aide à l'estimation

#### 4-5-1. Principes et étapes de l'analyse

L'analyse IA combine :
1. **SerpAPI (Google Lens)** : recherche par image pour trouver des objets visuellement similaires
2. **Firecrawl** : scraping des pages des résultats Lens (jusqu'à 3 URLs) pour extraire prix et contexte
3. **ImgBB** : si l'image est locale, upload temporaire pour fournir une URL publique à SerpAPI
4. **Gemini** : analyse des photos, du descriptif, des résultats Lens et du contenu scrapé pour produire une estimation structurée

**Lancement** : bouton IA sur une carte (grille) ou dans le bloc « Aide à l'estimation » (page détail).

#### 4-5-2. Résultats et mise à jour automatique

Une fois l'analyse terminée, les résultats s'affichent automatiquement dans le bloc « Aide à l'estimation » :
- Synthèse, recommandation, identité/biographie
- Intérêt, estimation, thème de vente
- Correspondances visuelles (verdicts : identique, même_modèle, similaire, différent)
- Résultats marché (références de prix)
- État et condition
- Signatures, marques, poinçons

##### 4-5-2-1. Questions pour le courrier du CP

L'IA génère 2 à 5 questions à poser au propriétaire. Elles apparaissent dans l'onglet « Questions » et peuvent être sélectionnées pour être incluses dans le courrier de réponse au vendeur.

##### 4-5-2-2. Remplissage des tags si possible

L'IA propose des slugs pour **intérêt**, **estimation** et **thème de vente**. Ces propositions peuvent être appliquées aux tags de l'estimation. Les tags IA sont affichés en vert ; les tags modifiés par le CP en bleu, par l'avis 2 en mauve.

##### 4-5-2-3. L'erreur de l'IA

Si l'IA se trompe : bouton **« L'IA se trompe ? »**  
- Au clic : masquage des résultats IA, rechargement, et enregistrement du signalement
- Un message « Merci, l'IA continue d'apprendre » s'affiche
- Les administrateurs reçoivent un email de notification

##### 4-5-2-4. Lancer des aides à l'estimation en nombre

Sur la grille (Mes estimations) :
1. Cliquer sur l'icône IA des cartes à analyser (elles se sélectionnent)
2. Une barre verte apparaît en bas : « X estimation(s) sélectionnée(s) »
3. Cliquer sur **« Lancer analyses IA »**
4. Les analyses se lancent en arrière-plan (une par une)

---

## 5. La facturation et le suivi de la consommation par le client

### Facturation (paliers tarifaires)

**LMD Apps IA → Facturation**

Tableau des paliers : montant min/max de l'estimation → prix par estimation (€).

Exemple par défaut :
| Min (€) | Max (€) | Prix / estimation (€) |
|---------|---------|------------------------|
| 0       | 100     | 5                      |
| 100     | 500     | 10                     |
| 500     | 1 000   | 15                     |
| 1 000   | 5 000   | 25                     |
| 5 000   | ∞       | 50                     |

Les paliers sont configurables. Des dérogations par email client sont possibles (table `lmd_client_pricing_overrides`).

### Suivi de la consommation IA (Ressources IA)

**LMD Apps IA → Ressources IA**

L'aide à l'estimation n'est pas une simple recherche IA : c'est l'**assemblage de plusieurs IA et technologies** (recherche par image, scraping web, analyse Gemini), chacune avec un coût à l'action.

**Tarification simplifiée :**
- **20 estimations offertes** à l'installation pour se familiariser
- **0,50 € HT** par aide à l'estimation au-delà

**Affichage :**
- Gratuites utilisées / payantes (total et ce mois)
- Période du mois (début et fin)
- Montant HT du mois
- Rappel : tous les montants sont HT

**Pour le site parent :** le résumé est mis à jour après chaque analyse. Récupération via `get_option('lmd_consumption_summary')` ou `lmd_get_consumption_summary($site_id)`.
