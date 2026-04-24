<?php
/**
 * Vue Liste des estimations
 */
if (!defined('ABSPATH')) {
    exit;
}
$db = new LMD_Database();
$status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$estimations = $db->get_estimations(['status' => $status, 'limit' => 100]);
?>
<div class="wrap lmd-page">
    <h1>Mes estimations</h1>
    <p class="lmd-ui-prose">Liste simple (vue de secours). La grille moderne est dans l’application <strong>Aide à l’estimation</strong> → onglet Mes estimations.</p>
    <ul class="subsubsub lmd-subsubsub">
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-app-estimation&tab=list')); ?>" <?php echo $status === '' ? 'class="current"' : ''; ?>>Tous</a> |</li>
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-app-estimation&tab=list&status=new')); ?>" <?php echo $status === 'new' ? 'class="current"' : ''; ?>>Non lus</a> |</li>
        <li><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-app-estimation&tab=list&status=ai_analyzed')); ?>" <?php echo $status === 'ai_analyzed' ? 'class="current"' : ''; ?>>Analysés</a></li>
    </ul>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Statut</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($estimations as $e) : ?>
            <tr>
                <td><?php echo esc_html($e->id); ?></td>
                <td><?php echo esc_html(wp_unslash($e->client_name ?: $e->client_email)); ?></td>
                <td><?php echo esc_html($e->status); ?></td>
                <td><?php echo esc_html($e->created_at); ?></td>
                <td><a href="<?php echo esc_url(admin_url('admin.php?page=lmd-estimation-detail&id=' . $e->id)); ?>">Voir</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

