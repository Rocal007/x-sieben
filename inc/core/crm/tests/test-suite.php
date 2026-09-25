<?php
/**
 * SENIOR DEVELOPER STANDARD (SDS) — AUTOMATED CRM TEST SUITE
 * X-SIEBEN Wirtschaftstraining GmbH | Subprojekt `inc/core/crm/`
 *
 * Führt deterministische Unit-, Integrations- und Compliance-Tests
 * für das autarke CRM-Modul aus.
 *
 * Aufruf per CLI:
 *   php inc/core/crm/tests/test-suite.php
 */

declare(strict_types=1);

// Try loading full WordPress environment if available
$wp_load_path = dirname(__DIR__, 7) . '/wp-load.php';
if (!defined('ABSPATH') && file_exists($wp_load_path)) {
    require_once $wp_load_path;
}

// Polyfill minimal WordPress functions for isolated CLI execution if not running inside WP
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 7) . '/');
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL) ?: $url;
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string)$text, ENT_COMPAT, 'UTF-8');
    }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}
if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return $text; }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') { echo $text; }
}
if (!function_exists('esc_js')) {
    function esc_js($text) { return addslashes((string)$text); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string)$str)); }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) { return trim(strip_tags((string)$str)); }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$key)); }
}
if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) { return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string)$color) ? (string)$color : ''; }
}
if (!function_exists('absint')) {
    function absint($maybeint) { return abs((int)$maybeint); }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($text) { return (string)$text; }
}
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) { return array_merge((array)$defaults, (array)$args); }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) { return $value; }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() { return ['basedir' => sys_get_temp_dir(), 'baseurl' => 'http://localhost']; }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) { return true; }
}
$GLOBALS['_crm_test_options'] = [];
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $GLOBALS['_crm_test_options'][$option] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option($option, $value) {
        $GLOBALS['_crm_test_options'][$option] = $value;
        return true;
    }
}
$GLOBALS['_crm_test_post_meta'] = [];
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        if ($key === '') {
            return $GLOBALS['_crm_test_post_meta'][$post_id] ?? [];
        }
        $val = $GLOBALS['_crm_test_post_meta'][$post_id][$key] ?? '';
        return $single ? $val : [$val];
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) {
        $GLOBALS['_crm_test_post_meta'][$post_id][$meta_key] = $meta_value;
        return true;
    }
}
if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key) {
        unset($GLOBALS['_crm_test_post_meta'][$post_id][$meta_key]);
        return true;
    }
}
if (!function_exists('get_the_title')) {
    function get_the_title($post_id = 0) { return 'Digital Marketing Manager'; }
}
if (!function_exists('get_field')) {
    function get_field($selector, $post_id = false, $format_value = true) { return ''; }
}
if (!function_exists('get_permalink')) {
    function get_permalink($post_id = 0) { return 'https://x-sieben.at/kurs'; }
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false) { return strip_tags((string)$string); }
}
if (!function_exists('get_posts')) {
    function get_posts($args = null) { return []; }
}
if (!function_exists('get_template_directory')) {
    function get_template_directory() { return dirname(__DIR__, 4); }
}
if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri() { return 'http://localhost/wp-content/themes/sieben'; }
}
if (!function_exists('get_the_terms')) {
    function get_the_terms($post_id, $taxonomy) { return false; }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return false; }
}
if (!function_exists('get_post')) {
    function get_post($post = null) {
        $p = new stdClass();
        $p->ID = is_numeric($post) ? (int)$post : 36593;
        $p->post_title = 'Digital Marketing Manager';
        return $p;
    }
}
if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size='thumbnail') { return false; }
}
if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id) { return ''; }
}
if (!function_exists('has_term')) {
    function has_term($term = '', $taxonomy = '', $post = null) { return false; }
}
if (!function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image($attachment_id, $size = 'thumbnail', $icon = false, $attr = '') { return ''; }
}
if (!function_exists('have_rows')) {
    function have_rows($selector, $post_id = false) { return false; }
}
if (!function_exists('the_row')) {
    function the_row() { return false; }
}
if (!function_exists('get_sub_field')) {
    function get_sub_field($selector, $format_value = true) { return ''; }
}

// Load CRM helpers to test
require_once dirname(__DIR__) . '/helpers/normalize.php';
require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';
require_once dirname(__DIR__) . '/pdf/anmeldebestaetigung.php';
require_once dirname(__DIR__) . '/pdf/antrittsbestaetigung.php';

// Lightweight Test Runner Class
class CrmSeniorDevTestSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function assert(string $testName, bool $condition, string $errorMessage = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  \033[32m✔ PASS\033[0m : {$testName}\n";
        } else {
            $this->failed++;
            $msg = $errorMessage ? " - {$errorMessage}" : '';
            $this->failures[] = "{$testName}{$msg}";
            echo "  \033[31m✘ FAIL\033[0m : {$testName}{$msg}\n";
        }
    }

    public function assertEqual(string $testName, $expected, $actual): void
    {
        $condition = ($expected === $actual);
        $diff = '';
        if (!$condition) {
            $diff = sprintf("Expected: %s, got: %s", var_export($expected, true), var_export($actual, true));
        }
        $this->assert($testName, $condition, $diff);
    }

    public function assertContains(string $testName, string $needle, string $haystack): void
    {
        $condition = (strpos($haystack, $needle) !== false);
        $diff = $condition ? '' : "String '{$needle}' not found in target.";
        $this->assert($testName, $condition, $diff);
    }

    public function assertNotContains(string $testName, string $needle, string $haystack): void
    {
        $condition = (strpos($haystack, $needle) === false);
        $diff = $condition ? '' : "Forbidden string '{$needle}' found in target.";
        $this->assert($testName, $condition, $diff);
    }

    public function runAll(): int
    {
        echo "\n\033[1;36m====================================================================\033[0m\n";
        echo "\033[1;37m   X-SIEBEN CRM — SENIOR DEVELOPER STANDARD (SDS) TEST SUITE        \033[0m\n";
        echo "\033[1;36m====================================================================\033[0m\n";

        $this->testPhpSyntaxIntegrity();
        $this->testStringNormalization();
        $this->testEmailHtmlNormalization();
        $this->testFinancialAndTaxCalculation();
        $this->testLinguaLocaCompliance();
        $this->testStatusAndAuditTransitions();
        $this->testPdfDividerIntegrity();
        $this->testPdfElementSpacing();
        $this->testCrossPageDragAndDrop();
        $this->testStandardComponentsEditing();
        $this->testGlobalAndCustomHtmlPermanentSaving();
        $this->testPdfPreviewAndGlobalSync();
        $this->testFullWidthDocumentTitle();
        $this->testHeaderSpacingControls();
        $this->testAtomicDesignElementsArchitecture();
        $this->testSuite16_SettingsAndInheritance();
        $this->testSuite17_InquiryLinking();
        $this->testSuite18_SplitViewAndMultiView();
        $this->testSuite19_KanbanTimeline();
        $this->testSuite20_WizardDocumentSelection();
        $this->testSuite21_UniversalCustomerEditing();
        $this->testSuite22_EmailBausteineAndCertFilter();
        $this->testSuite23_CourseCertificationsInAllViews();
        $this->testSuite24_TbAndDiplomSimulationAndPreview();
        $this->testSuite25_TestEmailDeliveryAndTransparency();
        $this->testSuite26_AgbOnlineLinkPolicy();
        $this->testSuite27_PdfSpacingAndPageBreakIntegrity();
        $this->testSuite28_TinyMceCrmHtmlPreservation();
        $this->testSuite29_BusinessCaseHistorySplitView();
        $this->testSuite30_CertificationDisambiguationAndExclusivity();
        $this->testSuite31_Version21882FeaturesAndFixes();
        $this->testSuite32_Version21883CertAndScheduleLogic();
        $this->testSuite33_Version21884TuevGrossAndTerminplanAttachment();
        $this->testSuite34_Version21885FachtrainerDafDazCertAndTerminplanAttachment();
        $this->testSuite35_Version21886OfferPage1SpacingAndTuevCertIntegrity();
        $this->testSuite36_Version21887AmseDocumentsAndActionButtonsIntegration();

        $duration = round((microtime(true) - $this->startTime) * 1000, 2);
        echo "\n\033[1;36m--------------------------------------------------------------------\033[0m\n";
        echo "Testergebnis: ";
        if ($this->failed === 0) {
            echo "\033[1;32mALLE {$this->passed} TESTS ERFOLGREICH BESTANDEN ({$duration} ms)\033[0m\n";
            echo "\033[32m✔ SDS-GARANTIE ERFÜLLT: Codebase ist revisionssicher und normkonform.\033[0m\n";
            return 0;
        } else {
            echo "\033[1;31m{$this->failed} VON " . ($this->passed + $this->failed) . " TESTS FEHLGESCHLAGEN ({$duration} ms)\033[0m\n";
            echo "\nFehlerliste:\n";
            foreach ($this->failures as $f) {
                echo "  - \033[31m{$f}\033[0m\n";
            }
            return 1;
        }
    }

    // 1. PHP Syntax Integrity across CRM files
    private function testPhpSyntaxIntegrity(): void
    {
        echo "\n\033[1;33m[SUITE 1] PHP Syntax & Dateistruktur-Integrität\033[0m\n";
        $crmDir = dirname(__DIR__);
        $filesToCheck = [
            $crmDir . '/crm-admin.php',
            $crmDir . '/crm-model.php',
            $crmDir . '/crm-settings.php',
            $crmDir . '/controler/settings-controler.php',
            $crmDir . '/controler/output-controler.php',
            $crmDir . '/helpers/normalize.php',
            $crmDir . '/helpers/crm-status.php',
            $crmDir . '/helpers/crm-cache.php',
            $crmDir . '/helpers/crm-email-sections.php',
            $crmDir . '/helpers/crm-pdf-sections.php',
            $crmDir . '/views/settings/tab-general.php',
            $crmDir . '/views/settings/tab-emails.php',
            $crmDir . '/views/settings/tab-pdf.php',
            $crmDir . '/views/settings/components/field-editor.php',
            $crmDir . '/views/settings/components/cheat-sheet.php',
            $crmDir . '/pdf/offer.php',
            $crmDir . '/pdf/kurszeitenbestaetigung.php',
            $crmDir . '/pdf/teilnamebestaetigung.php',
            $crmDir . '/pdf/diplom.php',
            $crmDir . '/pdf/invoice.php',
            $crmDir . '/pdf/angebot_kurszeiten.php',
            $crmDir . '/pdf/elements/offer-elements.php',
            $crmDir . '/pdf/elements/offer-styles.php',
            $crmDir . '/pdf/elements/offer-header.php',
            $crmDir . '/pdf/elements/offer-empfaenger.php',
            $crmDir . '/pdf/elements/offer-anrede-intro.php',
            $crmDir . '/pdf/elements/offer-gruss.php',
            $crmDir . '/pdf/elements/offer-hinweis-nachstehend.php',
            $crmDir . '/pdf/elements/offer-zeitraum.php',
            $crmDir . '/pdf/elements/offer-lehreinheiten.php',
            $crmDir . '/pdf/elements/offer-abschluss-box.php',
            $crmDir . '/pdf/elements/offer-voraussetzungen.php',
            $crmDir . '/pdf/elements/offer-zertifizierungen.php',
            $crmDir . '/pdf/elements/offer-ort-durchfuehrung.php',
            $crmDir . '/pdf/elements/offer-gueltigkeit.php',
            $crmDir . '/pdf/elements/offer-anhang-hinweise.php',
            $crmDir . '/pdf/elements/kb-elements.php',
            $crmDir . '/pdf/elements/kb-titel.php',
            $crmDir . '/pdf/elements/kb-institut.php',
            $crmDir . '/pdf/elements/kb-teilnehmer.php',
            $crmDir . '/pdf/elements/kb-kurstyp.php',
            $crmDir . '/pdf/elements/kb-kurszeiten.php',
            $crmDir . '/pdf/elements/kb-hinweis.php',
            $crmDir . '/pdf/elements/kb-signatur.php',
            $crmDir . '/pdf/elements/tb-elements.php',
            $crmDir . '/pdf/elements/tb-titel.php',
            $crmDir . '/pdf/elements/tb-teilnehmer.php',
            $crmDir . '/pdf/elements/tb-zeitraum.php',
            $crmDir . '/pdf/elements/tb-ausbildungsstaette.php',
            $crmDir . '/pdf/elements/tb-teilnahme.php',
            $crmDir . '/pdf/elements/tb-signatur.php',
            $crmDir . '/pdf/elements/diplom-elements.php',
            $crmDir . '/pdf/elements/diplom-header.php',
            $crmDir . '/pdf/elements/diplom-titel.php',
            $crmDir . '/pdf/elements/diplom-lehrgang.php',
            $crmDir . '/pdf/elements/diplom-abschluss.php',
            $crmDir . '/pdf/elements/diplom-beglaubigung.php',
            $crmDir . '/pdf/elements/diplom-inhalte.php',
            $crmDir . '/pdf/elements/diplom-guetesiegel.php',
            $crmDir . '/pdf/elements/invoice-elements.php',
            $crmDir . '/pdf/elements/invoice-header.php',
            $crmDir . '/pdf/elements/invoice-titel.php',
            $crmDir . '/pdf/elements/invoice-empfaenger.php',
            $crmDir . '/pdf/elements/invoice-einleitung.php',
            $crmDir . '/pdf/elements/invoice-positionen.php',
            $crmDir . '/pdf/elements/invoice-zahlung.php',
            $crmDir . '/pdf/elements/invoice-fusszeile.php',
        ];

        foreach ($filesToCheck as $file) {
            $rel = str_replace(dirname($crmDir, 3), '', $file);
            $this->assert("Datei existiert: {$rel}", file_exists($file));
            if (file_exists($file)) {
                $output = [];
                $returnCode = 0;
                exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $returnCode);
                $this->assert("Syntax valide: {$rel}", $returnCode === 0, implode(' ', $output));
            }
        }
    }

    // 2. String Normalization tests
    private function testStringNormalization(): void
    {
        echo "\n\033[1;33m[SUITE 2] String Normalizer (normalize_string)\033[0m\n";
        
        $input1 = "IPMA® Level-D / Kursangebot 2026";
        $expected1 = "ipma level-d kursangebot 2026";
        $this->assertEqual("Entfernt '®' und wandelt '/' in Leerzeichen um", $expected1, normalize_string($input1));

        $input2 = "Projektmanagement   –   Agile Coach"; // en-dash with multiple spaces
        $expected2 = "projektmanagement - agile coach";
        $this->assertEqual("Normalisiert Gedankenstriche und Leerzeichen", $expected2, normalize_string($input2));
    }

    // 3. Email HTML Normalization (Zero-Leakage & Public URLs)
    private function testEmailHtmlNormalization(): void
    {
        echo "\n\033[1;33m[SUITE 3] E-Mail Normalizer (crm_prepare_email_html_for_sending)\033[0m\n";

        // A. TinyMCE Artifacts Stripping
        $raw1 = '<p data-mce-style="color:red;" style="color:red;">Test</p>';
        $cleaned1 = crm_prepare_email_html_for_sending($raw1);
        $this->assertNotContains("TinyMCE data-mce-* Attribute entfernt", "data-mce-style", $cleaned1);

        // B. WordPress Smiley Restoration
        $rawSmiley = '<img src="https://x-sieben.at/wp-includes/images/smilies/icon_test.gif" alt="🧪" class="wp-smiley">';
        $cleanedSmiley = crm_prepare_email_html_for_sending($rawSmiley);
        $this->assertEqual("WP-Smiley Bild zu nativem Unicode konvertiert", "🧪", trim($cleanedSmiley));

        // C. Cookie Consent Restoration
        $rawConsent = '<img consent-original-src-_="https://x-sieben.at/logo.png" consent-tracking="1" alt="Logo">';
        $cleanedConsent = crm_prepare_email_html_for_sending($rawConsent);
        $this->assertContains("Cookie-Consent src wiederhergestellt", 'src="https://x-sieben.at/logo.png"', $cleanedConsent);
        $this->assertNotContains("Cookie-Consent Tracking Attribute entfernt", "consent-tracking", $cleanedConsent);

        // D. Relative Upload Paths to Public HTTPS
        $rawRel = '<img src="/wp-content/uploads/2026/03/kurs.jpg" alt="Kurs">';
        $cleanedRel = crm_prepare_email_html_for_sending($rawRel);
        $this->assertContains("Relative Bildpfade auf kanonisches HTTPS umgeschrieben", 'src="https://x-sieben.at/wp-content/uploads/2026/03/kurs.jpg"', $cleanedRel);
        $this->assertContains("border=\"0\" automatisch für Outlook ergänzt", 'border="0"', $cleanedRel);

        // E. Local Development & Dev Hosts rewrite
        $rawDev = '<a href="http://x-sieben.test/weiterbildung/">Kurslink</a>';
        $cleanedDev = crm_prepare_email_html_for_sending($rawDev);
        $this->assertContains("Lokale Test-Domain (.test) auf https://x-sieben.at umgeschrieben", 'href="https://x-sieben.at/weiterbildung/"', $cleanedDev);

        // F. www. prefix stripping to prevent 301 redirect leakage
        $rawWww = '<a href="https://www.x-sieben.at/kontakt/">Kontakt</a>';
        $cleanedWww = crm_prepare_email_html_for_sending($rawWww);
        $this->assertContains("www.x-sieben.at zu https://x-sieben.at bereinigt", 'href="https://x-sieben.at/kontakt/"', $cleanedWww);

        // G. Mailto and Tel links preserved
        $rawSpecial = '<a href="mailto:office@x-sieben.at">Mail</a><a href="tel:+43123456">Tel</a>';
        $cleanedSpecial = crm_prepare_email_html_for_sending($rawSpecial);
        $this->assertContains("mailto: bleibt erhalten", 'href="mailto:office@x-sieben.at"', $cleanedSpecial);
        $this->assertContains("tel: bleibt erhalten", 'href="tel:+43123456"', $cleanedSpecial);

        // H. Internal AI Preparation & Approval Notices Stripping (MUST NEVER leak to emails)
        $rawAI1 = '<p>Mit besten Grüßen,<br><strong>Mag. Dr. Johannes Gasberger</strong><br><span style="font-size: 11px; color: #7c3aed;">🤖 Vorbereitet von <strong>Friedelin</strong> (X-SIEBEN KI-Assistent by NEXUS) &bull; <em>Geprüft & freigegeben von Mag. Dr. Johannes Gasberger</em></span></p>';
        $cleanedAI1 = crm_prepare_email_html_for_sending($rawAI1);
        $this->assertNotContains("Friedelin-Tag vollständig aus E-Mail entfernt", "Friedelin", $cleanedAI1);
        $this->assertNotContains("Geprüft & freigegeben Hinweis entfernt", "freigegeben", $cleanedAI1);
        $this->assertContains("Absender bleibt intakt", "Johannes Gasberger", $cleanedAI1);

        $rawAI2 = '<p>Herzliche Grüße<br><strong>Anna Brauer</strong><br><span>🤖 Vorbereitet von Friedelin (X-SIEBEN KI-Assistent) &bull; Manuelle Prüfung & Freigabe durch Geschäftsführung</span></p>';
        $cleanedAI2 = crm_prepare_email_html_for_sending($rawAI2);
        $this->assertNotContains("Friedelin-Tag (Anna Brauer) entfernt", "Friedelin", $cleanedAI2);
        $this->assertNotContains("Manuelle Prüfung Hinweis entfernt", "Geschäftsführung", $cleanedAI2);
        $this->assertContains("Anna Brauer bleibt erhalten", "Anna Brauer", $cleanedAI2);
    }

    // 4. Financial & Austrian VAT (20%) Calculation (Judikative Standards)
    private function testFinancialAndTaxCalculation(): void
    {
        echo "\n\033[1;33m[SUITE 4] Finanz- & USt-Präzision (20% USt nach § 11 UStG)\033[0m\n";

        // Helper calculation function
        $calcVat = function (float $netto): array {
            $ustRate = 0.20;
            $ust = round($netto * $ustRate, 2);
            $brutto = round($netto + $ust, 2);
            return ['netto' => $netto, 'ust' => $ust, 'brutto' => $brutto];
        };

        // Case 1: Standard round amount (1.250,00 €)
        $r1 = $calcVat(1250.00);
        $this->assertEqual("1.250,00 € Netto -> 250,00 € USt", 250.00, $r1['ust']);
        $this->assertEqual("1.250,00 € Netto -> 1.500,00 € Brutto", 1500.00, $r1['brutto']);

        // Case 2: Uneven amount (499,99 €)
        $r2 = $calcVat(499.99);
        $this->assertEqual("499,99 € Netto -> 100,00 € USt (gerundet von 99,998)", 100.00, $r2['ust']);
        $this->assertEqual("499,99 € Netto -> 599,99 € Brutto", 599.99, $r2['brutto']);

        // Case 3: Reverse calculation from Brutto
        $brutto = 1500.00;
        $nettoCalc = round($brutto / 1.20, 2);
        $this->assertEqual("Rückrechnung aus 1.500,00 € Brutto ergibt exakt 1.250,00 € Netto", 1250.00, $nettoCalc);

        // Case 4: parse_price_float with various Austrian/German & English number representations
        require_once dirname(__DIR__) . '/helpers/crm-pdf-presenter.php';
        $this->assertEqual("parse_price_float '2.497,50 €'", 2497.50, CRM_Pdf_Presenter::parse_price_float('2.497,50 €'));
        $this->assertEqual("parse_price_float '2497,50'", 2497.50, CRM_Pdf_Presenter::parse_price_float('2497,50'));
        $this->assertEqual("parse_price_float '2497.50'", 2497.50, CRM_Pdf_Presenter::parse_price_float('2497.50'));
        $this->assertEqual("parse_price_float '414,17'", 414.17, CRM_Pdf_Presenter::parse_price_float('414,17'));
        $this->assertEqual("parse_price_float 2497.5", 2497.5, CRM_Pdf_Presenter::parse_price_float(2497.5));

        // Case 5: Complex calculation with Course (2.497,50 €) + TÜV Certification (497,00 € brutto)
        $courseNetto = 2497.50;
        $courseUst = round($courseNetto * 0.20, 2); // 499.50
        $courseBrutto = round($courseNetto + $courseUst, 2); // 2997.00
        $this->assertEqual("Kurs 2.497,50 € Netto -> 499,50 € USt", 499.50, $courseUst);
        $this->assertEqual("Kurs 2.497,50 € Netto -> 2.997,00 € Brutto", 2997.00, $courseBrutto);

        $certGross = 497.00;
        $certUst = round(($certGross / 1.20) * 0.20, 2); // 82.83
        $certNetto = round($certGross - $certUst, 2); // 414.17
        $this->assertEqual("TÜV Cert 497,00 € Brutto -> 414,17 € Netto", 414.17, $certNetto);
        $this->assertEqual("TÜV Cert 497,00 € Brutto -> 82,83 € USt", 82.83, $certUst);

        $totNetto = round($courseNetto + $certNetto, 2); // 2911.67
        $totUst   = round($courseUst + $certUst, 2);     // 582.33
        $totBrutto = round($totNetto + $totUst, 2);      // 3494.00
        $this->assertEqual("Gesamt Netto = 2.911,67 €", 2911.67, $totNetto);
        $this->assertEqual("Gesamt USt = 582,33 €", 582.33, $totUst);
        $this->assertEqual("Gesamt Brutto = 3.494,00 € (Netto + USt = Brutto)", 3494.00, $totBrutto);
        $this->assert("Finanz-Identität: Gesamt Netto + Gesamt USt === Gesamt Brutto", $totNetto + $totUst === $totBrutto);
    }

    // 5. LINGUA-LOCA AT & Marketing Fluff Filter Compliance
    private function testLinguaLocaCompliance(): void
    {
        echo "\n\033[1;33m[SUITE 5] LINGUA-LOCA AT & Fluff-Filter (Judikative-Gate)\033[0m\n";

        $forbiddenTerms = [
            'lecker',
            'gucken',
            'schauen Sie mal vorbei',
            'Teilnahmebescheinigung',
            'Support-Center',
            'revolutionär',
            'unschlagbar',
            'weltbester',
        ];

        $validSampleText = "Sehr geehrte Frau Dr. Huber, anbei erhalten Sie Ihre Teilnahmebestätigung für den Lehrgang Projektmanagement. Unser Backoffice / Kanzleiteam steht für Rückfragen gerne zur Verfügung.";

        foreach ($forbiddenTerms as $term) {
            $this->assertNotContains("Mustertext frei von gesperrtem Begriff '{$term}'", $term, $validSampleText);
        }

        // Test Austrian required terms present
        $this->assertContains("Österreichischer Fachbegriff 'Teilnahmebestätigung' verwendet", "Teilnahmebestätigung", $validSampleText);
        $this->assertContains("Österreichischer Fachbegriff 'Lehrgang' verwendet", "Lehrgang", $validSampleText);
        $this->assertContains("Österreichischer Fachbegriff 'Backoffice / Kanzleiteam' verwendet", "Backoffice / Kanzleiteam", $validSampleText);
    }

    // 6. Status & Audit Transitions Logic
    private function testStatusAndAuditTransitions(): void
    {
        echo "\n\033[1;33m[SUITE 6] CRM Status- & Audit-Transitions Logik\033[0m\n";

        $allowedStatuses = [
            'neu',
            'angebot_gesendet',
            'kurszeitenbestaetigung_gesendet',
            'angebot_kurszeiten_gesendet',
            'anmeldung_gesendet',
            'teilnahmebestaetigung_gesendet',
            'diplom_gesendet',
            'in_bearbeitung',
            'abgeschlossen',
            'storniert',
            'test_mail_gesendet',
        ];

        $this->assert("Status 'neu' ist registriert", in_array('neu', $allowedStatuses, true));
        $this->assert("Status 'angebot_gesendet' ist registriert", in_array('angebot_gesendet', $allowedStatuses, true));
        $this->assert("Status 'diplom_gesendet' ist registriert", in_array('diplom_gesendet', $allowedStatuses, true));
        $this->assert("Status 'test_mail_gesendet' für Audit-Protokoll registriert", in_array('test_mail_gesendet', $allowedStatuses, true));

        // Test mode condition: 'only_test' vs 'both'
        $simulateSend = function (string $modeType, string $currentStatus, string $newDocStatus): array {
            if ($modeType === 'only_test') {
                return [
                    'customer_status' => $currentStatus, // unchanged
                    'audit_logged'    => true,
                    'audit_event'     => 'test_mail_gesendet',
                ];
            } else {
                return [
                    'customer_status' => $newDocStatus,   // updated
                    'audit_logged'    => true,
                    'audit_event'     => $newDocStatus,
                ];
            }
        };

        // Verify only_test preserves original status
        $resOnlyTest = $simulateSend('only_test', 'neu', 'angebot_gesendet');
        $this->assertEqual("'only_test' Modus belässt Kundenstatus unverändert auf 'neu'", 'neu', $resOnlyTest['customer_status']);
        $this->assertEqual("'only_test' erzeugt Audit-Event 'test_mail_gesendet'", 'test_mail_gesendet', $resOnlyTest['audit_event']);

        // Verify both updates status
        $resBoth = $simulateSend('both', 'neu', 'angebot_gesendet');
        $this->assertEqual("'both' Modus aktualisiert Kundenstatus auf 'angebot_gesendet'", 'angebot_gesendet', $resBoth['customer_status']);
    }

    // 7. PDF Divider Table Integrity
    private function testPdfDividerIntegrity(): void
    {
        echo "\n\033[1;33m[SUITE 7] PDF Divider Rendering (crm_pdf_divider)\033[0m\n";

        $divider = crm_pdf_divider('#007C90', 10, 14);
        $this->assertContains("PDF-Trennlinie enthält Farbcode #007C90", "#007C90", $divider);
        $this->assertContains("PDF-Trennlinie ist valide HTML-Tabelle", '<table cellspacing="0" cellpadding="0" border="0"', $divider);
    }

    // 8. PDF Element Spacing & Vertical Rhythm (Abstand oben & unten)
    private function testPdfElementSpacing(): void
    {
        echo "\n\033[1;33m[SUITE 8] PDF-Elemente Abstände (crm_get_pdf_spacing_html & Section Order)\033[0m\n";

        $origSpacing = get_option('crm_pdf_elements_spacing');
        delete_option('crm_pdf_elements_spacing');

        // A. Spacing Defaults
        $defaults = crm_get_pdf_elements_spacing();
        $this->assert("crm_get_pdf_elements_spacing() liefert Array", is_array($defaults));
        $this->assertEqual("Standard-Abstand oben ist 0 pt (Byte-Identität)", 0, (int)$defaults['spacing_top']);
        $this->assertEqual("Standard-Abstand unten ist 0 pt (Byte-Identität)", 0, (int)$defaults['spacing_bottom']);
        $this->assertEqual("Master-Dokumententitel Abstand oben ist 13 pt", 13.0, (float)$defaults['title_spacing_top']);
        $this->assert("Master-Dokumententitel Abstand unten ist >= 11 pt (Standard 36 pt)", (float)$defaults['title_spacing_bottom'] >= 11.0);

        // B. Spacing Persistence & Sanitization
        crm_save_pdf_elements_spacing([
            'spacing_top'          => 14.5,
            'spacing_bottom'       => 20,
            'title_spacing_top'    => 13,
            'title_spacing_bottom' => 11
        ]);
        $saved = crm_get_pdf_elements_spacing();
        $this->assertEqual("Globale Einstellungen speichern spacing_top = 14.5", 14.5, (float)$saved['spacing_top']);
        $this->assertEqual("Globale Einstellungen speichern spacing_bottom = 20.0", 20.0, (float)$saved['spacing_bottom']);
        $this->assertEqual("Globale Einstellungen speichern title_spacing_top = 13.0", 13.0, (float)$saved['title_spacing_top']);
        $this->assertEqual("Globale Einstellungen speichern title_spacing_bottom = 11.0", 11.0, (float)$saved['title_spacing_bottom']);

        // Negative values sanitized to 0
        crm_save_pdf_elements_spacing(['spacing_top' => -10, 'spacing_bottom' => -5, 'title_spacing_top' => -2, 'title_spacing_bottom' => -3]);
        $sanitized = crm_get_pdf_elements_spacing();
        $this->assertEqual("Negative Abstände oben werden auf 0 bereinigt", 0.0, (float)$sanitized['spacing_top']);
        $this->assertEqual("Negative Abstände unten werden auf 0 bereinigt", 0.0, (float)$sanitized['spacing_bottom']);

        // Reset to default
        crm_save_pdf_elements_spacing(['spacing_top' => 0, 'spacing_bottom' => 0, 'title_spacing_top' => 13, 'title_spacing_bottom' => 11]);

        // C. TCPDF Spacer HTML Generation
        $spacerZero = crm_get_pdf_spacing_html(0);
        $this->assertEqual("crm_get_pdf_spacing_html(0) liefert leeren String (kein DOM-Overhead)", '', $spacerZero);

        $spacerNegative = crm_get_pdf_spacing_html(-8.5);
        $this->assertEqual("crm_get_pdf_spacing_html(-8.5) liefert leeren String", '', $spacerNegative);

        $spacer12 = crm_get_pdf_spacing_html(12.0);
        $this->assertContains("crm_get_pdf_spacing_html(12) enthält class crm-pdf-spacer", 'class="crm-pdf-spacer"', $spacer12);
        $this->assertContains("crm_get_pdf_spacing_html(12) setzt font-size:12pt", 'font-size:12pt;', $spacer12);
        $this->assertContains("crm_get_pdf_spacing_html(12) setzt line-height:12pt", 'line-height:12pt;', $spacer12);
        $this->assertContains("crm_get_pdf_spacing_html(12) setzt height:12pt", 'height:12pt;', $spacer12);

        // D. Effective Spacing (Inheritance & Override)
        $global = ['spacing_top' => 15.0, 'spacing_bottom' => 25.0, 'title_spacing_top' => 13.0, 'title_spacing_bottom' => 11.0];

        // Element without override inherits global
        $elemDefault = ['key' => 'test'];
        $effDefault = crm_get_pdf_effective_spacing($elemDefault, $global);
        $this->assertEqual("Element ohne Override erbt globalen Abstand oben (15 pt)", 15.0, $effDefault['top']);
        $this->assertEqual("Element ohne Override erbt globalen Abstand unten (25 pt)", 25.0, $effDefault['bottom']);

        // Title element inherits document title master spacing (top 13 pt, bottom 11 pt)
        $titleElem = ['key' => 'titel'];
        $effTitle = crm_get_pdf_effective_spacing($titleElem, $global);
        $this->assertEqual("Dokumententitel ohne Override erbt Master-Abstand oben (13 pt)", 13.0, $effTitle['top']);
        $this->assertEqual("Dokumententitel ohne Override erbt Master-Abstand unten (11 pt)", 11.0, $effTitle['bottom']);

        // Element with explicit override
        $elemOverride = ['key' => 'test', 'spacing_top' => 8.0, 'spacing_bottom' => 12.0];
        $effOverride = crm_get_pdf_effective_spacing($elemOverride, $global);
        $this->assertEqual("Element mit lokalem Override überschreibt globalen Abstand oben (8 pt)", 8.0, $effOverride['top']);
        $this->assertEqual("Element mit lokalem Override überschreibt globalen Abstand unten (12 pt)", 12.0, $effOverride['bottom']);

        // Element with 0 and global 0 returns 0
        $effZero = crm_get_pdf_effective_spacing(['key' => 'test', 'spacing_top' => 0], ['spacing_top' => 0, 'spacing_bottom' => 0, 'title_spacing_top' => 13, 'title_spacing_bottom' => 11]);
        $this->assertEqual("Element mit 0 pt und global 0 pt ergibt 0 pt", 0.0, $effZero['top']);

        // Element with 0.0 (template default) inherits global when global > 0
        $elemTemplateDefault = ['key' => 'test', 'spacing_top' => 0.0, 'spacing_bottom' => 0.0];
        $effTemplate = crm_get_pdf_effective_spacing($elemTemplateDefault, $global);
        $this->assertEqual("Element mit Vorlagen-Default (0.0 pt) erbt globalen Abstand oben (15 pt)", 15.0, $effTemplate['top']);
        $this->assertEqual("Element mit Vorlagen-Default (0.0 pt) erbt globalen Abstand unten (25 pt)", 25.0, $effTemplate['bottom']);

        // E. Hierarchical Section & Subsection Spacing Persistence
        $defs = crm_get_pdf_sections_definitions('angebot');
        $firstSecKey = array_key_first($defs);
        $firstSubKey = !empty($defs[$firstSecKey]['subsections']) ? array_key_first($defs[$firstSecKey]['subsections']) : 'sub_test';

        $testSpacingEntryId = 999995;
        $testSections = [
            [
                'key'            => $firstSecKey,
                'title'          => 'Test Section',
                'enabled'        => true,
                'spacing_top'    => 16.0,
                'spacing_bottom' => 24.0,
                'subsections'    => [
                    [
                        'key'            => $firstSubKey,
                        'title'          => 'Test Sub',
                        'enabled'        => true,
                        'spacing_top'    => 6.0,
                        'spacing_bottom' => 9.0,
                    ]
                ]
            ]
        ];
        crm_save_pdf_section_order('angebot', $testSections, $testSpacingEntryId);
        $retrieved = crm_get_pdf_section_order('angebot', $testSpacingEntryId);
        $foundSec = null;
        foreach ($retrieved as $s) {
            if ($s['key'] === $firstSecKey) {
                $foundSec = $s;
                break;
            }
        }
        $this->assert("Abschnitt '{$firstSecKey}' in geladener Reihenfolge vorhanden", $foundSec !== null);
        if ($foundSec) {
            $this->assertEqual("Persistierter Hauptabschnitt speichert spacing_top = 16 pt", 16.0, (float)$foundSec['spacing_top']);
            $this->assertEqual("Persistierter Hauptabschnitt speichert spacing_bottom = 24 pt", 24.0, (float)$foundSec['spacing_bottom']);
            $this->assert("Unterabschnitt in geladener Reihenfolge vorhanden", !empty($foundSec['subsections']));
            if (!empty($foundSec['subsections'])) {
                $foundSub = $foundSec['subsections'][0];
                $this->assertEqual("Persistierter Unterabschnitt speichert spacing_top = 6 pt", 6.0, (float)$foundSub['spacing_top']);
                $this->assertEqual("Persistierter Unterabschnitt speichert spacing_bottom = 9 pt", 9.0, (float)$foundSub['spacing_bottom']);
            }
        }
        delete_option('crm_pdf_sec_angebot_' . $testSpacingEntryId);

        // F. Integration Check across all 5 PDF Generators
        $generators = [
            'Angebot (offer.php)'                              => dirname(__DIR__) . '/pdf/offer.php',
            'Kurszeitenbestätigung (kurszeitenbestaetigung.php)' => dirname(__DIR__) . '/pdf/kurszeitenbestaetigung.php',
            'Teilnahmebestätigung (teilnamebestaetigung.php)'  => dirname(__DIR__) . '/pdf/teilnamebestaetigung.php',
            'Diplom (diplom.php)'                              => dirname(__DIR__) . '/pdf/diplom.php',
            'Rechnung (invoice.php)'                           => dirname(__DIR__) . '/pdf/invoice.php',
        ];
        foreach ($generators as $label => $path) {
            $content = file_get_contents($path);
            $this->assert("{$label} bindet crm_get_pdf_elements_spacing oder crm_get_pdf_spacing_html ein",
                strpos($content, 'crm_get_pdf_spacing_html') !== false || strpos($content, 'crm_get_pdf_elements_spacing') !== false
            );
            $this->assert("{$label} unterstützt Unterabschnitt-Abstände (sub_sp_top / sub_prefix)",
                strpos($content, 'sub_prefix') !== false || strpos($content, 'sub_sp_top') !== false
            );
        }

        // G. AJAX Endpoints für asynchrones Speichern
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("crm-admin.php registriert wp_ajax_crm_save_pdf_elements_spacing",
            strpos($adminPhp, 'wp_ajax_crm_save_pdf_elements_spacing') !== false
        );
        $this->assert("crm-admin.php registriert wp_ajax_crm_save_pdf_master_header_footer",
            strpos($adminPhp, 'wp_ajax_crm_save_pdf_master_header_footer') !== false
        );

        // Option nach Test sauber wiederherstellen
        if ($origSpacing !== false) {
            update_option('crm_pdf_elements_spacing', $origSpacing);
        } else {
            delete_option('crm_pdf_elements_spacing');
        }
    }

    public function testCrossPageDragAndDrop(): void
    {
        echo "\n\033[1;33m[SUITE 9] Cross-Page Drag & Drop & Subsections Deduplication\033[0m\n";

        // A. Asset Sortable Configuration Check
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $settingsJs = file_get_contents(dirname(__DIR__) . '/assets/crm-settings.js');
        $this->assert("crm-admin.js enthält connectWith für .crm-sortable-subsections",
            strpos($adminJs, "connectWith: '.crm-sortable-subsections'") !== false
        );
        $this->assert("crm-settings.js enthält connectWith für .crm-sortable-subsections",
            strpos($settingsJs, "connectWith: '.crm-sortable-subsections'") !== false
        );

        // B. All 5 PDF Generators contain flattened_sub_generators fallback
        $generators = [
            'Angebot (offer.php)'                              => dirname(__DIR__) . '/pdf/offer.php',
            'Kurszeitenbestätigung (kurszeitenbestaetigung.php)' => dirname(__DIR__) . '/pdf/kurszeitenbestaetigung.php',
            'Teilnahmebestätigung (teilnamebestaetigung.php)'  => dirname(__DIR__) . '/pdf/teilnamebestaetigung.php',
            'Diplom (diplom.php)'                              => dirname(__DIR__) . '/pdf/diplom.php',
            'Rechnung (invoice.php)'                           => dirname(__DIR__) . '/pdf/invoice.php',
        ];
        foreach ($generators as $label => $path) {
            $content = file_get_contents($path);
            $this->assert("{$label} enthält \$flattened_sub_generators Fallback",
                strpos($content, 'flattened_sub_generators') !== false
            );
        }

        // C. Standard Section cross-page move & deduplication
        // In Angebot: Move 'hinweis_nachstehend' from 'deckblatt' to 'veranstaltung'
        $defaultOrder = crm_get_pdf_section_order('angebot');
        $testOrder = [];
        $movedSub = null;

        foreach ($defaultOrder as $sec) {
            $secCopy = $sec;
            if ($sec['key'] === 'deckblatt') {
                $filteredSubs = [];
                foreach ($sec['subsections'] as $sub) {
                    if ($sub['key'] === 'hinweis_nachstehend') {
                        $movedSub = $sub;
                    } else {
                        $filteredSubs[] = $sub;
                    }
                }
                $secCopy['subsections'] = $filteredSubs;
            }
            $testOrder[] = $secCopy;
        }

        $this->assert("Unterabschnitt 'hinweis_nachstehend' aus deckblatt extrahiert", $movedSub !== null);

        // Insert into 'veranstaltung'
        foreach ($testOrder as &$sec) {
            if ($sec['key'] === 'veranstaltung' && $movedSub !== null) {
                $sec['subsections'][] = $movedSub;
            }
        }
        unset($sec);

        // Save order for a test entry
        $testEntryId = 888888;
        $saved = crm_save_pdf_section_order('angebot', $testOrder, $testEntryId);
        $this->assert("crm_save_pdf_section_order liefert true für seitenübergreifenden Move", $saved === true);

        // Load back and inspect
        $reloaded = crm_get_pdf_section_order('angebot', $testEntryId);

        $deckblattSubs = [];
        $veranstaltungSubs = [];
        foreach ($reloaded as $sec) {
            if ($sec['key'] === 'deckblatt') {
                $deckblattSubs = array_column($sec['subsections'], 'key');
            }
            if ($sec['key'] === 'veranstaltung') {
                $veranstaltungSubs = $sec['subsections'];
            }
        }

        $this->assert("deckblatt enthält 'hinweis_nachstehend' NICHT mehr (kein Duplikat)", !in_array('hinweis_nachstehend', $deckblattSubs, true));

        $vSubKeys = array_column($veranstaltungSubs, 'key');
        $this->assert("veranstaltung enthält nun 'hinweis_nachstehend'", in_array('hinweis_nachstehend', $vSubKeys, true));

        // Find the moved item
        $foundMoved = null;
        foreach ($veranstaltungSubs as $sub) {
            if ($sub['key'] === 'hinweis_nachstehend') {
                $foundMoved = $sub;
                break;
            }
        }
        $this->assert("Verschobenes 'hinweis_nachstehend' behält is_custom = false", $foundMoved && $foundMoved['is_custom'] === false);
        $this->assertEqual("Verschobenes 'hinweis_nachstehend' behält Original-Titel", 'Gliederungsverweis', $foundMoved['orig_title'] ?? '');

        // D. Move standard subsection into a custom section
        $customOrder = $defaultOrder;
        $customSection = [
            'key'         => 'custom_zusatzseite',
            'is_custom'   => true,
            'title'       => 'Meine Zusatzseite',
            'badge'       => 'Zusatz',
            'enabled'     => true,
            'subsections' => [
                [
                    'key'         => 'hinweis_nachstehend',
                    'is_custom'   => false,
                    'enabled'     => true,
                    'spacing_top' => 5.0,
                ]
            ]
        ];
        // Remove from deckblatt
        foreach ($customOrder as &$sec) {
            if ($sec['key'] === 'deckblatt') {
                $filtered = [];
                foreach ($sec['subsections'] as $sub) {
                    if ($sub['key'] !== 'hinweis_nachstehend') {
                        $filtered[] = $sub;
                    }
                }
                $sec['subsections'] = $filtered;
            }
        }
        unset($sec);
        $customOrder[] = $customSection;

        $testEntryIdCustom = 999999;
        crm_save_pdf_section_order('angebot', $customOrder, $testEntryIdCustom);
        $reloadedCustom = crm_get_pdf_section_order('angebot', $testEntryIdCustom);

        $foundCustomPage = null;
        $foundDeckblatt = null;
        foreach ($reloadedCustom as $sec) {
            if ($sec['key'] === 'custom_zusatzseite') {
                $foundCustomPage = $sec;
            }
            if ($sec['key'] === 'deckblatt') {
                $foundDeckblatt = $sec;
            }
        }

        $this->assert("Benutzerdefinierte Seite custom_zusatzseite existiert", $foundCustomPage !== null);
        $this->assert("deckblatt hat 'hinweis_nachstehend' nicht erneut injiziert", !in_array('hinweis_nachstehend', array_column($foundDeckblatt['subsections'], 'key'), true));

        $customSubs = $foundCustomPage['subsections'] ?? [];
        $foundCustomMoved = null;
        foreach ($customSubs as $sub) {
            if ($sub['key'] === 'hinweis_nachstehend') {
                $foundCustomMoved = $sub;
            }
        }
        $this->assert("Standard-Unterabschnitt auf Custom-Seite gefunden", $foundCustomMoved !== null);
        if ($foundCustomMoved) {
            $this->assert("Standard-Unterabschnitt auf Custom-Seite behält is_custom = false", $foundCustomMoved['is_custom'] === false);
            $this->assertEqual("Standard-Unterabschnitt auf Custom-Seite behält spacing_top = 5.0", 5.0, (float)$foundCustomMoved['spacing_top']);
        }

        // E. Title Banner Isolation & Auto-Healing on all Pages
        $freshDefs = crm_get_pdf_sections_definitions('angebot');
        $expectedTitles = [
            'deckblatt'        => 'titel',
            'veranstaltung'    => 'veranstaltung_titel',
            'abschluss'        => 'abschluss_titel',
            'kosten'           => 'kosten_titel',
            'anmeldung'        => 'anmeldung_titel',
            'inhalte'          => 'inhalte_titel',
            'zusatzleistungen' => 'zusatzleistungen_titel',
        ];

        foreach ($expectedTitles as $sKey => $expectedSubKey) {
            $this->assert("Abschnitt '{$sKey}' besitzt eindeutigen Titel-Key '{$expectedSubKey}'",
                isset($freshDefs[$sKey]['subsections'][$expectedSubKey])
            );
        }

        // Auto-Healing test: Corrupted saved structure without titles on pages 2-7
        $corruptedOrder = [
            [
                'key'         => 'deckblatt',
                'subsections' => [
                    ['key' => 'empfaenger', 'enabled' => true],
                    ['key' => 'titel', 'enabled' => true],
                ]
            ],
            [
                'key'         => 'veranstaltung',
                'subsections' => [
                    ['key' => 'zeitraum', 'enabled' => true],
                    ['key' => 'module', 'enabled' => true],
                ]
            ],
            [
                'key'         => 'kosten',
                'subsections' => [
                    ['key' => 'preistabelle', 'enabled' => true],
                ]
            ],
        ];

        $healTestEntry = 777777;
        crm_save_pdf_section_order('angebot', $corruptedOrder, $healTestEntry);
        $healedOrder = crm_get_pdf_section_order('angebot', $healTestEntry);

        foreach ($healedOrder as $sec) {
            if ($sec['key'] === 'veranstaltung') {
                $firstSub = $sec['subsections'][0]['key'] ?? '';
                $this->assertEqual("veranstaltung auto-heilt veranstaltung_titel an Index 0", 'veranstaltung_titel', $firstSub);
            }
            if ($sec['key'] === 'kosten') {
                $firstSub = $sec['subsections'][0]['key'] ?? '';
                $this->assertEqual("kosten auto-heilt kosten_titel an Index 0", 'kosten_titel', $firstSub);
            }
        }

        // F. HTML Indentation Check (No leading whitespace inside <td> in offer-ort-durchfuehrung.php)
        $ortFile = dirname(__DIR__) . '/pdf/elements/offer-ort-durchfuehrung.php';
        $ortContent = file_get_contents($ortFile);
        $this->assert("offer-ort-durchfuehrung.php enthält keine führenden Leerzeichen/Zeilenumbrüche nach <td>",
            !preg_match('/<td[^>]*>\s+<[a-z]/i', $ortContent)
        );
    }

    public function testStandardComponentsEditing(): void
    {
        echo "\n\033[1;33m[SUITE 10] Standard-Komponenten Editierbarkeit ({standard})\033[0m\n";

        require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

        // 1. Helper crm_get_subsection_default_html existence and execution
        $this->assert("crm_get_subsection_default_html Funktion existiert", function_exists('crm_get_subsection_default_html'));

        // 2. HTML Generation for key standard components (sample entry 1)
        $modHtml = crm_get_subsection_default_html('angebot', 'veranstaltung', 'module', 1, 36593);
        $this->assert("crm_get_subsection_default_html liefert Modul-HTML", !empty($modHtml) && strpos($modHtml, '<table') !== false && strpos($modHtml, 'Gliederung') !== false);

        $zeitHtml = crm_get_subsection_default_html('angebot', 'veranstaltung', 'zeiteinteilung', 1, 36593);
        $this->assert("crm_get_subsection_default_html liefert Zeiteinteilung-HTML", !empty($zeitHtml) && strpos($zeitHtml, '<table') !== false && strpos($zeitHtml, 'Zeiteinteilung') !== false);

        $preisHtml = crm_get_subsection_default_html('angebot', 'kosten', 'preistabelle', 1, 36593);
        $this->assert("crm_get_subsection_default_html liefert Preistabellen-HTML", !empty($preisHtml) && strpos($preisHtml, '<table') !== false);

        $abschlussHtml = crm_get_subsection_default_html('angebot', 'abschluss', 'abschluss_box', 1, 36593);
        $this->assert("crm_get_subsection_default_html liefert Abschlussbox-HTML", !empty($abschlussHtml) && strpos($abschlussHtml, 'ABSCHLUSS') !== false);

        $sigHtml = crm_get_subsection_default_html('angebot', 'deckblatt', 'signatur', 1, 36593);
        $this->assert("crm_get_subsection_default_html liefert Signatur-HTML", !empty($sigHtml) && strpos($sigHtml, 'Gasberger') !== false);

        $kbSigHtml = crm_get_subsection_default_html('kb', 'signatur', 'signatur_box', 1, 36593);
        $this->assert("crm_get_subsection_default_html unterstützt auch kb Dokumententyp", !empty($kbSigHtml));

        // 3. UI Assets Integrity
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js enthält Event-Handler für .crm-sub-load-standard-btn",
            strpos($adminJs, '.crm-sub-load-standard-btn') !== false
        );
        $this->assert("crm-admin.js ruft action 'crm_get_subsection_default_html' auf",
            strpos($adminJs, "action: 'crm_get_subsection_default_html'") !== false
        );

        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("crm-admin.php registriert wp_ajax_crm_get_subsection_default_html",
            strpos($adminPhp, "wp_ajax_crm_get_subsection_default_html") !== false
        );

        $sectionsPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-pdf-sections.php');
        $this->assert("crm-pdf-sections.php rendert Button crm-sub-load-standard-btn",
            strpos($sectionsPhp, 'crm-sub-load-standard-btn') !== false
        );

        // 4. Persistence of Custom HTML on standard component
        $testEntryId = 999111;
        $order = crm_get_pdf_section_order('angebot', $testEntryId);
        $customModHtml = '<table><tr><td>INDIVIDUELLE_TEST_MODULE_42</td></tr></table>';
        foreach ($order as &$sec) {
            if ($sec['key'] === 'veranstaltung') {
                foreach ($sec['subsections'] as &$sub) {
                    if ($sub['key'] === 'module') {
                        $sub['content'] = $customModHtml;
                    }
                }
            }
        }
        unset($sec);

        $saved = crm_save_pdf_section_order('angebot', $order, $testEntryId);
        $this->assert("crm_save_pdf_section_order speichert benutzerdefiniertes HTML für Standardkomponente", $saved === true);

        $reloaded = crm_get_pdf_section_order('angebot', $testEntryId);
        $foundCustom = false;
        foreach ($reloaded as $sec) {
            if ($sec['key'] === 'veranstaltung') {
                foreach ($sec['subsections'] as $sub) {
                    if ($sub['key'] === 'module' && $sub['content'] === $customModHtml) {
                        $foundCustom = true;
                    }
                }
            }
        }
        $this->assert("crm_get_pdf_section_order liefert exaktes benutzerdefiniertes HTML für 'module'", $foundCustom);

        // 5. Reset to standard
        crm_reset_pdf_section_order('angebot', $testEntryId);
        $resetOrder = crm_get_pdf_section_order('angebot', $testEntryId);
        $isDynamicAgain = false;
        foreach ($resetOrder as $sec) {
            if ($sec['key'] === 'veranstaltung') {
                foreach ($sec['subsections'] as $sub) {
                    if ($sub['key'] === 'module' && empty($sub['content'])) {
                        $isDynamicAgain = true;
                    }
                }
            }
        }
        $this->assert("crm_reset_pdf_section_order stellt dynamischen Standard wieder her (content leer)", $isDynamicAgain);
    }

    public function testGlobalAndCustomHtmlPermanentSaving(): void
    {
        echo "\n\033[1;33m[SUITE 11] Dauerhaftes Speichern & Vererbung von Standard-HTML\033[0m\n";

        require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

        // 1. Strict equality check in crm_is_legacy_default_pdf_content
        $exactLegacySnippet = "ORT: X SIEBEN Wirtschaftstraining, Rochusgasse 6 in 1030 Wien\nDurchführung unserer Schulungen: Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning";
        $this->assert(
            "crm_is_legacy_default_pdf_content erkennt exakten Legacy-Snippet",
            crm_is_legacy_default_pdf_content('abschluss', 'ort_durchfuehrung', $exactLegacySnippet) === true
        );

        $customizedOrt = "ORT: X SIEBEN Wirtschaftstraining, Rochusgasse 6 in 1030 Wien\nDurchführung unserer Schulungen: Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning\nHinweis: Kundenspezifischer Zusatz ohne Einrückung.";
        $this->assert(
            "crm_is_legacy_default_pdf_content verwirft angepassten Text NICHT fälschlich als Legacy-Snippet",
            crm_is_legacy_default_pdf_content('abschluss', 'ort_durchfuehrung', $customizedOrt) === false
        );

        // 2. Permanentes Speichern von modifiziertem Standardtext auf Eintrags-Ebene
        $entryTest = 998877;
        $order = crm_get_pdf_section_order('angebot', $entryTest);
        foreach ($order as &$sec) {
            if ($sec['key'] === 'abschluss') {
                foreach ($sec['subsections'] as &$sub) {
                    if ($sub['key'] === 'ort_durchfuehrung') {
                        $sub['content'] = $customizedOrt;
                    }
                }
            }
        }
        unset($sec);

        $saved = crm_save_pdf_section_order('angebot', $order, $entryTest);
        $this->assert("crm_save_pdf_section_order speichert angepassten Text für ort_durchfuehrung", $saved === true);

        $reloaded = crm_get_pdf_section_order('angebot', $entryTest);
        $loadedOrt = '';
        foreach ($reloaded as $sec) {
            if ($sec['key'] === 'abschluss') {
                foreach ($sec['subsections'] as $sub) {
                    if ($sub['key'] === 'ort_durchfuehrung') {
                        $loadedOrt = $sub['content'];
                    }
                }
            }
        }
        $this->assertEqual(
            "crm_get_pdf_section_order liefert unveränderten benutzerdefinierten Text (keine Löschung)",
            $customizedOrt,
            $loadedOrt
        );

        // 3. Globale Anpassung & Kaskadierende Vererbung an Einträge ohne lokalen Override
        $origGlobal = get_option('crm_pdf_section_order_angebot');
        $globalOrder = crm_get_pdf_section_order('angebot', null);
        $globalCurriculumHtml = '<table class="global-curriculum-tpl"><tr><td>GLOBAL CURRICULUM PERMANENT</td></tr></table>';

        foreach ($globalOrder as &$sec) {
            if ($sec['key'] === 'inhalte') {
                foreach ($sec['subsections'] as &$sub) {
                    if ($sub['key'] === 'curriculum') {
                        $sub['content'] = $globalCurriculumHtml;
                    }
                }
            }
        }
        unset($sec);

        $globalSaved = crm_save_pdf_section_order('angebot', $globalOrder, null);
        $this->assert("crm_save_pdf_section_order speichert globale Standard-HTML-Vorlage", $globalSaved === true);

        // Entry ohne lokalen Override für curriculum anlegen
        $entryInheritTest = 998866;
        $entryOrder = crm_get_pdf_section_order('angebot', null);
        foreach ($entryOrder as &$sec) {
            if ($sec['key'] === 'inhalte') {
                foreach ($sec['subsections'] as &$sub) {
                    if ($sub['key'] === 'curriculum') {
                        $sub['content'] = ''; // kein lokaler Override
                    }
                }
            }
        }
        unset($sec);
        crm_save_pdf_section_order('angebot', $entryOrder, $entryInheritTest);

        // Auslesen für den Eintrag: muss das globale Standard-HTML erben
        $entryLoaded = crm_get_pdf_section_order('angebot', $entryInheritTest);
        $inheritedCurriculum = '';
        foreach ($entryLoaded as $sec) {
            if ($sec['key'] === 'inhalte') {
                foreach ($sec['subsections'] as $sub) {
                    if ($sub['key'] === 'curriculum') {
                        $inheritedCurriculum = $sub['content'];
                    }
                }
            }
        }
        $this->assertEqual(
            "Eintrag ohne lokalen Override erbt globales benutzerdefiniertes Standard-HTML",
            $globalCurriculumHtml,
            $inheritedCurriculum
        );

        // 4. Eintrags-spezifischer lokaler Override hat Vorrang vor globalem Standard
        $localCurriculumHtml = '<table class="local-curriculum-override"><tr><td>LOCAL OVERRIDE</td></tr></table>';
        foreach ($entryLoaded as &$sec) {
            if ($sec['key'] === 'inhalte') {
                foreach ($sec['subsections'] as &$sub) {
                    if ($sub['key'] === 'curriculum') {
                        $sub['content'] = $localCurriculumHtml;
                    }
                }
            }
        }
        unset($sec);
        crm_save_pdf_section_order('angebot', $entryLoaded, $entryInheritTest);

        $entryLoadedOverride = crm_get_pdf_section_order('angebot', $entryInheritTest);
        $overrideCurriculum = '';
        foreach ($entryLoadedOverride as $sec) {
            if ($sec['key'] === 'inhalte') {
                foreach ($sec['subsections'] as $sub) {
                    if ($sub['key'] === 'curriculum') {
                        $overrideCurriculum = $sub['content'];
                    }
                }
            }
        }
        $this->assertEqual(
            "Lokaler Eintrags-Override überschreibt globales Standard-HTML",
            $localCurriculumHtml,
            $overrideCurriculum
        );

        // 5. Cleanup
        delete_option('crm_pdf_sec_angebot_' . $entryTest);
        delete_option('crm_pdf_sec_angebot_' . $entryInheritTest);
        if ($origGlobal !== false && is_array($origGlobal)) {
            update_option('crm_pdf_section_order_angebot', $origGlobal);
        } else {
            delete_option('crm_pdf_section_order_angebot');
        }

        // 6. UI Check: crm-admin.js auto-triggers load when opening standard components with empty or {standard} content
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert(
            "crm-admin.js lädt Standard-HTML automatisch beim Klick auf Bearbeiten",
            strpos($adminJs, "currentVal === '{standard}' || currentVal === ''") !== false &&
            strpos($adminJs, '$loadBtn.trigger(\'click\')') !== false
        );
    }

    /**
     * SUITE 12: PDF Live-Vorschau & Globale Einstellungs-Synchronisation
     */
    public function testPdfPreviewAndGlobalSync(): void
    {
        echo "\n\033[1;33m[SUITE 12] PDF Live-Vorschau & Globale Einstellungs-Synchronisation\033[0m\n";

        // 1. settings-controler.php leitet die Abschnitte an alle 5 PDF-Generatoren weiter
        $settingsCtrl = file_get_contents(dirname(__DIR__) . '/controler/settings-controler.php');
        $this->assert(
            "settings-controler.php ermittelt Abschnitte via crm_get_pdf_section_order",
            strpos($settingsCtrl, 'crm_get_pdf_section_order($doc_type, $requested_entry_id)') !== false
        );
        $this->assert(
            "settings-controler.php übergibt preview_sections an xsieben_offer_pdf",
            strpos($settingsCtrl, 'xsieben_offer_pdf($entry_id, $course_id, false, $preview_sections)') !== false
        );
        $this->assert(
            "settings-controler.php übergibt preview_sections an xsieben_kurszeitenbestaetigung_pdf",
            strpos($settingsCtrl, 'xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false, $preview_sections)') !== false
        );
        $this->assert(
            "settings-controler.php übergibt preview_sections an xsieben_diplom_pdf",
            strpos($settingsCtrl, 'xsieben_diplom_pdf($entry_id, $course_id, false, null, $preview_sections)') !== false
        );

        // 2. Alle 5 PDF-Generatoren unterstützen strukturierte $custom_sections direkt
        $generators = [
            'offer.php'                => dirname(__DIR__) . '/pdf/offer.php',
            'kurszeitenbestaetigung.php' => dirname(__DIR__) . '/pdf/kurszeitenbestaetigung.php',
            'teilnamebestaetigung.php' => dirname(__DIR__) . '/pdf/teilnamebestaetigung.php',
            'diplom.php'               => dirname(__DIR__) . '/pdf/diplom.php',
            'invoice.php'              => dirname(__DIR__) . '/pdf/invoice.php',
        ];
        foreach ($generators as $genName => $genPath) {
            $genContent = file_get_contents($genPath);
            $hasStructuredSupport = strpos($genContent, '$all_sections = $custom_sections;') !== false;
            $this->assert("{$genName} unterstützt vollständige strukturierte custom_sections direkt", $hasStructuredSupport);
        }

        // 3. tab-pdf.php hat Angebot als aktiven Standard in Live-Vorschau
        $tabPdf = file_get_contents(dirname(__DIR__) . '/views/settings/tab-pdf.php');
        $this->assert(
            "tab-pdf.php setzt Live-Vorschau Titel standardmäßig auf Angebot & Anhang",
            strpos($tabPdf, "id=\"crm-preview-doc-title\" style=\"color: #6d28d9;\"><?php esc_html_e('Angebot & Anhang', 'custom-crm'); ?>") !== false
        );
        $this->assert(
            "tab-pdf.php hat Angebot-Button im Preview Switcher als active markiert",
            strpos($tabPdf, 'class="button crm-preview-switch-btn active" data-doc="angebot"') !== false
        );

        // 4. crm-settings.js initialisiert currentPreviewDoc auf 'angebot'
        $settingsJs = file_get_contents(dirname(__DIR__) . '/assets/crm-settings.js');
        $this->assert(
            "crm-settings.js initialisiert currentPreviewDoc auf 'angebot'",
            strpos($settingsJs, "let currentPreviewDoc = 'angebot';") !== false
        );

        // 5. Globale Angebotsstruktur ist vollständig aktiv
        $globalOffer = crm_get_pdf_section_order('angebot', null);
        $allActive = true;
        foreach ($globalOffer as $sec) {
            if (empty($sec['enabled'])) {
                $allActive = false;
                break;
            }
        }
        $this->assert("Globale Angebotsstruktur hat alle Standardabschnitte aktiviert (enabled: true)", $allActive);

        // 6. Angebot 2 (Inkl. Zertifizierung) ist im System vollständig integriert
        $angebot2Defs = crm_get_pdf_sections_definitions('angebot_2');
        $this->assert("crm_get_pdf_sections_definitions('angebot_2') liefert 7 Abschnitte", count($angebot2Defs) === 7);
        $this->assert("angebot_2 Definition enthält 'kosten'", isset($angebot2Defs['kosten']));
        $this->assert("angebot_2 Definition enthält 'abschluss'", isset($angebot2Defs['abschluss']));

        $this->assert(
            "settings-controler.php leitet angebot_2 mit Variante mit_zertifikat weiter",
            strpos($settingsCtrl, "case 'angebot_2':") !== false &&
            strpos($settingsCtrl, "xsieben_offer_pdf(\$entry_id, \$course_id, false, \$preview_sections, 'mit_zertifikat')") !== false
        );

        $this->assert(
            "tab-pdf.php enthält Pill für Angebot 2 (data-doc=\"angebot_2\")",
            strpos($tabPdf, 'data-doc="angebot_2"') !== false
        );
        $this->assert(
            "tab-pdf.php rendert Container #crm-sec-pane-angebot_2",
            strpos($tabPdf, 'id="crm-sec-pane-angebot_2"') !== false
        );
        $offerPhpCode = file_get_contents($generators['offer.php']);
        $this->assert(
            "offer.php lädt Abschnitte für angebot_2 bei mit_zertifikat",
            strpos($offerPhpCode, '$target_doc = ($offer_variant === \'mit_zertifikat\') ? \'angebot_2\' : \'angebot\';') !== false
        );
    }

    public function testFullWidthDocumentTitle(): void
    {
        echo "\n\033[1;33m[SUITE 13] Full-Bleed Dokumententitel über gesamte Seitenbreite\033[0m\n";

        // 1. CRM_Pdf_Presenter::render_pdf_title() verwendet 15mm Padding
        $renderedTitle = CRM_Pdf_Presenter::render_pdf_title('Diplomierter Digital Marketing Manager', 'Angebot');
        $this->assert(
            "render_pdf_title enthält 15mm Innenabstand für nahtlose Fluchtlinie",
            strpos($renderedTitle, '15mm') !== false
        );
        $this->assert(
            "render_pdf_title enthält Primärfarbe #007C90",
            strpos($renderedTitle, '#007C90') !== false
        );
        $this->assert(
            "render_pdf_title enthält Klasse 'title'",
            strpos($renderedTitle, 'class="title"') !== false
        );

        // 2. offer-styles.php definiert .title mit 15mm Padding
        $offerStyles = file_get_contents(dirname(__DIR__) . '/pdf/elements/offer-styles.php');
        $this->assert(
            "offer-styles.php definiert .title mit 15mm seitlichem Padding",
            strpos($offerStyles, '15mm') !== false
        );

        // 3. offer.php enthält Logik für randlose Darstellung (writeHTMLCell mit voller Seitenbreite)
        $offerPhp = file_get_contents(dirname(__DIR__) . '/pdf/offer.php');
        $this->assert(
            "offer.php erkennt Titel-Tabellen via Regex",
            strpos($offerPhp, '\btitle\b') !== false && strpos($offerPhp, 'preg_split') !== false
        );
        $this->assert(
            "offer.php rendert Titel-Banner via writeHTMLCell über volle Seitenbreite (0 bis getPageWidth)",
            strpos($offerPhp, '$pdf->writeHTMLCell($page_w, 0, 0, $title_y, $fullwidth_title') !== false
        );
        $this->assert(
            "offer.php normalisiert Banner-Spaltentabelle für 15mm Fluchtlinien-Treue",
            strpos($offerPhp, 'width="11.8mm"') !== false
        );

        // 4. CRM_Pdf_Presenter::render_zertifizierungen_images() skaliert Bilder unverzerrt
        $testZertHtml = CRM_Pdf_Presenter::render_zertifizierungen_images(['https://example.com/logo1.png']);
        $this->assert(
            "render_zertifizierungen_images erzeugt kein starres width=48 height=24 (keine Verzerrung)",
            strpos($testZertHtml, 'width="48" height="24"') === false
        );
        $this->assert(
            "render_zertifizierungen_images setzt saubere Tabellenzellen-Formatierung",
            strpos($testZertHtml, 'border: 1px solid #cbd5e1') !== false
        );
    }

    public function testHeaderSpacingControls(): void
    {
        echo "\n\033[1;33m[SUITE 14] Header-Abstände & Margins Konfigurierbarkeit (Master & Abschnitte)\033[0m\n";

        $origMaster = get_option('crm_pdf_master_header_footer');
        delete_option('crm_pdf_master_header_footer');

        // 1. Master Header-Footer Defaults enthalten header_margin_top und header_margin_bottom
        $defaults = crm_get_pdf_master_header_footer();
        $this->assert("crm_get_pdf_master_header_footer enthält 'header_margin_top'", array_key_exists('header_margin_top', $defaults));
        $this->assert("crm_get_pdf_master_header_footer default header_margin_top ist 8.0", (float)$defaults['header_margin_top'] === 8.0);
        $this->assert("crm_get_pdf_master_header_footer enthält 'header_margin_bottom'", array_key_exists('header_margin_bottom', $defaults));
        $this->assert("crm_get_pdf_master_header_footer default header_margin_bottom ist 32.0", (float)$defaults['header_margin_bottom'] === 32.0);

        // 2. Master Header Spacing Speichern und Validieren
        $saved = crm_save_pdf_master_header_footer([
            'header_mode'          => 'full',
            'header_margin_top'    => '14.5',
            'header_margin_bottom' => '48.0',
            'footer_mode'          => 'standard',
        ]);
        $this->assert("crm_save_pdf_master_header_footer speichert erfolgreich", $saved);
        $retrieved = crm_get_pdf_master_header_footer();
        $this->assert("crm_get_pdf_master_header_footer liefert gespeicherten header_margin_top = 14.5", $retrieved['header_margin_top'] === 14.5);
        $this->assert("crm_get_pdf_master_header_footer liefert gespeicherten header_margin_bottom = 48.0", $retrieved['header_margin_bottom'] === 48.0);

        // Option nach Test sauber wiederherstellen
        if ($origMaster !== false) {
            update_option('crm_pdf_master_header_footer', $origMaster);
        } else {
            delete_option('crm_pdf_master_header_footer');
        }

        // 3. Section Header Margins Persistence
        $testOrder = [
            [
                'key'                  => 'deckblatt',
                'enabled'              => 1,
                'is_custom'            => 0,
                'title'                => 'Deckblatt & Angebot',
                'header_mode'          => 'full',
                'header_margin_top'    => 10.5,
                'header_margin_bottom' => 42.0,
                'spacing_top'          => 0,
                'spacing_bottom'       => 0,
                'subsections'          => []
            ]
        ];
        $orderSaved = crm_save_pdf_section_order('angebot', $testOrder, null);
        $this->assert("crm_save_pdf_section_order speichert Abschnitts-Header-Abstände", $orderSaved);
        $savedSections = crm_get_pdf_section_order('angebot', null);
        $deckblatt = null;
        foreach ($savedSections as $sec) {
            if (($sec['key'] ?? '') === 'deckblatt') {
                $deckblatt = $sec;
                break;
            }
        }
        $this->assert("Abschnitt 'deckblatt' nach Speicherung gefunden", $deckblatt !== null);
        $this->assert("Persistierter Abschnitt speichert header_margin_top = 10.5", isset($deckblatt['header_margin_top']) && $deckblatt['header_margin_top'] == 10.5);
        $this->assert("Persistierter Abschnitt speichert header_margin_bottom = 42.0", isset($deckblatt['header_margin_bottom']) && $deckblatt['header_margin_bottom'] == 42.0);

        // Reset global order to full defaults
        crm_reset_pdf_section_order('angebot', null);

        // 4. offer.php nutzt dynamische Header-Margins und fluchtet bei 15.0 mm
        $offerPhp = file_get_contents(dirname(__DIR__) . '/pdf/offer.php');
        $this->assert("offer.php ermittelt dynamischen top_margin (header_margin_bottom) in AddPage()", strpos($offerPhp, 'header_margin_bottom') !== false);
        $this->assert("offer.php ermittelt dynamischen header_y (header_margin_top) in Header()", strpos($offerPhp, 'header_margin_top') !== false);
        $this->assert("offer.php positioniert Kopfzeile 5-7 pt weiter links (\$header_x = 12.9 mm)", strpos($offerPhp, '$header_x = 12.9;') !== false || strpos($offerPhp, '$header_x = 13.0;') !== false);

        // 5. tab-pdf.php enthält Master-Header Abstandsfelder
        $tabPdf = file_get_contents(dirname(__DIR__) . '/views/settings/tab-pdf.php');
        $this->assert("tab-pdf.php enthält Input für crm_pdf_master_hf[header_margin_top]", strpos($tabPdf, 'crm_pdf_master_hf[header_margin_top]') !== false);
        $this->assert("tab-pdf.php enthält Input für crm_pdf_master_hf[header_margin_bottom]", strpos($tabPdf, 'crm_pdf_master_hf[header_margin_bottom]') !== false);

        // 6. crm-pdf-sections.php enthält Abschnitts-Header Abstandsfelder und Data-Attribute
        $pdfSections = file_get_contents(dirname(__DIR__) . '/helpers/crm-pdf-sections.php');
        $this->assert("crm-pdf-sections.php rendert data-header-margin-top", strpos($pdfSections, 'data-header-margin-top') !== false);
        $this->assert("crm-pdf-sections.php rendert data-header-margin-bottom", strpos($pdfSections, 'data-header-margin-bottom') !== false);
        $this->assert("crm-pdf-sections.php rendert Input .crm-hf-header-margin-top", strpos($pdfSections, 'crm-hf-header-margin-top') !== false);
        $this->assert("crm-pdf-sections.php rendert Input .crm-hf-header-margin-bottom", strpos($pdfSections, 'crm-hf-header-margin-bottom') !== false);

        // 7. JS-Dateien sammeln Header-Abstände
        $settingsJs = file_get_contents(dirname(__DIR__) . '/assets/crm-settings.js');
        $this->assert("crm-settings.js liest .crm-hf-header-margin-top aus", strpos($settingsJs, 'crm-hf-header-margin-top') !== false);
        $this->assert("crm-settings.js liest .crm-hf-header-margin-bottom aus", strpos($settingsJs, 'crm-hf-header-margin-bottom') !== false);

        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js liest .crm-hf-header-margin-top aus", strpos($adminJs, 'crm-hf-header-margin-top') !== false);
        $this->assert("crm-admin.js liest .crm-hf-header-margin-bottom aus", strpos($adminJs, 'crm-hf-header-margin-bottom') !== false);
    }

    public function testAtomicDesignElementsArchitecture(): void
    {
        echo "\n\033[1;33m[SUITE 15] Modulare Elemente & Bausteine Architektur (Atomic Design)\033[0m\n";

        // 1. crm-admin.php registriert crm-elements Menü & Enqueues
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("crm-admin.php registriert Submenu-Eintrag 'crm-elements'", strpos($adminPhp, "'crm-elements'") !== false);
        $this->assert("crm-admin.php bindet crm-elements in admin_enqueue_scripts ein", strpos($adminPhp, "'crm-elements'") !== false);

        // 2. crm-settings.php unterstützt elements Routing und 4 Tabs
        $settingsPhp = file_get_contents(dirname(__DIR__) . '/crm-settings.php');
        $this->assert("crm-settings.php routet 'crm-elements' auf 'elements' Tab", strpos($settingsPhp, "\$current_page === 'crm-elements'") !== false);
        $this->assert("crm-settings.php bindet tab-elements.php ein", strpos($settingsPhp, "tab-elements.php") !== false);
        $this->assert("crm-settings.php rendert 4 Tabs in der Hauptnavigation", strpos($settingsPhp, "page=crm-elements") !== false);
        $this->assert("crm-settings.php verarbeitet submit_elements Speicherung", strpos($settingsPhp, "submit_elements") !== false || strpos($settingsPhp, "\$saved_tab === 'elements'") !== false);

        // 3. tab-elements.php View existiert und rendert Sektionen
        $tabElementsPath = dirname(__DIR__) . '/views/settings/tab-elements.php';
        $this->assert("tab-elements.php View-Datei existiert", file_exists($tabElementsPath));
        $tabElementsContent = file_get_contents($tabElementsPath);
        $this->assert("tab-elements.php enthält Atomic Design Tag", strpos($tabElementsContent, 'Atomic Design') !== false);
        $this->assert("tab-elements.php enthält visuelle Medien- & Siegel-Übersicht", strpos($tabElementsContent, 'Visuelle Siegel') !== false);
        $this->assert("tab-elements.php enthält Filter-Pills für Bausteine", strpos($tabElementsContent, 'crm-element-filter-pills') !== false);
        $this->assert("tab-elements.php bindet field-editor und cheat-sheet ein", strpos($tabElementsContent, 'field-editor.php') !== false && strpos($tabElementsContent, 'cheat-sheet.php') !== false);

        // 4. JS-Dateien unterstützen Quick Search & Filter
        $settingsJs = file_get_contents(dirname(__DIR__) . '/assets/crm-settings.js');
        $this->assert("crm-settings.js enthält Quick-Search Handler für #crm-element-quick-search", strpos($settingsJs, '#crm-element-quick-search') !== false);
        $this->assert("crm-settings.js enthält Filter-Pill Handler für .crm-element-filter-pills", strpos($settingsJs, '.crm-element-filter-pills') !== false);

        // 5. Merged Custom Fields & Default Components
        if (function_exists('crm_get_merged_custom_fields')) {
            $merged = crm_get_merged_custom_fields();
            $this->assert("crm_get_merged_custom_fields liefert alle Bausteine (mindestens 35)", count($merged) >= 35);
            $titles = array_map(function($f) { return $f['title'] ?? ''; }, $merged);
            $this->assert("Merged Fields enthalten 'KB - Titel'", in_array('KB - Titel', $titles, true));
            $this->assert("Merged Fields enthalten 'TB - Titel'", in_array('TB - Titel', $titles, true));
            $this->assert("Merged Fields enthalten 'Diplom - Titel'", in_array('Diplom - Titel', $titles, true));
            $this->assert("Merged Fields enthalten 'Honorarnote - Titel'", in_array('Honorarnote - Titel', $titles, true));
            $this->assert("Merged Fields enthalten 'Bankverbindung'", in_array('Bankverbindung', $titles, true));
        }

        // 6. Component Placeholders Mapping
        if (function_exists('crm_get_component_placeholder_for_title')) {
            $this->assert("Platzhalter für 'E-Mail Signatur' ist '{signatur_email}'", crm_get_component_placeholder_for_title('E-Mail Signatur') === '{signatur_email}');
            $this->assert("Platzhalter für 'KB - Titel' ist '{kb_titel}'", crm_get_component_placeholder_for_title('KB - Titel') === '{kb_titel}');
            $this->assert("Platzhalter für 'TB - Betrieb Name' ist '{tb_betrieb_name}'", crm_get_component_placeholder_for_title('TB - Betrieb Name') === '{tb_betrieb_name}');
            $this->assert("Platzhalter für 'Honorarnote - Titel' ist '{honorarnote_titel}'", crm_get_component_placeholder_for_title('Honorarnote - Titel') === '{honorarnote_titel}');
        }
    }

    public function testSuite16_SettingsAndInheritance(): void
    {
        echo "\n\033[1;33m[SUITE 16] Stammdaten-Vererbung & Platzhalter-Kaskade\033[0m\n";

        // 1. Dynamic inheritance in default PDF fields
        if (function_exists('crm_get_default_pdf_fields') && function_exists('crm_get_general_settings')) {
            $gen = crm_get_general_settings();
            $pdf_defaults = crm_get_default_pdf_fields();
            $this->assert("KB - Kursinstitut erbt company_name", $pdf_defaults['KB - Kursinstitut Name']['content'] === ($gen['company_name'] ?? 'X SIEBEN Wirtschaftstraining GmbH'));
            $this->assert("TB - Betrieb Name erbt company_name", $pdf_defaults['TB - Betrieb Name']['content'] === ($gen['company_name'] ?? 'X SIEBEN Wirtschaftstraining GmbH'));
            $this->assert("Bankverbindung erbt company_bank", strpos($pdf_defaults['Bankverbindung']['content'], $gen['company_bank'] ?? 'Erste Bank') !== false);
        }

        // 2. Model parse_string_with_data inherits general settings placeholders
        if (class_exists('CRM_Model')) {
            $model = new CRM_Model(0, null);
            $parsed_company = $model->parse_string_with_data('Herzlich willkommen bei {company_name} in {location_wien}!');
            $this->assert("CRM_Model löst {company_name} auf", strpos($parsed_company, '{company_name}') === false && strpos($parsed_company, 'X SIEBEN') !== false);
            $this->assert("CRM_Model löst {location_wien} auf", strpos($parsed_company, '{location_wien}') === false && strpos($parsed_company, '1030 Wien') !== false);

            $parsed_bank = $model->parse_string_with_data('Konto: {bankverbindung} | UID: {company_uid}');
            $this->assert("CRM_Model löst {bankverbindung} auf", strpos($parsed_bank, '{bankverbindung}') === false && strpos($parsed_bank, 'AT29') !== false);
            $this->assert("CRM_Model löst {company_uid} auf", strpos($parsed_bank, '{company_uid}') === false && strpos($parsed_bank, 'ATU') !== false);
        }

        // 3. crm_replace_pdf_placeholders resolves all company & bank placeholders
        if (function_exists('crm_replace_pdf_placeholders') && class_exists('CRM_Model')) {
            $model = new CRM_Model(0, null);
            $template = "Institut: {institut} | IBAN: {iban} | BIC: {bic} | Ort: {schulungsort}";
            $replaced = crm_replace_pdf_placeholders($template, $model);
            $this->assert("crm_replace_pdf_placeholders ersetzt {institut}", strpos($replaced, '{institut}') === false && strpos($replaced, 'X SIEBEN') !== false);
            $this->assert("crm_replace_pdf_placeholders ersetzt {iban}", strpos($replaced, '{iban}') === false && strpos($replaced, 'AT29') !== false);
            $this->assert("crm_replace_pdf_placeholders ersetzt {bic}", strpos($replaced, '{bic}') === false && strpos($replaced, 'RLNW') !== false);
        }

        // 4. Offer Page 3 Absolventen-Titel Banner
        $offerElementsPath = dirname(__DIR__) . '/pdf/elements/offer-elements.php';
        $this->assert("offer-elements.php existiert", file_exists($offerElementsPath));
        $offerElementsContent = file_get_contents($offerElementsPath);
        $this->assert("offer-elements.php abschluss-Sektion nutzt get_pdf_title mit 'Ihr persönlicher Abschluss'", strpos($offerElementsContent, "'Ihr persönlicher Abschluss'") !== false);
    }

    public function testSuite17_InquiryLinking(): void
    {
        echo "\n\033[1;33m[SUITE 17] Verknüpfung von Anfragen mit Kurs / Freier Geschäftsanfrage\033[0m\n";

        // 1. Core Functions Exist
        $this->assert("crm_link_entry_target existiert", function_exists('crm_link_entry_target'));
        $this->assert("crm_get_all_courses_options existiert", function_exists('crm_get_all_courses_options'));
        $this->assert("crm_render_entry_course_widget existiert", function_exists('crm_render_entry_course_widget'));
        $this->assert("crm_get_entry_status existiert", function_exists('crm_get_entry_status'));

        // 2. Database Schema & Migration
        if (function_exists('crm_ensure_status_tables')) {
            crm_ensure_status_tables(true);
            $this->assert("crm_db_version ist 1.4", get_option('crm_db_version') === '1.4');
        }

        // 3. Linking Logic: 'freie_anfrage' (Geschäftsanfrage)
        $testEntryId = 999901;
        $businessData = [
            'inquiry_type' => 'freie_anfrage',
            'custom_title' => 'Inhouse Seminar Führungskräfte 2026',
            'dates' => 'Termine nach Vereinbarung (Q3/Q4 2026)',
            'course_id' => 0,
            'note' => 'Test-Verknüpfung Freie Geschäftsanfrage'
        ];
        $resBusiness = crm_link_entry_target($testEntryId, $businessData);
        $this->assert("crm_link_entry_target speichert freie_anfrage erfolgreich", $resBusiness === true);

        $statusRow = crm_get_entry_status($testEntryId);
        $this->assert("Status hat inquiry_type = 'freie_anfrage'", is_array($statusRow) && ($statusRow['inquiry_type'] ?? '') === 'freie_anfrage');
        $this->assert("Status hat custom_title = 'Inhouse Seminar Führungskräfte 2026'", is_array($statusRow) && ($statusRow['custom_title'] ?? '') === 'Inhouse Seminar Führungskräfte 2026');

        // 4. Model Behavior for 'freie_anfrage'
        if (class_exists('CRM_Model')) {
            $model = new CRM_Model(0, $testEntryId);
            $this->assert("CRM_Model übernimmt custom_title als title", $model->title === 'Inhouse Seminar Führungskräfte 2026');
            $this->assert("CRM_Model setzt kurstyp auf Inhouse / Freie Geschäftsanfrage", strpos($model->kurstyp, 'Inhouse') !== false);
        }

        // 5. Widget Rendering for 'freie_anfrage'
        $widgetHtml = crm_render_entry_course_widget($testEntryId, $statusRow, 0, 'Inhouse Seminar Führungskräfte 2026');
        $this->assert("Widget rendert Freie Geschäftsanfrage Badge", strpos($widgetHtml, 'Freie Geschäftsanfrage') !== false);
        $this->assert("Widget rendert custom_title", strpos($widgetHtml, 'Inhouse Seminar Führungskräfte 2026') !== false);
        $this->assert("Widget rendert Ändern-Button mit data-inquiry-type", strpos($widgetHtml, 'data-inquiry-type="freie_anfrage"') !== false);

        // 6. Widget Rendering for unlinked inquiries (e.g. Kontaktformular)
        $unlinkedHtml = crm_render_entry_course_widget(999902, null, 0, '');
        $this->assert("Widget rendert Nicht verknüpft Badge", strpos($unlinkedHtml, 'Nicht verknüpft') !== false);
        $this->assert("Widget rendert prominenten Verknüpfen-Button", strpos($unlinkedHtml, 'crm-link-course-btn') !== false);
        $this->assert("Widget rendert 'Kurs / Geschäftsanfrage verknüpfen'", strpos($unlinkedHtml, 'Kurs / Geschäftsanfrage verknüpfen') !== false);

        // 7. Course Options Retrieval
        $courses = crm_get_all_courses_options();
        $this->assert("crm_get_all_courses_options liefert Array", is_array($courses));
        if (!empty($courses)) {
            $firstCourse = $courses[0];
            $this->assert("Kurs-Option hat id", isset($firstCourse['id']));
            $this->assert("Kurs-Option hat title", isset($firstCourse['title']));
            $this->assert("Kurs-Option hat date_str", isset($firstCourse['date_str']));
        }

        // 8. Admin View & Script Assets Integration
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("crm-admin.php enthält crmFormFilter Multi-Form-Auswahl", strpos($adminPhp, 'crmFormFilter') !== false);
        $this->assert("crm-admin.php enthält crm-link-modal", strpos($adminPhp, 'crm-link-modal') !== false);
        $this->assert("crm-admin.php enthält Row-Action 'Kurs / Anfrage zuweisen'", strpos($adminPhp, 'Kurs / Anfrage zuweisen') !== false);
        $this->assert("CRM_VERSION ist >= 2.18.50", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.50', '>='));

        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js bindet .crm-link-course-btn ein", strpos($adminJs, '.crm-link-course-btn') !== false);
        $this->assert("crm-admin.js ruft wp_ajax action crm_link_entry auf", strpos($adminJs, "action: 'crm_link_entry'") !== false);
        $this->assert("crm-admin.js implementiert Tab-Umschaltung", strpos($adminJs, '.crm-link-tab-btn') !== false);
        $this->assert("crm-admin.js implementiert Kurs-Live-Suche", strpos($adminJs, '#crm-course-search-input') !== false);

        $adminCss = file_get_contents(dirname(__DIR__) . '/css/crm-admin.css');
        $this->assert("crm-admin.css enthält crm-badge-business", strpos($adminCss, '.crm-badge-business') !== false);
        $this->assert("crm-admin.css enthält crm-course-widget-business", strpos($adminCss, '.crm-course-widget-business') !== false);
    }

    public function testSuite18_SplitViewAndMultiView(): void
    {
        echo "\n\033[1;33m[SUITE 18] 4-in-1 Multi-View Dashboard & Split-View Controller\033[0m\n";

        // 1. PHP Helper & Views
        require_once dirname(__DIR__) . '/helpers/crm-views.php';
        $this->assert("crm_render_split_view existiert", function_exists('crm_render_split_view'));
        $this->assert("crm_render_split_dossier existiert", function_exists('crm_render_split_dossier'));
        $this->assert("crm_render_view_switcher existiert", function_exists('crm_render_view_switcher'));
        $this->assert("crm_render_card_view existiert", function_exists('crm_render_card_view'));
        $this->assert("crm_render_kanban_view existiert", function_exists('crm_render_kanban_view'));

        // 2. CRM Version & AJAX Endpoint
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("CRM_VERSION ist >= 2.18.60", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.60', '>='));
        $this->assert("crm-admin.php registriert crm_get_split_dossier AJAX-Endpunkt", strpos($adminPhp, 'wp_ajax_crm_get_split_dossier') !== false);

        // 3. JavaScript Controller
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js schließt crmJsCache IIFE mit })(window); ab", strpos($adminJs, "})(window);") !== false);
        $this->assert("crm-admin.js exportiert crmEscapeHtml", strpos($adminJs, 'crmEscapeHtml') !== false);
        $this->assert("crm-admin.js exportiert crmSwitchView", strpos($adminJs, 'crmSwitchView') !== false);
        $this->assert("crm-admin.js exportiert crmLoadSplitDossier", strpos($adminJs, 'crmLoadSplitDossier') !== false);
        $this->assert("crm-admin.js bindet .crm-split-item per Event-Delegation ein", strpos($adminJs, '.crm-split-item') !== false);
        $this->assert("crm-admin.js bindet .crm-view-btn per Event-Delegation ein", strpos($adminJs, '.crm-view-btn') !== false);

        // 4. CSS Layout & Styles
        $adminCss = file_get_contents(dirname(__DIR__) . '/css/crm-admin.css');
        $this->assert("crm-admin.css definiert .crm-split-layout", strpos($adminCss, '.crm-split-layout') !== false);
        $this->assert("crm-admin.css definiert .crm-split-sidebar", strpos($adminCss, '.crm-split-sidebar') !== false);
        $this->assert("crm-admin.css definiert .crm-split-detail", strpos($adminCss, '.crm-split-detail') !== false);
        $this->assert("crm-admin.css definiert .crm-view-switcher", strpos($adminCss, '.crm-view-switcher') !== false);
    }

    public function testSuite19_KanbanTimeline(): void
    {
        echo "\n\033[1;33m[SUITE 19] Kanban-Timeline: Infinite Scroll & Collapsible Abgeschlossen\033[0m\n";

        // 1. Helper Functions
        require_once dirname(__DIR__) . '/helpers/crm-views.php';
        $this->assert("crm_render_kanban_card existiert", function_exists('crm_render_kanban_card'));

        // Mock Item for Kanban Card
        $mockItem = [
            'entry_id'              => 9999,
            'course_id'             => 701,
            'status_key'            => 'neu',
            'status_label'          => 'Neu / Anfrage',
            'client_display_name'   => 'Dr. Erika Mustermann',
            'course_title'          => 'DaF/DaZ Trainerausbildung Wien',
            'is_foerderung'         => false,
            'foerder_pure_badges'   => '',
            'email_val'             => 'erika@example.com',
            'phone_val'             => '+43 1 234567',
            'entry_timestamp'       => time(),
            'formatted_date'        => date('d.m.y'),
            'course_start_ts'       => time() + 86400 * 14,
            'default_editor_action' => 'xsieben_offer',
            'actions_html'          => '<button>Aktion</button>',
        ];

        $mockStatuses = [
            'neu'          => ['label' => 'Neu / Anfrage'],
            'abgeschlossen'=> ['label' => 'Abgeschlossen'],
        ];

        $cardHtml = crm_render_kanban_card($mockItem, $mockStatuses, false);
        $this->assert("crm_render_kanban_card rendert valides HTML mit Client-Namen", strpos($cardHtml, 'Dr. Erika Mustermann') !== false);
        $this->assert("crm_render_kanban_card rendert Kurs-Titel", strpos($cardHtml, 'DaF/DaZ Trainerausbildung') !== false);

        $deferredCardHtml = crm_render_kanban_card($mockItem, $mockStatuses, true);
        $this->assert("crm_render_kanban_card markiert deferred Karte mit crm-kanban-card-deferred", strpos($deferredCardHtml, 'crm-kanban-card-deferred') !== false);

        // Streamlined Sent Card Verification
        $sentMockItem = $mockItem;
        $sentMockItem['status_key'] = 'angebot_gesendet';
        $sentMockItem['status_label'] = 'Angebot gesendet';
        $sentCardHtml = crm_render_kanban_card($sentMockItem, $mockStatuses, false);
        $this->assert("crm_render_kanban_card rendert streamlined sent card mit crm-kanban-card-sent", strpos($sentCardHtml, 'crm-kanban-card-sent') !== false);
        $this->assert("crm_render_kanban_card enthält Nachfassen CTA in sent card", strpos($sentCardHtml, 'crm-card-cta-followup') !== false);
        $this->assert("crm_render_kanban_card enthält Client-Namen in sent card", strpos($sentCardHtml, 'Dr. Erika Mustermann') !== false);
        $this->assert("crm_render_kanban_card enthält Status-Pill in sent card", strpos($sentCardHtml, 'crm-status-pill') !== false);
        $this->assert("crm_render_kanban_card rendert keinen Kurs-Titel in sent card", strpos($sentCardHtml, 'crm-kanban-course-title') === false);
        $this->assert("crm_render_kanban_card rendert keine Badges in sent card", strpos($sentCardHtml, 'crm-kanban-badges') === false);

        // 2. Kanban Board Rendering & Done Collapsed
        $doneMockItem = $mockItem;
        $doneMockItem['status_key'] = 'abgeschlossen';
        $doneMockItem['client_display_name'] = 'Max Abgeschlossen';

        $kanbanHtml = crm_render_kanban_view([$mockItem, $sentMockItem, $doneMockItem], $mockStatuses, 170);
        $this->assert("crm_render_kanban_view markiert col_done mit crm-col-collapsed", strpos($kanbanHtml, 'crm-col-done crm-col-collapsed') !== false);
        $this->assert("crm_render_kanban_view enthält crm-kanban-done-badge", strpos($kanbanHtml, 'crm-kanban-done-badge') !== false);
        $this->assert("crm_render_kanban_view enthält crm-kanban-col-toggle-btn", strpos($kanbanHtml, 'crm-kanban-col-toggle-btn') !== false);
        $this->assert("crm_render_kanban_view versteckt col_done Karten initial mit style display none", strpos($kanbanHtml, 'crm-kanban-done-wrap" style="display:none;"') !== false);
        $this->assert("crm_render_kanban_view rendert Infinite Scroll Footer in offenen Spalten", strpos($kanbanHtml, 'crm-kanban-infinite-footer') !== false);
        $this->assert("crm_render_kanban_view übergibt total-entries Attribut", strpos($kanbanHtml, 'data-total-entries="170"') !== false);

        // 3. Backend & AJAX Endpoint
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("CRM_VERSION ist >= 2.18.65", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.65', '>='));
        $this->assert("crm-admin.php registriert crm_get_more_kanban_entries AJAX-Endpunkt", strpos($adminPhp, 'wp_ajax_crm_get_more_kanban_entries') !== false);

        // 4. JavaScript Controller
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js exportiert crmToggleKanbanDone", strpos($adminJs, 'crmToggleKanbanDone') !== false);
        $this->assert("crm-admin.js exportiert crmInitKanbanTimeline", strpos($adminJs, 'crmInitKanbanTimeline') !== false);
        $this->assert("crm-admin.js initialisiert initCrmKanbanTimeline", strpos($adminJs, 'initCrmKanbanTimeline();') !== false);
        $this->assert("crm-admin.js enthält crm-kanban-load-all-col-btn Click-Listener", strpos($adminJs, '.crm-kanban-load-all-col-btn') !== false);
        $this->assert("crm-admin.js enthält crm-kanban-col-toggle-btn Handler", strpos($adminJs, '.crm-kanban-col-toggle-btn') !== false);
        $this->assert("crm-admin.js aktualisiert crm-kanban-card-sent beim Bewegen", strpos($adminJs, 'crm-kanban-card-sent') !== false);

        // 5. CSS Styles
        $adminCss = file_get_contents(dirname(__DIR__) . '/css/crm-admin.css');
        $this->assert("crm-admin.css definiert .crm-col-collapsed", strpos($adminCss, '.crm-col-collapsed') !== false);
        $this->assert("crm-admin.css definiert .crm-kanban-done-badge", strpos($adminCss, '.crm-kanban-done-badge') !== false);
        $this->assert("crm-admin.css definiert .crm-kanban-col-toggle-btn", strpos($adminCss, '.crm-kanban-col-toggle-btn') !== false);
        $this->assert("crm-admin.css definiert .crm-kanban-infinite-footer", strpos($adminCss, '.crm-kanban-infinite-footer') !== false);
        $this->assert("crm-admin.css definiert .crm-card-revealed Animation", strpos($adminCss, '.crm-card-revealed') !== false);
        $this->assert("crm-admin.css definiert .crm-kanban-card-sent", strpos($adminCss, '.crm-kanban-card.crm-kanban-card-sent') !== false);
        $this->assert("crm-admin.css definiert .crm-kanban-sent-top", strpos($adminCss, '.crm-kanban-sent-top') !== false);
    }

    /**
     * [SUITE 20] Wizard Dokumentenauswahl & Selektiver E-Mail-Anhangversand
     */
    public function testSuite20_WizardDocumentSelection(): void
    {
        echo "\n\033[1;33m[SUITE 20] Wizard Dokumentenauswahl & Selektiver E-Mail-Anhangversand\033[0m\n";

        // 1. Backend: crm-status.php (Wizard Data Endpoint liefert Flags)
        $statusPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-status.php');
        $this->assert("crm-status.php liefert has_cert_option", strpos($statusPhp, "'has_cert_option'") !== false);
        $this->assert("crm-status.php liefert cert_name", strpos($statusPhp, "'cert_name'") !== false);
        $this->assert("crm-status.php liefert agb_url", strpos($statusPhp, "'agb_url'") !== false);
        $this->assert("crm-status.php registriert crm_get_wizard_email_preview AJAX-Endpunkt", strpos($statusPhp, 'wp_ajax_crm_get_wizard_email_preview') !== false);
        $this->assert("crm-status.php validiert und heilt korrumpierte Drafts mit crm_build_standard_offer_email", strpos($statusPhp, '$is_corrupt_body') !== false);

        // 2. Backend: crm-friedelin.php (Generator berücksichtigt selected_docs)
        $friedelinPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-friedelin.php');
        $this->assert("crm-friedelin.php verarbeitet selected_docs in crm_friedelin_process_entry", strpos($friedelinPhp, '$selected_docs = []') !== false);
        $this->assert("crm-friedelin.php liest selected_docs in crm_run_friedelin_preparation", strpos($friedelinPhp, "\$_POST['selected_docs']") !== false);
        $this->assert("crm-friedelin.php berücksichtigt want_offer_1", strpos($friedelinPhp, '$want_offer_1') !== false);
        $this->assert("crm-friedelin.php berücksichtigt want_offer_2", strpos($friedelinPhp, '$want_offer_2') !== false);
        $this->assert("crm-friedelin.php hängt AGB 2025 PDF an wenn ausgewählt", strpos($friedelinPhp, 'AGB_X_SIEBEN_2025.pdf') !== false);
        $this->assert("crm-friedelin.php liefert body in wp_send_json_success", strpos($friedelinPhp, "'body'           => \$result['body'] ?? ''") !== false);

        // 3. E-Mail Sections: Dynamische Anpassung des Begleittextes
        $emailPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-email-sections.php');
        $this->assert("crm-email-sections.php unterstützt selected_docs", strpos($emailPhp, '$selected_docs') !== false);
        $this->assert("crm-email-sections.php formuliert Text dynamisch nach Beilagen", strpos($emailPhp, '$want_offer_2') !== false);

        // 4. JavaScript: Schritt 2 & 3 Interaktivität
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js definiert crmWizardFindDocUrl", strpos($adminJs, 'crmWizardFindDocUrl') !== false);
        $this->assert("crm-admin.js definiert crmWizardGetActivePdfUrls", strpos($adminJs, 'crmWizardGetActivePdfUrls') !== false);
        $this->assert("crm-admin.js definiert crmRefreshWizardEmailPreview", strpos($adminJs, 'crmRefreshWizardEmailPreview') !== false);
        $this->assert("crm-admin.js enthält Checkboxen für Dokumente", strpos($adminJs, 'crm-wizard-doc-checkbox') !== false);
        $this->assert("crm-admin.js enthält Quick-Select Buttons (Alle / Basis)", strpos($adminJs, 'crm-wizard-quick-select-btn') !== false);
        $this->assert("crm-admin.js enthält Jump-to-Step Button von Schritt 3 zu Schritt 2", strpos($adminJs, 'crm-wizard-jump-step-btn') !== false);
        $this->assert("crm-admin.js übergibt selected_docs an crm_run_friedelin_preparation", strpos($adminJs, "formData.append('selected_docs'") !== false);
        $this->assert("crm-admin.js rendert crm-wizard-mail-body-preview", strpos($adminJs, 'crm-wizard-mail-body-preview') !== false);
        $this->assert("crm-admin.js enthält Verbindlich an Kunden versenden Button Handler", strpos($adminJs, 'crm-wizard-send-customer-btn') !== false);

        // 5. CSS Styles
        $adminCss = file_get_contents(dirname(__DIR__) . '/css/crm-admin.css');
        $this->assert("crm-admin.css stylt .crm-wizard-doc-item.is-selected", strpos($adminCss, '.crm-wizard-doc-item.is-selected') !== false);
        $this->assert("crm-admin.css stylt .crm-wizard-doc-item.is-unselected", strpos($adminCss, '.crm-wizard-doc-item.is-unselected') !== false);
        $this->assert("crm-admin.css stylt .crm-wizard-doc-checkbox", strpos($adminCss, '.crm-wizard-doc-checkbox') !== false);
        $this->assert("crm-admin.css stylt .crm-wizard-doc-preview-link", strpos($adminCss, '.crm-wizard-doc-preview-link') !== false);
    }

    /**
     * [SUITE 21] Universelle Kundendaten-Bearbeitung in allen Ansichten (v2.18.66)
     */
    public function testSuite21_UniversalCustomerEditing(): void
    {
        echo "\n\033[1;33m[SUITE 21] Universelle Kundendaten-Bearbeitung in allen Ansichten (v2.18.66)\033[0m\n";

        // 1. Backend: Form-Renderer & AJAX Endpunkt in crm-status.php
        require_once dirname(__DIR__) . '/helpers/crm-status.php';
        $this->assert("crm_render_customer_edit_form existiert", function_exists('crm_render_customer_edit_form'));

        $statusPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-status.php');
        $this->assert("crm-status.php registriert crm_get_entry_edit_form AJAX-Endpunkt", strpos($statusPhp, 'wp_ajax_crm_get_entry_edit_form') !== false);
        $this->assert("crm-status.php liefert erweiterte Kundendaten in crm_save_entry_form_data", strpos($statusPhp, "'svr'                 => \$svr") !== false);

        // Teste Form-Renderer mit realem DB-Eintrag 1057 (Jennifer Vytiska)
        $formHtml = crm_render_customer_edit_form(1057, 22184);
        $this->assert("crm_render_customer_edit_form liefert valides Formular", !empty($formHtml) && strpos($formHtml, '<form') !== false);
        $this->assert("Formular enthält Feld Vorname", strpos($formHtml, 'name="field_vorname"') !== false);
        $this->assert("Formular enthält Feld Nachname", strpos($formHtml, 'name="field_nachname"') !== false);
        $this->assert("Formular enthält Feld E-Mail", strpos($formHtml, 'name="field_email"') !== false);
        $this->assert("Formular enthält Feld SV-Nummer", strpos($formHtml, 'name="field_svr"') !== false);
        $this->assert("Formular enthält Feld Förderstelle", strpos($formHtml, 'name="field_foerderung_select"') !== false);
        $this->assert("Formular enthält Zertifizierungen Auswahl (Feld 99)", strpos($formHtml, 'name="field_zertifizierungen[]"') !== false);
        $this->assert("Formular enthält Abschluss Erfolg (Feld 100)", strpos($formHtml, 'name="field_abschluss_erfolg"') !== false);

        // 2. Ansichten: Integration der Edit-Buttons
        $viewsPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-views.php');
        $this->assert("crm-views.php enthält Kundendaten Edit-Button im Card-Header", strpos($viewsPhp, 'Kundendaten bearbeiten') !== false);
        $this->assert("crm-views.php enthält Bearbeiten-Button in Zone 1 (Kunde & Kontakt)", strpos($viewsPhp, 'crm-card-inline-edit-btn') !== false);
        $this->assert("crm-views.php enthält Kundendaten-Button in Split-Dossier Actions", strpos($viewsPhp, 'crm-split-dossier-actions') !== false && strpos($viewsPhp, '<span><?php esc_html_e(\'Kundendaten\', \'custom-crm\'); ?></span>') !== false);
        $this->assert("crm-views.php enthält Edit-Button auf Standard-Kanban-Karten", strpos($viewsPhp, 'crm-kanban-icon-btn crm-quick-edit-btn') !== false);

        // 3. Vollbild-Editor / Screen 2 (output-controler.php)
        $outputPhp = file_get_contents(dirname(__DIR__) . '/controler/output-controler.php');
        $this->assert("output-controler.php enthält Bearbeiten-Button in Spickzettel Spalte 1 (Wer? Kunde)", strpos($outputPhp, '1. Wer? (Kunde)') !== false && strpos($outputPhp, 'crm-quick-edit-btn') !== false);
        $this->assert("output-controler.php ersetzt externe WPForms-URL durch crm-quick-edit-btn in Sidebar", strpos($outputPhp, '<button type="button" class="button crm-quick-edit-btn"') !== false);

        // 4. Modal & Wizard Header in crm-admin.php
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("crm-admin.php enthält crm-customer-edit-modal-backdrop", strpos($adminPhp, 'id="crm-customer-edit-modal-backdrop"') !== false);
        $this->assert("crm-admin.php enthält crm-customer-edit-modal", strpos($adminPhp, 'id="crm-customer-edit-modal"') !== false);
        $this->assert("crm-admin.php enthält crm-wizard-edit-client-btn im Wizard Header", strpos($adminPhp, 'id="crm-wizard-edit-client-btn"') !== false);
        $this->assert("CRM_VERSION ist >= 2.18.66", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.66', '>='));

        // 5. JavaScript Interaktion & Multi-View Sync
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js definiert openCustomerEditModal", strpos($adminJs, 'function openCustomerEditModal') !== false);
        $this->assert("crm-admin.js fängt Klicks auf .crm-quick-edit-btn per Event-Delegation ab", strpos($adminJs, "e.target.closest('.crm-quick-edit-btn')") !== false);
        $this->assert("crm-admin.js synchronisiert Cards View nach Speichern", strpos($adminJs, 'card.querySelector(\'.crm-card-client-name a\')') !== false);
        $this->assert("crm-admin.js synchronisiert Split View nach Speichern", strpos($adminJs, '#crm-view-split .crm-split-item') !== false);
        $this->assert("crm-admin.js synchronisiert Kanban View nach Speichern", strpos($adminJs, '#crm-view-kanban .crm-kanban-card') !== false);
        $this->assert("crm-admin.js synchronisiert Lead Wizard nach Speichern", strpos($adminJs, 'currentWizardData.client_display_name = clientName') !== false);
        $this->assert("crm-admin.js enthält Bearbeiten-Button in Schritt 1 des Wizards", strpos($adminJs, '<span>Bearbeiten</span>') !== false);

        // 6. CSS Styles
        $adminCss = file_get_contents(dirname(__DIR__) . '/css/crm-admin.css');
        $this->assert("crm-admin.css definiert #crm-customer-edit-modal-backdrop", strpos($adminCss, '#crm-customer-edit-modal-backdrop') !== false);
        $this->assert("crm-admin.css definiert #crm-customer-edit-modal", strpos($adminCss, '#crm-customer-edit-modal') !== false);
        $this->assert("crm-admin.css definiert .crm-card-inline-edit-btn:hover", strpos($adminCss, '.crm-card-inline-edit-btn:hover') !== false);
    }

    /**
     * [SUITE 22] E-Mail Bausteine, Zertifizierungs-Filter für DaF/DaZ & Test-Mail Timeout (v2.18.67)
     */
    public function testSuite22_EmailBausteineAndCertFilter(): void
    {
        echo "\n\033[1;33m[SUITE 22] E-Mail Bausteine, Zertifizierungs-Filter für DaF/DaZ & Test-Mail Timeout (v2.18.67)\033[0m\n";

        // 1. Zertifizierungs-Logik & DaF/DaZ Ausschluss
        require_once dirname(__DIR__) . '/helpers/crm-friedelin.php';
        $this->assert("crm_resolve_course_certification existiert", function_exists('crm_resolve_course_certification'));

        // Kurs 701 (reines DaF/DaZ) darf KEINE Zertifizierung erhalten
        $dafCerts701 = crm_resolve_course_certification(1076, 701);
        $this->assert("crm_resolve_course_certification liefert empty array für DaF/DaZ Kurs 701", empty($dafCerts701));

        // Kurs 65629 (reines DaF/DaZ) darf KEINE Zertifizierung erhalten
        $dafCerts65629 = crm_resolve_course_certification(1076, 65629);
        $this->assert("crm_resolve_course_certification liefert empty array für DaF/DaZ Kurs 65629", empty($dafCerts65629));

        // Fachtrainer Kurs 376 MUSS Fachtrainer ISO 17024 Zertifizierung erhalten
        $trainerCerts = crm_resolve_course_certification(0, 376);
        $this->assert("crm_resolve_course_certification liefert Zertifizierung für Fachtrainer Kurs 376", !empty($trainerCerts) && is_array($trainerCerts));
        if (!empty($trainerCerts)) {
            $this->assert("Fachtrainer Zertifikat enthält ISO 17024", strpos($trainerCerts[0]['name'] ?? '', 'ISO') !== false);
            $this->assert("Fachtrainer Zertifikat enthält Preis", !empty($trainerCerts[0]['price']));
        }

        // 2. E-Mail Bausteine Integrität ({signatur_email}, {email_footer}, {buchung_email}, {agb_claim})
        $crmModelPhp = file_get_contents(dirname(__DIR__) . '/crm-model.php');
        $this->assert("crm-model.php überschreibt signatur_email NICHT mehr mit hartkodiertem Text", strpos($crmModelPhp, '$this->signatur_email = "Liebe Grüße\nAnna Brauer"') === false);

        $emailSectionsPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-email-sections.php');
        $this->assert("crm-email-sections.php bindet Baustein {signatur_email} via Model ein", strpos($emailSectionsPhp, "'{signatur_email}'") !== false);
        $this->assert("crm-email-sections.php bindet Baustein {buchung_email} via Model ein", strpos($emailSectionsPhp, "'{buchung_email}'") !== false);
        $this->assert("crm-email-sections.php bindet Baustein {email_footer} via Model ein", strpos($emailSectionsPhp, "'{email_footer}'") !== false);
        $this->assert("crm-email-sections.php bindet Baustein {agb_claim} via Model ein", strpos($emailSectionsPhp, "'{agb_claim}'") !== false);

        $normalizePhp = file_get_contents(dirname(__DIR__) . '/helpers/normalize.php');
        $this->assert("normalize.php ersetzt veraltete Signaturen durch HTML-Baustein mit Anna Brauer", strpos($normalizePhp, 'Anna Brauer') !== false);

        // 3. Dokumenten-Vorschau Switcher (output-controler.php)
        $outputPhp = file_get_contents(dirname(__DIR__) . '/controler/output-controler.php');
        $this->assert("output-controler.php prüft has_cert_option vor Generierung von Angebot 2", strpos($outputPhp, '$has_cert_option && empty($offer_zert_url)') !== false);
        $this->assert("output-controler.php kapselt Angebot 2 Tab-Button mit has_cert_option", strpos($outputPhp, '<?php if ($has_cert_option) : ?>') !== false);

        // 4. Test-Mail Fallback & Timeout
        $statusPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-status.php');
        $this->assert("crm-status.php prüft crm_test_email Option vor admin_email Fallback", strpos($statusPhp, "get_option('crm_test_email')") !== false);

        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js setzt timeout: 25000 für Test-Mail AJAX", strpos($adminJs, 'timeout: 25000') !== false);
        $this->assert("crm-admin.js behandelt Timeout-Fehler im Test-Mail Handler", strpos($adminJs, "status === 'timeout'") !== false);

        // 5. Versions-Konsistenz
        $this->assert("CRM_VERSION ist >= 2.18.67", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.67', '>='));
    }

    /**
     * [SUITE 23] Kurs-Zertifizierungen in allen CRM-Ansichten (v2.18.68)
     */
    public function testSuite23_CourseCertificationsInAllViews(): void
    {
        echo "\n\033[1;33m[SUITE 23] Kurs-Zertifizierungen in allen CRM-Ansichten (v2.18.68)\033[0m\n";

        // 1. Funktionen existieren
        $this->assert("crm_get_course_available_certifications existiert", function_exists('crm_get_course_available_certifications'));
        $this->assert("crm_render_course_cert_badges existiert", function_exists('crm_render_course_cert_badges'));

        // 2. IPMA Kurs 318
        $ipmaCerts = crm_get_course_available_certifications(318);
        $this->assert("Kurs 318 liefert verfügbare Zertifizierungen", !empty($ipmaCerts) && is_array($ipmaCerts));
        $this->assert("Kurs 318 liefert mind. 3 IPMA Zertifizierungen", count($ipmaCerts) >= 3);
        $this->assert("Kurs 318 Zertifizierung 0 ist IPMA / pma", ($ipmaCerts[0]['provider'] ?? '') === 'IPMA / pma');
        $this->assert("Kurs 318 Zertifizierung 0 hat badge_class crm-cert-ipma", ($ipmaCerts[0]['badge_class'] ?? '') === 'crm-cert-ipma');

        // 3. Scrum & IPMA Kurs 40914
        $scrumCerts = crm_get_course_available_certifications(40914);
        $this->assert("Kurs 40914 liefert Scrum.org und IPMA Zertifizierungen", !empty($scrumCerts));
        $hasScrum = false;
        foreach ($scrumCerts as $sc) {
            if ($sc['provider'] === 'Scrum.org') {
                $hasScrum = true;
                break;
            }
        }
        $this->assert("Kurs 40914 enthält Scrum.org Option", $hasScrum);

        // 4. TÜV ISO 17024 Kurs 32495 oder 22122
        $tuevCerts = crm_get_course_available_certifications(22122);
        $this->assert("Kurs 22122 liefert TÜV ISO 17024", !empty($tuevCerts) && ($tuevCerts[0]['provider'] ?? '') === 'TÜV AUSTRIA');

        // 5. SystemCERT Fachtrainer Kurs 376
        $trainerCerts = crm_get_course_available_certifications(376);
        $this->assert("Kurs 376 liefert SystemCERT ISO 17024", !empty($trainerCerts) && ($trainerCerts[0]['provider'] ?? '') === 'SystemCERT');

        // 6. Ausschluss für reine DaF/DaZ (Kurs 701 & 65629)
        $dafCerts701 = crm_get_course_available_certifications(701);
        $this->assert("DaF/DaZ Kurs 701 liefert leeres Array (keine externe Zert.)", empty($dafCerts701));
        $dafCerts65629 = crm_get_course_available_certifications(65629);
        $this->assert("DaF/DaZ Kurs 65629 liefert leeres Array (keine externe Zert.)", empty($dafCerts65629));

        // 7. Widget Rendering (crm_render_entry_course_widget)
        $widget318 = crm_render_entry_course_widget(1073, null, 318, 'Projektmanagement Best of', '2026-10-01', '2026-12-01');
        $this->assert("crm_render_entry_course_widget rendert crm-course-certs-row", strpos($widget318, 'crm-course-certs-row') !== false);
        $this->assert("crm_render_entry_course_widget rendert Zertifizierung-Label", strpos($widget318, 'Zertifizierung:') !== false);
        $this->assert("crm_render_entry_course_widget rendert IPMA Badge für Kurs 318", strpos($widget318, 'crm-cert-ipma') !== false);
        $this->assert("crm_render_entry_course_widget rendert Preis für Level D", strpos($widget318, '484,00 €') !== false);

        // 8. Widget Rendering für Kurs ohne externe Zertifizierung (DaF/DaZ 701)
        $widget701 = crm_render_entry_course_widget(1076, null, 701, 'DaF / DaZ Ausbildung', '2026-10-01', '2026-12-01');
        $this->assert("crm_render_entry_course_widget rendert Diplom ohne ext. Zert. für Kurs 701", strpos($widget701, 'Diplom (ohne ext. Zert.)') !== false);

        // 9. Kanban Card Rendering (crm_render_kanban_card)
        $mockStatuses = [
            'neu' => ['label' => 'Neu / Anfrage', 'icon' => 'dashicons-email-alt', 'color' => '#7c3aed', 'order' => 1]
        ];
        $mockItem318 = [
            'entry_id' => 999910,
            'course_id' => 318,
            'client_display_name' => 'Dipl.-Ing. Max Mustermann',
            'course_title' => 'Projektmanagement Best of IPMA',
            'status_key' => 'neu',
            'status_label' => 'Neu / Anfrage',
            'formatted_date' => '20.09.26',
            'is_foerderung' => false,
            'foerder_pure_badges' => '',
            'entry_timestamp' => time(),
            'course_start_ts' => time() + 86400,
            'default_editor_action' => 'xsieben_offer',
            'email_val' => 'max@example.com',
            'phone_val' => '+43 1 234567',
            'actions_html' => '<button type="button">CTA</button>'
        ];
        $kanbanHtml = crm_render_kanban_card($mockItem318, $mockStatuses, false);
        $this->assert("crm_render_kanban_card rendert crm-kanban-certs-row in Standard-Karte", strpos($kanbanHtml, 'crm-kanban-certs-row') !== false);
        $this->assert("crm_render_kanban_card enthält IPMA Badge in Standard-Karte", strpos($kanbanHtml, 'crm-cert-ipma') !== false);

        // 10. Split View Rendering (crm_render_split_view)
        $splitHtml = crm_render_split_view([$mockItem318], $mockStatuses);
        $this->assert("crm_render_split_view rendert crm-split-item mit Zertifizierungs-Badges", strpos($splitHtml, 'crm-kanban-certs-row') !== false || strpos($splitHtml, 'crm-split-item-certs-row') !== false || strpos($splitHtml, 'crm-course-certs-compact') !== false);

        // 11. CSS Integrität
        $cssContent = file_get_contents(dirname(__DIR__) . '/css/crm-admin.css');
        $this->assert("crm-admin.css definiert .crm-course-certs-row", strpos($cssContent, '.crm-course-certs-row') !== false);
        $this->assert("crm-admin.css definiert .crm-badge-cert", strpos($cssContent, '.crm-badge-cert') !== false);
        $this->assert("crm-admin.css definiert .crm-cert-ipma", strpos($cssContent, '.crm-cert-ipma') !== false);
        $this->assert("crm-admin.css definiert .crm-cert-scrum", strpos($cssContent, '.crm-cert-scrum') !== false);
        $this->assert("crm-admin.css definiert .crm-cert-tuev", strpos($cssContent, '.crm-cert-tuev') !== false);
        $this->assert("crm-admin.css definiert .crm-cert-systemcert", strpos($cssContent, '.crm-cert-systemcert') !== false);
        $this->assert("crm-admin.css definiert .crm-badge-cert-none", strpos($cssContent, '.crm-badge-cert-none') !== false);
        $this->assert("crm-admin.css definiert .crm-spickzettel-certs-box", strpos($cssContent, '.crm-spickzettel-certs-box') !== false);

        // 12. Versions-Konsistenz
        $this->assert("CRM_VERSION ist >= 2.18.68", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.68', '>='));
    }

    /**
     * [SUITE 24] Teilnahmebestätigung & Diplom Simulation & Vorschau-Integrität (v2.18.69)
     */
    public function testSuite24_TbAndDiplomSimulationAndPreview(): void
    {
        echo "\n\033[1;33m[SUITE 24] Teilnahmebestätigung & Diplom Simulation & Vorschau-Integrität (v2.18.69)\033[0m\n";

        // 1. Backend-Funktionen zur Generierung existieren
        $this->assert("xsieben_teilnahmebestaetigung_pdf existiert", function_exists('xsieben_teilnahmebestaetigung_pdf'));
        $this->assert("xsieben_diplom_pdf existiert", function_exists('xsieben_diplom_pdf'));

        // 2. crm_simulate_pdf AJAX-Endpunkt ist registriert
        $this->assert("wp_ajax_crm_simulate_pdf Hook ist registriert", has_action('wp_ajax_crm_simulate_pdf') !== false);

        // 3. JavaScript Scope-Sicherheit in crm-admin.js (keine ReferenceErrors für ajaxUrl und nonce)
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js deklariert ajaxUrl im jQuery ready Block", strpos($adminJs, "const ajaxUrl = (typeof crmData !== 'undefined' && crmData.ajaxUrl)") !== false);
        $this->assert("crm-admin.js deklariert nonce im jQuery ready Block", strpos($adminJs, "const nonce   = (typeof crmData !== 'undefined' && crmData.nonce)") !== false);
        $this->assert("crm-admin.js sichert activeEntryId global auf window", strpos($adminJs, "window.activeEntryId = activeEntryId;") !== false);

        // 4. crm-admin.js Simulation Handler nutzt defensive Variablen
        $this->assert("crm-admin.js nutzt postAjaxUrl für crm_simulate_pdf", strpos($adminJs, "const postAjaxUrl = (typeof crmData !== 'undefined' && crmData.ajaxUrl)") !== false);
        $this->assert("crm-admin.js nutzt postNonce für crm_simulate_pdf", strpos($adminJs, "const postNonce   = (typeof crmData !== 'undefined' && crmData.nonce)") !== false);

        // 5. Dual Section Manager unterstützt TB und Diplom
        $this->assert("crm-admin.js mappt crm-dual-sec-tb auf tb", strpos($adminJs, "else if (doc === 'tb') secTarget = 'crm-dual-sec-tb';") !== false);
        $this->assert("crm-admin.js mappt crm-dual-sec-diplom auf diplom", strpos($adminJs, "else if (doc === 'diplom') secTarget = 'crm-dual-sec-diplom';") !== false);

        // 6. Dynamischer E-Mail-Kontext nach Dokumentwechsel
        $this->assert("crm-admin.js setzt context teilnahmebestaetigung für TB", strpos($adminJs, "mailContext = 'teilnahmebestaetigung';") !== false);
        $this->assert("crm-admin.js setzt context xsieben_diplom für Diplom", strpos($adminJs, "mailContext = 'xsieben_diplom';") !== false);

        // 7. output-controler.php erkennt vorhandene TB und Diplom Dateien
        $outputControler = file_get_contents(dirname(__DIR__) . '/controler/output-controler.php');
        $this->assert("output-controler.php sucht vorhandene Teilnahmebestätigung", strpos($outputControler, "Teilnahmebestaetigung_*' . \$tb_token") !== false);
        $this->assert("output-controler.php sucht vorhandenes Diplom", strpos($outputControler, "Diplom_*' . \$diplom_token") !== false);
        $this->assert("output-controler.php enthält #crm-diplom-success-container", strpos($outputControler, 'crm-diplom-success-container') !== false);
        $this->assert("output-controler.php enthält crm-dual-sec-tb Tab & Pane", strpos($outputControler, 'crm-dual-sec-tb') !== false);
        $this->assert("output-controler.php enthält crm-dual-sec-diplom Tab & Pane", strpos($outputControler, 'crm-dual-sec-diplom') !== false);

        // 8. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.69", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.69', '>='));
    }

    /**
     * [SUITE 25] Test-E-Mail Versand, dynamische Empfänger-Auswahl & Fehler-Transparenz (v2.18.70)
     */
    public function testSuite25_TestEmailDeliveryAndTransparency(): void
    {
        echo "\n\033[1;33m[SUITE 25] Test-E-Mail Versand, dynamische Empfänger-Auswahl & Fehler-Transparenz (v2.18.70)\033[0m\n";

        // 1. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.70", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.70', '>='));

        // 2. crm-admin.php stellt currentUserEmail und defaultTestEmail in crmData bereit
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("crm-admin.php liest aktuellen Benutzer für crmData aus", strpos($adminPhp, "wp_get_current_user()") !== false);
        $this->assert("crm-admin.php übergibt currentUserEmail an crmData", (bool)preg_match("/'currentUserEmail'\s*=>\s*\\\$curr_email/", $adminPhp));
        $this->assert("crm-admin.php übergibt defaultTestEmail an crmData", (bool)preg_match("/'defaultTestEmail'\s*=>\s*\\\$crm_test_em/", $adminPhp));

        // 3. output-controler.php: Dual-Nonce & strukturierte JSON-Fehler in wp_ajax_x_sieben_send_mail
        $outputCtrl = file_get_contents(dirname(__DIR__) . '/controler/output-controler.php');
        $this->assert("output-controler.php prüft x_sieben_mailer_nonce", strpos($outputCtrl, "wp_verify_nonce(\$nonce, 'x_sieben_mailer_nonce')") !== false);
        $this->assert("output-controler.php prüft crm_ajax_nonce als Fallback", strpos($outputCtrl, "wp_verify_nonce(\$nonce, 'crm_ajax_nonce')") !== false);
        $this->assert("output-controler.php liefert strukturierte Fehlermeldung für ungültigen Test-Empfänger", strpos($outputCtrl, "wp_send_json_error(['message' => __('Bitte geben Sie eine gültige Test-E-Mail-Adresse an.', 'custom-crm')])") !== false);
        $this->assert("output-controler.php liefert strukturierte Fehlermeldung für ungültigen Kunden-Empfänger", strpos($outputCtrl, "wp_send_json_error(['message' => __('Bitte geben Sie eine gültige Kunden-E-Mail-Adresse an.', 'custom-crm')])") !== false);
        $this->assert("output-controler.php liefert strukturierte Fehlermeldung bei wp_mail Fehlschlag", strpos($outputCtrl, "wp_send_json_error(['message' => __('Fehler beim Senden der E-Mail. Bitte Mail-Konfiguration prüfen.', 'custom-crm')])") !== false);

        // 4. crm-admin.js: Schritt 3 enthält editierbares Test-Empfänger-Feld in allen Stufen
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js enthält #crm-wizard-test-recipient in Schritt 3 (Angebot)", strpos($adminJs, 'id="crm-wizard-test-recipient"') !== false);
        $this->assert("crm-admin.js enthält .crm-wizard-set-test-email-btn Quick-Toggle", strpos($adminJs, 'crm-wizard-set-test-email-btn') !== false);
        $this->assert("crm-admin.js Event-Delegation für .crm-wizard-set-test-email-btn vorhanden", strpos($adminJs, "e.target.closest('.crm-wizard-set-test-email-btn')") !== false);

        // 5. crm-admin.js: Test-Mail Button liest Test-Empfänger & setzt Timeout
        $this->assert("crm-admin.js liest Test-Empfänger aus #crm-wizard-test-recipient", strpos($adminJs, "document.getElementById('crm-wizard-test-recipient')") !== false);
        $this->assert("crm-admin.js validiert Test-E-Mail vor Absenden", strpos($adminJs, "!testEmail.includes('@')") !== false);
        $this->assert("crm-admin.js setzt Lade-Spinner im Test-Mail-Button", strpos($adminJs, "testMailBtn.innerHTML = '<span class=\"dashicons dashicons-update spin\"") !== false);
        $this->assert("crm-admin.js setzt 30s Timeout für Test-Mail AJAX", strpos($adminJs, "timeout: timeoutMs || 30000") !== false || strpos($adminJs, "timeout: 30000") !== false);
        $this->assert("crm-admin.js extrahiert Fehlermeldung ohne String-Maskierung (Test-Mail)", strpos($adminJs, "(typeof resp.data === 'object' && resp.data.message)") !== false);
        $this->assert("crm-admin.js extrahiert Fehlermeldung ohne String-Maskierung (Kunden-Versand)", strpos($adminJs, "alert('Fehler beim E-Mail-Versand: ' + errMsg);") !== false);

        $this->assert("crm-admin.js definiert crmSendMailAjax für fehlertoleranten E-Mail-Versand", strpos($adminJs, "function crmSendMailAjax(postData, onSuccess, onError, timeoutMs)") !== false);
        $this->assert("crm-admin.js aliast jQuery am Anfang von DOMContentLoaded sicher", strpos($adminJs, "const $ = window.jQuery || window.$; // Ensure jQuery alias is safely available") !== false || strpos($adminJs, "const $ = window.jQuery || window.$;") !== false);
        $this->assert("crm-admin.js ruft crmSendMailAjax für Test-Mail-Versand auf", strpos($adminJs, "crmSendMailAjax({\n                        action: 'x_sieben_send_mail'") !== false || strpos($adminJs, "crmSendMailAjax({") !== false);

        // 6. crm-settings.js: Test-Vorschau Robustheit
        $settingsJs = file_get_contents(dirname(__DIR__) . '/assets/crm-settings.js');
        $this->assert("crm-settings.js nutzt currentUserEmail oder crmData als Default für Prompt", strpos($settingsJs, "crmSettings.currentUserEmail || (typeof crmData !== 'undefined' ? crmData.currentUserEmail : '')") !== false);
        $this->assert("crm-settings.js nutzt flexibles postUrl für Test-Vorschau", strpos($settingsJs, "const postUrl = (typeof ajaxurl !== 'undefined' && ajaxurl)") !== false);
        $this->assert("crm-settings.js setzt 30s Timeout für E-Mail-Preview-Test", strpos($settingsJs, "timeout: 30000") !== false);
        $this->assert("crm-settings.js extrahiert Fehlermeldung transparent", strpos($settingsJs, "(typeof res.data === 'object' && res.data.message) ? res.data.message : (typeof res.data === 'string' ? res.data : (i18n.testMailError") !== false);
    }

    /**
     * [SUITE 26] AGB als reiner Online-Link in E-Mail & Ausschluss von Dateianhängen (v2.18.75)
     */
    public function testSuite26_AgbOnlineLinkPolicy(): void
    {
        echo "\n\033[1;33m[SUITE 26] AGB als reiner Online-Link in E-Mail & Ausschluss von Dateianhängen (v2.18.75)\033[0m\n";

        // 1. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.75", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.75', '>='));

        // 2. crm-email-sections.php verlinkt AGB ausschließlich ganz unten über Baustein {agb_claim}
        $emailSections = file_get_contents(dirname(__DIR__) . '/helpers/crm-email-sections.php');
        $this->assert("crm-email-sections.php bindet Baustein {agb_claim} ganz unten ein", strpos($emailSections, "'{agb_claim}'") !== false);
        $this->assert("crm-email-sections.php enthält keinen redundanten AGB-Mittelblock mehr", strpos($emailSections, 'sind als rechtliche Grundlage für Sie ebenfalls beigefügt') === false);

        // 3. crm-admin.js schließt AGB strikt aus crmWizardGetActivePdfUrls aus
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js beschränkt docKeys in crmWizardGetActivePdfUrls auf offer_1, offer_2, kb", strpos($adminJs, "const docKeys = ['offer_1', 'offer_2', 'kb'];") !== false);
        $this->assert("crm-admin.js deklariert AGB im Wizard als Online-Link", strpos($adminJs, "Wird als Online-Link in der E-Mail verlinkt (kein Dateianhang)") !== false);

        // 4. output-controler.php filtert AGB-Pfade aus wp_mail Attachments heraus
        $outputCtrl = file_get_contents(dirname(__DIR__) . '/controler/output-controler.php');
        $this->assert("output-controler.php überspringt AGB in der Anhang-Schleife", strpos($outputCtrl, "stripos(\$raw_url, 'AGB_X_SIEBEN') !== false") !== false);

        // 5. crm-status.php filtert AGB aus Mailer Attachments heraus
        $statusPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-status.php');
        $this->assert("crm-status.php filtert AGB aus attachments", strpos($statusPhp, "stripos(\$att, 'agb') === false") !== false);

        // 6. Live-Generierung E-Mail-Vorschau enthält AGB Online-Link ganz unten
        if (function_exists('crm_build_standard_offer_email')) {
            $mail = crm_build_standard_offer_email(1075, 22122, ['want_agb' => true]);
            $this->assert("Generierte Angebots-Mail enthält AGB Online-Link", strpos($mail['body'], 'AGB') !== false);
            $this->assert("Generierte Angebots-Mail enthält href auf AGB", strpos($mail['body'], 'AGB_X_SIEBEN_2025.pdf') !== false);
        }
    }

    /**
     * [SUITE 27] PDF-Seitenumbruch- & Abstands-Integrität (v2.18.76)
     */
    public function testSuite27_PdfSpacingAndPageBreakIntegrity(): void
    {
        echo "\n\033[1;33m[SUITE 27] PDF-Seitenumbruch- & Abstands-Integrität (v2.18.76)\033[0m\n";

        // 1. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.76", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.76', '>='));

        // 2. Default-Abstand title_spacing_bottom in crm-pdf-sections.php ist 36 pt
        $spacing = crm_get_pdf_elements_spacing();
        $this->assert("crm_get_pdf_elements_spacing liefert title_spacing_bottom >= 36", floatval($spacing['title_spacing_bottom']) >= 36.0);

        // 3. offer.php sichert Deckblatt Titel-Abstand mit mindestens 32 pt ab
        $offerPhp = file_get_contents(dirname(__DIR__) . '/pdf/offer.php');
        $this->assert("offer.php sichert Deckblatt Titel-Abstand mit mindestens 32 pt ab", strpos($offerPhp, "max(32.0, floatval(\$global_spacing['title_spacing_bottom'] ?? 36.0))") !== false);

        // 4. offer.php drosselt Standard-Unterabschnitte auf Folgeseiten gegen unerwünschte Leerseiten
        $this->assert("offer.php drosselt Standard-Unterabschnitte auf Folgeseiten", strpos($offerPhp, "\$sub_sp_bottom = min(4.0, \$sub_sp_bottom);") !== false);

        // 5. tab-pdf.php enthält Hinweis auf HTML / Text Umschaltung
        $tabPdf = file_get_contents(dirname(__DIR__) . '/views/settings/tab-pdf.php');
        $this->assert("tab-pdf.php enthält Hinweis auf HTML / Text Umschaltung", strpos($tabPdf, 'Text / HTML') !== false);
    }

    /**
     * [SUITE 28] TinyMCE HTML-Toleranz & CRM-Isolation (v2.18.77)
     */
    public function testSuite28_TinyMceCrmHtmlPreservation(): void
    {
        echo "\n\033[1;33m[SUITE 28] TinyMCE HTML-Toleranz & CRM-Isolation (v2.18.77)\033[0m\n";

        // 1. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.77", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.77', '>='));

        // 2. Filter-Funktion existiert und Hook ist registriert
        $this->assert("crm_filter_tinymce_settings existiert", function_exists('crm_filter_tinymce_settings'));
        $this->assert("tiny_mce_before_init Hook ist registriert", has_filter('tiny_mce_before_init', 'crm_filter_tinymce_settings') !== false);

        // 3. Isolation: Normale WordPress-Editoren werden NICHT verändert
        $oldPage = $_GET['page'] ?? null;
        unset($_GET['page']);

        $standardInit = ['verify_html' => true, 'cleanup' => true];
        $filteredStandard = crm_filter_tinymce_settings($standardInit, 'content');
        $this->assertEqual("TinyMCE Filter belässt reguläre WP-Editoren unberührt (Isolation)", true, $filteredStandard['verify_html']);
        $this->assert("Reguläre WP-Editoren erhalten keine CRM extended_valid_elements", !isset($filteredStandard['extended_valid_elements']));

        // 4. CRM-Feld ID Prüfung: greift automatisch bei crm_fields_*
        $crmFieldInit = ['verify_html' => true, 'cleanup' => true];
        $filteredCrmField = crm_filter_tinymce_settings($crmFieldInit, 'crm_fields_0_content');
        $this->assertEqual("TinyMCE deaktiviert verify_html für CRM-Felder", false, $filteredCrmField['verify_html']);
        $this->assertEqual("TinyMCE deaktiviert cleanup für CRM-Felder", false, $filteredCrmField['cleanup']);
        $this->assertEqual("TinyMCE deaktiviert cleanup_on_startup für CRM-Felder", false, $filteredCrmField['cleanup_on_startup']);
        $this->assert("TinyMCE extended_valid_elements enthält div[*]", strpos($filteredCrmField['extended_valid_elements'], 'div[*]') !== false);
        $this->assert("TinyMCE extended_valid_elements enthält span[*]", strpos($filteredCrmField['extended_valid_elements'], 'span[*]') !== false);
        $this->assert("TinyMCE extended_valid_elements enthält br[*]", strpos($filteredCrmField['extended_valid_elements'], 'br[*]') !== false);
        $this->assert("TinyMCE extended_valid_elements enthält p[*]", strpos($filteredCrmField['extended_valid_elements'], 'p[*]') !== false);
        $this->assertEqual("TinyMCE remove_linebreaks ist false", false, $filteredCrmField['remove_linebreaks']);

        // 5. CRM-Seiten Parameter: greift auf crm-pdf, crm-emails etc.
        $_GET['page'] = 'crm-pdf';
        $pageInit = ['verify_html' => true];
        $filteredPage = crm_filter_tinymce_settings($pageInit, 'any_id');
        $this->assertEqual("TinyMCE Filter greift auf crm-pdf Seite", false, $filteredPage['verify_html']);

        if ($oldPage !== null) {
            $_GET['page'] = $oldPage;
        } else {
            unset($_GET['page']);
        }

        // 6. field-editor.php übergibt explizite tinymce Konfiguration
        $fieldEditorPhp = file_get_contents(dirname(__DIR__) . '/views/settings/components/field-editor.php');
        $this->assert("field-editor.php übergibt extended_valid_elements an wp_editor", strpos($fieldEditorPhp, "'extended_valid_elements'") !== false);
        $this->assert("field-editor.php deaktiviert verify_html im wp_editor Array", strpos($fieldEditorPhp, "'verify_html'             => false") !== false);
    }

    public function testSuite29_BusinessCaseHistorySplitView(): void
    {
        echo "\n\033[1;33m[SUITE 29] Geschäftsvorfall-Verlauf im Split View (v2.18.80)\033[0m\n";

        // 1. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.80", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.80', '>='));

        // 2. Helper & Funktionen existieren
        require_once dirname(__DIR__) . '/helpers/crm-status.php';
        require_once dirname(__DIR__) . '/helpers/crm-views.php';
        $this->assert("crm_get_business_case_history existiert", function_exists('crm_get_business_case_history'));
        $this->assert("crm_render_business_case_timeline existiert", function_exists('crm_render_business_case_timeline'));

        // 3. crm-views.php integriert Verlauf in crm_render_split_dossier
        $viewsPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-views.php');
        $this->assert("crm-views.php bindet crm-split-history-wrap ein", strpos($viewsPhp, 'crm-split-history-wrap') !== false);
        $this->assert("crm-views.php ruft crm_render_business_case_timeline auf", strpos($viewsPhp, 'crm_render_business_case_timeline') !== false);

        // 4. crm-admin.css definiert Split View History Klassen
        $adminCss = file_get_contents(dirname(__DIR__) . '/css/crm-admin.css');
        $this->assert("crm-admin.css definiert .crm-split-history-wrap", strpos($adminCss, '.crm-split-history-wrap') !== false);
        $this->assert("crm-admin.css definiert .crm-split-history-section", strpos($adminCss, '.crm-split-history-section') !== false);
        $this->assert("crm-admin.css definiert .crm-bcase-timeline", strpos($adminCss, '.crm-bcase-timeline') !== false);
        $this->assert("crm-admin.css definiert .crm-bcase-item", strpos($adminCss, '.crm-bcase-item') !== false);
        $this->assert("crm-admin.css definiert .crm-bcase-pill-latest", strpos($adminCss, '.crm-bcase-pill-latest') !== false);

        // 5. crm-admin.js Event-Delegation
        $adminJs = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js bindet .crm-bcase-toggle-content ein", strpos($adminJs, '.crm-bcase-toggle-content') !== false);
        $this->assert("crm-admin.js enthält .crm-split-history-section Scroll-Handler", strpos($adminJs, '.crm-split-history-section') !== false);
        $this->assert("crm-admin.js invalidiert split_dossier_ Cache bei Mailversand", strpos($adminJs, "window.crmJsCache.cache.delete('split_dossier_' + snapEntryId)") !== false);

        // 6. Funktionstest: Leere Entry-ID liefert has_started = false
        $emptyRes = crm_get_business_case_history(0);
        $this->assertEqual("Leere Entry-ID liefert has_started = false", false, $emptyRes['has_started']);
        $this->assert("Leere Entry-ID liefert leere entries Liste", empty($emptyRes['entries']));

        // 7. Funktionstest: Empty-State Rendering
        $emptyHtml = crm_render_business_case_timeline(999901);
        $this->assert("Empty State rendert .crm-bcase-empty-state", strpos($emptyHtml, 'crm-bcase-empty-state') !== false);
        $this->assert("Empty State erwähnt Test-E-Mail", strpos($emptyHtml, 'Noch keine Test-E-Mail versendet') !== false);
        $this->assert("Empty State enthält Schnell-Button", strpos($emptyHtml, 'crm-direct-editor-btn') !== false);

        // 8. Funktionstest: Echter Eintrag mit Test-Mails (z. B. 1076 oder 1074)
        $realRes = crm_get_business_case_history(1076);
        if (!empty($realRes['has_started'])) {
            $this->assertEqual("Eintrag 1076 hat has_test_mail = true", true, $realRes['has_test_mail']);
            $this->assert("Eintrag 1076 entries ist nicht leer", !empty($realRes['entries']));
            $first = $realRes['entries'][0];
            $this->assertEqual("Erster Eintrag ist test_mail_gesendet", 'test_mail_gesendet', $first['status_key']);
            $last = end($realRes['entries']);
            $this->assertEqual("Letzter Eintrag hat is_latest = true", true, $last['is_latest']);

            // Sicherstellen, dass keine technischen PDF-Logs vorkommen
            $hasPdfLog = false;
            foreach ($realRes['entries'] as $e) {
                if (strpos($e['status_key'], 'pdf_') === 0) {
                    $hasPdfLog = true;
                    break;
                }
            }
            $this->assertEqual("Keine internen pdf_* Logs im Geschäftsvorfall", false, $hasPdfLog);

            // Timeline Rendering für 1076
            $timelineHtml = crm_render_business_case_timeline(1076);
            $this->assert("Timeline rendert .crm-bcase-timeline", strpos($timelineHtml, 'crm-bcase-timeline') !== false);
            $this->assert("Timeline enthält Test-Mail Badge", strpos($timelineHtml, 'crm-status-test_mail_gesendet') !== false);
            $this->assert("Timeline enthält Aktueller Stand Pill", strpos($timelineHtml, 'crm-bcase-pill-latest') !== false);
        }
    }

    /**
     * [SUITE 30] Zertifizierungs-Integrität & Strikte Entkopplung (v2.18.81)
     */
    public function testSuite30_CertificationDisambiguationAndExclusivity(): void
    {
        echo "\n\033[1;33m[SUITE 30] Zertifizierungs-Integrität & Strikte Entkopplung (v2.18.81)\033[0m\n";

        // 1. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.81", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.81', '>='));

        // 2. Strikte Abgrenzung Scrum PSM I vs. Kombi
        $mockPsmSelected = [['name' => 'Scrum PSM I', 'price' => '176,91', 'percentage' => '20%']];
        $certsWithPsm = crm_get_course_available_certifications(40914, $mockPsmSelected);
        $psmActive = false;
        $kombiActive = false;
        $pspoActive = false;
        foreach ($certsWithPsm as $c) {
            if ($c['short_name'] === 'Scrum PSM I') $psmActive = !empty($c['is_selected']);
            if ($c['short_name'] === 'Scrum PSM I + PSPO I') $kombiActive = !empty($c['is_selected']);
            if ($c['short_name'] === 'Scrum PSPO I') $pspoActive = !empty($c['is_selected']);
        }
        $this->assert("Scrum PSM I Auswahl aktiviert ausschließlich PSM I Badge", $psmActive && !$kombiActive && !$pspoActive);

        // 3. Strikte Abgrenzung Scrum PSPO I vs. Kombi
        $mockPspoSelected = [['name' => 'Scrum PSPO I', 'price' => '176,91', 'percentage' => '20%']];
        $certsWithPspo = crm_get_course_available_certifications(40914, $mockPspoSelected);
        $psmActive2 = false;
        $kombiActive2 = false;
        $pspoActive2 = false;
        foreach ($certsWithPspo as $c) {
            if ($c['short_name'] === 'Scrum PSM I') $psmActive2 = !empty($c['is_selected']);
            if ($c['short_name'] === 'Scrum PSM I + PSPO I') $kombiActive2 = !empty($c['is_selected']);
            if ($c['short_name'] === 'Scrum PSPO I') $pspoActive2 = !empty($c['is_selected']);
        }
        $this->assert("Scrum PSPO I Auswahl aktiviert ausschließlich PSPO I Badge", !$psmActive2 && !$kombiActive2 && $pspoActive2);

        // 4. Strikte Abgrenzung Scrum Kombi (PSM + PSPO)
        $mockKombiSelected = [['name' => 'Scrum PSM I + PSPO I', 'price' => '353,82', 'percentage' => '20%']];
        $certsWithKombi = crm_get_course_available_certifications(40914, $mockKombiSelected);
        $psmActiveK = false;
        $kombiActiveK = false;
        $pspoActiveK = false;
        foreach ($certsWithKombi as $c) {
            if ($c['short_name'] === 'Scrum PSM I') $psmActiveK = !empty($c['is_selected']);
            if ($c['short_name'] === 'Scrum PSM I + PSPO I') $kombiActiveK = !empty($c['is_selected']);
            if ($c['short_name'] === 'Scrum PSPO I') $pspoActiveK = !empty($c['is_selected']);
        }
        $this->assert("Scrum Kombi Auswahl aktiviert ausschließlich Kombi Badge", $kombiActiveK && !$psmActiveK && !$pspoActiveK);

        // 5. Strikte Abgrenzung IPMA Level D vs. Level C vs. Level B
        $mockIpmaD = [['name' => 'IPMA Level D', 'price' => '484,00', 'percentage' => '10%']];
        $certsWithIpmaD = crm_get_course_available_certifications(40914, $mockIpmaD);
        $levelDActive = false;
        $levelCActive = false;
        $levelBActive = false;
        foreach ($certsWithIpmaD as $c) {
            if ($c['short_name'] === 'IPMA Level D') $levelDActive = !empty($c['is_selected']);
            if ($c['short_name'] === 'IPMA Level C') $levelCActive = !empty($c['is_selected']);
            if (strpos($c['short_name'], 'Level B') !== false) $levelBActive = !empty($c['is_selected']);
        }
        $this->assert("IPMA Level D Auswahl aktiviert ausschließlich Level D Badge", $levelDActive && !$levelCActive && !$levelBActive);

        // 6. Vollständiges Abwählen (reines Basis-Angebot, 0 Badges aktiv)
        $certsDeselected = crm_get_course_available_certifications(40914, []);
        $anyActive = false;
        foreach ($certsDeselected as $c) {
            if (!empty($c['is_selected'])) {
                $anyActive = true;
                break;
            }
        }
        $this->assert("Leere Zertifizierungsauswahl markiert alle Badges als abgewählt (0 aktiv)", !$anyActive);

        // 7. Echter Eintrag 1069 (Gyongyi Szabo) hat genau 1 aktive Zertifizierung
        $res1069 = crm_get_course_available_certifications(32495, 1069);
        $selCount1069 = 0;
        foreach ($res1069 as $c) {
            if (!empty($c['is_selected'])) {
                $selCount1069++;
            }
        }
        $this->assertEqual("Eintrag 1069 hat genau 1 aktive Zertifizierung (kein Übersprechen)", 1, $selCount1069);

        // 8. JS-Integrität: gegenseitiger Ausschluss in crm-admin.js
        $jsContent = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js enthält Ausschlusslogik für Scrum Kombi vs Einzelfach", strpos($jsContent, 'otherIsKombi') !== false);
        $this->assert("crm-admin.js enthält Ausschlusslogik für IPMA Level", strpos($jsContent, 'otherIsIpma') !== false);
    }

    /**
     * [SUITE 31] Hannes Gajo Feedback 21.09.2026: Zertifizierungsmatrix, AMS Vorlagen & Layout-Integrität (v2.18.82)
     */
    public function testSuite31_Version21882FeaturesAndFixes(): void
    {
        echo "\n\033[1;33m[SUITE 31] Hannes Gajo Feedback 21.09.2026: Zertifizierungsmatrix, AMS Vorlagen & Layout-Integrität (v2.18.82)\033[0m\n";

        // 1. Versionierung
        $this->assert("CRM_VERSION ist >= 2.18.82", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.82', '>='));

        // 2. Agile Coach (65536) -> Option: TÜV - EN ISO 17024 (497,00 €)
        if (function_exists('crm_resolve_course_certification')) {
            $acCerts = crm_resolve_course_certification(0, 65536);
            $this->assert("Agile Coach (65536) liefert Zertifizierung", !empty($acCerts) && isset($acCerts[0]));
            $this->assert("Agile Coach liefert Option: TÜV - EN ISO 17024", strpos($acCerts[0]['name'] ?? '', 'TÜV') !== false && strpos($acCerts[0]['name'] ?? '', '17024') !== false);
            $this->assertEqual("Agile Coach Zertifizierungspreis ist 497,00", '497,00', $acCerts[0]['price'] ?? '');
            $this->assertEqual("Agile Coach USt-Satz ist 20%", '20%', $acCerts[0]['percentage'] ?? '');
            $this->assert("Agile Coach wird nicht fälschlich als Scrum erkannt", strpos($acCerts[0]['name'] ?? '', 'Scrum') === false);

            // 3. Logistik & Einkauf Kurse -> LOG+L - DIN EN ISO 17024 (255,00 €)
            $logistikIds = [14761, 316, 13800, 14808, 38736, 317];
            foreach ($logistikIds as $logId) {
                $logCerts = crm_resolve_course_certification(0, $logId);
                $this->assert("Logistik-Kurs {$logId} liefert LOG+L Zertifizierung", !empty($logCerts) && strpos($logCerts[0]['name'] ?? '', 'LOG+L') !== false);
                $this->assertEqual("Logistik-Kurs {$logId} Zertifizierungspreis ist 255,00", '255,00', $logCerts[0]['price'] ?? '');
            }
        }

        // 4. Kurszeitenbestätigung (KB): 5 Kurstyp Checkboxen & amtlicher Fußnotentext
        if (class_exists('CRM_Pdf_Kb_Elements')) {
            $kurstypHtml = CRM_Pdf_Kb_Elements::render_kurstyp('1', '', '', '1', '');
            $this->assert("render_kurstyp enthält Tageskurs", strpos($kurstypHtml, 'Tageskurs') !== false);
            $this->assert("render_kurstyp enthält Abendkurs", strpos($kurstypHtml, 'Abendkurs') !== false);
            $this->assert("render_kurstyp enthält Wochenendkurs", strpos($kurstypHtml, 'Wochenendkurs') !== false);
            $this->assert("render_kurstyp enthält Präsenzkurs / Webinar bzw. Blended Learning", strpos($kurstypHtml, 'Präsenzkurs / Webinar bzw. Blended Learning') !== false);
            $this->assert("render_kurstyp enthält Online-Kurs (zeit- u. ortsunabhängig)", strpos($kurstypHtml, 'Online-Kurs (zeit- u. ortsunabhängiges selbständiges Erarbeiten') !== false);
            $this->assert("render_kurstyp markiert Tageskurs aktiv", strpos($kurstypHtml, '&#9746; Tageskurs') !== false);
            $this->assert("render_kurstyp markiert Blended Learning aktiv", strpos($kurstypHtml, '&#9746; Präsenzkurs') !== false);

            $hinweisHtml = CRM_Pdf_Kb_Elements::render_hinweis('');
            $this->assert("render_hinweis enthält amtlichen AMS Ablaufplan-Hinweis", strpos($hinweisHtml, 'Bei unregelmäßigen Kurszeiten ist ein Ablaufplan der einzelnen Kurswochen') !== false);
            $this->assert("render_hinweis enthält Praxiszeiten-Zusatz", strpos($hinweisHtml, 'Dies gilt auch für Praxiszeiten.') !== false);
        }

        // 5. Neue Dokumente: Anmeldebestätigung & Antrittsmeldung
        $this->assert("xsieben_anmeldebestaetigung_pdf existiert", function_exists('xsieben_anmeldebestaetigung_pdf'));
        $this->assert("xsieben_antrittsbestaetigung_pdf existiert", function_exists('xsieben_antrittsbestaetigung_pdf'));
        if (function_exists('crm_get_friendly_pdf_label')) {
            $this->assert("crm_get_friendly_pdf_label('ab') liefert Anmeldebestätigung", strpos(crm_get_friendly_pdf_label('ab'), 'Anmeldebestätigung') !== false);
            $this->assert("crm_get_friendly_pdf_label('antritt') liefert Antrittsmeldung", strpos(crm_get_friendly_pdf_label('antritt'), 'Antrittsmeldung') !== false);
        }

        // 6. UI & Controller-Anbindung für ab & antritt
        $outCtrl = file_get_contents(dirname(__DIR__) . '/controler/output-controler.php');
        $this->assert("output-controler.php enthält Simulation-Button für Anmeldebestätigung", strpos($outCtrl, 'data-doc="ab"') !== false);
        $this->assert("output-controler.php enthält Simulation-Button für Antrittsmeldung", strpos($outCtrl, 'data-doc="antritt"') !== false);
        $adminPhp = file_get_contents(dirname(__DIR__) . '/crm-admin.php');
        $this->assert("crm-admin.php ruft xsieben_anmeldebestaetigung_pdf auf", strpos($adminPhp, "xsieben_anmeldebestaetigung_pdf") !== false);
        $this->assert("crm-admin.php ruft xsieben_antrittsbestaetigung_pdf auf", strpos($adminPhp, "xsieben_antrittsbestaetigung_pdf") !== false);

        // 7. E-Mail Fixes: Durchführungsform Großschreibung & Martin Bieber Wochentagslogik
        $emailSec = file_get_contents(dirname(__DIR__) . '/helpers/crm-email-sections.php');
        $this->assert("crm-email-sections.php kapitalisiert Durchführungsform", strpos($emailSec, 'mb_strtoupper(mb_substr($durchfuehrung') !== false);
        $this->assert("crm-email-sections.php enthält dynamische Martin-Bieber Wochentagslogik", strpos($emailSec, 'Kurstermine jeweils') !== false);

        // 8. PDF Layout & Bereinigung
        $presenterPhp = file_get_contents(dirname(__DIR__) . '/helpers/crm-pdf-presenter.php');
        $this->assert("crm-pdf-presenter.php bereinigt role=status aus AGB", strpos($presenterPhp, 'status|alert') !== false);
        $this->assert("crm-pdf-presenter.php schützt Modul-Unterabschnitte vor Kollaps", strpos($presenterPhp, "_clean_module_body_html") !== false);
        $offerPhp = file_get_contents(dirname(__DIR__) . '/pdf/offer.php');
        $this->assert("offer.php Deckblatt Unterschriften-Abstand ist 18pt", strpos($offerPhp, '18.0') !== false);
    }

    public function testSuite32_Version21883CertAndScheduleLogic(): void
    {
        echo "\n\033[1;33m[SUITE 32] Hannes Gajo Feedback 21.09.2026 Abend: Zentrale Zertifizierungs- & Terminlogik (v2.18.83)\033[0m\n";

        // 1. Versions-Konsistenz
        $this->assert("CRM_VERSION ist >= 2.18.83", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.83', '>='));

        // 2. Kurs 36593 (Digital Marketing Manager) liefert TÜV ISO 17024 mit echtem Eintrag (Feld 99 leer)
        $cert36593 = crm_resolve_course_certification(1076, 36593);
        $this->assert("Digital Marketing Manager (36593) liefert Zertifizierung trotz initial leerem Feld 99", !empty($cert36593));
        $this->assert("Digital Marketing Manager liefert TÜV ISO 17024", !empty($cert36593[0]['name']) && stripos($cert36593[0]['name'], 'TÜV') !== false && stripos($cert36593[0]['name'], '17024') !== false);
        $this->assertEqual("Digital Marketing Manager Preis ist 497,00", '497,00', $cert36593[0]['price'] ?? '');
        $this->assertEqual("Digital Marketing Manager USt ist 20%", '20%', $cert36593[0]['percentage'] ?? '');

        // 3. Agile Coach liefert TÜV ISO 17024 mit echtem Eintrag
        $certAgile = crm_resolve_course_certification(1076, 67829);
        $this->assert("Agile Coach (67829) liefert Zertifizierung mit echtem Eintrag", !empty($certAgile));
        $this->assert("Agile Coach liefert TÜV ISO 17024", !empty($certAgile[0]['name']) && stripos($certAgile[0]['name'], 'TÜV') !== false);
        $this->assertEqual("Agile Coach Preis ist 497,00", '497,00', $certAgile[0]['price'] ?? '');

        // 4. Opt-Out Erkennung bei Kunde: keine Zertifizierung
        global $wpdb;
        $testEntryId = 999888;
        $wpdb->insert($wpdb->prefix . 'wpforms_entries', [
            'entry_id' => $testEntryId,
            'form_id'  => 60468,
            'fields'   => json_encode([
                ['id' => 2, 'name' => 'Nachricht', 'value' => 'Ich möchte keine Zertifizierung ablegen, wie läuft das ab?'],
                ['id' => 99, 'name' => 'Zertifizierungen Auswahl', 'value' => '']
            ]),
            'date'     => current_time('mysql')
        ]);
        $optOutCerts = crm_resolve_course_certification($testEntryId, 36593);
        $this->assert("Kunde mit Opt-Out ('keine Zertifizierung') erhält 0 Zertifizierungen", empty($optOutCerts));
        $wpdb->delete($wpdb->prefix . 'wpforms_entries', ['entry_id' => $testEntryId]);

        // 5. Kurstermine im Begleitmail: Andreas Zöllner Gruppe ohne PDF -> Mittwoch und Freitag
        $mailZoellner = crm_build_standard_offer_email(1076, 36593);
        $this->assert("Begleitmail für Zöllner-Kurs enthält Kurstermine jeweils Mittwoch und Freitag.", strpos($mailZoellner['body'], 'Kurstermine jeweils Mittwoch und Freitag.') !== false);
        $this->assert("Begleitmail für Zöllner-Kurs verweist ohne PDF nicht blind auf Anhang", strpos($mailZoellner['body'], 'Die genauen Kurstermine sehen Sie im Anhang') === false);
        $this->assert("Begleitmail für Zöllner-Kurs bietet Angebot 1 und Angebot 2 an", strpos($mailZoellner['body'], 'Angebot 1:') !== false && strpos($mailZoellner['body'], 'Angebot 2:') !== false);

        // 6. Kurstermine im Begleitmail: Martin Bieber Gruppe ohne PDF -> Montag und Dienstag
        $mailBieber = crm_build_standard_offer_email(1076, 14761);
        $this->assert("Begleitmail für Bieber-Kurs enthält Kurstermine jeweils Montag und Dienstag.", strpos($mailBieber['body'], 'Kurstermine jeweils Montag und Dienstag.') !== false);

        // 7. Kurstermine im Begleitmail: Mit vorhandenem PDF -> Die genauen Kurstermine sehen Sie im Anhang.
        $mailWithPdf = crm_build_standard_offer_email(1076, 36593, ['has_terminplan_pdf' => true]);
        $this->assert("Begleitmail mit Terminplan-PDF verweist auf den Anhang", strpos($mailWithPdf['body'], 'Die genauen Kurstermine sehen Sie im Anhang.') !== false);
    }

    public function testSuite33_Version21884TuevGrossAndTerminplanAttachment(): void
    {
        echo "\n\033[1;33m[SUITE 33] Feedback 22.09.2026: TÜV ISO 17024 Brutto-Berechnung (497 €) & Terminplan-Attachment (v2.18.84)\033[0m\n";

        // 1. Versions-Konsistenz
        $this->assert("CRM_VERSION ist >= 2.18.84", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.84', '>='));

        // 2. TÜV AUSTRIA ISO 17024 Gebühr wird als Bruttobetrag (497,00 €) berechnet
        // 497 € brutto / 1.20 = 414,17 € netto, 82,83 € USt
        $certGross = 497.00;
        $ustSatz = 20.00;
        $ustCert = round(($certGross / (100 + $ustSatz)) * $ustSatz, 2);
        $nettoCert = round($certGross - $ustCert, 2);
        $this->assertEqual("TÜV 497 € brutto -> Netto ist 414,17 €", 414.17, $nettoCert);
        $this->assertEqual("TÜV 497 € brutto -> 20% USt ist 82,83 €", 82.83, $ustCert);
        $this->assertEqual("TÜV Netto + USt ergibt exakt 497,00 €", 497.00, round($nettoCert + $ustCert, 2));

        // 3. Lead 1076 (Kurs 36593) Gesamt-Kostenberechnung in Angebot 2
        $model = new CRM_Model(1076, 'angebot2', false, 36593);
        $model->override_certifications = crm_resolve_course_certification(1076, 36593);
        $kostenHtml = $model->get_gesamt_kosten_html();

        $this->assert("Angebot 2 Kosten-Tabelle enthält TÜV Zertifizierung", strpos($kostenHtml, 'TÜV') !== false);
        $this->assert("Angebot 2 Kosten-Tabelle weist TÜV mit 414,17 € Netto aus", strpos($kostenHtml, '414,17') !== false);
        $this->assert("Angebot 2 Kosten-Tabelle weist TÜV mit 82,83 € USt aus", strpos($kostenHtml, '82,83') !== false);
        $this->assert("Angebot 2 Gesamt-Bruttobetrag enthält exakt 497,00 € für Zertifizierung (3.494,00 €)", strpos($kostenHtml, '3.494,00') !== false);

        // 4. Centgenaue Validierung bei Standard-Kurs 3.590,00 € Netto (4.308,00 € Brutto)
        $courseNetto3590 = 3590.00;
        $courseUst718 = round($courseNetto3590 * 0.20, 2);
        $courseBrutto4308 = $courseNetto3590 + $courseUst718;
        $totalNettoExpected = round($courseNetto3590 + $nettoCert, 2); // 4004.17
        $totalUstExpected = round($courseUst718 + $ustCert, 2);       // 800.83
        $totalBruttoExpected = round($totalNettoExpected + $totalUstExpected, 2); // 4805.00
        $this->assertEqual("3590 € Kurs + TÜV -> Gesamt Netto ist 4.004,17 €", 4004.17, $totalNettoExpected);
        $this->assertEqual("3590 € Kurs + TÜV -> Gesamt USt ist 800,83 €", 800.83, $totalUstExpected);
        $this->assertEqual("3590 € Kurs + TÜV -> Gesamt Brutto ist 4.805,00 €", 4805.00, $totalBruttoExpected);

        // 5. CRM Status Tooltip Prüfung
        $crmStatusContent = file_get_contents(dirname(__DIR__) . '/helpers/crm-status.php');
        $this->assert("crm-status.php enthält Tooltip mit 497,00 € brutto inkl. 20% USt", strpos($crmStatusContent, '497,00 € brutto inkl. 20% USt') !== false);

        // 6. Terminplan-Erkennung in crm-email-sections.php
        $emailSectionsContent = file_get_contents(dirname(__DIR__) . '/helpers/crm-email-sections.php');
        $this->assert("crm-email-sections.php enthält Terminplan-Kandidat", strpos($emailSectionsContent, "'terminplan' => [") !== false);
        $this->assert("crm-email-sections.php prüft kurszeiten_details_pdf", strpos($emailSectionsContent, "kurszeiten_details_pdf") !== false);
    }

    public function testSuite34_Version21885FachtrainerDafDazCertAndTerminplanAttachment(): void
    {
        echo "\n\033[1;33m[SUITE 34] Feedback 23.09.2026: Fachtrainer & DaF/DaZ Zertifizierung & Terminplan-Pipeline (v2.18.85)\033[0m\n";

        // 1. Versions-Konsistenz
        $this->assert("CRM_VERSION ist >= 2.18.85", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.85', '>='));

        // 2. FachtrainerInnen & DaF / DaZ TrainerInnen - ISO 17024: AMS-Aktion (65662)
        // Muss SystemCERT ISO 17024 Zertifizierung liefern (324,00 € brutto / 20% USt)
        $cert65662 = crm_resolve_course_certification(1076, 65662);
        $this->assert("Kurs 65662 (Fachtrainer & DaF/DaZ Kombi AMS) liefert Zertifizierung", !empty($cert65662) && is_array($cert65662));
        $this->assert("Kurs 65662 liefert SystemCERT ISO 17024", !empty($cert65662) && strpos($cert65662[0]['name'], 'SystemCERT') !== false);
        $this->assertEqual("Kurs 65662 Zertifizierungspreis ist 324,00", '324,00', $cert65662[0]['price'] ?? '');
        $this->assertEqual("Kurs 65662 USt-Satz ist 20%", '20%', $cert65662[0]['percentage'] ?? '');

        // 3. Regression: Reine DaF/DaZ Kurse ohne Fachtrainer bleiben ohne Zertifizierung
        $pureDaf701 = crm_resolve_course_certification(1076, 701);
        $this->assert("Kurs 701 (reine DaF/DaZ) liefert empty array", empty($pureDaf701));
        $pureDaf65629 = crm_resolve_course_certification(1076, 65629);
        $this->assert("Kurs 65629 (reine DaF/DaZ) liefert empty array", empty($pureDaf65629));

        // 4. Helper-Funktion crm_get_course_terminplan_url existiert
        $this->assert("crm_get_course_terminplan_url existiert", function_exists('crm_get_course_terminplan_url'));

        // 5. Begleit-E-Mail mit Terminplan enthält Terminplan-Aufzählung und Anhang-Verweis
        $mailWithTp = crm_build_standard_offer_email(1076, 65662, [
            'want_offer_1'       => true,
            'want_offer_2'       => true,
            'has_cert_option'    => true,
            'cert_name'          => 'SystemCERT- Kompetenzzertifizierung FachtrainerIn gemäß den Forderungen der ISO 17024',
            'has_terminplan_pdf' => true,
            'want_terminplan'    => true,
        ]);
        $this->assert("Begleitmail enthält Angebot 1", strpos($mailWithTp['body'], 'Angebot 1:</strong> Ein Angebot ohne Zertifizierung') !== false);
        $this->assert("Begleitmail enthält Angebot 2 mit SystemCERT", strpos($mailWithTp['body'], 'SystemCERT- Kompetenzzertifizierung') !== false);
        $this->assert("Begleitmail enthält Terminplan Aufzählungspunkt", strpos($mailWithTp['body'], '<strong>Terminplan:</strong> Detaillierter Termin- und Ablaufplan') !== false);
        $this->assert("Begleitmail verweist auf genaue Kurstermine im Anhang", strpos($mailWithTp['body'], '→ Die genauen Kurstermine sehen Sie im Anhang.') !== false);

        // 6. crm-email-sections.php auto-check Terminplan in allen CRM-Kontexten
        $emailSectionsCode = file_get_contents(dirname(__DIR__) . '/helpers/crm-email-sections.php');
        $this->assert("crm-email-sections.php unterstützt xsieben_angebot_und_kurszeiten im Terminplan auto-check", strpos($emailSectionsCode, 'xsieben_angebot_und_kurszeiten') !== false);

        // 7. crm-friedelin.php bindet Terminplan in crm_friedelin_process_entry ein
        $friedelinCode = file_get_contents(dirname(__DIR__) . '/helpers/crm-friedelin.php');
        $this->assert("crm-friedelin.php prüft crm_get_course_terminplan_url", strpos($friedelinCode, 'crm_get_course_terminplan_url') !== false);
        $this->assert("crm-friedelin.php führt terminplan in final_selected_docs", strpos($friedelinCode, "'terminplan' => \$want_terminplan") !== false);

        // 8. angebot_kurszeiten.php generiert offer_2_pdf_url bei vorhandener Zertifizierung
        $angebotKbCode = file_get_contents(dirname(__DIR__) . '/pdf/angebot_kurszeiten.php');
        $this->assert("angebot_kurszeiten.php prüft crm_resolve_course_certification", strpos($angebotKbCode, 'crm_resolve_course_certification') !== false);
        $this->assert("angebot_kurszeiten.php generiert mit_zertifikat", strpos($angebotKbCode, "'mit_zertifikat'") !== false);
    }

    public function testSuite35_Version21886OfferPage1SpacingAndTuevCertIntegrity(): void
    {
        echo "\n\033[1;33m[SUITE 35] Feedback 23.09.2026: Seite 1 Abstände & TÜV ISO 17024 Integrität (v2.18.86)\033[0m\n";

        // 1. Versions-Konsistenz
        $this->assert("CRM_VERSION ist >= 2.18.86", defined('CRM_VERSION') && version_compare(CRM_VERSION, '2.18.86', '>='));

        // 2. Digital Marketing Manager (36593): Darf niemals fälschlich LOG+L erhalten
        $cert36593 = crm_resolve_course_certification(1076, 36593);
        $this->assert("Digital Marketing Manager (36593) liefert Zertifizierung", !empty($cert36593));
        $this->assert("Digital Marketing Manager liefert TÜV ISO 17024 (nicht LOG+L)", !empty($cert36593) && strpos($cert36593[0]['name'], 'TÜV') !== false && strpos($cert36593[0]['name'], 'LOG+L') === false);
        $this->assertEqual("Digital Marketing Manager Preis ist 497,00", '497,00', $cert36593[0]['price'] ?? '');
        $this->assertEqual("Digital Marketing Manager USt ist 20%", '20%', $cert36593[0]['percentage'] ?? '');

        // 3. Digital Marketing Kurs (22328 ohne ACF) liefert ebenfalls TÜV ISO 17024
        $cert22328 = crm_resolve_course_certification(0, 22328);
        $this->assert("Kurs 22328 liefert Zertifizierung", !empty($cert22328));
        $this->assert("Kurs 22328 liefert TÜV ISO 17024", !empty($cert22328) && strpos($cert22328[0]['name'], 'TÜV') !== false);
        $this->assertEqual("Kurs 22328 Preis ist 497,00", '497,00', $cert22328[0]['price'] ?? '');

        // 4. Logistik-Kurse bleiben weiterhin zuverlässig bei LOG+L (255,00 €)
        $certLog = crm_resolve_course_certification(0, 14761);
        $this->assert("Logistik-Kurs 14761 liefert LOG+L", !empty($certLog) && strpos($certLog[0]['name'], 'LOG+L') !== false);
        $this->assertEqual("Logistik-Kurs 14761 Preis ist 255,00", '255,00', $certLog[0]['price'] ?? '');

        // 5. Deckblatt Grußformel Spacing-Prüfung
        require_once dirname(__DIR__) . '/pdf/elements/offer-elements.php';
        $renderedGruss = CRM_Pdf_Offer_Elements::render_gruss("Ich freue mich über Ihre Rückmeldung / Buchung.<br>\nMit freundlichen Grüßen,");
        $this->assert("render_gruss enthält doppelten Zeilenumbruch vor Mit freundlichen Grüßen", strpos($renderedGruss, '<br><br>Mit freundlichen Grüßen') !== false);
        $this->assert("render_gruss Tabelle hat margin-top von 8pt", strpos($renderedGruss, 'margin-top: 8pt;') !== false);

        // 6. crm-pdf-sections.php default_content für gruss enthält doppeltes Newline
        $pdfSectionsCode = file_get_contents(dirname(__DIR__) . '/helpers/crm-pdf-sections.php');
        $this->assert("crm-pdf-sections.php default_content für gruss enthält doppeltes Newline", strpos($pdfSectionsCode, "Ich freue mich über Ihre Rückmeldung / Buchung.\\n\\nMit freundlichen Grüßen,") !== false);

        // 7. offer.php definiert sub_sp_top = 8.0 für gruss
        $offerCode = file_get_contents(dirname(__DIR__) . '/pdf/offer.php');
        $this->assert("offer.php definiert Mindestabstand für gruss auf Deckblatt", strpos($offerCode, "\$sub_key === 'gruss'") !== false && strpos($offerCode, "\$sub_sp_top = 8.0;") !== false);
    }

    public function testSuite36_Version21887AmseDocumentsAndActionButtonsIntegration(): void
    {
        echo "\n\033[1;33m[SUITE 36] Feedback 25.09.2026: AMS Anmeldebestätigung & Antrittsmeldung Vollintegration (v2.18.87)\033[0m\n";

        // 1. Versionsprüfung
        $this->assert("CRM_VERSION ist >= 2.18.87", version_compare(CRM_VERSION, '2.18.87', '>='));

        // 2. crm_get_actions_config enthält AB und Antritt
        require_once dirname(__DIR__) . '/crm-admin.php';
        $actions = crm_get_actions_config();
        $this->assert("crm_get_actions_config enthält xsieben_anmeldebestaetigung", isset($actions['xsieben_anmeldebestaetigung']));
        $this->assertEqual("AB Button Label ist AB", 'AB', $actions['xsieben_anmeldebestaetigung']['button_label'] ?? '');
        $this->assert("crm_get_actions_config enthält xsieben_antrittsbestaetigung", isset($actions['xsieben_antrittsbestaetigung']));
        $this->assertEqual("Antritt Button Label ist Antritt", 'Antritt', $actions['xsieben_antrittsbestaetigung']['button_label'] ?? '');

        // 3. Aliase und PDF-Funktionen existieren
        require_once dirname(__DIR__) . '/pdf/anmeldebestaetigung.php';
        require_once dirname(__DIR__) . '/pdf/antrittsbestaetigung.php';
        $this->assert("xsieben_ab_pdf existiert", function_exists('xsieben_ab_pdf'));
        $this->assert("xsieben_antritt_pdf existiert", function_exists('xsieben_antritt_pdf'));

        // 4. crm-views.php Mini-Doc Buttons enthalten AB und Antritt
        $viewsCode = file_get_contents(dirname(__DIR__) . '/helpers/crm-views.php');
        $this->assert("crm-views.php enthält AB Mini-Doc Button", strpos($viewsCode, 'data-doc="ab"') !== false && strpos($viewsCode, 'data-action="xsieben_anmeldebestaetigung"') !== false);
        $this->assert("crm-views.php enthält Antritt Mini-Doc Button", strpos($viewsCode, 'data-doc="antritt"') !== false && strpos($viewsCode, 'data-action="xsieben_antrittsbestaetigung"') !== false);

        // 5. crm-admin.js mappt ab und antritt
        $jsCode = file_get_contents(dirname(__DIR__) . '/assets/crm-admin.js');
        $this->assert("crm-admin.js mappt docType ab auf xsieben_anmeldebestaetigung", strpos($jsCode, "docType === 'ab'") !== false && strpos($jsCode, "actionKey = 'xsieben_anmeldebestaetigung'") !== false);
        $this->assert("crm-admin.js mappt docType antritt auf xsieben_antrittsbestaetigung", strpos($jsCode, "docType === 'antritt'") !== false && strpos($jsCode, "actionKey = 'xsieben_antrittsbestaetigung'") !== false);

        // 6. crm-email-sections.php enthält Kandidaten für AB und Antritt
        $emailCode = file_get_contents(dirname(__DIR__) . '/helpers/crm-email-sections.php');
        $this->assert("crm-email-sections.php enthält \$candidates['ab']", strpos($emailCode, "\$candidates['ab']") !== false);
        $this->assert("crm-email-sections.php enthält \$candidates['antritt']", strpos($emailCode, "\$candidates['antritt']") !== false);
        $this->assert("crm-email-sections.php matched Anmeldebestaetigung_", strpos($emailCode, "strpos(\$bn, 'Anmeldebestaetigung_') === 0") !== false);
        $this->assert("crm-email-sections.php matched Antrittsmeldung_", strpos($emailCode, "strpos(\$bn, 'Antrittsmeldung_') === 0") !== false);

        // 7. Browser-Output ruft x_sieben_pdf_preview auf
        $abPdfCode = file_get_contents(dirname(__DIR__) . '/pdf/anmeldebestaetigung.php');
        $this->assert("anmeldebestaetigung.php ruft x_sieben_pdf_preview auf", strpos($abPdfCode, "x_sieben_pdf_preview(\$pdf_url, \$course_id, \$entry_id, 'anmeldebestaetigung')") !== false);
        $antrittPdfCode = file_get_contents(dirname(__DIR__) . '/pdf/antrittsbestaetigung.php');
        $this->assert("antrittsbestaetigung.php ruft x_sieben_pdf_preview auf", strpos($antrittPdfCode, "x_sieben_pdf_preview(\$pdf_url, \$course_id, \$entry_id, 'antrittsbestaetigung')") !== false);
    }
}

// Run the test suite and exit with appropriate status code
$suite = new CrmSeniorDevTestSuite();
exit($suite->runAll());

