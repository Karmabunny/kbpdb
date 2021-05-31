<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DOMDocument;
use Exception;
use InvalidArgumentException;
use karmabunny\kb\Enc;
use karmabunny\kb\XMLException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\SyncActions;
use PDOException;

/**
 * Database sync.
 *
 * This is a wrapper around PdbParser + PdbSync with some additions so it
 * feels like home.
 *
 * It's recommended to create your abstractions around the core classes however.
 * You'll have a lot more power of over the logging output and error handling.
 */
class DatabaseSync
{
    /** @var Pdb */
    public $pdb;

    /** @var bool */
    public $act;

    /** @var PdbParser */
    public $parser;

    /** @var PdbSync */
    public $sync;


    /**
     *
     * @param Pdb|PdbConfig|array $config
     * @param bool $act
     */
    public function __construct($config, bool $act)
    {
        if ($config instanceof Pdb) {
            $this->pdb = $config;
        }
        else {
            $this->pdb = Pdb::create($config);
        }

        $this->act = $act;

        $this->sync = new PdbSync($this->pdb);
        $this->parser = new PdbParser();
    }


    /**
     *
     * @param string|DOMDocument $dom
     * @return void
     * @throws XMLException
     * @throws Exception
     */
    public function loadXml($dom)
    {
        $this->parser->loadXml($dom);
    }


    /**
     *
     * @return bool
     */
    public function sanityCheck()
    {
        $this->parser->sanityCheck();
        return $this->parser->hasErrors();
    }


    public function getErrors()
    {
        return $this->parser->getErrors();
    }


    /**
    * Return HTML of the load or sanity check errors
    *
    * @return string HTML
    **/
    public function getErrorsHtml()
    {
        $out = '';

        foreach ($this->errors as $file => $errors) {
            $out .= "<h3>Errors in " . Enc::html($file) . "</h3>";
            $out .= "<ul>";
            foreach ($errors as $error) {
                $out .= "<li>" . Enc::html($error) . "</li>";
            }
            $out .= "</ul>";
        }

        return $out;
    }


    /**
     *
     * @return true|string[]
     */
    public function checkConnPermissions()
    {
        return $this->sync->checkConnPermissions();
    }


    /**
     *
     * @param SyncActions|array|null $do
     * @return void
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws Exception
     * @throws QueryException
     */
    public function updateDatabase($do = null)
    {
        $log = $this->sync->updateDatabase($this->parser, $do);

        ob_start();
        PdbSync::printMigration($log);
        return ob_end_clean();
    }
}
