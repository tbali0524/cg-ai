#!/usr/bin/env php
<?php

/**
 * CG-AI: Codingame puzzle solution language converter frontend for OpenAI, (c) 2025 by TBali.
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
$openai_api_key = getenv('OPENAI_API_KEY');
if ($openai_api_key === false || $openai_api_key == 'your_api_key_here') {
    echo ERROR_TAG . 'Missing OPENAI_API_KEY' . PHP_EOL;
    exit(1);
}
$cg_session = getenv('CG_SESSION');
if ($cg_session === false || $cg_session == 'your_cg_session_id_here') {
    echo ERROR_TAG . 'Missing CG_SESSION' . PHP_EOL;
    exit(1);
}
$cg_userid = getenv('CG_USERID');
if ($cg_userid === false || $cg_session == 'your_cg_user_id_here') {
    echo ERROR_TAG . 'Missing CG_USERID' . PHP_EOL;
    exit(1);
}
$config_file_path = getenv('CGTEST_CONFIG');
if ($config_file_path === false) {
    $config_file_path = LanguageTransformer::DEFAULT_CGTEST_CONFIG_PATH;
}
$openai_model = getenv('OPENAI_MODEL');
if ($openai_model === false) {
    $openai_model = LanguageTransformer::DEFAULT_OPENAI_MODEL;
}
$filesystem_adapter = new LocalFilesystemAdapter(__DIR__ . '/../');
$filesystem = new Filesystem($filesystem_adapter);
$http_client = new HttpClient([]);
// $ai_client = OpenAI::client($openai_api_key);
$ai_client = \OpenAI::factory()
    ->withApiKey($openai_api_key)
    ->withHttpClient($http_client)
    ->make()
;
$solver = new LanguageTransformer(
    $ai_client,
    $filesystem,
    $http_client,
    $cg_session,
    $cg_userid,
    $config_file_path,
    $openai_model
);
$success = $solver->cliRun($argv);
exit($success ? 0 : 1);
