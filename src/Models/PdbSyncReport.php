<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

/**
 *
 * @package karmabunny\pdb
 */
class PdbSyncReport
{
    /** @var PdbSyncReportItem[] */
    public $tables;

    /** @var PdnSyncReportItem[] */
    public $views;


    public function __construct($config)
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }


    public function toText(): string
    {
        ob_start();

        if ($this->views) {
            echo "Tables\n";

            foreach ($this->tables as $item) {
                echo $item->toText() . PHP_EOL;
            }
            echo PHP_EOL;
        }

        if ($this->views) {
            echo "Views\n";

            foreach ($this->tables as $item) {
                echo $item->toText() . PHP_EOL;
            }
            echo PHP_EOL;
        }

        return ob_get_clean() ?: '';
    }
}
