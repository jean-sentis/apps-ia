<?php
/**
 * Enrichissement SEO Gemini-only pour les CPT lot.
 *
 * @package LMD_Module1
 */

if (!defined("ABSPATH")) {
    exit();
}

class LMD_Seo_Enricher
{
    const PROMPT_VERSION = "gemini-only-v1";
    const MAX_GEMINI_IMAGES = 3;
    const BATCH_OPTION = "lmd_seo_batch_state";
    const BATCH_DEFAULT_SIZE = 1;
    const AUTO_QUEUE_OPTION = "lmd_seo_auto_queue_state";
    const AUTO_QUEUE_HOOK = "lmd_seo_process_auto_queue";
    const AUTO_QUEUE_DELAY = 900;
    const AUTO_QUEUE_INTERVAL = 60;
    const AUTO_QUEUE_BATCH_SIZE = 1;

    private $api;
    private $settings;

    public function __construct($settings = null)
    {
        $this->api = class_exists("LMD_Api_Manager")
            ? new LMD_Api_Manager()
            : null;
        $this->settings = is_array($settings)
            ? wp_parse_args($settings, lmd_get_seo_settings_defaults())
            : (function_exists("lmd_get_seo_settings")
                ? lmd_get_seo_settings()
                : lmd_get_seo_settings_defaults());
    }

    public static function register_hooks()
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        add_action("plmd_import_completed", [__CLASS__, "handle_passerelle_import"], 10, 2);
        add_action(self::AUTO_QUEUE_HOOK, [__CLASS__, "handle_scheduled_auto_queue"]);

        $registered = true;
    }

    public static function handle_passerelle_import($sale_id, $result = [])
    {
        $enricher = new self();
        $enricher->enqueue_sale_after_import($sale_id, is_array($result) ? $result : []);
    }

    public static function handle_scheduled_auto_queue()
    {
        $enricher = new self();
        $enricher->process_auto_queue();
    }

    public static function get_meta_keys()
    {

        return [
            "status" => "_lmd_seo_status",
            "error" => "_lmd_seo_error",
            "title" => "_lmd_seo_title",
            "description" => "_lmd_seo_description",
            "canonical_label" => "_lmd_seo_canonical_label",
            "alt_base" => "_lmd_seo_alt_base",
            "image_alts" => "_lmd_seo_image_alts",
            "focus_terms" => "_lmd_seo_focus_terms",
            "schema_payload" => "_lmd_seo_schema_payload",
            "source_hash" => "_lmd_seo_source_hash",
            "enriched_at" => "_lmd_seo_enriched_at",
            "model" => "_lmd_seo_model",
            "prompt_version" => "_lmd_seo_prompt_version",
        ];
    }

    public function get_stored_output($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {

        return [];
        }

        $meta = self::get_meta_keys();


        return [
            "status" => (string) get_post_meta($lot_id, $meta["status"], true),
            "error" => (string) get_post_meta($lot_id, $meta["error"], true),
            "title" => (string) get_post_meta($lot_id, $meta["title"], true),
            "description" => (string) get_post_meta(
                $lot_id,
                $meta["description"],
                true,
            ),
            "canonical_label" => (string) get_post_meta(
                $lot_id,
                $meta["canonical_label"],
                true,
            ),
            "alt_base" => (string) get_post_meta(
                $lot_id,
                $meta["alt_base"],
                true,
            ),
            "image_alts" => $this->normalize_string_list(
                get_post_meta($lot_id, $meta["image_alts"], true),
            ),
            "focus_terms" => $this->normalize_string_list(
                get_post_meta($lot_id, $meta["focus_terms"], true),
            ),
            "schema_payload" => get_post_meta(
                $lot_id,
                $meta["schema_payload"],
                true,
            ),
            "source_hash" => (string) get_post_meta(
                $lot_id,
                $meta["source_hash"],
                true,
            ),
            "enriched_at" => (string) get_post_meta(
                $lot_id,
                $meta["enriched_at"],
                true,
            ),
            "model" => (string) get_post_meta($lot_id, $meta["model"], true),
            "prompt_version" => (string) get_post_meta(
                $lot_id,
                $meta["prompt_version"],
                true,
            ),
        ];
    }

    public function get_batch_state()
    {
        return $this->normalize_batch_state(get_option(self::BATCH_OPTION, []));
    }

    public function get_auto_queue_state()
    {
        $state = $this->normalize_auto_queue_state(get_option(self::AUTO_QUEUE_OPTION, []));
        $scheduled = wp_next_scheduled(self::AUTO_QUEUE_HOOK);
        $state["next_run_ts"] = $scheduled ? (int) $scheduled : 0;
        if ($state["pending"] > 0 && $state["status"] === "idle" && $state["next_run_ts"] > 0) {
            $state["status"] = "scheduled";
        }
        return $state;
    }

    public function get_month_stats($month = "")
    {
        $month = $this->normalize_stats_month($month);
        $sale_ids = $this->get_sale_ids_for_month($month);
        $stats = [
            "month" => $month,
            "label" => $this->get_stats_month_label($month),
            "sales" => count($sale_ids),
            "analysed" => 0,
            "boosted" => 0,
            "ignored" => 0,
        ];

        if (empty($sale_ids)) {
            return $stats;
        }

        foreach ($sale_ids as $sale_id) {
            foreach ($this->get_sale_lot_ids($sale_id) as $lot_id) {
                $stats["analysed"]++;

                $stored = $this->get_stored_output($lot_id);
                if (($stored["status"] ?? "") === "done") {
                    $stats["boosted"]++;
                    continue;
                }

                $context = $this->collect_lot_context($lot_id);
                if (!$context) {
                    $stats["ignored"]++;
                    continue;
                }

                $eligibility = $this->evaluate_lot_eligibility(
                    $context,
                    $this->settings,
                );

                if (empty($eligibility["eligible"])) {
                    $stats["ignored"]++;
                }
            }
        }

        return $stats;
    }

    public function get_filter_sales_calendar_entries()
    {
        $sale_ids = get_posts([
            "post_type" => "vente",
            "post_status" => ["publish", "future", "draft", "pending", "private"],
            "fields" => "ids",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "meta_key" => "vente_date",
            "orderby" => "meta_value",
            "order" => "ASC",
            "suppress_filters" => true,
        ]);

        if (empty($sale_ids)) {
            return [];
        }

        $entries = [];
        foreach ((array) $sale_ids as $sale_id) {
            $sale_id = absint($sale_id);
            if (!$sale_id) {
                continue;
            }

            $sale = get_post($sale_id);
            if (!$sale || $sale->post_type !== "vente") {
                continue;
            }

            $sale_date = $this->normalize_sale_calendar_date(
                get_post_meta($sale_id, "vente_date", true),
            );
            if ($sale_date === "") {
                continue;
            }

            $sale_terms = taxonomy_exists("categorie_vente")
                ? wp_get_post_terms($sale_id, "categorie_vente")
                : [];
            if (is_wp_error($sale_terms)) {
                $sale_terms = [];
            }

            $entries[] = [
                "id" => $sale_id,
                "title" => $this->plain_text($sale->post_title),
                "date" => $sale_date,
                "type" => $this->plain_text(
                    get_post_meta($sale_id, "vente_type", true),
                ),
                "categories" => array_values(
                    array_filter(
                        array_map(function ($term) {
                            return isset($term->name)
                                ? $this->plain_text($term->name)
                                : "";
                        }, (array) $sale_terms),
                    ),
                ),
            ];
        }

        usort($entries, static function ($left, $right) {
            $leftDate = (string) ($left["date"] ?? "");
            $rightDate = (string) ($right["date"] ?? "");
            if ($leftDate !== $rightDate) {
                return strcmp($leftDate, $rightDate);
            }

            return strcasecmp(
                (string) ($left["title"] ?? ""),
                (string) ($right["title"] ?? ""),
            );
        });

        return $entries;
    }

    public function reset_batch_state()
    {
        delete_option(self::BATCH_OPTION);
        return $this->get_batch_state_defaults();
    }

    public function reset_auto_queue_state()
    {
        $this->unschedule_auto_queue();
        delete_option(self::AUTO_QUEUE_OPTION);
        return $this->get_auto_queue_state_defaults();
    }

    public function prepare_batch($args = [])
    {
        $current_state = $this->get_batch_state();
        if (($current_state["status"] ?? "idle") === "running") {

        return [
                "success" => false,
                "warning" => true,
                "message" => __(
                    "Mettez d'abord le batch SEO en pause avant de preparer une nouvelle file.",
                    "lmd-apps-ia",
                ),
                "state" => $current_state,
            ];
        }

        $batch_size = absint($args["batch_size"] ?? self::BATCH_DEFAULT_SIZE);
        if ($batch_size < 1) {
            $batch_size = self::BATCH_DEFAULT_SIZE;
        }

        $cleared_marks = $this->clear_pending_batch_marks();
        $lot_ids = $this->get_candidate_lot_ids();
        $queue = [];
        $scanned = is_array($lot_ids) ? count($lot_ids) : 0;
        $eligible = 0;
        $ineligible = 0;
        $up_to_date = 0;
        $invalid = 0;

        foreach ((array) $lot_ids as $lot_id) {
            $lot_id = absint($lot_id);
            if (!$lot_id) {
                continue;
            }

            $context = $this->collect_lot_context($lot_id);
            if (!$context) {
                $invalid++;
                continue;
            }

            $eligibility = $this->evaluate_lot_eligibility($context, $this->settings);
            if (empty($eligibility["eligible"])) {
                $ineligible++;
                continue;
            }

            $eligible++;
            $stored = $this->get_stored_output($lot_id);
            $source_hash = $this->build_source_hash($context, $this->settings);
            if (
                !empty($stored["source_hash"]) &&
                $stored["source_hash"] === $source_hash &&
                ($stored["status"] ?? "") === "done"
            ) {
                $up_to_date++;
                continue;
            }

            $queue[] = $lot_id;
            $this->mark_status($lot_id, "queued", "");
        }

        $message = empty($queue)
            ? __(
                "Aucun lot supplementaire n'est a traiter avec les reglages SEO actuels.",
                "lmd-apps-ia",
            )
            : sprintf(
                __(
                    'File SEO preparee : %1$d lot(s) en attente, %2$d deja a jour, %3$d non eligibles.',
                    "lmd-apps-ia",
                ),
                count($queue),
                $up_to_date,
                $ineligible,
            );

        $state = $this->normalize_batch_state([
            "status" => !empty($queue) ? "ready" : "idle",
            "queue" => $queue,
            "total" => count($queue),
            "cursor" => 0,
            "processed" => 0,
            "success" => 0,
            "errors" => 0,
            "skipped" => 0,
            "cached" => 0,
            "scanned" => $scanned,
            "eligible" => $eligible,
            "ineligible" => $ineligible,
            "up_to_date" => $up_to_date,
            "invalid" => $invalid,
            "prepared_at" => current_time("mysql"),
            "started_at" => "",
            "finished_at" => "",
            "current_lot_id" => 0,
            "last_lot_id" => 0,
            "last_lot_title" => "",
            "last_message" => $message,
            "batch_size" => $batch_size,
            "cleared_marks" => $cleared_marks,
        ]);

        $this->save_batch_state($state);


        return [
            "success" => true,
            "warning" => empty($queue),
            "message" => $message,
            "state" => $state,
        ];
    }

    public function resume_batch()
    {
        $state = $this->get_batch_state();
        if (empty($state["queue"]) || empty($state["total"])) {

        return [
                "success" => false,
                "warning" => true,
                "message" => __(
                    "Preparez d'abord une file de lots avant de lancer le batch SEO.",
                    "lmd-apps-ia",
                ),
                "state" => $state,
            ];
        }

        if (($state["processed"] ?? 0) >= ($state["total"] ?? 0)) {
            $state["status"] = "completed";
            $state["finished_at"] = $state["finished_at"] ?: current_time("mysql");
            $state["last_message"] = __(
                "Le batch SEO est deja termine. Repreparez une file pour relancer un traitement.",
                "lmd-apps-ia",
            );
            $this->save_batch_state($state);


        return [
                "success" => true,
                "warning" => true,
                "message" => $state["last_message"],
                "state" => $state,
            ];
        }

        $state["status"] = "running";
        if (empty($state["started_at"])) {
            $state["started_at"] = current_time("mysql");
        }
        $state["finished_at"] = "";
        $state["last_message"] = __(
            "Traitement SEO en cours.",
            "lmd-apps-ia",
        );
        $this->save_batch_state($state);


        return [
            "success" => true,
            "message" => $state["last_message"],
            "state" => $state,
        ];
    }

    public function pause_batch()
    {
        $state = $this->get_batch_state();
        if (($state["status"] ?? "idle") !== "running") {

        return [
                "success" => true,
                "warning" => true,
                "message" => __(
                    "Le batch SEO n'etait pas en cours d'execution.",
                    "lmd-apps-ia",
                ),
                "state" => $state,
            ];
        }

        $state["status"] = "paused";
        $state["current_lot_id"] = 0;
        $state["last_message"] = __(
            "Traitement SEO mis en pause.",
            "lmd-apps-ia",
        );
        $this->save_batch_state($state);


        return [
            "success" => true,
            "message" => $state["last_message"],
            "state" => $state,
        ];
    }

    public function process_batch($args = [])
    {
        $state = $this->get_batch_state();
        if (($state["status"] ?? "idle") !== "running") {

        return [
                "success" => false,
                "warning" => true,
                "message" => __(
                    "Le batch SEO n'est pas en cours. Lancez-le ou reprenez-le avant de traiter une tranche.",
                    "lmd-apps-ia",
                ),
                "state" => $state,
            ];
        }

        $batch_size = absint($args["batch_size"] ?? ($state["batch_size"] ?? self::BATCH_DEFAULT_SIZE));
        if ($batch_size < 1) {
            $batch_size = self::BATCH_DEFAULT_SIZE;
        }
        $state["batch_size"] = $batch_size;

        $queue = array_values(array_filter(array_map("absint", (array) ($state["queue"] ?? []))));
        $cursor = absint($state["cursor"] ?? 0);
        if ($cursor >= count($queue)) {
            $state["status"] = "completed";
            $state["finished_at"] = current_time("mysql");
            $state["current_lot_id"] = 0;
            $state["last_message"] = $this->build_batch_completed_message($state);
            $this->save_batch_state($state);


        return [
                "success" => true,
                "message" => $state["last_message"],
                "state" => $state,
            ];
        }

        if (function_exists("ignore_user_abort")) {
            ignore_user_abort(true);
        }
        if (function_exists("set_time_limit")) {
            @set_time_limit(180);
        }

        $chunk = array_slice($queue, $cursor, $batch_size);
        foreach ($chunk as $lot_id) {
            $lot_id = absint($lot_id);
            if (!$lot_id) {
                $state["cursor"]++;
                $state["processed"]++;
                $state["skipped"]++;
                continue;
            }

            $state["current_lot_id"] = $lot_id;
            $result = $this->enrich_lot($lot_id, ["force" => false]);
            $state["cursor"]++;
            $state["processed"]++;
            $state["last_lot_id"] = $lot_id;
            $state["last_lot_title"] = get_the_title($lot_id);
            $state["last_message"] = (string) ($result["message"] ?? "");

            if (!empty($result["success"])) {
                if (!empty($result["cached"])) {
                    $state["cached"]++;
                } else {
                    $state["success"]++;
                }
            } elseif (!empty($result["skipped"])) {
                $state["skipped"]++;
            } else {
                $state["errors"]++;
            }
        }

        if (($state["cursor"] ?? 0) >= ($state["total"] ?? 0)) {
            $state["status"] = "completed";
            $state["finished_at"] = current_time("mysql");
            $state["current_lot_id"] = 0;
            $state["last_message"] = $this->build_batch_completed_message($state);
        }

        $state = $this->normalize_batch_state($state);
        $this->save_batch_state($state);


        return [
            "success" => true,
            "message" => $state["last_message"],
            "state" => $state,
        ];
    }


    public function enqueue_sale_after_import($sale_id, $result = [])
    {
        $sale_id = absint($sale_id);
        if (!$sale_id || get_post_type($sale_id) !== "vente") {

        return [
                "success" => false,
                "warning" => true,
                "message" => __(
                    "La vente importee n'a pas pu etre identifiee pour la file SEO automatique.",
                    "lmd-apps-ia",
                ),
                "state" => $this->get_auto_queue_state(),
            ];
        }

        $lot_ids = $this->get_sale_lot_ids($sale_id);
        return $this->enqueue_auto_queue(
            $lot_ids,
            [
                "sale_id" => $sale_id,
                "source_name" => sanitize_text_field((string) ($result["source_name"] ?? "")),
            ],
        );
    }

    public function enqueue_auto_queue($lot_ids, $args = [])
    {
        $state = $this->get_auto_queue_state();
        $queue = is_array($state["queue"]) ? $state["queue"] : [];
        $sale_id = absint($args["sale_id"] ?? 0);
        $source_name = sanitize_text_field((string) ($args["source_name"] ?? ""));
        $queued_at = current_time("mysql");

        $added = 0;
        $already_queued = 0;
        $up_to_date = 0;
        $ineligible = 0;
        $invalid = 0;

        foreach ((array) $lot_ids as $lot_id) {
            $lot_id = absint($lot_id);
            if (!$lot_id) {
                continue;
            }

            $context = $this->collect_lot_context($lot_id);
            if (!$context) {
                $invalid++;
                continue;
            }

            $eligibility = $this->evaluate_lot_eligibility($context, $this->settings);
            if (empty($eligibility["eligible"])) {
                $ineligible++;
                continue;
            }

            $source_hash = $this->build_source_hash($context, $this->settings);
            $stored = $this->get_stored_output($lot_id);
            if (
                !empty($stored["source_hash"]) &&
                $stored["source_hash"] === $source_hash &&
                ($stored["status"] ?? "") === "done"
            ) {
                $up_to_date++;
                continue;
            }

            $queue_key = (string) $lot_id;
            if (
                !empty($queue[$queue_key]["source_hash"]) &&
                $queue[$queue_key]["source_hash"] === $source_hash
            ) {
                $already_queued++;
                continue;
            }

            $queue[$queue_key] = [
                "lot_id" => $lot_id,
                "sale_id" => $sale_id,
                "source_hash" => $source_hash,
                "queued_at" => $queued_at,
                "source_name" => $source_name,
            ];
            $this->mark_status($lot_id, "queued", "");
            $added++;
        }

        $state["queue"] = $queue;
        $state["last_sale_id"] = $sale_id;
        $state["last_source_name"] = $source_name;
        $state["added_total"] = absint($state["added_total"] ?? 0) + $added;
        $state["up_to_date_total"] = absint($state["up_to_date_total"] ?? 0) + $up_to_date;
        $state["ineligible_total"] = absint($state["ineligible_total"] ?? 0) + $ineligible;
        $state["invalid_total"] = absint($state["invalid_total"] ?? 0) + $invalid;
        $state["last_queued_at"] = $queued_at;

        if ($added > 0) {
            $scheduled = $this->schedule_auto_queue_run_if_needed(self::AUTO_QUEUE_DELAY);
            $state["status"] = $scheduled ? "scheduled" : "idle";
            $state["last_message"] = sprintf(
                __(
                    'File SEO automatique mise a jour : %1$d lot(s) ajoute(s), %2$d deja a jour, %3$d non eligibles.',
                    "lmd-apps-ia",
                ),
                $added,
                $up_to_date,
                $ineligible,
            );
        } else {
            $state["last_message"] = sprintf(
                __(
                    'Aucun nouveau lot ajoute a la file SEO automatique (%1$d deja en file, %2$d deja a jour, %3$d non eligibles).',
                    "lmd-apps-ia",
                ),
                $already_queued,
                $up_to_date,
                $ineligible,
            );
        }

        $state = $this->normalize_auto_queue_state($state);
        $this->save_auto_queue_state($state);


        return [
            "success" => true,
            "warning" => ($added === 0),
            "message" => $state["last_message"],
            "state" => $state,
        ];
    }

    public function process_auto_queue()
    {
        $state = $this->get_auto_queue_state();
        $queue = is_array($state["queue"]) ? $state["queue"] : [];

        if (empty($queue)) {
            $state["status"] = "idle";
            $state["last_message"] = __(
                "Aucun lot en attente dans la file SEO automatique.",
                "lmd-apps-ia",
            );
            $state = $this->normalize_auto_queue_state($state);
            $this->save_auto_queue_state($state);
            return $state;
        }

        $manual_batch = $this->get_batch_state();
        if (($manual_batch["status"] ?? "idle") === "running") {
            $scheduled = $this->schedule_auto_queue_run_if_needed(300);
            $state["status"] = $scheduled ? "scheduled" : "idle";
            $state["last_message"] = __(
                "Le worker SEO automatique a ete reporte car un batch manuel est en cours.",
                "lmd-apps-ia",
            );
            $state = $this->normalize_auto_queue_state($state);
            $this->save_auto_queue_state($state);
            return $state;
        }

        $state["status"] = "running";
        $state["last_run_at"] = current_time("mysql");
        $this->save_auto_queue_state($state);

        if (function_exists("ignore_user_abort")) {
            ignore_user_abort(true);
        }
        if (function_exists("set_time_limit")) {
            @set_time_limit(180);
        }

        $items = array_slice($queue, 0, self::AUTO_QUEUE_BATCH_SIZE, true);
        foreach ($items as $queue_key => $item) {
            $lot_id = absint($item["lot_id"] ?? 0);
            if (!$lot_id) {
                unset($queue[$queue_key]);
                $state["processed_total"] = absint($state["processed_total"] ?? 0) + 1;
                $state["skipped_total"] = absint($state["skipped_total"] ?? 0) + 1;
                continue;
            }

            $result = $this->enrich_lot($lot_id, ["force" => false]);
            unset($queue[$queue_key]);

            $state["processed_total"] = absint($state["processed_total"] ?? 0) + 1;
            $state["last_lot_id"] = $lot_id;
            $state["last_lot_title"] = get_the_title($lot_id);
            $state["last_message"] = (string) ($result["message"] ?? "");

            if (!empty($result["success"])) {
                if (!empty($result["cached"]) || !empty($result["skipped"])) {
                    $state["skipped_total"] = absint($state["skipped_total"] ?? 0) + 1;
                } else {
                    $state["success_total"] = absint($state["success_total"] ?? 0) + 1;
                }
            } else {
                $state["errors_total"] = absint($state["errors_total"] ?? 0) + 1;
            }
        }

        $state["queue"] = $queue;

        if (!empty($queue)) {
            $scheduled = $this->schedule_auto_queue_run_if_needed(self::AUTO_QUEUE_INTERVAL);
            $state["status"] = $scheduled ? "scheduled" : "idle";
            $state["last_message"] = sprintf(
                __(
                    'File SEO automatique en cours : %d lot(s) restant(s).',
                    "lmd-apps-ia",
                ),
                count($queue),
            );
        } else {
            $state["status"] = "idle";
            $state["last_message"] = sprintf(
                __(
                    'File SEO automatique terminee : %1$d succes, %2$d erreur(s), %3$d saute(s).',
                    "lmd-apps-ia",
                ),
                absint($state["success_total"] ?? 0),
                absint($state["errors_total"] ?? 0),
                absint($state["skipped_total"] ?? 0),
            );
        }

        $state = $this->normalize_auto_queue_state($state);
        $this->save_auto_queue_state($state);

        return $state;
    }
    public function purge_lot($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {

        return [
                "success" => false,
                "message" => __(
                    "Identifiant de lot invalide.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $post = get_post($lot_id);
        if (!$post || $post->post_type !== "lot") {

        return [
                "success" => false,
                "message" => __(
                    "Lot introuvable ou type de contenu non pris en charge.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $this->remove_lot_from_auto_queue($lot_id);
        $removed = $this->purge_lot_meta($lot_id);


        return [
            "success" => true,
            "warning" => ($removed === 0),
            "lot_id" => $lot_id,
            "removed" => $removed,
            "message" => ($removed > 0)
                ? sprintf(
                    __('Enrichissement SEO purge pour le lot #%1$d (%2$d meta supprimee(s)).', 'lmd-apps-ia'),
                    $lot_id,
                    $removed,
                )
                : sprintf(
                    __('Aucune donnee SEO a purger pour le lot #%d.', 'lmd-apps-ia'),
                    $lot_id,
                ),
        ];
    }

    public function purge_all_lots()
    {
        $lot_ids = get_posts([
            "post_type" => "lot",
            "post_status" => "any",
            "fields" => "ids",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "orderby" => "ID",
            "order" => "ASC",
            "suppress_filters" => true,
        ]);

        $scanned = is_array($lot_ids) ? count($lot_ids) : 0;
        $touched = 0;
        $removed_total = 0;

        foreach ((array) $lot_ids as $lot_id) {
            $removed = $this->purge_lot_meta((int) $lot_id);
            if ($removed > 0) {
                $touched++;
                $removed_total += $removed;
            }
        }


        return [
            "success" => true,
            "warning" => ($touched === 0),
            "scanned" => $scanned,
            "touched" => $touched,
            "removed" => $removed_total,
            "message" => ($touched > 0)
                ? sprintf(
                    __('Purge SEO terminee : %1$d lot(s) nettoye(s), %2$d meta supprimee(s).', 'lmd-apps-ia'),
                    $touched,
                    $removed_total,
                )
                : __("Aucune donnee SEO stockee a purger sur ce site.", "lmd-apps-ia"),
        ];
    }
    public function enrich_lot($lot_id, $args = [])
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {

        return [
                "success" => false,
                "message" => __(
                    "Identifiant de lot invalide.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $context = $this->collect_lot_context($lot_id);
        if (!$context) {

        return [
                "success" => false,
                "message" => __(
                    "Lot introuvable ou type de contenu non pris en charge.",
                    "lmd-apps-ia",
                ),
            ];
        }

        $settings = $this->settings;
        $force = !empty($args["force"]);
        $eligibility = $this->evaluate_lot_eligibility($context, $settings);
        $source_hash = $this->build_source_hash($context, $settings);
        $stored = $this->get_stored_output($lot_id);

        if (!$force && !$eligibility["eligible"]) {
            $this->mark_status($lot_id, "skipped", $eligibility["message"]);


        return [
                "success" => false,
                "skipped" => true,
                "message" => $eligibility["message"],
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        if (
            !$force &&
            !empty($stored["source_hash"]) &&
            $stored["source_hash"] === $source_hash &&
            ($stored["status"] ?? "") === "done"
        ) {

        return [
                "success" => true,
                "cached" => true,
                "message" => __(
                    "Ce lot est deja enrichi avec ces reglages.",
                    "lmd-apps-ia",
                ),
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $stored,
            ];
        }

        if (!$this->api) {
            $this->mark_status(
                $lot_id,
                "error",
                __("Module API indisponible.", "lmd-apps-ia"),
            );


        return [
                "success" => false,
                "message" => __("Module API indisponible.", "lmd-apps-ia"),
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $this->mark_status($lot_id, "processing", "");

        $prompt = $this->build_gemini_prompt($context);
        $images = $this->prepare_images_for_gemini($context["images"]);
        $result = $this->api->call_gemini($prompt, $images);

        if (isset($result["error"])) {
            $message = (string) $result["error"];
            $this->mark_status($lot_id, "error", $message);


        return [
                "success" => false,
                "message" => $message,
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $json = $this->extract_json_from_response((string) ($result["text"] ?? ""));
        if (!is_array($json)) {
            $message = __(
                "Reponse Gemini invalide : JSON attendu.",
                "lmd-apps-ia",
            );
            $this->mark_status($lot_id, "error", $message);


        return [
                "success" => false,
                "message" => $message,
                "lot_id" => $lot_id,
                "context" => $context,
                "eligibility" => $eligibility,
                "stored" => $this->get_stored_output($lot_id),
            ];
        }

        $output = $this->normalize_output($json, $context, $settings);
        $this->save_output($lot_id, $output, $source_hash);
        $this->log_api_usage("gemini", 1);

        if (function_exists("lmd_update_consumption_summary")) {
            lmd_update_consumption_summary();
        }


        return [
            "success" => true,
            "cached" => false,
            "message" => __(
                "Enrichissement SEO généré pour ce lot.",
                "lmd-apps-ia",
            ),
            "lot_id" => $lot_id,
            "context" => $context,
            "eligibility" => $eligibility,
            "stored" => $this->get_stored_output($lot_id),
        ];
    }

    public function evaluate_lot_eligibility($context, $settings = null)
    {
        $settings = is_array($settings) ? $settings : $this->settings;

        if (empty($settings["enabled"])) {

        return [
                "eligible" => false,
                "message" => __(
                    "L'enrichissement SEO est desactive sur ce site.",
                    "lmd-apps-ia",
                ),
            ];
        }

        if (!$this->has_enabled_outputs($settings)) {

        return [
                "eligible" => false,
                "message" => __(
                    "Aucun contenu SEO n'est active dans les reglages.",
                    "lmd-apps-ia",
                ),
            ];
        }

                $excluded_sale_ids = array_values(
            array_filter(
                array_map("absint", (array) ($settings["excluded_sale_ids"] ?? [])),
            ),
        );
        $sale_id = absint($context["sale"]["id"] ?? 0);
        if ($sale_id > 0 && in_array($sale_id, $excluded_sale_ids, true)) {

        return [
                "eligible" => false,
                "message" => __(
                    "Cette vente est exclue de l'enrichissement SEO.",
                    "lmd-apps-ia",
                ),
            ];
        }

        if ($this->has_sale_type_filter($settings)) {
            $sale_type = (string) ($context["sale"]["type_normalized"] ?? "");
            if ($sale_type === "") {

        return [
                    "eligible" => false,
                    "message" => __(
                        "Le type de vente de ce lot n'est pas reconnu.",
                        "lmd-apps-ia",
                    ),
                ];
            }
            if (empty($settings["sale_types"][$sale_type])) {

        return [
                    "eligible" => false,
                    "message" => __(
                        "Le type de vente de ce lot n'est pas active dans les reglages SEO.",
                        "lmd-apps-ia",
                    ),
                ];
            }
        }

        $threshold_check = $this->passes_estimate_gate($context, $settings);
        if (!$threshold_check["eligible"]) {
            return $threshold_check;
        }

        if (!empty($settings["limit_categories"])) {
            $allowed_categories = array_values(
                array_filter(
                    array_map(
                        "sanitize_key",
                        (array) ($settings["allowed_categories"] ?? []),
                    ),
                ),
            );
            if (empty($allowed_categories)) {

        return [
                    "eligible" => false,
                    "message" => __(
                        "Le filtre par categories est actif, mais aucune categorie n'est selectionnee.",
                        "lmd-apps-ia",
                    ),
                ];
            }

            $sale_slugs = (array) ($context["sale"]["category_slugs"] ?? []);
            if (empty(array_intersect($allowed_categories, $sale_slugs))) {

        return [
                    "eligible" => false,
                    "message" => __(
                        "La categorie de vente de ce lot n'est pas incluse dans le ciblage SEO.",
                        "lmd-apps-ia",
                    ),
                ];
            }
        }


        return [
            "eligible" => true,
            "message" => __(
                "Lot eligible aux reglages SEO actuels.",
                "lmd-apps-ia",
            ),
        ];
    }

    private function collect_lot_context($lot_id)
    {
        $lot = get_post($lot_id);
        if (!$lot || $lot->post_type !== "lot") {
            return null;
        }

        $sale_id = (int) $lot->post_parent;
        $sale = $sale_id ? get_post($sale_id) : null;
        $sale_terms =
            $sale_id && taxonomy_exists("categorie_vente")
                ? wp_get_post_terms($sale_id, "categorie_vente")
                : [];
        if (is_wp_error($sale_terms)) {
            $sale_terms = [];
        }

        $lot_description_text = $this->plain_text($lot->post_content);


        return [
            "lot_id" => $lot_id,
            "lot_title" => $this->plain_text($lot->post_title),
            "lot_description_raw" => (string) $lot->post_content,
            "lot_description_text" => $lot_description_text,
            "lot_type" => $this->plain_text(
                get_post_meta($lot_id, "lot_type", true),
            ),
            "lot_category_id" => (string) get_post_meta(
                $lot_id,
                "lot_categorie_id",
                true,
            ),
            "explicit_brand" => $this->extract_explicit_brand(
                $this->plain_text($lot->post_title),
                $lot_description_text,
            ),
            "estimates" => [
                "low" => $this->parse_number(
                    get_post_meta($lot_id, "lot_estimation_basse", true),
                ),
                "high" => $this->parse_number(
                    get_post_meta($lot_id, "lot_estimation_haute", true),
                ),
            ],
            "reserve" => $this->parse_number(
                get_post_meta($lot_id, "lot_prix_reserve", true),
            ),
            "dimensions" => [
                "width" => $this->parse_number(
                    get_post_meta($lot_id, "lot_largeur", true),
                ),
                "length" => $this->parse_number(
                    get_post_meta($lot_id, "lot_longueur", true),
                ),
                "depth" => $this->parse_number(
                    get_post_meta($lot_id, "lot_profondeur", true),
                ),
            ],
            "external_url" => esc_url_raw(
                (string) get_post_meta($lot_id, "lot_lien_externe", true),
            ),
            "permalink" => (string) get_permalink($lot_id),
            "site_name" => (string) get_bloginfo("name"),
            "images" => $this->collect_lot_images($lot_id),
            "sale" => [
                "id" => $sale_id,
                "title" => $sale ? $this->plain_text($sale->post_title) : "",
                "permalink" => $sale_id ? (string) get_permalink($sale_id) : "",
                "external_url" => $sale_id
                    ? esc_url_raw(
                        (string) get_post_meta($sale_id, "vente_url", true),
                    )
                    : "",
                "date" => $sale_id
                    ? $this->plain_text(
                        get_post_meta($sale_id, "vente_date", true),
                    )
                    : "",
                "type_raw" => $sale_id
                    ? $this->plain_text(
                        get_post_meta($sale_id, "vente_type", true),
                    )
                    : "",
                "type_normalized" => $this->normalize_sale_type(
                    $sale_id
                        ? get_post_meta($sale_id, "vente_type", true)
                        : "",
                ),
                "category_names" => array_values(
                    array_filter(
                        array_map(function ($term) {
                            return isset($term->name)
                                ? $this->plain_text($term->name)
                                : "";
                        }, is_array($sale_terms) ? $sale_terms : []),
                    ),
                ),
                "category_slugs" => array_values(
                    array_filter(
                        array_map(function ($term) {
                            return isset($term->slug)
                                ? sanitize_key($term->slug)
                                : "";
                        }, is_array($sale_terms) ? $sale_terms : []),
                    ),
                ),
            ],
        ];
    }

    private function collect_lot_images($lot_id)
    {
        $image_ids = [];
        $thumb_id = get_post_thumbnail_id($lot_id);
        if ($thumb_id) {
            $image_ids[] = (int) $thumb_id;
        }

        $gallery = get_post_meta($lot_id, "lot_gallery", true);
        if (is_string($gallery) && $gallery !== "") {
            $gallery = array_map("trim", explode(",", $gallery));
        }
        if (is_array($gallery)) {
            foreach ($gallery as $attachment_id) {
                $attachment_id = absint($attachment_id);
                if ($attachment_id > 0) {
                    $image_ids[] = $attachment_id;
                }
            }
        }

        $image_ids = array_values(array_unique(array_filter($image_ids)));
        $images = [];
        foreach ($image_ids as $attachment_id) {
            $url = wp_get_attachment_image_url($attachment_id, "full");
            if (!$url) {
                continue;
            }
            $path = (string) get_attached_file($attachment_id);
            $images[] = [
                "id" => $attachment_id,
                "url" => (string) $url,
                "path" => $path && file_exists($path) ? $path : "",
                "current_alt" => $this->plain_text(
                    get_post_meta(
                        $attachment_id,
                        "_wp_attachment_image_alt",
                        true,
                    ),
                ),
                "title" => $this->plain_text(get_the_title($attachment_id)),
                "caption" => $this->plain_text(
                    wp_get_attachment_caption($attachment_id),
                ),
            ];
        }

        return array_slice($images, 0, self::MAX_GEMINI_IMAGES);
    }

    private function prepare_images_for_gemini($images)
    {
        $out = [];
        foreach ((array) $images as $image) {
            $path = (string) ($image["path"] ?? "");
            $url = (string) ($image["url"] ?? "");
            if ($path !== "" && file_exists($path)) {
                $out[] = $path;
            } elseif ($url !== "" && filter_var($url, FILTER_VALIDATE_URL)) {
                $out[] = $url;
            }
        }
        return array_slice($out, 0, self::MAX_GEMINI_IMAGES);
    }

    private function build_gemini_prompt($context)
    {
        $sale_categories = !empty($context["sale"]["category_names"])
            ? implode(", ", $context["sale"]["category_names"])
            : "Aucune";
        $dimensions = $this->format_dimensions_sentence($context["dimensions"]);
        $estimate_sentence = $this->format_estimate_sentence(
            $context["estimates"]["low"] ?? null,
            $context["estimates"]["high"] ?? null,
        );

        return implode(
            "\n",
            [
                "Tu es un assistant SEO expert des fiches lot pour des hotels de ventes.",
                "Ta mission : produire des champs SEO fiables et factuels pour un CPT lot.",
                "Contraintes :",
                "- Ecris en francais.",
                "- Reste strictement factuel et descriptif.",
                "- N'invente jamais une attribution, un artiste, une epoque, une provenance ou un materiau s'ils ne sont pas certains.",
                "- N'utilise pas de superlatifs marketing.",
                "- Le title SEO doit rester sous 65 caracteres.",
                "- La meta description doit rester sous 155 caracteres.",
                "- Les alts d'images doivent etre concis, descriptifs et rester sous 125 caracteres.",
                "- Retourne uniquement du JSON valide, sans markdown.",
                "",
                "Structure JSON attendue :",
                "{",
                '  "canonical_label": "libelle canonique court du lot",',
                '  "seo_title": "title SEO",',
                '  "meta_description": "meta description",',
                '  "focus_terms": ["mot cle 1", "mot cle 2"],',
                '  "alt_base": "description courte commune des images",',
                '  "image_alts": ["alt image 1", "alt image 2"]',
                "}",
                "",
                "Contexte du lot :",
                "Nom actuel : " . ($context["lot_title"] ?: "Non renseigne"),
                "Description : " .
                    ($context["lot_description_text"] ?: "Non renseignee"),
                "Type de lot : " . ($context["lot_type"] ?: "Non renseigne"),
                "Estimation : " . $estimate_sentence,
                "Dimensions : " . $dimensions,
                "Vente : " .
                    (($context["sale"]["title"] ?? "") ?: "Non renseignee"),
                "Date de vente : " .
                    (($context["sale"]["date"] ?? "") ?: "Non renseignee"),
                "Type de vente : " .
                    (($context["sale"]["type_raw"] ?? "") ?: "Non renseigne"),
                "Categories de vente : " . $sale_categories,
                "Nombre d'images jointes : " . count($context["images"]),
            ],
        );
    }

    private function normalize_output($raw, $context, $settings)
    {
        $outputs = (array) ($settings["outputs"] ?? []);
        $image_count = count($context["images"]);

        $canonical_label = $this->plain_text($raw["canonical_label"] ?? "");
        if ($canonical_label === "") {
            $canonical_label = $this->default_canonical_label($context);
        }

        $seo_title = $this->plain_text($raw["seo_title"] ?? "");
        if ($seo_title === "") {
            $seo_title = $this->build_default_title($canonical_label, $context);
        }
        $seo_title = $this->truncate_text($seo_title, 65);

        $meta_description = $this->plain_text(
            $raw["meta_description"] ?? "",
        );
        if ($meta_description === "") {
            $meta_description = $this->build_default_description(
                $canonical_label,
                $context,
            );
        }
        $meta_description = $this->truncate_text($meta_description, 155);

        $alt_base = $this->plain_text($raw["alt_base"] ?? "");
        if ($alt_base === "") {
            $alt_base = $canonical_label;
        }
        $alt_base = $this->truncate_text($alt_base, 125);

        $image_alts = [];
        if (is_array($raw["image_alts"] ?? null)) {
            foreach ($raw["image_alts"] as $value) {
                $value = $this->truncate_text($this->plain_text($value), 125);
                if ($value !== "") {
                    $image_alts[] = $value;
                }
            }
        }
        $image_alts = array_slice($image_alts, 0, $image_count);
        for ($i = count($image_alts); $i < $image_count; $i++) {
            $image_alts[] = $this->build_default_image_alt(
                $alt_base,
                $i + 1,
                $image_count,
            );
        }

        $focus_terms = $this->normalize_focus_terms($raw["focus_terms"] ?? []);
        $schema_payload = !empty($outputs["schema"])
            ? $this->build_schema_payload(
                $context,
                [
                    "canonical_label" => $canonical_label,
                    "meta_description" => $meta_description,
                ],
            )
            : [];


        return [
            "canonical_label" => $canonical_label,
            "title" => !empty($outputs["title"]) ? $seo_title : "",
            "description" => !empty($outputs["description"])
                ? $meta_description
                : "",
            "alt_base" => !empty($outputs["alts"]) ? $alt_base : "",
            "image_alts" => !empty($outputs["alts"]) ? $image_alts : [],
            "focus_terms" => $focus_terms,
            "schema_payload" => $schema_payload,
        ];
    }

    private function purge_lot_meta($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return 0;
        }

        $removed = 0;
        foreach (self::get_meta_keys() as $meta_key) {
            if (!metadata_exists("post", $lot_id, $meta_key)) {
                continue;
            }

            $removed += count(get_post_meta($lot_id, $meta_key, false));
            delete_post_meta($lot_id, $meta_key);
        }

        return $removed;
    }
    private function save_output($lot_id, $output, $source_hash)
    {
        $meta = self::get_meta_keys();

        update_post_meta($lot_id, $meta["status"], "done");
        delete_post_meta($lot_id, $meta["error"]);
        update_post_meta($lot_id, $meta["canonical_label"], $output["canonical_label"]);
        $this->save_string_or_delete($lot_id, $meta["title"], $output["title"]);
        $this->save_string_or_delete(
            $lot_id,
            $meta["description"],
            $output["description"],
        );
        $this->save_string_or_delete(
            $lot_id,
            $meta["alt_base"],
            $output["alt_base"],
        );
        $this->save_array_or_delete(
            $lot_id,
            $meta["image_alts"],
            $output["image_alts"],
        );
        $this->save_array_or_delete(
            $lot_id,
            $meta["focus_terms"],
            $output["focus_terms"],
        );
        $this->save_array_or_delete(
            $lot_id,
            $meta["schema_payload"],
            $output["schema_payload"],
            true,
        );
        update_post_meta($lot_id, $meta["source_hash"], $source_hash);
        update_post_meta($lot_id, $meta["enriched_at"], current_time("mysql"));
        update_post_meta(
            $lot_id,
            $meta["model"],
            $this->api ? $this->api->get_gemini_model() : "",
        );
        update_post_meta($lot_id, $meta["prompt_version"], self::PROMPT_VERSION);
    }


    private function get_auto_queue_state_defaults()
    {

        return [
            "status" => "idle",
            "queue" => [],
            "pending" => 0,
            "added_total" => 0,
            "processed_total" => 0,
            "success_total" => 0,
            "errors_total" => 0,
            "skipped_total" => 0,
            "up_to_date_total" => 0,
            "ineligible_total" => 0,
            "invalid_total" => 0,
            "last_sale_id" => 0,
            "last_lot_id" => 0,
            "last_lot_title" => "",
            "last_source_name" => "",
            "last_queued_at" => "",
            "last_run_at" => "",
            "last_message" => "",
            "next_run_ts" => 0,
        ];
    }

    private function normalize_auto_queue_state($state)
    {
        $defaults = $this->get_auto_queue_state_defaults();
        $state = is_array($state) ? array_merge($defaults, $state) : $defaults;

        $state["status"] = sanitize_key((string) $state["status"]);
        if ($state["status"] === "") {
            $state["status"] = "idle";
        }

        $normalized_queue = [];
        foreach ((array) ($state["queue"] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lot_id = absint($item["lot_id"] ?? 0);
            if (!$lot_id) {
                continue;
            }
            $normalized_queue[(string) $lot_id] = [
                "lot_id" => $lot_id,
                "sale_id" => absint($item["sale_id"] ?? 0),
                "source_hash" => sanitize_text_field((string) ($item["source_hash"] ?? "")),
                "queued_at" => is_string($item["queued_at"] ?? null) ? $item["queued_at"] : "",
                "source_name" => sanitize_text_field((string) ($item["source_name"] ?? "")),
            ];
        }
        $state["queue"] = $normalized_queue;
        $state["pending"] = count($normalized_queue);

        foreach (["added_total", "processed_total", "success_total", "errors_total", "skipped_total", "up_to_date_total", "ineligible_total", "invalid_total", "last_sale_id", "last_lot_id", "next_run_ts"] as $key) {
            $state[$key] = absint($state[$key]);
        }

        foreach (["last_lot_title", "last_source_name", "last_queued_at", "last_run_at", "last_message"] as $key) {
            $state[$key] = is_string($state[$key]) ? $state[$key] : "";
        }

        return $state;
    }

    private function save_auto_queue_state($state)
    {
        $state = $this->normalize_auto_queue_state($state);
        if (false === get_option(self::AUTO_QUEUE_OPTION, false)) {
            add_option(self::AUTO_QUEUE_OPTION, $state, "", "no");
        } else {
            update_option(self::AUTO_QUEUE_OPTION, $state, false);
        }
    }

    private function schedule_auto_queue_run_if_needed($delay = self::AUTO_QUEUE_DELAY)
    {
        $delay = max(1, absint($delay));
        $scheduled = wp_next_scheduled(self::AUTO_QUEUE_HOOK);
        if ($scheduled) {
            return (int) $scheduled;
        }

        $timestamp = time() + $delay;
        wp_schedule_single_event($timestamp, self::AUTO_QUEUE_HOOK);
        return $timestamp;
    }

    private function unschedule_auto_queue()
    {
        while ($timestamp = wp_next_scheduled(self::AUTO_QUEUE_HOOK)) {
            wp_unschedule_event($timestamp, self::AUTO_QUEUE_HOOK);
        }
    }

    private function get_sale_lot_ids($sale_id)
    {
        $sale_id = absint($sale_id);
        if (!$sale_id) {

        return [];
        }

        return get_posts([
            "post_type" => "lot",
            "post_status" => ["publish", "future", "draft", "pending", "private"],
            "post_parent" => $sale_id,
            "fields" => "ids",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "orderby" => "ID",
            "order" => "ASC",
            "suppress_filters" => true,
        ]);
    }

    private function remove_lot_from_auto_queue($lot_id)
    {
        $lot_id = absint($lot_id);
        if (!$lot_id) {
            return;
        }

        $state = $this->get_auto_queue_state();
        $queue_key = (string) $lot_id;
        if (!isset($state["queue"][$queue_key])) {
            return;
        }

        unset($state["queue"][$queue_key]);
        $state = $this->normalize_auto_queue_state($state);
        $this->save_auto_queue_state($state);
    }
    private function get_batch_state_defaults()
    {

        return [
            "status" => "idle",
            "queue" => [],
            "total" => 0,
            "cursor" => 0,
            "processed" => 0,
            "success" => 0,
            "errors" => 0,
            "skipped" => 0,
            "cached" => 0,
            "scanned" => 0,
            "eligible" => 0,
            "ineligible" => 0,
            "up_to_date" => 0,
            "invalid" => 0,
            "cleared_marks" => 0,
            "prepared_at" => "",
            "started_at" => "",
            "finished_at" => "",
            "current_lot_id" => 0,
            "last_lot_id" => 0,
            "last_lot_title" => "",
            "last_message" => "",
            "batch_size" => self::BATCH_DEFAULT_SIZE,
        ];
    }

    private function normalize_batch_state($state)
    {
        $defaults = $this->get_batch_state_defaults();
        $state = is_array($state) ? array_merge($defaults, $state) : $defaults;

        $state["status"] = sanitize_key($state["status"]);
        if ($state["status"] === "") {
            $state["status"] = "idle";
        }

        $state["queue"] = array_values(
            array_filter(array_map("absint", (array) $state["queue"])),
        );

        foreach (
            [
                "total",
                "cursor",
                "processed",
                "success",
                "errors",
                "skipped",
                "cached",
                "scanned",
                "eligible",
                "ineligible",
                "up_to_date",
                "invalid",
                "cleared_marks",
                "current_lot_id",
                "last_lot_id",
                "batch_size",
            ] as $key
        ) {
            $state[$key] = absint($state[$key]);
        }

        if ($state["batch_size"] < 1) {
            $state["batch_size"] = self::BATCH_DEFAULT_SIZE;
        }

        foreach (
            ["prepared_at", "started_at", "finished_at", "last_message", "last_lot_title"] as $key
        ) {
            $state[$key] = is_string($state[$key]) ? $state[$key] : "";
        }

        return $state;
    }

    private function save_batch_state($state)
    {
        $state = $this->normalize_batch_state($state);
        if (false === get_option(self::BATCH_OPTION, false)) {
            add_option(self::BATCH_OPTION, $state, "", "no");
        } else {
            update_option(self::BATCH_OPTION, $state, false);
        }
    }

    private function normalize_stats_month($month)
    {
        $month = $this->plain_text($month);
        if (!preg_match("/^\d{4}-\d{2}$/", $month)) {
            $month = function_exists("wp_date")
                ? wp_date("Y-m", null, wp_timezone())
                : date("Y-m");
        }

        return $month;
    }

    private function get_stats_month_label($month)
    {
        try {
            $date = new DateTimeImmutable(
                $month . "-01 00:00:00",
                function_exists("wp_timezone")
                    ? wp_timezone()
                    : new DateTimeZone("UTC"),
            );

            return function_exists("wp_date")
                ? wp_date("F Y", $date->getTimestamp(), $date->getTimezone())
                : $date->format("F Y");
        } catch (Exception $e) {
            return $month;
        }
    }

        private function normalize_sale_calendar_date($value)
    {
        $value = $this->plain_text($value);
        if ($value === "") {
            return "";
        }

        try {
            $timezone = function_exists("wp_timezone")
                ? wp_timezone()
                : new DateTimeZone("UTC");
            $date = date_create_immutable($value, $timezone);
            if (!$date) {
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return "";
                }
                $date = (new DateTimeImmutable("@" . $timestamp))->setTimezone($timezone);
            }

            return $date->format("Y-m-d");
        } catch (Exception $e) {
            return "";
        }
    }

    private function get_sale_ids_for_month($month)
    {
        $month_prefix = $this->normalize_stats_month($month) . "-";
        $sale_ids = get_posts([
            "post_type" => "vente",
            "post_status" => ["publish", "future", "draft", "pending", "private"],
            "fields" => "ids",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "meta_key" => "vente_date",
            "orderby" => "meta_value",
            "order" => "DESC",
            "suppress_filters" => true,
        ]);

        if (empty($sale_ids)) {
            return [];
        }

        return array_values(
            array_filter($sale_ids, function ($sale_id) use ($month_prefix) {
                $value = $this->plain_text(
                    get_post_meta((int) $sale_id, "vente_date", true),
                );

                return $value !== "" && strpos($value, $month_prefix) === 0;
            }),
        );
    }

    private function get_candidate_lot_ids()
    {
        return get_posts([
            "post_type" => "lot",
            "post_status" => ["publish", "future", "draft", "pending", "private"],
            "fields" => "ids",
            "posts_per_page" => -1,
            "no_found_rows" => true,
            "orderby" => "ID",
            "order" => "ASC",
            "suppress_filters" => true,
        ]);
    }

    private function clear_pending_batch_marks()
    {
        $meta = self::get_meta_keys();
        $cleared = 0;
        foreach (["queued", "processing"] as $status) {
            $lot_ids = get_posts([
                "post_type" => "lot",
                "post_status" => ["publish", "future", "draft", "pending", "private"],
                "fields" => "ids",
                "posts_per_page" => -1,
                "no_found_rows" => true,
                "orderby" => "ID",
                "order" => "ASC",
                "suppress_filters" => true,
                "meta_key" => $meta["status"],
                "meta_value" => $status,
            ]);

            foreach ((array) $lot_ids as $lot_id) {
                delete_post_meta((int) $lot_id, $meta["status"]);
                delete_post_meta((int) $lot_id, $meta["error"]);
                $cleared++;
            }
        }

        return $cleared;
    }

    private function build_batch_completed_message($state)
    {
        return sprintf(
            __(
                'Batch SEO termine : %1$d lot(s) traites, %2$d succes, %3$d erreur(s), %4$d saute(s), %5$d deja a jour.',
                "lmd-apps-ia",
            ),
            absint($state["processed"] ?? 0),
            absint($state["success"] ?? 0),
            absint($state["errors"] ?? 0),
            absint($state["skipped"] ?? 0),
            absint($state["cached"] ?? 0),
        );
    }

    private function build_schema_payload($context, $output)
    {
        $payload = [
            "@context" => "https://schema.org",
            "@type" => "Product",
            "name" => $output["canonical_label"],
            "description" => $output["meta_description"],
            "url" => $context["permalink"],
        ];

        $image_urls = array_values(
            array_filter(
                array_map(function ($image) {
                    return (string) ($image["url"] ?? "");
                }, (array) $context["images"]),
            ),
        );
        if (!empty($image_urls)) {
            $payload["image"] = $image_urls;
        }

        if (!empty($context["sale"]["category_names"])) {
            $payload["category"] = implode(
                ", ",
                (array) $context["sale"]["category_names"],
            );
        }

        if (!empty($context["explicit_brand"])) {
            $payload["brand"] = [
                "@type" => "Brand",
                "name" => $context["explicit_brand"],
            ];
        }

        $width = $this->build_quantitative_value(
            $context["dimensions"]["width"] ?? null,
        );
        if (!empty($width)) {
            $payload["width"] = $width;
        }

        $height = $this->build_quantitative_value(
            $context["dimensions"]["length"] ?? null,
        );
        if (!empty($height)) {
            $payload["height"] = $height;
        }

        $depth = $this->build_quantitative_value(
            $context["dimensions"]["depth"] ?? null,
        );
        if (!empty($depth)) {
            $payload["depth"] = $depth;
        }

        $properties = [];
        if (!empty($context["lot_type"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Type de lot",
                "value" => $context["lot_type"],
            ];
        }
        if (!empty($context["sale"]["type_raw"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Type de vente",
                "value" => $context["sale"]["type_raw"],
            ];
        }
        if (!empty($context["estimates"]["low"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Estimation basse",
                "value" => $this->format_money_value(
                    $context["estimates"]["low"],
                ),
            ];
        }
        if (!empty($context["estimates"]["high"])) {
            $properties[] = [
                "@type" => "PropertyValue",
                "name" => "Estimation haute",
                "value" => $this->format_money_value(
                    $context["estimates"]["high"],
                ),
            ];
        }

        if (!empty($properties)) {
            $payload["additionalProperty"] = $properties;
        }

        if (!empty($context["sale"]["title"])) {
            $payload["isRelatedTo"] = [
                "@type" => "SaleEvent",
                "name" => $context["sale"]["title"],
            ];
            if (!empty($context["sale"]["permalink"])) {
                $payload["isRelatedTo"]["url"] = $context["sale"]["permalink"];
            }
            if (!empty($context["sale"]["external_url"])) {
                $payload["isRelatedTo"]["sameAs"] = $context["sale"]["external_url"];
            }
            if (!empty($context["sale"]["date"])) {
                $payload["isRelatedTo"]["startDate"] = $this->format_schema_datetime(
                    $context["sale"]["date"],
                );
            }
            if (!empty($context["site_name"])) {
                $payload["isRelatedTo"]["organizer"] = [
                    "@type" => "Organization",
                    "name" => $context["site_name"],
                    "url" => home_url("/"),
                ];
            }
        }

        return $payload;
    }

    private function has_enabled_outputs($settings)
    {
        foreach ((array) ($settings["outputs"] ?? []) as $enabled) {
            if (!empty($enabled)) {
                return true;
            }
        }
        return false;
    }

    private function has_sale_type_filter($settings)
    {
        $sale_types = (array) ($settings["sale_types"] ?? []);
        return empty($sale_types["volontaire"]) || empty($sale_types["judiciaire"]);
    }

    private function passes_estimate_gate($context, $settings)
    {
        $mode = sanitize_key($settings["estimate_gate"]["mode"] ?? "either");
        $low_min = $this->parse_number(
            $settings["estimate_gate"]["low_min"] ?? "",
        );
        $high_min = $this->parse_number(
            $settings["estimate_gate"]["high_min"] ?? "",
        );
        $low_value = $context["estimates"]["low"] ?? null;
        $high_value = $context["estimates"]["high"] ?? null;

        $passes_low = $low_min === null || ($low_value !== null && $low_value >= $low_min);
        $passes_high = $high_min === null || ($high_value !== null && $high_value >= $high_min);

        if ($mode === "low") {
            if ($low_min === null) {

        return ["eligible" => true, "message" => ""];
            }
            return $passes_low
                ? ["eligible" => true, "message" => ""]
                : [
                    "eligible" => false,
                    "message" => __(
                        "L'estimation basse de ce lot est sous le seuil SEO configure.",
                        "lmd-apps-ia",
                    ),
                ];
        }

        if ($mode === "high") {
            if ($high_min === null) {

        return ["eligible" => true, "message" => ""];
            }
            return $passes_high
                ? ["eligible" => true, "message" => ""]
                : [
                    "eligible" => false,
                    "message" => __(
                        "L'estimation haute de ce lot est sous le seuil SEO configure.",
                        "lmd-apps-ia",
                    ),
                ];
        }

        $checks = [];
        if ($low_min !== null) {
            $checks[] = $passes_low;
        }
        if ($high_min !== null) {
            $checks[] = $passes_high;
        }
        if (empty($checks)) {

        return ["eligible" => true, "message" => ""];
        }

        if (in_array(true, $checks, true)) {

        return ["eligible" => true, "message" => ""];
        }


        return [
            "eligible" => false,
            "message" => __(
                "Ce lot ne franchit aucun des seuils d'estimation SEO configures.",
                "lmd-apps-ia",
            ),
        ];
    }

    private function build_source_hash($context, $settings)
    {
        $hash_source = [
            "lot_title" => $context["lot_title"],
            "lot_description_text" => $context["lot_description_text"],
            "lot_type" => $context["lot_type"],
            "lot_category_id" => $context["lot_category_id"],
            "explicit_brand" => $context["explicit_brand"] ?? "",
            "estimates" => $context["estimates"],
            "dimensions" => $context["dimensions"],
            "site_name" => $context["site_name"] ?? "",
            "sale" => [
                "title" => $context["sale"]["title"] ?? "",
                "permalink" => $context["sale"]["permalink"] ?? "",
                "external_url" => $context["sale"]["external_url"] ?? "",
                "date" => $context["sale"]["date"] ?? "",
                "type_normalized" => $context["sale"]["type_normalized"] ?? "",
                "category_slugs" => $context["sale"]["category_slugs"] ?? [],
            ],
            "images" => array_map(function ($image) {

        return [
                    "id" => (int) ($image["id"] ?? 0),
                    "url" => (string) ($image["url"] ?? ""),
                ];
            }, (array) $context["images"]),
            "outputs" => (array) ($settings["outputs"] ?? []),
        ];

        return md5(wp_json_encode($hash_source));
    }

    private function mark_status($lot_id, $status, $error_message = "")
    {
        $meta = self::get_meta_keys();
        update_post_meta($lot_id, $meta["status"], sanitize_key($status));
        if ($error_message !== "") {
            update_post_meta($lot_id, $meta["error"], $this->plain_text($error_message));
        } else {
            delete_post_meta($lot_id, $meta["error"]);
        }
    }

    private function log_api_usage($api_name, $units)
    {
        if (!class_exists("LMD_Api_Usage")) {
            return;
        }
        $usage = new LMD_Api_Usage();
        $usage->log($api_name, (int) $units, null);
    }

    private function extract_json_from_response($text)
    {
        $text = trim((string) $text);
        if ($text === "") {
            return null;
        }
        $text = preg_replace("/^```json\\s*/", "", $text);
        $text = preg_replace('/\\s*```\\s*$/', "", $text);
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function normalize_focus_terms($terms)
    {
        if (is_string($terms)) {
            $terms = explode(",", $terms);
        }
        $out = [];
        foreach ((array) $terms as $term) {
            $term = $this->plain_text($term);
            if ($term !== "") {
                $out[] = $term;
            }
        }
        $out = array_values(array_unique($out));
        return array_slice($out, 0, 6);
    }

    private function normalize_string_list($value)
    {
        if (!is_array($value)) {

        return [];
        }
        return array_values(
            array_filter(
                array_map(function ($item) {
                    return $this->plain_text($item);
                }, $value),
            ),
        );
    }

    private function save_string_or_delete($lot_id, $meta_key, $value)
    {
        $value = $this->plain_text($value);
        if ($value === "") {
            delete_post_meta($lot_id, $meta_key);
            return;
        }
        update_post_meta($lot_id, $meta_key, $value);
    }

    private function save_array_or_delete($lot_id, $meta_key, $value, $preserve_keys = false)
    {
        if (!is_array($value) || empty($value)) {
            delete_post_meta($lot_id, $meta_key);
            return;
        }

        $value = $preserve_keys ? $value : array_values($value);
        update_post_meta($lot_id, $meta_key, $value);
    }

    private function build_default_title($canonical_label, $context)
    {
        $title = $canonical_label;
        $sale_title = (string) ($context["sale"]["title"] ?? "");
        if ($sale_title !== "" && $this->string_length($title) < 42) {
            $title .= " | " . $sale_title;
        }
        return $title;
    }

    private function build_default_description($canonical_label, $context)
    {
        $parts = [$canonical_label];

        $estimate = $this->format_estimate_sentence(
            $context["estimates"]["low"] ?? null,
            $context["estimates"]["high"] ?? null,
        );
        if ($estimate !== "Non renseignee") {
            $parts[] = "Estimation " . strtolower($estimate);
        }

        if (!empty($context["sale"]["title"])) {
            $parts[] = "Vente " . $context["sale"]["title"];
        }

        return implode(". ", array_filter($parts)) . ".";
    }

    private function build_default_image_alt($alt_base, $index, $total)
    {
        if ($total <= 1) {
            return $alt_base;
        }
        return $this->truncate_text(
            sprintf("%s - vue %d", $alt_base, (int) $index),
            125,
        );
    }

    private function default_canonical_label($context)
    {
        $parts = [];
        if (!empty($context["lot_type"])) {
            $parts[] = $context["lot_type"];
        }
        if (!empty($context["lot_title"])) {
            $parts[] = $context["lot_title"];
        }
        if (empty($parts) && !empty($context["lot_description_text"])) {
            $parts[] = $context["lot_description_text"];
        }
        $label = trim(implode(" - ", array_filter($parts)));
        if ($label === "") {
            $label = sprintf("Lot %d", (int) $context["lot_id"]);
        }
        return $this->truncate_text($label, 90);
    }

    private function normalize_sale_type($raw)
    {
        $raw = strtolower($this->plain_text($raw));
        if ($raw === "") {
            return "";
        }
        if (strpos($raw, "judic") !== false) {
            return "judiciaire";
        }
        if (strpos($raw, "volont") !== false) {
            return "volontaire";
        }
        return sanitize_key($raw);
    }

    private function parse_number($value)
    {
        if ($value === null || $value === "") {
            return null;
        }
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5);
        $value = str_replace(["\xc2\xa0", " "], "", $value);
        $value = str_replace(",", ".", $value);
        $value = preg_replace("/[^0-9.\\-]/", "", $value);
        if ($value === "" || $value === "." || $value === "-") {
            return null;
        }
        return (float) $value;
    }

    private function format_estimate_sentence($low, $high)
    {
        if ($low && $high) {
            return sprintf(
                "%s a %s",
                $this->format_money_value($low),
                $this->format_money_value($high),
            );
        }
        if ($high) {
            return "jusqu'a " . $this->format_money_value($high);
        }
        if ($low) {
            return "a partir de " . $this->format_money_value($low);
        }
        return "Non renseignee";
    }

    private function format_dimensions_sentence($dimensions)
    {
        $parts = [];
        if (!empty($dimensions["width"])) {
            $parts[] = "L " . $this->format_dimension_value($dimensions["width"]) . " cm";
        }
        if (!empty($dimensions["length"])) {
            $parts[] = "H " . $this->format_dimension_value($dimensions["length"]) . " cm";
        }
        if (!empty($dimensions["depth"])) {
            $parts[] = "P " . $this->format_dimension_value($dimensions["depth"]) . " cm";
        }
        return !empty($parts) ? implode(", ", $parts) : "Non renseignees";
    }

    private function format_dimension_value($value)
    {
        return rtrim(rtrim(number_format((float) $value, 2, ".", ""), "0"), ".");
    }

    private function format_money_value($value)
    {
        return number_format((float) $value, 0, ",", " ") . " EUR";
    }

    private function build_quantitative_value($value)
    {
        if ($value === null || $value === "") {
            return null;
        }


        return [
            "@type" => "QuantitativeValue",
            "value" => (float) $value,
            "unitCode" => "CMT",
        ];
    }

    private function format_schema_datetime($value)
    {
        $value = $this->plain_text($value);
        if ($value === "") {
            return "";
        }

        try {
            $timezone = function_exists("wp_timezone")
                ? wp_timezone()
                : new DateTimeZone("UTC");
            $date = date_create_immutable($value, $timezone);
            if (!$date) {
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return $value;
                }
                $date = (new DateTimeImmutable("@" . $timestamp))->setTimezone($timezone);
            }

            return $date->format("c");
        } catch (Exception $e) {
            return $value;
        }
    }

    private function extract_explicit_brand($title, $description)
    {
        $haystacks = [
            (string) $title,
            (string) $description,
        ];

        foreach ($haystacks as $text) {
            if ($text === "") {
                continue;
            }

            if (
                preg_match(
                    "/(?:^|[\\s\\(\\[])(?:marque|brand|fabricant|manufacture)\\s*[:\\-]\\s*([^\\n\\r\\.;,\\)\\]]{2,80})/iu",
                    $text,
                    $matches,
                )
            ) {
                $brand = $this->plain_text($matches[1] ?? "");
                if ($brand !== "") {
                    return $this->truncate_text($brand, 80);
                }
            }
        }

        return "";
    }

    private function plain_text($value)
    {
        $value = is_scalar($value) ? (string) $value : "";
        $value = wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        $value = preg_replace("/\\s+/u", " ", $value);
        return trim((string) $value);
    }

    private function truncate_text($value, $limit)
    {
        $value = $this->plain_text($value);
        if ($value === "" || $this->string_length($value) <= $limit) {
            return $value;
        }

        $slice = $this->string_substr($value, 0, max(0, $limit - 1));
        $slice = preg_replace("/\\s+\\S*$/u", "", $slice);
        if (!$slice) {
            $slice = $this->string_substr($value, 0, max(0, $limit - 1));
        }
        return rtrim($slice, " ,;:-") . "...";
    }

    private function string_length($value)
    {
        return function_exists("mb_strlen")
            ? mb_strlen($value)
            : strlen($value);
    }

    private function string_substr($value, $start, $length = null)
    {
        if (function_exists("mb_substr")) {
            return $length === null
                ? mb_substr($value, $start)
                : mb_substr($value, $start, $length);
        }

        return $length === null
            ? substr($value, $start)
            : substr($value, $start, $length);
    }
}














