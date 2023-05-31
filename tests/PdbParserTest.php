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


    /**
     * TODO mysql only
     */
    public function testEnums()
    {
        $parser = $this->parse();

        $clubs = $parser->getTable('clubs');

        $column = $clubs->columns['status'];
        $enum = "ENUM('new','active','retired')";

        // Regular enums.
        $this->assertEquals(false, $column->is_nullable);
        $this->assertEquals('string', $column->getPhpType());
        $this->assertEquals($enum, $column->type);

        // XML style enums.
        $column = $clubs->columns['type'];
        $enum = "ENUM('one','two','three')";
        $this->assertEquals(true, $column->is_nullable);
        $this->assertEquals('string|null', $column->getPhpType());
        $this->assertEquals($enum, $column->type);
    }
}
