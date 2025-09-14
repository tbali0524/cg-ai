#!/usr/bin/env php
<?php

/**
 * CG-AI: Codingame puzzle solution language converter frontend for OpenAI.
 */

declare(strict_types=1);

namespace TBali\CgAi;

use Dotenv\Dotenv;
use GuzzleHttp\Client as HttpClient;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use OpenAI;

require 'vendor/autoload.php';

echo LanguageTransformer::TITLE . PHP_EOL . PHP_EOL;
$dotenv = Dotenv::createUnsafeImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
$open_ai_api_key = getenv('OPENAI_API_KEY');
if ($open_ai_api_key === false || $open_ai_api_key == 'your_api_key_here') {
    echo ERROR_TAG . 'Missing OPENAI_API_KEY' . PHP_EOL;
    exit(1);
}
$config_file_path = getenv('CGTEST_CONFIG');
if ($config_file_path === false) {
    $config_file_path = LanguageTransformer::DEFAULT_CGTEST_CONFIG_PATH;
}
$filesystem_adapter = new LocalFilesystemAdapter(__DIR__ . '/../');
$filesystem = new Filesystem($filesystem_adapter);
// $ai_client = OpenAI::client($open_ai_api_key);
$ai_client = \OpenAI::factory()
    ->withApiKey($open_ai_api_key)
    ->withHttpClient($httpClient = new HttpClient([]))
    ->make()
;
$solver = new LanguageTransformer($ai_client, $filesystem, $config_file_path);
$success = $solver->cli_run($argv);
exit($success ? 0 : 1);
