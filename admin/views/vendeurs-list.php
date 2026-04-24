<?php
/**
 * Liste des vendeurs — coordonnées, proposition, intérêt, dernière date
 */
if (!defined('ABSPATH')) {
    exit;
}
$db = new LMD_Database();
$db->ensure_tags_seeded();
global $wpdb;
$e = $wpdb->prefix . 'lmd_estimations';
$et = $wpdb->prefix . 'lmd_estimation_tags';
$t = $wpdb->prefix . 'lmd_tags';
$site_id = get_current_blog_id();

$rows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT e.id, e.client_first_name, e.client_name, e.client_email, e.client_phone, e.description, e.created_at,
                (SELECT ti.slug FROM $et eti
                    INNER JOIN $t ti ON eti.tag_id = ti.id AND ti.type = 'interet' AND ti.site_id = %d
                 WHERE eti.estimation_id = e.id LIMIT 1) AS interet_slug,
                (SELECT tv.slug FROM $et etv
                    INNER JOIN $t tv ON etv.tag_id = tv.id AND tv.type = 'vendeur' AND tv.site_id = %d
                 WHERE etv.estimation_id = e.id LIMIT 1) AS vendeur_slug
         FROM $e e
         WHERE e.site_id = %d
         ORDER BY e.created_at DESC, e.id DESC",
        $site_id,
        $site_id,
        $site_id,
    ),
);

$vendeurs_map = [];
foreach ($rows as $row) {
    $email = strtolower(trim((string) ($row->client_email ?? '')));
    $first_name = trim((string) ($row->client_first_name ?? ''));
    $last_name = trim((string) ($row->client_name ?? ''));
    $full_name = trim($first_name . ' ' . $last_name);
    if ($full_name === '') {
        $full_name = $last_name !== '' ? $last_name : $first_name;
    }

    $group_key = $email !== ''
        ? 'email:' . $email
        : 'name:' . ($row->vendeur_slug ?: sanitize_title($full_name ?: ('vendeur-' . (int) $row->id)));

    if (!isset($vendeurs_map[$group_key])) {
        $interet_name = '';
        if (!empty($row->interet_slug) && function_exists('lmd_get_interet_name')) {
            $interet_name = lmd_get_interet_name($row->interet_slug);
        } elseif (!empty($row->interet_slug)) {
            $interet_name = ucfirst(str_replace('_', ' ', $row->interet_slug));
        }

        $slug = $email !== '' ? $email : ($row->vendeur_slug ?: sanitize_title($full_name ?: ('vendeur-' . (int) $row->id)));
        $label = $full_name !== '' ? $full_name : ($email !== '' ? $email : __('Vendeur sans email', 'lmd-apps-ia'));
        $coords_parts = array_filter([
            $full_name,
            $email,
            trim((string) ($row->client_phone ?? '')),
        ]);

        $vendeurs_map[$group_key] = (object) [
            'slug' => $slug,
            'name' => $label,
            'email' => $email,
            'count' => 0,
            'coords' => !empty($coords_parts) ? implode(' ', $coords_parts) : '-',
            'proposition' => !empty($row->description) ? wp_trim_words(strip_tags($row->description), 12) : '',
            'interet' => $interet_name,
            'last_date' => $row->created_at,
        ];
    }

    $vendeurs_map[$group_key]->count++;
}

$vendeurs = array_values($vendeurs_map);
usort($vendeurs, function ($a, $b) {
    $da = $a->last_date ? strtotime($a->last_date) : 0;
    $db = $b->last_date ? strtotime($b->last_date) : 0;
    if ($db !== $da) {
        return $db - $da;
    }
    return strcasecmp($a->name, $b->name);
});
?>
<div class="wrap lmd-page">
    <h1><?php esc_html_e('Liste vendeurs', 'lmd-apps-ia'); ?></h1>
    <p class="lmd-ui-prose">Vue consolidée des vendeurs par adresse email, avec repli sur le nom uniquement si aucun email n’est renseigné.</p>
    <?php if (empty($vendeurs)) : ?>
    <div class="lmd-ui-panel"><p style="margin:0;">Aucun vendeur.</p></div>
    <?php else : ?>
    <div class="lmd-ui-panel" style="padding:0;overflow:hidden;">
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Nom / Email</th>
                <th>Coordonnées</th>
                <th>Proposition</th>
                <th>Intérêt</th>
                <th>Dernière date</th>
                <th>Demandes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vendeurs as $v) :
                $filter_url = admin_url('admin.php?page=lmd-app-estimation&tab=list&filter_vendeur[]=' . urlencode($v->slug));
            ?>
            <tr>
                <td><a href="<?php echo esc_url($filter_url); ?>"><?php echo esc_html($v->name); ?></a></td>
                <td><?php echo esc_html($v->coords ?: '-'); ?></td>
                <td><?php echo esc_html($v->proposition ?: '-'); ?></td>
                <td><?php echo esc_html($v->interet ?: '-'); ?></td>
                <td><?php echo $v->last_date ? esc_html(wp_date('d/m/Y H:i', strtotime($v->last_date))) : '-'; ?></td>
                <td><a href="<?php echo esc_url($filter_url); ?>"><?php echo (int) $v->count; ?></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

