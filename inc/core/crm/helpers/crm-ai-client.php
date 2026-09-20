<?php
/**
 * CRM Local AI Client — NEXUS Multi-Model GPU Routing
 *
 * Implements deterministic local AI inference via Ollama (NVIDIA RTX 5060 Ti GPU, 16 GB VRAM).
 * Routes tasks to specialized local models:
 *  - FACTORIUM (Code, HTML5, Templates, Schemas): qwen2.5-coder:7b / 1.5b
 *  - JUDIKATIVE (Audit, Proof, USt, Logic): deepseek-r1:14b / phi4:14b
 *  - LUPOS / LINGUA (Email, German didactics, Tonalität): qwen2.5:14b
 *  - VISIUM / STYLISTICS (European nuances): mistral-nemo:latest
 *
 * Automatically filters out DeepSeek-R1 <think>...</think> reasoning blocks.
 * Supports execution inside Docker (host.docker.internal) and native host (127.0.0.1).
 *
 * @package CustomCRM
 * @version 2.18.17
 */

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Determine the Ollama base URL.
 *
 * Checks in order:
 * 1. Constant CRM_OLLAMA_URL or CRM_OLLAMA_HOST
 * 2. Environment variable OLLAMA_URL or OLLAMA_HOST
 * 3. Docker container environment (host.docker.internal:11434)
 * 4. Localhost fallback (127.0.0.1:11434)
 *
 * @return string The base URL for Ollama, without trailing slash.
 */
function crm_get_ollama_base_url(): string
{
    if (defined('CRM_OLLAMA_URL') && !empty(CRM_OLLAMA_URL)) {
        return rtrim(CRM_OLLAMA_URL, '/');
    }
    if (defined('CRM_OLLAMA_HOST') && !empty(CRM_OLLAMA_HOST)) {
        return rtrim(CRM_OLLAMA_HOST, '/');
    }

    $env_url = getenv('OLLAMA_URL') ?: getenv('OLLAMA_HOST');
    if ($env_url) {
        return rtrim($env_url, '/');
    }

    // 1. If host.docker.internal is resolved via DNS/hosts
    if (gethostbyname('host.docker.internal') !== 'host.docker.internal') {
        return 'http://host.docker.internal:11434';
    }

    // 2. If inside a Docker container, determine the host bridge gateway IP from /proc/net/route
    if ((file_exists('/.dockerenv') || file_exists('/run/.containerenv')) && is_readable('/proc/net/route')) {
        $routes = @file('/proc/net/route');
        if (is_array($routes)) {
            foreach ($routes as $route) {
                $parts = preg_split('/\s+/', trim($route));
                if (isset($parts[1], $parts[2]) && $parts[1] === '00000000') {
                    $gateway_ip = implode('.', array_reverse(array_map('hexdec', str_split($parts[2], 2))));
                    if (filter_var($gateway_ip, FILTER_VALIDATE_IP)) {
                        return "http://{$gateway_ip}:11434";
                    }
                }
            }
        }
    }

    return 'http://127.0.0.1:11434';
}

/**
 * Map task type to the optimal local AI model according to the NEXUS 7-Agent protocol.
 *
 * @param string $task_type The task identifier (e.g. 'code', 'audit', 'email', 'general').
 * @return string The model tag name for Ollama.
 */
function crm_get_local_ai_model(string $task_type = 'general'): string
{
    $norm = strtolower(trim($task_type));

    // 1. FACTORIUM: Micro-Syntax & Quick HTML/Schema normalization (130+ t/s)
    if (in_array($norm, ['micro', 'fast-code', 'quick-syntax', 'html-clean', 'lint'], true)) {
        return defined('CRM_OLLAMA_MICRO_MODEL') ? CRM_OLLAMA_MICRO_MODEL : 'qwen2.5-coder:1.5b';
    }

    // 2. FACTORIUM: Workhorse Coder & Template Engine (~75 t/s)
    if (
        str_contains($norm, 'code') ||
        str_contains($norm, 'syntax') ||
        str_contains($norm, 'html') ||
        str_contains($norm, 'schema') ||
        str_contains($norm, 'template') ||
        str_contains($norm, 'sql') ||
        str_contains($norm, 'factorium')
    ) {
        return defined('CRM_OLLAMA_CODER_MODEL') ? CRM_OLLAMA_CODER_MODEL : 'qwen2.5-coder:7b';
    }

    // 3. FACTORIUM: Senior Architecture & MVC
    if (str_contains($norm, 'arch') || str_contains($norm, 'mvc') || str_contains($norm, 'refactor')) {
        return defined('CRM_OLLAMA_ARCH_MODEL') ? CRM_OLLAMA_ARCH_MODEL : 'qwen2.5-coder:14b';
    }

    // 4. JUDIKATIVE: Deep Reasoning, Proof, Logic, USt & Compliance Check
    if (
        str_contains($norm, 'audit') ||
        str_contains($norm, 'reason') ||
        str_contains($norm, 'proof') ||
        str_contains($norm, 'law') ||
        str_contains($norm, 'legal') ||
        str_contains($norm, 'compliance') ||
        str_contains($norm, 'tax') ||
        str_contains($norm, 'ust') ||
        str_contains($norm, 'judikative')
    ) {
        return defined('CRM_OLLAMA_REASONING_MODEL') ? CRM_OLLAMA_REASONING_MODEL : 'deepseek-r1:14b';
    }

    // 5. VISIUM / STYLISTICS: European nuances & multilingual styling
    if (str_contains($norm, 'nuance') || str_contains($norm, 'style') || str_contains($norm, 'european')) {
        return defined('CRM_OLLAMA_STYLE_MODEL') ? CRM_OLLAMA_STYLE_MODEL : 'mistral-nemo:latest';
    }

    // 6. LUPOS / LINGUA-LOCA: Austrian German, Email formulation, Didactics
    if (
        str_contains($norm, 'email') ||
        str_contains($norm, 'mail') ||
        str_contains($norm, 'german') ||
        str_contains($norm, 'deutsch') ||
        str_contains($norm, 'didactic') ||
        str_contains($norm, 'lingua') ||
        str_contains($norm, 'lupos') ||
        str_contains($norm, 'text')
    ) {
        return defined('CRM_OLLAMA_TEXT_MODEL') ? CRM_OLLAMA_TEXT_MODEL : 'qwen2.5:14b';
    }

    // Default Fallback: Qwen 2.5 14B Workhorse
    return defined('CRM_OLLAMA_DEFAULT_MODEL') ? CRM_OLLAMA_DEFAULT_MODEL : 'qwen2.5:14b';
}

/**
 * Strip DeepSeek-R1 `<think>...</think>` internal reasoning tags from the model output.
 *
 * @param string $text Raw text from LLM.
 * @return string Filtered text.
 */
function crm_strip_reasoning_tags(string $text): string
{
    if (!str_contains($text, '<think>')) {
        return trim($text);
    }
    return trim(preg_replace('/<think>[\s\S]*?<\/think>/i', '', $text));
}

/**
 * Perform a low-level HTTP POST request to Ollama.
 * Uses wp_remote_post if available, otherwise cURL.
 *
 * @param string $endpoint Full URL endpoint (e.g. http://127.0.0.1:11434/api/generate).
 * @param array  $payload  JSON payload to send.
 * @param int    $timeout  Timeout in seconds (default 120s).
 * @return array{success: bool, status_code: int, body: string, error?: string}
 */
function crm_ollama_http_post(string $endpoint, array $payload, int $timeout = 120): array
{
    $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('wp_remote_post')) {
        $response = wp_remote_post($endpoint, [
            'method'      => 'POST',
            'timeout'     => $timeout,
            'redirection' => 2,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'        => $json_payload,
        ]);

        if (is_wp_error($response)) {
            return [
                'success'     => false,
                'status_code' => 0,
                'body'        => '',
                'error'       => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return [
            'success'     => ($code >= 200 && $code < 300),
            'status_code' => (int) $code,
            'body'        => $body,
            'error'       => ($code >= 200 && $code < 300) ? null : "HTTP Error {$code}",
        ];
    }

    // Standalone / CLI cURL fallback
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json_payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $errmsg = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return [
            'success'     => false,
            'status_code' => $code,
            'body'        => '',
            'error'       => "cURL error ({$errno}): {$errmsg}",
        ];
    }

    return [
        'success'     => ($code >= 200 && $code < 300),
        'status_code' => $code,
        'body'        => (string) $body,
        'error'       => ($code >= 200 && $code < 300) ? null : "HTTP Error {$code}",
    ];
}

/**
 * Execute a local AI call via Ollama with automatic model routing and formatting.
 *
 * @param string $prompt  The instruction or prompt to process.
 * @param array  $options Configuration options:
 *                        - 'task_type' (string): Routing key ('code', 'audit', 'email', etc.)
 *                        - 'model' (string): Explicit model override
 *                        - 'format' (string): 'json' for JSON schema mode
 *                        - 'system' (string): System prompt
 *                        - 'temperature' (float): Sampling temperature (default 0.3)
 *                        - 'timeout' (int): Timeout in seconds (default 120)
 *                        - 'response_key' (string): Specific key to extract from parsed JSON
 *                        - 'raw' (bool): Return raw string instead of parsed JSON
 * @return mixed Parsed JSON array/value, plain string, or null on error.
 */
function crm_call_local_ai(string $prompt, array $options = []): mixed
{
    $base_url = crm_get_ollama_base_url();
    $endpoint = $base_url . '/api/generate';

    $task_type = $options['task_type'] ?? 'general';
    $model = !empty($options['model']) ? $options['model'] : crm_get_local_ai_model($task_type);
    $timeout = (int) ($options['timeout'] ?? 120);

    $payload = [
        'model'  => $model,
        'prompt' => $prompt,
        'stream' => false,
    ];

    if (!empty($options['format'])) {
        $payload['format'] = $options['format'];
    }
    if (!empty($options['system'])) {
        $payload['system'] = $options['system'];
    }

    $model_options = [];
    if (isset($options['temperature'])) {
        $model_options['temperature'] = (float) $options['temperature'];
    } else {
        $model_options['temperature'] = 0.3;
    }
    $payload['options'] = $model_options;

    $res = crm_ollama_http_post($endpoint, $payload, $timeout);

    if (!$res['success']) {
        error_log("[CRM-AI-CLIENT] Request failed to {$endpoint} (Model: {$model}): " . ($res['error'] ?? 'Unknown error'));
        return null;
    }

    $decoded = json_decode($res['body'], true);
    if (!is_array($decoded) || !isset($decoded['response'])) {
        error_log("[CRM-AI-CLIENT] Invalid Ollama response payload: " . substr($res['body'], 0, 200));
        return null;
    }

    // Strip <think> reasoning blocks (critical for deepseek-r1:14b)
    $clean_text = crm_strip_reasoning_tags($decoded['response']);

    // Return raw text if requested or format is not json
    if (!empty($options['raw']) || ($options['format'] ?? '') !== 'json') {
        return $clean_text;
    }

    // Handle JSON parsing
    $json_candidate = $clean_text;
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $clean_text, $m)) {
        $json_candidate = trim($m[1]);
    }

    $parsed_json = json_decode($json_candidate, true);
    if ($parsed_json === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("[CRM-AI-CLIENT] Failed to parse JSON response: " . substr($json_candidate, 0, 200));
        return $clean_text;
    }

    if (!empty($options['response_key'])) {
        $key = $options['response_key'];
        if (is_array($parsed_json) && array_key_exists($key, $parsed_json)) {
            return $parsed_json[$key];
        }
        // Case-insensitive key match fallback
        if (is_array($parsed_json)) {
            foreach ($parsed_json as $k => $v) {
                if (strcasecmp($k, $key) === 0) {
                    return $v;
                }
            }
        }
    }

    return $parsed_json;
}

/**
 * Helper to generate plain text using local AI.
 *
 * @param string $prompt       The input prompt.
 * @param string $task_type    The task type for model routing ('email', 'general', 'code', etc.).
 * @param array  $extra_options Additional options.
 * @return string|null Generated text or null on failure.
 */
function crm_ai_generate_text(string $prompt, string $task_type = 'general', array $extra_options = []): ?string
{
    $options = array_merge($extra_options, [
        'task_type' => $task_type,
        'raw'       => true,
    ]);

    $res = crm_call_local_ai($prompt, $options);
    return is_string($res) ? $res : null;
}

/**
 * Helper to generate structured JSON using local AI.
 *
 * @param string      $prompt       The input prompt.
 * @param string      $task_type    The task type for model routing ('audit', 'schema', etc.).
 * @param string|null $response_key Optional JSON key to extract.
 * @param array       $extra_options Additional options.
 * @return mixed Parsed JSON data or null on failure.
 */
function crm_ai_generate_json(string $prompt, string $task_type = 'general', ?string $response_key = null, array $extra_options = []): mixed
{
    $options = array_merge($extra_options, [
        'task_type'    => $task_type,
        'format'       => 'json',
        'response_key' => $response_key,
    ]);

    return crm_call_local_ai($prompt, $options);
}

/**
 * Check connectivity to the local Ollama daemon and list installed models.
 *
 * @return array Status report containing reachability and model list.
 */
function crm_ai_healthcheck(): array
{
    $base_url = crm_get_ollama_base_url();
    $tags_url = $base_url . '/api/tags';

    if (function_exists('wp_remote_get')) {
        $res = wp_remote_get($tags_url, ['timeout' => 5]);
        if (is_wp_error($res)) {
            return [
                'status'  => 'error',
                'url'     => $base_url,
                'message' => $res->get_error_message(),
                'models'  => [],
            ];
        }
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
    } else {
        $ch = curl_init($tags_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code !== 200) {
            return [
                'status'  => 'error',
                'url'     => $base_url,
                'message' => $err ?: "HTTP Status {$code}",
                'models'  => [],
            ];
        }
    }

    $data = json_decode((string) $body, true);
    $models = [];
    if (!empty($data['models']) && is_array($data['models'])) {
        foreach ($data['models'] as $m) {
            $models[] = $m['name'] ?? 'unknown';
        }
    }

    return [
        'status'  => ($code === 200) ? 'ok' : 'error',
        'url'     => $base_url,
        'models'  => $models,
        'count'   => count($models),
    ];
}

/**
 * Friedelin Lead-Vorbereitungs-Pipeline (Workflow Version 1).
 *
 * Analysiert einen neuen WPForms-Eintrag, ermittelt den passenden Kurs,
 * generiert die erforderlichen PDF-Dokumente (Angebot bzw. Kombi Angebot + KB)
 * und setzt den Status auf "KI vorbereitet – Freigabe erforderlich".
 *
 * Human-in-the-Loop: Kein automatischer Versand an Kunden!
 *
 * @param int $entry_id WPForms Entry ID
 * @param int $form_id  WPForms Form ID (Standard: 60468)
 * @return array
 */
function crm_friedelin_prepare_lead(int $entry_id, int $form_id = 60468): array
{
    global $wpdb;

    if ($entry_id <= 0) {
        return ['success' => false, 'error' => 'Ungültige Entry-ID'];
    }

    require_once dirname(__DIR__) . '/crm-model.php';
    require_once dirname(__DIR__) . '/helpers/crm-status.php';
    require_once dirname(__DIR__) . '/helpers/normalize.php';
    require_once dirname(__DIR__) . '/pdf/offer.php';
    require_once dirname(__DIR__) . '/pdf/kurszeitenbestaetigung.php';
    require_once dirname(__DIR__) . '/pdf/angebot_kurszeiten.php';

    // 1. WPForms-Eintrag auslesen
    $entry = null;
    if (class_exists('wpdb') && isset($wpdb)) {
        $table_entries = $wpdb->prefix . 'wpforms_entries';
        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_entries} WHERE entry_id = %d", $entry_id));
    }

    $fields = [];
    if ($entry && !empty($entry->fields)) {
        $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
    }

    // 2. Kurs-ID ermitteln
    $course_id = 0;
    if (!empty($fields)) {
        $course_title = get_field_value($fields, 'Verborgenes Feld');
        $course_id = find_course_id_by_title_exact($course_title);

        if (!$course_id) {
            $kurs_id_raw = get_field_value($fields, 'Kurs ID');
            if ($kurs_id_raw && preg_match('/(\d+)/', (string)$kurs_id_raw, $m)) {
                $candidate_id = intval($m[1]);
                if (get_post_type($candidate_id) === 'courses') {
                    $course_id = $candidate_id;
                }
            }
        }
    }

    // Fallback auf 0 wenn kein Kurs zuordenbar
    $course = new CRM_Model($course_id, $entry_id);

    // 3. Lead-Analyse: Förderfall / AMS / WAFF erkennen
    $foerderung_data = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry_id, $fields) : ['ams' => false, 'waff' => false];
    $message_raw    = (string) get_field_value($fields, 'Nachricht / Freitext');
    $foerderung_raw = (string) get_field_value($fields, 'Förderung');
    $combined_text  = mb_strtolower($message_raw . ' ' . $foerderung_raw . ' ' . ($course->title ?? ''), 'UTF-8');

    $is_ams_foerderfall = !empty($foerderung_data['ams']) || !empty($foerderung_data['waff']);
    if (
        !$is_ams_foerderfall && (
            str_contains($combined_text, 'ams') ||
            str_contains($combined_text, 'waff') ||
            str_contains($combined_text, 'kurszeiten') ||
            str_contains($combined_text, 'kostenvoranschlag') ||
            str_contains($combined_text, 'förderung') ||
            str_contains($combined_text, 'foerderung') ||
            str_contains($combined_text, 'bildungsförderung')
        )
    ) {
        $is_ams_foerderfall = true;
    }

    // 4. Dokumente über bestehende CRM-Generatoren erzeugen
    $generated_docs = [];
    $context = 'xsieben_angebot';

    // PDF Angebot immer generieren
    if (function_exists('xsieben_offer_pdf')) {
        $offer_url = xsieben_offer_pdf($entry_id, $course_id, false);
        $generated_docs['angebot'] = $offer_url;
    }

    // Bei AMS / Förderfall: Kurszeitenbestätigung (KB) zusätzlich generieren
    if ($is_ams_foerderfall && function_exists('xsieben_kb_pdf')) {
        $kb_url = xsieben_kb_pdf($entry_id, $course_id, false);
        $generated_docs['kb'] = $kb_url;
        $context = 'xsieben_angebot_und_kurszeiten';
    }

    // 5. Status im CRM setzen & auditieren
    $note = $is_ams_foerderfall
        ? '🤖 Friedelin Lead-Analyse: AMS-/Förderfall erkannt. Angebot + Kurszeitenbestätigung (KB) generiert. Versandfreigabe durch Hannes erforderlich.'
        : '🤖 Friedelin Lead-Analyse: Reguläres Kursangebot generiert. Versandfreigabe durch Hannes erforderlich.';

    if (function_exists('crm_set_entry_status')) {
        crm_set_entry_status($entry_id, 'ai_prepared', $note, null, 0, $form_id);
    }

    return [
        'success'            => true,
        'entry_id'           => $entry_id,
        'course_id'          => $course_id,
        'course_title'       => $course->title ?? '',
        'customer_name'      => trim(($course->salutation ?? '') . ' ' . ($course->vorname ?? '') . ' ' . ($course->nachname ?? '')),
        'customer_email'     => $course->email ?? '',
        'is_ams_foerderfall' => $is_ams_foerderfall,
        'context'            => $context,
        'status'             => 'ai_prepared',
        'status_label'       => '🤖 KI vorbereitet – Freigabe erforderlich',
        'docs'               => $generated_docs,
        'note'               => $note,
    ];
}
