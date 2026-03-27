<?php
/**
 * Vue Nouvelle estimation
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap lmd-page">
    <h1>Nouvelle demande</h1>
    <p class="lmd-ui-prose">Saisie vendeur — présentation alignée sur le reste de l’aide à l’estimation.</p>
    <div class="lmd-ui-panel">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="lmd_submit_estimation_admin" />
        <?php wp_nonce_field('lmd_new_estimation'); ?>
        <table class="form-table">
            <tr>
                <th><label>Civilité</label></th>
                <td>
                    <label><input type="radio" name="client_civility" value="Monsieur" /> Monsieur</label>
                    <label style="margin-left:16px;"><input type="radio" name="client_civility" value="Madame" /> Madame</label>
                </td>
            </tr>
            <tr>
                <th><label for="client_first_name">Prénom</label></th>
                <td><input type="text" name="client_first_name" id="client_first_name" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="client_name">Nom</label></th>
                <td><input type="text" name="client_name" id="client_name" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="client_postal_code">Code postal</label></th>
                <td><input type="text" name="client_postal_code" id="client_postal_code" class="regular-text" maxlength="5" pattern="[0-9]{5}" placeholder="75001" /></td>
            </tr>
            <tr>
                <th><label for="client_commune">Commune</label></th>
                <td><select name="client_commune" id="client_commune" class="regular-text"><option value="">— Choisir après code postal —</option></select></td>
            </tr>
            <tr>
                <th><label for="client_email">Email</label></th>
                <td><input type="email" name="client_email" id="client_email" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="client_phone">Téléphone</label><span class="description"> (facultatif)</span></th>
                <td><input type="text" name="client_phone" id="client_phone" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="description">Description</label></th>
                <td><textarea name="description" id="description" rows="5" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label for="photos">Photos</label></th>
                <td>
                    <input type="file" name="photos[]" id="photos" multiple accept="image/*" />
                    <div class="lmd-admin-photos-vignettes" id="lmd-admin-photos-vignettes" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;"></div>
                </td>
            </tr>
            <tr>
                <th><label for="dimensions">Dimensions</label><span class="description"> (L × H × P en cm)</span></th>
                <td><input type="text" name="dimensions" id="dimensions" class="regular-text" placeholder="ex: 30 × 40 × 5" /></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button button-primary" value="Créer l'estimation" />
        </p>
    </form>
    </div>
</div>
