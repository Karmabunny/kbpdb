<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * This is a array format for describing logs from the parser and sync tools.
 *
 * The format is simple - an array of pairs.
 *
 * 1. the 'handle' - section, heading, message, query
 * 2. the contents, typically a string
 *
 * For example:
 *
 * ```
 * [
 *    ['section', 'Tables'],
 *    ['heading', 'MISSING - Table ~sample'],
 *    ['query', 'CREATE TABLE ~sample (...)'],
 *    ['section', 'Errors'],
 *    ['message', 'Oh it was bad'],
 * ]
 * ```
 *
 * This can be easily formatted to HTML, JSON, Markdown, CLI outputs, etc.
 *
 * It's also possible to pick out the 'query' parts and run them separately.
 * For example PdbSync includes a section called 'Fixes' that includes 'query'
 * components. An interface could highlight this and provide a option to
 * run these.
 *
 * Note: PdbSync and PdbParser both compose their logs manually. That is, not
 * using the helpers provided here.
 *
 * @package karmabunny\pdb
 */
class PdbLog implements IteratorAggregate
{
    const SECTION = 'section';
    const HEADING = 'heading';
    const MESSAGE = 'message';
    const QUERY = 'query';


    /** @var array{0:string,1:string}[] */
    protected $log = [];

    /** @var string[] */
    protected $errors = [];


    public function __construct(array $log = [])
    {
        $this->log = $log;
    }


    /** @inheritdoc */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->log);
    }


    public function getLog(): array
    {
        return $this->log;
    }


    public function isEmpty(): bool
    {
        return empty($this->log);
    }


    public function getErrors(): array
    {
        return $this->errors;
    }


    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }


    public function section(string $section)
    {
        $this->log[] = [ self::SECTION, $section ];
    }


    public function heading(string $heading)
    {
        $this->log[] = [ self::HEADING, $heading ];
    }


    public function message(string $message, bool $error = false)
    {
        $this->log[] = [ self::MESSAGE, $message ];

        if ($error) {
            $this->errors[] = $message;
        }
    }


    public function query(string $query)
    {
        $this->log[] = [ self::QUERY, $query ];
    }


    /**
     * Print the log to the console.
     *
     * This is formatted in markdown too.
     *
     * TODO Add (optional) colours.
     *
     * @param static|array $log
     * @return void echos output
     */
    public static function print($log)
    {
        if ($log instanceof static) {
            $log = $log->getLog();
        }

        foreach ($log as [$type, $body]) {
            switch ($type) {
                case 'section':
                    echo ' ' . $body . PHP_EOL;
                    echo '--------------' . PHP_EOL;
                    echo PHP_EOL;
                break;

                case 'heading':
                    echo '## ' . $body . PHP_EOL;
                    echo PHP_EOL;
                break;

                case 'query':
                    echo '> ' . str_replace("\n", "\n> ", $body) . PHP_EOL;
                    echo PHP_EOL;
                    break;

                case 'message':
                    echo '!! ' . $body . PHP_EOL;
                    echo PHP_EOL;
            }
        }
        echo PHP_EOL;
    }
}