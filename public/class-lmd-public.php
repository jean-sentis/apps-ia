<?php
/**
 * Côté public - Shortcode, assets, AJAX formulaire
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Public {

    public function __construct() {
        add_shortcode('lmd_formulaire_estimation', [$this, 'render_form']);
        add_shortcode('lmd_demande_estimation', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_lmd_submit_estimation', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_lmd_submit_estimation', [$this, 'handle_ajax']);
        add_action('wp_ajax_lmd_generate_lot_expertise', [$this, 'handle_lot_expertise_ajax']);
        add_action('wp_ajax_nopriv_lmd_generate_lot_expertise', [$this, 'handle_lot_expertise_ajax']);
    }

    public function render_form($atts = []) {
        $atts = shortcode_atts(['style' => '', 'titre' => 'Demande d\'estimation'], $atts, 'lmd_formulaire_estimation');
        ob_start();
        if (class_exists('LMD_Public_Form')) {
            $form = new LMD_Public_Form();
            $form->render($atts);
        } else {
            echo '<p>Formulaire non disponible.</p>';
        }
        return ob_get_clean();
    }

    public function enqueue_assets() {
        $public_css = LMD_PLUGIN_DIR . 'assets/public.css';
        $public_js = LMD_PLUGIN_DIR . 'assets/public.js';
        $public_css_version = file_exists($public_css) ? (string) filemtime($public_css) : LMD_VERSION;
        $public_js_version = file_exists($public_js) ? (string) filemtime($public_js) : LMD_VERSION;

        wp_enqueue_style('lmd-public', LMD_PLUGIN_URL . 'assets/public.css', [], $public_css_version);
        wp_enqueue_script('lmd-public', LMD_PLUGIN_URL . 'assets/public.js', ['jquery'], $public_js_version, true);
        wp_localize_script('lmd-public', 'lmdPublic', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lmd_public'),
            'lotId' => is_singular('lot') ? (int) get_queried_object_id() : 0,
            'expertiseAction' => 'lmd_generate_lot_expertise',
            'expertiseMessages' => [
                'loading' => __('Analyse en cours...', 'lmd-apps-ia'),
                'error' => __('Impossible de générer l’analyse IA pour le moment.', 'lmd-apps-ia'),
                'empty' => __('Aucune analyse disponible pour ce lot.', 'lmd-apps-ia'),
                'lotMissing' => __('Lot introuvable.', 'lmd-apps-ia'),
                'disabled' => __('Le service Expertise IA est désactivé sur ce site.', 'lmd-apps-ia'),
                'processing' => __('Une analyse IA est déjà en cours pour ce lot.', 'lmd-apps-ia'),
                'rateLimited' => __('Trop de demandes d’analyse IA. Réessayez dans quelques instants.', 'lmd-apps-ia'),
                'network' => __('Erreur réseau. Réessayez dans quelques instants.', 'lmd-apps-ia'),
            ],
        ]);
    }

    public function handle_ajax() {
        if (class_exists('LMD_Public_Form')) {
            $form = new LMD_Public_Form();
            $form->handle_submission();
        }
    }

    public function handle_lot_expertise_ajax() {
        if (!check_ajax_referer('lmd_public', 'lmd_nonce', false)) {
            wp_send_json_error([
                'message' => __('Session expirée. Rechargez la page.', 'lmd-apps-ia'),
            ], 403);
        }

        $lot_id = isset($_POST['lot_id']) ? absint(wp_unslash($_POST['lot_id'])) : 0;
        if (!$lot_id || get_post_type($lot_id) !== 'lot') {
            wp_send_json_error([
                'message' => __('Lot introuvable.', 'lmd-apps-ia'),
            ], 400);
        }

        if (get_post_status($lot_id) !== 'publish') {
            wp_send_json_error([
                'message' => __('Ce lot n’est pas disponible publiquement.', 'lmd-apps-ia'),
            ], 403);
        }

        if (!class_exists('LMD_Expertise_Analyzer')) {
            wp_send_json_error([
                'message' => __('Le service Expertise IA est indisponible.', 'lmd-apps-ia'),
            ], 500);
        }

        if (function_exists('lmd_is_expertise_enabled') && !lmd_is_expertise_enabled()) {
            wp_send_json_error([
                'message' => __('Le service Expertise IA est désactivé sur ce site.', 'lmd-apps-ia'),
                'disabled' => true,
            ], 403);
        }

        $analyzer = new LMD_Expertise_Analyzer();
        $result = $analyzer->get_current_cached_result($lot_id);
        if (!$result) {
            $rate_limit_error = $this->check_and_consume_lot_expertise_rate_limit($lot_id);
            if ($rate_limit_error !== '') {
                wp_send_json_error([
                    'message' => $rate_limit_error,
                    'rate_limited' => true,
                ], 429);
            }

            $result = $analyzer->analyze_lot($lot_id);
        }
        $stored = is_array($result['stored'] ?? null)
            ? $result['stored']
            : $analyzer->get_stored_output($lot_id);

        if (empty($result['success'])) {
            wp_send_json_error([
                'message' => $this->normalize_lot_expertise_error_message($result),
                'disabled' => !empty($result['disabled']),
                'processing' => !empty($result['processing']),
            ], $this->get_lot_expertise_error_status($result));
        }

        $payload = is_array($stored['payload'] ?? null) ? $stored['payload'] : [];
        if (empty($payload)) {
            wp_send_json_error([
                'message' => __('Aucune analyse disponible pour ce lot.', 'lmd-apps-ia'),
            ], 500);
        }

        wp_send_json_success([
            'lot_id' => $lot_id,
            'cached' => !empty($result['cached']),
            'generated_at' => (string) ($stored['generated_at'] ?? ''),
            'payload' => [
                'explication' => (string) ($payload['explication'] ?? ''),
                'createur' => isset($payload['createur']) ? (string) $payload['createur'] : '',
            ],
        ]);
    }

    private function check_and_consume_lot_expertise_rate_limit($lot_id) {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return '';
        }

        if (is_user_logged_in() && current_user_can('edit_post', $lot_id)) {
            return '';
        }

        $client_hash = $this->get_lot_expertise_client_hash();
        $lot_key = 'lmd_exp_rate_lot_' . $lot_id . '_' . $client_hash;
        if (get_transient($lot_key)) {
            return __('Une analyse IA vient déjà d’être demandée pour ce lot. Réessayez dans une minute.', 'lmd-apps-ia');
        }

        $client_key = 'lmd_exp_rate_client_' . $client_hash;
        $client_attempts = (int) get_transient($client_key);
        if ($client_attempts >= 6) {
            return __('Trop de demandes d’analyse IA en peu de temps. Réessayez dans quelques minutes.', 'lmd-apps-ia');
        }

        set_transient($lot_key, time(), MINUTE_IN_SECONDS);
        set_transient($client_key, $client_attempts + 1, 10 * MINUTE_IN_SECONDS);

        return '';
    }

    private function get_lot_expertise_client_hash() {
        $ip = '';
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $server_key) {
            if (empty($_SERVER[$server_key])) {
                continue;
            }

            $raw = sanitize_text_field(wp_unslash($_SERVER[$server_key]));
            $parts = explode(',', $raw);
            $ip = trim((string) ($parts[0] ?? ''));
            if ($ip !== '') {
                break;
            }
        }

        $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 160)
            : '';
        $identity = get_current_user_id() ? 'user:' . get_current_user_id() : 'ip:' . $ip;
        if ($identity === 'ip:') {
            $identity = 'anonymous';
        }

        return md5($identity . '|' . $user_agent);
    }

    private function normalize_lot_expertise_error_message($result) {
        $result = is_array($result) ? $result : [];

        if (!empty($result['disabled'])) {
            return __('Le service Expertise IA est désactivé sur ce site.', 'lmd-apps-ia');
        }

        if (!empty($result['processing'])) {
            return __('Une analyse IA est déjà en cours pour ce lot.', 'lmd-apps-ia');
        }

        $message = trim((string) ($result['message'] ?? ''));
        $lower = strtolower($message);
        if (
            strpos($lower, 'timed out') !== false ||
            strpos($lower, 'timeout') !== false ||
            strpos($lower, 'curl error 28') !== false
        ) {
            return __('Gemini met trop de temps à répondre. Réessayez dans quelques instants.', 'lmd-apps-ia');
        }

        if (
            strpos($lower, 'gemini') !== false ||
            strpos($lower, 'api') !== false ||
            strpos($lower, 'json attendu') !== false ||
            strpos($lower, 'réponse') !== false ||
            strpos($lower, 'reponse') !== false
        ) {
            return __('Le service IA n’a pas pu produire une analyse exploitable pour ce lot. Réessayez dans quelques instants.', 'lmd-apps-ia');
        }

        return $message !== '' ? $message : __('Analyse IA impossible pour ce lot.', 'lmd-apps-ia');
    }

    private function get_lot_expertise_error_status($result) {
        $result = is_array($result) ? $result : [];
        if (!empty($result['disabled'])) {
            return 403;
        }
        if (!empty($result['processing'])) {
            return 409;
        }

        return 400;
    }
}
