<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

class PdbSyncReportItem
{
    /** @var string */
    public $header;

    /** @var string */
    public $query;

    /** @var string|null */
    public $error;


    public function __construct($config)
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }


    public function toText(): string
    {
        ob_start();

        echo $this->header . PHP_EOL;
        echo PHP_EOL;
        echo '> ' . $this->query . PHP_EOL;

        if ($this->error) {
            echo PHP_EOL;
            echo '!! ' . $this->error . PHP_EOL;
        }

        echo PHP_EOL;
        return ob_get_clean() ?: '';
    }
}
