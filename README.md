# CG-AI: Codingame puzzle solution language converter frontend for OpenAI

![php v8.2](https://shields.io/badge/php-8.2-blue?logo=php)
![build](https://img.shields.io/github/actions/workflow/status/tbali0524/advent-of-code-solutions/qa.yml)
![license](https://img.shields.io/github/license/tbali0524/advent-of-code-solutions)

by [TBali](https://www.codingame.com/profile/08e6e13d9f7cad047d86ec4d10c777500155033)

## About

This is a simple tool I used for an AI experiment in September-October 2025. I checked how good are current LLMs (e.g. gpt-5) to convert code from one programming language to another. I used it to convert my existing, manual Codingame puzzle solutions in PHP to Rust.
It uses OpenAI REST API for the conversion and my other CG-related tool for local testing of the results.

Note: the tool does not send the puzzle statement as part of the prompt, only the original solution source code.

## Setup

* install `php` and [Composer](https://getcomposer.org/)
* run `composer install`
* optional: lint with `composer qa` as needed
    * PHP dev tools (phpcs, php-cs-fixer, phpstan), are NOT listed in `composer.json` as dev dependencies. Instead, the commands must be available in the `PATH`.
* copy `.env.example` to `.env`
* edit `.env`, add your OpenAI API key
* copy your php puzzle solution source files to `php/`, follow the naming convention.
* add puzzle names (without path and extension) to the config file `.cgtest.cg-ai.php`
    * use `composer start -- --list` to print directory listing to console
* clone [CGTest](https://github.com/tbali0524/cgtest) repo from GitHub
    * copy test cases to `.tests/input/` and `.tests/expected/`
    * add `cgtest` to your path
* run with `composer start`
* see result in `rust/`
* clean temporary files with `composer clean`

## CLI usage

```txt
Usage:
  php src/cg-ai.php [arguments]
Arguments:
  --from=LANG    set input language  [default: php]
  --to=LANG      set output language [default: rust]
  --list         generate puzzle names list
  --test         run cgtest only
  --submit       submit generated solutions to Codingame [WIP, NOT WORKING]
  --help         show this help
```

* for using non-default languages also update the config file `.cgtest.cg-ai.php`

## My own usage experience

Results of using the tool to AI-translate my own puzzle solutions (originally written manually in PHP) to Rust:

* easy puzzles
    * success rate with `gpt-5`: ~95%
* medium puzzles
    * success rate with `gpt-5-mini`: ~90%
* hard puzzles
    * success rate with `gpt-5-mini`: ~80%
    * success rate with `gpt-5`: additional +10%
* expert puzzles
    * success rate with `gpt-5`: ~80%

* Totals
    * translation attempts: 841
    * successful: 776
    * with build failure: 42
    * with partial test failure: 23
        * most test failures could be fixed manually by small changes.
    * submitted to CG: 647 (I had 166 puzzles with manual translations already submitted earlier)

* overall success rate (after some manual fixes): ~93%

* OpenAI usage
    * spending: ~17€ (could have been less if starting with `gpt-5-mini` for easy puzzles)
    * 913 requests
    * 961K input tokens
    * 2929K output tokens

## TODOs

* Add support and test with local run of Ollama open source models (e-g- Gemma3)
* Fix the `--submit` functionality

### Internal note

What is the correct way to submit a puzzle solution to Codingame without using the UI, through API? What I tried but still not working:

* (1) Sending POST to `https://www.codingame.com/services/Puzzle/generateSessionFromPuzzlePrettyId` with request body `[my-numeric-userid, "puzzle-name", false]`, and with cookies `cgSession` and `godfatherId` set. The response should have a `handle` element to use in next step.
* (2) Sending POST to `https://www.codingame.com/services/TestSession/submit` with request body `["handle", {"code": "my-source-code", "programmingLanguageId": "python-or-whatever"}, null]`. Response should be a _report-id_ for next step.
* (3) Sending POST to `https://www.codingame.com/services/Report/findReportBySubmission` with request body `[ report-id ]`. Response should be a JSON with a `score` element in it if the validation finished, and a different object if still in progress.

However at step (1) I receive a 200 OK response with the string `null` as response.
