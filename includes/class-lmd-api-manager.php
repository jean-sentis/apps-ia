<?php
/**
 * Gestion des appels API (Gemini, SerpAPI Google Lens, Firecrawl)
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Api_Manager
{
    /**
     * En multisite, lit d'abord la valeur sur le site principal.
     * Pour les clés/modèles, si la valeur parent est absente ou vide, on garde un fallback local.
     */
    private function get_runtime_option(
        $option_name,
        $default = "",
        $treat_empty_parent_as_missing = true,
    ) {
        $not_set = "__LMD_OPTION_NOT_SET__";

        if (!is_multisite() || is_main_site()) {
            $value = get_option($option_name, $not_set);
            return $value === $not_set ? $default : $value;
        }

        $parent_value = $not_set;
        switch_to_blog(1);
        try {
            $parent_value = get_option($option_name, $not_set);
        } finally {
            restore_current_blog();
        }

        if ($parent_value !== $not_set) {
            $parent_is_empty_string =
                is_string($parent_value) && trim($parent_value) === "";
            if (!$treat_empty_parent_as_missing || !$parent_is_empty_string) {
                return $parent_value;
            }
        }

        $local_value = get_option($option_name, $not_set);
        return $local_value === $not_set ? $default : $local_value;
    }

    public function get_gemini_key()
    {
        return $this->get_runtime_option("lmd_gemini_key", "");
    }

    public function get_firecrawl_key()
    {
        return $this->get_runtime_option("lmd_firecrawl_key", "");
    }

    public function get_serpapi_key()
    {
        return $this->get_runtime_option("lmd_serpapi_key", "");
    }

    public function get_gemini_model()
    {
        return $this->get_runtime_option("lmd_gemini_model", "gemini-2.5-pro");
    }

    public function get_imgbb_key()
    {
        if (!$this->get_runtime_option("lmd_imgbb_enabled", false, false)) {
            return "";
        }
        return $this->get_runtime_option("lmd_imgbb_key", "");
    }

    /**
     * Upload une image (fichier ou base64) vers ImgBB et retourne l'URL publique.
     * Utilisé quand l'image est locale (SerpAPI ne peut pas y accéder).
     *
     * @param string $path_or_base64 Chemin fichier ou chaîne base64
     * @param bool $is_base64 true si $path_or_base64 est déjà en base64
     * @return string|null URL publique ou null si échec
     */
    public function upload_to_imgbb($path_or_base64, $is_base64 = false)
    {
        $key = $this->get_imgbb_key();
        if (empty($key)) {
            return null;
        }

        $base64 = "";
        if ($is_base64) {
            $base64 = $path_or_base64;
        } elseif (is_string($path_or_base64) && file_exists($path_or_base64)) {
            $raw = file_get_contents($path_or_base64);
            if ($raw === false) {
                return null;
            }
            $base64 = base64_encode($raw);
        } else {
            return null;
        }

        if (empty($base64)) {
            return null;
        }

        $response = wp_remote_post("https://api.imgbb.com/1/upload", [
            "timeout" => 15,
            "body" => [
                "key" => $key,
                "image" => $base64,
            ],
        ]);

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) !== 200
        ) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $url = $data["data"]["url"] ?? ($data["data"]["display_url"] ?? null);
        return is_string($url) ? $url : null;
    }

    /**
     * Appel Gemini pour l'analyse (texte + images optionnelles).
     * Retourne le texte généré ou ['error' => '...'].
     *
     * @param string $prompt Prompt texte
     * @param array $images Tableau de chemins de fichiers ou URLs d'images
     * @return array ['text' => string] ou ['error' => string]
     */
    public function call_gemini($prompt, $images = [])
    {
        $key = $this->get_gemini_key();
        if (empty($key)) {
            return ["error" => "Clé Gemini non configurée"];
        }

        $parts = [["text" => $prompt]];

        foreach ($images as $img) {
            $part = $this->gemini_image_part($img);
            if ($part) {
                $parts[] = $part;
            }
        }

        $body = [
            "contents" => [["parts" => $parts]],
            "generationConfig" => [
                "temperature" => 0.3,
                "topK" => 40,
                "topP" => 0.95,
                "maxOutputTokens" => 8192,
                "responseMimeType" => "application/json",
            ],
        ];

        $model = $this->get_gemini_model();
        $model =
            preg_replace("/[^a-z0-9.\-]/", "", strtolower(trim($model))) ?:
            "gemini-2.5-pro";
        $url =
            "https://generativelanguage.googleapis.com/v1beta/models/" .
            $model .
            ":generateContent?key=" .
            urlencode($key);
        $response = wp_remote_post($url, [
            "timeout" => 120,
            "headers" => ["Content-Type" => "application/json"],
            "body" => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return ["error" => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        if ($code !== 200) {
            $msg =
                $data["error"]["message"] ?? $body_raw ?: "Erreur API Gemini";
            return ["error" => $msg];
        }

        $text = $data["candidates"][0]["content"]["parts"][0]["text"] ?? "";
        if (empty($text)) {
            return ["error" => "Réponse Gemini vide"];
        }

        return ["text" => $text];
    }

    /**
     * Construit une part image pour Gemini (base64 inline).
     */
    private function gemini_image_part($path_or_url)
    {
        $mime = "image/jpeg";
        $data = null;

        if (filter_var($path_or_url, FILTER_VALIDATE_URL)) {
            $resp = wp_remote_get($path_or_url, ["timeout" => 15]);
            if (
                is_wp_error($resp) ||
                wp_remote_retrieve_response_code($resp) !== 200
            ) {
                return null;
            }
            $data = base64_encode(wp_remote_retrieve_body($resp));
            $ct = wp_remote_retrieve_header($resp, "content-type");
            if ($ct) {
                $mime = $ct;
            }
        } elseif (is_string($path_or_url) && file_exists($path_or_url)) {
            $ext = strtolower(pathinfo($path_or_url, PATHINFO_EXTENSION));
            $mime = in_array($ext, ["png", "gif", "webp"])
                ? "image/" . $ext
                : "image/jpeg";
            $data = base64_encode(file_get_contents($path_or_url));
        }

        if (!$data) {
            return null;
        }

        return [
            "inline_data" => [
                "mime_type" => $mime,
                "data" => $data,
            ],
        ];
    }

    /**
     * SerpAPI Google Lens - recherche par image (type Google Lens).
     * Retourne visual_matches, exact_matches, products, etc.
     *
     * @param string $image_url URL publique de l'image
     * @param string $query Requête texte optionnelle pour affiner
     * @param string $type all|visual_matches|exact_matches|products|about_this_image
     * @return array Données SerpAPI ou [] si erreur
     */
    public function call_serpapi_lens(
        $image_url,
        $query = "",
        $type = "visual_matches",
    ) {
        $key = $this->get_serpapi_key();
        if (empty($key)) {
            return [];
        }

        $params = [
            "engine" => "google_lens",
            "url" => $image_url,
            "type" => $type,
            "api_key" => $key,
        ];
        if (!empty($query)) {
            $params["q"] = $query;
        }

        $url = "https://serpapi.com/search?" . http_build_query($params);
        $response = wp_remote_get($url, ["timeout" => 30]);

        if (
            is_wp_error($response) ||
            wp_remote_retrieve_response_code($response) !== 200
        ) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Firecrawl - scrape une URL et retourne le contenu en markdown.
     *
     * @param string $url URL à scraper
     * @return array ['markdown' => string, 'content' => string] ou ['error' => string]
     */
    public function call_firecrawl_scrape($url)
    {
        $key = $this->get_firecrawl_key();
        if (empty($key)) {
            return ["error" => "Clé Firecrawl non configurée"];
        }

        $endpoint = "https://api.firecrawl.dev/v1/scrape";
        $body = [
            "url" => $url,
            "formats" => ["markdown"],
        ];

        $response = wp_remote_post($endpoint, [
            "timeout" => 45,
            "headers" => [
                "Authorization" => "Bearer " . $key,
                "Content-Type" => "application/json",
            ],
            "body" => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return ["error" => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $data["error"] ?? "Erreur Firecrawl";
            return ["error" => is_string($msg) ? $msg : wp_json_encode($msg)];
        }

        $md = $data["data"]["markdown"] ?? ($data["markdown"] ?? "");
        $content = $data["data"]["content"] ?? $md;

        return ["markdown" => $md, "content" => $content];
    }
}
