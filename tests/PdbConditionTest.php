<?php

use karmabunny\pdb\Models\PdbCondition;
use karmabunny\pdb\Pdb;
use kbtests\Database;
use PHPUnit\Framework\TestCase;


class PdbConditionTest extends TestCase
{

    public function testBasic(): void
    {
        $pdb = Database::getConnection();

        $condition = new PdbCondition('=', 'abc.def', 1234);

        $values = [];
        $clause = $condition->build($pdb, $values);

        // Assuming MySQL escapes.
        $this->assertEquals([1234], $values);
        $this->assertEquals("`abc`.`def` = ?", $clause);

    }


    public function testArrays(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            ['a12.b34', '>', 5.67],
            ['hello', '!=', 'world    '],
        ]);

        $this->assertCount(2, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);

        $this->assertEquals('`a12`.`b34` > ?', $clause1);
        $this->assertEquals('`hello` != ?', $clause2);
        $this->assertEquals([5.67, 'world    '], $values);
    }


    public function testKeyed(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            ['cool.beans' => '\'BEANS \''],
            ['hello' => null],
            ['world', 'is not', null],
        ]);

        $this->assertCount(3, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);
        $clause3 = $conditions[2]->build($pdb, $values);

        $this->assertEquals('`cool`.`beans` = ?', $clause1);
        $this->assertEquals('`hello` IS NULL', $clause2);
        $this->assertEquals('`world` IS NOT NULL', $clause3);
        $this->assertEquals(['\'BEANS \''], $values);
    }


    public function testKeyedNotNested(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            'nested' => 'inline',
            'big.hacks' => null,
        ]);

        $this->assertCount(2, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);

        $this->assertEquals('`nested` = ?', $clause1);
        $this->assertEquals('`big`.`hacks` IS NULL', $clause2);
        $this->assertEquals(['inline'], $values);
    }


    public function testStrings(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            'big.stuff <= little.stuff',
            'you_better_hope_you >= \'have escaped this\'',
            'what.will <> this_do',
        ]);

        $this->assertCount(3, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);
        $clause3 = $conditions[2]->build($pdb, $values);

        $this->assertEquals('`big`.`stuff` <= `little`.`stuff`', $clause1);
        $this->assertEquals('`you_better_hope_you` >= ?', $clause2);
        $this->assertEquals('`what`.`will` <> `this_do`', $clause3);
        $this->assertEquals(['have escaped this'], $values);

        // TODO Test bad string
        // - 'non.scalars in (\'chaos\', \'anarchy\', mess)'
        // - 'bad == operators',
        // - 'escapes``in = bad_places',
        // - oh_look != spaces in things',
    }


    public function testNoBindValue(): void
    {
        $pdb = Database::getConnection();
        if (!Database::isConnected()) $this->markTestSkipped();

        $condition = new PdbCondition(
            PdbCondition::NOT_IN,
            'what.total',
            ['bollocks', 'mouth protection'],
            Pdb::QUOTE_VALUE
        );

        $values = [];
        $clause = $condition->build($pdb, $values);

        $this->assertEmpty($values);
        $this->assertEquals('`what`.`total` NOT IN (\'bollocks\', \'mouth protection\')', $clause);
    }


    public function testNoBindField(): void
    {
        $pdb = Database::getConnection();
        if (!Database::isConnected()) $this->markTestSkipped();

        $condition = new PdbCondition(
            PdbCondition::NOT_IN,
            'what.total',
            ['bollocks', 'mouth_protection'],
            Pdb::QUOTE_FIELD
        );

        $condition->validate();

        $values = [];
        $clause = $condition->build($pdb, $values);

        $this->assertEmpty($values);
        $this->assertEquals('`what`.`total` NOT IN (`bollocks`, `mouth_protection`)', $clause);

        // TODO Test validate of column arrays.
    }
}
