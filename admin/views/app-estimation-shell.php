<?php
/**
 * Application « Aide à l'estimation » — onglets + cartouche (liseré commun).
 *
 * @var string $tab Onglet actif.
 * @var bool   $is_parent Site parent (multisite) ou monosite.
 */
if (!defined('ABSPATH')) {
    exit;
}

$current = isset($tab) ? sanitize_key($tab) : 'list';
$is_parent = !is_multisite() || get_current_blog_id() === 1;

$tabs = [
    'list' => ['Mes estimations', 'dashicons-list-view'],
    'new' => ['Nouvelle demande', 'dashicons-plus-alt2'],
    'dashboard' => ['Tableau de bord', 'dashicons-dashboard'],
    'help' => [__('Aide', 'lmd-apps-ia'), 'dashicons-editor-help'],
    'ventes' => ['Planning ventes', 'dashicons-calendar-alt'],
    'vendeurs' => [__('Liste vendeurs', 'lmd-apps-ia'), 'dashicons-groups'],
];

$tabs_primary = ['list', 'new', 'dashboard', 'help', 'ventes', 'vendeurs'];

$lmd_suite_banner_title = 'Aide à l’estimation';
$lmd_suite_banner_subtitle = '';

$tab_in_primary = in_array($current, $tabs_primary, true);
?>
<div class="wrap lmd-app-shell lmd-app-shell--estimation lmd-page">
    <?php require LMD_PLUGIN_DIR . 'admin/views/partials/lmd-suite-banner.php'; ?>
    <?php if (current_user_can('manage_options')) : ?>
    <p class="lmd-app-shell-desc lmd-app-shell-desc--after-banner">Espace dédié à cette application — les réglages réseau et la conso globale sont dans <a href="<?php echo esc_url(admin_url('admin.php?page=lmd-apps-ia')); ?>">Vue d’ensemble</a>.</p>
    <?php endif; ?>

    <div class="lmd-app-col lmd-app-col--estimation <?php echo $tab_in_primary ? 'lmd-app-col--primary-open' : ''; ?>">
        <div class="lmd-app-estimation-outline">
            <div class="lmd-app-estimation-outline__tabs">
                <div class="lmd-app-tabs lmd-app-tabs--liseret" aria-label="<?php esc_attr_e('Flux métier', 'lmd-apps-ia'); ?>">
                    <?php foreach ($tabs_primary as $slug) :
                        if (!isset($tabs[$slug])) {
                            continue;
                        }
                        $info = $tabs[$slug];
                        $label = $info[0];
                        $icon = $info[1];
                        $url = function_exists('lmd_app_estimation_admin_url') ? lmd_app_estimation_admin_url($slug) : add_query_arg(['page' => 'lmd-app-estimation', 'tab' => $slug], admin_url('admin.php'));
                        $active = ($current === $slug);
                        ?>
                    <a role="tab" class="lmd-app-tab lmd-app-tab--primary <?php echo $active ? 'is-active' : ''; ?>" href="<?php echo esc_url($url); ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>><span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span><span class="lmd-app-tab__label"><?php echo esc_html($label); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="lmd-app-estimation-outline__body">
                <div class="lmd-app-tab-cartouche lmd-app-tab-cartouche--estimation" id="lmd-app-estimation-cartouche">
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
            case 'help':
                $lmd_help_embed = true;
                include LMD_PLUGIN_DIR . 'admin/views/help.php';
                break;
            case 'ventes':
                include LMD_PLUGIN_DIR . 'admin/views/ventes-list.php';
                break;
            case 'vendeurs':
                include LMD_PLUGIN_DIR . 'admin/views/vendeurs-list.php';
                break;
            default:
                $lmd_inner_shell = true;
                include LMD_PLUGIN_DIR . 'admin/views/dashboard.php';
        }
        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>





