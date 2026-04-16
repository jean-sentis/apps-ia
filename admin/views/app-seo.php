<?php
/**
 * App SEO - reglages d'eligibilite et test manuel.
 *
 * @var array $lmd_seo_settings
 * @var bool  $lmd_seo_saved
 * @var array $lmd_seo_categories
 * @var array $lmd_seo_run_result
 * @var int   $lmd_seo_test_lot_id
 */
if (!defined("ABSPATH")) {
    exit();
}

$lmd_seo_settings = isset($lmd_seo_settings) && is_array($lmd_seo_settings)
    ? $lmd_seo_settings
    : (function_exists("lmd_get_seo_settings") ? lmd_get_seo_settings() : []);
$lmd_seo_saved = !empty($lmd_seo_saved);
$lmd_seo_categories = isset($lmd_seo_categories) && is_array($lmd_seo_categories)
    ? $lmd_seo_categories
    : (function_exists("lmd_get_seo_sale_category_terms") ? lmd_get_seo_sale_category_terms() : []);
$lmd_seo_run_result = isset($lmd_seo_run_result) && is_array($lmd_seo_run_result)
    ? $lmd_seo_run_result
    : [];
$lmd_seo_purge_result = isset($lmd_seo_purge_result) && is_array($lmd_seo_purge_result)
    ? $lmd_seo_purge_result
    : [];
$lmd_seo_batch_state = isset($lmd_seo_batch_state) && is_array($lmd_seo_batch_state)
    ? $lmd_seo_batch_state
    : [];
$lmd_seo_test_lot_id = isset($lmd_seo_test_lot_id) ? absint($lmd_seo_test_lot_id) : 0;
$lmd_seo_purge_lot_id = isset($lmd_seo_purge_lot_id) ? absint($lmd_seo_purge_lot_id) : $lmd_seo_test_lot_id;

$lmd_suite_banner_title = __("Enrichissement SEO", "lmd-apps-ia");
$lmd_suite_banner_subtitle = __("Reglages des lots a enrichir en SEO.", "lmd-apps-ia");
$site_badge = !is_multisite()
    ? __("Reglages du site", "lmd-apps-ia")
    : (is_main_site() ? __("Reglages du site principal", "lmd-apps-ia") : __("Reglages du site enfant", "lmd-apps-ia"));
$threshold_labels = [
    "low" => __("Estimation basse", "lmd-apps-ia"),
    "high" => __("Estimation haute", "lmd-apps-ia"),
    "either" => __("L'une des deux estimations", "lmd-apps-ia"),
];
$selected_mode = $lmd_seo_settings["estimate_gate"]["mode"] ?? "either";
$selected_categories = array_values(array_filter(array_map("strval", $lmd_seo_settings["allowed_categories"] ?? [])));
$categories_available = !empty($lmd_seo_categories);

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
$run_stored = is_array($lmd_seo_run_result["stored"] ?? null) ? $lmd_seo_run_result["stored"] : [];
$run_context = is_array($lmd_seo_run_result["context"] ?? null) ? $lmd_seo_run_result["context"] : [];
$run_lot_id = !empty($lmd_seo_run_result["lot_id"]) ? absint($lmd_seo_run_result["lot_id"]) : $lmd_seo_test_lot_id;
$run_lot_title = $run_lot_id ? get_the_title($run_lot_id) : "";
$run_edit_link = $run_lot_id ? get_edit_post_link($run_lot_id, "") : "";
$run_schema_json = !empty($run_stored["schema_payload"])
    ? wp_json_encode($run_stored["schema_payload"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : "";
?>
<div class="wrap lmd-page lmd-app-shell lmd-app-shell--seo">
    <?php require LMD_PLUGIN_DIR . "admin/views/partials/lmd-suite-banner.php"; ?>

    <div class="lmd-app-shell-post-banner lmd-app-shell-post-banner--seo">
        <?php if ($lmd_seo_saved) : ?>
        <div class="lmd-app-feedback lmd-app-feedback--success"><p><?php esc_html_e("Reglages SEO enregistres.", "lmd-apps-ia"); ?></p></div>
        <?php endif; ?>

        <p class="lmd-app-shell-desc lmd-app-shell-desc--after-banner">
            Definissez ici quels lots peuvent etre enrichis automatiquement pour le referencement, afin de reserver l'IA aux ventes et objets qui en valent la peine.
        </p>
    </div>

    <div class="lmd-ui-panel">
        <div class="lmd-seo-badge-row">
            <span class="lmd-seo-badge"><?php echo esc_html($site_badge); ?></span>
            <span class="lmd-seo-badge lmd-seo-badge--soft"><?php echo esc_html($threshold_labels[$selected_mode] ?? $threshold_labels["either"]); ?></span>
            <span class="lmd-seo-badge lmd-seo-badge--soft"><?php echo esc_html(!empty($lmd_seo_settings["limit_categories"]) ? __("Filtre categories actif", "lmd-apps-ia") : __("Toutes categories de vente", "lmd-apps-ia")); ?></span>
        </div>
        <p class="lmd-ui-prose" style="margin-bottom:0;">
            La premiere version genere un title SEO, une meta description, des alts d'images et un schema stockes directement sur chaque lot.
        </p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field("lmd_save_seo_settings"); ?>

        <div class="lmd-ui-panel">
            <h2 class="lmd-ui-section-title"><?php esc_html_e("Eligibilite des lots", "lmd-apps-ia"); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e("Activation", "lmd-apps-ia"); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(!empty($lmd_seo_settings["enabled"])); ?> />
                                <?php esc_html_e("Activer l'enrichissement SEO des lots sur ce site", "lmd-apps-ia"); ?>
                            </label>
                            <p class="description"><?php esc_html_e("Si cette option est desactivee, aucun lot ne sera envoye a l'IA.", "lmd-apps-ia"); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e("Critere estimation", "lmd-apps-ia"); ?></th>
                        <td>
                            <select name="estimate_gate[mode]">
                                <?php foreach ($threshold_labels as $mode => $label) : ?>
                                <option value="<?php echo esc_attr($mode); ?>" <?php selected($selected_mode, $mode); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e("Choisissez quelle estimation sert de base au filtre.", "lmd-apps-ia"); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e("Seuil mini estimation basse", "lmd-apps-ia"); ?></th>
                        <td>
                            <input type="text" class="regular-text" inputmode="decimal" name="estimate_gate[low_min]" value="<?php echo esc_attr((string) ($lmd_seo_settings["estimate_gate"]["low_min"] ?? "")); ?>" />
                            <p class="description"><?php esc_html_e("Laissez vide si vous ne souhaitez pas filtrer sur l'estimation basse.", "lmd-apps-ia"); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e("Seuil mini estimation haute", "lmd-apps-ia"); ?></th>
                        <td>
                            <input type="text" class="regular-text" inputmode="decimal" name="estimate_gate[high_min]" value="<?php echo esc_attr((string) ($lmd_seo_settings["estimate_gate"]["high_min"] ?? "")); ?>" />
                            <p class="description"><?php esc_html_e("Laissez vide si vous ne souhaitez pas filtrer sur l'estimation haute.", "lmd-apps-ia"); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e("Type de vente", "lmd-apps-ia"); ?></th>
                        <td>
                            <div class="lmd-seo-inline-checks">
                                <label><input type="checkbox" name="sale_types[volontaire]" value="1" <?php checked(!empty($lmd_seo_settings["sale_types"]["volontaire"])); ?> /> <?php esc_html_e("Vente volontaire", "lmd-apps-ia"); ?></label>
                                <label><input type="checkbox" name="sale_types[judiciaire]" value="1" <?php checked(!empty($lmd_seo_settings["sale_types"]["judiciaire"])); ?> /> <?php esc_html_e("Vente judiciaire", "lmd-apps-ia"); ?></label>
                            </div>
                            <p class="description"><?php esc_html_e("Vous pouvez reserver l'enrichissement a certains contextes de vente seulement.", "lmd-apps-ia"); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="lmd-ui-panel">
            <h2 class="lmd-ui-section-title"><?php esc_html_e("Categories de vente", "lmd-apps-ia"); ?></h2>
            <?php if ($categories_available) : ?>
            <p class="lmd-seo-toggle-row">
                <label>
                    <input type="checkbox" name="limit_categories" value="1" <?php checked(!empty($lmd_seo_settings["limit_categories"])); ?> />
                    <?php esc_html_e("Limiter l'enrichissement a certaines categories de vente", "lmd-apps-ia"); ?>
                </label>
            </p>
            <div class="lmd-seo-category-grid">
                <?php foreach ($lmd_seo_categories as $term) : ?>
                <label class="lmd-seo-category-option">
                    <input type="checkbox" name="allowed_categories[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selected_categories, true)); ?> />
                    <span><?php echo esc_html($term->name); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <p class="description"><?php esc_html_e("Si la limitation est active, seuls les lots rattaches a ces categories de vente seront eligibles.", "lmd-apps-ia"); ?></p>
            <?php else : ?>
            <div class="lmd-seo-callout lmd-seo-callout--neutral">
                <?php esc_html_e("Les categories de vente ne sont pas encore detectees sur ce site. Ce filtre s'activera automatiquement des qu'elles seront disponibles.", "lmd-apps-ia"); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="lmd-ui-panel">
            <h2 class="lmd-ui-section-title"><?php esc_html_e("Contenus a generer", "lmd-apps-ia"); ?></h2>
            <div class="lmd-seo-inline-checks lmd-seo-inline-checks--stacked">
                <label><input type="checkbox" name="outputs[title]" value="1" <?php checked(!empty($lmd_seo_settings["outputs"]["title"])); ?> /> <?php esc_html_e("SEO title", "lmd-apps-ia"); ?></label>
                <label><input type="checkbox" name="outputs[description]" value="1" <?php checked(!empty($lmd_seo_settings["outputs"]["description"])); ?> /> <?php esc_html_e("Meta description", "lmd-apps-ia"); ?></label>
                <label><input type="checkbox" name="outputs[alts]" value="1" <?php checked(!empty($lmd_seo_settings["outputs"]["alts"])); ?> /> <?php esc_html_e("Alts des images", "lmd-apps-ia"); ?></label>
                <label><input type="checkbox" name="outputs[schema]" value="1" <?php checked(!empty($lmd_seo_settings["outputs"]["schema"])); ?> /> <?php esc_html_e("Schema JSON-LD", "lmd-apps-ia"); ?></label>
            </div>
            <p class="description"><?php esc_html_e("Les textes seront stockes directement sur chaque lot pour pouvoir etre reutilises dans le site et dans un plugin SEO plus tard.", "lmd-apps-ia"); ?></p>
            <p class="submit"><button type="submit" name="lmd_save_seo_settings" value="1" class="button button-primary"><?php esc_html_e("Enregistrer les reglages SEO", "lmd-apps-ia"); ?></button></p>
        </div>
    </form>

    <div class="lmd-ui-panel" id="lmd-seo-batch-app">
        <div class="lmd-seo-batch-header">
            <div>
                <h2 class="lmd-ui-section-title"><?php esc_html_e("Traitement par lot", "lmd-apps-ia"); ?></h2>
                <p class="lmd-ui-prose">
                    <?php esc_html_e("Preparez une file de lots eligibles, puis lancez un traitement progressif par paquets. Chaque passage envoie au maximum 3 images par lot a Gemini. Les images supplementaires reutiliseront l'alt enrichi avec la mention autre vue.", "lmd-apps-ia"); ?>
                </p>
            </div>
            <div class="lmd-seo-batch-actions">
                <button type="button" class="button" data-batch-action="prepare"><?php esc_html_e("Preparer la file", "lmd-apps-ia"); ?></button>
                <button type="button" class="button button-primary" data-batch-action="resume"><?php esc_html_e("Lancer le batch", "lmd-apps-ia"); ?></button>
                <button type="button" class="button" data-batch-action="pause"><?php esc_html_e("Mettre en pause", "lmd-apps-ia"); ?></button>
                <button type="button" class="button" data-batch-action="refresh"><?php esc_html_e("Actualiser", "lmd-apps-ia"); ?></button>
            </div>
        </div>

        <div id="lmd-seo-batch-feedback" class="lmd-app-feedback lmd-app-feedback--info" hidden>
            <p></p>
        </div>

        <div class="lmd-seo-batch-progress">
            <div class="lmd-seo-batch-track"><span id="lmd-seo-batch-fill"></span></div>
            <div class="lmd-seo-batch-progress-meta">
                <strong data-batch-text="status_label"><?php esc_html_e("Inactif", "lmd-apps-ia"); ?></strong>
                <span data-batch-text="progress_label">0 / 0</span>
            </div>
        </div>

        <div class="lmd-seo-overview-grid lmd-seo-batch-stats">
            <div class="lmd-seo-overview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Lots scannes", "lmd-apps-ia"); ?></span>
                <div class="lmd-seo-card-title" data-batch-number="scanned">0</div>
            </div>
            <div class="lmd-seo-overview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("En attente", "lmd-apps-ia"); ?></span>
                <div class="lmd-seo-card-title" data-batch-number="remaining">0</div>
            </div>
            <div class="lmd-seo-overview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Traites", "lmd-apps-ia"); ?></span>
                <div class="lmd-seo-card-title" data-batch-number="processed">0</div>
            </div>
            <div class="lmd-seo-overview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Succes", "lmd-apps-ia"); ?></span>
                <div class="lmd-seo-card-title" data-batch-number="success">0</div>
            </div>
            <div class="lmd-seo-overview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Erreurs", "lmd-apps-ia"); ?></span>
                <div class="lmd-seo-card-title" data-batch-number="errors">0</div>
            </div>
            <div class="lmd-seo-overview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Deja a jour", "lmd-apps-ia"); ?></span>
                <div class="lmd-seo-card-title" data-batch-number="up_to_date">0</div>
            </div>
            <div class="lmd-seo-overview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Non eligibles", "lmd-apps-ia"); ?></span>
                <div class="lmd-seo-card-title" data-batch-number="ineligible">0</div>
            </div>
        </div>

        <p class="description lmd-seo-batch-last" data-batch-text="last_message"><?php esc_html_e("Aucune file preparee pour le moment.", "lmd-apps-ia"); ?></p>
    </div>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e("Tester sur un lot", "lmd-apps-ia"); ?></h2>
        <p class="lmd-ui-prose">
            Lancez ici un enrichissement sur un lot precis pour verifier la qualite des contenus avant de passer aux traitements plus larges.
        </p>
        <form method="post" action="">
            <?php wp_nonce_field("lmd_run_seo_enrichment"); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e("Identifiant du lot", "lmd-apps-ia"); ?></th>
                        <td>
                            <input type="number" min="1" class="regular-text" name="lmd_seo_test_lot_id" value="<?php echo esc_attr((string) $lmd_seo_test_lot_id); ?>" />
                            <p class="description"><?php esc_html_e("Saisissez l'ID d'un CPT lot deja present sur le site.", "lmd-apps-ia"); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e("Mode test", "lmd-apps-ia"); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="lmd_seo_test_force" value="1" />
                                <?php esc_html_e("Forcer ce lot pour un test meme s'il n'est pas eligible avec les reglages actuels", "lmd-apps-ia"); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><button type="submit" name="lmd_run_seo_enrichment" value="1" class="button button-primary"><?php esc_html_e("Lancer le test Gemini", "lmd-apps-ia"); ?></button></p>
        </form>
    </div>

    <div class="lmd-ui-panel">
        <h2 class="lmd-ui-section-title"><?php esc_html_e("Purge et reinitialisation", "lmd-apps-ia"); ?></h2>
        <p class="lmd-ui-prose">
            Supprimez les donnees SEO generees pour pouvoir rejouer un lot apres modification des reglages ou du prompt. Cette purge n'efface que les metas <code>_lmd_seo_*</code> ajoutees par cette application.
        </p>
        <div class="lmd-seo-test-grid">
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Purge ciblee", "lmd-apps-ia"); ?></span>
                <form method="post" action="">
                    <?php wp_nonce_field("lmd_purge_seo_lot"); ?>
                    <p>
                        <label for="lmd-seo-purge-lot-id"><?php esc_html_e("Identifiant du lot", "lmd-apps-ia"); ?></label><br />
                        <input id="lmd-seo-purge-lot-id" type="number" min="1" class="regular-text" name="lmd_seo_purge_lot_id" value="<?php echo esc_attr((string) $lmd_seo_purge_lot_id); ?>" />
                    </p>
                    <p class="description"><?php esc_html_e("Supprime uniquement les donnees SEO generees pour ce lot.", "lmd-apps-ia"); ?></p>
                    <p class="submit"><button type="submit" name="lmd_purge_seo_lot" value="1" class="button"><?php esc_html_e("Purger ce lot", "lmd-apps-ia"); ?></button></p>
                </form>
            </div>
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Purge globale", "lmd-apps-ia"); ?></span>
                <form method="post" action="">
                    <?php wp_nonce_field("lmd_purge_seo_all"); ?>
                    <p class="description"><?php esc_html_e("Supprime les donnees SEO generees pour tous les lots du site courant. Les contenus editoriaux d'origine ne sont pas modifies.", "lmd-apps-ia"); ?></p>
                    <p>
                        <label>
                            <input type="checkbox" name="lmd_seo_purge_confirm_all" value="1" />
                            <?php esc_html_e("Je confirme la purge de tous les enrichissements SEO du site.", "lmd-apps-ia"); ?>
                        </label>
                    </p>
                    <p class="submit"><button type="submit" name="lmd_purge_seo_all" value="1" class="button"><?php esc_html_e("Tout purger sur ce site", "lmd-apps-ia"); ?></button></p>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($lmd_seo_purge_result)) : ?>
    <div class="<?php echo esc_attr($purge_notice_class); ?>"><p><?php echo esc_html((string) ($lmd_seo_purge_result["message"] ?? "")); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($lmd_seo_run_result)) : ?>
    <div class="<?php echo esc_attr($run_notice_class); ?>"><p><?php echo esc_html((string) ($lmd_seo_run_result["message"] ?? "")); ?></p></div>

    <div class="lmd-ui-panel">
        <div class="lmd-seo-badge-row">
            <?php if (!empty($run_stored["status"])) : ?>
            <span class="lmd-seo-badge lmd-seo-badge--soft"><?php echo esc_html(sprintf(__("Statut : %s", "lmd-apps-ia"), $run_stored["status"])); ?></span>
            <?php endif; ?>
            <?php if (!empty($run_stored["model"])) : ?>
            <span class="lmd-seo-badge lmd-seo-badge--soft"><?php echo esc_html($run_stored["model"]); ?></span>
            <?php endif; ?>
            <?php if (!empty($run_stored["enriched_at"])) : ?>
            <span class="lmd-seo-badge lmd-seo-badge--soft"><?php echo esc_html(sprintf(__("Genere le %s", "lmd-apps-ia"), $run_stored["enriched_at"])); ?></span>
            <?php endif; ?>
        </div>

        <h2 class="lmd-ui-section-title"><?php esc_html_e("Apercu du resultat", "lmd-apps-ia"); ?></h2>
        <?php if ($run_lot_id) : ?>
        <p class="lmd-ui-prose">
            <strong><?php echo esc_html($run_lot_title ?: sprintf("Lot #%d", $run_lot_id)); ?></strong>
            <?php if (!empty($run_edit_link)) : ?>
            · <a href="<?php echo esc_url($run_edit_link); ?>"><?php esc_html_e("Ouvrir la fiche lot", "lmd-apps-ia"); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <div class="lmd-seo-test-grid">
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("SEO title", "lmd-apps-ia"); ?></span>
                <p class="lmd-seo-preview-value"><?php echo esc_html((string) ($run_stored["title"] ?? "")); ?></p>
            </div>
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Label canonique", "lmd-apps-ia"); ?></span>
                <p class="lmd-seo-preview-value"><?php echo esc_html((string) ($run_stored["canonical_label"] ?? "")); ?></p>
            </div>
            <div class="lmd-seo-preview-card lmd-seo-preview-card--wide">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Meta description", "lmd-apps-ia"); ?></span>
                <p class="lmd-seo-preview-value"><?php echo esc_html((string) ($run_stored["description"] ?? "")); ?></p>
            </div>
            <div class="lmd-seo-preview-card">
                <span class="lmd-seo-card-kicker"><?php esc_html_e("Mots cles", "lmd-apps-ia"); ?></span>
                <?php if (!empty($run_stored["focus_terms"])) : ?>
                <ul class="lmd-seo-preview-list">
                    <?php foreach ((array) $run_stored["focus_terms"] as $term) : ?>
                    <li><?php echo esc_html($term); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php else : ?>
                <p class="lmd-seo-preview-empty"><?php esc_html_e("Aucun mot cle genere.", "lmd-apps-ia"); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <h3 class="lmd-ui-subsection"><?php esc_html_e("Alts d'images", "lmd-apps-ia"); ?></h3>
        <?php if (!empty($run_stored["image_alts"])) : ?>
        <ol class="lmd-seo-preview-list lmd-seo-preview-list--ordered">
            <?php foreach ((array) $run_stored["image_alts"] as $alt) : ?>
            <li><?php echo esc_html($alt); ?></li>
            <?php endforeach; ?>
        </ol>
        <?php else : ?>
        <p class="lmd-seo-preview-empty"><?php esc_html_e("Aucun alt image genere pour ce lot.", "lmd-apps-ia"); ?></p>
        <?php endif; ?>

        <h3 class="lmd-ui-subsection"><?php esc_html_e("Schema stocke", "lmd-apps-ia"); ?></h3>
        <?php if ($run_schema_json) : ?>
        <pre class="lmd-seo-preview-pre"><?php echo esc_html($run_schema_json); ?></pre>
        <?php else : ?>
        <p class="lmd-seo-preview-empty"><?php esc_html_e("Aucun schema genere pour ce lot.", "lmd-apps-ia"); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(function ($) {
    const batchRoot = $("#lmd-seo-batch-app");
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

    let state = $.extend({}, defaults, <?php echo wp_json_encode($lmd_seo_batch_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {});
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
        batchRoot.find('[data-batch-text="last_message"]').text(state.last_message || "Aucune file preparee pour le moment.");
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










