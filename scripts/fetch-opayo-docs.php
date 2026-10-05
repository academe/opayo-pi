<?php

/**
 * Pull Elavon developer-portal pages down as readable markdown.
 *
 * The portal is a Next.js single-page app: it is slow, it sometimes fails to
 * render, and it cannot be read by anything that does not run JavaScript. But
 * each page ships its own markdown *source* embedded as a JSON string in the
 * served HTML, and the navigation ships with it. So a plain HTTP request gets
 * the lot - no login, no browser, no scraping of rendered DOM.
 *
 * The API reference pages are different: they are rendered from an OpenAPI
 * description embedded in the page as JSON, and the only markdown on the page
 * is its introduction. For those the whole description is saved as
 * <page>.openapi.json, and <page>.md is built from it: the introduction, then
 * every section (error codes, change log, sandbox accounts...) and a list of
 * the endpoints.
 *
 * Usage:
 *
 *   php scripts/fetch-opayo-docs.php --list
 *   php scripts/fetch-opayo-docs.php google-pay-1 test-in-sandbox
 *   php scripts/fetch-opayo-docs.php --all
 *
 * Options:
 *
 *   --list             show the pages the portal navigation exposes, then stop
 *   --all              fetch every page in the navigation
 *   --out=DIR          where to write (default: docs/vendor/opayo)
 *   --product=NAME     portal product id (default: opayo)
 *   --version=NAME     product version (default: v1)
 *   --locale=NAME      locale segment (default: en-uk)
 *   --delay=SECONDS    pause between requests (default: 1)
 *
 * The output is Elavon's copyrighted documentation, so it is written to a
 * gitignored directory. Read it locally; do not commit it.
 */

declare(strict_types=1);

const PORTAL = 'https://developer.elavon.com';

// A real browser UA: the portal sits behind a bot check that serves an empty
// shell to anything it does not recognise. Identify the tool honestly after it.
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
    . '(KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 (opayo-pi docs fetcher)';

$options = getopt('', ['list', 'all', 'out:', 'product:', 'version:', 'locale:', 'delay:']);

$product = $options['product'] ?? 'opayo';
$version = $options['version'] ?? 'v1';
$locale = $options['locale'] ?? 'en-uk';
$outDir = rtrim($options['out'] ?? (dirname(__DIR__) . '/docs/vendor/' . $product), '/');
$delay = (float)($options['delay'] ?? 1);

$slugs = array_values(array_filter(
    $argv,
    fn ($arg, $i) => $i > 0 && ! str_starts_with($arg, '--'),
    ARRAY_FILTER_USE_BOTH
));

// ---------------------------------------------------------------------------

$pathPrefix = "/products/$locale/$product/$version/";

/**
 * Fetch a URL as a browser would. Returns null on any non-200.
 */
function fetchHtml(string $url): ?string
{
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "User-Agent: " . USER_AGENT . "\r\nAccept: text/html\r\n",
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);

    $body = @file_get_contents($url, false, $context);

    if ($body === false) {
        return null;
    }

    // $http_response_header is set by the stream wrapper.
    $status = isset($http_response_header[0]) ? $http_response_header[0] : '';

    return str_contains($status, ' 200') ? $body : null;
}

/**
 * How many backslashes immediately precede this offset? An odd count means the
 * character at the offset is escaped, so it does not delimit a JSON string.
 */
function precedingBackslashes(string $s, int $i): int
{
    $n = 0;

    while ($i > 0 && $s[$i - 1] === '\\') {
        $n++;
        $i--;
    }

    return $n;
}

/**
 * Expand from an offset inside a JSON string out to its delimiting quotes, and
 * decode it. Returns null if the bounds cannot be found or it will not decode.
 */
function decodeEnclosingJsonString(string $html, int $offset): ?string
{
    $start = $offset;

    do {
        $start = strrpos(substr($html, 0, $start), '"');

        if ($start === false) {
            return null;
        }
    } while (precedingBackslashes($html, $start) % 2 !== 0);

    $end = $offset;

    do {
        $end = strpos($html, '"', $end + 1);

        if ($end === false) {
            return null;
        }
    } while (precedingBackslashes($html, $end) % 2 !== 0);

    $decoded = json_decode(substr($html, $start, $end - $start + 1));

    return is_string($decoded) ? $decoded : null;
}

/**
 * The page's markdown source. Several JSON strings on the page look like
 * markdown; the document itself is reliably the longest one.
 */
function extractMarkdown(string $html): ?string
{
    // In the raw HTML a markdown line break is the two characters \ and n.
    if (! preg_match_all('/\\\\n#{1,4} /', $html, $matches, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $best = null;

    foreach ($matches[0] as [$_, $offset]) {
        $candidate = decodeEnclosingJsonString($html, (int)$offset);

        if ($candidate !== null && ($best === null || strlen($candidate) > strlen($best))) {
            $best = $candidate;
        }
    }

    return $best;
}

/**
 * The JSON object that starts at this offset, found by matching braces
 * outside of strings. Returns null if it never closes.
 */
function extractJsonObject(string $html, int $start): ?string
{
    $depth = 0;
    $inString = false;
    $length = strlen($html);

    for ($i = $start; $i < $length; $i++) {
        $char = $html[$i];

        if ($inString) {
            if ($char === '\\') {
                $i++;
            } elseif ($char === '"') {
                $inString = false;
            }

            continue;
        }

        if ($char === '"') {
            $inString = true;
        } elseif ($char === '{') {
            $depth++;
        } elseif ($char === '}' && --$depth === 0) {
            return substr($html, $start, $i - $start + 1);
        }
    }

    return null;
}

/**
 * The OpenAPI description an API reference page is rendered from, or null
 * for an ordinary page.
 */
function extractOpenApi(string $html): ?array
{
    $marker = '"specData":';
    $position = strpos($html, $marker . '{');

    if ($position === false) {
        return null;
    }

    $json = extractJsonObject($html, $position + strlen($marker));
    $spec = $json === null ? null : json_decode($json, true);

    return is_array($spec) && isset($spec['openapi'], $spec['paths']) ? $spec : null;
}

/**
 * The readable parts of an OpenAPI description as one markdown document:
 * the introduction, each section, and the endpoints.
 */
function openApiToMarkdown(array $spec): string
{
    $info = $spec['info'] ?? [];

    $markdown = '# ' . ($info['title'] ?? 'API reference')
        . ' (OpenAPI description version ' . ($info['version'] ?? 'unknown') . ")\n\n"
        . trim($info['description'] ?? '') . "\n";

    foreach ($spec['tags'] ?? [] as $tag) {
        if (trim($tag['description'] ?? '') !== '') {
            $markdown .= "\n# " . $tag['name'] . "\n\n" . trim($tag['description']) . "\n";
        }
    }

    $markdown .= "\n# Endpoints\n\nThe fields of each are in the .openapi.json file saved next to this one.\n\n";

    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if (is_array($operation) && isset($operation['responses'])) {
                $markdown .= '- `' . strtoupper($method) . ' ' . $path . '` ' . ($operation['summary'] ?? '') . "\n";
            }
        }
    }

    return $markdown;
}

/**
 * Page slugs from the navigation, which every page carries.
 */
function extractSlugs(string $html, string $pathPrefix): array
{
    preg_match_all('#' . preg_quote($pathPrefix, '#') . '([a-z0-9][a-z0-9\-]{2,})#', $html, $m);

    $slugs = array_unique($m[1]);
    sort($slugs);

    return $slugs;
}

// ---------------------------------------------------------------------------

fwrite(STDERR, "Reading the portal navigation...\n");

$indexHtml = fetchHtml(PORTAL . $pathPrefix . 'support');

if ($indexHtml === null) {
    fwrite(STDERR, "Could not reach the portal. Check the product/version/locale options.\n");
    exit(1);
}

$available = extractSlugs($indexHtml, $pathPrefix);

if (isset($options['list']) || (! isset($options['all']) && $slugs === [])) {
    echo "Pages available for $product/$version ($locale):\n\n";

    foreach ($available as $slug) {
        echo "  $slug\n";
    }

    echo "\nFetch with: php scripts/fetch-opayo-docs.php <slug> [<slug>...]\n";
    echo "Or all of them: php scripts/fetch-opayo-docs.php --all\n";
    exit(0);
}

if (isset($options['all'])) {
    $slugs = $available;
}

if (! is_dir($outDir) && ! mkdir($outDir, 0o777, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Could not create $outDir\n");
    exit(1);
}

$written = 0;
$failed = [];
$empty = [];

foreach ($slugs as $i => $slug) {
    $url = PORTAL . $pathPrefix . $slug;

    if ($i > 0 && $delay > 0) {
        usleep((int)($delay * 1_000_000));
    }

    $html = fetchHtml($url);

    if ($html === null) {
        printf("  %-46s %s\n", $slug, 'fetch failed');
        $failed[] = $slug;
        continue;
    }

    $openApi = extractOpenApi($html);

    if ($openApi !== null) {
        file_put_contents(
            "$outDir/$slug.openapi.json",
            json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    $markdown = $openApi !== null ? openApiToMarkdown($openApi) : extractMarkdown($html);

    // Some slugs in the navigation are section headings rather than documents:
    // they carry no markdown, and render blank in a real browser too. Not an
    // error - there is simply nothing to save.
    if ($markdown === null) {
        printf("  %-46s %s\n", $slug, 'no content (navigation heading)');
        $empty[] = $slug;
        continue;
    }

    // Provenance, so nobody mistakes a local copy for something we wrote.
    $header = "<!--\nFetched from $url\non " . date('Y-m-d')
        . " by scripts/fetch-opayo-docs.php\nElavon's documentation, reproduced locally for reading. Do not commit.\n-->\n\n";

    file_put_contents("$outDir/$slug.md", $header . $markdown . "\n");

    printf(
        "  %-46s %6d bytes%s\n",
        $slug,
        strlen($markdown),
        $openApi !== null ? " + $slug.openapi.json" : ''
    );
    $written++;
}

echo "\n$written page(s) written to $outDir\n";

if ($empty !== []) {
    echo count($empty) . ' navigation heading(s) skipped: ' . implode(', ', $empty) . "\n";
}

if ($failed !== []) {
    echo 'Could not fetch: ' . implode(', ', $failed) . "\n";
    echo "Retry those, or check whether the portal has changed how it embeds content.\n";
}
