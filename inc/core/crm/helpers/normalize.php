<?php
/**
 * Normalizes a string for comparison by making it lowercase, trimming whitespace,
 * and removing special characters.
 *
 * @param string $str The input string.
 * @return string The normalized string.
 */
function normalize_string($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    // Replace en-dash, em-dash, and minus sign with a standard hyphen
    $str = preg_replace('/[\x{2013}\x{2014}\x{2212}]/u', '-', $str);
    // Remove registered trademark symbol and replace slashes with spaces
    $str = str_replace(['®', '/'], ['', ' '], $str);
    // Collapse multiple whitespace characters into a single space
    $str = preg_replace('/\s+/', ' ', $str);
    // Remove all characters except lowercase letters, numbers, hyphens, and spaces
    return preg_replace('/[^a-z0-9\- ]/', '', $str);
}

/**
 * Strips all internal AI preparation notices, Friedelin tags, and approval disclaimers
 * from email bodies and templates. These notices are strictly for internal CRM review
 * and must NEVER be visible to the customer or present in outgoing emails.
 *
 * @param string $html
 * @return string Cleaned HTML / text without internal AI notices.
 */
function crm_strip_internal_ai_notices($html) {
    if (empty($html) || !is_string($html)) {
        return '';
    }

    // 1. Remove <span> elements containing Friedelin / KI-Assistent / Vorbereitet von / Freigabe disclaimers
    $html = preg_replace('/<span\b[^>]*>(?:(?!<\/span>).)*(?:Vorbereitet\s+von|Friedelin|KI-Assistent|Gepr(?:ü|&uuml;)ft\s*&(?:amp;)?\s*freigegeben|Manuelle\s+Pr(?:ü|&uuml;)fung)(?:(?!<\/span>).)*<\/span>/isu', '', $html);

    // 2. Remove dedicated Friedelin <div> or <p> containers if class or id contains friedelin
    $html = preg_replace('/<(?:div|p)\b[^>]*(?:class|id)=["\'][^"\']*friedelin[^"\']*["\'][^>]*>.*?<\/(?:div|p)>/isu', '', $html);

    // 3. Remove any remaining plaintext badge text without touching sender names or greetings
    $pattern_text = [
        '/(?:🤖\s*)?Vorbereitet\s+von\s+(?:<strong>)?Friedelin(?:<\/strong>)?(?:\s*\([^)]*\))?\s*(?:&bull;|•|-)?\s*(?:<em>)?(?:Gepr(?:ü|&uuml;)ft\s*&(?:amp;)?\s*freigegeben[^<\r\n]*|Manuelle\s+Pr(?:ü|&uuml;)fung[^<\r\n]*)?(?:<\/em>)?/iu',
        '/(?:🤖\s*)?Vorbereitet\s+von\s+Friedelin[^<\r\n]*/iu',
        '/(?:&bull;|•|-)?\s*(?:<em>)?\s*Gepr(?:ü|&uuml;)ft\s*&(?:amp;)?\s*freigegeben\s+von\s+Mag\.\s+Dr\.\s+Johannes\s+Gasberger\s*(?:<\/em>)?/iu',
        '/(?:&bull;|•|-)?\s*(?:<em>)?\s*Manuelle\s+Pr(?:ü|&uuml;)fung\s*&(?:amp;)?\s*Freigabe\s+durch\s+Gesch(?:ä|&auml;)ftsf(?:ü|&uuml;)hrung\s*(?:<\/em>)?/iu',
    ];
    $html = preg_replace($pattern_text, '', $html);

    // 4. Clean up any trailing breaks before closing paragraph or excessive breaks
    $html = preg_replace('#(<br\s*/?>\s*)+(</p>)#i', '$2', $html);
    $html = preg_replace('#<p>\s*</p>#i', '', $html);
    $html = preg_replace('#(<br\s*/?>\s*){3,}#i', '<br><br>', $html);

    return $html;
}

if (!has_filter('wp_mail', 'crm_filter_strip_internal_ai_notices_from_mail')) {
    function crm_filter_strip_internal_ai_notices_from_mail($args) {
        if (isset($args['message']) && is_string($args['message'])) {
            $args['message'] = crm_strip_internal_ai_notices($args['message']);
        }
        return $args;
    }
    add_filter('wp_mail', 'crm_filter_strip_internal_ai_notices_from_mail', 9999);
}

/**
 * Normalizes HTML for transactional and CRM outgoing emails.
 *
 * Ensures all image sources (src) and hyperlinks (href) use full, valid, canonical HTTPS URLs
 * pointing to the live production server (https://x-sieben.at), eliminating relative paths
 * (/wp-content/...), local test domains, redirecting domains (www.), and destructive
 * attributes added by cookie consent plugins (e.g. consent-original-src-_).
 *
 * @param string $body Raw or semi-processed HTML content.
 * @return string Fully normalized, mail-client-ready HTML string.
 */
function crm_prepare_email_html_for_sending($body) {
    if (empty($body) || !is_string($body)) {
        return '';
    }

    // 0a. Strip any internal AI preparation notices / Friedelin badges (MUST NEVER appear in emails)
    $body = crm_strip_internal_ai_notices($body);

    $canonical_host = 'https://x-sieben.at';

    // 0b. Strip TinyMCE editor artifacts globally before attribute parsing
    $body = preg_replace('/\s*data-mce-[a-z0-9_-]+=["\'][^"\']*["\']/i', '', $body);

    // 0c. Replace any legacy Dr. Gasberger signatures in customer-facing offer/consultation emails with Anna Brauer
    if (stripos($body, 'Mag. Dr. Johannes Gasberger') !== false && stripos($body, 'Geschäftsführer') !== false) {
        $sig_field = '';
        if (function_exists('crm_get_merged_custom_fields')) {
            $c_fields = crm_get_merged_custom_fields();
            foreach ($c_fields as $cf) {
                if (strcasecmp(trim($cf['title'] ?? ''), 'E-Mail Signatur') === 0 && !empty($cf['content'])) {
                    $sig_field = $cf['content'];
                    break;
                }
            }
        }
        $anna_brauer_sig = !empty($sig_field) ? $sig_field : '<p style="margin:0; line-height:1.4;">Herzliche Grüße<br><br><strong>Anna Brauer</strong><br><span style="color:#64748b; font-size:12px;">Kundenbetreuung &amp; Lehrgangsmanagement | X SIEBEN Wirtschaftstraining GmbH</span><br><span style="color:#007C90; font-size:11px;">Gebührenfrei: 0800 / 700 170 &bull; <a href="https://x-sieben.at" style="color:#007C90; text-decoration:none;">www.x-sieben.at</a></span></p>';
        $body = preg_replace('/<p[^>]*>\s*(?:Mit besten Grüßen|Herzliche Grüße|Freundliche Grüße)[\s\S]*?Mag\.\s*Dr\.\s*Johannes\s*Gasberger[\s\S]*?<\/p>/iu', $anna_brauer_sig, $body);
        $body = preg_replace('/(?:Mit besten Grüßen|Herzliche Grüße|Freundliche Grüße)[,\s]*<br\s*\/?>\s*<br\s*\/?>\s*<strong>\s*Mag\.\s*Dr\.\s*Johannes\s*Gasberger\s*<\/strong>[\s\S]*?(?:FN\s*550277\s*g|<div|<\/p)/iu', $anna_brauer_sig, $body);
    }

    // 1. Convert WordPress emoji smiley images back to their text emoji (e.g. 🧪)
    // to prevent broken image boxes or cookie banner interception on smilies
    $body = preg_replace_callback('/<img\s+[^>]*class=["\'][^"\']*wp-smiley[^"\']*["\'][^>]*\/?>/i', function ($matches) {
        if (preg_match('/alt=["\']([^"\']+)["\']/i', $matches[0], $alt)) {
            return $alt[1];
        }
        return '';
    }, $body);

    // 2. Restore src from cookie banner consent attributes (e.g. Real Cookie Banner, Cookiebot)
    $body = preg_replace_callback('/<img([^>]+)>/i', function ($matches) {
        $tag = $matches[0];
        if (preg_match('/consent-original-src-_=["\']([^"\']+)["\']/i', $tag, $m)) {
            $orig_src = $m[1];
            $tag = preg_replace('/\s*consent-[a-z0-9_-]+=["\'][^"\']*["\']/i', '', $tag);
            if (preg_match('/\ssrc=["\'][^"\']*["\']/i', $tag)) {
                $tag = preg_replace('/\ssrc=["\'][^"\']*["\']/i', ' src="' . esc_url($orig_src) . '"', $tag);
            } else {
                $tag = preg_replace('/<img/i', '<img src="' . esc_url($orig_src) . '"', $tag);
            }
        }
        return $tag;
    }, $body);

    // 3. Normalize all <img> src attributes to guaranteed public HTTPS
    $body = preg_replace_callback('/<img(\s+[^>]*?)\bsrc=["\']([^"\']+)["\']([^>]*)>/i', function ($matches) use ($canonical_host) {
        $before = $matches[1];
        $src    = trim($matches[2]);
        $after  = $matches[3];

        // Skip data: URIs or cid: if already present
        if (strpos($src, 'data:') === 0 || strpos($src, 'cid:') === 0) {
            return $matches[0];
        }

        // Clean local development hosts
        $src = preg_replace('#^https?://(www\.)?x-sieben\.(test|local|dev)/#i', $canonical_host . '/', $src);
        $src = preg_replace('#^https?://localhost(:[0-9]+)?/#i', $canonical_host . '/', $src);

        // Strip www. from x-sieben.at to avoid 301 redirects in mail proxies
        $src = preg_replace('#^https?://www\.x-sieben\.at/#i', $canonical_host . '/', $src);

        // Force HTTPS for x-sieben.at
        $src = preg_replace('#^http://x-sieben\.at/#i', 'https://x-sieben.at/', $src);

        // Expand root-relative or theme-relative upload paths
        if (preg_match('#^/wp-content/(.+)$#i', $src, $m)) {
            $src = $canonical_host . '/wp-content/' . $m[1];
        } elseif (preg_match('#^wp-content/(.+)$#i', $src, $m)) {
            $src = $canonical_host . '/wp-content/' . $m[1];
        } elseif (preg_match('#^\.\./wp-content/(.+)$#i', $src, $m)) {
            $src = $canonical_host . '/wp-content/' . $m[1];
        }

        // Clean up accidental double slashes in paths
        $src = preg_replace('#([^:])//+#', '$1/', $src);

        // Ensure border="0" is present for legacy email clients
        if (stripos($before, 'border=') === false && stripos($after, 'border=') === false) {
            $after .= ' border="0"';
        }

        return '<img' . $before . ' src="' . esc_url($src) . '"' . $after . '>';
    }, $body);

    // 4. Normalize all <a href="..."> links to guaranteed absolute HTTPS
    $body = preg_replace_callback('/<a(\s+[^>]*?)\bhref=["\']([^"\']+)["\']([^>]*)>/i', function ($matches) use ($canonical_host) {
        $before = $matches[1];
        $href   = trim($matches[2]);
        $after  = $matches[3];

        if (preg_match('/^(mailto:|tel:|#|javascript:)/i', $href)) {
            return $matches[0];
        }


        // Fix damaged relative URLs from older database filters
        if (preg_match('#^http://(weiterbildung|experte|blog|datenschutzerklaerung|kontakt|agb)(/.*)?$#i', $href, $m)) {
            $href = $canonical_host . '/' . $m[1] . ($m[2] ?? '');
        }

        // Clean dev hosts & www
        $href = preg_replace('#^https?://(www\.)?x-sieben\.(test|local|dev)/#i', $canonical_host . '/', $href);
        $href = preg_replace('#^https?://(xsieben\.)?localhost(:[0-9]+)?/#i', $canonical_host . '/', $href);
        $href = preg_replace('#^https?://www\.x-sieben\.at/#i', $canonical_host . '/', $href);
        $href = preg_replace('#^http://x-sieben\.at/#i', 'https://x-sieben.at/', $href);

        // Expand root-relative paths
        if (strpos($href, '/') === 0 && strpos($href, '//') !== 0) {
            $href = $canonical_host . $href;
        }

        return '<a' . $before . ' href="' . esc_url($href) . '"' . $after . '>';
    }, $body);

    return $body;
}

/**
 * Erzeugt eine saubere, dezente Trennlinie für TCPDF ohne schwarze Block-Artefakte.
 *
 * @param string $color Hex-Farbcode (Standard: #cbd5e1 - dezentes Grau)
 * @param int $padding_top Abstand nach oben in pt
 * @param int $padding_bottom Abstand nach unten in pt
 * @return string HTML-Trennlinie
 */
function crm_pdf_divider(string $color = '#cbd5e1', int $padding_top = 12, int $padding_bottom = 16): string {
    $pt = max(4, intval($padding_top));
    $pb = max(4, intval($padding_bottom));
    return '<table cellspacing="0" cellpadding="0" border="0" style="width: 100%; border-collapse: collapse; border: none; margin: 0; padding: 0;">'
        . '<tr><td style="height: ' . $pt . 'pt; font-size: ' . $pt . 'pt; line-height: ' . $pt . 'pt; border: none; margin: 0; padding: 0;">&nbsp;</td></tr>'
        . '<tr><td style="border-top: 1px solid ' . esc_attr($color) . '; border-bottom: none; border-left: none; border-right: none; height: ' . $pb . 'pt; font-size: ' . $pb . 'pt; line-height: ' . $pb . 'pt; margin: 0; padding: 0;">&nbsp;</td></tr>'
        . '</table>';
}

// Ensure TCPDF temporary cache directory is within WordPress uploads or CRM cache to eliminate open_basedir & permission restrictions on live Linux servers
if (!defined('K_PATH_CACHE')) {
    $crm_tcpdf_cache = null;
    if (function_exists('wp_upload_dir')) {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['basedir'])) {
            $candidate = $upload_dir['basedir'] . '/tcpdf/';
            if (!file_exists($candidate)) {
                @wp_mkdir_p($candidate);
            }
            if (is_dir($candidate) && is_writable($candidate)) {
                $crm_tcpdf_cache = $candidate;
            }
        }
    }
    if (!$crm_tcpdf_cache) {
        $candidate = dirname(__DIR__) . '/assets/cache/';
        if (!file_exists($candidate)) {
            @mkdir($candidate, 0755, true);
        }
        if (is_dir($candidate) && is_writable($candidate)) {
            $crm_tcpdf_cache = $candidate;
        }
    }
    if (!$crm_tcpdf_cache) {
        $candidate = sys_get_temp_dir() . '/tcpdf/';
        if (!file_exists($candidate)) {
            @mkdir($candidate, 0755, true);
        }
        if (is_dir($candidate) && is_writable($candidate)) {
            $crm_tcpdf_cache = $candidate;
        } else {
            $crm_tcpdf_cache = sys_get_temp_dir() . '/';
        }
    }
    define('K_PATH_CACHE', trailingslashit(str_replace('\\', '/', $crm_tcpdf_cache)));
}

/**
 * Flacht binäre PNG-Bilddaten in-memory auf einen reinweißen (#FFFFFF) 24-Bit-RGB-Hintergrund ab.
 * 
 * Beseitigt den Alpha-Kanal vollständig und verhindert den ImageMagick-7-Fehler auf Linux-Servern.
 *
 * @param string $binary_data Rohe PNG-Bytes
 * @return string Abgeflachte PNG-Bytes (oder Originaldaten bei Fehler)
 */
function crm_flatten_png_binary_for_tcpdf(string $binary_data): string {
    if (strlen($binary_data) < 8 || substr($binary_data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return $binary_data;
    }

    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
        return $binary_data;
    }

    $src = @imagecreatefromstring($binary_data);
    if (!$src) {
        return $binary_data;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($src);
        return $binary_data;
    }

    $dst = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $w, $h, $white);
    imagealphablending($dst, true);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, false);

    ob_start();
    imagepng($dst, null, 6);
    $flat_data = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    return (!empty($flat_data)) ? $flat_data : $binary_data;
}

/**
 * Flacht ein transparentes PNG-Bild auf einen reinweißen (#FFFFFF) 24-Bit-RGB-Hintergrund ab.
 * 
 * Verhindert den berüchtigten ImageMagick-7-Fehler auf Linux-Servern (PHP 8.x), bei dem
 * TCPDFs ImagePngAlpha() mit separateImageChannel(39) schwarze Streifen, Balken oder
 * komplett schwarze Masken bei transparenten PNGs erzeugt.
 * Durch das Abflachen entfällt der Alpha-Kanal vollständig (Truecolor RGB, Farbmodus 2,
 * keine tRNS-Chunks), wodurch TCPDF die Datei nativ und fehlerfrei über GD/DeviceRGB einbettet.
 *
 * @param string $local_file Absoluter Pfad zum lokalen Bild
 * @return string Pfad zur abgeflachten PNG-Datei (oder Originalpfad bei Fehler/Nicht-PNG)
 */
function crm_flatten_png_for_tcpdf(string $local_file): string {
    if (!@file_exists($local_file) || !@is_readable($local_file)) {
        return $local_file;
    }

    // Nur PNG-Dateien prüfen und transformieren
    $is_png = preg_match('/\.png$/i', $local_file) || (function_exists('exif_imagetype') && @exif_imagetype($local_file) === IMAGETYPE_PNG);
    if (!$is_png) {
        return $local_file;
    }

    $cache_dir = dirname(__DIR__) . '/assets/cache/';
    if (!is_dir($cache_dir)) {
        @wp_mkdir_p($cache_dir);
    }

    $filename = basename($local_file);
    // 1. Prüfen, ob bereits eine vorkompilierte flat_<name> Datei im autarken Cache liegt
    $named_cache = $cache_dir . 'flat_' . $filename;
    if (@file_exists($named_cache) && @filesize($named_cache) > 0) {
        return $named_cache;
    }

    // 2. Prüfen, ob via MD5 gehasht im Cache vorhanden
    $file_hash = @md5_file($local_file) ?: md5($local_file);
    $hashed_cache = $cache_dir . 'flat_' . $file_hash . '.png';
    if (@file_exists($hashed_cache) && @filesize($hashed_cache) > 0) {
        return $hashed_cache;
    }

    // 3. Wenn noch nicht im Cache: Dynamisch mit GD abflachen
    if (!extension_loaded('gd') || !function_exists('imagecreatefrompng') || !function_exists('imagecreatetruecolor')) {
        return $local_file;
    }

    $src = @imagecreatefrompng($local_file);
    if (!$src) {
        $raw = @file_get_contents($local_file);
        if ($raw) {
            $src = @imagecreatefromstring($raw);
        }
    }

    if (!$src) {
        return $local_file;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($src);
        return $local_file;
    }

    $dst = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $w, $h, $white);
    imagealphablending($dst, true);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, false);

    // Versuche in den autarken Cache zu schreiben
    $target = (is_dir($cache_dir) && is_writable($cache_dir))
        ? $hashed_cache
        : (defined('K_PATH_CACHE') ? (K_PATH_CACHE . 'flat_' . $file_hash . '.png') : (sys_get_temp_dir() . '/flat_' . $file_hash . '.png'));

    $saved = @imagepng($dst, $target, 6);

    imagedestroy($src);
    imagedestroy($dst);

    if ($saved && @file_exists($target) && @filesize($target) > 0) {
        return $target;
    }

    return $local_file;
}

/**
 * Löst einen CRM-Asset-Pfad, Bild-URL oder Dateinamen zum optimalen,
 * ausfallsicheren Format für TCPDF auf.
 *
 * Sucht die Datei lokal im Dateisystem (Autarkes CRM-Assets-Verzeichnis, Theme,
 * Uploads, Media-Library). Flacht alle transparenten PNGs automatisch auf
 * einen weißen Hintergrund ab (beseitigt ImageMagick-7-Schwarze-Streifen-Bug auf Linux).
 * Liefert für maximale Kompatibilität mit TCPDF und zur Vermeidung von
 * Loopback-cURL-Blockaden / Firewall-Sperren auf Live-Servern einen nativen
 * TCPDF Data-Stream ('@' . base64_encode(binary)) zurück.
 *
 * @param string $path_or_filename Dateiname (z.B. 'xsieben_logo.png') oder vollständige URL/Pfad
 * @return string TCPDF Data-Stream (@base64), lokaler Dateipfad oder Original-URL
 */
function crm_resolve_asset_path(string $path_or_filename): string
{
    $input = trim($path_or_filename);
    if (empty($input)) {
        return '';
    }

    // Falls bereits als TCPDF Data-Stream übergeben: auf Alpha-Kanal prüfen & ggf. in-memory abflachen
    if ($input[0] === '@') {
        $bin = @base64_decode(substr($input, 1));
        if ($bin && strlen($bin) >= 8 && substr($bin, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $flat_bin = crm_flatten_png_binary_for_tcpdf($bin);
            return '@' . base64_encode($flat_bin);
        }
        return $input;
    }

    $local_file = null;
    $crm_dir    = dirname(__DIR__) . '/assets/';
    $cache_dir  = $crm_dir . 'cache/';
    $filename   = basename($input);

    // 1. Direktes file_exists (falls absoluter lokaler Pfad übergeben)
    if (@file_exists($input) && @is_file($input) && @is_readable($input)) {
        $local_file = $input;
    }

    // 2. Bereits als abgeflachtes Bild im Cache vorhanden?
    if (!$local_file && @file_exists($cache_dir . 'flat_' . $filename) && @is_file($cache_dir . 'flat_' . $filename)) {
        $local_file = $cache_dir . 'flat_' . $filename;
    }

    // 3. Im autarken CRM-Assets-Verzeichnis (relative to codebase - 100% autark!)
    if (!$local_file && @file_exists($crm_dir . $filename) && @is_file($crm_dir . $filename)) {
        $local_file = $crm_dir . $filename;
    }

    // 4. Im Theme-Verzeichnis (get_template_directory)
    if (!$local_file && function_exists('get_template_directory')) {
        $theme_asset = get_template_directory() . '/inc/core/crm/assets/' . $filename;
        if (@file_exists($theme_asset) && @is_file($theme_asset)) {
            $local_file = $theme_asset;
        }
    }

    // 5. Wenn es eine Media-Library URL ist: attachment_url_to_postid
    if (!$local_file && function_exists('attachment_url_to_postid') && preg_match('#^https?://#i', $input)) {
        $att_id = attachment_url_to_postid($input);
        if ($att_id && function_exists('get_attached_file')) {
            $att_path = get_attached_file($att_id);
            if ($att_path && @file_exists($att_path) && @is_file($att_path)) {
                $local_file = $att_path;
            }
        }
    }

    // 6. Wenn Pfad /uploads/ enthält: lokales Uploads-Verzeichnis auflösen
    if (!$local_file && function_exists('wp_upload_dir')) {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['basedir'])) {
            if (preg_match('#/uploads/(.+)$#i', $input, $m)) {
                $candidate = rtrim($upload_dir['basedir'], '/\\') . '/' . ltrim($m[1], '/\\');
                $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
                if (@file_exists($candidate) && @is_file($candidate)) {
                    $local_file = $candidate;
                }
            }
        }
    }

    // 7. Wenn Pfad /wp-content/ enthält: WP_CONTENT_DIR auflösen
    if (!$local_file && defined('WP_CONTENT_DIR')) {
        if (preg_match('#/wp-content/(.+)$#i', $input, $m)) {
            $candidate = rtrim(WP_CONTENT_DIR, '/\\') . '/' . ltrim($m[1], '/\\');
            $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
            if (@file_exists($candidate) && @is_file($candidate)) {
                $local_file = $candidate;
            }
        }
    }

    // 8. Wenn lokale Datei gefunden wurde: Bei PNG abflachen und als TCPDF Data-Stream (@base64) zurückgeben!
    if ($local_file && @is_readable($local_file)) {
        $final_file = crm_flatten_png_for_tcpdf($local_file);
        $content = @file_get_contents($final_file);
        if ($content !== false && strlen($content) > 0) {
            return '@' . base64_encode($content);
        }
        return str_replace('\\', '/', $final_file);
    }

    // 9. Fallback für Remote-URLs: Über WordPress HTTP API laden, bei PNG abflachen und als @base64 übergeben (SSRF-gehärtet)
    if (preg_match('#^https?://#i', $input) && function_exists('wp_remote_get')) {
        $validated_url = function_exists('wp_http_validate_url') ? wp_http_validate_url($input) : filter_var($input, FILTER_VALIDATE_URL);
        if ($validated_url) {
            $parsed = parse_url($validated_url);
            $host   = $parsed['host'] ?? '';
            // SSRF-Schutz: Private, Loopback- & Metadaten-IPs blockieren
            $is_private_ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP) !== false;
            $is_localhost  = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '169.254.169.254'], true);

            if (!$is_private_ip && !$is_localhost) {
                $sslverify = apply_filters('crm_sslverify', true);
                $response  = wp_remote_get($validated_url, [
                    'timeout'     => 8,
                    'sslverify'   => $sslverify,
                    'redirection' => 2,
                ]);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $content_type = wp_remote_retrieve_header($response, 'content-type');
                    // Nur erlaubte Bildformate verarbeiten
                    if (empty($content_type) || stripos($content_type, 'image/') !== false) {
                        $body = wp_remote_retrieve_body($response);
                        if (!empty($body)) {
                            $body = crm_flatten_png_binary_for_tcpdf($body);
                            return '@' . base64_encode($body);
                        }
                    }
                }
            }
        }
    }

    // 10. Letzter Ausfallschutz für Logos: Default xsieben_logo.png (abgeflacht) als @base64 laden
    $default_logo_file = $crm_dir . 'xsieben_logo.png';
    if (@file_exists($default_logo_file) && @is_readable($default_logo_file)) {
        $flat_logo = crm_flatten_png_for_tcpdf($default_logo_file);
        $content = @file_get_contents($flat_logo);
        if ($content !== false && strlen($content) > 0) {
            return '@' . base64_encode($content);
        }
    }

    // Letzter Fallback: URL
    if (function_exists('get_template_directory_uri')) {
        return get_template_directory_uri() . '/inc/core/crm/assets/' . $filename;
    }

    return $input;
}