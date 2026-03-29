# Corrections à faire sur `lmd-apps-ia`

Document de travail basé sur l'audit actuel du plugin.
Objectif: lister les corrections à implémenter, sans encore faire les patchs.

## Priorité haute

- [ ] Corriger la fuite de contexte multisite dans le hub parent.
  Fichiers concernés:
  - `wp-content/plugins/lmd-apps-ia/includes/class-lmd-activity-analytics.php`
  - `wp-content/plugins/lmd-apps-ia/admin/views/hub.php`
  - `wp-content/plugins/lmd-apps-ia/admin/views/api-config.php`
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

- [ ] Corriger les fuites de contexte multisite dans les fonctions qui utilisent `switch_to_blog()`.
  Fichiers concernés:
  - `wp-content/plugins/lmd-apps-ia/lmd-apps-ia.php`
  - `wp-content/plugins/lmd-apps-ia/includes/class-lmd-full-export-import.php`
  À corriger:
  - `lmd_send_monthly_consumption_report()` doit toujours exécuter `restore_current_blog()` avant chaque `return` après un `switch_to_blog(1)`.
  - `LMD_Full_Export_Import::export()` doit restaurer le blog courant de façon fiable après export, y compris en cas d'erreur.
  - La logique de restauration ne doit pas dépendre d'un test du type `$site_id !== get_current_blog_id()` après le switch, car ce test devient faux une fois le blog déjà changé.

- [ ] Corriger la logique parent/enfant de la configuration API.
  Fichiers concernés:
  - `wp-content/plugins/lmd-apps-ia/admin/views/api-config.php`
  - `wp-content/plugins/lmd-apps-ia/includes/class-lmd-api-manager.php`
  - éventuellement les autres classes qui lisent directement des options API
  Problème:
  - les écrans de config API sont pensés comme "site principal uniquement";
  - mais le runtime lit actuellement les options sur le blog courant, donc un site enfant n'utilise pas automatiquement la config du site principal.
  À corriger:
  - définir une source unique de vérité pour les clés API et modèles;
  - si la règle métier est "config centralisée sur le site principal", alors toutes les lectures runtime des options API doivent aller lire les options du blog `1` en multisite;
  - vérifier aussi les options de modèle Gemini et les autres options liées à l'IA, pas seulement les clés.

- [ ] Uniformiser les protections serveur des écrans et routes parent-only.
  Fichiers concernés:
  - `wp-content/plugins/lmd-apps-ia/admin/class-lmd-admin.php`
  - `wp-content/plugins/lmd-apps-ia/admin/views/api-config.php`
  - `wp-content/plugins/lmd-apps-ia/admin/class-lmd-ajax.php`
  Problème:
  - certaines pages/exports sont protégées explicitement avec `is_main_site()`;
  - d'autres reposent surtout sur le menu ou l'interface, ce qui n'est pas suffisant.
  À corriger:
  - ajouter une vérification serveur homogène sur tous les handlers parent-only;
  - ajouter la même règle aux routes `admin_post` d'export/import;
  - vérifier les endpoints AJAX de préférences/configuration pour éviter qu'un site enfant puisse exécuter une action réservée au principal;
  - ajouter une garde explicite dans `api-config.php` pour être cohérent avec les autres vues parent-only.

- [ ] Corriger l'export multisite pour qu'il embarque les bonnes options parent.
  Fichier concerné:
  - `wp-content/plugins/lmd-apps-ia/includes/class-lmd-full-export-import.php`
  Problème:
  - lors de l'export d'un site enfant, le code ne récupère pas correctement certaines options censées venir du parent, à cause d'une condition évaluée après `switch_to_blog($site_id)`.
  À corriger:
  - séparer clairement les données locales au site exporté et les données réseau/parent;
  - lire les options du parent dans le bon contexte;
  - vérifier en particulier les options de reporting mensuel et, selon l'architecture retenue, les options API partagées.

## Priorité moyenne

- [ ] Revoir la stratégie de navigation admin du site principal.
  Fichiers concernés:
  - `wp-content/plugins/lmd-apps-ia/admin/class-lmd-admin.php`
  - `wp-content/plugins/lmd-apps-ia/admin/views/hub.php`
  Constat:
  - plusieurs pages sont enregistrées avec `menu_title = null`, donc elles sont volontairement masquées dans le menu latéral WordPress;
  - sur le site principal, cela donne l'impression qu'une partie des menus "manque", alors qu'ils sont en réalité déplacés dans les onglets du hub.
  À décider:
  - soit assumer pleinement cette navigation et clarifier visuellement dans le hub où se trouvent les outils parent;
  - soit rendre visibles certaines entrées dans le menu latéral du site principal;
  - dans tous les cas, éviter une navigation hybride qui ressemble à un bug pour l'utilisateur final.

- [ ] Corriger la suppression des tags par avis.
  Fichier concerné:
  - `wp-content/plugins/lmd-apps-ia/admin/class-lmd-ajax.php`
  Problème:
  - la suppression/remplacement d'un tag ne semble pas filtrer correctement sur `modified_by_avis`;
  - enregistrer un tag pour l'avis 1 peut supprimer celui de l'avis 2, et inversement.
  À corriger:
  - filtrer les opérations de suppression par estimation, type de tag et avis concerné;
  - vérifier l'intégrité des tags déjà existants après correction.

- [ ] Corriger l'incohérence du paramètre de token dans le système de délégation externe.
  Fichiers concernés:
  - `wp-content/plugins/lmd-apps-ia/admin/class-lmd-ajax.php`
  - `wp-content/plugins/lmd-apps-ia/public/class-lmd-delegation-view.php`
  Problème:
  - le lien généré côté admin utilise `lmd_delegation_token`;
  - le shortcode/vue publique lit `token`.
  À corriger:
  - unifier le nom du paramètre;
  - vérifier toutes les URLs générées et toutes les entrées possibles;
  - supprimer les variantes mortes ou les rendre compatibles si nécessaire.

- [ ] Vérifier la cohérence globale du workflow de deuxième avis externe.
  Fichiers concernés:
  - `wp-content/plugins/lmd-apps-ia/admin/class-lmd-ajax.php`
  - `wp-content/plugins/lmd-apps-ia/public/class-lmd-delegation-view.php`
  - `wp-content/plugins/lmd-apps-ia/admin/views/estimation-detail.php`
  Constat:
  - le système actuel partage bien un dossier par lien tokenisé;
  - mais il ne fournit pas encore un vrai workflow complet de retour externe de l'avis 2.
  Action attendue:
  - décider si le comportement actuel est volontaire;
  - sinon, cadrer une correction fonctionnelle distincte pour permettre une réponse externe exploitable.

## Priorité basse

- [ ] Aligner la version du plugin entre l'en-tête WordPress et la constante interne.
  Fichier concerné:
  - `wp-content/plugins/lmd-apps-ia/lmd-apps-ia.php`
  Problème:
  - la version déclarée dans l'entête du plugin et `LMD_VERSION` ne sont pas identiques.
  À corriger:
  - choisir une seule version de référence et garder les deux valeurs synchronisées.

## Vérifications à prévoir après correctifs

- [ ] Vérifier qu'une analyse IA lancée depuis un site enfant utilise bien la configuration du site principal, si c'est la règle métier retenue.
- [ ] Vérifier qu'aucune exportation/importation depuis le site principal ne laisse WordPress bloqué sur le mauvais blog après exécution.
- [ ] Vérifier qu'un utilisateur admin d'un site enfant ne peut pas accéder ni exécuter les fonctions réservées au site principal, même en appelant directement les URLs.
- [ ] Vérifier qu'un tag d'avis 1 n'efface plus un tag d'avis 2.
- [ ] Vérifier qu'un lien de délégation généré côté admin ouvre bien la vue publique prévue.
