# CG-AI: Codingame puzzle solution language converter frontend for OpenAI

![php v8.2](https://shields.io/badge/php-8.2-blue?logo=php)
![build](https://img.shields.io/github/actions/workflow/status/tbali0524/advent-of-code-solutions/qa.yml)
![license](https://img.shields.io/github/license/tbali0524/advent-of-code-solutions)

by [TBali](https://www.codingame.com/profile/08e6e13d9f7cad047d86ec4d10c777500155033)

## Usage

* install `php` and [Composer](https://getcomposer.org/)
* run `composer install`
* lint with `composer qa` as needed
    * tools are NOT listed in `composer.json` as dev dependencies. Instead, the commands must be available in the `PATH`.
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

## Using with different input or output languages

* update config file `.cgtest.cg-ai.php`
* update in `src/cg-ai.php` the line `$solver = new LanguageTransformer(...)`
