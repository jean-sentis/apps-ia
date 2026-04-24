<?php
/**
 * Réglages SEO des lots (site par site)
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

function lmd_get_seo_settings_defaults() {
    return [
        'enabled' => false,
        'estimate_gate' => [
            'mode' => 'either',
            'low_min' => '',
            'high_min' => '',
        ],
        'sale_types' => [
            'volontaire' => true,
            'judiciaire' => true,
        ],
        'excluded_sale_ids' => [],
        'limit_categories' => false,
        'allowed_categories' => [],
        'outputs' => [
            'title' => true,
            'description' => true,
            'alts' => true,
            'schema' => true,
        ],
        'premium' => [
            'lens' => false,
            'firecrawl' => false,
        ],
    ];
}

function lmd_get_seo_sale_category_terms() {
    if (!taxonomy_exists('categorie_vente')) {
        return [];
    }

    $terms = get_terms([
        'taxonomy' => 'categorie_vente',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || !is_array($terms)) {
        return [];
    }

    usort($terms, static function ($a, $b) {
        return strcasecmp((string) $a->name, (string) $b->name);
    });

    return $terms;
}

function lmd_get_seo_settings() {
    $saved = get_option('lmd_seo_settings', []);
    if (!is_array($saved)) {
        $saved = [];
    }

    $defaults = lmd_get_seo_settings_defaults();
    $settings = wp_parse_args($saved, $defaults);
    $settings['estimate_gate'] = wp_parse_args(
        is_array($settings['estimate_gate'] ?? null) ? $settings['estimate_gate'] : [],
        $defaults['estimate_gate']
    );
    $settings['sale_types'] = wp_parse_args(
        is_array($settings['sale_types'] ?? null) ? $settings['sale_types'] : [],
        $defaults['sale_types']
    );
    $settings['outputs'] = wp_parse_args(
        is_array($settings['outputs'] ?? null) ? $settings['outputs'] : [],
        $defaults['outputs']
    );
    $settings['premium'] = wp_parse_args(
        is_array($settings['premium'] ?? null) ? $settings['premium'] : [],
        $defaults['premium']
    );
    $settings['allowed_categories'] = array_values(array_unique(array_filter(array_map(
        'sanitize_key',
        is_array($settings['allowed_categories'] ?? null) ? $settings['allowed_categories'] : []
    ))));
    $settings['excluded_sale_ids'] = array_values(array_unique(array_filter(array_map(
        'absint',
        is_array($settings['excluded_sale_ids'] ?? null) ? $settings['excluded_sale_ids'] : []
    ))));
    $settings['enabled'] = !empty($settings['enabled']);
    $settings['limit_categories'] = !empty($settings['limit_categories']);

    return $settings;
}

function lmd_normalize_seo_threshold_value($value) {
    $value = trim((string) wp_unslash($value));
    if ($value === '') {
        return '';
    }

    $value = str_replace(',', '.', $value);
    $value = preg_replace('/[^0-9.\-]/', '', $value);
    if ($value === '' || $value === '-' || $value === '.') {
        return '';
    }

    $float = max(0, (float) $value);
    $normalized = number_format($float, 2, '.', '');
    $normalized = rtrim(rtrim($normalized, '0'), '.');

    return $normalized;
}

function lmd_save_seo_settings($raw) {
    $defaults = lmd_get_seo_settings_defaults();
    $estimate_modes = ['low', 'high', 'either'];
    $selected_mode = sanitize_key($raw['estimate_gate']['mode'] ?? $defaults['estimate_gate']['mode']);
    if (!in_array($selected_mode, $estimate_modes, true)) {
        $selected_mode = $defaults['estimate_gate']['mode'];
    }

    $allowed_category_slugs = [];
    foreach (lmd_get_seo_sale_category_terms() as $term) {
        $allowed_category_slugs[] = $term->slug;
    }

    $selected_categories = is_array($raw['allowed_categories'] ?? null) ? $raw['allowed_categories'] : [];
    $selected_categories = array_values(array_unique(array_filter(array_map('sanitize_key', $selected_categories))));
    $excluded_sale_ids = is_array($raw['excluded_sales'] ?? null) ? $raw['excluded_sales'] : [];
    $excluded_sale_ids = array_values(array_unique(array_filter(array_map('absint', $excluded_sale_ids))));
    if (!empty($allowed_category_slugs)) {
        $selected_categories = array_values(array_intersect($selected_categories, $allowed_category_slugs));
        if (empty($selected_categories)) {
            $selected_categories = array_values($allowed_category_slugs);
        }
    }

    $settings = [
        'enabled' => !empty($raw['enabled']),
        'estimate_gate' => [
            'mode' => $selected_mode,
            'low_min' => lmd_normalize_seo_threshold_value($raw['estimate_gate']['low_min'] ?? ''),
            'high_min' => lmd_normalize_seo_threshold_value($raw['estimate_gate']['high_min'] ?? ''),
        ],
        'sale_types' => [
            'volontaire' => !empty($raw['sale_types']['volontaire']),
            'judiciaire' => !empty($raw['sale_types']['judiciaire']),
        ],
        'excluded_sale_ids' => $excluded_sale_ids,
        'limit_categories' => !empty($raw['limit_categories']),
        'allowed_categories' => $selected_categories,
        'outputs' => [
            'title' => !empty($raw['outputs']['title']),
            'description' => !empty($raw['outputs']['description']),
            'alts' => !empty($raw['outputs']['alts']),
            'schema' => !empty($raw['outputs']['schema']),
        ],
        'premium' => [
            'lens' => !empty($raw['premium']['lens']),
            'firecrawl' => !empty($raw['premium']['firecrawl']),
        ],
    ];

    update_option('lmd_seo_settings', $settings);

    return wp_parse_args($settings, $defaults);
}

