<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function xsieben_mailer()
{

    require_once ABSPATH . WPINC . "/PHPMailer/PHPMailer.php";
    require_once ABSPATH . WPINC . "/PHPMailer/Exception.php";
    require_once ABSPATH . WPINC . "/PHPMailer/SMTP.php";

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    // Angenommen: Diese Variablen sind bereits validiert und kommen z. B. aus einem Model
    global $title, $anrede, $vorname, $nachname, $email, $kurstyp, $permalink;
    global $trainer, $startdatum, $enddatum, $termine_link, $ams;
    global $pdfName, $pdfName_1, $pdfName_2, $pdfName_R;

    $message = <<<HTML
<html>
<head><title>Angebot - {$title}</title></head>
<body>
  <table style="width:800px; font-size:14px; border:0; border-collapse:collapse;">
    <tr>
      <td colspan="4">
        {$anrede} {$vorname} {$nachname}, {$email}<br><br>
        willkommen in der X SIEBEN-Community und Danke für Ihr Interesse am {$kurstyp} 
        <a href="{$permalink}">{$title}</a>.<br><br>
        Anbei <strong>Ihre Eckdaten zur Schulung</strong>.<br><br>
        <strong>Vortragende</strong>:<br><br>
        {$trainer}<br><br>
        <strong>Zeitraum</strong>:<br><br>
        Vom <strong>{$startdatum}</strong> bis einschließlich <strong>{$enddatum}</strong>.<br><br>
        √ Ihr persönliches <strong>Angebot</strong> und die Kurszeiten zur Ausbildung finden sich 
        <strong>im Anhang</strong>.<br>
        {$termine_link}<br>
        √ Die Ausbildung findet via <strong>Live-Online-Event bzw. in 1030 Wien</strong> statt.<br><br>
        ...
        <!-- Der Rest deines HTML-Contents kommt hier rein -->
        ...
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    try {
        $mail->setFrom('r.sauer007@gmail.com', 'X-Sieben - Wirtschaftstraining');
        $mail->addAddress('r.sauer007@gmail.com');
        $mail->addAddress('r.sauer007@gmail.com', 'Roland');

        $mail->isHTML(true);
        $mail->Subject = $title;
        $mail->Body = $message;
        $mail->AltBody = 'Body in plain text for non-HTML mail clients';

        // Anhänge
        $upload_dir = get_template_directory() . '/angebote/';

        if ($ams === 'true') {
            $mail->addAttachment("{$upload_dir}{$pdfName}", "Kurszeitenbestaetigung_{$title}_{$vorname}_{$nachname}_{$startdatum}.pdf");
            $mail->addAttachment("{$upload_dir}{$pdfName_2}", "Teilnahmebestaetigung_{$title}_{$vorname}_{$nachname}_{$startdatum}.pdf");
        }

        $mail->addAttachment("{$upload_dir}{$pdfName_1}", "XSIEBEN_Angebot_{$title}_{$vorname}_{$nachname}_{$startdatum}.pdf");
        $mail->addAttachment("{$upload_dir}{$pdfName_R}", "XSIEBEN_Kalkulation_{$title}_{$vorname}_{$nachname}_{$startdatum}.pdf");

        $mail->send();

        echo '<br><h4>Mail versendet</h4>';
    } catch (Exception $e) {
        echo "Diese Mail wurde nicht versendet: {$mail->ErrorInfo} ({$email})";
    }

    die(); // wichtig für AJAX
}
