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

}
