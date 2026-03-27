<?php
/**
 * Configuration des 7 catégories de tags pour les estimations
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Couleurs des tags - VERT réservé à l'IA uniquement.
 * Pour l'estimation, la couleur dépend de la source (ia=vert, cp=bleu, avis2=mauve).
 *
 * @param string $type Type de tag
 * @param string $slug Slug du tag
 * @param string $source 'ia'|'cp'|'avis2' pour estimation uniquement
 */
function lmd_get_tag_filter_colors($type, $slug = '', $source = '') {
    $neutral = ['bg' => '#f9fafb', 'text' => '#6b7280', 'border' => '#e5e7eb'];
    $vendeur_neutral = ['bg' => '#fff', 'text' => '#111827', 'border' => '#9ca3af'];
    $green_ia = ['bg' => '#f0fdf4', 'text' => '#15803d', 'border' => '#22c55e'];
    $blue_cp = ['bg' => '#eff6ff', 'text' => '#1d4ed8', 'border' => '#3b82f6'];
    $mauve_avis2 = ['bg' => '#f5f3ff', 'text' => '#6d28d9', 'border' => '#8b5cf6'];
    if ($type === 'vendeur') return $vendeur_neutral;
    if ($source === 'ia' || $source === 'form') return $green_ia;
    if ($source === 'cp') return $blue_cp;
    if ($source === 'avis2') return $mauve_avis2;
    $colors = [
        'message' => [
            'default' => ['bg' => '#f3f4f6', 'text' => '#374151', 'border' => '#e5e7eb'],
            'vendu' => ['bg' => '#fef2f2', 'text' => '#b91c1c', 'border' => '#fecaca'],
        ],
        'interet' => [
            'pas_pour_nous' => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#e5e7eb'],
            'peu_interessant' => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#e5e7eb'],
            'a_examiner' => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#e5e7eb'],
            'interessant' => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#e5e7eb'],
            'tres_interessant' => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#e5e7eb'],
            'exceptionnel' => ['bg' => '#f3f4f6', 'text' => '#6b7280', 'border' => '#e5e7eb'],
        ],
        'estimation' => $neutral,
        'theme_vente' => ['bg' => '#f3f4f6', 'text' => '#374151', 'border' => '#e5e7eb'],
        'date_vente' => ['bg' => '#f3f4f6', 'text' => '#374151', 'border' => '#e5e7eb'],
        'vendeur' => ['bg' => '#f3f4f6', 'text' => '#475569', 'border' => '#e5e7eb'],
    ];
    if ($type === 'interet' && $slug && isset($colors['interet'][$slug])) {
        return $colors['interet'][$slug];
    }
    if ($type === 'message' && $slug === 'vendu' && isset($colors['message']['vendu'])) {
        return $colors['message']['vendu'];
    }
    if ($type === 'message') return $colors['message']['default'] ?? $neutral;
    if ($type === 'interet') return $neutral;
    return $colors[$type] ?? $neutral;
}

function lmd_get_interet_name($slug) {
    $cat = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
    $opts = $cat['interet']['options'] ?? [];
    foreach ($opts as $o) {
        if (($o['slug'] ?? '') === $slug) return $o['name'] ?? $slug;
    }
    return $slug;
}

function lmd_get_estimation_name($slug) {
    $cat = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
    $opts = $cat['estimation']['options'] ?? [];
    foreach ($opts as $o) {
        if (($o['slug'] ?? '') === $slug) return $o['name'] ?? $slug;
    }
    return $slug;
}

function lmd_get_theme_vente_name($slug) {
    $cat = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
    $opts = $cat['theme_vente']['options'] ?? [];
    foreach ($opts as $o) {
        if (($o['slug'] ?? '') === $slug) return $o['name'] ?? $slug;
    }
    $sub = $cat['theme_vente']['sub_options'] ?? [];
    foreach ($sub as $subopts) {
        foreach ($subopts as $o) {
            if (($o['slug'] ?? '') === $slug) return $o['name'] ?? $slug;
        }
    }
    return $slug;
}

function lmd_get_tag_categories() {
    $estimation_opts = function_exists('lmd_get_estimation_options_merged') ? lmd_get_estimation_options_merged() : [
        ['slug' => 'moins_25', 'name' => '< 25 €'],
        ['slug' => 'moins_100', 'name' => '< 100 €'],
        ['slug' => 'moins_500', 'name' => '< 500 €'],
        ['slug' => 'moins_1000', 'name' => '< 1 000 €'],
        ['slug' => 'moins_5000', 'name' => '< 5 000 €'],
        ['slug' => 'plus_5000', 'name' => '> 5 000 €'],
    ];
    $theme_opts = function_exists('lmd_get_theme_vente_options_merged') ? lmd_get_theme_vente_options_merged() : [
        ['slug' => 'tableaux_dessins_anciens', 'name' => 'Tableaux & Dessins anciens'],
        ['slug' => 'art_moderne', 'name' => 'Art moderne'],
        ['slug' => 'art_contemporain', 'name' => 'Art contemporain'],
        ['slug' => 'arts_decoratifs_design', 'name' => 'Arts décoratifs & Design'],
        ['slug' => 'mobilier_objets_art', 'name' => 'Mobilier & Objets d\'art'],
        ['slug' => 'bijoux_joaillerie', 'name' => 'Bijoux & Joaillerie'],
        ['slug' => 'mode_maroquinerie_luxe', 'name' => 'Mode & Maroquinerie de luxe'],
        ['slug' => 'livres_manuscrits_autographes', 'name' => 'Livres, Manuscrits & Autographes'],
        ['slug' => 'vins_spiritueux', 'name' => 'Vins & Spiritueux'],
        ['slug' => 'vehicules_collection', 'name' => 'Véhicules de collection'],
        ['slug' => 'arts_premiers_civilisations', 'name' => 'Arts premiers & Civilisations'],
    ];
    return [
        'vente' => [
            'label' => 'VV / VJ',
            'options' => [
                ['slug' => 'volontaire', 'name' => 'VV'],
                ['slug' => 'judiciaire', 'name' => 'VJ'],
            ],
        ],
        'message' => [
            'label' => 'Échanges',
            'options' => [
                ['slug' => 'nouveau', 'name' => 'Nouveau'],
                ['slug' => 'non_lu', 'name' => 'Non lu'],
                ['slug' => 'lu_non_repondu', 'name' => 'Non répondu'],
                ['slug' => 'en_retard', 'name' => 'En retard'],
                ['slug' => 'repondu', 'name' => 'Répondu'],
                ['slug' => 'vendu', 'name' => 'Vendu'],
            ],
        ],
        'interet' => [
            'label' => 'Intérêt',
            'options' => [
                ['slug' => 'pas_pour_nous', 'name' => 'Pas pour nous'],
                ['slug' => 'peu_interessant', 'name' => 'Peu intéressant'],
                ['slug' => 'a_examiner', 'name' => 'À examiner'],
                ['slug' => 'interessant', 'name' => 'Intéressant'],
                ['slug' => 'tres_interessant', 'name' => 'Très intéressant'],
                ['slug' => 'exceptionnel', 'name' => 'Exceptionnel'],
            ],
        ],
        'estimation' => [
            'label' => 'Estimation',
            'options' => $estimation_opts,
        ],
        'theme_vente' => [
            'label' => 'Thème de vente',
            'options' => array_map(function ($o) { return ['slug' => $o['slug'], 'name' => $o['name']]; }, $theme_opts),
            'sub_options' => [],
        ],
        'date_vente' => [
            'label' => 'Date de vente',
            'options' => [], // Dynamique - créée par le CP
        ],
        'vendeur' => [
            'label' => 'Vendeur',
            'options' => [], // Dynamique - basé sur l'email
        ],
    ];
}
