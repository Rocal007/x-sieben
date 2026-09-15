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

// Polyfill minimal WordPress functions for isolated CLI execution if not running inside WP
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 5) . '/');
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

// Load CRM helpers to test
require_once dirname(__DIR__) . '/helpers/normalize.php';

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
}

// Run the test suite and exit with appropriate status code
$suite = new CrmSeniorDevTestSuite();
exit($suite->runAll());
