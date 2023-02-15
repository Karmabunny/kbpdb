<?php

use karmabunny\kb\Uuid;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;
use kbtests\Database;
use PHPUnit\Framework\TestCase;
use kbtests\Models\Club;
use kbtests\Models\DirtyClub;
use kbtests\Models\SproutItem;

class PdbModelTest extends TestCase
{

    public function assertArraySame(array $expected, array $actual, string $message = '')
    {
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual, $message);
    }

    public function assertArraySameKeys(array $expected, array $actual, string $message = '')
    {
        ksort($expected);
        ksort($actual);
        $this->assertEquals($expected, $actual, $message);
    }


    public function setUp(): void
    {
        $pdb = Database::getConnection();
        if (!Database::isConnected()) $this->markTestSkipped();

        $pdb->query('DROP TABLE IF EXISTS ~clubs', [], 'null');

        $sync = new PdbSync($pdb);

        $struct = new PdbParser();
        $struct->loadXml(__DIR__ . '/db_struct.xml');
        $struct->sanityCheck();

        $sync->updateDatabase($struct);
    }


    public function testBasicModel()
    {
        // A new model.
        $model = new Club();
        $model->name = 'thingo';
        $model->status = 'new';
        $model->founded = Pdb::now();

        $expected = [
            'active',
            'date_added',
            'date_modified',
            'founded',
            'name',
            'status',
            'uid',
        ];

        $actual = array_keys(array_filter($model->getSaveData()));
        $this->assertArraySame($expected, $actual);

        // Pdo is opened here.
        $this->assertTrue($model->save());
        $this->assertGreaterThan(0, $model->id);

        $id = $model->id;
        $uid = $model->uid;
        $added = $model->date_added;
        $modified = $model->date_modified;
        $active = $model->active;

        sleep(1);

        $this->assertTrue($model->save());

        // Some things change, others stay the same.
        $this->assertNotEquals($modified, $model->date_modified);
        $this->assertEquals($added, $model->date_added);
        $this->assertEquals($active, $model->active);
        $this->assertEquals($id, $model->id);
        $this->assertEquals($uid, $model->uid);

        // Fetch a fresh one.
        $model = Club::findOne(['id' => $id]);

        // Test it all again.
        $this->assertNotEquals($modified, $model->date_modified);
        $this->assertEquals($added, $model->date_added);
        $this->assertEquals($active, $model->active);
        $this->assertEquals($id, $model->id);
        $this->assertEquals($uid, $model->uid);

        $modified = $model->date_modified;

        sleep(1);

        $pdb = Database::getConnection();

        // Record exists.
        $exists = $pdb->recordExists($model->getTableName(), ['id' => $model->id]);
        $this->assertTrue($exists);

        // Hard delete.
        $this->assertTrue($model->delete());

        // Does not exist.
        $exists = $pdb->recordExists($model->getTableName(), ['id' => $model->id]);
        $this->assertFalse($exists);
    }


    public function testDirtyModel()
    {
        // A new model.
        $model = new DirtyClub();
        $model->name = 'thingo';
        $model->status = 'new';
        $model->founded = Pdb::now();

        $expected = [
            'active',
            'date_added',
            'date_modified',
            'founded',
            'name',
            'status',
            'uid',
        ];

        $actual = array_keys(array_filter($model->getSaveData()));
        $this->assertArraySame($expected, $actual);

        // Pdo is opened here.
        $this->assertTrue($model->save());
        $this->assertGreaterThan(0, $model->id);

        $id = $model->id;
        $uid = $model->uid;
        $added = $model->date_added;
        $modified = $model->date_modified;
        $active = $model->active;

        sleep(1);

        // Nothing has changed, so nothing should be saved.
        // Not even date_modified. Although that's not really part of the test
        // because that's specific to our test model. That said, real concrete
        // models _should_ behave the same.
        $actual = $model->getSaveData();
        $this->assertEquals([], $actual);

        // Update a property.
        $model->status = 'active';

        $expected = [ 'date_modified', 'status' ];
        $actual = array_keys($model->getSaveData());
        $this->assertArraySame($expected, $actual);

        $this->assertTrue($model->save());

        // Some things change, others stay the same.
        $this->assertNotEquals($modified, $model->date_modified);
        $this->assertEquals($added, $model->date_added);
        $this->assertEquals($active, $model->active);
        $this->assertEquals($id, $model->id);
        $this->assertEquals($uid, $model->uid);

        // Fetch a fresh one.
        $model = Club::findOne(['id' => $id]);

        // Test it all again.
        $this->assertNotEquals($modified, $model->date_modified);
        $this->assertEquals($added, $model->date_added);
        $this->assertEquals($active, $model->active);
        $this->assertEquals($id, $model->id);
        $this->assertEquals($uid, $model->uid);

        $modified = $model->date_modified;

        sleep(1);

        $pdb = Database::getConnection();

        // Record exists.
        $exists = $pdb->recordExists($model->getTableName(), ['id' => $model->id]);
        $this->assertTrue($exists);

        // Hard delete.
        $this->assertTrue($model->delete());

        // Does not exist.
        $exists = $pdb->recordExists($model->getTableName(), ['id' => $model->id]);
        $this->assertFalse($exists);
    }


    public function testSproutModel()
    {
        // A new model.
        $model = new SproutItem();
        $model->name = 'sprout test';

        $expected = [
            'date_added',
            'name',
            'uid',
        ];

        $actual = array_keys(array_filter($model->getSaveData()));
        $this->assertArraySame($expected, $actual);

        $this->assertNull($model->uid);
        $this->assertNull($model->date_added);

        // Pdo is opened here.
        $this->assertTrue($model->save());
        $this->assertGreaterThan(0, $model->id);

        $this->assertNotEquals(Uuid::NIL, $model->uid);
        $this->assertTrue(Uuid::valid($model->uid, 5));

        $this->assertGreaterThanOrEqual(date('Y-m-d H:i:s'), $model->date_added);

        $uid = $model->uid;
        $added = $model->date_added;

        usleep(500 * 1000);

        $model->name = 'something else';
        $this->assertTrue($model->save());

        // Some things change, others stay the same.
        $this->assertEquals($added, $model->date_added);
        $this->assertEquals($uid, $model->uid);

        // Fetch a fresh one.
        $other = SproutItem::findOne(['id' => $model->id]);

        // Test it all again.
        $this->assertEquals($model->id, $other->id);
        $this->assertEquals($model->uid, $other->uid);
        $this->assertEquals($model->date_added, $other->date_added);
        $this->assertEquals($model->name, $other->name);
    }
}
