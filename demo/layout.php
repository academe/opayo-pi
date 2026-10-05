<?php

/**
 * Page chrome for the demo. Presentation only: your application has its own.
 */

declare(strict_types=1);

/** Where the optional debug layer may insert its panel. Inert without it. */
const DEBUG_PANEL_MARKER = '<!-- debug-panel -->';

function pageTop(string $title): void
{
    $title = h($title);

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$title} - Opayo Pi demo</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-100 min-h-screen">
    <div class="max-w-6xl mx-auto p-6">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Opayo Pi demo <span class="text-slate-400 font-normal">/ {$title}</span></h1>
        </header>
        <div class="flex flex-col lg:flex-row gap-6">
            <main class="flex-1 min-w-0 space-y-6">
    HTML;
}

function pageBottom(): void
{
    echo '</main>' . DEBUG_PANEL_MARKER . '</div></div></body></html>';
}

/**
 * The order as hidden inputs, so every payment form posts the same order.
 * data-order lets checkout.php mirror edits from the visible order block.
 *
 * @param array<string, string> $order
 */
function orderFields(array $order): string
{
    $html = '';
    foreach ($order as $name => $value) {
        $html .= '<input type="hidden" name="' . h($name) . '" value="' . h($value) . '" data-order="' . h($name) . '">';
    }

    return $html;
}

/**
 * A JavaScript file inlined in a script element. Any "</" in it is escaped as
 * "<\/" (the same string to JavaScript), so text such as "</script>" inside
 * the file cannot end the element early. Your site would more likely serve
 * the file and load it with a src attribute.
 */
function inlineScript(string $path): string
{
    return '<script>' . str_replace('</', '<\/', (string) file_get_contents($path)) . '</script>';
}

/**
 * The box a method shows instead of its button when it cannot be used.
 */
function placeholder(string $title, string $detail): string
{
    return '<div class="border border-dashed border-slate-300 bg-slate-50 rounded-lg p-4 text-sm text-slate-600">'
        . '<span class="font-medium">' . h($title) . '</span>'
        . ($detail !== '' ? '<p class="mt-1 text-slate-500">' . h($detail) . '</p>' : '')
        . '</div>';
}
