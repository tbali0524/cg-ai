<?php

declare(strict_types=1);

namespace TBali\CgAi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use OpenAI\Client;

const ANSI_RED_INV = "\e[1;37;41m";
const ANSI_GREEN_INV = "\e[1;37;42m";
const ANSI_YELLOW_INV = "\e[1;37;43m";
const ANSI_GREEN = "\e[32m";
const ANSI_YELLOW = "\e[33m";
const ANSI_LIGHT_CYAN = "\e[96m";
const ANSI_RESET = "\e[0m";
const ERROR_TAG = ANSI_RED_INV . '[ERROR]' . ANSI_RESET . ' ';
const WARN_TAG = ANSI_YELLOW_INV . '[WARN]' . ANSI_RESET . ' ';

final class LanguageTransformer
{
    public const TITLE = ANSI_GREEN . 'CG-AI' . ANSI_RESET
        . ': Codingame puzzle solution language converter frontend for OpenAI, (c) 2025 by TBali';
    public const DEFAULT_OPENAI_MODEL = 'gpt-5-mini';
    public const DEFAULT_CGTEST_CONFIG_PATH = '.cgtest.cg-ai.php';
    public const EXTENSIONS = [
        'c' => 'c',
        'c++' => 'cpp',
        'c#' => 'cs',
        'clojure' => 'clj',
        'd' => 'd',
        'dart' => 'dart',
        'f#' => 'fsx',
        'go' => 'go',
        'groovy' => 'groovy',
        'haskell' => 'hs',
        'java' => 'java',
        'javascript' => 'js',
        'kotlin' => 'kt',
        'lua' => 'lua',
        'ocaml' => 'ml',
        'pascal' => 'pas',
        'perl' => 'pl',
        'php' => 'php',
        'python' => 'py',
        'ruby' => 'rb',
        'rust' => 'rs',
        'scala' => 'scala',
        'typescript' => 'ts',
        'vb.net' => 'vb',
    ];

    private int $inputTokens = 0;
    private int $outputTokens = 0;

    public function __construct(
        private Client $aiClient,
        private Filesystem $filesystem,
        private HttpClient $httpClient,
        private string $cgSession,
        private string $cgUserid,
        private string $configFilePath = self::DEFAULT_CGTEST_CONFIG_PATH,
        private string $openaiModel = self::DEFAULT_OPENAI_MODEL,
        private string $fromLanguage = 'php',
        private string $toLanguage = 'rust',
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function readPuzzleList(): array
    {
        $cgtest_config_path = $this->configFilePath;
        try {
            $file_exists = $this->filesystem->fileExists($cgtest_config_path);
        } catch (FilesystemException | UnableToCheckExistence $exception) {
            echo ERROR_TAG . 'Cannot check if file exists: '
                . ANSI_YELLOW . $cgtest_config_path . ANSI_RESET . PHP_EOL;
            return [];
        }
        if (!$file_exists) {
            echo ERROR_TAG . 'Cannot open config file: '
                . ANSI_YELLOW . $cgtest_config_path . ANSI_RESET . PHP_EOL;
            return [];
        }
        echo '[INFO] Reading puzzle list from config file: '
            . ANSI_LIGHT_CYAN . $cgtest_config_path . ANSI_RESET . PHP_EOL;
        $cgtest_config = include_once $cgtest_config_path;
        if (
            !is_array($cgtest_config)
            || !isset($cgtest_config['puzzles'])
            || !is_array($cgtest_config['puzzles'])
        ) {
            echo ERROR_TAG . 'Invalid config file: '
                . ANSI_YELLOW . $cgtest_config_path . ANSI_RESET . PHP_EOL;
            return [];
        }
        $puzzles = [];
        foreach ($cgtest_config['puzzles'] as $puzzle_list) {
            if (is_array($puzzle_list)) {
                $puzzles = array_merge($puzzles, $puzzle_list);
            }
        }
        // @phpstan-ignore-next-line return.type
        return array_unique($puzzles);
    }

    public function hello(): ?string
    {
        $response = $this->aiClient->responses()->create([
            'model' => 'gpt-5-nano',
            'input' => 'Hello!',
        ]);
        return $response->outputText;
    }

    /**
     * @return array<int, string>
     */
    public function listModels(): array
    {
        $response = $this->aiClient->models()->list();
        $ans = [];
        foreach ($response->data as $result) {
            $ans[] = $result->id;
        }
        sort($ans);
        return $ans;
    }

    public function processPuzzle(string $puzzle): bool
    {
        $input_file_path = $this->getInputPath($puzzle);
        try {
            $file_exists = $this->filesystem->fileExists($input_file_path);
        } catch (FilesystemException | UnableToCheckExistence $exception) {
            echo ERROR_TAG . 'Cannot check if file exists: '
                . ANSI_YELLOW . $input_file_path . ANSI_RESET . PHP_EOL;
            return false;
        }
        if (!$file_exists) {
            echo WARN_TAG . 'Missing input file, skipped: '
                . ANSI_YELLOW . $puzzle . ANSI_RESET . PHP_EOL;
            return false;
        }
        $output_file_path = $this->getOutputPath($puzzle);
        try {
            $file_exists = $this->filesystem->fileExists($output_file_path);
        } catch (FilesystemException | UnableToCheckExistence $exception) {
            echo ERROR_TAG . 'Cannot check if file exists: '
                . ANSI_YELLOW . $output_file_path . ANSI_RESET . PHP_EOL;
            return false;
        }
        if ($file_exists) {
            echo WARN_TAG . 'Output file already exists, skipped: '
                . ANSI_YELLOW . $puzzle . ANSI_RESET . PHP_EOL;
            return false;
        }
        try {
            $input_file_contents = $this->filesystem->read($input_file_path);
        } catch (FilesystemException | UnableToReadFile $exception) {
            echo ERROR_TAG . 'Cannot read input file: '
                . ANSI_YELLOW . $input_file_path . ANSI_RESET . PHP_EOL;
            return false;
        }
        $prompt = 'Convert following ' . $this->fromLanguage . ' code to ' . $this->toLanguage
            . ', output only the code.' . PHP_EOL;
        if ($this->toLanguage == 'php') {
            $prompt .= ' Use only PHP v7.3 syntax.' . PHP_EOL;
        }
        echo '[INFO] Calling OpenAI to convert the code: '
            . ANSI_LIGHT_CYAN . $puzzle . ANSI_RESET . ' ...';
        if (strlen($puzzle) < 40) {
            echo str_repeat('.', 40 - strlen($puzzle));
        }
        try {
            $response = $this->aiClient->responses()->create([
                'model' => $this->openaiModel,
                'input' => $prompt . $input_file_contents,
            ]);
        } catch (\Exception $exception) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'OpenAI call threw an exception.' . PHP_EOL;
            return false;
        }
        if (is_null($response->outputText)) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'OpenAI call returned error.' . PHP_EOL;
            return false;
        }
        try {
            $this->filesystem->write($output_file_path, $response->outputText);
        } catch (FilesystemException | UnableToWriteFile $exception) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'Cannot write output file: '
                . ANSI_YELLOW . $output_file_path . ANSI_RESET . PHP_EOL;
            return false;
        }
        echo ' ' . ANSI_GREEN_INV . '[OK]' . ANSI_RESET . PHP_EOL;
        return true;
    }

    public function testSolutions(): bool
    {
        echo '[INFO] Testing all AI-generated conversions with CGTest...' . PHP_EOL . PHP_EOL;
        $command = 'cgtest --config=' . $this->configFilePath . ' --stats --lang=' . $this->toLanguage;
        $execResultCode = 0;
        $execResult = system($command, $execResultCode);
        if (($execResult === false) or ($execResultCode != 0)) {
            echo ERROR_TAG . 'CGTest returned error' . PHP_EOL . PHP_EOL;
            return false;
        }
        return true;
    }

    public function submitSolution(string $puzzle): bool
    {
        $output_file_path = $this->getOutputPath($puzzle);
        try {
            $input_file_contents = $this->filesystem->read($output_file_path);
        } catch (FilesystemException | UnableToReadFile $exception) {
            echo ERROR_TAG . 'Cannot read generated solution file: '
                . ANSI_YELLOW . $output_file_path . ANSI_RESET . PHP_EOL;
            return false;
        }
        echo '[INFO] Submitting code: '
            . ANSI_LIGHT_CYAN . $puzzle . ANSI_RESET . ' ...';
        if (strlen($puzzle) < 40) {
            echo str_repeat('.', 40 - strlen($puzzle));
        }
        $jar = CookieJar::fromArray(
            [
                'cgSession' => $this->cgSession,
                'godfatherId' => $this->cgUserid,
            ],
            'https://www.codingame.com',
        );
        // CALL 1
        echo '[1] ';
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://www.codingame.com/services/Puzzle/generateSessionFromPuzzlePrettyId',
                [
                    'headers' => [
                        'Accept' => 'application/json, text/plain, */*',
                        'Content-Type' => 'application/json;charset=UTF-8',
                    ],
                    'body' => '[' . $this->cgUserid . ', "' . $puzzle . '", false]',
                    'cookies' => $jar,
                ]
            );
        } catch (ClientException $e) {
            echo ERROR_TAG . PHP_EOL;
            echo Psr7\Message::toString($e->getRequest());
            echo Psr7\Message::toString($e->getResponse());
            return false;
        }
        $code = $response->getStatusCode();
        if ($code != 200) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'call [1] returned error: ' . PHP_EOL
                . $response->getReasonPhrase() . PHP_EOL;
            return false;
        }
        $body = strval($response->getBody());
        $json = json_decode($body);
        // @phpstan-ignore property.nonObject
        if (!isset($json->handle)) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'call [1] returned invalid answer: ' . PHP_EOL . $body . PHP_EOL;
            return false;
        }
        // @phpstan-ignore cast.string
        $handle = (string) $json->handle;
        // CALL 2
        echo '[2] ';
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://www.codingame.com/services/TestSession/submit',
                [
                    'headers' => [
                        'Accept' => 'application/json, text/plain, */*',
                        'Content-Type' => 'application/json;charset=UTF-8',
                        'Referer' => 'https://www.codingame.com/ide/puzzle/' . $puzzle,
                    ],
                    'body' => '["' . $handle . '", {'
                        . '"code":"' . $input_file_contents . '",'
                        . '"programmingLanguageId":"' . $this->toLanguage . '"},null]',
                    'cookies' => $jar,
                ]
            );
        } catch (ClientException $e) {
            echo ERROR_TAG . PHP_EOL;
            echo Psr7\Message::toString($e->getRequest());
            echo Psr7\Message::toString($e->getResponse());
            return false;
        }
        $code = $response->getStatusCode();
        if ($code != 200) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'call [2] returned error: ' . PHP_EOL
                . $response->getReasonPhrase() . PHP_EOL;
            return false;
        }
        $report_handle = (string) $response->getBody();
        if (!is_numeric($report_handle)) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'call [2] returned invalid answer: ' . PHP_EOL
                . $report_handle . PHP_EOL;
            return false;
        }
        // CALL 3
        echo '[3] ';
        sleep(3);
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://www.codingame.com/services/Report/findReportBySubmission',
                [
                    'headers' => [
                        'Accept' => 'application/json, text/plain, */*',
                        'Content-Type' => 'application/json;charset=UTF-8',
                        'Referer' => 'https://www.codingame.com/ide/puzzle/' . $puzzle,
                    ],
                    'body' => '[' . $report_handle . ']',
                    'cookies' => $jar,
                ]
            );
        } catch (ClientException $e) {
            echo ERROR_TAG . PHP_EOL;
            echo Psr7\Message::toString($e->getRequest());
            echo Psr7\Message::toString($e->getResponse());
            return false;
        }
        $code = $response->getStatusCode();
        if ($code != 200) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'call [3] returned error: ' . PHP_EOL
                . $response->getReasonPhrase() . PHP_EOL;
            return false;
        }
        $body = (string) $response->getBody();
        $json = json_decode($body);
        // @phpstan-ignore property.nonObject
        if (!isset($json->score)) {
            echo ERROR_TAG . PHP_EOL . ERROR_TAG . 'call [3] returned invalid answer: ' . PHP_EOL . $body . PHP_EOL;
            return false;
        }
        // @phpstan-ignore cast.double
        $score = (float) $json->score;
        if ($score != 100.0) {
            echo WARN_TAG . PHP_EOL . WARN_TAG . 'Code submitted successfully but some validators failed.' . PHP_EOL;
            return false;
        }
        echo ' ' . ANSI_GREEN_INV . '[OK]' . ANSI_RESET . PHP_EOL;
        return true;
    }

    public function submitSolutions(): bool
    {
        echo '[INFO] Submitting ' . ANSI_LIGHT_CYAN . $this->toLanguage . ANSI_RESET
            . ' solutions to Codingame...' . PHP_EOL;
        // ...
        $this->submitSolution('easy_com_seeing-squares');
        return true;
    }

    public function convertAll(): bool
    {
        try {
            $response = $this->aiClient->models()->retrieve($this->openaiModel);
        } catch (\Exception $exception) {
            echo ERROR_TAG . 'Unsupported OpenAI model: '
                . ANSI_YELLOW . $this->openaiModel . ANSI_RESET . PHP_EOL . PHP_EOL;
            return false;
        }
        $puzzles = $this->readPuzzleList();
        if (count($puzzles) > 0) {
            echo '[INFO] Processing ' . ANSI_LIGHT_CYAN . count($puzzles) . ANSI_RESET . ' puzzle solution'
                . (count($puzzles) > 1 ? 's' : '') . '.' . PHP_EOL;
            echo '[INFO] Converting ' . ANSI_LIGHT_CYAN . $this->fromLanguage . ANSI_RESET
                . ' code to ' . ANSI_LIGHT_CYAN . $this->toLanguage . ANSI_RESET
                . ', using OpenAI model: ' . ANSI_LIGHT_CYAN . $this->openaiModel . ANSI_RESET . PHP_EOL;
        }
        $count_converted = 0;
        foreach ($puzzles as $puzzle) {
            if ($this->processPuzzle($puzzle)) {
                ++$count_converted;
            }
        }
        echo '[INFO] Converted ' . ANSI_LIGHT_CYAN . $count_converted . ANSI_RESET;
        if (count($puzzles) != $count_converted) {
            echo ', skipped ' . ANSI_LIGHT_CYAN . (count($puzzles) - $count_converted) . ANSI_RESET;
        }
        echo ' puzzle solution' . (count($puzzles) > 1 ? 's' : '') . '.' . PHP_EOL;
        if ($count_converted > 0) {
            echo '[INFO] Used ' . ANSI_LIGHT_CYAN . $this->inputTokens . ANSI_RESET . ' input and '
                . ANSI_LIGHT_CYAN . $this->outputTokens . ANSI_RESET . ' output tokens.' . PHP_EOL;
            return $this->testSolutions();
        }
        echo PHP_EOL;
        return true;
    }

    /**
     * @param array<int, string> $cli_arguments
     */
    public function cliRun(array $cli_arguments): bool
    {
        $from_changed = false;
        $to_changed = false;
        $list = false;
        $help = false;
        $test = false;
        $submit = false;
        $invalid = false;
        for ($i = 1; $i < count($cli_arguments); ++$i) {
            if (str_starts_with($cli_arguments[$i], '--from=')) {
                if ($from_changed) {
                    echo ERROR_TAG . 'Multiple --from arguments' . PHP_EOL;
                    $invalid = true;
                }
                $from_changed = true;
                $this->fromLanguage = substr($cli_arguments[$i], 7);
                if (!isset(self::EXTENSIONS[$this->fromLanguage])) {
                    echo ERROR_TAG . 'Unsupported language in --from argument' . PHP_EOL;
                    $invalid = true;
                }
                continue;
            }
            if (str_starts_with($cli_arguments[$i], '--to=')) {
                if ($to_changed) {
                    echo ERROR_TAG . 'Multiple --to arguments' . PHP_EOL;
                    $invalid = true;
                }
                $to_changed = true;
                $this->toLanguage = substr($cli_arguments[$i], 5);
                if (!isset(self::EXTENSIONS[$this->toLanguage])) {
                    echo ERROR_TAG . 'Unsupported language in --to argument' . PHP_EOL;
                    $invalid = true;
                }
                continue;
            }
            if ($cli_arguments[$i] == '--help') {
                if ($help) {
                    echo ERROR_TAG . 'Multiple --help arguments' . PHP_EOL;
                    $invalid = true;
                }
                $help = true;
                continue;
            }
            if ($cli_arguments[$i] == '--list') {
                if ($list) {
                    echo ERROR_TAG . 'Multiple --list arguments' . PHP_EOL;
                    $invalid = true;
                }
                $list = true;
                continue;
            }
            if ($cli_arguments[$i] == '--test') {
                if ($test) {
                    echo ERROR_TAG . 'Multiple --test arguments' . PHP_EOL;
                    $invalid = true;
                }
                $test = true;
                continue;
            }
            if ($cli_arguments[$i] == '--submit') {
                if ($submit) {
                    echo ERROR_TAG . 'Multiple --submit arguments' . PHP_EOL;
                    $invalid = true;
                }
                $submit = true;
                continue;
            }
            echo ERROR_TAG . 'Invalid argument: ' . ANSI_YELLOW . $cli_arguments[$i] . ANSI_RESET . PHP_EOL;
            $invalid = true;
        }
        if (
            ($help && ($list || $test || $submit))
            || ($list && ($help || $test || $submit))
            || ($test && ($help || $list || $submit))
            || ($submit && ($help || $list || $test))
        ) {
            echo ERROR_TAG . '--help, --list, --test, --submit arguments must be exclusive' . PHP_EOL;
            $invalid = true;
        }
        if ($invalid) {
            echo PHP_EOL;
            $this->printHelp();
            return false;
        }
        if ($help) {
            $this->printHelp();
            return true;
        }
        if ($list) {
            $this->printPuzzlesFromDir();
            return true;
        }
        if ($test) {
            return $this->testSolutions();
        }
        if ($submit) {
            return $this->submitSolutions();
        }
        return $this->convertAll();
    }

    public function getInputPath(string $puzzle): string
    {
        return $this->fromLanguage . '/' . $puzzle
            . '.' . (self::EXTENSIONS[$this->fromLanguage] ?? $this->fromLanguage);
    }

    public function getOutputPath(string $puzzle): string
    {
        return $this->toLanguage . '/' . $puzzle
            . '.' . (self::EXTENSIONS[$this->toLanguage] ?? $this->toLanguage);
    }

    private function printPuzzlesFromDir(): void
    {
        $all_paths = $this->filesystem->listContents('/' . $this->fromLanguage . '/')
            ->filter(static fn (StorageAttributes $attributes) => $attributes->isFile())
            ->sortByPath()
            ->map(static fn (StorageAttributes $attributes) => $attributes->path())
            ->toArray()
        ;
        echo 'List of puzzle solutions found in the ' . ANSI_LIGHT_CYAN . $this->fromLanguage . '/' . ANSI_RESET
            . ' directory:' . PHP_EOL . PHP_EOL;
        $prefix = $this->fromLanguage . '/';
        $postfix = '.' . (self::EXTENSIONS[$this->fromLanguage] ?? $this->fromLanguage);
        $count = 0;
        foreach ($all_paths as $path) {
            $puzzle = $path;
            if (str_starts_with($puzzle, $prefix)) {
                $puzzle = substr($puzzle, strlen($prefix));
            }
            if (str_ends_with($puzzle, $postfix)) {
                $puzzle = substr($puzzle, 0, -strlen($postfix));
            }
            if ($puzzle == '.gitignore') {
                continue;
            }
            ++$count;
            echo "            '{$puzzle}'," . PHP_EOL;
        }
        echo PHP_EOL . '[INFO] Found ' . ANSI_LIGHT_CYAN . $count . ANSI_RESET . ' puzzle solutions.' . PHP_EOL
            . PHP_EOL;
    }

    private function printHelp(): void
    {
        echo 'Usage:' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  php src/cg-ai.php' . ANSI_RESET . ' [arguments]' . PHP_EOL
            . 'Arguments:' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --from=' . ANSI_RESET . 'LANG    set input language  [default: php]' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --to=' . ANSI_RESET . 'LANG      set output language [default: rust]' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --list' . ANSI_RESET . '         generate puzzle names list' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --submit' . ANSI_RESET . '       submit puzzle solutions to CG site' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --test' . ANSI_RESET . '         run cgtest only' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --help' . ANSI_RESET . '         show this help' . PHP_EOL
            . 'Supported languages:' . PHP_EOL
            . '  ' . implode(', ', array_slice(array_keys(self::EXTENSIONS), 0, 14)) . PHP_EOL
            . '  ' . implode(', ', array_slice(array_keys(self::EXTENSIONS), 14)) . PHP_EOL
            . PHP_EOL;
    }
}
