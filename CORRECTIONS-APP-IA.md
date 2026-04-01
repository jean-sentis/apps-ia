# Corrections à faire sur `lmd-apps-ia`

Document de travail basé sur l'audit actuel du plugin.
Objectif: lister les corrections à implémenter, sans encore faire les patchs.

## Priorité haute

- [x] Corriger la fuite de contexte multisite dans le hub parent.
  Fichiers concernés:
  - `wp-content/plugins/apps-ia/includes/class-lmd-activity-analytics.php`
  - `wp-content/plugins/apps-ia/admin/views/hub.php`
  - `wp-content/plugins/apps-ia/admin/views/api-config.php`
  Problème:
  - le hub du site principal appelle `LMD_Activity_Analytics::get_feature_usage()` pour agréger l'usage réseau;
  - cette méthode fait des `switch_to_blog()` successifs sans restaurer correctement le blog courant;
  - à la fin du rendu, WordPress reste positionné sur le dernier site enfant parcouru;
  - les `admin_url()` calculées ensuite dans le hub pointent donc vers l'admin d'un site enfant.
  Effets observables:
  - le formulaire "Configuration des APIs" du hub parent soumet vers un site enfant;
  - les clés API semblent ne pas se sauvegarder sur le parent;
  - d'autres boutons/liens générés après cette agrégation peuvent eux aussi pointer vers un mauvais site.
  À corriger:
  - restaurer systématiquement le blog initial dans `get_feature_usage()`;
  - vérifier le même anti-pattern dans les autres boucles multisite de `class-lmd-activity-analytics.php`, notamment le rapport de signatures;
  - revalider ensuite tous les liens du hub parent.
  Statut:
  - corrigé;
  - validé par test manuel: la sauvegarde des settings API depuis le hub parent reste maintenant sur le site principal.

- [x] Corriger les fuites de contexte multisite dans les fonctions qui utilisent `switch_to_blog()`.
  Fichiers concernés:
  - `wp-content/plugins/apps-ia/lmd-apps-ia.php`
  - `wp-content/plugins/apps-ia/includes/class-lmd-full-export-import.php`
  À corriger:
  - `lmd_send_monthly_consumption_report()` doit toujours exécuter `restore_current_blog()` avant chaque `return` après un `switch_to_blog(1)`.
  - `LMD_Full_Export_Import::export()` doit restaurer le blog courant de façon fiable après export, y compris en cas d'erreur.
  - La logique de restauration ne doit pas dépendre d'un test du type `$site_id !== get_current_blog_id()` après le switch, car ce test devient faux une fois le blog déjà changé.
  Statut:
  - corrigé;
  - validé partiellement par test manuel sur l'export parent: téléchargement OK, pas de redirection intempestive, pas d'écran blanc.

- [x] Corriger la logique parent/enfant de la configuration API.
  Fichiers concernés:
  - `wp-content/plugins/apps-ia/admin/views/api-config.php`
  - `wp-content/plugins/apps-ia/includes/class-lmd-api-manager.php`
  - éventuellement les autres classes qui lisent directement des options API
  Problème:
  - les écrans de config API sont pensés comme "site principal uniquement";
  - mais le runtime lit actuellement les options sur le blog courant, donc un site enfant n'utilise pas automatiquement la config du site principal.
  À corriger:
  - définir une source unique de vérité pour les clés API et modèles;
  - si la règle métier est "config centralisée sur le site principal", alors toutes les lectures runtime des options API doivent aller lire les options du blog `1` en multisite;
  - vérifier aussi les options de modèle Gemini et les autres options liées à l'IA, pas seulement les clés.
  Statut:
  - corrigé dans le runtime des lectures API;
  - validé par test manuel: l'analyse IA fonctionne maintenant aussi sur un site enfant avec les clés configurées sur le parent.

- [ ] Uniformiser les protections serveur des écrans et routes parent-only.
  Fichiers concernés:
  - `wp-content/plugins/apps-ia/admin/class-lmd-admin.php`
  - `wp-content/plugins/apps-ia/admin/views/api-config.php`
  - `wp-content/plugins/apps-ia/admin/class-lmd-ajax.php`
  Problème:
  - certaines pages/exports sont protégées explicitement avec `is_main_site()`;
  - d'autres reposent surtout sur le menu ou l'interface, ce qui n'est pas suffisant.
  À corriger:
  - ajouter une vérification serveur homogène sur tous les handlers parent-only;
  - ajouter la même règle aux routes `admin_post` d'export/import;
  - vérifier les endpoints AJAX de préférences/configuration pour éviter qu'un site enfant puisse exécuter une action réservée au principal;
  - ajouter une garde explicite dans `api-config.php` pour être cohérent avec les autres vues parent-only.
  État du test:
  - les accès directs depuis un site enfant à `admin.php?page=lmd-api-config`, `lmd-consumption`, `lmd-product-margin`, `lmd-copy-export-import`, `lmd-promotions` et `lmd-activity` sont déjà refusés par WordPress;
  - les routes `admin-post` parent-only d'export/import ont été verrouillées côté serveur;
  - le point restant à auditer concerne surtout certains endpoints AJAX si l'on veut fermer complètement cette priorité.

- [x] Corriger l'export multisite pour qu'il embarque les bonnes options parent.
  Fichier concerné:
  - `wp-content/plugins/apps-ia/includes/class-lmd-full-export-import.php`
  Problème:
  - lors de l'export d'un site enfant, le code ne récupère pas correctement certaines options censées venir du parent, à cause d'une condition évaluée après `switch_to_blog($site_id)`.
  À corriger:
  - séparer clairement les données locales au site exporté et les données réseau/parent;
  - lire les options du parent dans le bon contexte;
  - vérifier en particulier les options de reporting mensuel et, selon l'architecture retenue, les options API partagées.
  Statut:
  - corrigé dans le flux d'export;
  - validé partiellement par un export manuel réussi depuis le parent.

- [x] Corriger l'agrégation multisite de l'écran `Consommation IA`.
  Fichier corrigé:
  - `wp-content/plugins/apps-ia/includes/class-lmd-api-usage.php`
  Problème:
  - `LMD_Api_Usage` mémorisait le préfixe SQL du blog au moment de l'instanciation;
  - depuis le site parent, l'écran `Consommation IA` parcourait bien les sites enfants, mais continuait à lire la table du parent après `switch_to_blog()`;
  - le tableau réseau pouvait donc afficher `0` ou des chiffres faux alors que les logs existaient bien sur les tables des sites enfants.
  Correction appliquée:
  - la classe resynchronise maintenant son contexte WordPress/SQL avant chaque accès à la table `api_usage`;
  - les lectures multisites (`get_consumption_for_period()`, `get_all_clients_consumption()`, agrégats associés) utilisent désormais la bonne table pour chaque blog.
  Vérification à faire:
  - recharger `Consommation IA` sur le site parent;
  - vérifier que le tableau réseau remonte bien les analyses déjà effectuées sur les sites enfants.

## Priorité moyenne

- [x] Revoir la stratégie de navigation admin du site principal.
  Fichier corrigé:
  - `wp-content/plugins/apps-ia/admin/class-lmd-admin.php`
  Correction appliquée:
  - les sous-menus utiles ne sont plus masqués sur le site principal;
  - les sites enfants gardent leurs sous-pages masquées comme avant;
  - la page technique `Détail estimation` reste cachée.

- [x] Corriger la suppression des tags par avis.
  Fichiers corrigés:
  - `wp-content/plugins/apps-ia/admin/class-lmd-ajax.php`
  - `wp-content/plugins/apps-ia/admin/ajax-handlers.php`
  - `wp-content/plugins/apps-ia/admin/views/estimation-detail.php`
  - `wp-content/plugins/apps-ia/admin/views/estimations-list-modern.php`
  - `wp-content/plugins/apps-ia/includes/class-lmd-database.php`
  - `wp-content/plugins/apps-ia/includes/lmd-helpers.php`
  Correction appliquée:
  - l'écriture des tags `interet`, `estimation` et `theme_vente` est maintenant isolée par avis;
  - la lecture des tags dans la fiche détail, l'AJAX de rendu et la liste admin tient maintenant compte de `modified_by_avis`;
  - le cas où les deux avis choisissent exactement le même tag est géré via un état partagé (`modified_by_avis = 0`) sans migration de schéma lourde.
  Vérification à faire:
  - confirmer en UI qu'un tag choisi sur l'avis 1 n'efface plus celui de l'avis 2, et inversement;
  - confirmer qu'un même tag peut être choisi sur les deux avis sans perte de données.

- [x] Empêcher le signalement "IA se trompe" de purger les tags manuels.
  Fichier corrigé:
  - `wp-content/plugins/apps-ia/admin/ajax-handlers.php`
  Correction appliquée:
  - le reset du dossier après signalement d'erreur IA ne supprime plus les tags `interet`, `estimation` et `theme_vente`;
  - les tags manuels posés en avis 1 ou avis 2 sont donc conservés.

- [x] Corriger l'incohérence du paramètre de token dans le système de délégation externe.
  Fichiers corrigés:
  - `wp-content/plugins/apps-ia/admin/class-lmd-ajax.php`
  - `wp-content/plugins/apps-ia/public/class-lmd-delegation-view.php`
  Correction appliquée:
  - la vue publique et le shortcode lisent maintenant un extracteur commun de token;
  - le système accepte `lmd_delegation_token` et garde une compatibilité arrière avec `token`;
  - l'URL admin générée est alignée sur `lmd_delegation_token`.
  Vérification à faire:
  - regénérer un lien et vérifier qu'il ouvre toujours la vue publique;
  - si le shortcode est utilisé, vérifier qu'il fonctionne avec le paramètre `lmd_delegation_token`.

- [ ] Vérifier la cohérence globale du workflow de deuxième avis externe.
  Fichiers concernés:
  - `wp-content/plugins/apps-ia/admin/class-lmd-ajax.php`
  - `wp-content/plugins/apps-ia/public/class-lmd-delegation-view.php`
  - `wp-content/plugins/apps-ia/admin/views/estimation-detail.php`
  Constat:
  - le système actuel partage bien un dossier par lien tokenisé;
  - mais il ne fournit pas encore un vrai workflow complet de retour externe de l'avis 2.
  Action attendue:
  - décider si le comportement actuel est volontaire;
  - sinon, cadrer une correction fonctionnelle distincte pour permettre une réponse externe exploitable.
  État du test:
  - le lien tokenisé s'ouvre bien côté public;
  - la page affichée est aujourd'hui minimale: titre `Estimation #ID`, photo(s), description si présente;
  - aucun formulaire ou mécanisme de retour d'avis externe n'est actuellement exposé.

- [x] Corriger la logique des correspondances Lens dans l'analyse IA.
  Fichier corrigé:
  - `wp-content/plugins/apps-ia/includes/class-lmd-estimation-processor.php`
  Problème:
  - le plugin ne demandait à SerpAPI que `visual_matches`;
  - si Lens renvoyait surtout `exact_matches` ou `products`, le volet `Correspondances` restait presque vide;
  - en plus, Gemini pouvait produire des `visual_comparisons` alors qu'aucune vraie correspondance n'avait été conservée.
  Correction appliquée:
  - le pipeline Lens interroge maintenant `all` au lieu de `visual_matches` seulement;
  - les correspondances fusionnent désormais `exact_matches`, `visual_matches` et `products`;
  - un fallback sans requête texte est tenté si le premier appel Lens revient vide;
  - les faux `visual_comparisons` sont vidés lorsqu'aucune correspondance réelle n'est disponible.
  Vérification à faire:
  - relancer une analyse IA sur quelques objets variés;
  - vérifier que l'onglet `Correspondances` remonte plus souvent des résultats concrets;
  - vérifier que les objets qui n'ont vraiment aucune piste n'affichent plus de comparaisons fantômes.

## Priorité basse

- [x] Aligner la version du plugin entre l'en-tête WordPress et la constante interne.
  Fichier corrigé:
  - `wp-content/plugins/apps-ia/lmd-apps-ia.php`
  Correction appliquée:
  - l'en-tête WordPress et `LMD_VERSION` sont maintenant synchronisés sur `1.0.38`.

## Vérifications à prévoir après correctifs

- [x] Vérifier qu'une analyse IA lancée depuis un site enfant utilise bien la configuration du site principal, si c'est la règle métier retenue.
- [ ] Vérifier qu'aucune importation depuis le site principal ne laisse WordPress bloqué sur le mauvais blog après exécution.
- [x] Vérifier qu'une exportation depuis le site principal ne laisse pas WordPress bloqué sur le mauvais blog après exécution.
- [x] Vérifier qu'un utilisateur admin d'un site enfant ne peut pas accéder aux pages réservées au site principal via `admin.php?page=...`.
- [ ] Vérifier qu'un utilisateur admin d'un site enfant ne peut pas exécuter les actions réservées au site principal via `admin-post.php` ou AJAX.
- [ ] Vérifier qu'un tag d'avis 1 n'efface plus un tag d'avis 2.
- [x] Vérifier qu'un lien de délégation généré côté admin ouvre bien la vue publique prévue.
- [ ] Vérifier qu'un signalement "IA se trompe" ne supprime plus les tags manuels déjà posés.
- [ ] Vérifier que la vue publique de délégation expose bien tout le contenu et les actions attendus pour un vrai deuxième avis externe.
