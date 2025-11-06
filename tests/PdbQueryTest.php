<?php

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
use karmabunny\pdb\PdbQuery;
use PHPUnit\Framework\TestCase;


class PdbQueryTest extends TestCase
{

    /** @var Pdb */
    public $pdb;


    public function setUp(): void
    {
        $this->pdb = Pdb::create([
            'type' => PdbConfig::TYPE_SQLITE,
            'dsn' => __DIR__ . '/db.sqlite',
        ]);

        $this->pdb->query('DROP TABLE IF EXISTS ~mmm', [], 'null');
    }

    public function testSelect(): void
    {
        $query = $this->pdb->find('mmm')
            ->select([
                'person_pre_seeds.*',
                'given' => 'persons.first_name',
                'persons.last_name surname',
                'email_address' => 'persons.email',
                'persons.phone',
                'IF(~mmm.status IS NOT NULL, ~mmm.status, \'-\') AS access_status',
            ]);

        [$sql] = $query->build();

        $expected = 'SELECT "person_pre_seeds".*, "persons"."first_name" AS "given", "persons"."last_name" AS "surname", ';
        $expected .= '"persons"."email" AS "email_address", "persons"."phone", ';
        $expected .= 'IF("pdb_mmm"."status" IS NOT NULL, "pdb_mmm"."status", \'-\') AS "access_status" ';
        $expected .= 'FROM "pdb_mmm"';

        $this->assertEquals($expected, $sql);
    }


    public function testBasic(): void
    {
        $dates = ['2021-01-01', '2021-12-31'];
        $regions = [1,2,3];
        $trades = [5,6,7];

        $query = $this->pdb->find('project_roles')
            ->innerJoin(['projects', 'project'], [
                'project.id = ~project_roles.project_id',
                ['project.active', '!=', true],
                ['project.visible_members', '!=', true],
                ['project.profile_id', '!=', 123],
                ['project.region_id', 'IN', $regions],
            ])
            ->innerJoin(['project_roles_trade_type_ids', 'trade_join'], [
                'trade_join.role_id = ~project_roles.id',
                ['trade_join.trade_type_id', 'IN', $trades],
            ])
            ->where([
                ['active', '!=', true],
                ['start_date', 'BETWEEN', $dates],
                ['end_date', 'BETWEEN', $dates],
            ])
            ->andWhere([
                'level1' => 'value1',
                'and' => [
                    'level2' => 'value2',
                    'level3' => 'value3',
                ],
            ], 'OR')
            ->groupBy('project_roles.id');

        [$sql, $params] = $query->build();

        $expected = 'SELECT "pdb_project_roles".* FROM "pdb_project_roles" ';
        $expected .= 'INNER JOIN "pdb_projects" AS "project" ON "project"."id" = "pdb_project_roles"."project_id" AND "project"."active" != ? AND "project"."visible_members" != ? AND "project"."profile_id" != ? AND "project"."region_id" IN (?, ?, ?) ';
        $expected .= 'INNER JOIN "pdb_project_roles_trade_type_ids" AS "trade_join" ON "trade_join"."role_id" = "pdb_project_roles"."id" AND "trade_join"."trade_type_id" IN (?, ?, ?) ';
        $expected .= 'WHERE "active" != ? AND "start_date" BETWEEN ? AND ? AND "end_date" BETWEEN ? AND ? AND ("level1" = ? OR ("level2" = ? AND "level3" = ?)) ';
        $expected .= 'GROUP BY "project_roles"."id"';

        $this->assertEquals($expected, $sql);
        $this->assertCount(17, $params);
    }


    public function testAlias()
    {
        $query = $this->pdb->find('big_thingy');

        [$sql, $params] = $query->build();
        $expected = 'SELECT "pdb_big_thingy".* FROM "pdb_big_thingy"';

        $this->assertEquals($expected, $sql);
        $this->assertCount(0, $params);

        [$sql, $params] = $query->alias('thing')->build();
        $expected = 'SELECT "thing".* FROM "pdb_big_thingy" AS "thing"';

        $this->assertEquals($expected, $sql);
        $this->assertCount(0, $params);
    }


    public function testKeyed()
    {
        $this->pdb->query('CREATE TABLE ~mmm (id INT, name VARCHAR(100))', [], 'null');
        $this->pdb->insert('mmm', ['id' => 10, 'name' => 'test ten']);
        $this->pdb->insert('mmm', ['id' => 20, 'name' => 'test twenty']);
        $this->pdb->insert('mmm', ['id' => 30, 'name' => 'test thirty']);

        // Maps.
        $actual = $this->pdb->find('mmm')
            ->indexBy('id')
            ->all();

        $expected = [
            10 => [
                'id' => 10,
                'name' => 'test ten',
            ],
            20 => [
                'id' => 20,
                'name' => 'test twenty',
            ],
            30 => [
                'id' => 30,
                'name' => 'test thirty',
            ],
        ];

        $this->assertEquals($expected, $actual);

        // Columns.
        $actual = $this->pdb->find('mmm')
            ->indexBy('id')
            ->column('name');

        $expected = [
            10 => 'test ten',
            20 => 'test twenty',
            30 => 'test thirty',
        ];

        $this->assertEquals($expected, $actual);
    }


    public function testOrderBy()
    {
        // Basic ordering, default direction.
        $query = $this->pdb->find('mmm')
            ->orderBy('name');

        [$sql, $params] = $query->build();
        $expected = 'SELECT "pdb_mmm".* FROM "pdb_mmm" ORDER BY "name" ASC';

        $this->assertEquals($expected, $sql);


        // Explicit ordering.
        $query = $this->pdb->find('mmm')
            ->orderBy('name DESC', 'id ASC');

        [$sql, $params] = $query->build();
        $expected = 'SELECT "pdb_mmm".* FROM "pdb_mmm" ORDER BY "name" DESC, "id" ASC';

        $this->assertEquals($expected, $sql);


        // Array syntax + nested array.
        $query = $this->pdb->find('mmm')
            ->orderBy([
                ['record_order' => SORT_DESC],
                ['name' => 'DESC'],
                ['id ASC', 'status'],
            ]);

        [$sql, $params] = $query->build();
        $expected = 'SELECT "pdb_mmm".* FROM "pdb_mmm" ORDER BY "record_order" DESC, "name" DESC, "id" ASC, "status" ASC';

        $this->assertEquals($expected, $sql);


        // Functions ordering.
        $query = $this->pdb->find('mmm')
            ->orderBy('rand()');

        [$sql, $params] = $query->build();
        $expected = 'SELECT "pdb_mmm".* FROM "pdb_mmm" ORDER BY rand() ASC';

        $this->assertEquals($expected, $sql);
    }


    public function testEscapes(): void
    {
        $prefix = $this->pdb->getPrefix();

        $query = $this->pdb->find('stuff')
            ->innerJoin('more as mmm', ['~stuff.id = mmm.stuff_id'])
            ->select([
                'count(~stuff.id)',
                'max(missing.count)',
                'min(~also.count)',
            ]);

        // Also test IDs while we're here.
        $ids = $query->getIdentifiers();

        $expected = [
            $prefix . 'stuff' => $prefix . 'stuff',
            'mmm' => $prefix . 'more',
        ];
        $this->assertEquals($expected, $ids);

        [$sql, $params] = $query->build();

        $expected = 'SELECT count("pdb_stuff"."id"), max(missing.count), min("pdb_also"."count") ';
        $expected .= 'FROM "pdb_stuff" ';
        $expected .= 'INNER JOIN "pdb_more" AS "mmm" ';
        $expected .= 'ON "pdb_stuff"."id" = "mmm"."stuff_id"';
        $this->assertEquals($expected, $sql);
    }


    public function testFromSubQuery(): void
    {
        $subQuery = $this->pdb->find('ahh')
            ->where(['name' => 'test'])
            ->select('id, name AS title');

        $query = (new PdbQuery($this->pdb))
            ->from($subQuery, 'ahh');

        [$sql, $params] = $query->build();

        $expected = 'SELECT "ahh".* FROM (SELECT "id", "name" AS "title" FROM "pdb_ahh" WHERE "name" = ?) AS "ahh"';
        $this->assertEquals($expected, $sql);
    }


    public function testWithQuery(): void
    {
        $query2 = $this->pdb->find('ahh');

        $query1 = $this->pdb->find('mmm')
            ->alias('mmm')
            ->innerJoin('sub', 'mmm.id = sub._id')
            ->with($query2, 'sub');

        // Modify after attaching.
        $query2->select('id as _id')
            ->where(['name' => 'test']);

        [$sql, $params] = $query1->build();

        $expected = 'WITH "sub" AS (SELECT "id" AS "_id" FROM "pdb_ahh" WHERE "name" = ?)' . "\n";
        $expected .= 'SELECT "mmm".* FROM "pdb_mmm" AS "mmm" INNER JOIN "sub" ON "mmm"."id" = "sub"."_id"';
        $this->assertEquals($expected, $sql);
    }


}
