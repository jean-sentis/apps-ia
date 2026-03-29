<?php
/**
 * Paramètres personnalisables des catégories (estimation, theme_vente)
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LMD_OPTION_CUSTOM_CATEGORIES', 'lmd_custom_categories');

/**
 * Options par défaut pour estimation (intervalles)
 */
function lmd_get_default_estimation_options() {
    return [
        ['slug' => 'moins_25', 'name' => '< 25 €', 'max' => 25],
        ['slug' => 'moins_100', 'name' => '< 100 €', 'max' => 100],
        ['slug' => 'moins_500', 'name' => '< 500 €', 'max' => 500],
        ['slug' => 'moins_1000', 'name' => '< 1 000 €', 'max' => 1000],
        ['slug' => 'moins_5000', 'name' => '< 5 000 €', 'max' => 5000],
        ['slug' => 'plus_5000', 'name' => '> 5 000 €', 'min' => 5000],
    ];
}

/**
 * Options par défaut pour theme_vente
 */
function lmd_get_default_theme_vente_options() {
    return [
        ['slug' => 'tableaux_dessins_anciens', 'name' => 'Tableaux & Dessins anciens', 'parent_slug' => ''],
        ['slug' => 'art_moderne', 'name' => 'Art moderne', 'parent_slug' => ''],
        ['slug' => 'art_contemporain', 'name' => 'Art contemporain', 'parent_slug' => ''],
        ['slug' => 'arts_decoratifs_design', 'name' => 'Arts décoratifs & Design', 'parent_slug' => ''],
        ['slug' => 'mobilier_objets_art', 'name' => 'Mobilier & Objets d\'art', 'parent_slug' => ''],
        ['slug' => 'bijoux_joaillerie', 'name' => 'Bijoux & Joaillerie', 'parent_slug' => ''],
        ['slug' => 'mode_maroquinerie_luxe', 'name' => 'Mode & Maroquinerie de luxe', 'parent_slug' => ''],
        ['slug' => 'livres_manuscrits_autographes', 'name' => 'Livres, Manuscrits & Autographes', 'parent_slug' => ''],
        ['slug' => 'vins_spiritueux', 'name' => 'Vins & Spiritueux', 'parent_slug' => ''],
        ['slug' => 'vehicules_collection', 'name' => 'Véhicules de collection', 'parent_slug' => ''],
        ['slug' => 'arts_premiers_civilisations', 'name' => 'Arts premiers & Civilisations', 'parent_slug' => ''],
        ['slug' => 'electromenager_objets_commerce', 'name' => 'Électroménager & Objets du commerce', 'parent_slug' => ''],
        ['slug' => 'autres', 'name' => 'Autres', 'parent_slug' => ''],
    ];
}

/**
 * Récupère les options personnalisées (estimation ou theme_vente)
 */
function lmd_get_custom_category_options($type) {
    $all = get_option(LMD_OPTION_CUSTOM_CATEGORIES, []);
    $opts = isset($all[$type]) && is_array($all[$type]) ? $all[$type] : [];
    return $opts;
}

/**
 * Sauvegarde les options personnalisées
 */
function lmd_save_custom_category_options($type, $options) {
    $all = get_option(LMD_OPTION_CUSTOM_CATEGORIES, []);
    $all[$type] = $options;
    update_option(LMD_OPTION_CUSTOM_CATEGORIES, $all);
}

/**
 * Options estimation fusionnées (défaut + custom, custom remplace si défini)
 */
function lmd_get_estimation_options_merged() {
    $defaults = lmd_get_default_estimation_options();
    $custom = lmd_get_custom_category_options('estimation');
    if (empty($custom)) {
        return array_map(function ($o) {
            return ['slug' => $o['slug'], 'name' => $o['name']];
        }, $defaults);
    }
    return array_map(function ($o) {
        return ['slug' => $o['slug'], 'name' => $o['name']];
    }, $custom);
}

/**
 * Options theme_vente fusionnées (défaut + custom)
 */
function lmd_get_theme_vente_options_merged() {
    $defaults = lmd_get_default_theme_vente_options();
    $custom = lmd_get_custom_category_options('theme_vente');
    $by_slug = [];
    foreach ($defaults as $o) {
        $by_slug[$o['slug']] = ['slug' => $o['slug'], 'name' => $o['name'], 'parent_slug' => $o['parent_slug'] ?? ''];
    }
    foreach ($custom as $o) {
        $s = $o['slug'] ?? '';
        if ($s) {
            $by_slug[$s] = [
                'slug' => $s,
                'name' => $o['name'] ?? $s,
                'parent_slug' => $o['parent_slug'] ?? '',
            ];
        }
    }
    return array_values($by_slug);
}

/**
 * Paliers estimation pour filtres et conversion montant → slug
 */
function lmd_get_estimation_paliers() {
    $custom = lmd_get_custom_category_options('estimation');
    if (!empty($custom)) {
        $paliers = [];
        foreach ($custom as $o) {
            $s = $o['slug'] ?? '';
            if (!$s) continue;
            if (isset($o['max'])) {
                $paliers[$s] = ['max' => floatval($o['max'])];
            } elseif (isset($o['min'])) {
                $paliers[$s] = ['min' => floatval($o['min'])];
            }
        }
        return $paliers;
    }
    return [
        'moins_25' => ['max' => 25],
        'moins_100' => ['max' => 100],
        'moins_500' => ['max' => 500],
        'moins_1000' => ['max' => 1000],
        'moins_5000' => ['max' => 5000],
        'plus_5000' => ['min' => 5000],
    ];
}
