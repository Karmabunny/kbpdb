<?php

use karmabunny\pdb\PdbParser;
use kbtests\Database;
use PHPUnit\Framework\TestCase;


class PdbParserTest extends TestCase
{

    public function testLoad()
    {
        $pdb = Database::getConnection();

        $parser = new PdbParser($pdb);
        $parser->loadXml(__DIR__ . '/db_struct.xml');
        $parser->sanityCheck();

        echo print_r($parser->getErrors(), true), PHP_EOL;
    }
}
