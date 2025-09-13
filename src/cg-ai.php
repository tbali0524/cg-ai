#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace TBali\CgAi;

use Dotenv\Dotenv;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

require 'vendor/autoload.php';

echo LanguageTransformer::TITLE . PHP_EOL . PHP_EOL;
$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
$OpenAiApiKey = getenv('OPENAI_API_KEY');
if ($OpenAiApiKey === false) {
    echo ERROR_TAG . 'Missing OPENAI_API_KEY' . PHP_EOL;
    exit(1);
}
$adapter = new LocalFilesystemAdapter(__DIR__ . '/../');
$filesystem = new Filesystem($adapter);
$ai_client = \OpenAI::client($OpenAiApiKey);
$solver = new LanguageTransformer($ai_client, $filesystem, 'php', 'rust');
if (count($argv) == 2 && $argv[1] == '-l') {
    $solver->printPuzzlesFromDir();
} else {
    $solver->convertAll();
}
