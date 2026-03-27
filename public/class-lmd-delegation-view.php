<?php
/**
 * Vue publique pour délégation (accès par token, sans login)
 *
 * @package LMD_Module1
 */

if (!defined('ABSPATH')) {
    exit;
}

class LMD_Delegation_View {

    public static function register() {
        add_shortcode('lmd_delegation_view', [__CLASS__, 'render_shortcode']);
        add_action('template_redirect', [__CLASS__, 'maybe_serve_delegation']);
    }

    public static function maybe_serve_delegation() {
        if (!isset($_GET['lmd_delegation_token'])) {
            return;
        }
        $token = sanitize_text_field($_GET['lmd_delegation_token']);
        if (!$token) {
            return;
        }
        $estimation = self::get_estimation_by_token($token);
        if (!$estimation) {
            status_header(404);
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Lien invalide</title></head><body><p>Lien invalide ou expiré.</p></body></html>';
            exit;
        }
        self::render_delegation_page($estimation);
        exit;
    }

    private static function get_estimation_by_token($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'lmd_delegation_tokens';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT estimation_id, expires_at FROM $table WHERE token = %s",
            $token
        ));
        if (!$row || ($row->expires_at && strtotime($row->expires_at) < time())) {
            return null;
        }
        $db = new LMD_Database();
        return $db->get_estimation((int) $row->estimation_id);
    }

    public static function render_shortcode($atts = []) {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        if (!$token) {
            return '<p>Utilisez le lien reçu par email.</p>';
        }
        $estimation = self::get_estimation_by_token($token);
        if (!$estimation) {
            return '<p>Lien invalide ou expiré.</p>';
        }
        ob_start();
        self::render_delegation_page($estimation);
        return ob_get_clean();
    }

    private static function render_delegation_page($estimation) {
        $id = (int) $estimation->id;
        $photos = [];
        if (!empty($estimation->photos)) {
            $decoded = json_decode($estimation->photos, true);
            $photos = is_array($decoded) ? $decoded : (is_string($estimation->photos) ? [$estimation->photos] : []);
        }
        $upload = wp_upload_dir();
        $baseurl = $upload['baseurl'];
        $basedir = $upload['basedir'];
        $photo_url_fn = function ($path) use ($baseurl, $basedir) {
            if (is_array($path)) { $path = reset($path); }
            if (!$path || !is_string($path)) { return ''; }
            if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) { return $path; }
            $full = (strpos($path, $basedir) === 0) ? $path : $basedir . '/' . ltrim(str_replace('\\', '/', $path), '/');
            return file_exists($full) ? str_replace($basedir, $baseurl, $full) : ($baseurl . '/' . ltrim(str_replace('\\', '/', $path), '/'));
        };
        ?>
        <!DOCTYPE html>
        <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Estimation #<?php echo $id; ?></title>
        <style>body{font-family:sans-serif;max-width:900px;margin:40px auto;padding:20px;line-height:1.6;} img{max-width:100%;height:auto;} .lmd-photos{display:flex;flex-wrap:wrap;gap:12px;margin:20px 0;} .lmd-photo{border:1px solid #ddd;border-radius:8px;overflow:hidden;max-width:300px;} .lmd-desc{background:#f9fafb;padding:16px;border-radius:8px;margin:16px 0;}</style>
        </head><body>
        <h1>Estimation #<?php echo $id; ?></h1>
        <?php if (!empty($photos)) : ?>
        <div class="lmd-photos">
            <?php foreach ($photos as $p) :
                $url = $photo_url_fn($p);
                if ($url) :
            ?><div class="lmd-photo"><img src="<?php echo esc_url($url); ?>" alt="" /></div><?php
                endif;
            endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($estimation->description)) : ?>
        <div class="lmd-desc"><h3>Description</h3><?php echo nl2br(esc_html(wp_unslash($estimation->description))); ?></div>
        <?php endif; ?>
        </body></html>
        <?php
    }
}
