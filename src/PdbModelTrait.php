<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;


/**
 * This implements basic methods for {@see PdbModelInterface}.
 *
 * - The application is responsible for implementing `getConnection()`.
 * - Each concrete model should implement their own `getTableName()`.
 *
 * For example:
 *
 * ```
 * // app/Model.php
 * abstract class Model implements PdbModelInterface
 * {
 *    use PdbModelTrait;
 *
 *    public static function getConnection(): Pdb
 *    {
 *       return MyApp::getPdb();
 *    }
 * }
 *
 * // app/Models/User.php
 * class User extends Model
 * {
 *    public static function getTableName(): string
 *    {
 *       return 'users';
 *    }
 * }
 * ```
 *
 * @package karmabunny\pdb
 */
trait PdbModelTrait
{

    /** @var int */
    public $id = 0;


    /**
     * The connection used queries in this model.
     *
     * @return Pdb
     */
    public abstract static function getConnection(): Pdb;


    /**
     * The table name for this model, non-prefixed.
     *
     * @return string
     */
    public abstract static function getTableName(): string;


    /**
     * Create a query for this model.
     *
     * @param array $conditions
     * @return PdbModelQuery
     */
    public static function find(array $conditions = []): PdbModelQuery
    {
        return (new PdbModelQuery(static::class))
            ->where($conditions);
    }


    /**
     * Get a list of default values for the database record.
     *
     * @return array
     */
    public static function getFieldDefaults(): array
    {
        $defaults = [];

        $pdb = static::getConnection();
        $table = static::getTableName();
        $columns = $pdb->fieldList($table);

        foreach ($columns as $column) {
            if (!property_exists(static::class, $column->name)) continue;
            if ($column->default === null) continue;

            $defaults[$column->name] = $column->default;
        }

        return $defaults;
    }


    /**
     * Loads default values from database table schema.
     *
     * This will only set defaults values for properties that are null.
     *
     * @return static
     */
    public function populateDefaults()
    {
        $defaults = static::getFieldDefaults();

        foreach ($defaults as $name => $value) {
            if ($this->{$name} !== null) continue;
            $this->{$name} = $value;
        }

        return $this;
    }


    /**
     * Find one model.
     *
     * @param array $conditions
     * @return static
     */
    public static function findOne(array $conditions)
    {
        /** @var static */
        return static::find($conditions)->one();
    }


    /**
     * Find a list of models.
     *
     * @param array $conditions
     * @return static[]
     */
    public static function findAll(array $conditions = [])
    {
        return static::find($conditions)->all();
    }


    /**
     *
     * @return array
     */
    public function getSaveData(): array
    {
        $data = iterator_to_array($this, true);
        unset($data['id']);
        return $data;
    }


    /**
     * Save this model.
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function save(): bool
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        $transact = false;

        // Start a transaction but only if there isn't one already.
        if (!$pdb->inTransaction()) {
            $pdb->transact();
            $transact = true;
        }

        try {
            $this->_beforeSave();

            // Only populate defaults for new models.
            if (!$this->id) {
                $this->populateDefaults();
            }

            $data = $this->getSaveData();

            if ($this->id > 0) {
                if (empty($data)) return true;
                $pdb->update($table, $data, [ 'id' => $this->id ]);
            }
            else {
                $data['id'] = $pdb->insert($table, $data);
            }

            // Punch it.
            if ($transact and $pdb->inTransaction()) {
                $pdb->commit();
            }

            $this->id = $data['id'];
            $this->_afterSave($data);
        }
        finally {
            if ($transact and $pdb->inTransaction()) {
                $pdb->rollback();
            }
        }

        return (bool) $this->id;
    }


    /**
     *
     * @return void
     */
    protected function _beforeSave()
    {
    }


    /**
     *
     * @param array $data
     * @return void
     */
    protected function _afterSave(array $data)
    {
    }


    /**
     * Delete this model.
     *
     * @return bool True if the record was deleted.
     * @throws InvalidArgumentException
     * @throws QueryException
     * @throws ConnectionException
     */
    public function delete(): bool
    {
        $pdb = static::getConnection();
        $table = static::getTableName();
        return (bool) $pdb->delete($table, ['id' => $this->id]);
    }

}
