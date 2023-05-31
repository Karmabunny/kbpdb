<?php

use karmabunny\pdb\PdbParser;
use PHPUnit\Framework\TestCase;


class PdbParserTest extends TestCase
{

    public function parse()
    {
        $parser = new PdbParser();
        $parser->loadXml(__DIR__ . '/db_struct.xml');
        $parser->sanityCheck();
        return $parser;
    }


    public function testLoad()
    {
        $parser = $this->parse();
        $errors = $parser->getErrors();
        $this->assertEmpty($errors, print_r($errors, true));
    }


    public function testAllowNull()
    {
        $parser = $this->parse();
        $clubs = $parser->getTable('clubs');

        // Explicit true.
        $column = $clubs->columns['date_deleted'];
        $this->assertEquals(true, $column->is_nullable);
        $this->assertEquals(null, $column->default);

        // Explicit false.
        $column = $clubs->columns['date_added'];
        $this->assertEquals(false, $column->is_nullable);
        $this->assertEquals(null, $column->default);

        // Implicit true.
        $column = $clubs->columns['flags'];
        $this->assertEquals(true, $column->is_nullable);
        $this->assertEquals(null, $column->default);
    }
}
