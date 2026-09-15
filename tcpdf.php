<?php
/**
 * Forwarder to modern TCPDF library in tcbpdf/
 * Prevents version fragmentation between root tcpdf.php and tcbpdf/ directory.
 *
 * @package X_SIEBEN
 * @version 6.11.4
 */
require_once __DIR__ . '/tcbpdf/tcpdf.php';