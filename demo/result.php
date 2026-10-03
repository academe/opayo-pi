<?php

/**
 * The outcome page for the Apple Pay flow.
 *
 * Apple Pay pays by fetch (so the sheet can complete with the real status), so
 * there is no full-page POST that would render a result. pay.php stashes the
 * rendered outcome and the wire log in the session; this page replays them, so
 * an Apple Pay payment shows exactly what every other method's result page does,
 * wire panel included.
 */

declare(strict_types=1);

require __DIR__ . '/shared.php';

$html = $_SESSION['demoResultHtml'] ?? null;

if ($html === null) {
    // Nothing to show (opened directly, or the result was already consumed).
    header('Location: index.php');
    exit;
}

// Replay the wire log captured during the fetch payment call.
$GLOBALS['wire'] = $_SESSION['demoResultWire'] ?? [];
unset($_SESSION['demoResultHtml'], $_SESSION['demoResultWire']);

pageTop('Payment result');
echo $html;
pageBottom();
