<?php

namespace karmabunny\pdb\Models;

use InvalidArgumentException;
use karmabunny\kb\DataObject;
use karmabunny\pdb\Exceptions\RowMissingException;
use karmabunny\pdb\Pdb;
use karmabunny\pdb\PdbConfig;
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
class PdbReturn extends DataObject
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
     * @param string|array|PdbReturn $config
     * @return static
     * @throws InvalidArgumentException
     */
    public static function parse($config)
    {
        if ($config instanceof static) {
            return $config;
        }

        if (is_string($config)) {
            $config = [ 'type' => $config ];
        }

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


    /**
     * Determine an appropriate TTL between the return config and pdb config.
     *
     * @param PdbConfig $config
     * @return null|int
     */
    public function getCacheTtl(PdbConfig $config): ?int
    {
        // Can't cache PDO things.
        if ($this->type === Pdb::RETURN_PDO) {
            return null;
        }

        // Use whatever default.
        if ($this->cache_ttl === true and $config->ttl > 0) {
            return $config->ttl;
        }

        // Then this.
        if (is_numeric($this->cache_ttl) and $this->cache_ttl > 0) {
            return $this->cache_ttl;
        }

        // There's no infinite TTL.
        // Give up.
        return null;
    }


    /**
     * Format the result set.
     *
     * {@see Pdb::formatRs}
     *
     * @param PDOStatement $rs
     * @return string|int|null|array
     * @throws RowMissingException
     */
    public function format(PDOStatement $rs)
    {
        return Pdb::formatRs($rs, $this);
    }


    /**
     * Format a result into class instance.
     *
     * Given the 'class' property and an appropriate 'return type', this
     * creates instance(s) of the class.
     *
     * Valid return types are:
     * - row
     * - row?
     * - arr
     * - map-arr
     *
     * @param array $result
     * @return object[]|object|null
     * @throws InvalidArgumentException
     */
    public function buildClass($result)
    {
        if ($class = $this->class) {
            switch ($this->type) {
                case 'row':
                case 'row?':
                    return new $class($result);

                case 'arr':
                case 'map-arr':
                    foreach ($result as &$item) {
                        $item = new $class($item);
                    }
                    unset($item);
                    return $result;
                ;;
            }
        }

        return null;
    }

}
