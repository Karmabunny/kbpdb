<?php

use karmabunny\pdb\Models\PdbCondition;
use karmabunny\pdb\Models\PdbRawCondition;
use karmabunny\pdb\Models\PdbSimpleCondition;
use karmabunny\pdb\Pdb;
use kbtests\Database;
use PHPUnit\Framework\TestCase;


class PdbConditionTest extends TestCase
{

    public function testBasic(): void
    {
        $pdb = Database::getConnection();

        $condition = new PdbSimpleCondition('=', 'abc.def', 1234);

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


    public function testExtendedLoose(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            "date_format(date, '%y-%m-%d')" => '2021-12-12',
            '~table.column' => '~thing',
        ]);

        $this->assertCount(2, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);

        $this->assertEquals("date_format(date, '%y-%m-%d') = ?", $clause1);
        $this->assertEquals('~table.`column` = ?', $clause2);
        $this->assertEquals(['2021-12-12', '~thing'], $values);
    }


    public function testBadStrings(): void
    {
        $pdb = Database::getConnection();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unsafe conditions/');

        $conditions = PdbCondition::fromArray([
            'big.stuff <= little.stuff',
        ]);

        $values = [];
        $conditions[0]->build($pdb, $values);
    }


    public function testStrings(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            new PdbRawCondition('big.stuff <= little.stuff'),
            new PdbRawCondition('you_better_hope_you >= \'have escaped this\''),
            new PdbRawCondition('what.will <> this_do'),
            new PdbRawCondition('~prefix in (~voodoo.magic, ~stuff.id)'),
        ]);

        $this->assertCount(4, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);
        $clause3 = $conditions[2]->build($pdb, $values);
        $clause4 = $conditions[3]->build($pdb, $values);

        $this->assertEquals('big.stuff <= little.stuff', $clause1);
        $this->assertEquals('you_better_hope_you >= \'have escaped this\'', $clause2);
        $this->assertEquals('what.will <> this_do', $clause3);
        $this->assertEquals('~prefix in (~voodoo.magic, ~stuff.id)', $clause4);
        $this->assertEquals([], $values);

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

        $condition = new PdbSimpleCondition(
            PdbSimpleCondition::NOT_IN,
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

        $condition = new PdbSimpleCondition(
            PdbSimpleCondition::NOT_IN,
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


    public function testNested(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            'not.nested' => 'ok',

            // Silly nested that does nothing.
            'or' => ['single.nested' => 'also ok'],

            // wrapped.
            ['or' => [
                'nested' => 'things',
                ['>=', 'neato' => 100],
            ]],

            // not wrapped.
            'and' => [
                'another' => 'one',
                ['one_more', 'IS NOT', null],
                '~table.i_lied' => 'last-one',
            ],
        ]);

        $this->assertCount(4, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);
        $clause3 = $conditions[2]->build($pdb, $values);
        $clause4 = $conditions[3]->build($pdb, $values);

        $this->assertEquals('`not`.`nested` = ?', $clause1);
        $this->assertEquals('(`single`.`nested` = ?)', $clause2);
        $this->assertEquals('(`nested` = ? OR `neato` >= ?)', $clause3);
        $this->assertEquals('(`another` = ? AND `one_more` IS NOT NULL AND ~table.`i_lied` = ?)', $clause4);

        $this->assertEquals(['ok', 'also ok', 'things', 100, 'one', 'last-one'], $values);
    }


    public function testNegated(): void
    {
        $pdb = Database::getConnection();

        $conditions = PdbCondition::fromArray([
            ['NOT' => ['abc' => 123]],
            ['NOT', 'not' => 456],
            ['NOT' => [
                ['not' => null],
                ['NOT', 'def' => 'not'],
            ]],
        ]);

        $this->assertCount(3, $conditions);

        $values = [];
        $clause1 = $conditions[0]->build($pdb, $values);
        $clause2 = $conditions[1]->build($pdb, $values);
        $clause3 = $conditions[2]->build($pdb, $values);

        $this->assertEquals('NOT (`abc` = ?)', $clause1);
        $this->assertEquals('NOT (`not` = ?)', $clause2);
        $this->assertEquals('NOT (`not` IS NULL AND NOT (`def` = ?))', $clause3);

        $this->assertEquals([123, 456, 'not'], $values);
    }
}
