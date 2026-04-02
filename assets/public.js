/**
 * Script formulaire public
 */
(function ($) {
  "use strict";
  $(document).ready(function () {
    // Code postal -> Communes (API geo.api.gouv.fr) - formulaire public et admin
    function initCodePostalCommune(cpId, communeId) {
      var $cp = $(cpId);
      var $commune = $(communeId);
      if (!$cp.length || !$commune.length) return;
      function fetchCommunes() {
        var cp = $cp.val().replace(/\D/g, "").slice(0, 5);
        if (cp.length !== 5) {
          $commune.find("option:gt(0)").remove();
          return;
        }
        $.getJSON(
          "https://geo.api.gouv.fr/communes?codePostal=" + cp + "&fields=nom",
        )
          .done(function (communes) {
            var $opts = $commune.find("option:first").clone();
            $commune.find("option").remove();
            $commune.append($opts);
            if (communes && communes.length) {
              $.each(communes, function (i, c) {
                $commune.append($("<option>").val(c.nom).text(c.nom));
              });
              if (communes.length === 1) {
                $commune.val(communes[0].nom);
              }
            } else {
              $commune.append(
                $("<option>").val("").text("Aucune commune trouvée"),
              );
            }
          })
          .fail(function () {
            $commune.find("option:gt(0)").remove();
          });
      }
      var inputTimer;
      $cp.on("input", function () {
        var cp = $(this).val().replace(/\D/g, "").slice(0, 5);
        if (cp.length !== 5) {
          $commune.find("option:gt(0)").remove();
          return;
        }
        clearTimeout(inputTimer);
        inputTimer = setTimeout(fetchCommunes, 400);
      });
      $cp.on("blur", function () {
        var cp = $(this).val().replace(/\D/g, "").slice(0, 5);
        if (cp.length === 5) {
          clearTimeout(inputTimer);
          fetchCommunes();
        }
      });
    }
    initCodePostalCommune("#lmd-code-postal", "#lmd-commune");
    initCodePostalCommune("#client_postal_code", "#client_commune");

    // Vignettes des fichiers sélectionnés
    var $photosInput = $("#lmd-photos");
    var $vignettes = $("#lmd-photos-vignettes");
    if ($photosInput.length && $vignettes.length) {
      $photosInput.on("change", function () {
        $vignettes.empty();
        var files = this.files;
        for (var i = 0; i < files.length; i++) {
          var f = files[i];
          if (!f.type.match(/^image\//)) continue;
          var reader = new FileReader();
          reader.onload = (function (idx) {
            return function (e) {
              var $img = $("<img>")
                .attr("src", e.target.result)
                .addClass("lmd-vignette-img");
              var $wrap = $("<div>").addClass("lmd-vignette-wrap").append($img);
              $vignettes.append($wrap);
            };
          })(i);
          reader.readAsDataURL(f);
        }
      });
    }

    $("#lmd-estimation-form").on("submit", function (e) {
      e.preventDefault();
      var $form = $(this);
      var $msg = $("#lmd-form-message");
      var $submit = $form.find('button[type="submit"]');
      var data = new FormData($form[0]);
      data.append("action", "lmd_submit_estimation");
      if (typeof lmdPublic !== "undefined" && lmdPublic.nonce) {
        data.append("lmd_nonce", lmdPublic.nonce);
      }
      $msg.removeClass("is-success is-error").hide().text("");
      $submit.prop("disabled", true);
      $.ajax({
        url: typeof lmdPublic !== "undefined" ? lmdPublic.ajaxurl : "",
        type: "POST",
        data: data,
        processData: false,
        contentType: false,
      })
        .done(function (r) {
          $msg
            .removeClass("is-success is-error")
            .addClass(r.success ? "is-success" : "is-error")
            .show()
            .text(
              r.data && r.data.message
                ? r.data.message
                : r.success
                  ? "Envoyé !"
                  : "Erreur",
            );
          if (r.success) {
            $form[0].reset();
            $("#lmd-photos-vignettes").empty();
            $("#lmd-commune").find("option:gt(0)").remove();
          }
        })
        .fail(function () {
          $msg
            .removeClass("is-success")
            .addClass("is-error")
            .show()
            .text("Erreur réseau. Réessayez.");
        })
        .always(function () {
          $submit.prop("disabled", false);
        });
    });
  });
})(jQuery);
