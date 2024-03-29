<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb\Models;

use InvalidArgumentException;
use karmabunny\kb\Configurable;
use karmabunny\kb\ConfigurableInit;
use karmabunny\kb\DataObject;
use karmabunny\pdb\Exceptions\RowMissingException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbReturnInterface;
use PDO;
use PDOStatement;

/**
 * Configuration for query return types.
 *
 * - `type`      (string)      - 'pdo' or a format type {@see Pdb::formatRs}
 * - `class`     (string)      - a class name to wrap results (for 'row', 'arr', 'map-arr')
 * - `cache_ttl` (int|bool)    - cache expiry in seconds, true to use global config, false to disable
 * - `cache_key` (string)      - an override cache key, providing a user with invalidation powers
 * - `map_key`   (string|null) - a column for 'map-arr' or `null` for the first column
 * - `throw`     (bool)        - throw an exception if the row is missing (default true)
 *
 * This class can be overridden if one wants to customize the formatting behaviour.
 * By default this base class uses the `Pdb::formatRs` helper.
 *
 * @package karmabunny\pdb
 */
class PdbReturn extends DataObject implements PdbReturnInterface
{
    /**
     * A return type is one support by the `format()` method of this class.
     *
     * The default set of return types are specified in the `Pdb::RETURN` enum.
     *
     * This class can be extended and thus support _more_ return types.
     *
     * @var string
     */
    public $type;

    /**
     * A class name.
     *
     * Wrap results in a class instance, passing the object into the constructor.
     *
     * Supported types:
     * - row
     * - arr
     * - map-arr
     *
     * @var string|null
     */
    public $class;

    /**
     * A Time-to-live for the caching layer.
     *
     * This enables query caching with the configured caching adapter.
     *
     * If set to `true` this uses the Pdb `ttl` config.
     *
     * @see PdbCache
     * @var int|bool seconds
     */
    public $cache_ttl = false;

    /**
     * The key for a query is generated as a shasum of the query + parameters.
     *
     * However, one is able to override this if they wish to manually invalidate
     * the cache _before_ the TTL expires.
     *
     * @var string|null
     */
    public $cache_key;

    /**
     * Used for the return type: `map-arr`.
     *
     * This specifies the column to use as the key for the map.
     *
     * If null, it uses the first column returned in the query.
     *
     * @var string|null
     */
    public $map_key = null;

    /**
     * Whether RowMissingException should be thrown if the row is missing.
     *
     * This only applies for the following return types:
     * - val
     * - row
     * - row-num
     *
     * This is also inferred by the shorthand `val?` style return-types when
     * using the `parse()` helper.
     *
     * @var bool
     */
    public $throw = true;


    /**
     * Parse a return type config.
     *
     * This converts string-style 'return types' as documented in {@see Pdb::resultRs}.
     *
     * The object/array syntax permits class building, cache controls, nullables, and more.
     *
     * @param string|array $config
     * @return PdbReturn
     * @throws InvalidArgumentException
     */
    public static function parse($config): PdbReturn
    {
        if (is_string($config)) {
            $config = [ 'type' => $config ];
        }

        if (is_array($config)) {
            if ($type = $config['type'] ?? null) {
                $type = self::parseType($type);
                $config = array_merge($config, $type);
            }

            Pdb::validateReturnType($config['type'] ?? '(empty)');

            // @phpstan-ignore-next-line
            $instance = new static();
            $instance->update($config);
            return $instance;
        }

        // @phpstan-ignore-next-line : there's no guarantees. Gotta check.
        $type = is_object($config) ? get_class($config) : gettype($config);
        throw new InvalidArgumentException('Invalid return type: ' . $type);
    }


    /**
     * A return type string may contain a few additional indicators.
     *
     * - `'*:null'` or `'*?'` will set 'throw' to false
     * - `'map-arr:{column}'` will set 'map_key'
     *
     * The result 'type' will be cleaned of these 'arguments'.
     *
     * @param string $type
     * @return array [ type, throw, map_key ]
     */
    public static function parseType(string $type): array
    {
        $config = [];

        $args = explode(':', $type, 2);
        $config['type'] = array_shift($args);

        $type = rtrim($config['type'], '?');

        // Extract the shorthand nullish form.
        if ($config['type'] !== $type) {
            $config['throw'] = false;
        }

        if (isset($args[0])) {
            if ($args[0] === 'null') {
                $config['throw'] = false;
            }

            // Extract column args.
            if ($config['type'] === 'map-arr') {
                $config['map_key'] = $args[0];
            }
        }

        return $config;
    }


    /** @inheritdoc */
    public function getCacheTtl(): int
    {
        // Can't cache PDO things.
        if ($this->type === Pdb::RETURN_PDO) {
            return 0;
        }

        // Use whatever default.
        if ($this->cache_ttl === true) {
            return -1;
        }

        // A valid TTL.
        if (is_numeric($this->cache_ttl) and $this->cache_ttl > 0) {
            return $this->cache_ttl;
        }

        // Give up.
        return 0;
    }


    /** @inheritdoc */
    public function getCacheKey(): string
    {
        $key = rtrim($this->type, '?');

        // Optionally permit the user to invalidate their own cache.
        if ($this->cache_key) {

            // This is also used to cache the key across queue-prepare-execute.
            // Don't re-add the prefix.
            if (strpos($this->cache_key, $key) === 0) {
                return $this->cache_key;
            }

            // This is just the user's custom key.
            return $key . ':' . $this->cache_key;
        }

        return $key;
    }


    /** @inheritdoc */
    public function hasFormatting(): bool
    {
        return $this->type !== Pdb::RETURN_PDO;
    }


    /** @inheritdoc */
    public function format(PDOStatement $rs)
    {
        $nullable = !$this->throw;

        switch ($this->type) {
        case 'null':
            return null;

        case 'count':
            // Using SQL count() is always faster than rowCount().
            if (preg_match('/^\s*SELECT\s+COUNT\([1*]\)/i', $rs->queryString)) {
                $row = $rs->fetch(PDO::FETCH_NUM);
                return $row[0] ?? 0;
            }

            return $rs->rowCount();

        case 'arr':
            return $rs->fetchAll(PDO::FETCH_ASSOC);

        case 'arr-num':
            return $rs->fetchAll(PDO::FETCH_NUM);

        case 'row?':
            $nullable = true;
            // fall-through.

        case 'row':
            $row = $rs->fetch(PDO::FETCH_ASSOC);
            if (!empty($row)) {
                return $row;
            }
            if ($nullable) {
                return null;
            }
            throw new RowMissingException('Expected a row');

        case 'row-num?':
            $nullable = true;
            // fall-through.

        case 'row-num':
            $row = $rs->fetch(PDO::FETCH_NUM);
            if (!empty($row)) {
                return $row;
            }
            if ($nullable) {
                return null;
            }
            throw new RowMissingException('Expected a row');

        case 'map':
            if ($rs->columnCount() < 2) {
                throw new InvalidArgumentException('Two columns required');
            }
            $map = array();
            while ($row = $rs->fetch(PDO::FETCH_NUM)) {
                $map[$row[0]] = $row[1];
            }
            return $map;

        case 'map-arr':
            $map = array();
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                $id = $row[$this->map_key] ?? reset($row);
                $map[$id] = $row;
            }
            return $map;

        case 'val?':
            $nullable = true;
            // fall-through.

        case 'val':
            $row = $rs->fetch(PDO::FETCH_NUM);
            if (!empty($row)) {
                return $row[0];
            }
            if ($nullable) {
                return null;
            }
            throw new RowMissingException('Expected a row');

        case 'col':
            $arr = [];
            while (($col = $rs->fetchColumn(0)) !== false) {
                $arr[] = $col;
            }
            return $arr;

        default:
            $err = 'Unknown return type: ' . $this->type;
            throw new InvalidArgumentException($err);
        }
    }


    /** @inheritdoc */
    public function buildClass($result)
    {
        if ($class = $this->class) {
            if (is_subclass_of($class, Configurable::class)) {
                $create = function($item) use ($class) {
                    $object = new $class();
                    $object->update($item);

                    if ($object instanceof ConfigurableInit) {
                        $object->init();
                    }

                    return $object;
                };
            }
            else {
                $create = function($item) use ($class) {
                    $object = new $class();
                    foreach ($item as $key => $value) {
                        $object->$key = $value;
                    }
                    return $object;
                };
            }

            switch ($this->type) {
                case 'row':
                case 'row?':
                    return $create($result);

                case 'arr':
                case 'map-arr':
                    return array_map($create, $result);
                ;;
            }
        }

        return null;
    }

}
