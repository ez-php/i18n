<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\I18n\Translator.
 *
 * Measures the overhead of loading a language file and resolving
 * translation keys with placeholder substitution, including the
 * in-memory cache warm-up path and subsequent cached lookups.
 *
 * Exits with code 1 if the per-lookup time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/translate.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\I18n\Translator;

const ITERATIONS = 5000;
const LOOKUPS_PER_ITER = 10;
const THRESHOLD_MS = 1.0; // per-iteration upper bound in milliseconds

// ── Prepare a temporary language directory ───────────────────────────────────

$langDir = sys_get_temp_dir() . '/ez-i18n-bench-' . getmypid();
@mkdir($langDir . '/en', recursive: true);

file_put_contents($langDir . '/en/messages.php', '<?php return ' . var_export([
    'welcome' => 'Welcome, :name!',
    'farewell' => 'Goodbye, :name. See you in :days days.',
    'error' => 'An error occurred: :message',
    'count' => 'You have :count items.',
    'nested' => ['deep' => 'Deep key :value'],
    'plain' => 'No placeholders here.',
    'a' => 'Alpha :x',
    'b' => 'Beta :y',
    'c' => 'Gamma :z',
    'd' => 'Delta',
], true) . ';');

$translator = new Translator('en', 'en', $langDir);

// Warm-up: load the language file into cache
$translator->get('messages.welcome', ['name' => 'World']);

// ── Benchmark ─────────────────────────────────────────────────────────────────

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    $translator->get('messages.welcome', ['name' => 'Alice']);
    $translator->get('messages.farewell', ['name' => 'Bob', 'days' => '3']);
    $translator->get('messages.error', ['message' => 'timeout']);
    $translator->get('messages.count', ['count' => '42']);
    $translator->get('messages.nested.deep', ['value' => 'X']);
    $translator->get('messages.plain', []);
    $translator->get('messages.a', ['x' => '1']);
    $translator->get('messages.b', ['y' => '2']);
    $translator->get('messages.c', ['z' => '3']);
    $translator->get('messages.d', []);
}

$end = hrtime(true);

// ── Cleanup ───────────────────────────────────────────────────────────────────
@unlink($langDir . '/en/messages.php');
@rmdir($langDir . '/en');
@rmdir($langDir);

$totalMs = ($end - $start) / 1_000_000;
$perIter = $totalMs / ITERATIONS;

echo sprintf(
    "Translator Benchmark\n" .
    "  Lookups per iteration: %d\n" .
    "  Iterations           : %d\n" .
    "  Total time           : %.2f ms\n" .
    "  Per iteration        : %.3f ms\n" .
    "  Threshold            : %.1f ms\n",
    LOOKUPS_PER_ITER,
    ITERATIONS,
    $totalMs,
    $perIter,
    THRESHOLD_MS,
);

if ($perIter > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perIter,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
