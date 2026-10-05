<?php

/**
 * The wire panel: every request and response body, the data arriving at the
 * callback endpoints, and the session. Entries are kept in the session so
 * they survive the redirects between pay.php, notification.php and
 * complete.php, and are shown (then cleared) on the next page that renders.
 */

declare(strict_types=1);

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function debugRecord(string $label, mixed $data): void
{
    $_SESSION['debugWire'][] = [
        'label' => $label,
        'page' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
        'data' => $data,
    ];
}

function debugDecode(string $body): mixed
{
    return json_decode($body) ?? $body;
}

/**
 * Guzzle middleware: records each request and response body as it passes.
 */
function debugWireMiddleware(): callable
{
    return static function (callable $next): callable {
        return static function (RequestInterface $request, array $options) use ($next) {
            debugRecord(
                'Request ' . $request->getMethod() . ' ' . $request->getUri()->getPath(),
                debugDecode((string) $request->getBody())
            );

            return $next($request, $options)->then(static function (ResponseInterface $response) {
                debugRecord('Response HTTP ' . $response->getStatusCode(), debugDecode((string) $response->getBody()));
                $response->getBody()->rewind();

                return $response;
            });
        };
    };
}

/**
 * What the shopper's browser brought to a callback endpoint.
 */
function debugRecordIncoming(): void
{
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');

    if ($page === 'notification.php' && $_POST) {
        debugRecord('Incoming 3D Secure result (POSTed by the browser)', [
            'cres' => substr((string) ($_POST['cres'] ?? ''), 0, 60) . '... (truncated)',
            'threeDSSessionData (decoded)' => base64_decode((string) ($_POST['threeDSSessionData'] ?? '')),
        ]);
    }

    if ($page === 'paypal-return.php') {
        debugRecord('Incoming PayPal return (query string)', $_GET);
    }
}

/**
 * Output-buffer callback: puts the panel where layout.php left its marker.
 * Pages that redirect or answer JSON have no marker, so their entries wait
 * in the session for the next page.
 */
function debugInjectPanel(string $buffer): string
{
    if (! str_contains($buffer, DEBUG_PANEL_MARKER)) {
        return $buffer;
    }

    $account = $_SESSION['debugAccount'] ?? 'env';
    $html = '<aside class="lg:w-1/2 min-w-0 space-y-4">'
        . '<div class="bg-slate-800 text-slate-100 rounded-xl p-4 text-sm space-y-2">'
        . '<div class="font-semibold">Debug (demo only: DEMO_DEBUG=0 hides this)</div>'
        . '<div class="flex flex-wrap gap-3">'
        . '<a class="underline" href="/debug/check.php">Setup check</a>'
        . '<a class="underline" href="/debug/test-card.php">Test card</a>'
        . '<a class="underline" href="?debugAccount=' . ($account === 'sandbox' ? 'env' : 'sandbox') . '">'
        . 'Account: ' . ($account === 'sandbox' ? 'public sandbox' : '.env') . ' (switch)</a>'
        . '</div></div>';

    foreach ($_SESSION['debugWire'] ?? [] as $entry) {
        $html .= '<div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">'
            . h($entry['page'] . ': ' . $entry['label']) . '</div>'
            . '<pre class="bg-slate-900 text-emerald-300 text-xs rounded-lg p-3 overflow-x-auto">'
            . h(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
    }

    $session = $_SESSION;
    unset($session['debugWire']);
    $html .= '<div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">PHP session</div>'
        . '<pre class="bg-slate-800 text-sky-300 text-xs rounded-lg p-3 overflow-x-auto">'
        . h(json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div></aside>';

    // Shown once. Write the session now: this runs while PHP shuts down.
    $_SESSION['debugWire'] = [];
    session_write_close();

    return str_replace(DEBUG_PANEL_MARKER, $html, $buffer);
}
