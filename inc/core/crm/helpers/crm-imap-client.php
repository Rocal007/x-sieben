<?php
/**
 * CRM E-Mail Postfach & IMAP-Synchronisations-Engine
 *
 * Verwaltet IMAP-Einstellungen, Verbindungstests, automatisierte E-Mail-Abgleiche
 * und die nahtlose Übergabe eingegangener Kursanfragen an die CRM-Lead-Ingestion-Engine.
 *
 * Unterstützt sowohl native PHP-Streams (OpenSSL Socket mit IMAP4rev1 Protokoll,
 * zero C-extension dependency) als auch die PHP-IMAP-Erweiterung falls verfügbar.
 *
 * @package Sieben\CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. EINSTELLUNGEN & DEFAULTS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Standardwerte für IMAP-Einstellungen.
 *
 * @return array<string, mixed>
 */
function crm_get_imap_defaults(): array
{
    return [
        'enabled'           => 0,
        'host'              => 'mail.x-sieben.at',
        'port'              => 993,
        'encryption'        => 'ssl', // 'ssl', 'tls', 'none'
        'username'          => 'office@x-sieben.at',
        'password'          => '',
        'mailbox'           => 'INBOX',
        'filter_unseen'     => 1,
        'filter_keywords'   => 'Anfrage, Kurs, Kursfinder, Buchung, Ausbildung, Seminar, Interesse',
        'mark_as_read'      => 1,
        'max_emails'        => 20,
        'auto_create_leads' => 1,
        'run_friedelin'     => 1,
        'sync_interval'     => 'hourly', // 'disabled', 'every15min', 'hourly', 'twicedaily', 'daily'
        'last_sync_time'    => '',
        'last_sync_status'  => __('Noch kein Abgleich durchgeführt.', 'custom-crm'),
        'last_sync_count'   => 0,
        'last_sync_leads'   => [],
    ];
}

/**
 * Ruft die gespeicherten IMAP-Einstellungen ab (mit Fallbacks).
 *
 * @return array<string, mixed>
 */
function crm_get_imap_settings(): array
{
    $defaults = crm_get_imap_defaults();
    $saved    = get_option('crm_imap_settings', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    return wp_parse_args($saved, $defaults);
}

/**
 * Speichert IMAP-Einstellungen ab und aktualisiert den WP-Cron-Zeitplan.
 *
 * @param array $input Rohe Formulardaten
 * @return bool
 */
function crm_save_imap_settings(array $input): bool
{
    $existing = crm_get_imap_settings();
    $defaults = crm_get_imap_defaults();
    $clean    = [];

    $clean['enabled']           = !empty($input['enabled']) ? 1 : 0;
    $clean['host']              = sanitize_text_field($input['host'] ?? $defaults['host']);
    $clean['port']              = absint($input['port'] ?? $defaults['port']) ?: 993;
    $clean['encryption']        = in_array($input['encryption'] ?? '', ['ssl', 'tls', 'none'], true) ? $input['encryption'] : 'ssl';
    $clean['username']          = sanitize_text_field($input['username'] ?? $defaults['username']);

    // Passwort nur überschreiben wenn eingegeben, sonst bestehendes beibehalten
    if (!empty($input['password'])) {
        $clean['password'] = trim($input['password']);
    } else {
        $clean['password'] = $existing['password'] ?? '';
    }

    $clean['mailbox']           = sanitize_text_field($input['mailbox'] ?? 'INBOX');
    if (empty($clean['mailbox'])) {
        $clean['mailbox'] = 'INBOX';
    }

    $clean['filter_unseen']     = isset($input['filter_unseen']) ? (int)!empty($input['filter_unseen']) : 1;
    $clean['filter_keywords']   = sanitize_text_field($input['filter_keywords'] ?? '');
    $clean['mark_as_read']      = isset($input['mark_as_read']) ? (int)!empty($input['mark_as_read']) : 1;
    $clean['max_emails']        = min(100, max(1, absint($input['max_emails'] ?? 20)));
    $clean['auto_create_leads'] = isset($input['auto_create_leads']) ? (int)!empty($input['auto_create_leads']) : 1;
    $clean['run_friedelin']     = isset($input['run_friedelin']) ? (int)!empty($input['run_friedelin']) : 1;
    $clean['sync_interval']     = in_array($input['sync_interval'] ?? '', ['disabled', 'every15min', 'hourly', 'twicedaily', 'daily'], true) ? $input['sync_interval'] : 'hourly';

    // Historie & Status beibehalten
    $clean['last_sync_time']    = $existing['last_sync_time'] ?? '';
    $clean['last_sync_status']  = $existing['last_sync_status'] ?? '';
    $clean['last_sync_count']   = $existing['last_sync_count'] ?? 0;
    $clean['last_sync_leads']   = $existing['last_sync_leads'] ?? [];

    $updated = update_option('crm_imap_settings', $clean);

    // Cronjob anpassen
    crm_reschedule_imap_cron($clean);

    return $updated;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. WP-CRON INTEGRATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Registriert benutzerdefinierte Intervalle für WP-Cron.
 */
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['every15min'])) {
        $schedules['every15min'] = [
            'interval' => 900,
            'display'  => __('Alle 15 Minuten (CRM IMAP Sync)', 'custom-crm'),
        ];
    }
    return $schedules;
});

/**
 * Richtet den WP-Cron-Zeitplan gemäß Einstellungen ein.
 */
function crm_reschedule_imap_cron(?array $settings = null): void
{
    if ($settings === null) {
        $settings = crm_get_imap_settings();
    }

    $hook = 'crm_imap_sync_cron';
    wp_clear_scheduled_hook($hook);

    if (!empty($settings['enabled']) && !empty($settings['sync_interval']) && $settings['sync_interval'] !== 'disabled') {
        $interval = $settings['sync_interval'];
        $allowed  = ['every15min', 'hourly', 'twicedaily', 'daily'];
        if (in_array($interval, $allowed, true)) {
            wp_schedule_event(time() + 60, $interval, $hook);
        }
    }
}

/**
 * Cronjob-Aktions-Handler.
 */
add_action('crm_imap_sync_cron', function () {
    $settings = crm_get_imap_settings();
    if (!empty($settings['enabled'])) {
        crm_run_imap_sync();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. ROBUSTER IMAP SOCKET CLIENT (Zero External C-Dependencies)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * CrmImapClient
 * 
 * Implementiert einen leichtgewichtigen, autarken IMAP4rev1-Client über
 * native PHP OpenSSL Stream Sockets. Funktioniert zuverlässig in jeder
 * Docker-, Apache-, Nginx- und PHP-Umgebung (inkl. PHP 8.1 - 8.4) ohne php-imap.
 */
class CrmImapClient
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private int $timeout;

    /** @var resource|null */
    private $socket = null;
    private int $tagCounter = 1;
    private bool $connected = false;
    private string $selectedMailbox = '';

    public function __construct(string $host, int $port = 993, string $encryption = 'ssl', string $username = '', string $password = '', int $timeout = 15)
    {
        $this->host       = trim($host);
        $this->port       = $port > 0 ? $port : 993;
        $this->encryption = strtolower(trim($encryption));
        $this->username   = trim($username);
        $this->password   = trim($password);
        $this->timeout    = $timeout > 0 ? $timeout : 15;
    }

    /**
     * Stellt die Verbindung zum IMAP-Server her und führt den Login durch.
     *
     * @return array{success: bool, message: string, capability?: string}
     */
    public function connect(): array
    {
        if (empty($this->host)) {
            return ['success' => false, 'message' => __('IMAP-Server Host ist leer.', 'custom-crm')];
        }

        $protocol = ($this->encryption === 'ssl') ? 'ssl://' : 'tcp://';
        $target   = $protocol . $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        $errno  = 0;
        $errstr = '';
        $this->socket = @stream_socket_client($target, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$this->socket) {
            return [
                'success' => false,
                'message' => sprintf(__('Verbindungsaufbau zu %s fehlgeschlagen (%d: %s).', 'custom-crm'), $target, $errno, $errstr),
            ];
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Server-Greeting lesen
        $greeting = $this->readLine();
        if (stripos($greeting, '* OK') === false) {
            $this->disconnect();
            return [
                'success' => false,
                'message' => sprintf(__('Ungültige Server-Antwort: %s', 'custom-crm'), substr($greeting, 0, 100)),
            ];
        }

        // STARTTLS falls gewünscht und Verbindung bisher rein TCP
        if ($this->encryption === 'tls') {
            $startTlsTag = $this->nextTag();
            $res = $this->sendCommand($startTlsTag . " STARTTLS\r\n", $startTlsTag);
            if (!$res['ok']) {
                $this->disconnect();
                return ['success' => false, 'message' => __('STARTTLS vom Server abgelehnt: ' . $res['last_line'], 'custom-crm')];
            }
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->disconnect();
                return ['success' => false, 'message' => __('TLS-Verschlüsselung konnte nicht aktiviert werden.', 'custom-crm')];
            }
        }

        // Login durchführen
        if (!empty($this->username)) {
            $loginTag = $this->nextTag();
            // Quotes escapen
            $userEsc = addcslashes($this->username, '\\"');
            $passEsc = addcslashes($this->password, '\\"');

            $cmd = sprintf("%s LOGIN \"%s\" \"%s\"\r\n", $loginTag, $userEsc, $passEsc);
            $loginRes = $this->sendCommand($cmd, $loginTag);

            if (!$loginRes['ok']) {
                $this->disconnect();
                return [
                    'success' => false,
                    'message' => sprintf(__('IMAP-Authentifizierung fehlgeschlagen: %s', 'custom-crm'), $loginRes['last_line']),
                ];
            }
        }

        $this->connected = true;
        return [
            'success' => true,
            'message' => sprintf(__('Erfolgreich mit IMAP-Server %s verbunden.', 'custom-crm'), $this->host),
        ];
    }

    /**
     * Wählt ein Postfach (z. B. INBOX) aus und liest Metadaten.
     *
     * @param string $mailbox
     * @return array{success: bool, message: string, exists: int, recent: int, unseen: int}
     */
    public function selectMailbox(string $mailbox = 'INBOX'): array
    {
        if (!$this->connected || !$this->socket) {
            return ['success' => false, 'message' => __('Nicht verbunden.', 'custom-crm'), 'exists' => 0, 'recent' => 0, 'unseen' => 0];
        }

        $mailbox = trim($mailbox) ?: 'INBOX';
        $tag = $this->nextTag();
        $cmd = sprintf("%s SELECT \"%s\"\r\n", $tag, addcslashes($mailbox, '\\"'));
        $res = $this->sendCommand($cmd, $tag);

        if (!$res['ok']) {
            return ['success' => false, 'message' => sprintf(__('Ordner "%s" konnte nicht geöffnet werden: %s', 'custom-crm'), $mailbox, $res['last_line']), 'exists' => 0, 'recent' => 0, 'unseen' => 0];
        }

        $this->selectedMailbox = $mailbox;

        $exists = 0;
        $recent = 0;
        $unseen = 0;

        foreach ($res['lines'] as $line) {
            if (preg_match('/^\*\s+(\d+)\s+EXISTS/i', $line, $m)) {
                $exists = (int)$m[1];
            }
            if (preg_match('/^\*\s+(\d+)\s+RECENT/i', $line, $m)) {
                $recent = (int)$m[1];
            }
            if (preg_match('/\[UNSEEN\s+(\d+)\]/i', $line, $m)) {
                $unseen = (int)$m[1];
            }
        }

        // Falls UNSEEN nicht explizit im Select gemeldet wurde, gezielte Suche nach UNSEEN
        if ($unseen === 0 && $exists > 0) {
            $searchUnseen = $this->search('UNSEEN');
            if (!empty($searchUnseen['uids'])) {
                $unseen = count($searchUnseen['uids']);
            }
        }

        return [
            'success' => true,
            'message' => sprintf(__('Ordner "%s" erfolgreich geöffnet.', 'custom-crm'), $mailbox),
            'exists'  => $exists,
            'recent'  => $recent,
            'unseen'  => $unseen,
        ];
    }

    /**
     * Sucht nach E-Mails gemäß Kriterien (z. B. 'UNSEEN', 'ALL').
     *
     * @param string $criteria
     * @return array{success: bool, uids: array<int>}
     */
    public function search(string $criteria = 'UNSEEN'): array
    {
        if (!$this->connected || !$this->socket) {
            return ['success' => false, 'uids' => []];
        }

        $tag = $this->nextTag();
        $cmd = sprintf("%s SEARCH %s\r\n", $tag, $criteria);
        $res = $this->sendCommand($cmd, $tag);

        $ids = [];
        if ($res['ok']) {
            foreach ($res['lines'] as $line) {
                if (stripos($line, '* SEARCH') === 0) {
                    $parts = preg_split('/\s+/', trim(substr($line, 8)));
                    foreach ($parts as $p) {
                        $num = (int)$p;
                        if ($num > 0) {
                            $ids[] = $num;
                        }
                    }
                }
            }
        }

        return ['success' => $res['ok'], 'uids' => $ids];
    }

    /**
     * Ruft eine einzelne E-Mail nach Sequenznummer oder UID ab und parst sie.
     *
     * @param int $seq
     * @return array<string, mixed>|null
     */
    public function fetchMessage(int $seq): ?array
    {
        if (!$this->connected || !$this->socket || $seq <= 0) {
            return null;
        }

        $tag = $this->nextTag();
        $cmd = sprintf("%s FETCH %d (UID RFC822)\r\n", $tag, $seq);
        fwrite($this->socket, $cmd);

        $rawMessage = '';
        $inLiteral  = false;
        $literalRemaining = 0;
        $uid = $seq;

        while (!feof($this->socket)) {
            if ($inLiteral && $literalRemaining > 0) {
                $chunk = fread($this->socket, min($literalRemaining, 8192));
                $rawMessage .= $chunk;
                $literalRemaining -= strlen($chunk);
                if ($literalRemaining <= 0) {
                    $inLiteral = false;
                }
                continue;
            }

            $line = fgets($this->socket);
            if ($line === false) {
                break;
            }

            // Suche UID im Antwortkopf
            if (preg_match('/UID\s+(\d+)/i', $line, $um)) {
                $uid = (int)$um[1];
            }

            // Suche Literal-Ankündigung {12345}
            if (preg_match('/\{(\d+)\}\s*$/', $line, $lm)) {
                $literalRemaining = (int)$lm[1];
                $inLiteral = true;
                continue;
            }

            // Abschluss-Zeile prüfen
            if (strpos($line, $tag . ' OK') === 0) {
                break;
            }
            if (strpos($line, $tag . ' NO') === 0 || strpos($line, $tag . ' BAD') === 0) {
                break;
            }
        }

        if (empty($rawMessage)) {
            return null;
        }

        return $this->parseRawRfc822($rawMessage, $uid, $seq);
    }

    /**
     * Markiert eine E-Mail als gelesen (\Seen).
     *
     * @param int $seq
     * @return bool
     */
    public function markAsSeen(int $seq): bool
    {
        if (!$this->connected || !$this->socket || $seq <= 0) {
            return false;
        }

        $tag = $this->nextTag();
        $cmd = sprintf("%s STORE %d +FLAGS (\\Seen)\r\n", $tag, $seq);
        $res = $this->sendCommand($cmd, $tag);
        return $res['ok'];
    }

    /**
     * Trennt die Verbindung sauber via LOGOUT.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            if ($this->connected) {
                $tag = $this->nextTag();
                @fwrite($this->socket, $tag . " LOGOUT\r\n");
            }
            @fclose($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }

    // ─────────────────────────────────────────────
    // INTERNE PROTOKOLL-HILFEN
    // ─────────────────────────────────────────────

    private function nextTag(): string
    {
        return 'A' . str_pad((string)($this->tagCounter++), 4, '0', STR_PAD_LEFT);
    }

    private function readLine(): string
    {
        if (!$this->socket) {
            return '';
        }
        $line = fgets($this->socket, 4096);
        return $line !== false ? trim($line) : '';
    }

    /**
     * @return array{ok: bool, lines: array<string>, last_line: string}
     */
    private function sendCommand(string $cmd, string $expectedTag): array
    {
        if (!$this->socket) {
            return ['ok' => false, 'lines' => [], 'last_line' => 'Not connected'];
        }

        fwrite($this->socket, $cmd);

        $lines = [];
        $lastLine = '';

        while (!feof($this->socket)) {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                break;
            }
            $trimmed = trim($line);
            $lines[] = $trimmed;
            $lastLine = $trimmed;

            if (strpos($trimmed, $expectedTag . ' OK') === 0) {
                return ['ok' => true, 'lines' => $lines, 'last_line' => $trimmed];
            }
            if (strpos($trimmed, $expectedTag . ' NO') === 0 || strpos($trimmed, $expectedTag . ' BAD') === 0) {
                return ['ok' => false, 'lines' => $lines, 'last_line' => $trimmed];
            }
        }

        return ['ok' => false, 'lines' => $lines, 'last_line' => $lastLine];
    }

    /**
     * Parst eine rohe RFC822 E-Mail (Header und Body mit Multipart / Charset Normalisierung).
     *
     * @param string $raw
     * @param int $uid
     * @param int $seq
     * @return array<string, mixed>
     */
    private function parseRawRfc822(string $raw, int $uid, int $seq): array
    {
        // Teile Header und Body
        $parts = preg_split("/\r?\n\r?\n/", $raw, 2);
        $headerRaw = $parts[0] ?? '';
        $bodyRaw   = $parts[1] ?? '';

        // Header falten (Multiline Headers auflösen)
        $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $headerRaw);
        $headerLines = explode("\n", $unfolded);

        $headers = [];
        foreach ($headerLines as $hl) {
            if (strpos($hl, ':') !== false) {
                [$k, $v] = explode(':', $hl, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                $headers[$k] = $v;
            }
        }

        // MIME-dekodieren
        $subject = $this->decodeMimeHeader($headers['subject'] ?? '');
        $from    = $this->decodeMimeHeader($headers['from'] ?? '');
        $to      = $this->decodeMimeHeader($headers['to'] ?? '');
        $date    = $headers['date'] ?? '';
        $msgId   = trim($headers['message-id'] ?? '', '<>');

        // Absender E-Mail extrahieren
        $fromEmail = '';
        if (preg_match('/<([^>]+)>/', $from, $fm)) {
            $fromEmail = trim($fm[1]);
        } elseif (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $from, $fm)) {
            $fromEmail = trim($fm[0]);
        }

        // Body extrahieren (Plain Text bevorzugt)
        $bodyText = $this->extractBodyText($bodyRaw, $headers);

        return [
            'uid'        => $uid,
            'seq'        => $seq,
            'message_id' => $msgId ?: ('uid_' . $uid . '_' . md5($subject . $date)),
            'subject'    => $subject,
            'from'       => $from,
            'from_email' => $fromEmail,
            'to'         => $to,
            'date'       => $date,
            'body'       => $bodyText,
            'raw_length' => strlen($raw),
        ];
    }

    private function decodeMimeHeader(string $value): string
    {
        if (empty($value)) {
            return '';
        }
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false) {
                return $decoded;
            }
        }
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($value);
        }
        return $value;
    }

    private function extractBodyText(string $rawBody, array $headers): string
    {
        $contentType = $headers['content-type'] ?? 'text/plain';
        $transferEnc = strtolower(trim($headers['content-transfer-encoding'] ?? ''));

        // Dekodiere Haupt-Body falls nicht multipart
        $decodedBody = $rawBody;
        if ($transferEnc === 'base64') {
            $decodedBody = base64_decode(preg_replace('/\s+/', '', $rawBody));
        } elseif ($transferEnc === 'quoted-printable') {
            $decodedBody = quoted_printable_decode($rawBody);
        }

        // Falls multipart: suche boundary
        if (stripos($contentType, 'multipart/') !== false && preg_match('/boundary=["\']?([^"\'\s;]+)["\']?/i', $contentType, $bm)) {
            $boundary = $bm[1];
            $parts = explode('--' . $boundary, $rawBody);
            $foundText = '';
            $foundHtml = '';

            foreach ($parts as $p) {
                if (trim($p) === '' || trim($p) === '--') continue;
                $subParts = preg_split("/\r?\n\r?\n/", $p, 2);
                $subHeader = $subParts[0] ?? '';
                $subBody   = $subParts[1] ?? '';

                $subEncoding = '';
                if (preg_match('/content-transfer-encoding:\s*([^\s;]+)/i', $subHeader, $em)) {
                    $subEncoding = strtolower($em[1]);
                }
                if ($subEncoding === 'base64') {
                    $subBody = base64_decode(preg_replace('/\s+/', '', $subBody));
                } elseif ($subEncoding === 'quoted-printable') {
                    $subBody = quoted_printable_decode($subBody);
                }

                if (stripos($subHeader, 'text/plain') !== false && empty($foundText)) {
                    $foundText = $subBody;
                } elseif (stripos($subHeader, 'text/html') !== false && empty($foundHtml)) {
                    $foundHtml = $subBody;
                }
            }

            if (!empty($foundText)) {
                $decodedBody = $foundText;
            } elseif (!empty($foundHtml)) {
                $decodedBody = wp_strip_all_tags($foundHtml);
            }
        } elseif (stripos($contentType, 'text/html') !== false) {
            $decodedBody = wp_strip_all_tags($decodedBody);
        }

        // Zeichensatz nach UTF-8 konvertieren falls angegeben
        if (preg_match('/charset=["\']?([^"\'\s;]+)["\']?/i', $contentType, $cm)) {
            $charset = strtoupper(trim($cm[1]));
            if ($charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $decodedBody = @mb_convert_encoding($decodedBody, 'UTF-8', $charset) ?: $decodedBody;
            }
        }

        return trim($decodedBody);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. TESTVERBINDUNG & SYNCHRONISATIONS-ORCHESTRIERUNG
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Führt einen Verbindungstest zu den angegebenen oder gespeicherten IMAP-Daten durch.
 *
 * @param array|null $override_settings
 * @return array{success: bool, message: string, details?: array}
 */
function crm_test_imap_connection(?array $override_settings = null): array
{
    $settings = $override_settings ? wp_parse_args($override_settings, crm_get_imap_settings()) : crm_get_imap_settings();

    if (empty($settings['host'])) {
        return ['success' => false, 'message' => __('Bitte tragen Sie einen IMAP-Server Host ein.', 'custom-crm')];
    }
    if (empty($settings['username'])) {
        return ['success' => false, 'message' => __('Bitte tragen Sie eine E-Mail-Adresse / Benutzername ein.', 'custom-crm')];
    }
    if (empty($settings['password'])) {
        return ['success' => false, 'message' => __('Bitte tragen Sie ein Passwort ein.', 'custom-crm')];
    }

    $client = new CrmImapClient(
        $settings['host'],
        (int)$settings['port'],
        $settings['encryption'],
        $settings['username'],
        $settings['password'],
        15
    );

    $conn = $client->connect();
    if (!$conn['success']) {
        return $conn;
    }

    $mailbox = $settings['mailbox'] ?: 'INBOX';
    $mbox = $client->selectMailbox($mailbox);
    $client->disconnect();

    if (!$mbox['success']) {
        return [
            'success' => false,
            'message' => sprintf(__('Verbindung hergestellt, aber Postfach "%s" konnte nicht geöffnet werden: %s', 'custom-crm'), $mailbox, $mbox['message']),
        ];
    }

    return [
        'success' => true,
        'message' => sprintf(
            __('Verbindung erfolgreich! Postfach "%s" enthält %d E-Mails (%d ungelesen, %d neu).', 'custom-crm'),
            $mailbox,
            $mbox['exists'],
            $mbox['unseen'],
            $mbox['recent']
        ),
        'details' => [
            'host'    => $settings['host'],
            'port'    => $settings['port'],
            'mailbox' => $mailbox,
            'exists'  => $mbox['exists'],
            'unseen'  => $mbox['unseen'],
            'recent'  => $mbox['recent'],
        ],
    ];
}

/**
 * Führt den vollständigen IMAP-Abgleich oder einen Trockenlauf (Test-Simulation) aus:
 * Liest neue E-Mails, filtert nach Kriterien, parst Anfragen und legt CRM-Leads an.
 *
 * Im $dry_run Modus:
 * - 0 Schreibzugriffe auf die Datenbank (weder wp_wpforms_entries noch wp_crm_entry_status)
 * - Keine Markierung als gelesen (\Seen) auf dem Mailserver
 * - Keine Modifikation von crm_imap_synced_uids oder Audit-Logs
 * - Gibt strukturierte Simulationsdaten mit extrahierten Kundendaten, Kurserkennung und Einstufung zurück.
 *
 * @param array|null $override_settings
 * @param bool $dry_run
 * @return array{success: bool, dry_run: bool, message: string, scanned: int, imported: int, qualified: int, skipped: int, leads: array, log: array, items: array, used_fallback?: bool}
 */
function crm_run_imap_sync(?array $override_settings = null, bool $dry_run = false): array
{
    $settings = $override_settings ? wp_parse_args($override_settings, crm_get_imap_settings()) : crm_get_imap_settings();

    $client = new CrmImapClient(
        $settings['host'],
        (int)$settings['port'],
        $settings['encryption'],
        $settings['username'],
        $settings['password'],
        20
    );

    $conn = $client->connect();
    if (!$conn['success']) {
        if (!$dry_run) {
            crm_record_sync_result($settings, false, $conn['message'], 0, 0, []);
        }
        return [
            'success'   => false,
            'dry_run'   => $dry_run,
            'message'   => $conn['message'],
            'scanned'   => 0,
            'imported'  => 0,
            'qualified' => 0,
            'skipped'   => 0,
            'leads'     => [],
            'log'       => [],
            'items'     => [],
        ];
    }

    $mailbox = $settings['mailbox'] ?: 'INBOX';
    $mbox = $client->selectMailbox($mailbox);
    if (!$mbox['success']) {
        $client->disconnect();
        $msg = sprintf(__('Ordner "%s" nicht verfügbar: %s', 'custom-crm'), $mailbox, $mbox['message']);
        if (!$dry_run) {
            crm_record_sync_result($settings, false, $msg, 0, 0, []);
        }
        return [
            'success'   => false,
            'dry_run'   => $dry_run,
            'message'   => $msg,
            'scanned'   => 0,
            'imported'  => 0,
            'qualified' => 0,
            'skipped'   => 0,
            'leads'     => [],
            'log'       => [],
            'items'     => [],
        ];
    }

    // Suchkriterium: Standardmäßig UNSEEN, falls deaktiviert dann ALL
    $criteria = !empty($settings['filter_unseen']) ? 'UNSEEN' : 'ALL';
    $searchRes = $client->search($criteria);
    $seqList = $searchRes['uids'] ?? [];
    $usedFallbackAll = false;

    // Im Trockenlauf: Falls UNSEEN keine Mails liefert, testen wir auf ALL,
    // damit der Nutzer sofort die Extraktion an realen Mails im Postfach prüfen kann!
    if ($dry_run && empty($seqList) && $criteria === 'UNSEEN') {
        $searchResAll = $client->search('ALL');
        $allUids = $searchResAll['uids'] ?? [];
        if (!empty($allUids)) {
            $seqList = $allUids;
            $usedFallbackAll = true;
        }
    }

    if (empty($seqList)) {
        $client->disconnect();
        $msg = sprintf(__('Abgleich abgeschlossen: Keine neuen (%s) E-Mails in "%s" gefunden.', 'custom-crm'), $criteria, $mailbox);
        if (!$dry_run) {
            crm_record_sync_result($settings, true, $msg, 0, 0, []);
        }
        return [
            'success'   => true,
            'dry_run'   => $dry_run,
            'message'   => $msg,
            'scanned'   => 0,
            'imported'  => 0,
            'qualified' => 0,
            'skipped'   => 0,
            'leads'     => [],
            'log'       => [],
            'items'     => [],
        ];
    }

    // Bereits synchronisierte Message-IDs / UIDs aus der DB abrufen
    $syncedMap = get_option('crm_imap_synced_uids', []);
    if (!is_array($syncedMap)) {
        $syncedMap = [];
    }

    // Begrenzung auf maximale Anzahl E-Mails pro Durchlauf (neueste zuerst)
    rsort($seqList, SORT_NUMERIC);
    $limit = (int)($settings['max_emails'] ?? 20);
    $processList = array_slice($seqList, 0, $limit);

    $scannedCount    = 0;
    $importedCount   = 0;
    $qualifiedCount  = 0;
    $skippedCount    = 0;
    $createdLeads    = [];
    $logEntries      = [];
    $simulationItems = [];

    // Filter-Schlüsselwörter vorbereiten
    $keywordList = [];
    if (!empty($settings['filter_keywords'])) {
        $rawKeywords = explode(',', $settings['filter_keywords']);
        foreach ($rawKeywords as $kw) {
            $t = mb_strtolower(trim($kw));
            if (!empty($t)) {
                $keywordList[] = $t;
            }
        }
    }

    // Anfragen-Parser und Hilfsfunktionen laden
    if (!function_exists('crm_parse_email_inquiry_text')) {
        require_once __DIR__ . '/crm-email-lead-importer.php';
    }

    global $wpdb;

    foreach ($processList as $seq) {
        $email = $client->fetchMessage($seq);
        if (!$email) {
            continue;
        }

        $scannedCount++;
        $msgKey = $email['message_id'];

        // Duplikatschutz prüfen
        $isAlreadySynced = isset($syncedMap[$msgKey]);

        // Schlüsselwort-Filter prüfen
        $matchesKeyword = true;
        if (!empty($keywordList)) {
            $matchesKeyword = false;
            $combinedText = mb_strtolower($email['subject'] . ' ' . $email['body']);
            foreach ($keywordList as $kw) {
                if (mb_strpos($combinedText, $kw) !== false) {
                    $matchesKeyword = true;
                    break;
                }
            }
        }

        // Anfragen-Parser aufrufen
        $rawContent = "Betreff: " . $email['subject'] . "\nVon: " . $email['from'] . "\nDatum: " . $email['date'] . "\n\n" . $email['body'];
        $parsed = crm_parse_email_inquiry_text($rawContent);

        // Kurs-Zuordnung ermitteln
        $matchedCourse = function_exists('crm_match_course_by_inquiry')
            ? crm_match_course_by_inquiry($rawContent, $email['subject'])
            : ['course_id' => 0, 'course_title' => ''];

        // Externe Kundenadresse ermitteln
        $hasCustomerEmail = !empty($parsed['email']) && (!function_exists('crm_is_internal_email') || !crm_is_internal_email($parsed['email']));

        // Prüfen, ob Kunde bereits im CRM als Lead existiert
        $existingEntryId = null;
        if (!empty($parsed['email'])) {
            $clean_email = strtolower(trim($parsed['email']));
            $existingRow = $wpdb->get_row($wpdb->prepare(
                "SELECT entry_id FROM {$wpdb->prefix}wpforms_entries WHERE fields LIKE %s ORDER BY entry_id DESC LIMIT 1",
                '%' . $wpdb->esc_like('"value":"' . $clean_email . '"') . '%'
            ), ARRAY_A);
            if ($existingRow && !empty($existingRow['entry_id'])) {
                $existingEntryId = (int)$existingRow['entry_id'];
            }
        }

        // ─── IM TROCKENLAUF (SIMULATION): 0 DB-SCHREIBVORGÄNGE ───
        if ($dry_run) {
            $verdict      = 'qualified_lead';
            $verdictLabel = __('✓ Qualifizierter Lead (Neuanlage)', 'custom-crm');
            $verdictColor = '#15803d';
            $reason       = __('Gültige Kundenanfrage erkannt: Würde als neuer Lead im CRM angelegt werden.', 'custom-crm');

            if ($isAlreadySynced) {
                $verdict      = 'skipped_synced';
                $verdictLabel = __('Bereits im CRM erfasst', 'custom-crm');
                $verdictColor = '#64748b';
                $reason       = __('Diese E-Mail wurde bei einem früheren Abgleich bereits erfasst.', 'custom-crm');
                $skippedCount++;
            } elseif (!$matchesKeyword) {
                $verdict      = 'skipped_keyword';
                $verdictLabel = __('Schlüsselwort fehlt', 'custom-crm');
                $verdictColor = '#d97706';
                $reason       = sprintf(__('Entspricht keinem der Filter-Schlüsselwörter (%s).', 'custom-crm'), implode(', ', $keywordList));
                $skippedCount++;
            } elseif (!$hasCustomerEmail) {
                $verdict      = 'skipped_internal';
                $verdictLabel = __('Intern / Systemabsender', 'custom-crm');
                $verdictColor = '#94a3b8';
                $reason       = __('Keine externe Kunden-E-Mail erkannt (interne Teamadresse oder Systemmeldung).', 'custom-crm');
                $skippedCount++;
            } elseif ($existingEntryId) {
                $verdict      = 'existing_lead';
                $verdictLabel = sprintf(__('Bestandskunde (Lead #%d)', 'custom-crm'), $existingEntryId);
                $verdictColor = '#0284c7';
                $reason       = sprintf(__('Kunde bereits mit Lead #%d im CRM vorhanden (Folgekommunikation).', 'custom-crm'), $existingEntryId);
                $qualifiedCount++;
            } else {
                $qualifiedCount++;
            }

            $clientName = trim(($parsed['anrede'] ?? '') . ' ' . ($parsed['vorname'] ?? '') . ' ' . ($parsed['nachname'] ?? ''));
            if (empty($clientName) && !empty($parsed['email'])) {
                $clientName = $parsed['email'];
            }

            $courseTitle = !empty($matchedCourse['course_title'])
                ? $matchedCourse['course_title']
                : (!empty($parsed['kurs_titel']) ? $parsed['kurs_titel'] : __('Allgemeine Anfrage / Kursberatung', 'custom-crm'));

            $courseId = !empty($matchedCourse['course_id']) ? (int)$matchedCourse['course_id'] : (int)($parsed['kurs_id'] ?? 0);

            $dates = !empty($parsed['termin'])
                ? $parsed['termin']
                : (!empty($parsed['start_datum']) ? ($parsed['start_datum'] . (!empty($parsed['end_datum']) ? ' bis ' . $parsed['end_datum'] : '')) : '');

            $funding = !empty($parsed['foerderung']) ? (!empty($parsed['foerderstelle']) ? $parsed['foerderstelle'] : 'Ja (Gefördert)') : 'Keine';

            $addressParts = array_filter([$parsed['strasse'] ?? '', ($parsed['plz'] ?? '') . ' ' . ($parsed['ort'] ?? '')]);
            $address = implode(', ', $addressParts);

            $simulationItems[] = [
                'seq'             => $seq,
                'uid'             => $email['uid'] ?? $seq,
                'subject'         => $email['subject'] ?: __('(Kein Betreff)', 'custom-crm'),
                'from'            => $email['from'] ?: 'Unbekannt',
                'from_email'      => $email['from_email'] ?: '',
                'date'            => $email['date'] ?: '',
                'verdict'         => $verdict,
                'verdict_label'   => $verdictLabel,
                'verdict_color'   => $verdictColor,
                'reason'          => $reason,
                'existing_id'     => $existingEntryId,
                'client_name'     => $clientName,
                'salutation'      => $parsed['anrede'] ?? '',
                'email'           => $parsed['email'] ?? '',
                'phone'           => $parsed['telefon'] ?? '',
                'company'         => $parsed['firma'] ?? '',
                'course_title'    => $courseTitle,
                'course_id'       => $courseId,
                'dates'           => $dates,
                'funding'         => $funding,
                'address'         => $address,
                'preview_snippet' => mb_substr(preg_replace('/\s+/', ' ', strip_tags($parsed['nachricht'] ?? $email['body'])), 0, 180) . '...',
            ];

            continue;
        }

        // ─── ECHTER LIVE-ABGLEICH (DATEN WERDEN GESPEICHERT) ───
        if ($isAlreadySynced) {
            $logEntries[] = [
                'subject' => $email['subject'],
                'from'    => $email['from'],
                'date'    => $email['date'],
                'action'  => 'skipped',
                'reason'  => __('Bereits zuvor synchronisiert.', 'custom-crm'),
            ];
            continue;
        }

        if (!$matchesKeyword) {
            $logEntries[] = [
                'subject' => $email['subject'],
                'from'    => $email['from'],
                'date'    => $email['date'],
                'action'  => 'skipped',
                'reason'  => __('Keine Übereinstimmung mit Filter-Schlüsselwörtern.', 'custom-crm'),
            ];
            $syncedMap[$msgKey] = time();
            continue;
        }

        if (!$hasCustomerEmail) {
            $logEntries[] = [
                'subject' => $email['subject'],
                'from'    => $email['from'],
                'date'    => $email['date'],
                'action'  => 'ignored',
                'reason'  => __('Keine Kundenadresse erkannt (intern oder Systemabsender).', 'custom-crm'),
            ];
            $syncedMap[$msgKey] = time();
            continue;
        }

        // Lead im CRM anlegen
        if (!empty($settings['auto_create_leads'])) {
            $runPrep = !empty($settings['run_friedelin']);
            $res = crm_create_lead_from_parsed_data($parsed, $runPrep);

            if (!empty($res['success']) && !empty($res['entry_id'])) {
                $entryId = (int)$res['entry_id'];
                $importedCount++;
                $createdLeads[] = [
                    'entry_id'    => $entryId,
                    'client_name' => trim(($parsed['vorname'] ?? '') . ' ' . ($parsed['nachname'] ?? '')),
                    'course'      => $parsed['kurs_titel'] ?? 'Kurs',
                    'subject'     => $email['subject'],
                ];

                $logEntries[] = [
                    'subject'  => $email['subject'],
                    'from'     => $email['from'],
                    'date'     => $email['date'],
                    'action'   => 'created',
                    'entry_id' => $entryId,
                    'reason'   => sprintf(__('Lead #%d erfolgreich angelegt.', 'custom-crm'), $entryId),
                ];

                // Gelesen-Flag setzen
                if (!empty($settings['mark_as_read'])) {
                    $client->markAsSeen($seq);
                }
            } else {
                $logEntries[] = [
                    'subject' => $email['subject'],
                    'from'    => $email['from'],
                    'date'    => $email['date'],
                    'action'  => 'error',
                    'reason'  => $res['message'] ?? __('Fehler beim Anlegen des Eintrags.', 'custom-crm'),
                ];
            }
        } else {
            $logEntries[] = [
                'subject' => $email['subject'],
                'from'    => $email['from'],
                'date'    => $email['date'],
                'action'  => 'preview',
                'reason'  => __('Anfrage erkannt (automatisches Anlegen ist deaktiviert).', 'custom-crm'),
            ];
        }

        $syncedMap[$msgKey] = time();
    }

    $client->disconnect();

    // ─── ABSCHLUSS TROCKENLAUF ───
    if ($dry_run) {
        $summary = sprintf(
            __('Test-Simulation abgeschlossen: %d E-Mails geprüft — %d Anfragen/Leads erkannt, %d übersprungen (Trockenlauf: 0 DB-Änderungen).', 'custom-crm'),
            $scannedCount,
            $qualifiedCount,
            $skippedCount
        );

        return [
            'success'       => true,
            'dry_run'       => true,
            'message'       => $summary,
            'scanned'       => $scannedCount,
            'imported'      => 0,
            'qualified'     => $qualifiedCount,
            'skipped'       => $skippedCount,
            'leads'         => [],
            'log'           => [],
            'items'         => $simulationItems,
            'used_fallback' => $usedFallbackAll,
        ];
    }

    // ─── ABSCHLUSS LIVE-ABGLEICH ───
    if (count($syncedMap) > 1000) {
        asort($syncedMap);
        $syncedMap = array_slice($syncedMap, -800, null, true);
    }
    update_option('crm_imap_synced_uids', $syncedMap);

    $summary = sprintf(
        __('Abgleich abgeschlossen: %d E-Mails gescannt, %d neue Leads angelegt.', 'custom-crm'),
        $scannedCount,
        $importedCount
    );

    crm_record_sync_result($settings, true, $summary, $scannedCount, $importedCount, $createdLeads, $logEntries);

    return [
        'success'   => true,
        'dry_run'   => false,
        'message'   => $summary,
        'scanned'   => $scannedCount,
        'imported'  => $importedCount,
        'qualified' => $importedCount,
        'skipped'   => $scannedCount - $importedCount,
        'leads'     => $createdLeads,
        'log'       => $logEntries,
        'items'     => [],
    ];
}

/**
 * Protokolliert das Ergebnis eines Abgleichs in den Einstellungen und im Audit-Log.
 */
function crm_record_sync_result(array $settings, bool $success, string $status, int $scanned, int $imported, array $leads, array $logEntries = []): void
{
    $now = current_time('mysql');
    $settings['last_sync_time']   = $now;
    $settings['last_sync_status'] = ($success ? '✓ ' : '✕ ') . $status;
    $settings['last_sync_count']  = $scanned;
    $settings['last_sync_leads']  = $leads;

    update_option('crm_imap_settings', $settings);

    // Detaillierten Log-Verlauf in eigener Option halten
    $history = get_option('crm_imap_sync_history', []);
    if (!is_array($history)) {
        $history = [];
    }

    array_unshift($history, [
        'time'     => $now,
        'success'  => $success,
        'message'  => $status,
        'scanned'  => $scanned,
        'imported' => $imported,
        'leads'    => $leads,
        'items'    => array_slice($logEntries, 0, 10),
    ]);

    // Begrenze auf die letzten 30 Durchläufe
    $history = array_slice($history, 0, 30);
    update_option('crm_imap_sync_history', $history);
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. AJAX-ENDPUNKTE FÜR DIE CRM-ADMIN-UI
// ─────────────────────────────────────────────────────────────────────────────

/**
 * AJAX: Verbindungstest
 */
add_action('wp_ajax_crm_test_imap_connection', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine ausreichenden Berechtigungen.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung (Nonce) fehlgeschlagen.', 'custom-crm')]);
    }

    $override = [];
    if (!empty($_POST['host'])) {
        $override['host'] = sanitize_text_field($_POST['host']);
    }
    if (!empty($_POST['port'])) {
        $override['port'] = absint($_POST['port']);
    }
    if (!empty($_POST['encryption'])) {
        $override['encryption'] = sanitize_text_field($_POST['encryption']);
    }
    if (!empty($_POST['username'])) {
        $override['username'] = sanitize_text_field($_POST['username']);
    }
    if (!empty($_POST['password'])) {
        $override['password'] = trim($_POST['password']);
    }
    if (!empty($_POST['mailbox'])) {
        $override['mailbox'] = sanitize_text_field($_POST['mailbox']);
    }

    $result = crm_test_imap_connection(!empty($override) ? $override : null);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

/**
 * AJAX: Sofortiger E-Mail-Abgleich oder Test-Simulation (Dry-Run)
 */
add_action('wp_ajax_crm_sync_imap_emails', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine ausreichenden Berechtigungen.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung (Nonce) fehlgeschlagen.', 'custom-crm')]);
    }

    $dry_run = !empty($_POST['dry_run']);

    $override = [];
    if (!empty($_POST['host'])) {
        $override['host'] = sanitize_text_field($_POST['host']);
    }
    if (!empty($_POST['port'])) {
        $override['port'] = absint($_POST['port']);
    }
    if (!empty($_POST['encryption'])) {
        $override['encryption'] = sanitize_text_field($_POST['encryption']);
    }
    if (!empty($_POST['username'])) {
        $override['username'] = sanitize_text_field($_POST['username']);
    }
    if (!empty($_POST['password'])) {
        $override['password'] = trim($_POST['password']);
    }
    if (!empty($_POST['mailbox'])) {
        $override['mailbox'] = sanitize_text_field($_POST['mailbox']);
    }
    if (isset($_POST['filter_unseen'])) {
        $override['filter_unseen'] = !empty($_POST['filter_unseen']) ? 1 : 0;
    }
    if (isset($_POST['filter_keywords'])) {
        $override['filter_keywords'] = sanitize_text_field($_POST['filter_keywords']);
    }
    if (!empty($_POST['max_emails'])) {
        $override['max_emails'] = absint($_POST['max_emails']);
    }

    $result = crm_run_imap_sync(!empty($override) ? $override : null, $dry_run);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});
