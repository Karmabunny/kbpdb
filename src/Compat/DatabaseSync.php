<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Compat;

use DOMDocument;
use Exception;
use InvalidArgumentException;
use karmabunny\kb\Enc;
use karmabunny\kb\XMLException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\SyncActions;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;
use PDOException;

/**
 * Database sync.
 *
 * This is a wrapper around PdbParser + PdbSync with some additions so it
 * feels like home.
 *
 * Extend this class and implement the `getPdb()` method.
 */
abstract class DatabaseSync
{
    /** @var bool */
    public $act;

    /** @var PdbParser */
    public $parser;

    /** @var PdbSync */
    public $sync;


    /**
     * Initial loading + set up.
     *
     * @param bool $act If queries should be executed, false for a dry-run.
     */
    public function __construct(bool $act)
    {
        $this->act = $act;
        $this->sync = new PdbSync(static::getPdb());
        $this->parser = new PdbParser();
    }


    /**
     * The pdb connection instance.
     *
     * @return Pdb
     */
    public static abstract function getPdb(): Pdb;


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


    public function hasLoadErrors()
    {
        return $this->parser->hasErrors();
    }


    /**
    * Return HTML of the load or sanity check errors
    *
    * @return string HTML
    **/
    public function getErrorsHtml()
    {
        $out = '';

        foreach ($this->getErrors() as $file => $errors) {
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
     * @return string
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws QueryException
     */
    public function updateDatabase($do = null)
    {
        $this->sync->migrate($this->parser, $do);
        $log = $this->sync->execute($this->act);

        ob_start();

        foreach ($log as [$type, $body]) {
            switch ($type) {
                case 'section':
                    echo "<h3>{$body}</h3>\n";
                    break;

                case 'heading':
                    $body = explode(' - ', $body, 2);
                    $title = $body[0];
                    echo "<p class='heading'><b>{$body[0]}</b>";
                    if (!empty($body[1])) echo $body[1];
                    echo "</p>\n";
                    break;

                case 'query':
                    $body = Enc::html($body);
                    echo "<pre class='query'>{$body}</pre>\n";
                    break;

                case 'message':
                    echo "<p>{$body}</p>";
                    break;
            }
        }

        return ob_get_clean();
    }
}
