<?php
/**
 * Element: offer-styles.php
 *
 * Liefert das TCPDF-Stylesheet für das Angebots-Dokument.
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

return '<style>
    div {font-size:11pt;}
    table {border-collapse: collapse; border: none;}
    td, th {border: none;}
    .title {
        text-align: left;
        font-size: 16px;
        background-color: #007C90;
        padding: 6pt 15mm 6pt 15mm;
        color: white;
        width: 100%;
    }
    .text {
        line-height: 13.5pt;
        font-size: 9.5pt;
    }
    .clear {font-size:unset;}
    a {color: #04b3ce}
    hr {border: none; height: 1px;}
    .table-dot {font-size:9pt; color: #04b3ce; border: 1px dashed #16A0B9;}
    strong, b {font-weight: bold;}
    h3.modul-heading {
        font-size: 11pt;
        font-weight: bold;
        color: #007C90;
        border-bottom: 1.5px solid #007C90;
        padding-bottom: 3pt;
        margin-top: 14pt;
        margin-bottom: 8pt;
    }
    .modul-label {
        font-weight: bold;
        color: #0f172a;
    }
    p.modul-text {
        font-size: 10pt;
        line-height: 16pt;
        margin-bottom: 6pt;
    }
    ul.modul-list {
        margin-top: 4pt;
        margin-bottom: 10pt;
    }
    ul.modul-list li {
        font-size: 10pt;
        line-height: 16pt;
        padding-bottom: 3pt;
    }
    .modul-intro {
        margin-top: 4pt;
        margin-bottom: 12pt;
        line-height: 16pt;
    }
    .modul-intro p {
        line-height: 16pt;
        margin-bottom: 6pt;
    }
    .abschluss-heading {
        font-size: 11pt;
        font-weight: bold;
        color: #007C90;
        border-bottom: 1.5px solid #007C90;
        padding-bottom: 3pt;
        margin-top: 16pt;
        margin-bottom: 8pt;
    }
</style>';
