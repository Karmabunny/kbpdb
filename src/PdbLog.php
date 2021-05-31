<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

/**
 *
 * @package karmabunny\pdb
 */
class PdbLog
{
    const SECTION = 'section';
    const HEADING = 'heading';
    const MESSAGE = 'message';
    const QUERY = 'query';


    protected $log = [];


    public function __construct(array $log = [])
    {
        $this->log = $log;
    }


    public function getLog()
    {
        return $this->log;
    }


    public function section(string $section)
    {
        $this->log[] = [ self::SECTION, $section ];
    }


    public function heading(string $heading)
    {
        $this->log[] = [ self::HEADING, $heading ];
    }


    public function message(string $message)
    {
        $this->log[] = [ self::MESSAGE, $message ];
    }


    public function query(string $query)
    {
        $this->log[] = [ self::QUERY, $query ];
    }


    /**
     *
     * @param array $log
     * @return void echos output
     */
    public static function print(array $log)
    {
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
        echo 'Done!' . PHP_EOL;
        echo PHP_EOL;
    }
}