<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use karmabunny\pdb\Exceptions\RowMissingException;

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
     * Loads default values from database table schema.
     *
     * This will only set defaults values for properties that are null.
     *
     * @return static
     */
    public function populateDefaults()
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        $columns = $pdb->fieldList($table);

        foreach ($columns as $column) {
            if (
                property_exists($this, $column->name)
                and $this->{$column->name} === null
                and $column->default !== null
            ) {
                $this->{$column->name} = $column->default;
            }
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
     * Find one model. Create a default one if not found.
     *
     * @see {PdbQuery::find()}
     * @param array $conditions
     * @return static
     */
    public static function findOrCreate(array $conditions)
    {
        try {
            $model = static::findOne($conditions);
        } catch (RowMissingException $ex) {
            $model = new static();
            $model->populateDefaults();
        }

        return $model;
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
        // Only populate defaults for new models.
        if (!$this->id) {
            $this->populateDefaults();
        }

        $pdb = static::getConnection();
        $table = static::getTableName();
        $data = iterator_to_array($this);

        if ($this->id > 0) {
            $pdb->update($table, $data, [ 'id' => $this->id ]);
        }
        else {
            $this->id = $pdb->insert($table, $data);
        }

        return (bool) $this->id;
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
