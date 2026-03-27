# Plan de simulation Parent-Enfant API — 2 semaines

## Objectif

Valider que la relation parent (robinet API) ↔ enfant (extensions consommatrices) fonctionne avant d'investir dans la facturation et la recommandation de modèles.

---

## Architecture cible

```
┌─────────────────────────────────────────────────────────┐
│  LMD API Faucet (parent)                                 │
│  - Clés API centralisées (Gemini, Firecrawl, etc.)       │
│  - Registre des services disponibles                      │
│  - Enfants s'enregistrent et récupèrent ce dont ils ont   │
│    besoin                                                  │
│  - Log des appels (préparation facturation future)        │
└─────────────────────────────────────────────────────────┘
         │                    │
         ▼                    ▼
┌──────────────────┐  ┌──────────────────────────────┐
│ LMD Apps IA      │  │ Splitscreen                  │
│ (module1)        │  │ (thème ou plugin)             │
│ - Gemini analyse │  │ - Gemini génération images   │
│ - Firecrawl      │  │ - Récupère clé via parent     │
│ - ImgBB          │  │ - S'enregistre comme enfant   │
└──────────────────┘  └──────────────────────────────┘
```

---

## Semaine 1 — Parent minimal + 1 enfant

### J1–J2 : Créer le plugin parent `lmd-api-faucet`

**Fichiers :**
```
wp-content/plugins/lmd-api-faucet/
├── lmd-api-faucet.php          # Bootstrap, charge avant les enfants
├── includes/
│   ├── class-faucet-registry.php   # Registre des enfants et services
│   └── class-faucet-keys.php      # Gestion des clés (get_option pour l'instant)
└── admin/
    └── settings.php               # Page réglages : Gemini, Firecrawl, etc.
```

**Fonctionnalités minimales :**
1. `LMD_API_Faucet::get_key('gemini')` → retourne la clé Gemini
2. `do_action('lmd_faucet_register', $child_slug, $needs)` → les enfants s'enregistrent
3. `apply_filters('lmd_faucet_key_gemini', $key)` → les enfants récupèrent la clé
4. `do_action('lmd_faucet_log_call', $child, $service, $metadata)` → log pour facturation future
5. Ordre de chargement : parent en priorité (Plugin Name avec dépendance ou mu-plugin)

### J3–J4 : Splitscreen comme enfant

**Modifications dans le thème `maison-lmd` (ou plugin Splitscreen) :**
- `class-passoc12-gemini-montage.php` : au lieu de `get_option('lmd_gemini_key')`, appeler `LMD_API_Faucet::get_key('gemini')` si le parent existe, sinon fallback
- Au chargement : `do_action('lmd_faucet_register', 'splitscreen', ['gemini'])`
- Après chaque appel API : `do_action('lmd_faucet_log_call', 'splitscreen', 'gemini_image', [...])`

**Test :** Générer un montage → doit fonctionner avec la clé du parent. Si parent désactivé → fallback sur options du thème/site.

### J5 : Vérifier l'ordre de chargement

- Le parent doit se charger avant les enfants
- Options : `Requires Plugins` header (WP 6.5+), ou mu-plugin pour le parent, ou numéro dans le nom du dossier

---

## Semaine 2 — Module1 comme enfant + validation

### J6–J7 : LMD Apps IA (lmd-apps-ia) comme enfant

**Modifications dans `lmd-apps-ia` :**
- `class-lmd-api-manager.php` : 
  - `get_gemini_key()` → si parent existe, `LMD_API_Faucet::get_key('gemini')`, sinon `get_option('lmd_gemini_key')`
  - Idem pour Firecrawl, ImgBB
- Au chargement : `do_action('lmd_faucet_register', 'lmd-estim', ['gemini', 'firecrawl', 'imgbb'])`
- Dans les méthodes d'appel API : log via `do_action('lmd_faucet_log_call', ...)`

**Test :** Lancer une analyse → doit utiliser les clés du parent.

### J8–J9 : Page admin Parent — Vue des enfants

- Afficher la liste des enfants enregistrés
- Afficher un log simplifié des appels (derniers 50)
- Vérifier que les deux enfants (splitscreen, lmd-estim) apparaissent

### J10 : Rétrocompatibilité et doc

- Si parent désactivé : les deux enfants continuent de fonctionner avec leurs options locales
- Rédiger une page "Comment créer un enfant" (enregistrement, récupération de clé, log)

---

## Critères de succès

| Critère | OK ? |
|---------|------|
| Parent charge avant les enfants | |
| Splitscreen récupère la clé Gemini du parent | |
| Module1 récupère Gemini/Firecrawl/ImgBB du parent | |
| Si parent désactivé, fallback local fonctionne | |
| Les enfants apparaissent dans la page admin du parent | |
| Les appels sont loggés | |

---

## Fichiers à créer / modifier

| Fichier | Action |
|---------|--------|
| `plugins/lmd-api-faucet/` | Créer (nouveau plugin) |
| `themes/maison-lmd/includes/class-passoc12-gemini-montage.php` (Splitscreen) | Modifier (utiliser parent) |
| `plugins/lmd-apps-ia/includes/class-lmd-api-manager.php` | Modifier (utiliser parent) |

---

## Suite (après validation)

- Table `lmd_faucet_calls` pour persister les logs
- Calcul coût par appel (tokens, modèle)
- Facturation client (coût vs facturé)
- Recommandation de modèles (plus puissant / plus rentable)
