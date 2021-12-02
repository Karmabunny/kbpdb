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

        print_r($sql);
        echo "\n";
        print_r($params);
    }

}
