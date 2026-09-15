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
        $this->pdb ??= Database::getConnection('sqlite', true);

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
