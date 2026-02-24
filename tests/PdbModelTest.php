<?php

use karmabunny\kb\Uuid;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;
use kbtests\Database;
use PHPUnit\Framework\TestCase;
use kbtests\Models\Club;
use kbtests\Models\DirtyClub;
use kbtests\Models\TypedModel;
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
        $pdb->query('DROP TABLE IF EXISTS ~model_property_tests', [], 'null');

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
        $model->founded = $model->getConnection()->now();

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
        $model->founded = $model->getConnection()->now();

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


    /**
     * Test that saving and loading data using native types works,
     * including SET and JSON fields with default values
     */
    public function testTypedSaveLoad(): void
    {
        $name = 'Test save and load data';
        $bool_val = false;
        $int_val = 3;
        $currency_val = 13.95;
        $float_val = 1234.56789;

        $dummy = new TypedModel();
        $dummy->name = $name;
        $dummy->bool_val = $bool_val;
        $dummy->int_val = $int_val;
        $dummy->currency_val = $currency_val;
        $dummy->float_val = $float_val;
        $dummy->save();

        $models = TypedModel::findAll();
        $this->assertEquals(count($models), 1);

        // Confirm that all data saved and loaded correctly from native types
        /** @var TypedModel $dummy */
        $dummy = reset($models);
        $this->assertEquals($dummy->name, $name);
        $this->assertEquals($dummy->bool_val, $bool_val);
        $this->assertEquals($dummy->bool_default_false, false); // from default defined in DB
        $this->assertEquals($dummy->bool_default_true, true); // from default defined in DB
        $this->assertEquals($dummy->int_val, $int_val);
        $this->assertEquals($dummy->int_default_zero, 0); // from default defined in DB
        $this->assertEquals($dummy->int_default_one, 1); // from default defined in DB
        $this->assertEquals($dummy->currency_val, $currency_val);
        $this->assertEquals($dummy->float_val, $float_val);
        $this->assertEquals($dummy->options_db_default, ['a', 'b']); // from default defined in DB
        $this->assertEquals($dummy->options_model_default, ['c', 'd']); // from default defined in model
        $this->assertEquals($dummy->json_db_default, [1, 2, ['a' => 'b', 3 => 9]]); // from default defined in DB
        $this->assertEquals($dummy->json_model_default, ['trash' => 'panda', 123]); // from default defined in model
        $this->assertEquals($dummy->non_json, '{"do not parse JSON": "for a non-array attribute"}');

        $new_bool = true;
        $new_int = 5;
        $new_options = ['a', 'b', 'c'];
        $new_json = ['x' => 1, 'y' => 543.21, 'z' => ['ciao' => 'hola']];
        $dummy->bool_val = $new_bool;
        $dummy->int_val = $new_int;
        $dummy->options_db_default = $new_options;
        $dummy->options_model_default = $new_options;
        $dummy->json_db_default = $new_json;
        $dummy->json_model_default = $new_json;
        $dummy->save();

        // Confirm that new data saved and loaded correctly from native types
        /** @var TypedModel $dummy */
        $dummy = TypedModel::find(['id' => $dummy->id])->one();
        $this->assertEquals($dummy->bool_val, $new_bool);
        $this->assertEquals($dummy->int_val, $new_int);
        $this->assertEquals($dummy->options_db_default, $new_options);
        $this->assertEquals($dummy->options_model_default, $new_options);
        $this->assertEquals($dummy->json_db_default, $new_json);
        $this->assertEquals($dummy->json_model_default, $new_json);
    }


    /**
     * Test that overriding defaults works sanely
     */
    public function testOverrideDefaults(): void
    {
        $dummy = new TypedModel();
        $dummy->name = 'Test override defaults';
        $dummy->bool_val = true;
        $dummy->int_val = 100;
        $dummy->currency_val = 1.0;
        $dummy->float_val = 123.45;
        $dummy->bool_default_false = true;
        $dummy->bool_default_true = false;
        $dummy->int_default_zero = 1;
        $dummy->int_default_one = 0;
        $dummy->save();

        $models = TypedModel::findAll();
        $this->assertEquals(count($models), 1);

        /** @var TypedModel $dummy */
        $dummy = reset($models);
        $this->assertEquals($dummy->bool_default_false, true);
        $this->assertEquals($dummy->bool_default_true, false);
        $this->assertEquals($dummy->int_default_zero, 1);
        $this->assertEquals($dummy->int_default_one, 0);
    }


    /**
     * Test that creation using DB defaults doesn't cause a TypeError
     */
    public function testFindOrCreate(): void
    {
        $dummy = TypedModel::findOrCreate([
            'name' => 'Test by findOrCreate'
        ]);

        // Useless assertion just to ensure no TypeError occurs
        $this->assertInstanceOf(TypedModel::class, $dummy);
    }
}

