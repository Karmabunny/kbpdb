<?php

use karmabunny\kb\Event;
use karmabunny\kb\Events;
use karmabunny\kb\Uuid;
use karmabunny\pdb\Events\AfterDeleteEvent;
use karmabunny\pdb\Events\AfterPopulateEvent;
use karmabunny\pdb\Events\AfterSaveEvent;
use karmabunny\pdb\Events\BeforeDeleteEvent;
use karmabunny\pdb\Events\BeforeSaveEvent;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbParser;
use karmabunny\pdb\PdbSync;
use kbtests\Database;
use PHPUnit\Framework\TestCase;
use kbtests\Models\BackedPriority;
use kbtests\Models\BackedStatus;
use kbtests\Models\Club;
use kbtests\Models\DirtyClub;
use kbtests\Models\TypedModel;
use kbtests\Models\SproutItem;
use kbtests\Models\UnitDirection;

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
        if (!Database::isConnected()) {
            fwrite(STDERR, "host=" . getenv('SITES_DB_HOSTNAME') . "\n");
            try {
                $pdb->getConnection();
            }
            catch (Throwable $error) {
                fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
            }
            $this->markTestSkipped();
        }

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


    public function testSaveFields()
    {
        $model = new Club();
        $model->name = 'thingo';
        $model->status = 'new';
        $model->founded = $model->getConnection()->now();

        $this->assertTrue($model->save());
        $this->assertGreaterThan(0, $model->id);

        $id = $model->id;
        $founded = $model->founded;
        $modified = $model->date_modified;
        $active = $model->active;
        $uid = $model->uid;
        $added = $model->date_added;

        sleep(1);

        $model->name = 'renamed';
        $model->status = 'retired';
        $model->active = false;

        // Only the listed columns are written. date_modified stays put too.
        $this->assertTrue($model->save(['name', 'status']));

        // Unsaved changes remain on the instance.
        $this->assertEquals('renamed', $model->name);
        $this->assertEquals('retired', $model->status);
        $this->assertFalse($model->active);
        $this->assertEquals($modified, $model->date_modified);

        $fresh = Club::findOne(['id' => $id]);

        $this->assertEquals('renamed', $fresh->name);
        $this->assertEquals('retired', $fresh->status);
        $this->assertEquals($founded, $fresh->founded);
        $this->assertEquals($active, $fresh->active);
        $this->assertEquals($modified, $fresh->date_modified);
        $this->assertEquals($uid, $fresh->uid);
        $this->assertEquals($added, $fresh->date_added);
        $this->assertEquals($id, $fresh->id);
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
        $this->assertSame(BackedStatus::Draft, $dummy->backed_status); // from default defined in DB
        $this->assertSame(BackedPriority::Low, $dummy->backed_priority); // from default defined in DB
        $this->assertSame(UnitDirection::North, $dummy->unit_direction); // from default defined in DB
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
        $dummy->backed_status = BackedStatus::Published;
        $dummy->backed_priority = BackedPriority::High;
        $dummy->unit_direction = UnitDirection::West;
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
        $this->assertSame(BackedStatus::Published, $dummy->backed_status);
        $this->assertSame(BackedPriority::High, $dummy->backed_priority);
        $this->assertSame(UnitDirection::West, $dummy->unit_direction);
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


    /**
     * Save, load, and delete fire model events in order.
     */
    public function testModelEvents()
    {
        $events = [];
        $pdb = Database::getConnection();

        $record = function(string $class) use (&$events, $pdb) {
            return function(Event $event) use (&$events, $pdb, $class) {
                /** @var Club|DirtyClub $sender */
                $sender = $event->sender;
                $id = $sender->id;

                $row = [
                    'class' => $class,
                    'id' => $id,
                    'sender' => $sender,
                    'exists' => false,
                ];

                if ($id > 0) {
                    $row['exists'] = $pdb->recordExists($sender->getTableName(), ['id' => $id]);
                }

                if (property_exists($event, 'insert')) {
                    $row['insert'] = $event->insert;
                }

                if ($event instanceof AfterPopulateEvent) {
                    $row['config'] = $event->config;
                }

                $events[] = $row;
            };
        };

        $listen = function(string $model) use ($record) {
            Events::off(Club::class);
            Events::off(DirtyClub::class);

            Events::on($model, BeforeSaveEvent::class, $record(BeforeSaveEvent::class));
            Events::on($model, AfterSaveEvent::class, $record(AfterSaveEvent::class));
            Events::on($model, AfterPopulateEvent::class, $record(AfterPopulateEvent::class));
            Events::on($model, BeforeDeleteEvent::class, $record(BeforeDeleteEvent::class));
            Events::on($model, AfterDeleteEvent::class, $record(AfterDeleteEvent::class));
        };

        $classes = function() use (&$events): array {
            return array_column($events, 'class');
        };

        try {
            $listen(Club::class);

            $model = new Club();
            $model->name = 'events';
            $model->status = 'new';
            $model->founded = $pdb->now();

            // Insert assigns the id after the row exists.
            $this->assertTrue($model->save());
            $this->assertEquals([
                BeforeSaveEvent::class,
                AfterSaveEvent::class,
            ], $classes());

            $this->assertTrue($events[0]['insert']);
            $this->assertSame(0, $events[0]['id']);
            $this->assertFalse($events[0]['exists']);
            $this->assertSame($model, $events[0]['sender']);

            $this->assertTrue($events[1]['insert']);
            $this->assertSame($model->id, $events[1]['id']);
            $this->assertGreaterThan(0, $events[1]['id']);
            $this->assertTrue($events[1]['exists']);
            $this->assertSame($model, $events[1]['sender']);

            $id = $model->id;
            $events = [];

            // Update keeps the same id and reports insert as false.
            $model->status = 'active';
            $this->assertTrue($model->save());
            $this->assertEquals([
                BeforeSaveEvent::class,
                AfterSaveEvent::class,
            ], $classes());
            $this->assertFalse($events[0]['insert']);
            $this->assertFalse($events[1]['insert']);
            $this->assertSame($id, $events[0]['id']);
            $this->assertSame($id, $events[1]['id']);
            $this->assertTrue($events[0]['exists']);
            $this->assertTrue($events[1]['exists']);
            $this->assertSame($model, $events[0]['sender']);
            $this->assertSame($model, $events[1]['sender']);

            $events = [];

            // A field list still saves, and still fires both events.
            $model->name = 'renamed';
            $model->status = 'retired';
            $this->assertTrue($model->save(['name']));
            $this->assertEquals([
                BeforeSaveEvent::class,
                AfterSaveEvent::class,
            ], $classes());
            $this->assertFalse($events[0]['insert']);
            $this->assertFalse($events[1]['insert']);
            $this->assertSame($model, $events[0]['sender']);
            $this->assertSame($model, $events[1]['sender']);

            $events = [];

            // Nothing to write, so after-save does not fire.
            $model->name = 'ignored';
            $this->assertTrue($model->save(['flags']));
            $this->assertEquals([
                BeforeSaveEvent::class,
            ], $classes());
            $this->assertFalse($events[0]['insert']);
            $this->assertSame($id, $events[0]['id']);
            $this->assertSame($model, $events[0]['sender']);

            $events = [];

            $fresh = Club::findOne(['id' => $id]);
            $this->assertEquals('renamed', $fresh->name);
            $this->assertEquals('active', $fresh->status);
            $this->assertEquals([
                AfterPopulateEvent::class,
            ], $classes());
            $this->assertSame($fresh, $events[0]['sender']);
            $this->assertSame($id, $events[0]['id']);
            $this->assertEquals($id, $events[0]['config']['id']);
            $this->assertEquals('renamed', $events[0]['config']['name']);
            $this->assertEquals('active', $events[0]['config']['status']);

            $other = new Club();
            $other->name = 'other';
            $other->status = 'new';
            $other->founded = $pdb->now();
            $this->assertTrue($other->save());

            $events = [];

            $found = Club::findAll();
            $this->assertCount(2, $found);
            $this->assertEquals([
                AfterPopulateEvent::class,
                AfterPopulateEvent::class,
            ], $classes());

            $senderIds = [];
            foreach ($events as $event) {
                $senderIds[] = spl_object_id($event['sender']);
            }

            $foundIds = [];
            foreach ($found as $club) {
                $foundIds[] = spl_object_id($club);
            }

            sort($senderIds);
            sort($foundIds);
            $this->assertEquals($foundIds, $senderIds);

            $events = [];

            // Delete fires after the row is gone. A second delete does not.
            $this->assertTrue($model->delete());
            $this->assertEquals([
                BeforeDeleteEvent::class,
                AfterDeleteEvent::class,
            ], $classes());
            $this->assertSame($model, $events[0]['sender']);
            $this->assertSame($model, $events[1]['sender']);
            $this->assertSame($id, $events[0]['id']);
            $this->assertSame($id, $events[1]['id']);
            $this->assertTrue($events[0]['exists']);
            $this->assertFalse($events[1]['exists']);

            $events = [];

            $this->assertFalse($model->delete());
            $this->assertEquals([
                BeforeDeleteEvent::class,
            ], $classes());
            $this->assertFalse($events[0]['exists']);
            $this->assertSame($id, $events[0]['id']);
            $this->assertSame($model, $events[0]['sender']);

            $events = [];
            $listen(DirtyClub::class);

            // An unchanged dirty model still fires before-save only.
            $dirty = new DirtyClub();
            $dirty->name = 'dirty';
            $dirty->status = 'new';
            $dirty->founded = $pdb->now();

            $this->assertTrue($dirty->save());
            $this->assertEquals([
                BeforeSaveEvent::class,
                AfterSaveEvent::class,
            ], $classes());
            $this->assertTrue($events[0]['insert']);
            $this->assertSame(0, $events[0]['id']);
            $this->assertFalse($events[0]['exists']);
            $this->assertTrue($events[1]['insert']);
            $this->assertSame($dirty->id, $events[1]['id']);
            $this->assertGreaterThan(0, $events[1]['id']);
            $this->assertTrue($events[1]['exists']);
            $this->assertSame($dirty, $events[0]['sender']);
            $this->assertSame($dirty, $events[1]['sender']);

            $events = [];

            $this->assertTrue($dirty->save());
            $this->assertEquals([
                BeforeSaveEvent::class,
            ], $classes());
            $this->assertFalse($events[0]['insert']);
            $this->assertSame($dirty->id, $events[0]['id']);
            $this->assertTrue($events[0]['exists']);
            $this->assertSame($dirty, $events[0]['sender']);
        }
        finally {
            Events::off(Club::class);
            Events::off(DirtyClub::class);
        }
    }
}

