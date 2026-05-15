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

        $analyzer = new LMD_Expertise_Analyzer();
        $result = $analyzer->analyze_lot($lot_id);
        $stored = is_array($result['stored'] ?? null)
            ? $result['stored']
            : $analyzer->get_stored_output($lot_id);

        if (empty($result['success'])) {
            wp_send_json_error([
                'message' => (string) ($result['message'] ?? __('Analyse IA impossible.', 'lmd-apps-ia')),
                'disabled' => !empty($result['disabled']),
                'processing' => !empty($result['processing']),
            ], !empty($result['disabled']) ? 403 : 400);
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
}
