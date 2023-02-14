<?php

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
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
                'trade_join = ~project_rules.id',
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

        $expected = 'SELECT ~project_roles.* FROM ~project_roles ';
        $expected .= 'INNER JOIN ~projects AS "project" ON project.id = ~project_roles.project_id AND "project"."active" != ? AND "project"."visible_members" != ? AND "project"."profile_id" != ? AND "project"."region_id" IN (?, ?, ?) ';
        $expected .= 'INNER JOIN ~project_roles_trade_type_ids AS "trade_join" ON trade_join = ~project_rules.id AND "trade_join"."trade_type_id" IN (?, ?, ?) ';
        $expected .= 'WHERE "active" != ? AND "start_date" BETWEEN ? AND ? AND "end_date" BETWEEN ? AND ? AND ("level1" = ? OR ("level2" = ? AND "level3" = ?)) ';
        $expected .= 'GROUP BY "project_roles"."id"';

        $this->assertEquals($expected, $sql);
        $this->assertCount(17, $params);
    }


    public function testAlias()
    {
        $query = $this->pdb->find('big_thingy');

        [$sql, $params] = $query->build();
        $expected = 'SELECT ~big_thingy.* FROM ~big_thingy';

        $this->assertEquals($expected, $sql);
        $this->assertCount(0, $params);

        [$sql, $params] = $query->alias('thing')->build();
        $expected = 'SELECT "thing".* FROM ~big_thingy AS "thing"';

        $this->assertEquals($expected, $sql);
        $this->assertCount(0, $params);
    }
}
