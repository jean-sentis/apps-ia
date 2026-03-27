<?php
/**
 * Préférences de l'aide à l'estimation
 */
if (!defined('ABSPATH')) {
    exit;
}
$prefs = function_exists('lmd_get_prefs') ? lmd_get_prefs() : [];
$categories = function_exists('lmd_get_tag_categories') ? lmd_get_tag_categories() : [];
$opts_theme = $categories['theme_vente']['options'] ?? [];
$grid_criteria = $prefs['grid_criteria'] ?? [];
$excluded = $prefs['excluded_theme_slugs'] ?? [];
$excluded = is_array($excluded) ? $excluded : [];

$cp = function_exists('lmd_get_cp_settings_for_user') ? lmd_get_cp_settings_for_user() : ['signature' => '', 'copy_emails' => []];
$copy1 = $cp['copy_emails'][0] ?? '';
$copy2 = $cp['copy_emails'][1] ?? '';

$estimation_opts = [];
if (function_exists('lmd_get_custom_category_options')) {
    $estimation_opts = lmd_get_custom_category_options('estimation');
}
if (empty($estimation_opts) && function_exists('lmd_get_default_estimation_options')) {
    $estimation_opts = lmd_get_default_estimation_options();
}

$criteria_labels = [
    'message' => ['label' => 'Échanges (Nouveau, Répondu, Vendu…)', 'color' => '#1d4ed8'],
    'interet' => ['label' => 'Intérêt', 'color' => '#a16207'],
    'estimation' => ['label' => 'Estimation (montant ou palier)', 'color' => '#6b7280'],
    'theme' => ['label' => 'Catégorie / Thème', 'color' => '#374151'],
    'date' => ['label' => 'Date d\'envoi', 'color' => '#6b7280'],
    'cp_avis2' => ['label' => 'CP / 2e avis', 'color' => '#1d4ed8'],
];

if (isset($_POST['lmd_prefs_nonce']) && wp_verify_nonce($_POST['lmd_prefs_nonce'], 'lmd_save_prefs')) {
    $grid_criteria = [];
    foreach (array_keys($criteria_labels) as $k) {
        $grid_criteria[$k] = !empty($_POST['grid_criteria'][$k]);
    }
    $display_last_n = max(0, min(500, (int) ($_POST['display_last_n'] ?? 0)));
    $display_include_unanswered = !empty($_POST['display_include_unanswered']);
    $display_older_than_days = max(0, min(365, (int) ($_POST['display_older_than_days'] ?? 0)));
    $excluded = isset($_POST['excluded_theme']) && is_array($_POST['excluded_theme']) ? array_map('sanitize_text_field', $_POST['excluded_theme']) : [];
    if (function_exists('lmd_save_prefs')) {
        lmd_save_prefs([
            'grid_criteria' => $grid_criteria,
            'display_last_n' => $display_last_n,
            'display_include_unanswered' => $display_include_unanswered,
            'display_older_than_days' => $display_older_than_days,
            'excluded_theme_slugs' => $excluded,
        ]);
    }
    if (function_exists('lmd_save_cp_settings_for_user')) {
        $cp_current = lmd_get_cp_settings_for_user();
        $sig = isset($_POST['cp_signature']) ? wp_kses_post(wp_unslash($_POST['cp_signature'])) : '';
        $c1 = isset($_POST['cp_copy_email_1']) ? sanitize_email(wp_unslash($_POST['cp_copy_email_1'])) : '';
        $c2 = isset($_POST['cp_copy_email_2']) ? sanitize_email(wp_unslash($_POST['cp_copy_email_2'])) : '';
        lmd_save_cp_settings_for_user($cp_current['email'], $sig, array_filter([$c1, $c2]));
    }
    if (function_exists('lmd_save_custom_category_options') && isset($_POST['estimation_intervals']) && is_array($_POST['estimation_intervals'])) {
        $opts = [];
        foreach ($_POST['estimation_intervals'] as $row) {
            if (!is_array($row)) continue;
            $name = isset($row['name']) ? wp_strip_all_tags($row['name']) : '';
            $max = isset($row['max']) && $row['max'] !== '' ? floatval($row['max']) : null;
            $min = isset($row['min']) && $row['min'] !== '' ? floatval($row['min']) : null;
            if (!$name || ($max === null && $min === null)) continue;
            if ($max !== null) {
                $opts[] = ['slug' => 'moins_' . intval($max), 'name' => $name, 'max' => $max];
            } else {
                $opts[] = ['slug' => 'plus_' . intval($min), 'name' => $name, 'min' => $min];
            }
        }
        if (!empty($opts)) {
            lmd_save_custom_category_options('estimation', $opts);
        }
    }
    echo '<div class="notice notice-success"><p>Préférences enregistrées.</p></div>';
    $prefs = lmd_get_prefs();
    $grid_criteria = $prefs['grid_criteria'] ?? $grid_criteria;
    $excluded = $prefs['excluded_theme_slugs'] ?? $excluded;
    $cp = lmd_get_cp_settings_for_user();
    $copy1 = $cp['copy_emails'][0] ?? '';
    $copy2 = $cp['copy_emails'][1] ?? '';
    $estimation_opts = function_exists('lmd_get_custom_category_options') ? lmd_get_custom_category_options('estimation') : [];
    if (empty($estimation_opts) && function_exists('lmd_get_default_estimation_options')) {
        $estimation_opts = lmd_get_default_estimation_options();
    }
}
?>
<div class="wrap lmd-preferences lmd-page">
    <h1>Préférences — LMD Apps IA</h1>
    <p class="lmd-ui-prose">Réglages de la grille « Mes estimations », signature, formules et paliers — même style que le reste de l’aide à l’estimation.</p>

    <form method="post" action="">
        <?php wp_nonce_field('lmd_save_prefs', 'lmd_prefs_nonce'); ?>

        <h2 class="lmd-ui-section-title">Affichage sur la grille</h2>
        <p class="description">Choisissez les critères affichés sur chaque vignette de la grille « Mes estimations ». Tous sont cochés par défaut. Décochez ceux que vous ne souhaitez pas voir.</p>
        <div class="lmd-pref-grid-criteria">
            <?php foreach ($criteria_labels as $key => $info) :
                $checked = !isset($grid_criteria[$key]) || $grid_criteria[$key];
            ?>
            <label class="lmd-pref-criterion" style="--criterion-color:<?php echo esc_attr($info['color']); ?>">
                <input type="checkbox" name="grid_criteria[<?php echo esc_attr($key); ?>]" value="1" <?php checked($checked); ?> />
                <span class="lmd-pref-criterion-chip"><?php echo esc_html($info['label']); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="lmd-pref-example">
            <strong>Exemple</strong> — Une vignette avec les critères cochés affiche : <span class="lmd-card-tag tag-message">Répondu</span> <span class="lmd-card-tag tag-interet">Intéressant</span> <span class="lmd-card-tag">1 500 €</span> <span class="lmd-card-tag">Art contemporain</span> <span class="lmd-card-date">15 mars 2025</span> <span class="lmd-card-tag lmd-card-tag-mini tag-source-cp">CP</span>
        </div>

        <h2 class="lmd-ui-section-title">Affichage : combien de demandes afficher</h2>
        <p class="description">Par défaut, la grille affiche les demandes les plus récentes. Vous pouvez aussi inclure automatiquement celles auxquelles vous n'avez pas répondu et celles envoyées depuis plus de X jours. <strong>Utilisez la recherche par dates</strong> (Période envoi) pour affiner.</p>
        <table class="form-table">
            <tr>
                <th><label for="display_last_n">Les X derniers</label></th>
                <td>
                    <input type="number" id="display_last_n" name="display_last_n" value="<?php echo esc_attr($prefs['display_last_n'] ?? 0); ?>" min="0" max="500" step="5" />
                    <span class="description">0 = pas de limite. Sinon, demandes les plus récentes à afficher (10–500)</span>
                </td>
            </tr>
            <tr>
                <th><label for="display_include_unanswered">+ Non répondues</label></th>
                <td>
                    <label><input type="checkbox" id="display_include_unanswered" name="display_include_unanswered" value="1" <?php checked(!empty($prefs['display_include_unanswered'])); ?> /> Inclure automatiquement toutes les demandes auxquelles vous n'avez pas répondu</label>
                </td>
            </tr>
            <tr>
                <th><label for="display_older_than_days">+ Depuis plus de … jours</label></th>
                <td>
                    <input type="number" id="display_older_than_days" name="display_older_than_days" value="<?php echo esc_attr($prefs['display_older_than_days'] ?? 0); ?>" min="0" max="365" /> jours
                    <span class="description">0 = désactivé. Inclure les demandes envoyées il y a plus de X jours</span>
                </td>
            </tr>
        </table>

        <h2 class="lmd-ui-section-title">Catégories qui ne vous intéressent pas</h2>
        <p class="description">Décochez les catégories (thèmes de vente) que vous ne souhaitez pas voir dans la grille. Les demandes de ces catégories seront masquées par défaut.</p>
        <div class="lmd-pref-excluded-themes">
            <?php foreach ($opts_theme as $opt) :
                $slug = $opt['slug'] ?? '';
                $name = $opt['name'] ?? $slug;
                if (!$slug) continue;
                $checked = in_array($slug, $excluded);
            ?>
            <label class="lmd-pref-theme-check">
                <input type="checkbox" name="excluded_theme[]" value="<?php echo esc_attr($slug); ?>" <?php checked($checked); ?> />
                <?php echo esc_html($name); ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php if (empty($opts_theme)) : ?>
        <p class="description">Aucune catégorie configurée.</p>
        <?php endif; ?>

        <h2 class="lmd-ui-section-title">Signature (cartouche)</h2>
        <p class="description">Texte ou HTML affiché en fin de mail au vendeur. Accepte les balises HTML de base.</p>
        <table class="form-table">
            <tr>
                <th><label for="cp_signature">Signature</label></th>
                <td>
                    <textarea id="cp_signature" name="cp_signature" rows="6" class="large-text"><?php echo esc_textarea($cp['signature'] ?? ''); ?></textarea>
                </td>
            </tr>
        </table>

        <h2 class="lmd-ui-section-title">Copie cachée (CC)</h2>
        <p class="description">Adresses email où envoyer une copie cachée du mail au vendeur. Maximum deux adresses.</p>
        <table class="form-table">
            <tr>
                <th><label for="cp_copy_email_1">Email 1</label></th>
                <td>
                    <input type="email" id="cp_copy_email_1" name="cp_copy_email_1" value="<?php echo esc_attr($copy1); ?>" class="regular-text" placeholder="email1@exemple.fr" />
                </td>
            </tr>
            <tr>
                <th><label for="cp_copy_email_2">Email 2</label></th>
                <td>
                    <input type="email" id="cp_copy_email_2" name="cp_copy_email_2" value="<?php echo esc_attr($copy2); ?>" class="regular-text" placeholder="email2@exemple.fr" />
                </td>
            </tr>
        </table>

        <h2 class="lmd-ui-section-title">Formules pour répondre aux vendeurs</h2>
        <p class="description">Les formules enregistrées sont réutilisables dans les mails aux vendeurs. Vous pouvez les gérer ici ou depuis la fiche d'une estimation (icône ⚙ à côté de « Formules enregistrées »).</p>
        <div id="lmd-pref-formules-wrap" class="lmd-pref-formules">
            <div id="lmd-pref-formules-list"></div>
            <div class="lmd-pref-formules-add">
                <button type="button" class="button lmd-pref-add-formule-btn">Ajouter une formule</button>
            </div>
            <div id="lmd-pref-formule-form" class="lmd-pref-formule-form" style="display:none;">
                <input type="hidden" id="lmd-formule-edit-id" value="" />
                <p><label>Nom <input type="text" id="lmd-formule-name" class="regular-text" placeholder="Ex: Refus standard" /></label></p>
                <p><label>Contenu <textarea id="lmd-formule-content" rows="4" class="large-text" placeholder="Texte de la formule..."></textarea></label></p>
                <p><button type="button" class="button button-primary lmd-save-formule-pref-btn">Enregistrer</button> <button type="button" class="button lmd-cancel-formule-pref-btn">Annuler</button></p>
            </div>
        </div>

        <h2 class="lmd-ui-section-title">Intervalles d'estimation</h2>
        <p class="description">Définissez les paliers affichés dans les filtres et sur les vignettes (&lt; 25 €, &lt; 100 €, etc.).</p>
        <table class="form-table lmd-pref-intervals">
            <thead>
                <tr><th>Libellé</th><th>Max (&lt; X €)</th><th>Min (&gt; X €)</th><th></th></tr>
            </thead>
            <tbody id="lmd-intervals-tbody">
                <?php foreach ($estimation_opts as $i => $o) :
                    $has_max = isset($o['max']) && $o['max'] !== null && $o['max'] !== '';
                    $has_min = isset($o['min']) && $o['min'] !== null && $o['min'] !== '';
                    $max_val = $has_max ? $o['max'] : '';
                    $min_val = $has_min ? $o['min'] : '';
                ?>
                <tr>
                    <td><input type="text" name="estimation_intervals[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr($o['name'] ?? ''); ?>" class="regular-text" /></td>
                    <td><input type="number" name="estimation_intervals[<?php echo (int) $i; ?>][max]" value="<?php echo esc_attr($max_val); ?>" min="0" step="1" placeholder="—" style="width:80px;" /></td>
                    <td><input type="number" name="estimation_intervals[<?php echo (int) $i; ?>][min]" value="<?php echo esc_attr($min_val); ?>" min="0" step="1" placeholder="—" style="width:80px;" /></td>
                    <td><button type="button" class="button lmd-remove-interval" title="Supprimer">×</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button lmd-add-interval">Ajouter un palier</button></p>

        <p class="submit">
            <button type="submit" class="button button-primary">Enregistrer les préférences</button>
        </p>
    </form>
</div>
<style>
.lmd-preferences .lmd-pref-grid-criteria { display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0; }
.lmd-preferences .lmd-pref-criterion { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.lmd-preferences .lmd-pref-criterion-chip { padding: 6px 12px; border-radius: 6px; background: var(--criterion-color, #e5e7eb); color: #fff; font-size: 13px; font-weight: 500; }
.lmd-preferences .lmd-pref-example { margin: 20px 0; padding: 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.lmd-preferences .lmd-pref-example .lmd-card-tag { display: inline-flex; padding: 4px 10px; border-radius: 6px; font-size: 12px; margin: 0 4px 4px 0; }
.lmd-preferences .lmd-pref-example .tag-message { background: #eff6ff; color: #1d4ed8; }
.lmd-preferences .lmd-pref-example .tag-interet { background: #fefce8; color: #a16207; }
.lmd-preferences .lmd-pref-example .lmd-card-date { color: #6b7280; font-size: 12px; margin: 0 4px; }
.lmd-preferences .lmd-pref-example .tag-source-cp { background: #dbeafe; color: #1d4ed8; }
.lmd-preferences .lmd-pref-excluded-themes { display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0; }
.lmd-preferences .lmd-pref-theme-check { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.lmd-preferences .lmd-ui-section-title { margin-top: 32px; }
.lmd-preferences .lmd-ui-section-title:first-of-type { margin-top: 0; }
.lmd-preferences .lmd-pref-formules { margin: 16px 0; }
.lmd-preferences .lmd-pref-formule-form { margin-top: 12px; padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; }
.lmd-preferences #lmd-pref-formules-list .ed-formule-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; margin: 4px 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; }
.lmd-preferences .lmd-pref-intervals { max-width: 600px; }
.lmd-preferences .lmd-pref-intervals input[type="number"] { width: 80px; }
</style>
<script>
(function($){
    var nonce = typeof lmdAdmin !== 'undefined' ? lmdAdmin.nonce : '';
    var ajaxurl = typeof lmdAdmin !== 'undefined' ? lmdAdmin.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    var intervalIndex = <?php echo count($estimation_opts); ?>;

    var formulesCache = [];
    function loadFormules() {
        if (!ajaxurl || !nonce) return;
        $.post(ajaxurl, { action: 'lmd_list_formules', nonce: nonce }).done(function(r){
            if (r.success && r.data && r.data.formules) {
                formulesCache = r.data.formules;
                var html = '';
                formulesCache.forEach(function(f){
                    html += '<div class="ed-formule-item" data-id="'+f.id+'"><strong>'+f.name+'</strong><span class="ed-formule-actions"><button type="button" class="button ed-edit-formule-pref" data-id="'+f.id+'">Modifier</button> <button type="button" class="button ed-delete-formule-pref" data-id="'+f.id+'">Suppr.</button></span></div>';
                });
                $('#lmd-pref-formules-list').html(html || '<p class="ed-formules-empty">Aucune formule enregistrée.</p>');
            }
        });
    }
    $(document).on('click', '.lmd-pref-add-formule-btn', function(){
        $('#lmd-formule-edit-id').val('');
        $('#lmd-formule-name').val('');
        $('#lmd-formule-content').val('');
        $('#lmd-pref-formule-form').show();
    });
    $(document).on('click', '.lmd-cancel-formule-pref-btn', function(){
        $('#lmd-pref-formule-form').hide();
    });
    $(document).on('click', '.lmd-save-formule-pref-btn', function(){
        var id = $('#lmd-formule-edit-id').val(), name = $('#lmd-formule-name').val(), content = $('#lmd-formule-content').val();
        if (!name) { alert('Nom requis'); return; }
        var data = { action: 'lmd_save_formule', nonce: nonce, name: name, content: content };
        if (id) data.id = id;
        $.post(ajaxurl, data).done(function(r){
            if (r.success) {
                loadFormules();
                $('#lmd-pref-formule-form').hide();
            }
        });
    });
    $(document).on('click', '.ed-edit-formule-pref', function(){
        var id = $(this).data('id');
        var f = formulesCache.filter(function(x){ return x.id == id; })[0];
        if (f) {
            $('#lmd-formule-edit-id').val(f.id);
            $('#lmd-formule-name').val(f.name);
            $('#lmd-formule-content').val(f.content || '');
            $('#lmd-pref-formule-form').show();
        }
    });
    $(document).on('click', '.ed-delete-formule-pref', function(){
        var id = $(this).data('id');
        if (confirm('Supprimer cette formule ?')) {
            $.post(ajaxurl, { action: 'lmd_delete_formule', nonce: nonce, id: id }).done(function(){ loadFormules(); });
        }
    });

    $(document).on('click', '.lmd-add-interval', function(){
        var i = intervalIndex++;
        var row = '<tr><td><input type="text" name="estimation_intervals['+i+'][name]" value="" class="regular-text" placeholder="Ex: &lt; 50 €" /></td><td><input type="number" name="estimation_intervals['+i+'][max]" value="" min="0" step="1" placeholder="—" style="width:80px;" /></td><td><input type="number" name="estimation_intervals['+i+'][min]" value="" min="0" step="1" placeholder="—" style="width:80px;" /></td><td><button type="button" class="button lmd-remove-interval" title="Supprimer">×</button></td></tr>';
        $('#lmd-intervals-tbody').append(row);
    });
    $(document).on('click', '.lmd-remove-interval', function(){
        $(this).closest('tr').remove();
    });

    $(document).ready(function(){ loadFormules(); });
})(jQuery);
</script>
