<?php

use karmabunny\kb\Time;
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
                ['IS', 'null'],
                'column IS NULL',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IS NULL',
                [],
            ],
            'is not null' => [
                'not null',
                ['IS NOT', 'null'],
                'column IS NOT NULL',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IS NOT NULL',
                [],
            ],
            'equal string (implicit)' => [
                'test',
                ['=', 'test'],
                'column = ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" = ?',
                ['test'],
            ],
            'equal numeric (implicit)' => [
                1.23,
                ['=', 1.23],
                'column = ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" = ?',
                [1.23],
            ],
            'equal numeric (explicit)' => [
                '= 1.23',
                ['=', '1.23'],
                'column = ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" = ?',
                ['1.23'],
            ],
            'not equal' => [
                '!= abc',
                ['!=', 'abc'],
                'column != ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" != ?',
                ['abc'],
            ],
            'greater than' => [
                '> 1.23',
                ['>', '1.23'],
                'column > ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" > ?',
                ['1.23'],
            ],
            'less than' => [
                '< 1.23',
                ['<', '1.23'],
                'column < ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" < ?',
                ['1.23'],
            ],
            'between' => [
                'between 1, 2',
                ['BETWEEN', '1', '2'],
                'column BETWEEN ? AND ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" BETWEEN ? AND ?',
                ['1', '2'],
            ],
            'like' => [
                'like %abc%',
                ['LIKE', '%abc%'],
                'column LIKE ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" LIKE ?',
                ['\%abc\%'],
            ],
            'begins' => [
                'begins abc def',
                ['BEGINS', 'abc def'],
                'column LIKE CONCAT(?, \'%\')',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" LIKE CONCAT(?, \'%\')',
                ['abc def'],
            ],
            'in (implicit)' => [
                '1, 2, 3',
                ['IN', '1', '2', '3'],
                'column IN (...)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IN (?, ?, ?)',
                ['1', '2', '3'],
            ],
            'in (string)' => [
                'abc def, foo\, bar, test',
                ['IN', 'abc def', 'foo, bar', 'test'],
                'column IN (...)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IN (?, ?, ?)',
                ['abc def', 'foo, bar', 'test'],
            ],
            'in (explicit)' => [
                'in abc def, foo\, bar, test',
                ['IN', 'abc def', 'foo, bar', 'test'],
                'column IN (...)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" IN (?, ?, ?)',
                ['abc def', 'foo, bar', 'test'],
            ],
            'not in' => [
                'not in abc, def, ghi',
                ['NOT IN', 'abc', 'def', 'ghi'],
                'column NOT IN (...)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" NOT IN (?, ?, ?)',
                ['abc', 'def', 'ghi'],
            ],
            'not in (array)' => [
                ['not in', 'abc', 'def, ghi', 'ghi\, test'],
                ['NOT IN', 'abc', 'def, ghi', 'ghi\, test'],
                'column NOT IN (...)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" NOT IN (?, ?, ?)',
                ['abc', 'def, ghi', 'ghi\, test'],
            ],
            'nested' => [
                '>= 10, <= 20',
                ['OR', ['>=', '10'], ['<=', '20']],
                '(column >= ? OR column <= ?)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE ("id" >= ? OR "id" <= ?)',
                ['10', '20'],
            ],
            'nested (and)' => [
                'and >= 10, <= 20',
                ['AND', ['>=', '10'], ['<=', '20']],
                '(column >= ? AND column <= ?)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE ("id" >= ? AND "id" <= ?)',
                ['10', '20'],
            ],
            'nested (not)' => [
                'not 10, 20',
                ['NOT', ['=', '10'], ['=', '20']],
                'NOT (column = ? AND column = ?)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE NOT ("id" = ? AND "id" = ?)',
                ['10', '20'],
            ],
            'nested (not or)' => [
                'not or 10, 20',
                ['NOT OR', ['=', '10'], ['=', '20']],
                'NOT (column = ? OR column = ?)',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE NOT ("id" = ? OR "id" = ?)',
                ['10', '20'],
            ],
            'between dates' => [
                ['between', new DateTime('2026-01-01'), new DateTime('2026-12-31')],
                ['BETWEEN', ['date' => '2026-01-01 00:00:00.000000', 'timezone' => 'UTC'], ['date' => '2026-12-31 00:00:00.000000', 'timezone' => 'UTC']],
                'column BETWEEN ? AND ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" BETWEEN ? AND ?',
                [new DateTime('2026-01-01'), new DateTime('2026-12-31')],
            ],
            'no relative dates' => [
                '> today',
                ['>', 'today'],
                'column > ?',
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "id" > ?',
                ['today'],
            ],
        ];
    }


    /** @dataProvider dataParse */
    public function testParse(mixed $input, array $expected, string $expectedWhere, $expectedSql, $expectedParams): void
    {
        $pdb = Database::getConnection('sqlite');

        $param = PdbParam::parse($input);

        // A serialized form, that can also be parsed back in.
        $array = $param->toArray();
        $this->assertEquals($expected, $array, 'array');

        // Simple form, midly useful.
        $condition = $param->toCondition('column');
        $sql = $condition->getPreviewSql();
        $this->assertEquals($expectedWhere, $sql, 'where');

        // Full test with a query builder.
        $query = new TestParamQuery($pdb);
        $query->from('test', 't');
        $query->id($input);

        [$sql, $params] = $query->build();
        $this->assertEquals($expectedSql, $sql, 'sql');
        $this->assertEquals($expectedParams, $params, 'params');

        // Re-parsing the serialised form.
        $array = PdbParam::fromJson($array)->toArray();
        $this->assertEquals($expected, $array, 'array (re-parsed');

        // Re-parsed within a query.
        $query = new TestParamQuery($pdb);
        $query->from('test', 't');
        $query->id($array);

        [$sql, $params] = $query->build();
        $this->assertEquals($expectedSql, $sql, 'sql (re-parsed)');
        $this->assertEquals($expectedParams, $params, 'params (re-parsed)');
    }


    public static function dataParseDates(): array
    {
        $format = function(string $date) {
            $date = new DateTime($date);
            $date = json_decode(json_encode($date), true);
            unset($date['timezone_type']);
            return $date;
        };

        return [
            // Same as before.
            'between dates' => [
                ['between', new DateTime('2026-01-01'), new DateTime('2026-12-31')],
                ['BETWEEN', ['date' => '2026-01-01 00:00:00.000000', 'timezone' => 'UTC'], ['date' => '2026-12-31 00:00:00.000000', 'timezone' => 'UTC']],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "dateCreated" BETWEEN ? AND ?',
                [new DateTime('2026-01-01'), new DateTime('2026-12-31')],
            ],
            // Parsing relative dates.
            'between dates (relative)' => [
                ['between', '9am monday next week', '5pm friday next week'],
                ['BETWEEN', $format('9am monday next week'), $format('5pm friday next week')],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "dateCreated" BETWEEN ? AND ?',
                [new DateTime('9am monday next week'), new DateTime('5pm friday next week')],
            ],
            'older than today' => [
                '< today',
                ['<', $format('today')],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "dateCreated" < ?',
                [new DateTime('today')],
            ],
            'absolute dates' => [
                '= 2026-01-01',
                ['=', '2026-01-01'],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "dateCreated" = ?',
                ['2026-01-01'],
            ],
            'invalid dates' => [
                '= invalid',
                ['=', 'invalid'],
                'SELECT "t".* FROM "pdb_test" AS "t" WHERE "dateCreated" = ?',
                ['invalid'],
            ]
        ];
    }


    /** @dataProvider dataParseDates */
    public function testParseDates(mixed $input, array $expected, string $expectedSql, array $expectedParams)
    {
        $pdb = Database::getConnection('sqlite');

        $param = PdbParam::parse($input, ['relativeDates']);

        // Serialized form.
        $array = $param->toArray();
        $this->assertEquals($expected, $array, 'array');

        // Parsed into a query.
        $query = new TestParamQuery($pdb);
        $query->from('test', 't');
        $query->dateCreated($array);

        [$sql, $params] = $query->build();
        $this->assertEquals($expectedSql, $sql, 'sql');
        $this->assertEquals($expectedParams, $params, 'params');
    }
}



class TestParamQuery extends PdbQuery
{

    public mixed $ids = null;

    public mixed $dateCreated = null;


    public function id(mixed $value): static
    {
        $this->ids = $value;
        return $this;
    }


    public function dateCreated(mixed $value): static
    {
        $this->dateCreated = $value;
        return $this;
    }


    /** @inheritdoc */
    public function _beforeBuild(PdbQuery &$query)
    {
        parent::_beforeBuild($query);

        if ($this->ids !== null) {
            $query->andWhere(PdbParam::prepare('id', $this->ids));
        }

        if ($this->dateCreated !== null) {
            $query->andWhere(PdbParam::prepare('dateCreated', $this->dateCreated));
        }
    }
}
