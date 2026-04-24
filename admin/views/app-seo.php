<?php
/**
 * App SEO - réglages d'éligibilité et test manuel.
 *
 * @var array $lmd_seo_settings
 * @var bool  $lmd_seo_saved
 * @var array $lmd_seo_categories
 * @var array $lmd_seo_run_result
 * @var array $lmd_seo_purge_result
 * @var array $lmd_seo_batch_state
 * @var int   $lmd_seo_test_lot_id
 * @var int   $lmd_seo_purge_lot_id
 * @var int   $lmd_seo_forced_sale_id
 * @var string $lmd_seo_forced_sale_label
 * @var string $lmd_seo_forced_lot_number
 */
if (!defined("ABSPATH")) {
    exit();
}

$lmd_seo_settings =
    isset($lmd_seo_settings) && is_array($lmd_seo_settings)
        ? $lmd_seo_settings
        : (function_exists("lmd_get_seo_settings")
            ? lmd_get_seo_settings()
            : []);
$lmd_seo_saved = !empty($lmd_seo_saved);
$lmd_seo_categories =
    isset($lmd_seo_categories) && is_array($lmd_seo_categories)
        ? $lmd_seo_categories
        : (function_exists("lmd_get_seo_sale_category_terms")
            ? lmd_get_seo_sale_category_terms()
            : []);
$lmd_seo_run_result =
    isset($lmd_seo_run_result) && is_array($lmd_seo_run_result)
        ? $lmd_seo_run_result
        : [];
$lmd_seo_purge_result =
    isset($lmd_seo_purge_result) && is_array($lmd_seo_purge_result)
        ? $lmd_seo_purge_result
        : [];
$lmd_seo_batch_state =
    isset($lmd_seo_batch_state) && is_array($lmd_seo_batch_state)
        ? $lmd_seo_batch_state
        : [];
$lmd_seo_test_lot_id = isset($lmd_seo_test_lot_id)
    ? absint($lmd_seo_test_lot_id)
    : 0;
$lmd_seo_purge_lot_id = isset($lmd_seo_purge_lot_id)
    ? absint($lmd_seo_purge_lot_id)
    : $lmd_seo_test_lot_id;
$lmd_seo_forced_sale_id = isset($lmd_seo_forced_sale_id)
    ? absint($lmd_seo_forced_sale_id)
    : 0;
$lmd_seo_forced_sale_label = isset($lmd_seo_forced_sale_label)
    ? sanitize_text_field((string) $lmd_seo_forced_sale_label)
    : "";
$lmd_seo_forced_lot_number = isset($lmd_seo_forced_lot_number)
    ? sanitize_text_field((string) $lmd_seo_forced_lot_number)
    : "";
$lmd_seo_stats_month =
    isset($lmd_seo_stats_month) &&
    preg_match('/^\d{4}-\d{2}$/', (string) $lmd_seo_stats_month)
        ? (string) $lmd_seo_stats_month
        : wp_date("Y-m", null, wp_timezone());
$lmd_seo_month_stats =
    isset($lmd_seo_month_stats) && is_array($lmd_seo_month_stats)
        ? $lmd_seo_month_stats
        : [];
$lmd_seo_stats_year = (int) substr($lmd_seo_stats_month, 0, 4);
$lmd_seo_stats_month_num = (int) substr($lmd_seo_stats_month, 5, 2);
$lmd_seo_month_choices = [
    1 => __("Janvier", "lmd-apps-ia"),
    2 => __("Février", "lmd-apps-ia"),
    3 => __("Mars", "lmd-apps-ia"),
    4 => __("Avril", "lmd-apps-ia"),
    5 => __("Mai", "lmd-apps-ia"),
    6 => __("Juin", "lmd-apps-ia"),
    7 => __("Juillet", "lmd-apps-ia"),
    8 => __("Août", "lmd-apps-ia"),
    9 => __("Septembre", "lmd-apps-ia"),
    10 => __("Octobre", "lmd-apps-ia"),
    11 => __("Novembre", "lmd-apps-ia"),
    12 => __("Décembre", "lmd-apps-ia"),
];
$lmd_seo_year_choices = range(
    $lmd_seo_stats_year + 1,
    max($lmd_seo_stats_year - 3, 2020),
);

$lmd_suite_banner_title = __("Enrichissement SEO", "lmd-apps-ia");
$lmd_suite_banner_subtitle = __(
    "Réglages des lots à enrichir en SEO.",
    "lmd-apps-ia",
);
$site_badge = !is_multisite()
    ? __("Réglages du site", "lmd-apps-ia")
    : (is_main_site()
        ? __("Réglages du site principal", "lmd-apps-ia")
        : __("Réglages du site enfant", "lmd-apps-ia"));
$threshold_labels = [
    "low" => __("Estimation basse", "lmd-apps-ia"),
    "high" => __("Estimation haute", "lmd-apps-ia"),
    "either" => __("L'une des deux estimations", "lmd-apps-ia"),
];
$selected_mode = $lmd_seo_settings["estimate_gate"]["mode"] ?? "either";
$selected_categories = array_values(
    array_filter(
        array_map("strval", $lmd_seo_settings["allowed_categories"] ?? []),
    ),
);
$categories_available = !empty($lmd_seo_categories);
if ($categories_available && empty($selected_categories)) {
    $selected_categories = array_values(
        array_filter(
            array_map(static function ($term) {
                return (string) ($term->slug ?? "");
            }, $lmd_seo_categories),
        ),
    );
}
$lmd_seo_sale_calendar_entries =
    isset($lmd_seo_sale_calendar_entries) &&
    is_array($lmd_seo_sale_calendar_entries)
        ? $lmd_seo_sale_calendar_entries
        : [];
$lmd_seo_excluded_sale_ids = array_values(
    array_filter(
        array_map("absint", $lmd_seo_settings["excluded_sale_ids"] ?? []),
    ),
);
$lmd_seo_sale_calendar_data = [];
$lmd_seo_sale_calendar_month = wp_date("Y-m", null, wp_timezone());

foreach ($lmd_seo_sale_calendar_entries as $lmd_seo_sale_entry) {
    if (!is_array($lmd_seo_sale_entry)) {
        continue;
    }

    $lmd_seo_sale_entry_id = absint($lmd_seo_sale_entry["id"] ?? 0);
    $lmd_seo_sale_entry_date = sanitize_text_field(
        (string) ($lmd_seo_sale_entry["date"] ?? ""),
    );
    if (
        !$lmd_seo_sale_entry_id ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lmd_seo_sale_entry_date)
    ) {
        continue;
    }

    $lmd_seo_sale_calendar_data[] = [
        "id" => $lmd_seo_sale_entry_id,
        "title" => sanitize_text_field(
            (string) ($lmd_seo_sale_entry["title"] ?? ""),
        ),
        "date" => $lmd_seo_sale_entry_date,
        "type" => sanitize_text_field(
            (string) ($lmd_seo_sale_entry["type"] ?? ""),
        ),
        "categories" => array_values(
            array_filter(
                array_map(
                    "sanitize_text_field",
                    (array) ($lmd_seo_sale_entry["categories"] ?? []),
                ),
            ),
        ),
    ];

    if (in_array($lmd_seo_sale_entry_id, $lmd_seo_excluded_sale_ids, true)) {
        $lmd_seo_sale_calendar_month = substr($lmd_seo_sale_entry_date, 0, 7);
    }
}

$run_notice_class = "lmd-app-feedback lmd-app-feedback--info";
if (!empty($lmd_seo_run_result)) {
    if (!empty($lmd_seo_run_result["success"])) {
        $run_notice_class = "lmd-app-feedback lmd-app-feedback--success";
    } elseif (!empty($lmd_seo_run_result["skipped"])) {
        $run_notice_class = "lmd-app-feedback lmd-app-feedback--warning";
    } else {
        $run_notice_class = "lmd-app-feedback lmd-app-feedback--error";
    }
}
$purge_notice_class = "lmd-app-feedback lmd-app-feedback--info";
if (!empty($lmd_seo_purge_result)) {
    if (!empty($lmd_seo_purge_result["warning"])) {
        $purge_notice_class = "lmd-app-feedback lmd-app-feedback--warning";
    } elseif (!empty($lmd_seo_purge_result["success"])) {
        $purge_notice_class = "lmd-app-feedback lmd-app-feedback--success";
    } else {
        $purge_notice_class = "lmd-app-feedback lmd-app-feedback--error";
    }
}
$run_stored = is_array($lmd_seo_run_result["stored"] ?? null)
    ? $lmd_seo_run_result["stored"]
    : [];
$run_lot_id = !empty($lmd_seo_run_result["lot_id"])
    ? absint($lmd_seo_run_result["lot_id"])
    : $lmd_seo_test_lot_id;
$run_lot_title = $run_lot_id ? get_the_title($run_lot_id) : "";
$run_edit_link = $run_lot_id ? get_edit_post_link($run_lot_id, "") : "";
$run_schema_json = !empty($run_stored["schema_payload"])
    ? wp_json_encode(
        $run_stored["schema_payload"],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    )
    : "";
?>
<div class="wrap lmd-page lmd-app-shell lmd-app-shell--seo">
    <?php require LMD_PLUGIN_DIR .
        "admin/views/partials/lmd-suite-banner.php"; ?>

    <div class="lmd-app-shell-post-banner lmd-app-shell-post-banner--seo">
        <?php if ($lmd_seo_saved): ?>
        <div class="lmd-app-feedback lmd-app-feedback--success"><p><?php esc_html_e(
            "Réglages SEO enregistrés.",
            "lmd-apps-ia",
        ); ?></p></div>
        <?php endif; ?>

        <p class="lmd-app-shell-desc lmd-app-shell-desc--after-banner">
            <?php esc_html_e(
                "Définissez ici quels lots peuvent être enrichis automatiquement pour le référencement, afin de réserver l'IA aux ventes et objets qui en valent la peine.",
                "lmd-apps-ia",
            ); ?>
        </p>
    </div>

    <div class="lmd-ui-panel">
        <div class="lmd-seo-badge-row">
            <span class="lmd-seo-badge"><?php echo esc_html(
                $site_badge,
            ); ?></span>
            <span class="lmd-seo-badge lmd-seo-badge--soft"><?php echo esc_html(
                $threshold_labels[$selected_mode] ??
                    $threshold_labels["either"],
            ); ?></span>
            <span class="lmd-seo-badge lmd-seo-badge--soft"><?php echo esc_html(
                !empty($lmd_seo_settings["limit_categories"])
                    ? __("Filtre catégories actif", "lmd-apps-ia")
                    : __("Toutes catégories de vente", "lmd-apps-ia"),
            ); ?></span>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field("lmd_save_seo_settings"); ?>
        <input type="hidden" name="seo_month" value="<?php echo esc_attr(
            $lmd_seo_stats_month,
        ); ?>" />

        <div class="lmd-ui-panel">
            <h2 class="lmd-ui-section-title"><?php esc_html_e(
                "Activation du service SEO",
                "lmd-apps-ia",
            ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e(
                            "Activation",
                            "lmd-apps-ia",
                        ); ?></th>
                        <td>
                            <div class="form-check form-switch lmd-seo-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="lmd-seo-enabled" name="enabled" value="1" <?php checked(
                                    !empty($lmd_seo_settings["enabled"]),
                                ); ?> />
                                <label class="form-check-label lmd-seo-switch-copy" for="lmd-seo-enabled">
                                    <span class="lmd-seo-switch-title"><?php esc_html_e(
                                        "Activer l'enrichissement SEO des lots sur ce site",
                                        "lmd-apps-ia",
                                    ); ?></span>
                                    <span class="lmd-seo-switch-help"><?php esc_html_e(
                                        "Si cette option est désactivée, aucun lot ne sera enrichi en SEO.",
                                        "lmd-apps-ia",
                                    ); ?></span>
                                </label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e(
                            "Contenus à générer",
                            "lmd-apps-ia",
                        ); ?></th>
                        <td>
                            <div class="lmd-seo-output-list">
                                <div class="lmd-seo-output-option">
                                    <label class="lmd-seo-output-head" for="lmd-seo-output-title">
                                        <input id="lmd-seo-output-title" type="checkbox" name="outputs[title]" value="1" <?php checked(
                                            !empty(
                                                $lmd_seo_settings["outputs"][
                                                    "title"
                                                ]
                                            ),
                                        ); ?> />
                                        <span class="lmd-seo-output-title"><?php esc_html_e(
                                            "SEO title",
                                            "lmd-apps-ia",
                                        ); ?></span>
                                    </label>
                                    <p class="lmd-seo-output-help"><?php esc_html_e(
                                        "Le titre principal du lot affiché dans Google et dans l'onglet du navigateur.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </div>
                                <div class="lmd-seo-output-option">
                                    <label class="lmd-seo-output-head" for="lmd-seo-output-description">
                                        <input id="lmd-seo-output-description" type="checkbox" name="outputs[description]" value="1" <?php checked(
                                            !empty(
                                                $lmd_seo_settings["outputs"][
                                                    "description"
                                                ]
                                            ),
                                        ); ?> />
                                        <span class="lmd-seo-output-title"><?php esc_html_e(
                                            "Meta description",
                                            "lmd-apps-ia",
                                        ); ?></span>
                                    </label>
                                    <p class="lmd-seo-output-help"><?php esc_html_e(
                                        "Le court texte de présentation qui peut apparaître sous le titre dans les résultats de recherche.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </div>
                                <div class="lmd-seo-output-option">
                                    <label class="lmd-seo-output-head" for="lmd-seo-output-alts">
                                        <input id="lmd-seo-output-alts" type="checkbox" name="outputs[alts]" value="1" <?php checked(
                                            !empty(
                                                $lmd_seo_settings["outputs"][
                                                    "alts"
                                                ]
                                            ),
                                        ); ?> />
                                        <span class="lmd-seo-output-title"><?php esc_html_e(
                                            "Alts des images",
                                            "lmd-apps-ia",
                                        ); ?></span>
                                    </label>
                                    <p class="lmd-seo-output-help"><?php esc_html_e(
                                        "La description textuelle des photos, utile pour l'accessibilité et pour aider Google à comprendre l'image.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </div>
                                <div class="lmd-seo-output-option">
                                    <label class="lmd-seo-output-head" for="lmd-seo-output-schema">
                                        <input id="lmd-seo-output-schema" type="checkbox" name="outputs[schema]" value="1" <?php checked(
                                            !empty(
                                                $lmd_seo_settings["outputs"][
                                                    "schema"
                                                ]
                                            ),
                                        ); ?> />
                                        <span class="lmd-seo-output-title"><?php esc_html_e(
                                            "Schéma JSON-LD",
                                            "lmd-apps-ia",
                                        ); ?></span>
                                    </label>
                                    <p class="lmd-seo-output-help"><?php esc_html_e(
                                        "Les informations techniques lues par les moteurs de recherche pour mieux comprendre la fiche lot.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e(
                                "Les textes seront stockés directement sur chaque lot pour pouvoir être réutilisés dans le site.",
                                "lmd-apps-ia",
                            ); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="lmd-ui-panel">
            <div class="lmd-seo-batch-header lmd-seo-stats-header">
                <div>
                    <h2 class="lmd-ui-section-title"><?php esc_html_e(
                        "Statistiques",
                        "lmd-apps-ia",
                    ); ?></h2>
                    <p class="lmd-ui-prose"><?php esc_html_e(
                        "Aperçu global des lots du mois sélectionné, avec les réglages SEO actuellement enregistrés sur ce site.",
                        "lmd-apps-ia",
                    ); ?></p>
                </div>
                <div class="lmd-seo-stats-toolbar" data-seo-stats-base-url="<?php echo esc_url(
                    admin_url("admin.php?page=lmd-app-seo"),
                ); ?>">
                    <label for="lmd-seo-stats-month"><?php esc_html_e(
                        "Mois",
                        "lmd-apps-ia",
                    ); ?></label>
                    <select id="lmd-seo-stats-month">
                        <?php foreach (
                            $lmd_seo_month_choices
                            as $month_value => $month_label
                        ): ?>
                        <option value="<?php echo esc_attr(
                            sprintf("%02d", (int) $month_value),
                        ); ?>" <?php selected(
    $lmd_seo_stats_month_num,
    (int) $month_value,
); ?>><?php echo esc_html($month_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="lmd-seo-stats-year" class="screen-reader-text"><?php esc_html_e(
                        "Année",
                        "lmd-apps-ia",
                    ); ?></label>
                    <select id="lmd-seo-stats-year">
                        <?php foreach ($lmd_seo_year_choices as $year_value): ?>
                        <option value="<?php echo esc_attr(
                            (string) (int) $year_value,
                        ); ?>" <?php selected(
    $lmd_seo_stats_year,
    (int) $year_value,
); ?>><?php echo esc_html((string) (int) $year_value); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" data-seo-stats-apply><?php esc_html_e(
                        "Afficher",
                        "lmd-apps-ia",
                    ); ?></button>
                </div>
            </div>
            <p class="description lmd-seo-stats-period"><?php echo esc_html(
                sprintf(
                    __(
                        'Période analysée : %1$s · %2$d vente(s)',
                        "lmd-apps-ia",
                    ),
                    (string) ($lmd_seo_month_stats["label"] ??
                        $lmd_seo_stats_month),
                    (int) ($lmd_seo_month_stats["sales"] ?? 0),
                ),
            ); ?></p>
            <div class="lmd-seo-overview-grid lmd-seo-batch-stats lmd-seo-month-stats">
                <div class="lmd-seo-overview-card">
                    <span class="lmd-seo-card-kicker"><?php esc_html_e(
                        "Lots analysés",
                        "lmd-apps-ia",
                    ); ?></span>
                    <div class="lmd-seo-card-title"><?php echo esc_html(
                        number_format_i18n(
                            (int) ($lmd_seo_month_stats["analysed"] ?? 0),
                        ),
                    ); ?></div>
                </div>
                <div class="lmd-seo-overview-card">
                    <span class="lmd-seo-card-kicker"><?php esc_html_e(
                        "Lots boostés en SEO",
                        "lmd-apps-ia",
                    ); ?></span>
                    <div class="lmd-seo-card-title"><?php echo esc_html(
                        number_format_i18n(
                            (int) ($lmd_seo_month_stats["boosted"] ?? 0),
                        ),
                    ); ?></div>
                </div>
                <div class="lmd-seo-overview-card">
                    <span class="lmd-seo-card-kicker"><?php esc_html_e(
                        "Lots exclus par vos filtres",
                        "lmd-apps-ia",
                    ); ?></span>
                    <div class="lmd-seo-card-title"><?php echo esc_html(
                        number_format_i18n(
                            (int) ($lmd_seo_month_stats["ignored"] ?? 0),
                        ),
                    ); ?></div>
                </div>
            </div>
        </div>

        <div class="lmd-ui-panel">
            <h2 class="lmd-ui-section-title"><?php esc_html_e(
                "Filtres et éligibilité des lots",
                "lmd-apps-ia",
            ); ?></h2>
            <div class="lmd-seo-filter-columns">
                <div class="lmd-seo-filter-column">
                    <div class="lmd-seo-sale-filter-head">
                        <h3 class="lmd-ui-subsection"><?php esc_html_e(
                            "Définition des seuils et filtrage par type de vente",
                            "lmd-apps-ia",
                        ); ?></h3>
                        <p class="description"><?php esc_html_e(
                            "Définissez les lots qui doivent bénéficier d’un enrichissement SEO en fonction de vos estimations et du type de vente.",
                            "lmd-apps-ia",
                        ); ?></p>
                    </div>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e(
                                    "Critère d'estimation",
                                    "lmd-apps-ia",
                                ); ?></th>
                                <td>
                                    <select name="estimate_gate[mode]">
                                        <?php foreach (
                                            $threshold_labels
                                            as $mode => $label
                                        ): ?>
                                        <option value="<?php echo esc_attr(
                                            $mode,
                                        ); ?>" <?php selected(
    $selected_mode,
    $mode,
); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e(
                                        "Choisissez quelle estimation sert de base au filtre.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e(
                                    "Seuil mini estimation basse",
                                    "lmd-apps-ia",
                                ); ?></th>
                                <td>
                                    <input type="text" class="regular-text" inputmode="decimal" name="estimate_gate[low_min]" value="<?php echo esc_attr(
                                        (string) ($lmd_seo_settings[
                                            "estimate_gate"
                                        ]["low_min"] ?? ""),
                                    ); ?>" />
                                    <p class="description"><?php esc_html_e(
                                        "Laissez vide si vous ne souhaitez pas filtrer sur l'estimation basse.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e(
                                    "Seuil mini estimation haute",
                                    "lmd-apps-ia",
                                ); ?></th>
                                <td>
                                    <input type="text" class="regular-text" inputmode="decimal" name="estimate_gate[high_min]" value="<?php echo esc_attr(
                                        (string) ($lmd_seo_settings[
                                            "estimate_gate"
                                        ]["high_min"] ?? ""),
                                    ); ?>" />
                                    <p class="description"><?php esc_html_e(
                                        "Laissez vide si vous ne souhaitez pas filtrer sur l'estimation haute.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e(
                                    "Type de vente",
                                    "lmd-apps-ia",
                                ); ?></th>
                                <td>
                                    <div class="lmd-seo-inline-checks">
                                        <label><input type="checkbox" name="sale_types[volontaire]" value="1" <?php checked(
                                            !empty(
                                                $lmd_seo_settings["sale_types"][
                                                    "volontaire"
                                                ]
                                            ),
                                        ); ?> /> <?php esc_html_e(
     "Vente volontaire",
     "lmd-apps-ia",
 ); ?></label>
                                        <label><input type="checkbox" name="sale_types[judiciaire]" value="1" <?php checked(
                                            !empty(
                                                $lmd_seo_settings["sale_types"][
                                                    "judiciaire"
                                                ]
                                            ),
                                        ); ?> /> <?php esc_html_e(
     "Vente judiciaire",
     "lmd-apps-ia",
 ); ?></label>
                                    </div>
                                    <p class="description"><?php esc_html_e(
                                        "Vous pouvez réserver l'enrichissement à certains contextes de vente seulement.",
                                        "lmd-apps-ia",
                                    ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="lmd-seo-filter-column lmd-seo-filter-column--sales" id="lmd-seo-sale-calendar-app" data-default-month="<?php echo esc_attr(
                    $lmd_seo_sale_calendar_month,
                ); ?>">
                    <div class="lmd-seo-sale-filter-head">
                        <h3 class="lmd-ui-subsection"><?php esc_html_e(
                            "Exclure des ventes",
                            "lmd-apps-ia",
                        ); ?></h3>
                        <p class="description"><?php esc_html_e(
                            "Les ventes cochées ici seront entièrement exclues du service SEO, pour les statistiques comme pour les traitements manuels et automatiques.",
                            "lmd-apps-ia",
                        ); ?></p>
                    </div>
                    <?php if (!empty($lmd_seo_sale_calendar_data)): ?>
                    <div class="lmd-seo-sale-calendar-shell">
                        <div class="lmd-seo-sale-month-nav">
                            <button type="button" class="button button-secondary button-small" data-sale-calendar-nav="prev">←</button>
                            <strong class="lmd-seo-sale-month-label" data-sale-calendar-label></strong>
                            <button type="button" class="button button-secondary button-small" data-sale-calendar-nav="next">→</button>
                        </div>
                        <div class="lmd-seo-sale-weekdays">
                            <span class="lmd-seo-sale-weekday"><?php esc_html_e(
                                "Lun",
                                "lmd-apps-ia",
                            ); ?></span>
                            <span class="lmd-seo-sale-weekday"><?php esc_html_e(
                                "Mar",
                                "lmd-apps-ia",
                            ); ?></span>
                            <span class="lmd-seo-sale-weekday"><?php esc_html_e(
                                "Mer",
                                "lmd-apps-ia",
                            ); ?></span>
                            <span class="lmd-seo-sale-weekday"><?php esc_html_e(
                                "Jeu",
                                "lmd-apps-ia",
                            ); ?></span>
                            <span class="lmd-seo-sale-weekday"><?php esc_html_e(
                                "Ven",
                                "lmd-apps-ia",
                            ); ?></span>
                            <span class="lmd-seo-sale-weekday"><?php esc_html_e(
                                "Sam",
                                "lmd-apps-ia",
                            ); ?></span>
                            <span class="lmd-seo-sale-weekday"><?php esc_html_e(
                                "Dim",
                                "lmd-apps-ia",
                            ); ?></span>
                        </div>
                        <div class="lmd-seo-sale-calendar-grid" data-sale-calendar-grid></div>
                    </div>
                    <div class="lmd-seo-sale-day-box">
                        <p class="lmd-seo-sale-day-label" data-sale-day-label><?php esc_html_e(
                            "Sélectionnez une journée pour afficher les ventes correspondantes.",
                            "lmd-apps-ia",
                        ); ?></p>
                        <div class="lmd-seo-sale-list" data-sale-day-list></div>
                        <p class="description lmd-seo-sale-summary" data-sale-selection-summary></p>
                    </div>
                    <div class="lmd-seo-sale-hidden-inputs" data-sale-hidden-inputs>
                        <?php foreach (
                            $lmd_seo_excluded_sale_ids
                            as $sale_id
                        ): ?>
                        <input type="hidden" name="excluded_sales[]" value="<?php echo esc_attr(
                            (string) $sale_id,
                        ); ?>" />
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="lmd-seo-callout lmd-seo-callout--neutral">
                        <?php esc_html_e(
                            "Aucune vente CPT datée n'a été trouvée sur ce site pour le moment.",
                            "lmd-apps-ia",
                        ); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lmd-ui-panel">
            <h2 class="lmd-ui-section-title"><?php esc_html_e(
                "Filtrer par catégorie de vente",
                "lmd-apps-ia",
            ); ?></h2>
            <?php if ($categories_available): ?>
            <div class="form-check form-switch lmd-seo-switch lmd-seo-switch--categories">
                <input class="form-check-input" type="checkbox" role="switch" id="lmd-seo-limit-categories" name="limit_categories" value="1" <?php checked(
                    !empty($lmd_seo_settings["limit_categories"]),
                ); ?> />
                <label class="form-check-label lmd-seo-switch-copy" for="lmd-seo-limit-categories">
                    <span class="lmd-seo-switch-title"><?php esc_html_e(
                        "Limiter l'enrichissement à certaines catégories de vente",
                        "lmd-apps-ia",
                    ); ?></span>
                    <span class="lmd-seo-switch-help"><?php esc_html_e(
                        "Quand ce filtre est activé, seuls les lots rattachés aux catégories cochées seront éligibles.",
                        "lmd-apps-ia",
                    ); ?></span>
                </label>
            </div>
            <details class="lmd-seo-accordion">
                <summary><?php esc_html_e(
                    "Afficher les catégories",
                    "lmd-apps-ia",
                ); ?></summary>
                <div class="lmd-seo-accordion-body">
                    <div class="lmd-seo-category-grid">
                        <?php foreach ($lmd_seo_categories as $term): ?>
                        <label class="lmd-seo-category-option">
                            <input type="checkbox" name="allowed_categories[]" value="<?php echo esc_attr(
                                $term->slug,
                            ); ?>" <?php checked(
    in_array($term->slug, $selected_categories, true),
); ?> />
                            <span><?php echo esc_html($term->name); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
            <?php else: ?>
            <div class="lmd-seo-callout lmd-seo-callout--neutral">
                <?php esc_html_e(
                    "Les catégories de vente ne sont pas encore détectées sur ce site. Ce filtre s'activera automatiquement dès qu'elles seront disponibles.",
                    "lmd-apps-ia",
                ); ?>
            </div>
            <?php endif; ?>
        </div>

    <div class="lmd-ui-panel" id="lmd-seo-force-app">
        <h2 class="lmd-ui-section-title"><?php esc_html_e(
            "Forcer l'enrichissement SEO d'un lot",
            "lmd-apps-ia",
        ); ?></h2>
        <p class="lmd-ui-prose">
            <?php esc_html_e(
                "Choisissez d'abord une vente, puis recherchez un lot par son numéro pour lancer un enrichissement SEO même s'il est exclu par les filtres actuels.",
                "lmd-apps-ia",
            ); ?>
        </p>
        <?php if (!empty($lmd_seo_sale_calendar_data)): ?>
            <?php wp_nonce_field("lmd_run_seo_enrichment"); ?>
            <input type="hidden" name="lmd_seo_test_lot_id" value="<?php echo esc_attr(
                (string) $lmd_seo_test_lot_id,
            ); ?>" data-force-selected-lot-id />
            <input type="hidden" name="lmd_seo_test_force" value="1" />
            <input type="hidden" name="lmd_seo_forced_sale_id" value="<?php echo esc_attr(
                (string) $lmd_seo_forced_sale_id,
            ); ?>" data-force-selected-sale-id />
            <input type="hidden" name="lmd_seo_forced_sale_label" value="<?php echo esc_attr(
                $lmd_seo_forced_sale_label,
            ); ?>" data-force-selected-sale-label />
            <input type="hidden" name="lmd_seo_forced_lot_number" value="<?php echo esc_attr(
                $lmd_seo_forced_lot_number,
            ); ?>" data-force-selected-lot-number-hidden />

            <div class="lmd-seo-test-grid lmd-seo-force-grid">
                <div class="lmd-seo-preview-card">
                    <div class="lmd-seo-force-field" data-force-sale-autocomplete>
                        <label class="lmd-seo-force-label" for="lmd-seo-force-sale-search"><?php esc_html_e(
                            "Choisir une vente",
                            "lmd-apps-ia",
                        ); ?></label>
                        <input type="text" class="regular-text lmd-seo-force-input" id="lmd-seo-force-sale-search" value="<?php echo esc_attr(
                            $lmd_seo_forced_sale_label,
                        ); ?>" autocomplete="off" placeholder="<?php echo esc_attr__(
    "Nom de vente ou date",
    "lmd-apps-ia",
); ?>" data-force-sale-search />
                        <p class="description"><?php esc_html_e(
                            "Recherchez une vente par son nom ou sa date, puis choisissez-la dans la liste proposée.",
                            "lmd-apps-ia",
                        ); ?></p>
                        <div class="lmd-seo-force-results" data-force-sale-results hidden></div>
                        <p class="lmd-seo-force-selected" data-force-sale-selected><?php echo esc_html(
                            $lmd_seo_forced_sale_label !== ""
                                ? sprintf(
                                    __(
                                        "Vente sélectionnée : %s",
                                        "lmd-apps-ia",
                                    ),
                                    $lmd_seo_forced_sale_label,
                                )
                                : __(
                                    "Aucune vente sélectionnée pour le moment.",
                                    "lmd-apps-ia",
                                ),
                        ); ?></p>
                    </div>

                    <div class="lmd-seo-force-field">
                        <label class="lmd-seo-force-label" for="lmd-seo-force-lot-number"><?php esc_html_e(
                            "Numéro de lot",
                            "lmd-apps-ia",
                        ); ?></label>
                        <input type="text" class="regular-text lmd-seo-force-input" id="lmd-seo-force-lot-number" value="<?php echo esc_attr(
                            $lmd_seo_forced_lot_number,
                        ); ?>" placeholder="<?php echo esc_attr__(
    "Ex. 23 ou 23 bis",
    "lmd-apps-ia",
); ?>" data-force-lot-number />
                        <p class="description"><?php esc_html_e(
                            "Saisissez le numéro tel qu'il apparaît dans Interenchères ou dans le XML de la vente.",
                            "lmd-apps-ia",
                        ); ?></p>
                    </div>

                    <div class="lmd-seo-force-actions">
                        <button type="button" class="button" data-force-lot-preview><?php esc_html_e(
                            "Afficher le lot",
                            "lmd-apps-ia",
                        ); ?></button>
                        <button type="submit" name="lmd_run_seo_enrichment" value="1" class="button button-primary" data-force-lot-submit disabled><?php esc_html_e(
                            "Lancer l'enrichissement forcé",
                            "lmd-apps-ia",
                        ); ?></button>
                    </div>
                    <p class="description lmd-seo-force-note"><?php esc_html_e(
                        "Le forçage prend le pas sur tous les filtres d'éligibilité du service SEO.",
                        "lmd-apps-ia",
                    ); ?></p>
                </div>

                <div class="lmd-seo-preview-card">
                    <span class="lmd-seo-card-kicker"><?php esc_html_e(
                        "Lot sélectionné",
                        "lmd-apps-ia",
                    ); ?></span>
                    <div class="lmd-seo-force-preview" data-force-lot-preview-card>
                        <p class="lmd-seo-preview-empty"><?php esc_html_e(
                            "Choisissez d'abord une vente puis un numéro de lot pour vérifier que vous forcez bien le bon objet.",
                            "lmd-apps-ia",
                        ); ?></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
        <div class="lmd-seo-callout lmd-seo-callout--neutral">
            <?php esc_html_e(
                "Aucune vente CPT n'est disponible sur ce site pour le moment. Le forçage d'un lot sera possible dès qu'une vente importée sera présente.",
                "lmd-apps-ia",
            ); ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($lmd_seo_run_result)): ?>
        <div class="lmd-seo-force-run-result">
            <div class="<?php echo esc_attr($run_notice_class); ?>">
                <p><?php echo esc_html((string) ($lmd_seo_run_result["message"] ?? "")); ?></p>
            </div>
            <?php if (!empty($run_stored)): ?>
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Aperçu du résultat généré", "lmd-apps-ia"); ?></span>
                <?php if ($run_lot_title !== ""): ?><p class="lmd-seo-preview-value"><strong><?php esc_html_e("Lot :", "lmd-apps-ia"); ?></strong> <?php echo esc_html($run_lot_title); ?></p><?php endif; ?>
                <?php if (!empty($run_stored["title"])): ?><p class="lmd-seo-preview-value"><strong><?php esc_html_e("SEO title :", "lmd-apps-ia"); ?></strong> <?php echo esc_html((string) $run_stored["title"]); ?></p><?php endif; ?>
                <?php if (!empty($run_stored["description"])): ?><p class="lmd-seo-preview-value"><strong><?php esc_html_e("Meta description :", "lmd-apps-ia"); ?></strong> <?php echo esc_html((string) $run_stored["description"]); ?></p><?php endif; ?>
                <?php if (!empty($run_stored["canonical_label"])): ?><p class="lmd-seo-preview-value"><strong><?php esc_html_e("Label canonique :", "lmd-apps-ia"); ?></strong> <?php echo esc_html((string) $run_stored["canonical_label"]); ?></p><?php endif; ?>
                <?php if (!empty($run_stored["alt_base"])): ?><p class="lmd-seo-preview-value"><strong><?php esc_html_e("Alt d'image principal :", "lmd-apps-ia"); ?></strong> <?php echo esc_html((string) $run_stored["alt_base"]); ?></p><?php endif; ?>
                <?php if ($run_edit_link): ?><p class="lmd-seo-force-result-link"><a class="button button-secondary" href="<?php echo esc_url($run_edit_link); ?>"><?php esc_html_e("Ouvrir le lot", "lmd-apps-ia"); ?></a></p><?php endif; ?>
                <?php if ($run_schema_json !== ""): ?><pre class="lmd-seo-preview-pre"><?php echo esc_html($run_schema_json); ?></pre><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <p class="submit">
        <button class="button button-primary" type="submit" name="lmd_save_seo_settings" value="1"><?php esc_html_e(
            "Enregistrer les réglages SEO",
            "lmd-apps-ia",
        ); ?></button>
    </p>
    </form>

    <?php if (current_user_can("manage_options")): ?>
    <?php
    $batch_total = (int) ($lmd_seo_batch_state["total"] ?? 0);
    $batch_processed = (int) ($lmd_seo_batch_state["processed"] ?? 0);
    $batch_remaining = max($batch_total - $batch_processed, 0);
    $batch_status = sanitize_key((string) ($lmd_seo_batch_state["status"] ?? "idle"));
    $batch_status_label_map = [
        "idle" => __("Inactif", "lmd-apps-ia"),
        "ready" => __("Prêt", "lmd-apps-ia"),
        "running" => __("En cours", "lmd-apps-ia"),
        "paused" => __("En pause", "lmd-apps-ia"),
        "completed" => __("Terminé", "lmd-apps-ia"),
        "scheduled" => __("Planifié", "lmd-apps-ia"),
    ];
    $batch_status_label = $batch_status_label_map[$batch_status] ?? ucfirst($batch_status);
    $auto_status = sanitize_key((string) ($lmd_seo_auto_queue_state["status"] ?? "idle"));
    $auto_status_label = $batch_status_label_map[$auto_status] ?? ucfirst($auto_status);
    $auto_next_run_ts = (int) ($lmd_seo_auto_queue_state["next_run_ts"] ?? 0);
    $auto_next_run_label = $auto_next_run_ts > 0
        ? wp_date("d/m/Y H:i", $auto_next_run_ts, wp_timezone())
        : __("Aucune planification", "lmd-apps-ia");
    ?>
    <div class="lmd-ui-panel" id="lmd-seo-batch-app">
        <div class="lmd-seo-batch-header">
            <div>
                <h2 class="lmd-ui-section-title"><?php esc_html_e(
                    "Traitement par lot",
                    "lmd-apps-ia",
                ); ?></h2>
                <p class="lmd-ui-prose"><?php esc_html_e(
                    "Préparez une file de lots éligibles, puis lancez un traitement progressif par petits paquets. Chaque lot utilise au maximum 3 images pour limiter le temps d'exécution et la consommation IA.",
                    "lmd-apps-ia",
                ); ?></p>
            </div>
            <div class="lmd-seo-batch-actions">
                <button type="button" class="button" data-batch-action="prepare"><?php esc_html_e(
                    "Préparer la file",
                    "lmd-apps-ia",
                ); ?></button>
                <button type="button" class="button button-primary" data-batch-action="resume"><?php esc_html_e(
                    "Lancer le batch",
                    "lmd-apps-ia",
                ); ?></button>
                <button type="button" class="button" data-batch-action="pause"><?php esc_html_e(
                    "Mettre en pause",
                    "lmd-apps-ia",
                ); ?></button>
                <button type="button" class="button" data-batch-action="refresh"><?php esc_html_e(
                    "Actualiser",
                    "lmd-apps-ia",
                ); ?></button>
            </div>
        </div>

        <div id="lmd-seo-batch-feedback" class="lmd-app-feedback lmd-app-feedback--info" hidden><p></p></div>

        <div class="lmd-seo-batch-progress">
            <div class="lmd-seo-batch-track"><span id="lmd-seo-batch-fill"></span></div>
            <div class="lmd-seo-batch-progress-meta">
                <span data-batch-text="status_label"><?php echo esc_html($batch_status_label); ?></span>
                <span data-batch-text="progress_label"><?php echo esc_html(sprintf(__('%1$d / %2$d lot(s)', "lmd-apps-ia"), $batch_processed, $batch_total)); ?></span>
            </div>
        </div>

        <div class="lmd-seo-overview-grid lmd-seo-batch-stats">
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Lots scannés", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title" data-batch-number="scanned"><?php echo esc_html((string) ((int) ($lmd_seo_batch_state["scanned"] ?? 0))); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("En attente", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title" data-batch-number="remaining"><?php echo esc_html((string) $batch_remaining); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Traités", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title" data-batch-number="processed"><?php echo esc_html((string) $batch_processed); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Succès", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title" data-batch-number="success"><?php echo esc_html((string) ((int) ($lmd_seo_batch_state["success"] ?? 0))); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Erreurs", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title" data-batch-number="errors"><?php echo esc_html((string) ((int) ($lmd_seo_batch_state["errors"] ?? 0))); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Déjà à jour", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title" data-batch-number="up_to_date"><?php echo esc_html((string) (((int) ($lmd_seo_batch_state["up_to_date"] ?? 0)) + ((int) ($lmd_seo_batch_state["cached"] ?? 0)))); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Non éligibles", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title" data-batch-number="ineligible"><?php echo esc_html((string) ((int) ($lmd_seo_batch_state["ineligible"] ?? 0))); ?></p></div>
        </div>

        <p class="description lmd-seo-batch-last" data-batch-text="last_message"><?php echo esc_html((string) ($lmd_seo_batch_state["last_message"] ?? __("Aucune file préparée pour le moment.", "lmd-apps-ia"))); ?></p>
    </div>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e(
            "File automatique",
            "lmd-apps-ia",
        ); ?></h2>
        <p class="lmd-ui-prose"><?php esc_html_e(
            "Après un import réussi via Passerelle LMD, les lots éligibles sont ajoutés à une file différée. Le traitement automatique est lancé plus tard, sans alourdir le flux d'import.",
            "lmd-apps-ia",
        ); ?></p>
        <div class="lmd-seo-overview-grid">
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Statut", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title"><?php echo esc_html($auto_status_label); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Lots en attente", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-title"><?php echo esc_html((string) ((int) ($lmd_seo_auto_queue_state["pending"] ?? 0))); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Prochain lancement", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-copy"><?php echo esc_html($auto_next_run_label); ?></p></div>
            <div class="lmd-seo-overview-card"><span class="lmd-seo-card-kicker"><?php esc_html_e("Dernier message", "lmd-apps-ia"); ?></span><p class="lmd-seo-card-copy"><?php echo esc_html((string) ($lmd_seo_auto_queue_state["last_message"] ?? __("Aucune activité enregistrée pour le moment.", "lmd-apps-ia"))); ?></p></div>
        </div>
    </div>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e(
            "Purge et réinitialisation",
            "lmd-apps-ia",
        ); ?></h2>
        <p class="lmd-ui-prose"><?php esc_html_e(
            "Supprimez les contenus SEO générés pour pouvoir relancer des tests après modification des réglages ou du prompt. Les données éditoriales du lot ne sont pas touchées.",
            "lmd-apps-ia",
        ); ?></p>
        <?php if (!empty($lmd_seo_purge_result)): ?>
        <div class="<?php echo esc_attr($purge_notice_class); ?>"><p><?php echo esc_html((string) ($lmd_seo_purge_result["message"] ?? "")); ?></p></div>
        <?php endif; ?>
        <div class="lmd-seo-test-grid">
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Purge ciblée", "lmd-apps-ia"); ?></span>
                <form method="post" action="">
                    <?php wp_nonce_field("lmd_purge_seo_lot"); ?>
                    <input type="hidden" name="seo_month" value="<?php echo esc_attr($lmd_seo_stats_month); ?>" />
                    <p><label for="lmd-seo-purge-lot-id"><?php esc_html_e("ID du lot", "lmd-apps-ia"); ?></label></p>
                    <input type="text" class="regular-text" id="lmd-seo-purge-lot-id" name="lmd_seo_purge_lot_id" value="<?php echo esc_attr((string) $lmd_seo_purge_lot_id); ?>" />
                    <p class="description"><?php esc_html_e("Supprime uniquement les données SEO générées pour ce lot.", "lmd-apps-ia"); ?></p>
                    <p class="submit"><button class="button" type="submit" name="lmd_purge_seo_lot" value="1"><?php esc_html_e("Purger ce lot", "lmd-apps-ia"); ?></button></p>
                </form>
            </div>
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Purge globale", "lmd-apps-ia"); ?></span>
                <form method="post" action="">
                    <?php wp_nonce_field("lmd_purge_seo_all"); ?>
                    <input type="hidden" name="seo_month" value="<?php echo esc_attr($lmd_seo_stats_month); ?>" />
                    <p><label><input type="checkbox" name="lmd_seo_purge_confirm_all" value="1" /> <?php esc_html_e("Je confirme la purge de tous les enrichissements SEO du site.", "lmd-apps-ia"); ?></label></p>
                    <p class="description"><?php esc_html_e("Cette action remet à zéro les données SEO générées sur tous les lots du site courant.", "lmd-apps-ia"); ?></p>
                    <p class="submit"><button class="button button-secondary" type="submit" name="lmd_purge_seo_all" value="1"><?php esc_html_e("Tout purger sur ce site", "lmd-apps-ia"); ?></button></p>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

<script>
jQuery(function ($) {
    const batchRoot = $("#lmd-seo-batch-app");
    const statsToolbar = $(".lmd-seo-stats-toolbar");
    const statsMonthInput = $("#lmd-seo-stats-month");
    const statsYearInput = $("#lmd-seo-stats-year");

    $(document).on("click", "[data-seo-stats-apply]", function () {
        if (!statsToolbar.length || !statsMonthInput.length || !statsYearInput.length) {
            return;
        }

        const baseUrl = statsToolbar.data("seo-stats-base-url");
        const month = String(statsMonthInput.val() || "").trim();
        const year = String(statsYearInput.val() || "").trim();
        if (!baseUrl || !month || !year) {
            return;
        }

        const separator = baseUrl.indexOf("?") === -1 ? "?" : "&";
        window.location.href = baseUrl + separator + "seo_month=" + encodeURIComponent(year + "-" + month);
    });
    const saleCalendarRoot = $("#lmd-seo-sale-calendar-app");
    const saleCalendarEntries = <?php echo wp_json_encode(
        $lmd_seo_sale_calendar_data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ); ?> || [];
    const selectedSaleIds = new Set((<?php echo wp_json_encode(
        array_values($lmd_seo_excluded_sale_ids),
    ); ?> || []).map(function (value) {
        return parseInt(value, 10);
    }).filter(Boolean));
    const saleMonthNames = ["janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"];
    const salesByDate = Object.create(null);

    saleCalendarEntries.forEach(function (entry) {
        if (!entry || !entry.date) {
            return;
        }
        if (!salesByDate[entry.date]) {
            salesByDate[entry.date] = [];
        }
        salesByDate[entry.date].push(entry);
    });

    const saleCalendarGrid = saleCalendarRoot.find("[data-sale-calendar-grid]");
    const saleCalendarLabel = saleCalendarRoot.find("[data-sale-calendar-label]");
    const saleDayLabel = saleCalendarRoot.find("[data-sale-day-label]");
    const saleDayList = saleCalendarRoot.find("[data-sale-day-list]");
    const saleSummary = saleCalendarRoot.find("[data-sale-selection-summary]");
    const saleHiddenInputs = saleCalendarRoot.find("[data-sale-hidden-inputs]");
    let currentSaleMonth = String(saleCalendarRoot.data("default-month") || "").trim();
    let selectedSaleDate = "";

    function normalizeSaleMonth(value) {
        return /^\d{4}-\d{2}$/.test(String(value || "")) ? String(value) : "";
    }

    function getCurrentSaleMonth() {
        const now = new Date();
        return now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");
    }

    function parseSaleMonth(monthKey) {
        const parts = String(monthKey || "").split("-");
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
    }

    function formatSaleMonth(date) {
        return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, "0");
    }

    function getSaleDatesForMonth(monthKey) {
        return Object.keys(salesByDate).filter(function (dateKey) {
            return dateKey.slice(0, 7) === monthKey;
        }).sort();
    }

    function pickDefaultSaleDate(monthKey) {
        const dates = getSaleDatesForMonth(monthKey);
        if (!dates.length) {
            return "";
        }

        for (let i = 0; i < dates.length; i++) {
            const daySales = salesByDate[dates[i]] || [];
            if (daySales.some(function (sale) { return selectedSaleIds.has(parseInt(sale.id, 10)); })) {
                return dates[i];
            }
        }

        return dates[0];
    }

    function ensureSelectedSaleDate() {
        const dates = getSaleDatesForMonth(currentSaleMonth);
        if (!dates.length) {
            selectedSaleDate = "";
            return;
        }

        if (!selectedSaleDate || selectedSaleDate.slice(0, 7) !== currentSaleMonth || dates.indexOf(selectedSaleDate) === -1) {
            selectedSaleDate = pickDefaultSaleDate(currentSaleMonth);
        }
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatSaleDateLabel(dateKey) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateKey || ""))) {
            return "";
        }

        const parts = dateKey.split("-");
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const day = parseInt(parts[2], 10);
        return day + " " + saleMonthNames[month - 1] + " " + year;
    }

    function renderSaleHiddenInputs() {
        if (!saleHiddenInputs.length) {
            return;
        }

        const values = Array.from(selectedSaleIds).sort(function (left, right) {
            return left - right;
        });

        saleHiddenInputs.html(values.map(function (saleId) {
            return '<input type="hidden" name="excluded_sales[]" value="' + saleId + '" />';
        }).join(""));
    }

    function renderSaleSummary() {
        if (!saleSummary.length) {
            return;
        }

        const count = selectedSaleIds.size;
        saleSummary.text(count > 0
            ? count + " vente(s) actuellement exclue(s) du service SEO."
            : "Aucune vente exclue pour le moment.");
    }

    function renderSaleDayList() {
        if (!saleDayList.length || !saleDayLabel.length) {
            return;
        }

        const daySales = selectedSaleDate ? (salesByDate[selectedSaleDate] || []) : [];
        if (!selectedSaleDate || !daySales.length) {
            saleDayLabel.text(getSaleDatesForMonth(currentSaleMonth).length
                ? "Sélectionnez une journée pour afficher les ventes correspondantes."
                : "Aucune vente CPT n'est disponible sur ce mois.");
            saleDayList.html('<p class="lmd-seo-sale-list-empty">Aucune vente à afficher.</p>');
            return;
        }

        saleDayLabel.text("Ventes du " + formatSaleDateLabel(selectedSaleDate));
        const html = daySales.map(function (sale) {
            const saleId = parseInt(sale.id, 10);
            const metaParts = [];
            if (sale.type) {
                metaParts.push(escapeHtml(sale.type));
            }
            if (Array.isArray(sale.categories) && sale.categories.length) {
                metaParts.push(escapeHtml(sale.categories.join(", ")));
            }

            return [
                '<label class="lmd-seo-sale-list-item">',
                    '<input type="checkbox" data-sale-checkbox value="' + saleId + '" ' + (selectedSaleIds.has(saleId) ? 'checked' : '') + ' />',
                    '<span class="lmd-seo-sale-list-copy">',
                        '<strong class="lmd-seo-sale-list-title">' + escapeHtml(sale.title || ("Vente #" + saleId)) + '</strong>',
                        metaParts.length ? '<span class="lmd-seo-sale-list-meta">' + metaParts.join(' · ') + '</span>' : '',
                    '</span>',
                '</label>'
            ].join('');
        }).join('');

        saleDayList.html(html);
    }

    function renderSaleCalendar() {
        if (!saleCalendarRoot.length || !saleCalendarGrid.length || !saleCalendarLabel.length) {
            return;
        }

        currentSaleMonth = normalizeSaleMonth(currentSaleMonth) || getCurrentSaleMonth();
        ensureSelectedSaleDate();

        const baseDate = parseSaleMonth(currentSaleMonth);
        const monthLabel = saleMonthNames[baseDate.getMonth()];
        saleCalendarLabel.text(monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1) + " " + baseDate.getFullYear());

        const firstDay = new Date(baseDate.getFullYear(), baseDate.getMonth(), 1);
        const daysInMonth = new Date(baseDate.getFullYear(), baseDate.getMonth() + 1, 0).getDate();
        const leading = (firstDay.getDay() + 6) % 7;
        const html = [];

        for (let index = 0; index < leading; index++) {
            html.push('<span class="lmd-seo-sale-day-empty" aria-hidden="true"></span>');
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateKey = currentSaleMonth + '-' + String(day).padStart(2, '0');
            const daySales = salesByDate[dateKey] || [];
            const hasSales = daySales.length > 0;
            const hasExcluded = daySales.some(function (sale) {
                return selectedSaleIds.has(parseInt(sale.id, 10));
            });
            const classes = ['lmd-seo-sale-day'];

            if (hasSales) {
                classes.push('has-sales');
            } else {
                classes.push('is-empty');
            }
            if (selectedSaleDate === dateKey) {
                classes.push('is-selected');
            }
            if (hasExcluded) {
                classes.push('has-excluded');
            }

            html.push('<button type="button" class="' + classes.join(' ') + '" data-sale-date="' + dateKey + '" ' + (hasSales ? '' : 'disabled') + '>' +
                '<span class="lmd-seo-sale-day-number">' + day + '</span>' +
                (hasSales ? '<span class="lmd-seo-sale-day-count">' + daySales.length + '</span>' : '') +
            '</button>');
        }

        saleCalendarGrid.html(html.join(''));
    }

    saleCalendarRoot.on('click', '[data-sale-calendar-nav]', function () {
        const direction = $(this).data('saleCalendarNav');
        const baseDate = parseSaleMonth(normalizeSaleMonth(currentSaleMonth) || getCurrentSaleMonth());
        if (direction === 'prev') {
            baseDate.setMonth(baseDate.getMonth() - 1);
        } else {
            baseDate.setMonth(baseDate.getMonth() + 1);
        }
        currentSaleMonth = formatSaleMonth(baseDate);
        selectedSaleDate = '';
        renderSaleCalendar();
        renderSaleDayList();
    });

    saleCalendarRoot.on('click', '[data-sale-date]', function () {
        const nextDate = String($(this).data('saleDate') || '');
        if (!nextDate) {
            return;
        }
        selectedSaleDate = nextDate;
        renderSaleCalendar();
        renderSaleDayList();
    });

    saleCalendarRoot.on('change', '[data-sale-checkbox]', function () {
        const saleId = parseInt($(this).val(), 10);
        if (!saleId) {
            return;
        }

        if (this.checked) {
            selectedSaleIds.add(saleId);
        } else {
            selectedSaleIds.delete(saleId);
        }

        renderSaleHiddenInputs();
        renderSaleSummary();
        renderSaleCalendar();
        renderSaleDayList();
    });

    if (saleCalendarRoot.length) {
        currentSaleMonth = normalizeSaleMonth(currentSaleMonth) || getCurrentSaleMonth();
        selectedSaleDate = pickDefaultSaleDate(currentSaleMonth);
        renderSaleHiddenInputs();
        renderSaleSummary();
        renderSaleCalendar();
        renderSaleDayList();
    }
    const forceRoot = $("#lmd-seo-force-app");
    const forceSaleSearch = forceRoot.find("[data-force-sale-search]");
    const forceSaleResults = forceRoot.find("[data-force-sale-results]");
    const forceSaleSelectedCopy = forceRoot.find("[data-force-sale-selected]");
    const forceSaleIdInput = forceRoot.find("[data-force-selected-sale-id]");
    const forceSaleLabelInput = forceRoot.find("[data-force-selected-sale-label]");
    const forceLotNumberInput = forceRoot.find("[data-force-lot-number]");
    const forceLotNumberHidden = forceRoot.find("[data-force-selected-lot-number-hidden]");
    const forceLotIdInput = forceRoot.find("[data-force-selected-lot-id]");
    const forceLotPreviewCard = forceRoot.find("[data-force-lot-preview-card]");
    const forceLotSubmit = forceRoot.find("[data-force-lot-submit]");
    const saleLookupById = Object.create(null);
    const today = new Date();
    const todaySaleKey = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, "0") + "-" + String(today.getDate()).padStart(2, "0");

    function compareForceSales(left, right) {
        const leftDate = String(left && left.date ? left.date : "");
        const rightDate = String(right && right.date ? right.date : "");
        const leftUpcoming = leftDate >= todaySaleKey;
        const rightUpcoming = rightDate >= todaySaleKey;

        if (leftUpcoming && !rightUpcoming) {
            return -1;
        }
        if (!leftUpcoming && rightUpcoming) {
            return 1;
        }
        if (leftUpcoming && rightUpcoming) {
            return leftDate.localeCompare(rightDate);
        }
        return rightDate.localeCompare(leftDate);
    }

    const forceSales = saleCalendarEntries.slice().sort(compareForceSales);

    forceSales.forEach(function (entry) {
        if (!entry || !entry.id) {
            return;
        }
        saleLookupById[String(entry.id)] = entry;
    });

    function formatForceSaleLabel(entry) {
        if (!entry) {
            return "";
        }

        const parts = [];
        if (entry.date) {
            parts.push(formatSaleDateLabel(entry.date));
        }
        if (entry.title) {
            parts.push(entry.title);
        }
        if (entry.type) {
            parts.push(entry.type);
        }
        return parts.join(" · ");
    }

    function closeForceSaleResults() {
        if (!forceSaleResults.length) {
            return;
        }
        forceSaleResults.attr("hidden", true).empty();
    }

    function setForcePreviewMessage(message, tone) {
        if (!forceLotPreviewCard.length) {
            return;
        }
        const extraClass = tone ? " lmd-seo-preview-empty--" + tone : "";
        forceLotPreviewCard.html('<p class="lmd-seo-preview-empty' + extraClass + '">' + escapeHtml(message || "") + '</p>');
    }

    function resetForceLotSelection(message, tone) {
        forceLotIdInput.val("");
        forceLotSubmit.prop("disabled", true);
        forceLotNumberHidden.val(String(forceLotNumberInput.val() || "").trim());
        setForcePreviewMessage(message || "Choisissez d'abord une vente puis un numéro de lot pour vérifier que vous forcez bien le bon objet.", tone);
    }

    function setSelectedForceSale(entry) {
        const label = formatForceSaleLabel(entry);
        forceSaleIdInput.val(entry && entry.id ? entry.id : "");
        forceSaleLabelInput.val(label);
        forceSaleSearch.val(label);
        forceSaleSelectedCopy.text(label
            ? "Vente sélectionnée : " + label
            : "Aucune vente sélectionnée pour le moment.");
        closeForceSaleResults();
        resetForceLotSelection("Saisissez maintenant un numéro de lot puis cliquez sur Afficher le lot.");
    }

    function renderForceSaleResults(rawQuery) {
        if (!forceSaleResults.length) {
            return;
        }

        const query = String(rawQuery || "").trim().toLowerCase();
        let matches = forceSales;
        if (query !== "") {
            matches = forceSales.filter(function (entry) {
                const haystack = [entry.title, entry.date, formatForceSaleLabel(entry), entry.type, (entry.categories || []).join(" ")]
                    .join(" ")
                    .toLowerCase();
                return haystack.indexOf(query) !== -1;
            });
        }
        matches = matches.slice(0, 8);

        if (!matches.length) {
            forceSaleResults.html('<p class="lmd-seo-force-result-empty">Aucune vente ne correspond à cette recherche.</p>').removeAttr("hidden");
            return;
        }

        forceSaleResults.html(matches.map(function (entry) {
            return [
                '<button type="button" class="lmd-seo-force-result" data-force-sale-option="1" data-force-sale-id="' + escapeHtml(entry.id) + '">',
                    '<strong>' + escapeHtml(entry.title || ("Vente #" + entry.id)) + '</strong>',
                    '<span>' + escapeHtml(formatForceSaleLabel(entry)) + '</span>',
                '</button>'
            ].join("");
        }).join("")).removeAttr("hidden");
    }

    function renderForceLotPreview(payload) {
        if (!forceLotPreviewCard.length) {
            return;
        }

        const isEligible = !!(payload && payload.eligible);
        const isEnriched = !!(payload && payload.is_enriched);
        const eligibilityBadgeClass = isEligible ? "lmd-seo-badge lmd-seo-badge--soft" : "lmd-seo-badge lmd-seo-badge--warning";
        const eligibilityBadgeText = isEligible ? "Actuellement éligible" : "Actuellement exclu";
        const enrichmentBadgeClass = isEnriched ? "lmd-seo-badge lmd-seo-badge--success-soft" : "lmd-seo-badge lmd-seo-badge--soft";
        const enrichmentBadgeText = isEnriched ? "Déjà enrichi" : "Pas encore enrichi";
        const enrichmentMeta = payload.enriched_at
            ? '<p class="description lmd-seo-force-eligibility">Dernier traitement enregistré : ' + escapeHtml(payload.enriched_at) + '</p>'
            : '';
        const previewHtml = [
            '<div class="lmd-seo-badge-row lmd-seo-badge-row--compact">',
                '<span class="' + eligibilityBadgeClass + '">' + escapeHtml(eligibilityBadgeText) + '</span>',
                '<span class="' + enrichmentBadgeClass + '">' + escapeHtml(enrichmentBadgeText) + '</span>',
            '</div>',
            '<dl class="lmd-seo-force-summary">',
                '<div><dt>Vente</dt><dd>' + escapeHtml(payload.sale_label || "") + '</dd></div>',
                '<div><dt>Lot</dt><dd>' + escapeHtml(payload.lot_number || "") + ' · ' + escapeHtml(payload.lot_title || "") + '</dd></div>',
            '</dl>',
            '<p class="lmd-seo-force-description"><strong>Début de description :</strong> ' + escapeHtml(payload.description_excerpt || "") + '</p>',
            (payload.eligibility_message ? '<p class="description lmd-seo-force-eligibility">' + escapeHtml(payload.eligibility_message) + '</p>' : ''),
            (payload.enrichment_label ? '<p class="description lmd-seo-force-eligibility">' + escapeHtml(payload.enrichment_label) + '</p>' : ''),
            enrichmentMeta
        ].join('');

        forceLotPreviewCard.html(previewHtml);
    }

    function runForceLotLookup() {
        if (!forceRoot.length) {
            return;
        }

        const saleId = parseInt(forceSaleIdInput.val(), 10) || 0;
        const lotNumber = String(forceLotNumberInput.val() || "").trim();
        forceLotNumberHidden.val(lotNumber);

        if (!saleId) {
            resetForceLotSelection("Choisissez d'abord une vente dans la liste proposée.", "warning");
            return;
        }
        if (!lotNumber) {
            resetForceLotSelection("Saisissez un numéro de lot avant de lancer la recherche.", "warning");
            return;
        }

        forceLotSubmit.prop("disabled", true);
        setForcePreviewMessage("Recherche du lot en cours…");

        $.post(lmdAdmin.ajaxurl, {
            action: "lmd_seo_lookup_sale_lot",
            nonce: lmdAdmin.nonce,
            sale_id: saleId,
            lot_number: lotNumber
        }).done(function (response) {
            const payload = response && response.success ? (response.data || {}) : null;
            if (!payload || !payload.lot_id) {
                resetForceLotSelection((payload && payload.message) || "Le lot demandé est introuvable dans cette vente.", "error");
                return;
            }

            if (payload.sale_label) {
                forceSaleLabelInput.val(payload.sale_label);
                forceSaleSearch.val(payload.sale_label);
                forceSaleSelectedCopy.text("Vente sélectionnée : " + payload.sale_label);
            }
            forceLotNumberInput.val(payload.lookup_value || lotNumber);
            forceLotNumberHidden.val(payload.lookup_value || lotNumber);
            forceLotIdInput.val(payload.lot_id);
            forceLotSubmit.prop("disabled", !!payload.is_enriched);
            renderForceLotPreview(payload);
        }).fail(function (xhr) {
            const message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : "Le lot demandé est introuvable dans cette vente.";
            resetForceLotSelection(message, "error");
        });
    }

    forceRoot.on("focus", "[data-force-sale-search]", function () {
        renderForceSaleResults("");
    });

    forceRoot.on("input", "[data-force-sale-search]", function () {
        forceSaleIdInput.val("");
        forceSaleLabelInput.val("");
        forceSaleSelectedCopy.text("Aucune vente sélectionnée pour le moment.");
        renderForceSaleResults($(this).val());
        resetForceLotSelection("Choisissez une vente dans la liste, puis recherchez le lot.");
    });

    forceRoot.on("click", "[data-force-sale-option]", function () {
        const entry = saleLookupById[String($(this).data("forceSaleId") || "")];
        if (!entry) {
            return;
        }
        setSelectedForceSale(entry);
    });

    forceRoot.on("input", "[data-force-lot-number]", function () {
        if (forceLotIdInput.val()) {
            resetForceLotSelection("Le numéro de lot a changé. Cliquez de nouveau sur Afficher le lot pour confirmer votre choix.");
        } else {
            forceLotNumberHidden.val(String($(this).val() || "").trim());
        }
    });

    forceRoot.on("click", "[data-force-lot-preview]", function () {
        runForceLotLookup();
    });

    forceRoot.on("keydown", "[data-force-lot-number]", function (event) {
        if (event.key !== "Enter") {
            return;
        }
        event.preventDefault();
        runForceLotLookup();
    });

    $(document).on("click", function (event) {
        if (!$(event.target).closest("#lmd-seo-force-app [data-force-sale-autocomplete]").length) {
            closeForceSaleResults();
        }
    });

    if (forceRoot.length) {
        const initialSaleId = parseInt(forceSaleIdInput.val(), 10) || 0;
        const initialLotNumber = String(forceLotNumberInput.val() || "").trim();
        if (initialSaleId && saleLookupById[String(initialSaleId)]) {
            const initialEntry = saleLookupById[String(initialSaleId)];
            forceSaleSearch.val(forceSaleLabelInput.val() || formatForceSaleLabel(initialEntry));
            forceSaleSelectedCopy.text("Vente sélectionnée : " + (forceSaleLabelInput.val() || formatForceSaleLabel(initialEntry)));
        }
        if (initialSaleId && initialLotNumber) {
            runForceLotLookup();
        }
    }

    if (!batchRoot.length) {
        return;
    }

    const statusLabels = {
        idle: "Inactif",
        ready: "Prêt",
        running: "En cours",
        paused: "En pause",
        completed: "Terminé"
    };

    const defaults = {
        status: "idle",
        total: 0,
        processed: 0,
        success: 0,
        errors: 0,
        skipped: 0,
        cached: 0,
        scanned: 0,
        eligible: 0,
        ineligible: 0,
        up_to_date: 0,
        last_message: "",
        queue: []
    };

    let state = $.extend({}, defaults, <?php echo wp_json_encode(
        $lmd_seo_batch_state,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ); ?> || {});
    let loopTimer = null;
    let inFlight = false;
    let pendingPause = false;

    function normalizeState(nextState) {
        const merged = $.extend({}, defaults, nextState || {});
        ["total", "processed", "success", "errors", "skipped", "cached", "scanned", "eligible", "ineligible", "up_to_date"].forEach(function (key) {
            merged[key] = parseInt(merged[key] || 0, 10);
        });
        return merged;
    }

    function showFeedback(message, type) {
        const feedback = $("#lmd-seo-batch-feedback");
        if (!message) {
            feedback.attr("hidden", true).removeClass("lmd-app-feedback--success lmd-app-feedback--warning lmd-app-feedback--error lmd-app-feedback--info");
            return;
        }
        feedback.removeAttr("hidden");
        feedback.removeClass("lmd-app-feedback--success lmd-app-feedback--warning lmd-app-feedback--error lmd-app-feedback--info");
        feedback.addClass("lmd-app-feedback--" + (type || "info"));
        feedback.find("p").text(message);
    }

    function setButtonState(selector, enabled, label) {
        const button = batchRoot.find(selector);
        button.prop("disabled", !enabled);
        if (label) {
            button.text(label);
        }
    }

    function renderState() {
        state = normalizeState(state);
        const total = state.total;
        const processed = state.processed;
        const remaining = Math.max(total - processed, 0);
        const percent = total > 0 ? Math.max(0, Math.min(100, Math.round((processed / total) * 100))) : 0;

        $("#lmd-seo-batch-fill").css("width", percent + "%");
        batchRoot.find('[data-batch-text="status_label"]').text(statusLabels[state.status] || state.status || "Inactif");
        batchRoot.find('[data-batch-text="progress_label"]').text(total > 0 ? processed + " / " + total + " lot(s)" : "0 / 0 lot");
        batchRoot.find('[data-batch-text="last_message"]').text(state.last_message || "Aucune file préparée pour le moment.");
        batchRoot.find('[data-batch-number="scanned"]').text(state.scanned);
        batchRoot.find('[data-batch-number="remaining"]').text(remaining);
        batchRoot.find('[data-batch-number="processed"]').text(processed);
        batchRoot.find('[data-batch-number="success"]').text(state.success);
        batchRoot.find('[data-batch-number="errors"]').text(state.errors);
        batchRoot.find('[data-batch-number="up_to_date"]').text(state.up_to_date + state.cached);
        batchRoot.find('[data-batch-number="ineligible"]').text(state.ineligible);

        setButtonState('[data-batch-action="prepare"]', state.status !== "running");
        setButtonState('[data-batch-action="resume"]', total > 0 && state.status !== "running" && !(state.status === "completed" && remaining === 0), state.status === "paused" ? "Reprendre le batch" : "Lancer le batch");
        setButtonState('[data-batch-action="pause"]', state.status === "running");
        setButtonState('[data-batch-action="refresh"]', !inFlight);
    }

    function stopLoop() {
        if (loopTimer) {
            clearTimeout(loopTimer);
            loopTimer = null;
        }
    }

    function scheduleNext(delay) {
        stopLoop();
        if (state.status === "running") {
            loopTimer = setTimeout(processChunk, delay || 600);
        }
    }

    function request(action) {
        return $.post(lmdAdmin.ajaxurl, {
            action: action,
            nonce: lmdAdmin.nonce
        });
    }

    function getAjaxErrorMessage(xhr, fallback) {
        let message = fallback;

        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            message = xhr.responseJSON.data.message;
        } else if (xhr && xhr.responseText) {
            const raw = String(xhr.responseText)
                .replace(/<[^>]*>/g, " ")
                .replace(/\s+/g, " ")
                .trim();
            if (raw) {
                message = raw.length > 220 ? raw.substring(0, 220) + "…" : raw;
            }
        }

        if (xhr && xhr.status) {
            message += " (HTTP " + xhr.status + ")";
        }

        return message;
    }

    function pauseBatchRequest() {
        stopLoop();
        inFlight = true;
        request("lmd_seo_pause_batch")
            .done(function (response) {
                handleResponse(response, "warning");
            })
            .fail(function (xhr) {
                showFeedback(getAjaxErrorMessage(xhr, "Impossible de mettre le batch SEO en pause."), "error");
            })
            .always(function () {
                inFlight = false;
                renderState();
            });
    }

    function handleResponse(response, fallbackType) {
        if (!response || !response.success || !response.data) {
            showFeedback("Réponse AJAX invalide.", "error");
            return null;
        }

        const payload = response.data;
        if (payload.state) {
            state = normalizeState(payload.state);
            renderState();
        }

        const type = payload.success === false
            ? (payload.warning ? "warning" : "error")
            : (fallbackType || (payload.warning ? "warning" : "success"));

        if (payload.message) {
            showFeedback(payload.message, type);
        }

        return payload;
    }

    function processChunk() {
        if (inFlight || state.status !== "running") {
            return;
        }
        inFlight = true;
        request("lmd_seo_process_batch")
            .done(function (response) {
                const payload = handleResponse(response, state.status === "running" ? "info" : "success");
                if (payload && payload.state && payload.state.status === "running") {
                    scheduleNext(600);
                }
            })
            .fail(function (xhr) {
                showFeedback(getAjaxErrorMessage(xhr, "Le traitement batch SEO a échoué côté AJAX."), "error");
                stopLoop();
            })
            .always(function () {
                inFlight = false;
                renderState();
                if (pendingPause) {
                    pendingPause = false;
                    if (state.status === "running") {
                        pauseBatchRequest();
                    }
                }
            });
    }

    batchRoot.on("click", "[data-batch-action]", function () {
        const action = $(this).data("batch-action");
        if (!action) {
            return;
        }

        if (inFlight) {
            if (action === "pause") {
                pendingPause = true;
                showFeedback("Pause demandée. Le batch se mettra en pause à la fin du lot en cours.", "warning");
            } else if (action === "refresh") {
                showFeedback("Un lot est déjà en cours de traitement. L'état sera mis à jour à la fin de cette tranche.", "info");
            }
            return;
        }

        if (action === "prepare") {
            inFlight = true;
            request("lmd_seo_prepare_batch")
                .done(function (response) {
                    handleResponse(response, "success");
                })
                .fail(function () {
                    showFeedback("Impossible de préparer la file SEO.", "error");
                })
                .always(function () {
                    inFlight = false;
                    renderState();
                });
            return;
        }

        if (action === "resume") {
            inFlight = true;
            request("lmd_seo_resume_batch")
                .done(function (response) {
                    const payload = handleResponse(response, "info");
                    if (payload && payload.state && payload.state.status === "running") {
                        scheduleNext(150);
                    }
                })
                .fail(function (xhr) {
                    showFeedback(getAjaxErrorMessage(xhr, "Impossible de lancer le batch SEO."), "error");
                })
                .always(function () {
                    inFlight = false;
                    renderState();
                });
            return;
        }

        if (action === "pause") {
            pendingPause = false;
            pauseBatchRequest();
            return;
        }

        if (action === "refresh") {
            inFlight = true;
            request("lmd_seo_get_batch_state")
                .done(function (response) {
                    handleResponse(response, "info");
                })
                .fail(function (xhr) {
                    showFeedback(getAjaxErrorMessage(xhr, "Impossible de rafraîchir l'état du batch SEO."), "error");
                })
                .always(function () {
                    inFlight = false;
                    renderState();
                });
        }
    });

    renderState();
    if (state.status === "running") {
        showFeedback(state.last_message || "Traitement SEO en cours.", "info");
        scheduleNext(150);
    }
});
</script>
