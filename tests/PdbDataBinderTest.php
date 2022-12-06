<?php

use karmabunny\pdb\DataBinders\ConcatDataBinder;
use karmabunny\pdb\DataBinders\JsonLinesBinder;
use kbtests\Database;
use PHPUnit\Framework\TestCase;


class PdbDataBinderTest extends TestCase
{

    public function setUp(): void
    {
        Database::sync('mysql');
    }


    public function testConcat(): void
    {
        $pdb = Database::getConnection('mysql');

        // Initial insert.
        $data = [
            'date_added' => $pdb->now(),
            'data' => new ConcatDataBinder('foo'),
        ];
        $id = $pdb->insert('logs', $data);
        $this->assertGreaterThan(0, $id);

        $row = $pdb->get('logs', $id);
        $this->assertEquals('foo', $row['data']);

        // Update with more data.
        $data = [ 'data' => new ConcatDataBinder('bar') ];
        $nextid = $pdb->upsert('logs', $data, ['id' => $id]);

        $this->assertEquals($id, $nextid);

        // Check it.
        $row = $pdb->get('logs', $id);
        $this->assertEquals('foobar', $row['data']);
    }


    public function testJsonLines(): void
    {
        $pdb = Database::getConnection('mysql');

        $items = [
            ['ts' => time(), 'message' => 'foo'],
            ['ts' => time() + 1000, 'message' => 'bar'],
        ];

        // Initial insert.
        $data = [
            'date_added' => $pdb->now(),
            'data' => new JsonLinesBinder($items[0]),
        ];
        $id = $pdb->insert('logs', $data);
        $this->assertGreaterThan(0, $id);

        $row = $pdb->get('logs', $id);
        $lines = JsonLinesBinder::parseJsonLines($row['data']);
        $lines = iterator_to_array($lines);
        $this->assertEquals($items[0], $lines[0]);

        // Update with more data.
        $data = [ 'data' => new JsonLinesBinder($items[1]) ];
        $nextid = $pdb->upsert('logs', $data, ['id' => $id]);

        $this->assertEquals($id, $nextid);

        // Check it.
        $row = $pdb->get('logs', $id);
        $lines = JsonLinesBinder::parseJsonLines($row['data']);
        $lines = iterator_to_array($lines);

        $this->assertEquals($items, $lines);
    }

}
