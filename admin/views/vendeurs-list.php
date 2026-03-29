<?php
/**
 * Liste des vendeurs — coordonnées, proposition, intérêt, dernière date
 */
if (!defined('ABSPATH')) {
    exit;
}
$db = new LMD_Database();
$db->ensure_tags_seeded();
$vendeur_tags = $db->get_tag_options_for_type('vendeur');
global $wpdb;
$e = $wpdb->prefix . 'lmd_estimations';
$et = $wpdb->prefix . 'lmd_estimation_tags';
$t = $wpdb->prefix . 'lmd_tags';
$dr = $wpdb->prefix . 'lmd_delegation_recipients';
$site_id = get_current_blog_id();

$vendeurs = [];
foreach ($vendeur_tags as $v) {
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT e.id, e.client_name, e.client_email, e.client_phone, e.description, e.created_at,
                (SELECT ti.slug FROM $et eti INNER JOIN $t ti ON eti.tag_id = ti.id AND ti.type = 'interet' AND ti.site_id = %d
                 WHERE eti.estimation_id = e.id LIMIT 1) as interet_slug
        FROM $e e
        INNER JOIN $et et ON et.estimation_id = e.id
        INNER JOIN $t tv ON tv.id = et.tag_id AND tv.type = 'vendeur' AND tv.slug = %s AND tv.site_id = %d
        WHERE e.site_id = %d
        ORDER BY e.created_at DESC LIMIT 1",
        $site_id, $v->slug, $site_id, $site_id
    ));
    $cnt = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $et et INNER JOIN $t t ON et.tag_id = t.id WHERE t.id = %d AND t.site_id = %d",
        $v->id, $site_id
    ));
    $interet_name = '';
    if ($row && !empty($row->interet_slug) && function_exists('lmd_get_interet_name')) {
        $interet_name = lmd_get_interet_name($row->interet_slug);
    } elseif ($row && !empty($row->interet_slug)) {
        $interet_name = ucfirst(str_replace('_', ' ', $row->interet_slug));
    }
    $vendeurs[] = (object) [
        'slug' => $v->slug,
        'name' => $v->name,
        'count' => $cnt,
        'coords' => $row ? trim(($row->client_name ?: '') . ' ' . ($row->client_email ?: '') . ' ' . ($row->client_phone ?: '')) : '',
        'proposition' => $row && !empty($row->description) ? wp_trim_words(strip_tags($row->description), 12) : '',
        'interet' => $interet_name,
        'last_date' => $row ? $row->created_at : null,
    ];
}

if ($wpdb->get_var("SHOW TABLES LIKE '$dr'") === $dr) {
    $recipients = $wpdb->get_results($wpdb->prepare(
        "SELECT email FROM $dr WHERE site_id = %d ORDER BY email",
        $site_id
    ));
    $tag_slugs = array_map(function ($x) { return $x->slug; }, $vendeurs);
    foreach ($recipients as $r) {
        $em = trim($r->email ?? '');
        if (!$em || in_array($em, $tag_slugs)) continue;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, client_name, client_email, client_phone, description, created_at,
                    (SELECT ti.slug FROM $et eti INNER JOIN $t ti ON eti.tag_id = ti.id AND ti.type = 'interet' AND ti.site_id = %d
                     WHERE eti.estimation_id = e.id LIMIT 1) as interet_slug
            FROM $e e
            WHERE e.site_id = %d AND (e.client_email = %s OR e.delegation_email = %s)
            ORDER BY e.created_at DESC LIMIT 1",
            $site_id, $site_id, $em, $em
        ));
        $cnt = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $e WHERE site_id = %d AND (client_email = %s OR delegation_email = %s)",
            $site_id, $em, $em
        ));
        $interet_name = '';
        if ($row && !empty($row->interet_slug) && function_exists('lmd_get_interet_name')) {
            $interet_name = lmd_get_interet_name($row->interet_slug);
        } elseif ($row && !empty($row->interet_slug)) {
            $interet_name = ucfirst(str_replace('_', ' ', $row->interet_slug));
        }
        $vendeurs[] = (object) [
            'slug' => $em,
            'name' => $em,
            'count' => $cnt,
            'coords' => $row ? trim(($row->client_name ?: '') . ' ' . ($row->client_email ?: '') . ' ' . ($row->client_phone ?: '')) : $em,
            'proposition' => $row && !empty($row->description) ? wp_trim_words(strip_tags($row->description), 12) : '',
            'interet' => $interet_name,
            'last_date' => $row ? $row->created_at : null,
        ];
        $tag_slugs[] = $em;
    }
}

usort($vendeurs, function ($a, $b) {
    $da = $a->last_date ? strtotime($a->last_date) : 0;
    $db = $b->last_date ? strtotime($b->last_date) : 0;
    if ($db !== $da) return $db - $da;
    return strcasecmp($a->name, $b->name);
});
?>
<div class="wrap lmd-page">
    <h1><?php esc_html_e('Liste vendeurs', 'lmd-apps-ia'); ?></h1>
    <p class="lmd-ui-prose">Vue consolidée des vendeurs (tags + délégations) — accès rapide vers la grille filtrée.</p>
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
                $filter_url = admin_url('admin.php?page=lmd-estimations-list&filter_vendeur[]=' . urlencode($v->slug));
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
