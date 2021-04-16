<?php

use karmabunny\pdb\PdbParser;
use PHPUnit\Framework\TestCase;


class PdbParserTest extends TestCase
{

    public function testLoad()
    {
        $parser = new PdbParser();
        $parser->loadXml(__DIR__ . '/db_struct.xml');
        $parser->sanityCheck();

        echo print_r($parser->getErrors(), true), PHP_EOL;
    }
}
