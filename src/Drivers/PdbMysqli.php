<?php

namespace karmabunny\pdb\Drivers;

use InvalidArgumentException;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\MysqliStatement;
use karmabunny\pdb\PdbConfig;
use mysqli;
use mysqli_sql_exception;

/**
 * A Mysqli driver for Pdb.
 *
 * This provides compatibility for Sprout 2 sites.
 *
 * @package karmabunny\pdb
 */
class PdbMysqli extends PdbMysql
{

    /** @var mysqli */
    protected $connection;


    /** @inheritdoc */
    public static function connect($config)
    {
        if (!($config instanceof PdbConfig)) {
            $config = new PdbConfig($config);
        }

        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $db = new mysqli($config->host, $config->user, $config->pass, $config->database, $config->port);

            $db->set_charset($config->character_set);
        }
        catch (mysqli_sql_exception $exception) {
            throw ConnectionException::create($exception)
                ->setDsn($config->getDsn());
        }

        return $db;
    }


    /** @inheritdoc */
    public function prepare(string $query)
    {
        $db = $this->getConnection();
        $query = $this->insertPrefixes($query);

        try {
            // TODO uh, something.
            // $named = [];
            // $query = preg_replace_callback("/:[^\s]+/", function ($matches) use (&$named) {
            //     [$name] = $matches;
            //     $named[] = $name;
            //     return '?';
            // }, $query);

            $result = $db->prepare($query);

            if ($result === false) {
                throw new mysqli_sql_exception($db->error, $db->errno);
            }

            return new MysqliStatement($db, $result, $query);
        }
        catch (mysqli_sql_exception $ex) {
            throw QueryException::create($ex)
                ->setQuery($query);
        }
    }


    /** @inheritdoc */
    public function quoteValue($value): string
    {
        $db = $this->getConnection();
        return $db->escape_string($value);
    }

}
