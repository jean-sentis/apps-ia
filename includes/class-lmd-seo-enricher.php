<?php
/**
 * Enrichissement SEO Gemini-only pour les CPT lot.
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Seo_Enricher
{
    const PROMPT_VERSION = "gemini-only-v1";

    private $api;
    private $settings;

    public function __construct($settings = null)
    {
        $this->api = class_exists("LMD_Api_Manager")
            ? new LMD_Api_Manager()
            : null;
        $this->settings = is_array($settings)
            ? wp_parse_args($settings, lmd_get_seo_settings_defaults())
            : (function_exists("lmd_get_seo_settings")
                ? lmd_get_seo_settings()
                : lmd_get_seo_settings_defaults());
    }

    public static function get_meta_keys()
    {
        return [
            "status" => "_lmd_seo_status",
            "error" => "_lmd_seo_error",
            "title" => "_lmd_seo_title",
            "description" => "_lmd_seo_description",
            "canonical_label" => "_lmd_seo_canonical_label",
            "alt_base" => "_lmd_seo_alt_base",
            "image_alts" => "_lmd_seo_image_alts",
            "focus_terms" => "_lmd_seo_focus_terms",
            "schema_payload" => "_lmd_seo_schema_payload",
            "source_hash" => "_lmd_seo_source_hash",
            "enriched_at" => "_lmd_seo_enriched_at",
            "model" => "_lmd_seo_model",
            "prompt_version" => "_lmd_seo_prompt_version",
        ];
    }

    public function get_stored_output($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return [];
        }

        $meta = self::get_meta_keys();

        return [
            "status" => (string) get_post_meta($lot_id, $meta["status"], true),
            "error" => (string) get_post_meta($lot_id, $meta["error"], true),
            "title" => (string) get_post_meta($lot_id, $meta["title"], true),
            "description" => (string) get_post_meta(
                $lot_id,
                $meta["description"],
                true,
            ),
            "canonical_label" => (string) get_post_meta(
                $lot_id,
                $meta["canonical_label"],
                true,
            ),
            "alt_base" => (string) get_post_meta(
                $lot_id,
                $meta["alt_base"],
                true,
            ),
            "image_alts" => $this->normalize_string_list(
                get_post_meta($lot_id, $meta["image_alts"], true),
            ),
            "focus_terms" => $this->normalize_string_list(
                get_post_meta($lot_id, $meta["focus_terms"], true),
            ),
            "schema_payload" => get_post_meta(
                $lot_id,
                $meta["schema_payload"],
                true,
            ),
            "source_hash" => (string) get_post_meta(
                $lot_id,
                $meta["source_hash"],
                true,
            ),
            "enriched_at" => (string) get_post_meta(
                $lot_id,
                $meta["enriched_at"],
                true,
            ),
            "model" => (string) get_post_meta($lot_id, $meta["model"], true),
            "prompt_version" => (string) get_post_meta(
                $lot_id,
                $meta["prompt_version"],
                true,
            ),
        ];
    }

    public function purge_lot($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return [
                "success" => false,
                "message" => __(
                    "Identifiant de lot invalide.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $post = get_post($lot_id);
        if (!$post || $post->post_type !== "lot") {
            return [
                "success" => false,
                "message" => __(
                    "Lot introuvable ou type de contenu non pris en charge.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $removed = $this->purge_lot_meta($lot_id);

        return [
            "success" => true,
            "warning" => ($removed === 0),
            "lot_id" => $lot_id,
            "removed" => $removed,
            "message" => ($removed > 0)
                ? sprintf(
                    __('Enrichissement SEO purge pour le lot #%1$d (%2$d meta supprimee(s)).', 'lmd-apps-ia'),
                    $lot_id,
                    $removed,
                )
                : sprintf(
                    __('Aucune donnee SEO a purger pour le lot #%d.', 'lmd-apps-ia'),
                    $lot_id,
                ),
        ];
    }

    public function purge_all_lots()
    {
        $lot_ids = get_posts([
            "post_type" => "lot",
            "post_status" => "any",
            "fields" => "ids",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "orderby" => "ID",
            "order" => "ASC",
            "suppress_filters" => true,
        ]);

        $scanned = is_array($lot_ids) ? count($lot_ids) : 0;
        $touched = 0;
        $removed_total = 0;

        foreach ((array) $lot_ids as $lot_id) {
            $removed = $this->purge_lot_meta((int) $lot_id);
            if ($removed > 0) {
                $touched++;
                $removed_total += $removed;
            }
        }

        return [
            "success" => true,
            "warning" => ($touched === 0),
            "scanned" => $scanned,
            "touched" => $touched,
            "removed" => $removed_total,
            "message" => ($touched > 0)
                ? sprintf(
                    __('Purge SEO terminee : %1$d lot(s) nettoye(s), %2$d meta supprimee(s).', 'lmd-apps-ia'),
                    $touched,
                    $removed_total,
                )
                : __("Aucune donnee SEO stockee a purger sur ce site.", "lmd-apps-ia"),
        ];
    }
    public function enrich_lot($lot_id, $args = [])
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return [
                "success" => false,
                "message" => __(
                    "Identifiant de lot invalide.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $context = $this->collect_lot_context($lot_id);
        if (!$context) {
            return [
                "success" => false,
                "message" => __(
                    "Lot introuvable ou type de contenu non pris en charge.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $settings = $this->settings;
        $force = !empty($args["force"]);
        $eligibility = $this->evaluate_lot_eligibility($context, $settings);
        $source_hash = $this->build_source_hash($context, $settings);
        $stored = $this->get_stored_output($lot_id);

        if (!$force && !$eligibility["eligible"]) {
            $this->mark_status($lot_id, "skipped", $eligibility["message"]);

            return [
                "success" => false,
                "skipped" => true,
                "message" => $eligibility["message"],
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        if (
            !$force &&
            !empty($stored["source_hash"]) &&
            $stored["source_hash"] === $source_hash &&
            ($stored["status"] ?? "") === "done"
        ) {
            return [
                "success" => true,
                "cached" => true,
                "message" => __(
                    "Ce lot est deja enrichi avec ces reglages.",
                    "lmd-apps-ia",
                ),
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $stored,
            ];
        }

        if (!$this->api) {
            $this->mark_status(
                $lot_id,
                "error",
                __("Module API indisponible.", "lmd-apps-ia"),
            );

            return [
                "success" => false,
                "message" => __("Module API indisponible.", "lmd-apps-ia"),
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $this->mark_status($lot_id, "processing", "");

        $prompt = $this->build_gemini_prompt($context);
        $images = $this->prepare_images_for_gemini($context["images"]);
        $result = $this->api->call_gemini($prompt, $images);

        if (isset($result["error"])) {
            $message = (string) $result["error"];
            $this->mark_status($lot_id, "error", $message);

            return [
                "success" => false,
                "message" => $message,
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $json = $this->extract_json_from_response((string) ($result["text"] ?? ""));
        if (!is_array($json)) {
            $message = __(
                "Reponse Gemini invalide : JSON attendu.",
                "lmd-apps-ia",
            );
            $this->mark_status($lot_id, "error", $message);

            return [
                "success" => false,
                "message" => $message,
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $output = $this->normalize_output($json, $context, $settings);
        $this->save_output($lot_id, $output, $source_hash);
        $this->log_api_usage("gemini", 1);

        if (function_exists("lmd_update_consumption_summary")) {
            lmd_update_consumption_summary();
        }

        return [
            "success" => true,
            "cached" => false,
            "message" => __(
                "Enrichissement SEO genere pour ce lot.",
                "lmd-apps-ia",
            ),
            "lot_id" => $lot_id,
            "context" => $context,
            "eligibility" => $eligibility,
            "stored" => $this->get_stored_output($lot_id),
        ];
    }

    public function evaluate_lot_eligibility($context, $settings = null)
    {
        $settings = is_array($settings) ? $settings : $this->settings;

        if (empty($settings["enabled"])) {
            return [
                "eligible" => false,
                "message" => __(
                    "L'enrichissement SEO est desactive sur ce site.",
                    "lmd-apps-ia",
                ),
            ];
        }

        if (!$this->has_enabled_outputs($settings)) {
            return [
                "eligible" => false,
                "message" => __(
                    "Aucun contenu SEO n'est active dans les reglages.",
                    "lmd-apps-ia",
                ),
            ];
        }

        if ($this->has_sale_type_filter($settings)) {
            $sale_type = (string) ($context["sale"]["type_normalized"] ?? "");
            if ($sale_type === "") {
                return [
                    "eligible" => false,
                    "message" => __(
                        "Le type de vente de ce lot n'est pas reconnu.",
                        "lmd-apps-ia",
                    ),
                ];
            }
            if (empty($settings["sale_types"][$sale_type])) {
                return [
                    "eligible" => false,
                    "message" => __(
                        "Le type de vente de ce lot n'est pas active dans les reglages SEO.",
                        "lmd-apps-ia",
                    ),
                ];
            }
        }

        $threshold_check = $this->passes_estimate_gate($context, $settings);
        if (!$threshold_check["eligible"]) {
            return $threshold_check;
        }

        if (!empty($settings["limit_categories"])) {
            $allowed_categories = array_values(
                array_filter(
                    array_map(
                        "sanitize_key",
                        (array) ($settings["allowed_categories"] ?? []),
                    ),
                ),
            );
            if (empty($allowed_categories)) {
                return [
                    "eligible" => false,
                    "message" => __(
                        "Le filtre par categories est actif, mais aucune categorie n'est selectionnee.",
                        "lmd-apps-ia",
                    ),
                ];
            }

            $sale_slugs = (array) ($context["sale"]["category_slugs"] ?? []);
            if (empty(array_intersect($allowed_categories, $sale_slugs))) {
                return [
                    "eligible" => false,
                    "message" => __(
                        "La categorie de vente de ce lot n'est pas incluse dans le ciblage SEO.",
                        "lmd-apps-ia",
                    ),
                ];
            }
        }

        return [
            "eligible" => true,
            "message" => __(
                "Lot eligible aux reglages SEO actuels.",
                "lmd-apps-ia",
            ),
        ];
    }

    private function collect_lot_context($lot_id)
    {
        $lot = get_post($lot_id);
        if (!$lot || $lot->post_type !== "lot") {
            return null;
        }

        $sale_id = (int) $lot->post_parent;
        $sale = $sale_id ? get_post($sale_id) : null;
        $sale_terms =
            $sale_id && taxonomy_exists("categorie_vente")
                ? wp_get_post_terms($sale_id, "categorie_vente")
                : [];
        if (is_wp_error($sale_terms)) {
            $sale_terms = [];
        }

        $lot_description_text = $this->plain_text($lot->post_content);

        return [
            "lot_id" => $lot_id,
            "lot_title" => $this->plain_text($lot->post_title),
            "lot_description_raw" => (string) $lot->post_content,
            "lot_description_text" => $lot_description_text,
            "lot_type" => $this->plain_text(
                get_post_meta($lot_id, "lot_type", true),
            ),
            "lot_category_id" => (string) get_post_meta(
                $lot_id,
                "lot_categorie_id",
                true,
            ),
            "explicit_brand" => $this->extract_explicit_brand(
                $this->plain_text($lot->post_title),
                $lot_description_text,
            ),
            "estimates" => [
                "low" => $this->parse_number(
                    get_post_meta($lot_id, "lot_estimation_basse", true),
                ),
                "high" => $this->parse_number(
                    get_post_meta($lot_id, "lot_estimation_haute", true),
                ),
            ],
            "reserve" => $this->parse_number(
                get_post_meta($lot_id, "lot_prix_reserve", true),
            ),
            "dimensions" => [
                "width" => $this->parse_number(
                    get_post_meta($lot_id, "lot_largeur", true),
                ),
                "length" => $this->parse_number(
                    get_post_meta($lot_id, "lot_longueur", true),
                ),
                "depth" => $this->parse_number(
                    get_post_meta($lot_id, "lot_profondeur", true),
                ),
            ],
            "external_url" => esc_url_raw(
                (string) get_post_meta($lot_id, "lot_lien_externe", true),
            ),
            "permalink" => (string) get_permalink($lot_id),
            "site_name" => (string) get_bloginfo("name"),
            "images" => $this->collect_lot_images($lot_id),
            "sale" => [
                "id" => $sale_id,
                "title" => $sale ? $this->plain_text($sale->post_title) : "",
                "permalink" => $sale_id ? (string) get_permalink($sale_id) : "",
                "external_url" => $sale_id
                    ? esc_url_raw(
                        (string) get_post_meta($sale_id, "vente_url", true),
                    )
                    : "",
                "date" => $sale_id
                    ? $this->plain_text(
                        get_post_meta($sale_id, "vente_date", true),
                    )
                    : "",
                "type_raw" => $sale_id
                    ? $this->plain_text(
                        get_post_meta($sale_id, "vente_type", true),
                    )
                    : "",
                "type_normalized" => $this->normalize_sale_type(
                    $sale_id
                        ? get_post_meta($sale_id, "vente_type", true)
                        : "",
                ),
                "category_names" => array_values(
                    array_filter(
                        array_map(function ($term) {
                            return isset($term->name)
                                ? $this->plain_text($term->name)
                                : "";
                        }, is_array($sale_terms) ? $sale_terms : []),
                    ),
                ),
                "category_slugs" => array_values(
                    array_filter(
                        array_map(function ($term) {
                            return isset($term->slug)
                                ? sanitize_key($term->slug)
                                : "";
                        }, is_array($sale_terms) ? $sale_terms : []),
                    ),
                ),
            ],
        ];
    }

    private function collect_lot_images($lot_id)
    {
        $image_ids = [];
        $thumb_id = get_post_thumbnail_id($lot_id);
        if ($thumb_id) {
            $image_ids[] = (int) $thumb_id;
        }

        $gallery = get_post_meta($lot_id, "lot_gallery", true);
        if (is_string($gallery) && $gallery !== "") {
            $gallery = array_map("trim", explode(",", $gallery));
        }
        if (is_array($gallery)) {
            foreach ($gallery as $attachment_id) {
                $attachment_id = absint($attachment_id);
                if ($attachment_id > 0) {
                    $image_ids[] = $attachment_id;
                }
            }
        }

        $image_ids = array_values(array_unique(array_filter($image_ids)));
        $images = [];
        foreach ($image_ids as $attachment_id) {
            $url = wp_get_attachment_image_url($attachment_id, "full");
            if (!$url) {
                continue;
            }
            $path = (string) get_attached_file($attachment_id);
            $images[] = [
                "id" => $attachment_id,
                "url" => (string) $url,
                "path" => $path && file_exists($path) ? $path : "",
                "current_alt" => $this->plain_text(
                    get_post_meta(
                        $attachment_id,
                        "_wp_attachment_image_alt",
                        true,
                    ),
                ),
                "title" => $this->plain_text(get_the_title($attachment_id)),
                "caption" => $this->plain_text(
                    wp_get_attachment_caption($attachment_id),
                ),
            ];
        }

        return array_slice($images, 0, 5);
    }

    private function prepare_images_for_gemini($images)
    {
        $out = [];
        foreach ((array) $images as $image) {
            $path = (string) ($image["path"] ?? "");
            $url = (string) ($image["url"] ?? "");
            if ($path !== "" && file_exists($path)) {
                $out[] = $path;
            } elseif ($url !== "" && filter_var($url, FILTER_VALIDATE_URL)) {
                $out[] = $url;
            }
        }
        return $out;
    }

    private function build_gemini_prompt($context)
    {
        $sale_categories = !empty($context["sale"]["category_names"])
            ? implode(", ", $context["sale"]["category_names"])
            : "Aucune";
        $dimensions = $this->format_dimensions_sentence($context["dimensions"]);
        $estimate_sentence = $this->format_estimate_sentence(
            $context["estimates"]["low"] ?? null,
            $context["estimates"]["high"] ?? null,
        );

        return implode(
            "\n",
            [
                "Tu es un assistant SEO expert des fiches lot pour des hotels de ventes.",
                "Ta mission : produire des champs SEO fiables et factuels pour un CPT lot.",
                "Contraintes :",
                "- Ecris en francais.",
                "- Reste strictement factuel et descriptif.",
                "- N'invente jamais une attribution, un artiste, une epoque, une provenance ou un materiau s'ils ne sont pas certains.",
                "- N'utilise pas de superlatifs marketing.",
                "- Le title SEO doit rester sous 65 caracteres.",
                "- La meta description doit rester sous 155 caracteres.",
                "- Les alts d'images doivent etre concis, descriptifs et rester sous 125 caracteres.",
                "- Retourne uniquement du JSON valide, sans markdown.",
                "",
                "Structure JSON attendue :",
                "{",
                '  "canonical_label": "libelle canonique court du lot",',
                '  "seo_title": "title SEO",',
                '  "meta_description": "meta description",',
                '  "focus_terms": ["mot cle 1", "mot cle 2"],',
                '  "alt_base": "description courte commune des images",',
                '  "image_alts": ["alt image 1", "alt image 2"]',
                "}",
                "",
                "Contexte du lot :",
                "Nom actuel : " . ($context["lot_title"] ?: "Non renseigne"),
                "Description : " .
                    ($context["lot_description_text"] ?: "Non renseignee"),
                "Type de lot : " . ($context["lot_type"] ?: "Non renseigne"),
                "Estimation : " . $estimate_sentence,
                "Dimensions : " . $dimensions,
                "Vente : " .
                    (($context["sale"]["title"] ?? "") ?: "Non renseignee"),
                "Date de vente : " .
                    (($context["sale"]["date"] ?? "") ?: "Non renseignee"),
                "Type de vente : " .
                    (($context["sale"]["type_raw"] ?? "") ?: "Non renseigne"),
                "Categories de vente : " . $sale_categories,
                "Nombre d'images jointes : " . count($context["images"]),
            ],
        );
    }

    private function normalize_output($raw, $context, $settings)
    {
        $outputs = (array) ($settings["outputs"] ?? []);
        $image_count = count($context["images"]);

        $canonical_label = $this->plain_text($raw["canonical_label"] ?? "");
        if ($canonical_label === "") {
            $canonical_label = $this->default_canonical_label($context);
        }

        $seo_title = $this->plain_text($raw["seo_title"] ?? "");
        if ($seo_title === "") {
            $seo_title = $this->build_default_title($canonical_label, $context);
        }
        $seo_title = $this->truncate_text($seo_title, 65);

        $meta_description = $this->plain_text(
            $raw["meta_description"] ?? "",
        );
        if ($meta_description === "") {
            $meta_description = $this->build_default_description(
                $canonical_label,
                $context,
            );
        }
        $meta_description = $this->truncate_text($meta_description, 155);

        $alt_base = $this->plain_text($raw["alt_base"] ?? "");
        if ($alt_base === "") {
            $alt_base = $canonical_label;
        }
        $alt_base = $this->truncate_text($alt_base, 125);

        $image_alts = [];
        if (is_array($raw["image_alts"] ?? null)) {
            foreach ($raw["image_alts"] as $value) {
                $value = $this->truncate_text($this->plain_text($value), 125);
                if ($value !== "") {
                    $image_alts[] = $value;
                }
            }
        }
        $image_alts = array_slice($image_alts, 0, $image_count);
        for ($i = count($image_alts); $i < $image_count; $i++) {
            $image_alts[] = $this->build_default_image_alt(
                $alt_base,
                $i + 1,
                $image_count,
            );
        }

        $focus_terms = $this->normalize_focus_terms($raw["focus_terms"] ?? []);
        $schema_payload = !empty($outputs["schema"])
            ? $this->build_schema_payload(
                $context,
                [
                    "canonical_label" => $canonical_label,
                    "meta_description" => $meta_description,
                ],
            )
            : [];

        return [
            "canonical_label" => $canonical_label,
            "title" => !empty($outputs["title"]) ? $seo_title : "",
            "description" => !empty($outputs["description"])
                ? $meta_description
                : "",
            "alt_base" => !empty($outputs["alts"]) ? $alt_base : "",
            "image_alts" => !empty($outputs["alts"]) ? $image_alts : [],
            "focus_terms" => $focus_terms,
            "schema_payload" => $schema_payload,
        ];
    }

    private function purge_lot_meta($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return 0;
        }

        $removed = 0;
        foreach (self::get_meta_keys() as $meta_key) {
            if (!metadata_exists("post", $lot_id, $meta_key)) {
                continue;
            }

            $removed += count(get_post_meta($lot_id, $meta_key, false));
            delete_post_meta($lot_id, $meta_key);
        }

        return $removed;
    }
    private function save_output($lot_id, $output, $source_hash)
    {
        $meta = self::get_meta_keys();

        update_post_meta($lot_id, $meta["status"], "done");
        delete_post_meta($lot_id, $meta["error"]);
        update_post_meta($lot_id, $meta["canonical_label"], $output["canonical_label"]);
        $this->save_string_or_delete($lot_id, $meta["title"], $output["title"]);
        $this->save_string_or_delete(
            $lot_id,
            $meta["description"],
            $output["description"],
        );
        $this->save_string_or_delete(
            $lot_id,
            $meta["alt_base"],
            $output["alt_base"],
        );
        $this->save_array_or_delete(
            $lot_id,
            $meta["image_alts"],
            $output["image_alts"],
        );
        $this->save_array_or_delete(
            $lot_id,
            $meta["focus_terms"],
            $output["focus_terms"],
        );
        $this->save_array_or_delete(
            $lot_id,
            $meta["schema_payload"],
            $output["schema_payload"],
        );
        update_post_meta($lot_id, $meta["source_hash"], $source_hash);
        update_post_meta($lot_id, $meta["enriched_at"], current_time("mysql"));
        update_post_meta(
            $lot_id,
            $meta["model"],
            $this->api ? $this->api->get_gemini_model() : "",
        );
        update_post_meta($lot_id, $meta["prompt_version"], self::PROMPT_VERSION);
    }

    private function build_schema_payload($context, $output)
    {
        $payload = [
            "@context" => "https://schema.org",
            "@type" => "Product",
            "name" => $output["canonical_label"],
            "description" => $output["meta_description"],
            "url" => $context["permalink"],
        ];

        $image_urls = array_values(
            array_filter(
                array_map(function ($image) {
                    return (string) ($image["url"] ?? "");
                }, (array) $context["images"]),
            ),
        );
        if (!empty($image_urls)) {
            $payload["image"] = $image_urls;
        }

        if (!empty($context["sale"]["category_names"])) {
            $payload["category"] = implode(
                ", ",
                (array) $context["sale"]["category_names"],
            );
        }

        if (!empty($context["explicit_brand"])) {
            $payload["brand"] = [
                "@type" => "Brand",
                "name" => $context["explicit_brand"],
            ];
        }

        $width = $this->build_quantitative_value(
            $context["dimensions"]["width"] ?? null,
        );
        if (!empty($width)) {
            $payload["width"] = $width;
        }

        $height = $this->build_quantitative_value(
            $context["dimensions"]["length"] ?? null,
        );
        if (!empty($height)) {
            $payload["height"] = $height;
        }

        $depth = $this->build_quantitative_value(
            $context["dimensions"]["depth"] ?? null,
        );
        if (!empty($depth)) {
            $payload["depth"] = $depth;
        }

        $properties = [];
        if (!empty($context["lot_type"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Type de lot",
                "value" => $context["lot_type"],
            ];
        }
        if (!empty($context["sale"]["type_raw"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Type de vente",
                "value" => $context["sale"]["type_raw"],
            ];
        }
        if (!empty($context["estimates"]["low"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Estimation basse",
                "value" => $this->format_money_value(
                    $context["estimates"]["low"],
                ),
            ];
        }
        if (!empty($context["estimates"]["high"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Estimation haute",
                "value" => $this->format_money_value(
                    $context["estimates"]["high"],
                ),
            ];
        }

        if (!empty($properties)) {
            $payload["additionalProperty"] = $properties;
        }

        if (!empty($context["sale"]["title"])) {
            $payload["isRelatedTo"] = [
                "@type" => "SaleEvent",
                "name" => $context["sale"]["title"],
            ];
            if (!empty($context["sale"]["permalink"])) {
                $payload["isRelatedTo"]["url"] = $context["sale"]["permalink"];
            }
            if (!empty($context["sale"]["external_url"])) {
                $payload["isRelatedTo"]["sameAs"] = $context["sale"]["external_url"];
            }
            if (!empty($context["sale"]["date"])) {
                $payload["isRelatedTo"]["startDate"] = $context["sale"]["date"];
            }
            if (!empty($context["site_name"])) {
                $payload["isRelatedTo"]["organizer"] = [
                    "@type" => "Organization",
                    "name" => $context["site_name"],
                    "url" => home_url("/"),
                ];
            }
        }

        return $payload;
    }

    private function has_enabled_outputs($settings)
    {
        foreach ((array) ($settings["outputs"] ?? []) as $enabled) {
            if (!empty($enabled)) {
                return true;
            }
        }
        return false;
    }

    private function has_sale_type_filter($settings)
    {
        $sale_types = (array) ($settings["sale_types"] ?? []);
        return empty($sale_types["volontaire"]) || empty($sale_types["judiciaire"]);
    }

    private function passes_estimate_gate($context, $settings)
    {
        $mode = sanitize_key($settings["estimate_gate"]["mode"] ?? "either");
        $low_min = $this->parse_number(
            $settings["estimate_gate"]["low_min"] ?? "",
        );
        $high_min = $this->parse_number(
            $settings["estimate_gate"]["high_min"] ?? "",
        );
        $low_value = $context["estimates"]["low"] ?? null;
        $high_value = $context["estimates"]["high"] ?? null;

        $passes_low = $low_min === null || ($low_value !== null && $low_value >= $low_min);
        $passes_high = $high_min === null || ($high_value !== null && $high_value >= $high_min);

        if ($mode === "low") {
            if ($low_min === null) {
                return ["eligible" => true, "message" => ""];
            }
            return $passes_low
                ? ["eligible" => true, "message" => ""]
                : [
                    "eligible" => false,
                    "message" => __(
                        "L'estimation basse de ce lot est sous le seuil SEO configure.",
                        "lmd-apps-ia",
                    ),
                ];
        }

        if ($mode === "high") {
            if ($high_min === null) {
                return ["eligible" => true, "message" => ""];
            }
            return $passes_high
                ? ["eligible" => true, "message" => ""]
                : [
                    "eligible" => false,
                    "message" => __(
                        "L'estimation haute de ce lot est sous le seuil SEO configure.",
                        "lmd-apps-ia",
                    ),
                ];
        }

        $checks = [];
        if ($low_min !== null) {
            $checks[] = $passes_low;
        }
        if ($high_min !== null) {
            $checks[] = $passes_high;
        }
        if (empty($checks)) {
            return ["eligible" => true, "message" => ""];
        }

        if (in_array(true, $checks, true)) {
            return ["eligible" => true, "message" => ""];
        }

        return [
            "eligible" => false,
            "message" => __(
                "Ce lot ne franchit aucun des seuils d'estimation SEO configures.",
                "lmd-apps-ia",
            ),
        ];
    }

    private function build_source_hash($context, $settings)
    {
        $hash_source = [
            "lot_title" => $context["lot_title"],
            "lot_description_text" => $context["lot_description_text"],
            "lot_type" => $context["lot_type"],
            "lot_category_id" => $context["lot_category_id"],
            "explicit_brand" => $context["explicit_brand"] ?? "",
            "estimates" => $context["estimates"],
            "dimensions" => $context["dimensions"],
            "site_name" => $context["site_name"] ?? "",
            "sale" => [
                "title" => $context["sale"]["title"] ?? "",
                "permalink" => $context["sale"]["permalink"] ?? "",
                "external_url" => $context["sale"]["external_url"] ?? "",
                "date" => $context["sale"]["date"] ?? "",
                "type_normalized" => $context["sale"]["type_normalized"] ?? "",
                "category_slugs" => $context["sale"]["category_slugs"] ?? [],
            ],
            "images" => array_map(function ($image) {
                return [
                    "id" => (int) ($image["id"] ?? 0),
                    "url" => (string) ($image["url"] ?? ""),
                ];
            }, (array) $context["images"]),
            "outputs" => (array) ($settings["outputs"] ?? []),
        ];

        return md5(wp_json_encode($hash_source));
    }

    private function mark_status($lot_id, $status, $error_message = "")
    {
        $meta = self::get_meta_keys();
        update_post_meta($lot_id, $meta["status"], sanitize_key($status));
        if ($error_message !== "") {
            update_post_meta($lot_id, $meta["error"], $this->plain_text($error_message));
        } else {
            delete_post_meta($lot_id, $meta["error"]);
        }
    }

    private function log_api_usage($api_name, $units)
    {
        if (!class_exists("LMD_Api_Usage")) {
            return;
        }
        $usage = new LMD_Api_Usage();
        $usage->log($api_name, (int) $units, null);
    }

    private function extract_json_from_response($text)
    {
        $text = trim((string) $text);
        if ($text === "") {
            return null;
        }
        $text = preg_replace("/^```json\\s*/", "", $text);
        $text = preg_replace('/\\s*```\\s*$/', "", $text);
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function normalize_focus_terms($terms)
    {
        if (is_string($terms)) {
            $terms = explode(",", $terms);
        }
        $out = [];
        foreach ((array) $terms as $term) {
            $term = $this->plain_text($term);
            if ($term !== "") {
                $out[] = $term;
            }
        }
        $out = array_values(array_unique($out));
        return array_slice($out, 0, 6);
    }

    private function normalize_string_list($value)
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(
            array_filter(
                array_map(function ($item) {
                    return $this->plain_text($item);
                }, $value),
            ),
        );
    }

    private function save_string_or_delete($lot_id, $meta_key, $value)
    {
        $value = $this->plain_text($value);
        if ($value === "") {
            delete_post_meta($lot_id, $meta_key);
            return;
        }
        update_post_meta($lot_id, $meta_key, $value);
    }

    private function save_array_or_delete($lot_id, $meta_key, $value)
    {
        $value = is_array($value) ? array_values($value) : [];
        if (empty($value)) {
            delete_post_meta($lot_id, $meta_key);
            return;
        }
        update_post_meta($lot_id, $meta_key, $value);
    }

    private function build_default_title($canonical_label, $context)
    {
        $title = $canonical_label;
        $sale_title = (string) ($context["sale"]["title"] ?? "");
        if ($sale_title !== "" && $this->string_length($title) < 42) {
            $title .= " | " . $sale_title;
        }
        return $title;
    }

    private function build_default_description($canonical_label, $context)
    {
        $parts = [$canonical_label];

        $estimate = $this->format_estimate_sentence(
            $context["estimates"]["low"] ?? null,
            $context["estimates"]["high"] ?? null,
        );
        if ($estimate !== "Non renseignee") {
            $parts[] = "Estimation " . strtolower($estimate);
        }

        if (!empty($context["sale"]["title"])) {
            $parts[] = "Vente " . $context["sale"]["title"];
        }

        return implode(". ", array_filter($parts)) . ".";
    }

    private function build_default_image_alt($alt_base, $index, $total)
    {
        if ($total <= 1) {
            return $alt_base;
        }
        return $this->truncate_text(
            sprintf("%s - vue %d", $alt_base, (int) $index),
            125,
        );
    }

    private function default_canonical_label($context)
    {
        $parts = [];
        if (!empty($context["lot_type"])) {
            $parts[] = $context["lot_type"];
        }
        if (!empty($context["lot_title"])) {
            $parts[] = $context["lot_title"];
        }
        if (empty($parts) && !empty($context["lot_description_text"])) {
            $parts[] = $context["lot_description_text"];
        }
        $label = trim(implode(" - ", array_filter($parts)));
        if ($label === "") {
            $label = sprintf("Lot %d", (int) $context["lot_id"]);
        }
        return $this->truncate_text($label, 90);
    }

    private function normalize_sale_type($raw)
    {
        $raw = strtolower($this->plain_text($raw));
        if ($raw === "") {
            return "";
        }
        if (strpos($raw, "judic") !== false) {
            return "judiciaire";
        }
        if (strpos($raw, "volont") !== false) {
            return "volontaire";
        }
        return sanitize_key($raw);
    }

    private function parse_number($value)
    {
        if ($value === null || $value === "") {
            return null;
        }
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5);
        $value = str_replace(["\xc2\xa0", " "], "", $value);
        $value = str_replace(",", ".", $value);
        $value = preg_replace("/[^0-9.\\-]/", "", $value);
        if ($value === "" || $value === "." || $value === "-") {
            return null;
        }
        return (float) $value;
    }

    private function format_estimate_sentence($low, $high)
    {
        if ($low && $high) {
            return sprintf(
                "%s a %s",
                $this->format_money_value($low),
                $this->format_money_value($high),
            );
        }
        if ($high) {
            return "jusqu'a " . $this->format_money_value($high);
        }
        if ($low) {
            return "a partir de " . $this->format_money_value($low);
        }
        return "Non renseignee";
    }

    private function format_dimensions_sentence($dimensions)
    {
        $parts = [];
        if (!empty($dimensions["width"])) {
            $parts[] = "L " . $this->format_dimension_value($dimensions["width"]) . " cm";
        }
        if (!empty($dimensions["length"])) {
            $parts[] = "H " . $this->format_dimension_value($dimensions["length"]) . " cm";
        }
        if (!empty($dimensions["depth"])) {
            $parts[] = "P " . $this->format_dimension_value($dimensions["depth"]) . " cm";
        }
        return !empty($parts) ? implode(", ", $parts) : "Non renseignees";
    }

    private function format_dimension_value($value)
    {
        return rtrim(rtrim(number_format((float) $value, 2, ".", ""), "0"), ".");
    }

    private function format_money_value($value)
    {
        return number_format((float) $value, 0, ",", " ") . " EUR";
    }

    private function build_quantitative_value($value)
    {
        if ($value === null || $value === "") {
            return null;
        }

        return [
            "@type" => "QuantitativeValue",
            "value" => (float) $value,
            "unitCode" => "CMT",
        ];
    }

    private function extract_explicit_brand($title, $description)
    {
        $haystacks = [
            (string) $title,
            (string) $description,
        ];

        foreach ($haystacks as $text) {
            if ($text === "") {
                continue;
            }

            if (
                preg_match(
                    "/(?:^|[\\s\\(\\[])(?:marque|brand|fabricant|manufacture)\\s*[:\\-]\\s*([^\\n\\r\\.;,\\)\\]]{2,80})/iu",
                    $text,
                    $matches,
                )
            ) {
                $brand = $this->plain_text($matches[1] ?? "");
                if ($brand !== "") {
                    return $this->truncate_text($brand, 80);
                }
            }
        }

        return "";
    }

    private function plain_text($value)
    {
        $value = is_scalar($value) ? (string) $value : "";
        $value = wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        $value = preg_replace("/\\s+/u", " ", $value);
        return trim((string) $value);
    }

    private function truncate_text($value, $limit)
    {
        $value = $this->plain_text($value);
        if ($value === "" || $this->string_length($value) <= $limit) {
            return $value;
        }

        $slice = $this->string_substr($value, 0, max(0, $limit - 1));
        $slice = preg_replace("/\\s+\\S*$/u", "", $slice);
        if (!$slice) {
            $slice = $this->string_substr($value, 0, max(0, $limit - 1));
        }
        return rtrim($slice, " ,;:-") . "...";
    }

    private function string_length($value)
    {
        return function_exists("mb_strlen")
            ? mb_strlen($value)
            : strlen($value);
    }

    private function string_substr($value, $start, $length = null)
    {
        if (function_exists("mb_substr")) {
            return $length === null
                ? mb_substr($value, $start)
                : mb_substr($value, $start, $length);
        }

        return $length === null
            ? substr($value, $start)
            : substr($value, $start, $length);
    }
}


