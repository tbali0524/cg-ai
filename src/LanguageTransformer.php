<?php

declare(strict_types=1);

namespace TBali\CgAi;

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

class LanguageTransformer
{
    public const TITLE = ANSI_GREEN . 'CG-AI' . ANSI_RESET
        . ': Codingame puzzle solution language converter frontend for OpenAI, (c) 2025 by TBali';
    public const AI_MODEL = 'gpt-5-nano';
    public const DEFAULT_CGTEST_CONFIG_PATH = '.cgtest.cg-ai.php';
    public const EXTENSIONS = [
        'php' => 'php',
        'rust' => 'rs',
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
        'python' => 'py',
        'ruby' => 'rb',
        'scala' => 'scala',
        'typescript' => 'ts',
        'vb.net' => 'vb',
    ];

    public function __construct(
        private Client $aiClient,
        private Filesystem $filesystem,
        private string $configFilePath,
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
            echo ERROR_TAG . 'Cannot check if file exists: ' . $cgtest_config_path . PHP_EOL;
            return [];
        }
        if (!$file_exists) {
            echo ERROR_TAG . 'Cannot open config file: ' . $cgtest_config_path . PHP_EOL;
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
            echo ERROR_TAG . 'Invalid config file' . PHP_EOL;
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

    public function printPuzzlesFromDir(): void
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
        echo PHP_EOL . '[INFO] Found ' . ANSI_LIGHT_CYAN . $count . ANSI_RESET . ' puzzle solutions' . PHP_EOL;
    }

    public function printHelp(): void
    {
        echo 'Usage:' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  php src/cg-ai.php' . ANSI_RESET . ' [arguments]' . PHP_EOL
            . 'Arguments:' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --from=' . ANSI_RESET . 'LANG    set input language  [default: php]' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --to=' . ANSI_RESET . 'LANG      set output language [default: rust]' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --list' . ANSI_RESET . '         generate puzzle names list' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --test' . ANSI_RESET . '         run cgtest only' . PHP_EOL
            . ANSI_LIGHT_CYAN . '  --help' . ANSI_RESET . '         show this help' . PHP_EOL
            . 'Supported languages:' . PHP_EOL
            . '  ' . implode(', ', array_keys(self::EXTENSIONS)) . PHP_EOL
            . PHP_EOL;
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
            echo ERROR_TAG . 'Cannot check if file exists: ' . $input_file_path . PHP_EOL;
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
            echo ERROR_TAG . 'Cannot check if file exists: ' . $output_file_path . PHP_EOL;
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
            echo ERROR_TAG . 'Cannot read input file: ' . $input_file_path . PHP_EOL;
            return false;
        }
        $prompt = 'Convert following ' . $this->fromLanguage . ' code to ' . $this->toLanguage
            . ', output only the code:' . PHP_EOL;
        echo '[INFO] Calling OpenAI to convert the code: '
            . ANSI_LIGHT_CYAN . $puzzle . ANSI_RESET . ' ...';
        if (strlen($puzzle) < 40) {
            echo str_repeat('.', 40 - strlen($puzzle));
        }
        try {
            $response = $this->aiClient->responses()->create([
                'model' => self::AI_MODEL,
                'input' => $prompt . $input_file_contents,
            ]);
        } catch (\Exception $exception) {
            echo PHP_EOL . ERROR_TAG . ' OpenAI call threw an exception' . PHP_EOL;
            return false;
        }
        if (is_null($response->outputText)) {
            echo PHP_EOL . ERROR_TAG . ' OpenAI call returned error' . PHP_EOL;
            return false;
        }
        try {
            $this->filesystem->write($output_file_path, $response->outputText);
        } catch (FilesystemException | UnableToWriteFile $exception) {
            echo PHP_EOL . ERROR_TAG . 'Cannot write output file: ' . $output_file_path . PHP_EOL;
            return false;
        }
        echo ' ' . ANSI_GREEN_INV . '[OK]' . ANSI_RESET . PHP_EOL;
        return true;
    }

    public function testSolutions(): bool
    {
        echo '[INFO] Testing all AI-generated conversions with CGTest...' . PHP_EOL;
        $command = 'cgtest --config=' . $this->configFilePath . ' --stats --lang=' . $this->toLanguage;
        $execResultCode = 0;
        $execResult = system($command, $execResultCode);
        if (($execResult === false) or ($execResultCode != 0)) {
            echo ERROR_TAG . 'CGTest returned error' . PHP_EOL . PHP_EOL;
            return false;
        }
        return true;
    }

    public function convertAll(): bool
    {
        $puzzles = $this->readPuzzleList();
        if (count($puzzles) > 0) {
            echo '[INFO] Processing ' . ANSI_LIGHT_CYAN . count($puzzles) . ANSI_RESET
                . ' puzzle solutions' . PHP_EOL;
            echo '[INFO] Converting ' . ANSI_LIGHT_CYAN . $this->fromLanguage . ANSI_RESET
                . ' code to ' . ANSI_LIGHT_CYAN . $this->toLanguage . ANSI_RESET
                . ' using OpenAI model ' . ANSI_LIGHT_CYAN . self::AI_MODEL . ANSI_RESET . PHP_EOL;
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
        echo ' puzzle solutions' . PHP_EOL . PHP_EOL;
        if ($count_converted > 0) {
            return $this->testSolutions();
        }
        return true;
    }

    /**
     * @param array<int, string> $cli_arguments
     */
    public function cli_run(array $cli_arguments): bool
    {
        $from_changed = false;
        $to_changed = false;
        $list = false;
        $help = false;
        $test = false;
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
            echo ERROR_TAG . 'Invalid argument: ' . ANSI_YELLOW . $cli_arguments[$i] . ANSI_RESET . PHP_EOL;
            $invalid = true;
        }
        if (($help && ($list || $test)) || ($list && ($help || $test)) || ($test && ($help || $list))) {
            echo ERROR_TAG . '--help, --list, --test arguments must be exclusive' . PHP_EOL;
            $invalid = true;
        }
        if ($invalid || $help) {
            $this->printHelp();
            return !$invalid;
        }
        if ($list) {
            $this->printPuzzlesFromDir();
            return true;
        }
        if ($test) {
            return $this->testSolutions();
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
}
