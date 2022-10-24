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
            throw ConnectionException::create($exception, $db ?? null)
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
            $named = [];

            // Convert named parameters to ? and store them for later. The
            // order of theses parameters determine the binding order - this
            // must be preserved. Duplicates are permitted.
            $query = preg_replace_callback('/:([a-z][^\s]*)/i', function ($matches) use (&$named) {
                $named[] = $matches[1];
                return '?';
            }, $query);

            // The actual prepare.
            $stmt = $db->prepare($query);

            // Some additional error checking but the REPORT_ERROR mode should
            // be doing this already.
            if ($stmt === false) {
                throw new mysqli_sql_exception($db->error, $db->errno);
            }

            // Our PDO-style wrapper object.
            return new MysqliStatement($db, $stmt, $query, $named);
        }
        catch (mysqli_sql_exception $ex) {
            // Only emit our own exceptions.
            throw QueryException::create($ex, $db)
                ->setQuery($query);
        }
    }


    /** @inheritdoc */
    public function quoteValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return (int) $value;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return $value;
        }

        $db = $this->getConnection();
        return "'" . $db->escape_string($value) . "'";
    }
}
