<?php
/**
 * Rendu front des enrichissements SEO pour le CPT lot.
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Seo_Renderer
{
    public function __construct()
    {
        add_filter("pre_get_document_title", [$this, "filter_document_title"], 20);
        add_action("wp_head", [$this, "output_meta_description"], 5);
        add_action("wp_head", [$this, "output_schema_payload"], 25);
        add_filter("rank_math/frontend/title", [$this, "filter_rank_math_title"], 20);
        add_filter(
            "rank_math/frontend/description",
            [$this, "filter_rank_math_description"],
            20,
        );
        add_filter("rank_math/json_ld", [$this, "filter_rank_math_json_ld"], 110, 2);
        add_filter(
            "wp_get_attachment_image_attributes",
            [$this, "filter_attachment_image_attributes"],
            20,
            3,
        );
    }

    public function filter_document_title($title)
    {
        if ($this->has_external_seo_plugin()) {
            return $title;
        }

        $stored = $this->get_current_lot_seo();
        $seo_title = $this->sanitize_text($stored["title"] ?? "");
        $seo_title = $this->append_site_name($seo_title);

        return $seo_title !== "" ? $seo_title : $title;
    }

    public function output_meta_description()
    {
        if ($this->has_external_seo_plugin()) {
            return;
        }

        $stored = $this->get_current_lot_seo();
        $description = $this->sanitize_text($stored["description"] ?? "");
        if ($description === "") {
            return;
        }

        echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
    }

    public function output_schema_payload()
    {
        if ($this->has_external_seo_plugin()) {
            return;
        }

        $payload = $this->get_current_lot_schema_entity(false);
        if (empty($payload)) {
            return;
        }

        $json = wp_json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if (!$json) {
            return;
        }

        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    public function filter_rank_math_title($title)
    {
        if (!$this->has_rank_math()) {
            return $title;
        }

        $stored = $this->get_current_lot_seo();
        $seo_title = $this->sanitize_text($stored["title"] ?? "");
        $seo_title = $this->append_site_name($seo_title);

        return $seo_title !== "" ? $seo_title : $title;
    }

    public function filter_rank_math_description($description)
    {
        if (!$this->has_rank_math()) {
            return $description;
        }

        $stored = $this->get_current_lot_seo();
        $seo_description = $this->sanitize_text($stored["description"] ?? "");

        return $seo_description !== "" ? $seo_description : $description;
    }

    public function filter_rank_math_json_ld($data, $jsonld)
    {
        unset($jsonld);

        if (!$this->has_rank_math()) {
            return $data;
        }

        $payload = $this->get_current_lot_schema_entity(true);
        if (empty($payload) || !is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $entity) {
            if ($this->should_replace_rank_math_schema_entity($key, $entity)) {
                unset($data[$key]);
            }
        }

        $data["lmd-seo-product"] = $payload;
        return $data;
    }

    public function filter_attachment_image_attributes($attr, $attachment, $size)
    {
        unset($size);

        $lot_id = $this->get_current_lot_id();
        if (!$lot_id) {
            return $attr;
        }

        $attachment_id = is_object($attachment) ? (int) ($attachment->ID ?? 0) : 0;
        if (!$attachment_id) {
            return $attr;
        }

        $alt_map = $this->get_lot_image_alt_map($lot_id);
        $alt = $this->sanitize_text($alt_map[$attachment_id] ?? "");
        if ($alt === "") {
            return $attr;
        }

        $attr["alt"] = $alt;
        return $attr;
    }

    private function get_current_lot_id()
    {
        if (is_admin() || !is_singular("lot")) {
            return 0;
        }

        return absint(get_queried_object_id());
    }

    private function get_current_lot_seo()
    {
        static $cache = [];

        $lot_id = $this->get_current_lot_id();
        if (!$lot_id) {
            return [];
        }

        if (isset($cache[$lot_id])) {
            return $cache[$lot_id];
        }

        if (!class_exists("LMD_Seo_Enricher")) {
            $cache[$lot_id] = [];
            return $cache[$lot_id];
        }

        $enricher = new LMD_Seo_Enricher();
        $stored = $enricher->get_stored_output($lot_id);
        if (($stored["status"] ?? "") !== "done") {
            $cache[$lot_id] = [];
            return $cache[$lot_id];
        }

        $cache[$lot_id] = $stored;
        return $cache[$lot_id];
    }

    private function get_current_lot_schema_entity($for_rank_math = false)
    {
        $stored = $this->get_current_lot_seo();
        $payload = is_array($stored["schema_payload"] ?? null)
            ? $stored["schema_payload"]
            : [];
        if (empty($payload)) {
            return [];
        }

        if ($for_rank_math) {
            unset($payload["@context"]);
        }

        return $payload;
    }

    private function get_lot_image_alt_map($lot_id)
    {
        static $cache = [];

        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return [];
        }

        if (isset($cache[$lot_id])) {
            return $cache[$lot_id];
        }

        $stored = $this->get_current_lot_seo();
        $attachment_ids = $this->collect_lot_attachment_ids($lot_id);
        $image_alts = is_array($stored["image_alts"] ?? null)
            ? array_values($stored["image_alts"])
            : [];
        $alt_base = $this->sanitize_text($stored["alt_base"] ?? "");

        $map = [];
        foreach ($attachment_ids as $index => $attachment_id) {
            $alt = $this->sanitize_text($image_alts[$index] ?? "");
            if ($alt === "" && $alt_base !== "") {
                $alt = ($index === 0)
                    ? $alt_base
                    : sprintf("%s - autre vue", $alt_base);
            }
            if ($alt !== "") {
                $map[(int) $attachment_id] = $alt;
            }
        }

        $cache[$lot_id] = $map;
        return $cache[$lot_id];
    }

    private function collect_lot_attachment_ids($lot_id)
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

        return array_values(array_unique(array_filter($image_ids)));
    }

    private function has_external_seo_plugin()
    {
        return $this->has_yoast() || $this->has_rank_math();
    }

    private function has_yoast()
    {
        return defined("WPSEO_VERSION") || class_exists("WPSEO_Frontend");
    }

    private function has_rank_math()
    {
        return defined("RANK_MATH_VERSION") || class_exists("RankMath\\Helper");
    }

    private function should_replace_rank_math_schema_entity($key, $entity)
    {
        if ($key === "richSnippet") {
            return true;
        }

        if (!is_array($entity) || empty($entity["@type"])) {
            return false;
        }

        $types = is_array($entity["@type"]) ? $entity["@type"] : [$entity["@type"]];
        $types = array_map("strval", $types);

        return !empty(array_intersect($types, ["Product", "ProductGroup"]));
    }

    private function append_site_name($title)
    {
        $title = $this->sanitize_text($title);
        if ($title === "") {
            return "";
        }

        $site_name = $this->sanitize_text(get_bloginfo("name"));
        if ($site_name === "") {
            return $title;
        }

        if ($title === $site_name || str_ends_with($title, " | " . $site_name)) {
            return $title;
        }

        return $title . " | " . $site_name;
    }

    private function sanitize_text($value)
    {
        if (!is_scalar($value)) {
            return "";
        }

        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace("/\\s+/u", " ", $value);
        return trim((string) $value);
    }
}