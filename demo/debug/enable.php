<?php

/**
 * Demo debug layer. Required by one marked line in bootstrap.php, after .env
 * is loaded and before Auth and the HTTP client are built. Nothing outside
 * demo/debug/ calls into this folder: delete it and that line, and the demo is
 * the bare integration.
 *
 * In scope from bootstrap.php: $handler (GuzzleHttp\HandlerStack).
 */

declare(strict_types=1);

require __DIR__ . '/accounts.php';
require __DIR__ . '/wire.php';
require __DIR__ . '/explain.php';

/** @var \GuzzleHttp\HandlerStack $handler */

debugApplyAccount();
$handler->push(debugWireMiddleware(), 'debug-wire');
debugRecordIncoming();
ob_start('debugInjectPanel');
