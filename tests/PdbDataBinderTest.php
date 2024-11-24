<?php

use karmabunny\kb\DataObject;
use karmabunny\pdb\DataBinders\ConcatDataBinder;
use karmabunny\pdb\DataBinders\DateTimeFormatter;
use karmabunny\pdb\DataBinders\JsonLinesBinder;
use karmabunny\pdb\DataBinders\MathBinder;
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


    public function testDateFormatter(): void
    {
        $pdb = Database::getConnection('mysql');
        $expected = '2020-02-05 12:00:12';

        try {
            $pdb->insert('logs', [
                'date_added' => new DateTime($expected),
                'data' => 'date-format-test',
            ]);
            $this->fail('Should have thrown an exception');
        }
        catch (InvalidArgumentException $ex) {
            $this->assertStringContainsString('Unable to format object', $ex->getMessage());
        }

        // Add the formatter.
        $pdb->config->formatters[DateTimeInterface::class] = DateTimeFormatter::class;

        $id = $pdb->insert('logs', [
            'date_added' => new DateTime($expected),
            'data' => 'date-format-test',
        ]);

        $this->assertGreaterThan(0, $id);

        // Check it.
        $actual = $pdb->get('logs', $id);

        $this->assertEquals($expected, $actual['date_added']);
        $this->assertEquals('date-format-test', $actual['data']);

        // A configured formatter.
        $pdb->config->formatters[DateTimeInterface::class] = [
            DateTimeFormatter::class => [
                'format' => 'Y-m-d',
            ],
        ];

        $id = $pdb->insert('logs', [
            'date_added' => new DateTime(),
            'data' => 'date-configure-test',
        ]);

        $this->assertGreaterThan(0, $id);

        // Note, although the insert is formatted 'Y-m-d' - the database will
        // still return the time component. But we can verify we've done it
        // right because they're zeroed out.

        $expected = date('Y-m-d 00:00:00');
        $actual = $pdb->get('logs', $id);

        $this->assertEquals($expected, $actual['date_added']);
        $this->assertEquals('date-configure-test', $actual['data']);
    }


    public function testCallableFormatter(): void
    {
        $pdb = Database::getConnection('mysql');

        $object = new FormattableObject(['value' => 'hello']);

        try {
            $pdb->insert('logs', [
                'date_added' => $pdb->now(),
                'data' => $object,
            ]);
            $this->fail('Should have thrown an exception');
        }
        catch (InvalidArgumentException $ex) {
            $this->assertStringContainsString('Unable to format object', $ex->getMessage());
        }

        // Add the formatter.
        $pdb->config->formatters[FormattableObject::class] = function ($object) {
            return ucwords($object->value . ' world!');
        };

        $id = $pdb->insert('logs', [
            'date_added' => $pdb->now(),
            'data' => $object,
        ]);

        $this->assertGreaterThan(0, $id);

        // Check it.
        $actual = $pdb->get('logs', $id);
        $this->assertEquals('Hello World!', $actual['data']);
    }


    public function testMathBinder(): void
    {
        $pdb = Database::getConnection('mysql');

        $id = $pdb->insert('logs', [
            'date_added' => $pdb->now(),
            'data' => MathBinder::add(1),
        ]);

        $actual = $pdb->find('logs', ['id' => $id])->value('data');
        $this->assertEquals(1, $actual);

        $update = [
            'data' => MathBinder::add(10),
        ];
        $pdb->update('logs', $update, ['id' => $id]);

        $actual = $pdb->find('logs', ['id' => $id])->value('data');
        $this->assertEquals(11, $actual);

        $update = [
            'data' => MathBinder::subtract(6),
        ];
        $pdb->update('logs', $update, ['id' => $id]);

        $actual = $pdb->find('logs', ['id' => $id])->value('data');
        $this->assertEquals(5, $actual);

        $update = [
            'data' => MathBinder::multiply(10),
        ];
        $pdb->update('logs', $update, ['id' => $id]);

        $actual = $pdb->find('logs', ['id' => $id])->value('data');
        $this->assertEquals(50, $actual);

        $update = [
            'data' => MathBinder::divide(2),
        ];
        $pdb->update('logs', $update, ['id' => $id]);

        $actual = $pdb->find('logs', ['id' => $id])->value('data');
        $this->assertEquals(25, $actual);

        $update = [
            'data' => MathBinder::modulo(4),
        ];
        $pdb->update('logs', $update, ['id' => $id]);

        $actual = $pdb->find('logs', ['id' => $id])->value('data');
        $this->assertEquals(1, $actual);
    }
}


class FormattableObject extends DataObject
{
    public $value;
}
