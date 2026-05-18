<?php
/**
 * Expertise IA Gemini pour les CPT lot.
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Expertise_Analyzer
{
    const PROMPT_VERSION = "expertise-v1";
    const MODEL = "gemini-2.5-flash";
    const MAX_GEMINI_IMAGES = 3;

    private $api;

    public function __construct()
    {
        $this->api = class_exists("LMD_Api_Manager")
            ? new LMD_Api_Manager()
            : null;
    }

    public static function get_meta_keys()
    {
        return [
            "status" => "_lmd_expertise_status",
            "payload" => "_lmd_expertise_payload",
            "generated_at" => "_lmd_expertise_generated_at",
            "model" => "_lmd_expertise_model",
            "prompt_version" => "_lmd_expertise_prompt_version",
            "source_hash" => "_lmd_expertise_source_hash",
            "error" => "_lmd_expertise_error",
        ];
    }

    public function get_stored_output($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return [];
        }

        $meta = self::get_meta_keys();
        $payload = get_post_meta($lot_id, $meta["payload"], true);

        return [
            "status" => (string) get_post_meta($lot_id, $meta["status"], true),
            "payload" => is_array($payload) ? $payload : [],
            "generated_at" => (string) get_post_meta(
                $lot_id,
                $meta["generated_at"],
                true,
            ),
            "model" => (string) get_post_meta($lot_id, $meta["model"], true),
            "prompt_version" => (string) get_post_meta(
                $lot_id,
                $meta["prompt_version"],
                true,
            ),
            "source_hash" => (string) get_post_meta(
                $lot_id,
                $meta["source_hash"],
                true,
            ),
            "error" => (string) get_post_meta($lot_id, $meta["error"], true),
        ];
    }

    public function get_current_cached_result($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return null;
        }

        $context = $this->collect_lot_context($lot_id);
        if (!$context) {
            return null;
        }

        $source_hash = $this->build_source_hash($context);
        $stored = $this->get_stored_output($lot_id);
        if (
            !empty($stored["payload"]) &&
            !empty($stored["source_hash"]) &&
            $stored["source_hash"] === $source_hash &&
            ($stored["status"] ?? "") === "done"
        ) {
            return [
                "success" => true,
                "cached" => true,
                "message" => __(
                    "Expertise IA deja disponible pour ce lot.",
                    "lmd-apps-ia",
                ),
                "lot_id" => $lot_id,
                "context" => $context,
                "stored" => $stored,
            ];
        }

        return null;
    }

    public function analyze_lot($lot_id, $args = [])
    {
        $lot_id = absint($lot_id);
        $args = is_array($args) ? $args : [];
        $force = !empty($args["force"]);

        $context = $this->collect_lot_context($lot_id);
        if (!$context) {
            return [
                "success" => false,
                "message" => __(
                    "Lot introuvable ou type de contenu non pris en charge.",
                    "lmd-apps-ia",
                ),
                "lot_id" => $lot_id,
            ];
        }

        if (
            function_exists("lmd_is_expertise_enabled") &&
            !lmd_is_expertise_enabled() &&
            empty($args["ignore_enabled"])
        ) {
            return [
                "success" => false,
                "disabled" => true,
                "message" => __(
                    "Le service Expertise IA est desactive sur ce site.",
                    "lmd-apps-ia",
                ),
                "lot_id" => $lot_id,
                "context" => $context,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $source_hash = $this->build_source_hash($context);
        $stored = $this->get_stored_output($lot_id);
        if (
            !$force &&
            !empty($stored["payload"]) &&
            !empty($stored["source_hash"]) &&
            $stored["source_hash"] === $source_hash &&
            ($stored["status"] ?? "") === "done"
        ) {
            return [
                "success" => true,
                "cached" => true,
                "message" => __(
                    "Expertise IA deja disponible pour ce lot.",
                    "lmd-apps-ia",
                ),
                "lot_id" => $lot_id,
                "context" => $context,
                "stored" => $stored,
            ];
        }

        if (!$force && ($stored["status"] ?? "") === "processing") {
            if (get_transient($this->get_generation_lock_key($lot_id))) {
                return [
                    "success" => false,
                    "processing" => true,
                    "message" => __(
                        "Une analyse IA est deja en cours pour ce lot.",
                        "lmd-apps-ia",
                    ),
                    "lot_id" => $lot_id,
                    "context" => $context,
                    "stored" => $stored,
                ];
            }
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
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        if (!$this->acquire_generation_lock($lot_id)) {
            return [
                "success" => false,
                "processing" => true,
                "message" => __(
                    "Une analyse IA est deja en cours pour ce lot.",
                    "lmd-apps-ia",
                ),
                "lot_id" => $lot_id,
                "context" => $context,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $this->mark_status($lot_id, "processing", "");

        $prompt = $this->build_gemini_prompt($context);
        $images = $this->prepare_images_for_gemini($context["images"]);
        $result = $this->api->call_gemini($prompt, $images, [
            "model" => self::MODEL,
            "generationConfig" => [
                "temperature" => 0.35,
                "maxOutputTokens" => 4096,
                "responseMimeType" => "application/json",
            ],
        ]);

        if (isset($result["error"])) {
            $message = (string) $result["error"];
            $this->mark_status($lot_id, "error", $message);
            $this->release_generation_lock($lot_id);

            return [
                "success" => false,
                "message" => $message,
                "lot_id" => $lot_id,
                "context" => $context,
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
            $this->release_generation_lock($lot_id);

            return [
                "success" => false,
                "message" => $message,
                "lot_id" => $lot_id,
                "context" => $context,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $payload = $this->normalize_output($json);
        $this->save_output(
            $lot_id,
            $payload,
            $source_hash,
            (string) ($result["model"] ?? self::MODEL),
        );
        $this->log_generation_usage($lot_id);
        $this->release_generation_lock($lot_id);

        return [
            "success" => true,
            "cached" => false,
            "message" => __("Expertise IA generee pour ce lot.", "lmd-apps-ia"),
            "lot_id" => $lot_id,
            "context" => $context,
            "stored" => $this->get_stored_output($lot_id),
        ];
    }

    public function purge_lot($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return false;
        }

        foreach (self::get_meta_keys() as $meta_key) {
            delete_post_meta($lot_id, $meta_key);
        }

        return true;
    }

    private function acquire_generation_lock($lot_id)
    {
        $key = $this->get_generation_lock_key($lot_id);
        if (get_transient($key)) {
            return false;
        }

        return (bool) set_transient($key, time(), 5 * MINUTE_IN_SECONDS);
    }

    private function release_generation_lock($lot_id)
    {
        delete_transient($this->get_generation_lock_key($lot_id));
    }

    private function get_generation_lock_key($lot_id)
    {
        return "lmd_expertise_lock_" . absint($lot_id);
    }

    private function log_generation_usage($lot_id)
    {
        if (!class_exists("LMD_Api_Usage")) {
            return;
        }

        $usage = new LMD_Api_Usage();
        $usage->log("gemini", 1, null);
        $usage->log_service("expertise", (int) $lot_id);
    }

    private function collect_lot_context($lot_id)
    {
        $lot = get_post($lot_id);
        if (!$lot || $lot->post_type !== "lot") {
            return null;
        }

        return [
            "lot_id" => (int) $lot_id,
            "lot_title" => $this->plain_text($lot->post_title),
            "lot_description_text" => $this->plain_text($lot->post_content),
            "estimates" => [
                "low" => $this->parse_number(
                    get_post_meta($lot_id, "lot_estimation_basse", true),
                ),
                "high" => $this->parse_number(
                    get_post_meta($lot_id, "lot_estimation_haute", true),
                ),
            ],
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
            "images" => $this->collect_lot_images($lot_id),
        ];
    }

    private function collect_lot_images($lot_id)
    {
        $image_items = [];
        $thumb_id = get_post_thumbnail_id($lot_id);
        if ($thumb_id) {
            $image_items[] = ["id" => (int) $thumb_id, "url" => ""];
        }

        $gallery_values = get_post_meta($lot_id, "lot_gallery", false);
        foreach ($gallery_values as $gallery) {
            foreach ($this->normalize_gallery_items($gallery) as $item) {
                $image_items[] = $item;
            }
        }

        $unique = [];
        foreach ($image_items as $item) {
            $id = (int) ($item["id"] ?? 0);
            $url = (string) ($item["url"] ?? "");
            $key = $id ? "id:" . $id : "url:" . $url;
            if ($id || $url !== "") {
                $unique[$key] = ["id" => $id, "url" => $url];
            }
        }

        $images = [];
        foreach (array_values($unique) as $item) {
            $attachment_id = (int) ($item["id"] ?? 0);
            $url = "";
            $path = "";

            if ($attachment_id > 0) {
                $url = wp_get_attachment_image_url($attachment_id, "full");
                $path = (string) get_attached_file($attachment_id);
            } elseif (!empty($item["url"])) {
                $url = esc_url_raw((string) $item["url"]);
                $maybe_id = $url ? attachment_url_to_postid($url) : 0;
                if ($maybe_id) {
                    $attachment_id = (int) $maybe_id;
                    $path = (string) get_attached_file($attachment_id);
                }
            }

            if (!$url) {
                continue;
            }

            $images[] = [
                "id" => (int) $attachment_id,
                "url" => (string) $url,
                "path" => $path && file_exists($path) ? $path : "",
            ];
        }

        return array_slice($images, 0, self::MAX_GEMINI_IMAGES);
    }

    private function normalize_gallery_items($raw)
    {
        if (empty($raw)) {
            return [];
        }

        if (is_string($raw) && is_serialized($raw)) {
            $raw = maybe_unserialize($raw);
        }

        if (is_string($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $raw = $json;
            } else {
                if (preg_match('~^https?://~i', $raw) || strpos($raw, "//") === 0) {
                    return [["id" => 0, "url" => esc_url_raw($raw)]];
                }
                $ids = wp_parse_id_list($raw);
                if (!empty($ids)) {
                    return array_map(function ($id) {
                        return ["id" => (int) $id, "url" => ""];
                    }, $ids);
                }
                return [];
            }
        }

        $items = [];
        foreach ((array) $raw as $item) {
            if (is_numeric($item)) {
                $items[] = ["id" => absint($item), "url" => ""];
                continue;
            }

            if (is_string($item)) {
                $value = trim($item);
                if (preg_match('~^https?://~i', $value) || strpos($value, "//") === 0) {
                    $items[] = ["id" => 0, "url" => esc_url_raw($value)];
                }
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $id = $item["id"] ?? ($item["ID"] ?? 0);
            if (is_numeric($id)) {
                $items[] = ["id" => absint($id), "url" => ""];
                continue;
            }

            $url = $item["url"] ?? ($item["guid"] ?? "");
            if (is_string($url) && trim($url) !== "") {
                $items[] = ["id" => 0, "url" => esc_url_raw($url)];
            }
        }

        return array_values(array_filter($items));
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

        return array_slice($out, 0, self::MAX_GEMINI_IMAGES);
    }

    private function build_gemini_prompt($context)
    {
        $seed = <<<'PROMPT'
Tu es un expert en art et antiquites pour une maison de ventes aux encheres.

Ta mission : aider les acheteurs potentiels a comprendre le lot qu'ils consultent.

Tu dois fournir :
1. **Explication** : Explique de quoi il s'agit. Quel type d'objet ? A quoi
   servait-il ? Dans quel contexte etait-il utilise ? Quelle est sa valeur
   artistique ou historique ? Rends l'objet vivant et interessant pour un
   amateur d'art.

2. **Informations sur le createur** (si mentionne) : Si un artiste, un atelier,
   une manufacture ou un lieu de production est mentionne dans le lot, donne
   des informations biographiques ou historiques sur celui-ci. Par exemple :
   dates de vie de l'artiste, mouvement artistique, oeuvres celebres, histoire
   de la manufacture, etc. Si aucun createur n'est mentionne, retourne null.

Style : Sois informatif et accessible, comme un guide de musee passionne.
Evite le jargon technique excessif. 2-3 paragraphes maximum pour chaque section.
PROMPT;

        return implode(
            "\n",
            [
                $seed,
                "",
                "Retourne uniquement un JSON valide, sans markdown.",
                "Structure JSON attendue :",
                "{",
                '  "explication": "2-3 paragraphes maximum",',
                '  "createur": null',
                "}",
                "",
                "Contexte du lot :",
                "Nom actuel : " . ($context["lot_title"] ?: "Non renseigne"),
                "Description : " .
                    ($context["lot_description_text"] ?: "Non renseignee"),
                "Estimation : " .
                    $this->format_estimate_sentence(
                        $context["estimates"]["low"] ?? null,
                        $context["estimates"]["high"] ?? null,
                    ),
                "Dimensions : " .
                    $this->format_dimensions_sentence($context["dimensions"]),
                "Nombre d'images jointes : " . count($context["images"]),
            ],
        );
    }

    private function normalize_output($raw)
    {
        $explication = $this->clean_response_text($raw["explication"] ?? "");
        $createur_raw = $raw["createur"] ?? null;

        $createur = null;
        if (is_array($createur_raw)) {
            $parts = [];
            foreach (["nom", "name", "description", "biographie", "texte"] as $key) {
                if (!empty($createur_raw[$key]) && is_scalar($createur_raw[$key])) {
                    $parts[] = (string) $createur_raw[$key];
                }
            }
            $createur = $this->clean_response_text(implode("\n\n", $parts));
        } elseif (is_scalar($createur_raw)) {
            $createur = $this->clean_response_text($createur_raw);
        }

        if ($createur === "" || strtolower((string) $createur) === "null") {
            $createur = null;
        }

        return [
            "explication" => $explication,
            "createur" => $createur,
        ];
    }

    private function save_output($lot_id, $payload, $source_hash, $model)
    {
        $meta = self::get_meta_keys();

        update_post_meta($lot_id, $meta["status"], "done");
        delete_post_meta($lot_id, $meta["error"]);
        update_post_meta($lot_id, $meta["payload"], $payload);
        update_post_meta($lot_id, $meta["generated_at"], current_time("mysql"));
        update_post_meta($lot_id, $meta["model"], $model ?: self::MODEL);
        update_post_meta($lot_id, $meta["prompt_version"], self::PROMPT_VERSION);
        update_post_meta($lot_id, $meta["source_hash"], $source_hash);
    }

    private function build_source_hash($context)
    {
        $hash_source = [
            "prompt_version" => self::PROMPT_VERSION,
            "model" => self::MODEL,
            "lot_title" => $context["lot_title"],
            "lot_description_text" => $context["lot_description_text"],
            "estimates" => $context["estimates"],
            "dimensions" => $context["dimensions"],
            "images" => array_map(function ($image) {
                return [
                    "id" => (int) ($image["id"] ?? 0),
                    "url" => (string) ($image["url"] ?? ""),
                ];
            }, (array) $context["images"]),
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

    private function extract_json_from_response($text)
    {
        $text = trim((string) $text);
        if ($text === "") {
            return null;
        }

        $text = preg_replace("/^```json\s*/", "", $text);
        $text = preg_replace('/\s*```\s*$/', "", $text);
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function format_dimensions_sentence($dimensions)
    {
        $parts = [];
        if (!empty($dimensions["width"])) {
            $parts[] = "L " . $this->format_number($dimensions["width"]) . " cm";
        }
        if (!empty($dimensions["length"])) {
            $parts[] = "H " . $this->format_number($dimensions["length"]) . " cm";
        }
        if (!empty($dimensions["depth"])) {
            $parts[] = "P " . $this->format_number($dimensions["depth"]) . " cm";
        }

        return !empty($parts) ? implode(", ", $parts) : "Non renseignees";
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

    private function parse_number($value)
    {
        if ($value === null || $value === "") {
            return null;
        }

        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5);
        $value = str_replace(["\xc2\xa0", " "], "", $value);
        $value = str_replace(",", ".", $value);
        $value = preg_replace("/[^0-9.\-]/", "", $value);
        if ($value === "" || $value === "." || $value === "-") {
            return null;
        }

        return (float) $value;
    }

    private function format_number($value)
    {
        return rtrim(rtrim(number_format((float) $value, 2, ".", ""), "0"), ".");
    }

    private function format_money_value($value)
    {
        return number_format((float) $value, 0, ",", " ") . " EUR";
    }

    private function plain_text($value)
    {
        $value = is_scalar($value) ? (string) $value : "";
        $value = wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        $value = preg_replace("/\s+/u", " ", $value);

        return trim((string) $value);
    }

    private function clean_response_text($value)
    {
        $value = is_scalar($value) ? (string) $value : "";
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $value = wp_strip_all_tags($value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace("/[ \t]+/u", " ", $value);
        $value = preg_replace("/\n{3,}/u", "\n\n", $value);

        return trim((string) $value);
    }
}
