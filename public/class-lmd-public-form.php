<?php
/**
 * Rendu et logique du formulaire public
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Public_Form
{
    public function render($atts = [])
    {
        $style = $atts["style"] ?? "";
        $titre = $atts["titre"] ?? 'Demande d\'estimation';
        ?>
        <div class="lmd-form-wrapper" data-style="<?php echo esc_attr(
            $style,
        ); ?>">
            <div class="lmd-form-header">
                <h2><?php echo esc_html($titre); ?></h2>
                <p class="lmd-form-intro">Transmettez vos photos et quelques informations utiles. Le commissaire-priseur vous répond avec un premier avis sous 48h.</p>
            </div>
            <div class="lmd-form-card lmd-form-main-card">
            <?php if ($style !== "contact"): ?>
            <div class="lmd-left-section">
                <h3>Comment ça marche</h3>
                <ol>
                    <li>Envoyez vos photos et une description</li>
                    <li>Notre commissaire-priseur analyse</li>
                    <li>Réponse sous 48h</li>
                    <li>Un objet par demande. Vous pouvez multiplier les demandes si vous avez plusieurs objets.</li>
                </ol>
            </div>
            <?php endif; ?>
            <div class="lmd-right-section">
                <form id="lmd-estimation-form" enctype="multipart/form-data">
                    <?php wp_nonce_field("lmd_public", "lmd_nonce"); ?>
                    <p class="lmd-field-photos">
                        <label>Photos</label>
                        <input type="file" name="photos[]" id="lmd-photos" multiple accept="image/*" />
                        <div class="lmd-photos-vignettes" id="lmd-photos-vignettes"></div>
                    </p>
                    <p><label>Description <textarea name="description" rows="4"></textarea></label></p>
                    <p><label>Dimensions <span class="lmd-optional">(L × H × P en cm)</span> <input type="text" name="dimensions" id="lmd-dimensions" placeholder="ex: 30 × 40 × 5" /></label></p>
                    <p class="lmd-field-civility">
                        <label>Civilité</label>
                        <span class="lmd-civility-options">
                            <label><input type="radio" name="civility" value="Monsieur" /> Monsieur</label>
                            <label><input type="radio" name="civility" value="Madame" /> Madame</label>
                        </span>
                    </p>
                    <div class="lmd-form-row lmd-form-row--identity">
                        <p><label>Prénom <input type="text" name="prenom" required /></label></p>
                        <p><label>Nom <input type="text" name="nom" required /></label></p>
                    </div>
                    <div class="lmd-form-row lmd-form-row--contact">
                        <p><label>Email <input type="email" name="email" required /></label></p>
                        <p>
                            <label>
                                <span class="lmd-label-inline">Téléphone <span class="lmd-optional">(facultatif)</span></span>
                                <input type="text" name="telephone" />
                            </label>
                        </p>
                    </div>
                    <p class="lmd-field-cp">
                        <label>Code postal <input type="text" name="code_postal" id="lmd-code-postal" maxlength="5" pattern="[0-9]{5}" placeholder="75001" /></label>
                        <label class="lmd-commune-wrap">Commune <select name="commune" id="lmd-commune"><option value="">— Choisir après code postal —</option></select></label>
                    </p>
                    <p><button type="submit" class="button">Envoyer</button></p>
                </form>
                <div id="lmd-form-message" class="lmd-form-message" style="display:none;"></div>
            </div>
            </div>
        </div>
        <?php
    }

    public function handle_submission()
    {
        if (function_exists("ob_start")) {
            ob_start();
        }
        if (
            isset($_POST["lmd_nonce"]) &&
            !wp_verify_nonce($_POST["lmd_nonce"], "lmd_public")
        ) {
            if (function_exists("ob_end_clean")) {
                ob_end_clean();
            }
            wp_send_json_error(["message" => "Nonce invalide"]);
        }
        $email = isset($_POST["email"])
            ? sanitize_email(wp_unslash($_POST["email"]))
            : (isset($_POST["mail"])
                ? sanitize_email(wp_unslash($_POST["mail"]))
                : "");
        if (empty($email)) {
            if (function_exists("ob_end_clean")) {
                ob_end_clean();
            }
            wp_send_json_error(["message" => "Email requis"]);
        }
        $nom = isset($_POST["nom"])
            ? sanitize_text_field(wp_unslash($_POST["nom"]))
            : (isset($_POST["name"])
                ? sanitize_text_field(wp_unslash($_POST["name"]))
                : "");
        if (empty($nom)) {
            if (function_exists("ob_end_clean")) {
                ob_end_clean();
            }
            wp_send_json_error(["message" => "Nom requis"]);
        }
        $prenom = isset($_POST["prenom"])
            ? sanitize_text_field(wp_unslash($_POST["prenom"]))
            : "";
        $civility = isset($_POST["civility"])
            ? sanitize_text_field(wp_unslash($_POST["civility"]))
            : "";
        $code_postal = isset($_POST["code_postal"])
            ? sanitize_text_field(wp_unslash($_POST["code_postal"]))
            : "";
        $commune = isset($_POST["commune"])
            ? sanitize_text_field(wp_unslash($_POST["commune"]))
            : "";
        $phone = isset($_POST["telephone"])
            ? sanitize_text_field(wp_unslash($_POST["telephone"]))
            : (isset($_POST["phone"])
                ? sanitize_text_field(wp_unslash($_POST["phone"]))
                : "");
        $desc = isset($_POST["description"])
            ? sanitize_textarea_field(wp_unslash($_POST["description"]))
            : "";
        $dimensions = isset($_POST["dimensions"])
            ? sanitize_text_field(wp_unslash($_POST["dimensions"]))
            : "";

        $photo_urls = [];
        $files = $_FILES["photos"] ?? null;
        if (!empty($files["name"]) && is_array($files["name"])) {
            require_once ABSPATH . "wp-admin/includes/file.php";
            require_once ABSPATH . "wp-admin/includes/media.php";
            require_once ABSPATH . "wp-admin/includes/image.php";
            foreach ($files["name"] as $i => $name) {
                if (empty($name) || !empty($files["error"][$i])) {
                    continue;
                }
                $file = [
                    "name" => $files["name"][$i],
                    "type" => $files["type"][$i],
                    "tmp_name" => $files["tmp_name"][$i],
                    "error" => $files["error"][$i],
                    "size" => $files["size"][$i],
                ];
                $upload = wp_handle_upload($file, ["test_form" => false]);
                if (!empty($upload["url"])) {
                    $photo_urls[] = $upload["url"];
                }
            }
        }
        $photos_json = !empty($photo_urls) ? wp_json_encode($photo_urls) : null;

        global $wpdb;
        $cols = $wpdb->get_col("DESCRIBE {$wpdb->prefix}lmd_estimations");
        $insert_data = [
            "site_id" => get_current_blog_id(),
            "client_name" => $nom,
            "client_civility" => in_array(
                $civility,
                ["Monsieur", "Madame"],
                true,
            )
                ? $civility
                : null,
            "client_first_name" => $prenom ?: null,
            "client_email" => $email,
            "client_phone" => $phone ?: null,
            "client_postal_code" => preg_match('/^[0-9]{5}$/', $code_postal)
                ? $code_postal
                : null,
            "client_commune" => $commune ?: null,
            "description" => $desc,
            "photos" => $photos_json,
            "status" => "formulaire_public",
            "source" => "formulaire_public",
        ];
        $insert_fmt = [
            "%d",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
            "%s",
        ];
        if (in_array("dimensions", $cols, true)) {
            $insert_data["dimensions"] = $dimensions ?: null;
            $insert_fmt[] = "%s";
        }
        $wpdb->insert(
            $wpdb->prefix . "lmd_estimations",
            $insert_data,
            $insert_fmt,
        );
        $estimation_id = (int) $wpdb->insert_id;
        if (function_exists("ob_end_clean")) {
            ob_end_clean();
        }
        wp_send_json_success([
            "message" => "Demande envoyée. Nous vous répondrons sous 48h.",
            "id" => $estimation_id,
        ]);
    }
}
