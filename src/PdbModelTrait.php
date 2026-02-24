<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DateTimeInterface;
use InvalidArgumentException;
use karmabunny\kb\ConfigurableInit;
use karmabunny\kb\Configure;
use karmabunny\kb\Json;
use karmabunny\pdb\Exceptions\RowMissingException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use karmabunny\kb\Reflect;
use karmabunny\pdb\Exceptions\ConnectionException;
use karmabunny\pdb\Exceptions\QueryException;
use karmabunny\pdb\Models\PdbColumn;
use ReflectionProperty;

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
            if (!$column->hasDefault()) continue;

            $default = $column->default;
            $type = strtolower($column->type);
            if ($default !== null) {
                if (substr($type, 0, 4) === 'set(') {
                    $default = new PdbSetDefaults($default);
                } elseif (substr($type, 0, 4) === 'json' || $type === 'longtext') {
                    $default = new PdbJsonDefault($default);
                }
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
                // @phpstan-ignore-next-line : already guarded.
                $type = $property->getType();
                if ($value instanceof PdbSetDefaults || $value instanceof PdbJsonDefault) {
                    if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
                        $property->setValue($this, $value->toArray());
                        continue;
                    }
                    $value = (string) $value;
                }

                if ($value === null && $type instanceof ReflectionType && !$type->allowsNull()) {
                    continue;
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
     * Populate a model with a DB row.
     *
     * Extend this to implement custom logic, e.g. dirty property behaviour.
     *
     * @param static $instance
     * @param array $config
     * @return void
     */
    public static function populate($instance, array $config)
    {
        foreach ($config as $key => $value) {
            if (!property_exists($instance, $key)) {
                continue;
            }

            static::typeCastValue($key, $value);
            $instance->$key = $value;
        }

        // Preserve init() behaviour.
        if ($instance instanceof ConfigurableInit) {
            $instance->init();
        }
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
        $data = Reflect::getProperties($this, null);

        foreach ($data as $key => &$value) {

            // Convert arrays to SET or JSON strings.
            if (is_array($value)) {
                if (static::getFieldType($key) === 'set') {
                    $value = implode(',', $value);
                }
                else {
                    $value = json_encode($value);
                }

                continue;
            }

            // Ensure booleans are integers.
            if (is_bool($value)) {
                $value = (int) $value;
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $value = $value->format(Pdb::FORMAT_DATE_TIME);
                continue;
            }
        }

        unset($value);

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
        if (empty($this->id)) {
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

        if (!empty($this->id)) {
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


    /**
     * Get the field definitions for the table that holds this record
     *
     * @return array<string,PdbColumn>
     */
    protected static function getFieldList(): array
    {
        static $fieldList = [];

        $table = static::getTableName();

        if (!isset($fieldList[$table])) {
            $pdb = static::getConnection();
            $fieldList[$table] = $pdb->fieldList($table);
        }

        return $fieldList[$table];
    }


    /**
     * Test for a field type.
     *
     * @param string $field_name
     * @return string|null
     */
    protected static function getFieldType(string $field_name)
    {
        $fields = static::getFieldList();
        $field_defn = $fields[$field_name]['type'] ?? null;

        if ($field_defn === null) {
            return null;
        }

        if (stripos($field_defn, 'set(') === 0) {
            return 'set';
        }

        if (stripos($field_defn, 'enum(') === 0) {
            return 'enum';
        }

        return strtolower($field_defn);
    }


    /**
     * Convert a property value to it's appropriate type.
     *
     * Supported types:
     * - array: from JSON-encoded string
     * - array: from comma-separated SET
     * - bool: from '0' or '1'
     * - int: from numeric string
     * - float: from numeric string
     * - datetime: from string
     * - string: from anything else
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    protected static function typeCastValue(string $property, &$value): void
    {
        // We'll drop old PHP very soon. Promise.
        if (PHP_VERSION_ID < 74000) {
            return;
        }

        // @phpstan-ignore-next-line : already guarded.
        $type = (new ReflectionProperty(static::class, $property))->getType();

        // Can't do anything with this.
        if (!$type instanceof ReflectionNamedType) {
            return;
        }

        // Strict empty, not PHP empty.
        $is_empty = ($value === '' or $value === null);

        if ($type->allowsNull() and $is_empty) {
            $value = null;
            return;
        }

        if (!is_array($value) and $type->getName() === 'array') {
            if ($is_empty or !is_string($value)) {
                $value = [];
                return;
            }

            if (static::getFieldType($property) === 'set') {
                $value = explode(',', $value);
                return;
            }

            // N.B. data source (e.g. MySQL JSON column) should always provide
            // valid JSON, so Json::decode should never throw an exception,
            // outside of memory/depth constraints
            $value = Json::decode($value);

            // Gracefully handle change from single value to multi-value column
            if (is_scalar($value)) {
                $value = [$value];
            }

            return;
        }

        // Converting booleans from scalar types.
        if (!is_bool($value) and $type->getName() === 'bool') {
            if ($is_empty or !is_scalar($value)) {
                $value = false;
                return;
            }

            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            return;
        }

        // Converting integers from numeric types.
        if (!is_int($value) and $type->getName() === 'int') {
            if ($is_empty or !is_numeric($value)) {
                $value = 0;
                return;
            }

            $value = (int) $value;
            return;
        }

        // Converting floats from numeric types.
        if (!is_float($value) and $type->getName() === 'float') {
            if ($is_empty or !is_numeric($value)) {
                $value = 0.0;
                return;
            }

            $value = (float) $value;
            return;
        }

        // Converting datetimes from strings or numeric types.
        // Numeric assumes a timestamp from the Unix epoch.
        // String is anything date-ish looking.
        $type_name = $type->getName();
        if (
            !$value instanceof DateTimeInterface
            and (
                $type_name === DateTimeInterface::class
                or is_subclass_of($type_name, DateTimeInterface::class)
            )
        ) {
            $class = ($type_name === DateTimeInterface::class)
                ? \DateTimeImmutable::class
                : $type_name;
            $tz = static::getConnection()->getTimezone();

            if ($is_empty) {
                $value = new $class('@0', $tz);
                return;
            }

            if (is_numeric($value)) {
                $value = new $class('@' . $value, $tz);
                return;
            }

            if (is_string($value)) {
                $value = new $class($value, $tz);
                return;
            }

            // Invalid type, fallback to 0.
            $value = new $class('@0', $tz);
            return;
        }
    }
}
