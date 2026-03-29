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
$bcc_exclude_options = function_exists('lmd_get_bcc_exclude_response_options') ? lmd_get_bcc_exclude_response_options() : [];
$bcc_exclude_saved = $prefs['bcc_exclude_response_slugs'] ?? [];
$bcc_exclude_saved = is_array($bcc_exclude_saved) ? $bcc_exclude_saved : [];

$estimation_opts = [];
if (function_exists('lmd_get_custom_category_options')) {
    $estimation_opts = lmd_get_custom_category_options('estimation');
}
if (empty($estimation_opts) && function_exists('lmd_get_default_estimation_options')) {
    $estimation_opts = lmd_get_default_estimation_options();
}

$criteria_labels = [
    'message' => ['label' => 'Échanges (Nouveau, Répondu, Déposé, Vendu…)', 'color' => '#1d4ed8'],
    'interet' => ['label' => 'Intérêt', 'color' => '#a16207'],
    'estimation' => ['label' => 'Estimation (montant ou palier)', 'color' => '#6b7280'],
    'theme' => ['label' => 'Catégorie / Thème', 'color' => '#374151'],
    'date' => ['label' => 'Date d\'envoi', 'color' => '#6b7280'],
    'cp_avis2' => ['label' => 'CP / 2e avis', 'color' => '#1d4ed8'],
];

$lmd_prefs_embed = !empty($lmd_prefs_embed);
?>
<?php if (!$lmd_prefs_embed) : ?>
<div class="wrap lmd-preferences lmd-page">
    <h1><?php esc_html_e('Réglage affichages et réponses vendeurs', 'lmd-apps-ia'); ?></h1>
<?php else : ?>
<div class="lmd-preferences lmd-preferences--embed">
<?php endif; ?>

    <form id="lmd-prefs-form" class="lmd-prefs-form" method="post" action="#" onsubmit="return false;">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('lmd_admin')); ?>" />
        <p id="lmd-pref-autosave-status" class="lmd-pref-autosave-status" aria-live="polite" hidden></p>

        <p class="lmd-pref-megasection" role="heading" aria-level="2"><?php esc_html_e('AFFICHAGES', 'lmd-apps-ia'); ?></p>

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

        <h2 class="lmd-ui-section-title lmd-ui-section-title--tight-top"><?php esc_html_e('Intervalles d\'estimation', 'lmd-apps-ia'); ?></h2>
        <p class="description"><?php esc_html_e('Définissez les paliers affichés dans les filtres et sur les vignettes (&lt; 25 €, &lt; 100 €, etc.).', 'lmd-apps-ia'); ?></p>
        <input type="hidden" name="lmd_prefs_include_intervals" value="1" />
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
        <p class="lmd-pref-intervals-add-wrap"><button type="button" class="button lmd-add-interval"><?php esc_html_e('Ajouter un palier', 'lmd-apps-ia'); ?></button></p>

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

        <p class="lmd-pref-megasection lmd-pref-megasection--reponses" role="heading" aria-level="2"><?php esc_html_e('RÉPONSES VENDEUR', 'lmd-apps-ia'); ?></p>

        <h2 class="lmd-ui-section-title lmd-ui-section-title--after-megasection"><?php esc_html_e('Votre réponse au vendeur en copie cachée pour vous', 'lmd-apps-ia'); ?></h2>
        <div class="lmd-pref-cc-layout <?php echo !empty($bcc_exclude_options) ? 'lmd-pref-cc-layout--split' : 'lmd-pref-cc-layout--single'; ?>">
            <div class="lmd-pref-cc-col-left">
                <div class="lmd-pref-cc-field">
                    <label class="lmd-pref-cc-label" for="cp_copy_email_1"><?php esc_html_e('Email 1', 'lmd-apps-ia'); ?></label>
                    <input type="email" id="cp_copy_email_1" name="cp_copy_email_1" value="<?php echo esc_attr($copy1); ?>" class="regular-text lmd-pref-cc-input" placeholder="<?php esc_attr_e('email1@exemple.fr', 'lmd-apps-ia'); ?>" />
                </div>
                <div class="lmd-pref-cc-field">
                    <label class="lmd-pref-cc-label" for="cp_copy_email_2"><?php esc_html_e('Email 2', 'lmd-apps-ia'); ?></label>
                    <input type="email" id="cp_copy_email_2" name="cp_copy_email_2" value="<?php echo esc_attr($copy2); ?>" class="regular-text lmd-pref-cc-input" placeholder="<?php esc_attr_e('email2@exemple.fr', 'lmd-apps-ia'); ?>" />
                </div>
                <div class="lmd-pref-cc-field lmd-pref-cc-field-signature">
                    <label class="lmd-pref-cc-label" for="cp_signature"><?php esc_html_e('Cartouche de réponse', 'lmd-apps-ia'); ?></label>
                    <p class="description lmd-pref-cc-sig-hint"><?php esc_html_e('HTML autorisé (liens, images avec URL absolue, mise en forme). À l’envoi via le bouton Envoi sur la fiche estimation, le message ouvert dans votre messagerie est en texte : les liens et les adresses d’images sont recopiés en clair pour rester utilisables.', 'lmd-apps-ia'); ?></p>
                    <textarea id="cp_signature" name="cp_signature" rows="8" class="large-text lmd-pref-cc-textarea"><?php echo esc_textarea($cp['signature'] ?? ''); ?></textarea>
                </div>
            </div>
            <?php if (!empty($bcc_exclude_options)) : ?>
            <div class="lmd-pref-cc-col-right">
                <p class="lmd-pref-bcc-exclude-intro"><strong><?php esc_html_e('Quelle catégorie de réponse ne voulez-vous pas recevoir en copie ?', 'lmd-apps-ia'); ?></strong></p>
                <div class="lmd-pref-bcc-exclude">
                    <?php foreach ($bcc_exclude_options as $slug => $label) : ?>
                    <label class="lmd-pref-bcc-exclude-item">
                        <input type="checkbox" name="bcc_exclude[<?php echo esc_attr($slug); ?>]" value="1" <?php checked(in_array($slug, $bcc_exclude_saved, true)); ?> />
                        <?php echo esc_html($label); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <h2 class="lmd-ui-section-title"><?php esc_html_e('Formules pour répondre aux vendeurs', 'lmd-apps-ia'); ?></h2>
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
    </form>
</div>
<style>
.lmd-preferences .lmd-pref-grid-criteria { display: flex; flex-wrap: wrap; gap: 12px; margin: 20px 0 22px; }
.lmd-preferences .lmd-pref-criterion { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.lmd-preferences .lmd-pref-criterion-chip { padding: 6px 12px; border-radius: 6px; background: var(--criterion-color, #e5e7eb); color: #fff; font-size: 13px; font-weight: 500; }
.lmd-preferences .lmd-pref-example { margin: 24px 0 14px; padding: 18px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.lmd-preferences .lmd-pref-example .lmd-card-tag { display: inline-flex; padding: 4px 10px; border-radius: 6px; font-size: 12px; margin: 0 4px 4px 0; }
.lmd-preferences .lmd-pref-example .tag-message { background: #eff6ff; color: #1d4ed8; }
.lmd-preferences .lmd-pref-example .tag-interet { background: #fefce8; color: #a16207; }
.lmd-preferences .lmd-pref-example .lmd-card-date { color: #6b7280; font-size: 12px; margin: 0 4px; }
.lmd-preferences .lmd-pref-example .tag-source-cp { background: #dbeafe; color: #1d4ed8; }
.lmd-preferences .lmd-pref-excluded-themes { display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0 20px; }
.lmd-preferences .lmd-pref-theme-check { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.lmd-preferences .lmd-pref-megasection {
    margin: 0 0 1.35rem;
    padding: 0.85rem 1rem 1rem;
    text-align: center;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #0f172a;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}
.lmd-preferences .lmd-pref-megasection--reponses {
    margin-top: 3.25rem;
    margin-bottom: 1.5rem;
}
.lmd-preferences .lmd-ui-section-title {
    margin-top: 2.85rem;
    padding-top: 1.35rem;
    border-top: 1px solid #e5e7eb;
    clear: both;
}
.lmd-preferences .lmd-pref-megasection + .lmd-ui-section-title {
    margin-top: 0.35rem;
    padding-top: 0;
    border-top: 0;
}
.lmd-preferences .lmd-ui-section-title--tight-top {
    margin-top: 1.35rem !important;
    padding-top: 0.85rem !important;
    border-top: 1px dashed #cbd5e1 !important;
}
.lmd-preferences .lmd-ui-section-title--after-megasection {
    margin-top: 0.5rem !important;
    padding-top: 0 !important;
    border-top: 0 !important;
}
.lmd-preferences .lmd-pref-megasection + .lmd-ui-section-title--after-megasection {
    margin-top: 0.5rem !important;
}
.lmd-preferences .description { margin-top: 0; margin-bottom: 1rem; line-height: 1.55; }
.lmd-preferences h2.lmd-ui-section-title + .description { margin-top: 0.35rem; margin-bottom: 1.15rem; }
.lmd-preferences .form-table { margin-top: 0.5rem; margin-bottom: 1.5rem; }
.lmd-preferences .form-table th { padding: 14px 10px 12px 0; vertical-align: top; }
.lmd-preferences .form-table td { padding: 12px 10px 14px 0; }
.lmd-preferences .form-table + .description { margin-top: 0.75rem; }
.lmd-preferences .lmd-pref-cc-layout { display: grid; gap: 1.25rem 2rem; align-items: start; margin: 16px 0 12px; }
.lmd-preferences .lmd-pref-cc-layout--split { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); }
.lmd-preferences .lmd-pref-cc-layout--single { grid-template-columns: minmax(0, 1fr); max-width: 28rem; }
@media (max-width: 782px) {
    .lmd-preferences .lmd-pref-cc-layout--split { grid-template-columns: 1fr; }
}
.lmd-preferences .lmd-pref-cc-col-left { min-width: 0; }
.lmd-preferences .lmd-pref-cc-field { margin-bottom: 14px; }
.lmd-preferences .lmd-pref-cc-field:last-child { margin-bottom: 0; }
.lmd-preferences .lmd-pref-cc-field-signature { margin-top: 8px; padding-top: 12px; border-top: 1px solid #e5e7eb; }
.lmd-preferences .lmd-pref-cc-label { display: block; font-weight: 600; margin-bottom: 6px; }
.lmd-preferences .lmd-pref-cc-input,
.lmd-preferences .lmd-pref-cc-textarea { width: 100%; max-width: 100%; box-sizing: border-box; }
.lmd-preferences .lmd-pref-cc-sig-hint { margin: 0 0 10px; font-size: 12px; line-height: 1.5; max-width: 36rem; }
.lmd-preferences .lmd-pref-cc-col-right { min-width: 0; }
.lmd-preferences .lmd-pref-cc-col-right .lmd-pref-bcc-exclude-intro { margin: 0 0 12px; max-width: none; }
.lmd-preferences .lmd-pref-cc-col-right .lmd-pref-bcc-exclude {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px 16px;
    margin: 0;
    align-items: start;
}
.lmd-preferences .lmd-pref-cc-col-right .lmd-pref-bcc-exclude-item { display: flex; align-items: flex-start; gap: 8px; cursor: pointer; }
.lmd-preferences .lmd-pref-formules { margin: 18px 0 22px; }
.lmd-preferences .lmd-pref-formules-add { margin-top: 0.5rem; }
.lmd-preferences .lmd-pref-formule-form { margin-top: 16px; padding: 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; }
.lmd-preferences .lmd-pref-formule-form p { margin: 0 0 12px; }
.lmd-preferences .lmd-pref-formule-form p:last-child { margin-bottom: 0; }
.lmd-preferences #lmd-pref-formules-list .ed-formule-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; margin: 4px 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; }
.lmd-preferences .lmd-pref-intervals { max-width: 600px; margin-top: 0.75rem; margin-bottom: 1rem; }
.lmd-preferences .lmd-pref-intervals th,
.lmd-preferences .lmd-pref-intervals td { padding: 10px 12px 10px 0; }
.lmd-preferences .lmd-pref-intervals input[type="number"] { width: 80px; }
.lmd-preferences .lmd-pref-intervals + .lmd-pref-intervals-add-wrap { margin-top: 0.35rem; margin-bottom: 0.75rem; }
.lmd-preferences .lmd-pref-autosave-status { margin: 0 0 12px; font-size: 13px; color: #15803d; min-height: 1.25em; }
.lmd-preferences .lmd-pref-autosave-status.is-error { color: #b91c1c; }
</style>
<script>
(function($){
    /* Ce script est souvent exécuté avant wp_footer : lmdAdmin n’existe pas encore — fallback PHP obligatoire. */
    var lmdPrefsAjaxUrl = (typeof lmdAdmin !== 'undefined' && lmdAdmin.ajaxurl) ? lmdAdmin.ajaxurl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var lmdPrefsNonce = (typeof lmdAdmin !== 'undefined' && lmdAdmin.nonce) ? lmdAdmin.nonce : '<?php echo esc_js(wp_create_nonce('lmd_admin')); ?>';
    var intervalIndex = <?php echo count($estimation_opts); ?>;

    var formulesCache = [];
    function escAttr(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }
    function loadFormules() {
        if (!lmdPrefsAjaxUrl || !lmdPrefsNonce) return;
        $.post(lmdPrefsAjaxUrl, { action: 'lmd_list_formules', nonce: lmdPrefsNonce }).done(function(r){
            if (r.success && r.data && Array.isArray(r.data.formules)) {
                formulesCache = r.data.formules;
                var html = '';
                formulesCache.forEach(function(f){
                    html += '<div class="ed-formule-item" data-id="'+escAttr(f.id)+'"><strong>'+escAttr(f.name)+'</strong><span class="ed-formule-actions"><button type="button" class="button ed-edit-formule-pref" data-id="'+escAttr(f.id)+'">Modifier</button> <button type="button" class="button ed-delete-formule-pref" data-id="'+escAttr(f.id)+'">Suppr.</button></span></div>';
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
        var data = { action: 'lmd_save_formule', nonce: lmdPrefsNonce, name: name, content: content };
        if (id) data.id = id;
        $.post(lmdPrefsAjaxUrl, data).done(function(r){
            if (r.success) {
                loadFormules();
                $('#lmd-pref-formule-form').hide();
            } else {
                alert((r.data && r.data.message) ? r.data.message : 'Enregistrement impossible.');
            }
        }).fail(function(){ alert('Enregistrement impossible (réseau ou session).'); });
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
            $.post(lmdPrefsAjaxUrl, { action: 'lmd_delete_formule', nonce: lmdPrefsNonce, id: id }).done(function(){ loadFormules(); });
        }
    });

    $(document).on('click', '.lmd-add-interval', function(){
        var i = intervalIndex++;
        var row = '<tr><td><input type="text" name="estimation_intervals['+i+'][name]" value="" class="regular-text" placeholder="Ex: &lt; 50 €" /></td><td><input type="number" name="estimation_intervals['+i+'][max]" value="" min="0" step="1" placeholder="—" style="width:80px;" /></td><td><input type="number" name="estimation_intervals['+i+'][min]" value="" min="0" step="1" placeholder="—" style="width:80px;" /></td><td><button type="button" class="button lmd-remove-interval" title="Supprimer">×</button></td></tr>';
        $('#lmd-intervals-tbody').append(row);
    });
    $(document).on('click', '.lmd-remove-interval', function(){
        $(this).closest('tr').remove();
        lmdPrefsScheduleSave();
    });

    var prefSaveTimer = null;
    function lmdPrefsDoSave() {
        var $form = $('#lmd-prefs-form');
        var $st = $('#lmd-pref-autosave-status');
        if (!$form.length || !$st.length) return;
        var ajaxu = (typeof lmdAdmin !== 'undefined' && lmdAdmin.ajaxurl) ? lmdAdmin.ajaxurl : lmdPrefsAjaxUrl;
        if (!ajaxu) return;
        var data = $form.serialize() + '&action=lmd_save_preferences';
        $st.removeClass('is-error').removeAttr('hidden').text(<?php echo wp_json_encode(__('Enregistrement…', 'lmd-apps-ia')); ?>);
        $.post(ajaxu, data).done(function(r){
            if (r.success) {
                $st.text(<?php echo wp_json_encode(__('Enregistré', 'lmd-apps-ia')); ?>);
                setTimeout(function(){ $st.attr('hidden', 'hidden').text(''); }, 1800);
            } else {
                $st.addClass('is-error').text((r.data && r.data.message) ? r.data.message : <?php echo wp_json_encode(__('Erreur d’enregistrement', 'lmd-apps-ia')); ?>);
            }
        }).fail(function(){
            $st.addClass('is-error').text(<?php echo wp_json_encode(__('Erreur d’enregistrement', 'lmd-apps-ia')); ?>);
        });
    }
    function lmdPrefsScheduleSave() {
        clearTimeout(prefSaveTimer);
        prefSaveTimer = setTimeout(lmdPrefsDoSave, 500);
    }
    $(document).ready(function(){
        loadFormules();
        var $pf = $('#lmd-prefs-form');
        if ($pf.length) {
            $pf.on('change', 'input, select, textarea', lmdPrefsScheduleSave);
            $pf.on('input', 'textarea, input[type="number"], input[type="email"], input[type="text"]', lmdPrefsScheduleSave);
        }
    });
})(jQuery);
</script>
