# Proposition de refonte — Aide à l'estim

Document de spécification validé.  
Solution réalisée par **Le Marteau Digital**.

---

## 1. Logos et branding

- **Logo client** : à la création du site, le parent s'en occupe à chaque fois. Le site parent importe le logo du nouveau client.
- **Logo Le Marteau Digital** : inclus par défaut dans le plugin.
- Mention : "Solution Aide à l'estim par Le Marteau Digital"

---

## 2. Statistiques

- **Les deux** : stats par site client ET agrégat pour le parent.
- **Historique** : 24 mois.

---

## 3. Promotions

- **Ciblage** : possiblement les deux, mais surtout **client par client**.
- **Ristourne (€)** : déduction sur la prochaine facture.
- **Estimations gratuites** : nombre défini par le parent (ex. "Les 5 prochaines seront gratuites").
- **Affichage** : uniquement si le parent communique (ristourne ou cadeau).

---

## 4. Stratégie de migration

- **Ajouter d'abord**, puis retirer ensuite.
- Pas de suppression immédiate de l'existant.

---

## 5. Menu client (site enfant)

| Élément | Action |
|---------|--------|
| **Tableau de bord** | Nouveau design, menu hypertexte avec ancres HTML |
| **Statistiques de catégories** | Avec "nouveau gros vendeur" |
| **Leur consommation** | Avec coût |
| **Ristourne / Cadeau** | S'affiche uniquement si le parent communique |
| **Nouvelle demande** | Remplace "Nouvelle estimation" — même contenu |
| **Planning ventes** | Remplace "Ventes" — même contenu |
| **Activité** | → Migre vers parent, **disparaît** du client |
| **Vendeurs** | Liste : coordonnées, proposition, intérêt, dernière date |
| **Facturation** | **Disparaît** du client |
| **Détail** | **Disparaît** du menu (accès via liste ?) |
| **Aide** | Dossier d'aide |
| **Ressources IA** | Reste pour l'instant |
| **Consommation IA** | → Parent, **disparaît** du client |
| **Config APIs** | → Parent, **disparaît** du client |
| **Copie client** | Reste pour l'instant |
| **Remontée** | **Disparaît** |

---

## 6. Site parent — Compléments

- **Activité** : temps passé chaque mois sur le service par site enfant.
- **Activité** : nombre d'IP (par site ?).
- Gestion des promotions (ristourne, X estimations gratuites) par client.
- Config APIs centralisée.
- Consommation IA agrégée.

---

## 7. Dossier d'aide — Proposition de contenu

### Structure générale

1. **Bienvenue**
2. **Introduction** — Un service pour vous faire gagner du temps
3. **Aide à l'estimation** — Capacités et organisation
4. **Le service LMD** — Organisation des IA
5. **Fonctionnalités** — Point par point
6. **Référence** — Shortcodes, intégration

---

### 7.1 Bienvenue

**Texte proposé :**

> Bienvenue dans Aide à l'estim.
>
> Ce module vous accompagne pour traiter vos demandes d'estimation d'objets d'art et de collection. Il combine l'intelligence artificielle et l'organisation de vos dossiers pour vous faire gagner du temps.
>
> Utilisez le menu à gauche pour naviguer dans ce guide.

---

### 7.2 Introduction — Un service pour vous faire gagner du temps

**Texte proposé :**

> Aide à l'estim est conçu pour réduire le temps passé sur chaque demande d'estimation.
>
> **Deux leviers principaux :**
>
> 1. **L'organisation** — Centralisez vos demandes, ventes, vendeurs et planning. Filtrez par intérêt, estimation, thème. Suivez l'avancement de chaque dossier.
>
> 2. **L'assistance IA** — Pour chaque demande, l'IA analyse photos et description, propose une estimation, un niveau d'intérêt et des pistes de recherche. Vous gardez la décision finale, l'IA accélère le travail préparatoire.
>
> L'objectif : moins de temps par estimation, plus de clarté dans le suivi.

---

### 7.3 Aide à l'estimation — Capacités et organisation

**Texte proposé :**

> **Ce que fait Aide à l'estim :**
>
> - **Réception des demandes** — Via formulaire public sur votre site ou création manuelle.
> - **Analyse IA** — Estimation, intérêt, correspondances visuelles, résultats de marché.
> - **Tags et filtres** — Intérêt (exceptionnel, très intéressant, à examiner…), estimation (fourchettes €), thème de vente, message (répondu, non lu…).
> - **Planning des ventes** — Associez les estimations à vos ventes à venir.
> - **Vendeurs** — Coordonnées, proposition, intérêt, dernière activité.
>
> Tout est pensé pour enchaîner rapidement : recevoir → analyser → trier → planifier.

---

### 7.4 Le service LMD — Organisation des IA

**Texte proposé :**

> **Comment l'IA est organisée :**
>
> Le Marteau Digital combine plusieurs outils pour chaque analyse :
>
> - **Recherche visuelle** (Google Lens) — Trouve des objets similaires à partir de vos photos.
> - **Exploration web** — Récupère des informations sur les pages trouvées.
> - **Analyse de texte** (Gemini) — Synthétise description, photos et résultats pour proposer une estimation et un avis.
>
> Vous recevez une proposition structurée : fourchette de prix, niveau d'intérêt, fiabilité, pistes d'identification. Vous validez, ajustez ou signalez une erreur pour améliorer les prochaines analyses.

---

### 7.5 Fonctionnalités — Point par point

**Structure proposée :**

| Fonctionnalité | Contenu |
|----------------|---------|
| **Nouvelle demande** | Créer une estimation manuellement (client, description, photos). |
| **Mes estimations** | Liste avec filtres (intérêt, estimation, thème, message, vendeur). Grille ou tableau. |
| **Détail d'une estimation** | Fiche complète : IA, tags, avis CP, réponse, délégation. |
| **Lancer l'analyse IA** | Bouton "Aide à l'estimation", suivi de la progression. |
| **Interpréter les résultats** | Estimation, intérêt, fiabilité, correspondances, liens. |
| **Modifier les tags** | Intérêt, estimation, thème — barre de tags cliquable. |
| **Signaler une erreur IA** | "L'IA se trompe ?" — explication et relance possible. |
| **Planning ventes** | Associer une estimation à une vente, gérer les dates. |
| **Vendeurs** | Liste avec coordonnées, proposition, intérêt, dernière date. |
| **Répondre au client** | Rédiger et envoyer la réponse depuis la fiche. |
| **Déléguer** | Transférer une estimation à un autre destinataire. |

---

### 7.6 Référence — Shortcodes et intégration

**Texte proposé :**

> **Intégrer le formulaire sur votre site :**
>
> - `[lmd_formulaire_estimation]` — Formulaire complet de demande d'estimation
> - `[lmd_demande_estimation]` — Alias du formulaire
> - `[lmd_demande_estimation style="contact"]` — Formulaire compact pour page contact
>
> Placez le shortcode sur une page (ex. "Demande d'estimation" ou "Contact"). Les visiteurs pourront envoyer photos et description ; les demandes apparaîtront dans "Mes estimations".

---

### 7.7 FAQ (à compléter)

- Combien de photos par demande ?
- Délai de réponse de l'IA ?
- Que faire si l'IA se trompe ?
- Comment sont facturées les analyses ?

---

## 8. Test / Réinitialisation

- **Confirmé** : outil "Réinitialiser pour test" disparaît du client.
- Reste **uniquement côté parent** (outil admin).

---

## 9. Récapitulatif des migrations

| Élément | Client | Parent |
|---------|--------|--------|
| Tableau de bord | Nouveau (menu ancres, stats, conso, promo) | — |
| Nouvelle demande | Renommé, même contenu | — |
| Planning ventes | Renommé, même contenu | — |
| Vendeurs | Enrichi (coordonnées, proposition, intérêt, dernière date) | — |
| Activité | **Supprimé** | Nouveau (temps/mois, IP) |
| Facturation | **Supprimé** | — |
| Détail | **Supprimé du menu** | — |
| Consommation IA | **Supprimé** | Déjà présent |
| Config APIs | **Supprimé** | Centralisé |
| Copie client | Reste | Reste |
| Remontée | **Supprimé** | — |
| Ressources IA | Reste | — |
| Aide | Dossier d'aide (nouveau contenu) | Test/réinit uniquement |

---

---

## 10. Implémenté (1er mars 2025)

- Menu client : renommé (Nouvelle demande, Planning ventes), Détail masqué, Facturation/Conso/Config APIs/Remontée retirés du client
- Tableau de bord : logos LMD + client, menu ancres, stats par catégorie, gros vendeurs, consommation, promotion
- Dossier d'aide : nouveau contenu (Bienvenue, Introduction, Capacités, Service LMD, Fonctionnalités, Référence)
- Vendeurs : colonnes coordonnées, proposition, intérêt, dernière date
- Promotions : page parent (Promotions) pour ristourne € et X gratuites par client, avec logos
- Logo LMD par défaut dans assets/images/logo-lmd.svg

*Document mis à jour le 1er mars 2025 — Spécification validée et implémentée.*
