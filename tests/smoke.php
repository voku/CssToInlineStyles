<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use voku\CssToInlineStyles\CssToInlineStyles;

/** @var list<array{html: string, css: string, expected: string}> $cases */
$cases = [
    [
        'html' => '<div></div>',
        'css' => 'div { display: none; }',
        'expected' => '<div style="display: none;"></div>',
    ],
    [
        'html' => '<a class="test-class">nodeContent</a>',
        'css' => '.test-class { background-color: #aaa; text-decoration: none; }',
        'expected' => '<a class="test-class" style="background-color: #aaa; text-decoration: none;">nodeContent</a>',
    ],
];

foreach ($cases as $index => $case) {
    $actual = (new CssToInlineStyles($case['html'], $case['css']))->convert();

    if ($actual !== $case['expected']) {
        throw new RuntimeException(
            sprintf(
                'Runtime CSS contract #%d failed. Expected: %s; actual: %s',
                $index + 1,
                var_export($case['expected'], true),
                var_export($actual, true)
            )
        );
    }
}

echo count($cases) . ' runtime CSS conversion contracts passed.' . PHP_EOL;
