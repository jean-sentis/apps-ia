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

    function lmdGetExpertiseMessage(key, fallback) {
      if (
        typeof lmdPublic !== "undefined" &&
        lmdPublic.expertiseMessages &&
        lmdPublic.expertiseMessages[key]
      ) {
        return lmdPublic.expertiseMessages[key];
      }

      return fallback;
    }

    function lmdHasExpertiseText(text) {
      var value = String(text || "").trim().toLowerCase();
      return (
        value !== "" &&
        value !== "null" &&
        value !== "aucun" &&
        value !== "aucune" &&
        value !== "non renseigné" &&
        value !== "non renseigne"
      );
    }

    function lmdFormatExpertiseDate(value) {
      if (!value) return "";

      var normalized = String(value).replace(" ", "T");
      var date = new Date(normalized);
      if (Number.isNaN(date.getTime())) {
        return String(value);
      }

      return date.toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      });
    }

    function lmdAppendExpertiseParagraphs($section, paragraphs) {
      paragraphs.forEach(function (paragraph) {
        $section.append($("<p>").text(paragraph));
      });
    }

    function lmdRenderExpertiseResult($result, payload, meta) {
      $result.empty().removeClass("is-error is-loading").addClass("is-ready");

      var paragraphs = lmdTextToParagraphs(payload.explication);
      if (!paragraphs.length) {
        paragraphs = [
          lmdGetExpertiseMessage(
            "empty",
            "Aucune analyse disponible pour ce lot.",
          ),
        ];
      }

      var $article = $("<article>").addClass("lmd-expertise-card");
      var $header = $("<div>").addClass("lmd-expertise-header");
      var $kicker = $("<p>").addClass("lmd-expertise-kicker").text("Expertise IA");
      var $title = $("<h3>").text("Avis sur le lot");
      var metaParts = [
        meta && meta.cached ? "Analyse déjà disponible" : "Analyse générée",
      ];
      var generatedAt = lmdFormatExpertiseDate(meta && meta.generated_at);
      if (generatedAt) {
        metaParts.push(generatedAt);
      }

      $header.append(
        $kicker,
        $title,
        $("<p>").addClass("lmd-expertise-meta").text(metaParts.join(" · ")),
      );

      var $explanation = $("<section>").addClass("lmd-expertise-section");
      $explanation.append($("<h4>").text("Explication"));
      lmdAppendExpertiseParagraphs($explanation, paragraphs);

      $article.append($header, $explanation);

      if (lmdHasExpertiseText(payload.createur)) {
        var creatorParagraphs = lmdTextToParagraphs(payload.createur);
        if (creatorParagraphs.length) {
          var $creator = $("<section>").addClass("lmd-expertise-section");
          $creator.append($("<h4>").text("Créateur, atelier ou manufacture"));
          lmdAppendExpertiseParagraphs($creator, creatorParagraphs);
          $article.append($creator);
        }
      }

      $result.append($article);
    }

    function lmdRenderExpertiseMessage($result, message, type) {
      var stateClass = "is-loading";
      if (type === "error") {
        stateClass = "is-error";
      } else if (type === "ready") {
        stateClass = "is-ready";
      }

      $result
        .empty()
        .removeClass("is-ready is-loading is-error")
        .addClass(stateClass)
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

    function lmdSetExpertiseTriggerBusy($trigger, isBusy) {
      if (isBusy) {
        $trigger
          .data("lmdExpertiseInFlight", true)
          .addClass("lmd-expertise-trigger--loading")
          .attr("aria-busy", "true")
          .attr("aria-disabled", "true");

        if ($trigger.is("button,input,select,textarea")) {
          $trigger.prop("disabled", true);
        }

        return;
      }

      $trigger
        .removeData("lmdExpertiseInFlight")
        .removeClass("lmd-expertise-trigger--loading")
        .removeAttr("aria-busy")
        .removeAttr("aria-disabled");

      if ($trigger.is("button,input,select,textarea")) {
        $trigger.prop("disabled", false);
      }
    }

    function runLotExpertiseRequest($trigger) {
      if ($trigger.data("lmdExpertiseInFlight")) return;

      var context = lmdGetExpertiseContext($trigger);
      var loadingMessage = lmdGetExpertiseMessage(
        "loading",
        "Analyse en cours...",
      );
      var errorMessage = lmdGetExpertiseMessage(
        "error",
        "Impossible de générer l'analyse IA pour le moment.",
      );

      if (!context.lotId) {
        lmdRenderExpertiseMessage(
          context.$result,
          lmdGetExpertiseMessage("lotMissing", "Lot introuvable."),
          "error",
        );
        return;
      }

      lmdSetExpertiseTriggerBusy($trigger, true);
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
              : "";

          if (!message && xhr.status === 429) {
            message = lmdGetExpertiseMessage(
              "rateLimited",
              "Trop de demandes d'analyse IA. Réessayez dans quelques instants.",
            );
          } else if (!message && xhr.status === 409) {
            message = lmdGetExpertiseMessage(
              "processing",
              "Une analyse IA est déjà en cours pour ce lot.",
            );
          } else if (!message && xhr.status === 403) {
            message = lmdGetExpertiseMessage(
              "disabled",
              "Le service Expertise IA est désactivé sur ce site.",
            );
          } else if (!message) {
            message = lmdGetExpertiseMessage(
              "network",
              "Erreur réseau. Réessayez dans quelques instants.",
            );
          }

          if (!message) {
            message = errorMessage;
          }

          lmdRenderExpertiseMessage(context.$result, message, "error");
        })
        .always(function () {
          lmdSetExpertiseTriggerBusy($trigger, false);
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
