<?php

namespace kbtests;

use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;

/**
 *
 */
class PdbSqliteTest extends BasePdbCase
{
    public function setUp(): void
    {
        $this->pdb ??= Pdb::create([
            'type' => PdbConfig::TYPE_SQLITE,
            'dsn' => __DIR__ . '/db.sqlite',
        ]);

        parent::setUp();

        // Hack because sqlite doesn't support enums.
        // PdbSync handles this naturally.
        foreach ($this->struct->tables as $table) {
            foreach ($table->columns as $column) {
                if (preg_match('/^(ENUM|SET)/', $column->type)) {
                    $column->type = 'TEXT';
                }
            }
        }
    }
}
