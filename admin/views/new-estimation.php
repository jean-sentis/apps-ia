<?php
/**
 * Vue Nouvelle demande — formulaire + intégration shortcodes
 */
if (!defined('ABSPATH')) {
    exit;
}
$lmd_inner_shell = !empty($lmd_inner_shell);
?>
<?php if (!$lmd_inner_shell) : ?>
<div class="wrap lmd-page lmd-new-estimation">
<?php else : ?>
<div class="lmd-new-estimation lmd-new-estimation--inner">
<?php endif; ?>
    <h1 class="<?php echo $lmd_inner_shell ? 'screen-reader-text' : ''; ?>"><?php esc_html_e('Nouvelle demande', 'lmd-apps-ia'); ?></h1>

    <div class="lmd-dashboard-dash-gutter lmd-new-estimation-dash-gutter">
    <div class="lmd-new-estimation-two-col">
    <div class="lmd-ui-panel lmd-new-estimation-form-panel">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="lmd_submit_estimation_admin" />
        <?php wp_nonce_field('lmd_new_estimation'); ?>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('Civilité', 'lmd-apps-ia'); ?></label></th>
                <td>
                    <label><input type="radio" name="client_civility" value="Monsieur" /> <?php esc_html_e('Monsieur', 'lmd-apps-ia'); ?></label>
                    <label style="margin-left:16px;"><input type="radio" name="client_civility" value="Madame" /> <?php esc_html_e('Madame', 'lmd-apps-ia'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="client_first_name"><?php esc_html_e('Prénom', 'lmd-apps-ia'); ?></label></th>
                <td><input type="text" name="client_first_name" id="client_first_name" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="client_name"><?php esc_html_e('Nom', 'lmd-apps-ia'); ?></label></th>
                <td><input type="text" name="client_name" id="client_name" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="client_postal_code"><?php esc_html_e('Code postal', 'lmd-apps-ia'); ?></label></th>
                <td><input type="text" name="client_postal_code" id="client_postal_code" class="regular-text" maxlength="5" pattern="[0-9]{5}" placeholder="75001" /></td>
            </tr>
            <tr>
                <th><label for="client_commune"><?php esc_html_e('Commune', 'lmd-apps-ia'); ?></label></th>
                <td><select name="client_commune" id="client_commune" class="regular-text"><option value=""><?php esc_html_e('— Choisir après code postal —', 'lmd-apps-ia'); ?></option></select></td>
            </tr>
            <tr>
                <th><label for="client_email"><?php esc_html_e('Email', 'lmd-apps-ia'); ?></label></th>
                <td><input type="email" name="client_email" id="client_email" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="client_phone"><?php esc_html_e('Téléphone', 'lmd-apps-ia'); ?></label><span class="description"> <?php esc_html_e('(facultatif)', 'lmd-apps-ia'); ?></span></th>
                <td><input type="text" name="client_phone" id="client_phone" class="regular-text" /></td>
            </tr>
            <tr class="lmd-new-estimation-desc-row">
                <th><label for="description"><?php esc_html_e('Description', 'lmd-apps-ia'); ?></label></th>
                <td>
                    <div class="lmd-new-estimation-desc-cartouche">
                        <textarea name="description" id="description" rows="10" class="large-text"></textarea>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="photos"><?php esc_html_e('Photos', 'lmd-apps-ia'); ?></label></th>
                <td>
                    <input type="file" name="photos[]" id="photos" multiple accept="image/*" />
                    <div class="lmd-admin-photos-vignettes" id="lmd-admin-photos-vignettes" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;"></div>
                </td>
            </tr>
            <tr>
                <th><label for="dimensions"><?php esc_html_e('Dimensions', 'lmd-apps-ia'); ?></label><span class="description"> <?php esc_html_e('(L × H × P en cm)', 'lmd-apps-ia'); ?></span></th>
                <td><input type="text" name="dimensions" id="dimensions" class="regular-text" placeholder="<?php esc_attr_e('ex : 30 × 40 × 5', 'lmd-apps-ia'); ?>" /></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button button-primary" value="<?php esc_attr_e('Créer l’estimation', 'lmd-apps-ia'); ?>" />
        </p>
    </form>
    </div>
    <aside class="lmd-ui-panel lmd-new-estimation-ref lmd-new-estimation-integration">
        <h2 class="lmd-ui-section-title lmd-new-estimation-integration-title"><?php esc_html_e('Intégration sur votre site', 'lmd-apps-ia'); ?></h2>
        <p class="description lmd-new-estimation-integration-lead"><?php esc_html_e('Shortcodes à placer sur une page (ex. « Demande d’estimation » ou « Contact »).', 'lmd-apps-ia'); ?></p>
        <ul class="lmd-new-estimation-integration-list">
            <li><code>[lmd_formulaire_estimation]</code> — <?php esc_html_e('Formulaire complet', 'lmd-apps-ia'); ?></li>
            <li><code>[lmd_demande_estimation]</code> — <?php esc_html_e('Alias du formulaire', 'lmd-apps-ia'); ?></li>
            <li><code>[lmd_demande_estimation style="contact"]</code> — <?php esc_html_e('Formulaire compact (page contact)', 'lmd-apps-ia'); ?></li>
        </ul>
    </aside>
    </div>
    <style>
    .lmd-new-estimation-two-col {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: 28px;
        align-items: start;
    }
    .lmd-new-estimation-form-panel { min-width: 0; }
    .lmd-new-estimation-desc-row th { vertical-align: top; padding-top: 12px; }
    .lmd-new-estimation-desc-cartouche {
        max-width: 380px;
        width: 100%;
        padding: 16px 18px 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-sizing: border-box;
    }
    .lmd-new-estimation-desc-cartouche textarea {
        width: 100%;
        max-width: 100%;
        min-height: 200px;
        margin: 0;
        box-sizing: border-box;
        resize: vertical;
        background: #fff;
    }
    .lmd-new-estimation-integration {
        position: sticky;
        top: 32px;
        min-width: 0;
        padding: 22px 22px 26px;
        display: flex;
        flex-direction: column;
        gap: 18px;
        box-sizing: border-box;
    }
    .lmd-new-estimation-integration-title { margin: 0; line-height: 1.3; }
    .lmd-new-estimation-integration-lead { margin: 0 !important; line-height: 1.6; font-size: 13px; }
    .lmd-new-estimation-integration-list {
        margin: 0;
        padding: 0 0 0 1.2em;
        font-size: 13px;
        line-height: 1.65;
        list-style-position: outside;
    }
    .lmd-new-estimation-integration-list li { margin: 0 0 12px; padding: 0; }
    .lmd-new-estimation-integration-list li:last-child { margin-bottom: 0; }
    .lmd-new-estimation-integration-list code { font-size: 12px; }
    @media (max-width: 960px) {
        .lmd-new-estimation-two-col { grid-template-columns: 1fr !important; }
        .lmd-new-estimation-desc-cartouche { max-width: none; }
    }
    </style>
    </div>
</div>
