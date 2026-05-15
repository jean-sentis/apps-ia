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

    function lmdTextToParagraphs(text) {
      return String(text || "")
        .split(/\n{2,}/)
        .map(function (part) {
          return part.trim();
        })
        .filter(Boolean);
    }

    function lmdRenderExpertiseResult($result, payload, meta) {
      $result.empty().removeClass("is-error is-loading").addClass("is-ready");

      var $title = $("<h3>").text("Avis de l'expert IA");
      var $body = $("<div>").addClass("lmd-expertise-section");
      $body.append($("<h4>").text("Explication"));

      var paragraphs = lmdTextToParagraphs(payload.explication);
      if (!paragraphs.length) {
        paragraphs = [
          typeof lmdPublic !== "undefined" && lmdPublic.expertiseMessages
            ? lmdPublic.expertiseMessages.empty
            : "Aucune analyse disponible pour ce lot.",
        ];
      }
      paragraphs.forEach(function (paragraph) {
        $body.append($("<p>").text(paragraph));
      });

      if (payload.createur) {
        var $creator = $("<div>").addClass("lmd-expertise-section");
        $creator.append($("<h4>").text("Créateur, atelier ou manufacture"));
        lmdTextToParagraphs(payload.createur).forEach(function (paragraph) {
          $creator.append($("<p>").text(paragraph));
        });
        $body.append($creator);
      }

      if (meta && meta.cached) {
        $result.append($("<p>").addClass("lmd-expertise-meta").text("Analyse déjà disponible."));
      }
      $result.append($title, $body);
    }

    function lmdRenderExpertiseMessage($result, message, type) {
      $result
        .empty()
        .removeClass("is-ready is-loading is-error")
        .addClass(type === "error" ? "is-error" : "is-loading")
        .append($("<p>").text(message));
    }

    function lmdGetExpertiseContext($trigger) {
      var lotId =
        parseInt($trigger.attr("data-lot-id"), 10) ||
        (typeof lmdPublic !== "undefined" ? parseInt(lmdPublic.lotId, 10) : 0);
      var targetSelector = $trigger.attr("data-result-target");
      var $result = targetSelector ? $(targetSelector).first() : $("#ai-response").first();

      if (!$result.length) {
        $result = $('<div id="lmd-ai-analysis-result" class="lmd-expertise-result" aria-live="polite"></div>');
        $trigger.after($result);
      } else {
        $result.addClass("lmd-expertise-result").attr("aria-live", "polite");
      }

      if (!$trigger.attr("role")) {
        $trigger.attr("role", "button");
      }
      if (!$trigger.attr("tabindex")) {
        $trigger.attr("tabindex", "0");
      }

      return {
        lotId: lotId,
        $result: $result,
      };
    }

    function runLotExpertiseRequest($trigger) {
      if ($trigger.data("lmdExpertiseInFlight")) return;

      var context = lmdGetExpertiseContext($trigger);
      var loadingMessage =
        typeof lmdPublic !== "undefined" && lmdPublic.expertiseMessages
          ? lmdPublic.expertiseMessages.loading
          : "Analyse en cours...";
      var errorMessage =
        typeof lmdPublic !== "undefined" && lmdPublic.expertiseMessages
          ? lmdPublic.expertiseMessages.error
          : "Impossible de générer l'analyse IA pour le moment.";

      if (!context.lotId) {
        lmdRenderExpertiseMessage(context.$result, "Lot introuvable.", "error");
        return;
      }

      $trigger
        .data("lmdExpertiseInFlight", true)
        .addClass("lmd-expertise-trigger--loading")
        .attr("aria-busy", "true");
      lmdRenderExpertiseMessage(context.$result, loadingMessage, "loading");

      $.ajax({
        url: typeof lmdPublic !== "undefined" ? lmdPublic.ajaxurl : "",
        type: "POST",
        data: {
          action:
            typeof lmdPublic !== "undefined" && lmdPublic.expertiseAction
              ? lmdPublic.expertiseAction
              : "lmd_generate_lot_expertise",
          lmd_nonce: typeof lmdPublic !== "undefined" ? lmdPublic.nonce : "",
          lot_id: context.lotId,
        },
      })
        .done(function (response) {
          if (response && response.success && response.data && response.data.payload) {
            lmdRenderExpertiseResult(context.$result, response.data.payload, response.data);
            return;
          }
          lmdRenderExpertiseMessage(
            context.$result,
            response && response.data && response.data.message
              ? response.data.message
              : errorMessage,
            "error",
          );
        })
        .fail(function (xhr) {
          var message =
            xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
              ? xhr.responseJSON.data.message
              : errorMessage;
          lmdRenderExpertiseMessage(context.$result, message, "error");
        })
        .always(function () {
          $trigger
            .removeData("lmdExpertiseInFlight")
            .removeClass("lmd-expertise-trigger--loading")
            .removeAttr("aria-busy");
        });
    }

    function initLotExpertiseTrigger() {
      $(document)
        .off("click.lmdExpertise", "#trigger-ai-analysis")
        .on("click.lmdExpertise", "#trigger-ai-analysis", function (e) {
          e.preventDefault();
          runLotExpertiseRequest($(this));
        });

      $(document)
        .off("keydown.lmdExpertise", "#trigger-ai-analysis")
        .on("keydown.lmdExpertise", "#trigger-ai-analysis", function (e) {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            runLotExpertiseRequest($(this));
          }
        });

      $("#trigger-ai-analysis").each(function () {
        lmdGetExpertiseContext($(this));
      });
    }

    initLotExpertiseTrigger();
  });
})(jQuery);
