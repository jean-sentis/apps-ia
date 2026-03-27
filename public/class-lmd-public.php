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
        wp_enqueue_style('lmd-public', LMD_PLUGIN_URL . 'assets/public.css', [], LMD_VERSION);
        wp_enqueue_script('lmd-public', LMD_PLUGIN_URL . 'assets/public.js', ['jquery'], LMD_VERSION, true);
        wp_localize_script('lmd-public', 'lmdPublic', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lmd_public'),
        ]);
    }

    public function handle_ajax() {
        if (class_exists('LMD_Public_Form')) {
            $form = new LMD_Public_Form();
            $form->handle_submission();
        }
    }
}
