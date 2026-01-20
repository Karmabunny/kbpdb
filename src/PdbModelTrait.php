<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;
use karmabunny\kb\Configure;
use karmabunny\pdb\Exceptions\RowMissingException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use karmabunny\kb\Reflect;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\QueryException;

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

            $default = $column->default;
            $type = strtolower($column->type);
            if (substr($type, 0, 4) === 'set(') {
                $default = new PdbSetDefaults($default);
            } elseif (substr($type, 0, 4) === 'json' || $type === 'longtext') {
                $default = new PdbJsonDefault($default);
            }

            $defaults[$column->name] = $default;
        }

        return $defaults;
    }


    /**
     * Loads default values from database table schema.
     *
     * This will only set defaults values for properties that are null.
     *
     * @return void
     */
    public function populateDefaults()
    {
        $defaults = static::getFieldDefaults();
        $reflect = new ReflectionClass($this);

        foreach ($defaults as $name => $value) {
            // Skip invalid properties, that's someone else's problem.
            try {
                $property = $reflect->getProperty($name);
                $property->setAccessible(true);
            }
            catch (ReflectionException $ex) {
                continue;
            }

            // Newer PHP is picky about typed properties.
            // Here we set these immediately.
            // @phpstan-ignore-next-line : phpstan runs on 7.1.
            if (PHP_VERSION_ID >= 70400 and !$property->isInitialized($this)) {
                if ($value instanceof PdbSetDefaults || $value instanceof PdbJsonDefault) {
                    $type = $property->getType();
                    if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
                        $property->setValue($this, $value->toArray());
                        continue;
                    }
                    $value = (string) $value;
                }

                $property->setValue($this, $value);
                continue;
            }

            // The value is not set, so set it!
            if ($property->getValue($this) === null) {
                $property->setValue($this, $value);
                continue;
            }
        }
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
     * @param bool $update If true, set 'conditions' as properties on the model.
     * @return static
     */
    public static function findOrCreate(array $conditions, bool $update = true)
    {
        try {
            $model = static::findOne($conditions);
        } catch (RowMissingException $ex) {
            // @phpstan-ignore-next-line : I don't care.
            $model = new static();
            $model->populateDefaults();

            if ($update) {
                // Remove numeric keys + non-scalar values.
                foreach ($conditions as $key => $value) {
                    if (is_numeric($key)) {
                        unset($conditions[$key]);
                    }
                    else if (!is_scalar($value)) {
                        unset($conditions[$key]);
                    }
                }

                Configure::update($model, $conditions);
            }
        }

        return $model;
    }


    /**
     * Data to be inserted or updated.
     *
     * This is a perfect spot to add generated values like audit rows
     * (date_added, date_modified, uid, etc).
     *
     * Override this to implement dirty-property behaviour.
     *
     * @return array [ column => value ]
     */
    public function getSaveData(): array
    {
        $data = Reflect::getProperties($this);
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

        return $pdb->withTransaction(function($pdb, $transaction) {
            $this->_beforeSave();

            $data = $this->getSaveData();

            if (empty($data)) {
                return true;
            }

            $this->_internalSave($data);

            // Early commit.
            $transaction->commit();

            $this->_afterSave($data);

            return (bool) $this->id;
        });
    }


    /**
     * Performed before all save actions.
     *
     * @return void
     */
    protected function _beforeSave()
    {
        // Only populate defaults for new models.
        if (!$this->id) {
            $this->populateDefaults();
        }
    }


    /**
     * Perform inserts and updates.
     *
     * This is wrapped by the save() method with a transaction.
     *
     * This modifies the 'ID' into the `$data` array to the
     *
     * @param array $data as created by {@see getSaveData()}, this is mutable
     * @return void
     */
    protected function _internalSave(array &$data)
    {
        $pdb = static::getConnection();
        $table = static::getTableName();

        if ($this->id > 0) {
            $pdb->update($table, $data, [ 'id' => $this->id ]);
        }
        else {
            $data['id'] = $pdb->insert($table, $data);
        }
    }


    /**
     * Performed after all save actions.
     *
     * @param array $data as created by {@see getSaveData()}
     * @return void
     */
    protected function _afterSave(array $data)
    {
        if (isset($data['id'])) {
            $this->id = $data['id'];
        }
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
