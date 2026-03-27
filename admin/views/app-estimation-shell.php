<?php
/**
 * Application « Aide à l'estimation » — navigation par onglets.
 *
 * @var string $tab Onglet actif.
 * @var bool   $is_parent Site parent (multisite) ou monosite.
 */
if (!defined('ABSPATH')) {
    exit;
}

$current = isset($tab) ? sanitize_key($tab) : 'dashboard';
$is_parent = !is_multisite() || get_current_blog_id() === 1;

$tabs = [
    'dashboard' => ['Tableau de bord', 'dashicons-dashboard'],
    'new' => ['Nouvelle demande', 'dashicons-plus-alt2'],
    'list' => ['Mes estimations', 'dashicons-list-view'],
    'ventes' => ['Planning ventes', 'dashicons-calendar-alt'],
    'vendeurs' => ['Vendeurs', 'dashicons-groups'],
    'preferences' => ['Préférences', 'dashicons-admin-generic'],
    'help' => ['Aide', 'dashicons-editor-help'],
];
if ($is_parent) {
    $tabs['activity'] = ['Activité', 'dashicons-chart-line'];
}

$lmd_suite_banner_title = 'Aide à l’estimation';
$lmd_suite_banner_subtitle = 'Le Marteau Digital — LMD Apps IA';
?>
<div class="wrap lmd-app-shell lmd-app-shell--estimation lmd-page">
    <?php require LMD_PLUGIN_DIR . 'admin/views/partials/lmd-suite-banner.php'; ?>
    <p class="lmd-app-shell-desc lmd-app-shell-desc--after-banner">Espace dédié à cette application — les réglages réseau et la conso globale sont dans <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-apps-ia')); ?>">Vue d’ensemble</a>.</p>

    <nav class="lmd-app-tabs nav-tab-wrapper wp-clearfix" role="tablist" aria-label="Aide à l’estimation">
        <?php foreach ($tabs as $slug => $info) :
            $label = $info[0];
            $url = function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url($slug) : add_query_arg(['page' => 'lmd-app-estimation', 'tab' => $slug], admin_url('admin.php'));
            $active = ($current === $slug);
            ?>
        <a role="tab" class="nav-tab <?php echo $active ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($url); ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="lmd-app-shell-panel" role="tabpanel">
        <?php
        switch ($current) {
            case 'dashboard':
                $lmd_inner_shell = true;
                include LMD_PLUGIN_DIR . 'admin/views/dashboard.php';
                break;
            case 'new':
                include LMD_PLUGIN_DIR . 'admin/views/new-estimation.php';
                break;
            case 'list':
                $view = LMD_PLUGIN_DIR . 'admin/views/estimations-list-modern.php';
                if (!file_exists($view)) {
                    $view = LMD_PLUGIN_DIR . 'admin/views/estimations-list.php';
                }
                include $view;
                break;
            case 'ventes':
                include LMD_PLUGIN_DIR . 'admin/views/ventes-list.php';
                break;
            case 'vendeurs':
                include LMD_PLUGIN_DIR . 'admin/views/vendeurs-list.php';
                break;
            case 'preferences':
                include LMD_PLUGIN_DIR . 'admin/views/preferences.php';
                break;
            case 'help':
                include LMD_PLUGIN_DIR . 'admin/views/help.php';
                break;
            case 'activity':
                if ($is_parent) {
                    include LMD_PLUGIN_DIR . 'admin/views/activity.php';
                } else {
                    echo '<p>Non disponible sur ce site.</p>';
                }
                break;
            default:
                $lmd_inner_shell = true;
                include LMD_PLUGIN_DIR . 'admin/views/dashboard.php';
        }
        ?>
    </div>
</div>
