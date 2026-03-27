# Synthèse des fichiers de référence RESTORE

**Dossier source :** `/Users/jeansentis/Desktop/LMD Site 2025/business sites CP /RESTORE AIDE A LESTIM`  
*(Note : espace dans "business sites CP ")*

---

## Structure du dossier

```
RESTORE AIDE A LESTIM/
├── files 24 FEV/                    ← Fichiers Claude (24 fév)
│   ├── RESTAURATION-URGENTE.md
│   ├── lmd-apps-ia.php
│   ├── plugin-backup-complet.tar.gz
│   └── restore-plugin.sh
└── le-marteau-digital-module - 15 fevrier/   ← Plugin complet 15 fév
    ├── admin/views/
    │   ├── estimation-detail.php              ← VERSION-71-POST-FIX (principal)
    │   ├── estimation-detail-V47-LOVABLE-EXACT.php  ← Référence CSS Lovable
    │   ├── estimation-detail-original.php
    │   ├── estimation-detail-v62-current.php
    │   ├── estimation-detail-v63.php          ← Wrapper avec patches
    │   ├── estimation-detail-v48.php, v46, v47-backup, v61-backup...
    │   └── estimations-lovable-sidebar.php     ← Layout sidebar Lovable
    ├── admin/js/estimation-detail.js
    └── assets/admin-style.css                 ← Styles admin inspirés Lovable
```

---

## Référence CSS Lovable (V47-LOVABLE-EXACT)

**Fichier :** `estimation-detail-V47-LOVABLE-EXACT.php`

### Classes principales
| Lovable | Description |
|---------|--------------|
| `.detail-container` | max-width: 1600px, margin auto, padding 16px |
| `.grid-3col` | grid 3 colonnes égales (1fr 1fr 1fr), gap 0, min-height 400px |
| `.col` | border 2px #e5e7eb, padding 16px, background white, border-radius 12px |
| `.tabs` | flex, border-bottom 2px #e5e7eb |
| `.tab` | padding 10px 20px, color #9ca3af, active: #10b981 |
| `.actions-grid` | grid 3 colonnes, gap 8px |
| `.action-btn` | padding 12px 8px, border #e5e7eb, border-radius 6px |
| `.chrome-tab` | style onglet type Chrome, border-top 3px #22c55e |

### Palette Lovable
- Texte : #374151
- Gris clair : #9ca3af, #e5e7eb
- Vert actif : #10b981, #22c55e
- Bouton : #1f2937 (fond), #111827 (hover)

---

## Version 15 février (estimation-detail.php)

- **Classes :** `.ed-wrap`, `.ed-grid`, `.ed-col`, `.ed-tabs`, `.ed-tab`, `.ed-action-btns`, `.ed-chrome-tab`
- **Colonne 3 :** ACTIONS (Appeler, Email, Déléguer) avec panneau email dépliant
- **AI :** Synthèse + 5 onglets (Identité, Correspondances, Marché, État, Questions)
- **Pas de tags** (système d’intérêt via select simple)

---

## Plugin actuel (ce qui a été poussé plus loin)

- **Tags :** `.ed-tags-bar`, `.ed-tag-btn`, `.ed-tag-dd` (7 catégories, dropdowns)
- **Colonne 3 :** Réponse / Déléguer avec onglets, email, brouillon
- **1er / 2ème Avis :** séparation des notes et estimations
- **Grid 28 parts :** pour la section AI (Synthèse, bouton, badges, 5 onglets)
- **AJAX :** sauvegarde avis, tags, délégation, analyse

---

## Recommandations pour la restauration CSS

1. **Conserver** la structure actuelle (tags, colonne 3, 1er/2ème avis, AJAX).
2. **Aligner le rendu** sur Lovable :
   - Grille 3 colonnes type `.grid-3col` (1fr 1fr 1fr)
   - Bordures #e5e7eb, border-radius 12px
   - Onglets avec soulignement vert #10b981 / #22c55e
   - Boutons d’action type `.action-btn`
3. **Réduire** les `!important` en gardant uniquement ceux nécessaires contre l’admin WP.
4. **Vérifier** que la grille 28 parts reste lisible et proportionnée.

---

## Fichiers à consulter pour la restauration

| Objectif | Fichier |
|----------|---------|
| CSS Lovable | `estimation-detail-V47-LOVABLE-EXACT.php` (l.99–136) |
| Structure colonne 3 | `estimation-detail.php` 15 fév (l.239–295) |
| Sidebar Lovable | `estimations-lovable-sidebar.php` |
| Styles admin | `assets/admin-style.css` |
