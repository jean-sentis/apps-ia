<?php
/**
 * Reglages du module Expertise IA.
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

function lmd_get_expertise_settings_defaults()
{
    return [
        "enabled" => false,
    ];
}

function lmd_get_expertise_settings()
{
    $saved = get_option("lmd_expertise_settings", []);
    if (!is_array($saved)) {
        $saved = [];
    }

    $settings = wp_parse_args($saved, lmd_get_expertise_settings_defaults());
    $settings["enabled"] = !empty($settings["enabled"]);

    return $settings;
}

function lmd_save_expertise_settings($raw)
{
    $raw = is_array($raw) ? $raw : [];
    $settings = [
        "enabled" => !empty($raw["enabled"]),
    ];

    update_option("lmd_expertise_settings", $settings);

    return wp_parse_args($settings, lmd_get_expertise_settings_defaults());
}

function lmd_is_expertise_enabled()
{
    $settings = lmd_get_expertise_settings();

    return !empty($settings["enabled"]);
}
