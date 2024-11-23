<?php

use karmabunny\pdb\PdbParser;
use kbtests\Models\Team;
use PHPUnit\Framework\TestCase;


class PdbModelParserTest extends TestCase
{

    public function parse()
    {
        $parser = new PdbParser();
        $parser->loadXml(__DIR__ . '/db_struct.xml');
        $parser->loadModel(Team::class);
        $parser->sanityCheck();
        return $parser;
    }


    public function testLoad()
    {
        $parser = $this->parse();
        $errors = $parser->getErrors();
        $this->assertEmpty($errors, print_r($errors, true));
    }

}
