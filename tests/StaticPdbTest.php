<?php

use karmabunny\pdb\Compat\StaticPdb;
use karmabunny\pdb\Exceptions\TransactionRecursionException;
use karmabunny\pdb\PdbConfig;
use PHPUnit\Framework\TestCase;


class Pdb extends StaticPdb
{
    public static function getConfig(?string $name = null): PdbConfig
    {
        $config = require __DIR__ . '/config.php';
        $config['prefix'] = 'sprout_';
        $config['hacks'] = [
            'no_engine_substitution',
        ];

        return new PdbConfig($config);
    }
}


/**
 * This test suite is from Sprout 3.
 *
 * This largely tests a static version of Pdb.
 */
class StaticPdbTest extends TestCase
{

    public function setUp(): void
    {
        // Only run create and fill queries if table doesn't exist
        try {
            Pdb::q("SELECT COUNT(*) FROM sprout_pdb_test", [], 'val');
            return;
        } catch (Exception $ex) {
        }

        $insert = "INSERT INTO sprout_pdb_test (name, value) VALUES (?, ?)";
        $qs = [
            [
                "CREATE TABLE IF NOT EXISTS sprout_pdb_test (\n" .
                    "    id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
                    "    name VARCHAR(20) NOT NULL,\n" .
                    "    value DECIMAL(5,2) NOT NULL,\n" .
                    "    PRIMARY KEY (id)\n" .
                    ")",
                []
            ],
            [$insert, ['A', 5]],
            [$insert, ['B', 12.3]],
            [$insert, ['C', 987.65]],
        ];
        foreach ($qs as $q_p) {
            list($q, $params) = $q_p;
            Pdb::q($q, $params, 'count');
        }
    }


    public function tearDown(): void
    {
        Pdb::q("DROP TABLE IF EXISTS sprout_pdb_test", [], 'pdo');
        Pdb::q("DROP TABLE IF EXISTS typeof", [], 'pdo');
    }



    public function dataStripStrings()
    {
        return [
            ['test', 'test'],
            // 1x single quote
            ['te\'st', 'te\'st'],
            ['\'test', '\'test'],
            ['test\'', 'test\''],
            // 1x double quote
            ['te"st', 'te"st'],
            ['"test', '"test'],
            ['test"', 'test"'],
            // Single quoted string
            ['before\'mid\'after', 'beforeafter'],
            ['before\'m\\\'id\'after', 'beforeafter'],
            ['before\'\\\'mid\'after', 'beforeafter'],
            ['before\'mid\\\'\'after', 'beforeafter'],
            ['\'mid\'', ''],
            ['\'mid\'after', 'after'],
            ['before\'mid\'', 'before'],
            // Double quoted string
            ['before"mid"after', 'beforeafter'],
            ['before"m\"id"after', 'beforeafter'],
            ['before"mid\""after', 'beforeafter'],
            ['before"\"mid"after', 'beforeafter'],
            ['"mid"', ''],
            ['"mid"after', 'after'],
            ['before"mid"', 'before'],
        ];
    }


    /**
    * @dataProvider dataStripStrings
    **/
    public function testStripStrings($in, $exp)
    {
        $this->assertEquals($exp, Pdb::stripStrings($in));
    }


    public function testArr()
    {
        $q = "SELECT id, name FROM sprout_pdb_test ORDER BY id";
        $res = Pdb::q($q, [], 'arr');
        $this->assertTrue(is_array($res));
        $this->assertTrue(count($res) == 3);
        $this->assertTrue(count($res[0]) == 2);
        $this->assertTrue(isset($res[0]['id']));
        $this->assertFalse(isset($res[0][0]));
    }


    public function testArrNum()
    {
        $q = "SELECT id, name FROM sprout_pdb_test ORDER BY id";
        $res = Pdb::q($q, [], 'arr-num');
        $this->assertTrue(is_array($res));
        $this->assertTrue(count($res) == 3);
        $this->assertTrue(count($res[0]) == 2);
        $this->assertTrue(isset($res[0][0]));
    }


    public function testRow()
    {
        $q = "SELECT id, name FROM sprout_pdb_test ORDER BY id";
        $row = Pdb::q($q, [], 'row');
        $this->assertTrue(is_array($row));
        $this->assertTrue(count($row) == 2);
        $this->assertFalse(isset($row[0]));
        $this->assertTrue($row['id'] == 1);
        $this->assertTrue($row['name'] == 'A');
    }


    public function testRowNum()
    {
        $q = "SELECT id, name FROM sprout_pdb_test ORDER BY id";
        $row = Pdb::q($q, [], 'row-num');
        $this->assertTrue(is_array($row));
        $this->assertTrue(count($row) == 2);
        $this->assertFalse(isset($row['id']));
        $this->assertTrue($row[0] == 1);
        $this->assertTrue($row[1] == 'A');
    }


    public function testMap()
    {
        $q = "SELECT id, name, value FROM sprout_pdb_test ORDER BY id";
        $res = Pdb::q($q, [], 'map');
        $this->assertTrue(is_array($res));
        $this->assertTrue(count($res) == 3);
        $this->assertFalse(isset($res['id']));
        $this->assertTrue(isset($res[1]));
        $this->assertTrue($res[1] == 'A');
        $this->assertTrue($res[2] == 'B');
        $this->assertTrue($res[3] == 'C');
    }


    public function testMapArr()
    {
        $q = "SELECT id, name, value FROM sprout_pdb_test ORDER BY id";
        $res = Pdb::q($q, [], 'map-arr');
        $this->assertTrue(is_array($res));
        $this->assertCount(3, $res);

        $keys = array_keys($res);
        $this->assertEquals(1, reset($keys));
        $this->assertEquals(3, end($keys));
        $this->assertEquals('A', $res[1]['name']);
        $this->assertEquals(5, $res[1]['value']);
        $this->assertEquals('B', $res[2]['name']);
        $this->assertEquals(12.3, $res[2]['value']);
        $this->assertEquals('C', $res[3]['name']);
        $this->assertEquals(987.65, $res[3]['value']);
    }


    public function testVal()
    {
        $q = "SELECT id, name FROM sprout_pdb_test ORDER BY id";
        $val = Pdb::q($q, [], 'val');
        $this->assertTrue(is_scalar($val));
        $this->assertEquals(1, $val);
    }


    public function testCol()
    {
        $q = "SELECT name FROM ~pdb_test ORDER BY name";
        $this->assertEquals(['A', 'B', 'C'], Pdb::q($q, [], 'col'));
    }


    public function testInsert()
    {
        $id = Pdb::insert('pdb_test', ['value' => 505.05, 'name' => 'D']);
        $this->assertEquals(4, $id);

        $q = "SELECT value FROM sprout_pdb_test WHERE id = ?";
        $val = Pdb::q($q, [$id], 'val');
        $this->assertTrue($val == 505.05);
    }


    public function testUpdateKeyVal()
    {
        $count = Pdb::update('pdb_test', ['value' => 99], ['id' => 2]);
        $this->assertEquals(1, $count);

        $q = "SELECT value FROM sprout_pdb_test WHERE id = ?";
        $val = Pdb::q($q, [2], 'val');
        $this->assertEquals(99, $val);
    }


    /**
    * @depends testInsert
    **/
    public function testUpdateSingleCond()
    {
        $count = Pdb::update('pdb_test', ['value' => 5], [['id', '>=', 2]]);
        $this->assertEquals(2, $count);

        $q = "SELECT SUM(value) FROM sprout_pdb_test WHERE id >= 2";
        $val = Pdb::q($q, [], 'val');
        $this->assertEquals(10, $val);
    }


    public function testUpdateMultiCond()
    {
        $conds = [['id', '<=', 2], 'name' => 'B'];
        $count = Pdb::update('pdb_test', ['value' => 1], $conds);
        $this->assertEquals(1, $count);

        $q = "SELECT SUM(value) FROM sprout_pdb_test WHERE id <= 2";
        $val = Pdb::q($q, [], 'val');
        $this->assertEquals(6, $val);
    }


    public function dataThrowException()
    {
        $stuff = array(null, '', 1, '☺');
        $out = array();
        foreach ($stuff as $a) {
            foreach ($stuff as $b) {
                $out[] = [$a, [$b => '']];
            }
            $out[] = [$a, []];
        }
        return $out;
    }


    /**
    * @dataProvider dataThrowException
    **/
    public function testThrowException($table, $vals)
    {
        $this->expectException(InvalidArgumentException::class);
        Pdb::insert($table, $vals);
    }


    public function dataWhereClause()
    {
        return array(
            // Uh, this doesn't work?
            // array([1], '1'),

            array(['a' => 'b'], '`a` = ?'),
            array(['a' => 1], '`a` = ?'),

            array([['a', '=', 'b']], '`a` = ?'),
            array([['a', '!=', 'b']], '`a` != ?'),
            array([['a', '<', 'b']], '`a` < ?'),
            array([['a', '>', 'b']], '`a` > ?'),
            array([['a', '<=', 'b']], '`a` <= ?'),
            array([['a', '>=', 'b']], '`a` >= ?'),

            array([['a', '=', 'b'], ['c', '=', 'd']], '`a` = ? AND `c` = ?'),
            array([['a', '!=', 'b'], ['c', '=', 'd']], '`a` != ? AND `c` = ?'),
            array([['a', '<', 'b'], ['c', '=', 'd']], '`a` < ? AND `c` = ?'),
            array([['a', '>', 'b'], ['c', '=', 'd']], '`a` > ? AND `c` = ?'),
            array([['a', '<=', 'b'], ['c', '=', 'd']], '`a` <= ? AND `c` = ?'),
            array([['a', '>=', 'b'], ['c', '=', 'd']], '`a` >= ? AND `c` = ?'),

            array([['a', 'IS', 'NULL']], '`a` IS NULL'),
            array([['a', 'IS', 'NOT NULL']], '`a` IS NOT NULL'),

            array([['a', 'BETWEEN', [1, 2]]], '`a` BETWEEN ? AND ?'),

            array([['a', 'IN', [1]]], '`a` IN (?)'),
            array([['a', 'IN', [1, 2]]], '`a` IN (?, ?)'),
            array([['a', 'IN', [1, 2, 3]]], '`a` IN (?, ?, ?)'),

            array([['a', 'NOT IN', [1]]], '`a` NOT IN (?)'),
            array([['a', 'NOT IN', [1, 2]]], '`a` NOT IN (?, ?)'),
            array([['a', 'NOT IN', [1, 2, 3]]], '`a` NOT IN (?, ?, ?)'),

            array([['column', 'IN SET', 'value']], 'FIND_IN_SET(?, `column`) > 0'),
        );
    }

    /**
    * @dataProvider dataWhereClause
    **/
    public function testBuildClause($conditions, $expected)
    {
        $junk = array();
        $this->assertEquals($expected, Pdb::buildClause($conditions, $junk));
    }


    public function testBuildClauseCombineAND()
    {
        $junk = array();
        $this->assertEquals('`a` = ? AND `b` = ?', Pdb::buildClause(['a' => 1, 'b' => 2], $junk, 'AND'));
    }

    public function testBuildClauseCombineOR()
    {
        $junk = array();
        $this->assertEquals('`a` = ? OR `b` = ?', Pdb::buildClause(['a' => 1, 'b' => 2], $junk, 'OR'));
    }

    public function testBuildClauseCombineXOR()
    {
        $junk = array();
        $this->assertEquals('`a` = ? XOR `b` = ?', Pdb::buildClause(['a' => 1, 'b' => 2], $junk, 'XOR'));
    }


    /**
    * Enum to array method - includes tests for silly things
    **/
    public function dataConvertEnumArr()
    {
        return array(
            array("enum('aaa')", array('aaa')),
            array("ENUM('aaa')", array('aaa')),
            array("ENUM('aaa','bbb')", array('aaa', 'bbb')),
            array("ENUM('a''aa','bbb')", array('a\'aa', 'bbb')),
            array("ENUM('aaa','bb''b')", array('aaa', 'bb\'b')),
            array("ENUM('aaa','b'',''bb')", array('aaa', 'b\',\'bb')),
        );
    }

    /**
    * @dataProvider dataConvertEnumArr
    **/
    public function testConvertEnumArr($str, $expect)
    {
        $this->assertEquals($expect, Pdb::convertEnumArr($str));
    }

    public function testConvertEnumNotAnEnum()
    {
        $this->expectException(InvalidArgumentException::class);
        Pdb::convertEnumArr('VARCHAR(100)');
    }



    /**
    * VALID DATA for validateIdentifier
    **/
    public function dataValidateIdentifierValid()
    {
        return [  ['a'],  ['a_a'],  ['a123']  ];
    }

    /**
    * @dataProvider dataValidateIdentifierValid
    **/
    public function testValidateIdentifierValid($val)
    {
        Pdb::validateIdentifier($val);
        $this->assertTrue(true);
    }


    /**
    * INVALID DATA for validateIdentifier
    **/
    public function dataValidateIdentifierInvalid()
    {
        return [  ['1'],  ['1.1'],  ['aaa.aaa'],  ['']  ];
    }

    /**
    * @dataProvider dataValidateIdentifierInvalid
    **/
    public function testValidateIdentifierInvalid($val)
    {
        $this->expectException(InvalidArgumentException::class);
        Pdb::validateIdentifier($val);
    }


    /**
    * VALID DATA for validateIdentifierExtended
    **/
    public function dataValidateIdentifierExtendedValid()
    {
        return [  ['a'],  ['a_a'],  ['a123'],  ['a.b'],  ['a12.b12']  ];
    }

    /**
    * @dataProvider dataValidateIdentifierExtendedValid
    **/
    public function testValidateIdentifierExtendedValid($val)
    {
        Pdb::validateIdentifierExtended($val);
        $this->assertTrue(true);
    }


    /**
    * INVALID DATA for validateIdentifierExtended
    **/
    public function dataValidateIdentifierExtendedInvalid()
    {
        return [  ['1'],  ['1.1'],  [''],  ['a.1'],  ['1.a']  ];
    }


    /**
    * @dataProvider dataValidateIdentifierExtendedInvalid
    **/
    public function testValidateIdentifierExtendedInvalid($val)
    {
        $this->expectException(InvalidArgumentException::class);
        Pdb::validateIdentifierExtended($val);
    }

    public function testDoubleTransaction()
    {
        $this->expectException(TransactionRecursionException::class);
        Pdb::transact();
        Pdb::transact();
    }

    public function testObjectWithoutFormatter1() {
        $this->expectException(InvalidArgumentException::class);
        Pdb::query('SELECT 1', [new stdClass], 'row');
    }

    public function testObjectWithoutFormatter2() {
        $this->expectException(InvalidArgumentException::class);

        Pdb::setFormatter('stdClass', function(){  return "42";  });
        Pdb::removeFormatter('stdClass');
        Pdb::query('SELECT 1', [new stdClass], 'row');
    }

    public function testObjectFormatter1() {
        Pdb::setFormatter('stdClass', function(){  return "42";  });
        $val = Pdb::query('SELECT ? AS val', [new stdClass], 'val');
        $this->assertEquals(42, $val);
    }

    public function testObjectFormatter2() {
        Pdb::setFormatter('stdClass', function(){  return 42;  });
        $val = Pdb::query('SELECT ? AS val', [new stdClass], 'val');
        $this->assertEquals(42, $val);
    }

    public function testObjectInvalidFormatter() {
        $this->expectException(InvalidArgumentException::class);

        Pdb::setFormatter('stdClass', function(){  return new stdClass;  });
        $val = Pdb::query('SELECT ? AS val', [new stdClass], 'val');
    }

    public function testPreparedInvalid() {
        $this->expectException(InvalidArgumentException::class);
        $val = Pdb::query('SELECT ? AS val', ['a'], 'prep');
    }

    public function testPreparedInsert() {
        $q = "INSERT INTO ~pdb_test SET name = ?, value = ?";
        $st = Pdb::query($q, [], 'prep');

        Pdb::execute($st, ['prepared', 10], 'null');
        Pdb::execute($st, ['prepared', 20], 'null');
        Pdb::execute($st, ['prepared', 30], 'null');

        $q = "SELECT value FROM ~pdb_test WHERE name = ? ORDER BY value";
        $vals = Pdb::query($q, ['prepared'], 'col');
        $this->assertEquals([10, 20, 30], $vals);
    }

    public function testPreparedSelectArr() {
        $q = "SELECT value FROM ~pdb_test WHERE name = ?";
        $st = Pdb::query($q, [], 'prep');

        for ($i = 1; $i < 10; $i++) {
            $res = Pdb::execute($st, ['A'], 'arr');
            $this->assertEquals([['value' => 5]], $res);
        }
    }

    public function testPrepared2SelectArr() {
        $q = "SELECT value FROM ~pdb_test WHERE name = ?";
        $st = Pdb::prepare($q);

        for ($i = 1; $i < 10; $i++) {
            $res = Pdb::execute($st, ['A'], 'arr');
            $this->assertEquals([['value' => 5]], $res);
        }
    }

    public function testPreparedSelectPdo() {
        $q = "SELECT value FROM ~pdb_test WHERE name = ?";
        $st = Pdb::query($q, [], 'prep');

        for ($i = 1; $i < 10; $i++) {
            $res = Pdb::execute($st, ['A'], 'pdo');
            foreach ($res as $row) {
                $this->assertEquals(['value' => 5], $row);
            }
            $res->closeCursor();
        }
    }

    public function testOverride() {
        if (!in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('SQLite not available');
        }

        $sqlite = new PDO('sqlite::memory:');
        Pdb::setOverrideConnection($sqlite);

        Pdb::query('CREATE TABLE ~pdb_test (value INT UNSIGNED, PRIMARY KEY(value))', [], 'null');
        Pdb::query('INSERT INTO ~pdb_test (value) VALUES (10)', [], 'null');

        $sqlite_col = Pdb::query('SELECT value FROM ~pdb_test', [], 'col');
        $this->assertEquals([10], $sqlite_col);

        Pdb::clearOverrideConnection();

        $mysql_col = Pdb::query('SELECT value FROM ~pdb_test WHERE name = ?', ['A'], 'col');
        $this->assertEquals([5], $mysql_col);
    }


    public function testOverrideInsert()
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('SQLite not available');
        }

        $sqlite = new PDO('sqlite::memory:');
        Pdb::setOverrideConnection($sqlite);

        Pdb::query('CREATE TABLE ~pdb_test (id INTEGER PRIMARY KEY, value INT UNSIGNED)', [], 'null');

        Pdb::query('INSERT INTO ~pdb_test (value) VALUES (10)', [], 'null');
        $this->assertEquals(1, Pdb::getLastInsertId());

        Pdb::query('INSERT INTO ~pdb_test (value) VALUES (20)', [], 'null');
        $this->assertEquals(2, Pdb::getLastInsertId());

        Pdb::clearOverrideConnection();
    }


    public function dataParams()
    {
        return [
            [
                "SELECT ? AS a",
                [42],
                ['a' => '42']
            ],
            [
                "SELECT ? AS a",
                ['42'],
                ['a' => '42']
            ],
            [
                "SELECT ? AS a, ? AS b",
                [42, 24],
                ['a' => '42', 'b' => '24']
            ],
            [
                "SELECT :a AS a",
                ['a' => 42],
                ['a' => '42']
            ],
            [
                "SELECT :a AS a",
                ['a' => null],
                ['a' => null]
            ],
            [
                "SELECT :a AS a, :b AS b",
                ['a' => 42, 'b' => 24],
                ['a' => '42', 'b' => '24']
            ],
            [
                "SELECT * FROM sprout_pdb_test LIMIT ?",
                [1],
                ['id' => '1', 'name' => 'A', 'value' => '5.00']
            ],
            [
                "SELECT * FROM sprout_pdb_test LIMIT :limit",
                ['limit' => 1],
                ['id' => '1', 'name' => 'A', 'value' => '5.00']
            ],
        ];
    }

    /**
     * @dataProvider dataParams
     */
    public function testParams1($query, $params, $expect) {
        $row = Pdb::query($query, $params, 'row');
        $this->assertEquals($expect, $row);
    }

    // Hacky way to see that an INT is actually getting passed to MySQL (direct execute)
    public function testParams2() {
        $q = "CREATE TABLE typeof AS SELECT ? AS col";
        Pdb::query($q, [42], 'null');

        $q = "SHOW COLUMNS IN typeof";
        $defn = Pdb::query($q, [], 'row');
        $this->assertMatchesRegularExpression('/^int/i', $defn['Type']);

        $q = "DROP TABLE typeof";
        Pdb::query($q, [], 'null');
    }

    // Hacky way to see that an INT is actually getting passed to MySQL (prepared stmts)
    public function testParams3() {
        $q = "CREATE TABLE typeof AS SELECT ? AS col";
        $stmt = Pdb::prepare($q);

        Pdb::execute($stmt, [42], 'null');

        $q = "SHOW COLUMNS IN typeof";
        $defn = Pdb::query($q, [], 'row');
        $this->assertMatchesRegularExpression('/^int/i', $defn['Type']);

        $q = "DROP TABLE typeof";
        Pdb::query($q, [], 'null');
    }


    public function testPrettyQuery()
    {
        $expected = "SELECT *
            FROM `sprout_pdb_test`
            WHERE name = 'AAA'
            AND etc = 123
            AND bool = 1
            AND float = 4.56
            AND alt = 'AAA'
        ";
        $actual = Pdb::prettyQuery("SELECT *
            FROM ~pdb_test
            WHERE name = ?
            AND etc = ?
            AND bool = ?
            AND float = ?
            AND alt = ?
        ",
            [
                'AAA',
                123,
                true,
                4.56,
                'AAA',
            ]
        );

        $this->assertEquals($expected, $actual);

        $actual = Pdb::prettyQuery("SELECT *
            FROM ~pdb_test
            WHERE name = :name
            AND etc = :etc
            AND bool = :bool
            AND float = :float
            AND alt = :name
        ",
            [
                'name' => 'AAA',
                'etc' => 123,
                'bool' => true,
                'float' => 4.56,
            ]
        );

        $this->assertEquals($expected, $actual);

    }

}
