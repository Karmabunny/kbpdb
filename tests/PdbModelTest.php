<?php

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbSync;
use kbtests\Database;
use PHPUnit\Framework\TestCase;
use kbtests\Models\Club;


class PdbModelTest extends TestCase
{

    public function setUp(): void
    {
        $pdb = Database::getConnection();
        $pdb->query('DROP TABLE IF EXISTS ~clubs', [], 'null');

        $sync = new PdbSync($pdb, true);
        $sync->loadXml(__DIR__ . '/db_struct.xml');
        $sync->sanityCheck();
        $sync->updateDatabase();
    }


    public function testBasicModel()
    {
        // A new model.
        $model = new Club();
        $model->name = 'thingo';
        $model->status = 'new';
        $model->founded = Pdb::now();

        // Pdo is opened here.
        $this->assertTrue($model->save());
        $this->assertNull($model->date_deleted);

        $id = $model->id;
        $uid = $model->uid;
        $added = $model->date_added;
        $modified = $model->date_modified;
        $active = $model->active;

        sleep(1);

        // Update a property.
        $model->status = 'active';
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

        // Soft delete.
        $this->assertTrue($model->delete(true));
        $this->assertNotNull($model->date_deleted);
        $this->assertNotEquals($modified, $model->date_modified);

        // Still exists.
        $pdb = Database::getConnection();
        $exists = $pdb->recordExists($model->getTableName(), ['id' => $model->id]);
        $this->assertTrue($exists);

        // Hard delete.
        $this->assertTrue($model->delete(false));

        // Does not exist.
        $exists = $pdb->recordExists($model->getTableName(), ['id' => $model->id]);
        $this->assertFalse($exists);
    }
}
