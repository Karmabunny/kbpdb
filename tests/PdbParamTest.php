<?php

use karmabunny\pdb\Models\PdbParam;
use karmabunny\pdb\PdbQuery;
use kbtests\Database;
use PHPUnit\Framework\TestCase;


class PdbParamTest extends TestCase
{

    public static function dataParse(): array
    {
        return [
            'is null' => [
                'null',
                ['IS', null],
                ['IS', 'column' => null],
                'column IS NULL',
            ],
            'is not null' => [
                'not null',
                ['IS NOT', null],
                ['IS NOT', 'column' => null],
                'column IS NOT NULL',
            ],
            'equal string (implicit)' => [
                'test',
                ['=', 'test'],
                ['=', 'column' => 'test'],
                'column = ?',
            ],
            'equal numeric (implicit)' => [
                1.23,
                ['=', 1.23],
                ['=', 'column' => 1.23],
                'column = ?',
            ],
            'equal numeric (explicit)' => [
                '= 1.23',
                ['=', '1.23'],
                ['=', 'column' => '1.23'],
                'column = ?',
            ],
            'not equal' => [
                '!= abc',
                ['!=', 'abc'],
                ['!=', 'column' => 'abc'],
                'column != ?',
            ],
            'greater than' => [
                '> 1.23',
                ['>', '1.23'],
                ['>', 'column' => '1.23'],
                'column > ?',
            ],
            'less than' => [
                '< 1.23',
                ['<', '1.23'],
                ['<', 'column' => '1.23'],
                'column < ?',
            ],
            'between' => [
                'between 1, 2',
                ['BETWEEN', '1', '2'],
                ['BETWEEN', 'column' => ['1', '2']],
                'column BETWEEN ? AND ?',
            ],
            'like' => [
                'like %abc%',
                ['LIKE', '%abc%'],
                ['LIKE', 'column' => '%abc%'],
                'column LIKE ?',
            ],
            'begins' => [
                'begins abc',
                ['BEGINS', 'abc'],
                ['BEGINS', 'column' => 'abc'],
                'column LIKE CONCAT(?, \'%\')',
            ],
            'in (implicit)' => [
                '1, 2, 3',
                ['IN', '1', '2', '3'],
                ['IN', 'column' => ['1', '2', '3']],
                'column IN (...)',
            ],
            'in (explicit)' => [
                'in abc, def, ghi',
                ['IN', 'abc', 'def', 'ghi'],
                ['IN', 'column' => ['abc', 'def', 'ghi']],
                'column IN (...)',
            ],
            'not in' => [
                'not in abc, def, ghi',
                ['NOT IN', 'abc', 'def', 'ghi'],
                ['NOT IN', 'column' => ['abc', 'def', 'ghi']],
                'column NOT IN (...)',
            ],
            'not in (array)' => [
                ['not in', 'abc', 'def', 'ghi'],
                ['NOT IN', 'abc', 'def', 'ghi'],
                ['NOT IN', 'column' => ['abc', 'def', 'ghi']],
                'column NOT IN (...)',
            ],
            'nested' => [
                ['>= 10', '<= 20'],
                ['OR', ['>=', '10'], ['<=', '20']],
                ['OR' => [['>=', 'column' => '10'], ['<=', 'column' => '20']]],
                '(column >= ? OR column <= ?)',
            ],
            'nested (and)' => [
                ['and', '>= 10', '<= 20'],
                ['AND', ['>=', '10'], ['<=', '20']],
                ['AND' => [['>=', 'column' => '10'], ['<=', 'column' => '20']]],
                '(column >= ? AND column <= ?)',
            ],
            'nested (not)' => [
                ['not', '10', '20'],
                ['NOT', ['=', '10'], ['=', '20']],
                ['NOT' => [['=', 'column' => '10'], ['=', 'column' => '20']]],
                'NOT (column = ? AND column = ?)',
            ],
            'nested (not or)' => [
                ['not or', '10', '20'],
                ['NOT OR', ['=', '10'], ['=', '20']],
                ['NOT OR' => [['=', 'column' => '10'], ['=', 'column' => '20']]],
                'NOT (column = ? OR column = ?)',
            ],
        ];
    }


    /** @dataProvider dataParse */
    public function testParse(mixed $input, array $expected, array $expectedShorthand, string $expectedSql): void
    {
        $param = PdbParam::parse($input);

        $sketch = $param->toArray();
        $this->assertEquals($expected, $sketch, 'sketch');

        $shorthand = $param->toShorthand('column');
        $this->assertEquals($expectedShorthand, $shorthand, 'shorthand (sketch)');

        $condition = $param->toCondition('column');
        $sql = $condition->getPreviewSql();
        $this->assertEquals($expectedSql, $sql, 'sql');

        $condition = PdbParam::prepare('column', $sketch);
        $sql = $condition->getPreviewSql();
        $this->assertEquals($expectedSql, $sql, 'sql (sketch)');
    }


    public static function dataQueries(): array
    {
        return [
            'not null' => [
                'not null',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IS NOT NULL',
                [],
            ],
            'null' => [
                'null',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IS NULL',
                [],
            ],
            'in' => [
                '1, 2, 3',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IN (?, ?, ?)',
                ['1', '2', '3'],
            ],
            'not in' => [
                'not in 1, 2, 3',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" NOT IN (?, ?, ?)',
                ['1', '2', '3'],
            ],
            'not in (array)' => [
                ['not in', '1', '2', '3'],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" NOT IN (?, ?, ?)',
                ['1', '2', '3'],
            ],
            'equal' => [
                '1',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" = ?',
                ['1'],
            ],
            'not equal' => [
                '!= 1',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" != ?',
                ['1'],
            ],
            'greater than' => [
                '> 1',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" > ?',
                ['1'],
            ],
            'less than' => [
                '< 1',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" < ?',
                ['1'],
            ],
            'between' => [
                'between 1, 2',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" BETWEEN ? AND ?',
                ['1', '2'],
            ],
            'like' => [
                'like %abc%',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" LIKE ?',
                ['\%abc\%'],
            ],
            'begins' => [
                'begins abc',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" LIKE CONCAT(?, \'%\')',
                ['abc'],
            ],
            'nested' => [
                ['>= 10', '<= 20'],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE ("id" >= ? OR "id" <= ?)',
                ['10', '20'],
            ],
            'nested (and)' => [
                ['and', '>= 10', '<= 20'],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE ("id" >= ? AND "id" <= ?)',
                ['10', '20'],
            ],
            'nested (not)' => [
                ['not', '10', '20'],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE NOT ("id" = ? AND "id" = ?)',
                ['10', '20'],
            ],
            'nested (not or)' => [
                ['not or', '10', '20'],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE NOT ("id" = ? OR "id" = ?)',
                ['10', '20'],
            ],
        ];
    }

    /** @dataProvider dataQueries */
    public function testQueryBuilder(mixed $condition, string $expectedSql, array $expectedParams): void
    {
        $pdb = Database::getConnection('sqlite');
        $query = new TestParamQuery($pdb);
        $query->from('test', 't');
        $query->id($condition);

        [$sql, $params] = $query->build();
        $this->assertEquals($expectedSql, $sql);
        $this->assertEquals($expectedParams, $params);
    }
}



class TestParamQuery extends PdbQuery
{

    public ?array $ids = null;


    public function id(mixed $value): static
    {
        $this->ids = PdbParam::sketch($value);
        return $this;
    }


    /** @inheritdoc */
    public function _beforeBuild(PdbQuery &$query)
    {
        parent::_beforeBuild($query);

        if ($this->ids !== null) {
            $query->andWhere(PdbParam::prepare('id', $this->ids));
        }
    }
}
