<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use DOMElement;
use InvalidArgumentException;

/**
 *
 * @package karmabunny\pdb
 */
class PdbHelpers
{

    // TODO Find a better home for these.
    const TYPE_SELECT = 'SELECT';
    const TYPE_INSERT = 'INSERT';
    const TYPE_UPDATE = 'UPDATE';
    const TYPE_CREATE = 'CREATE';
    const TYPE_ALTER  = 'ALTER';
    const TYPE_DELETE = 'DELETE';
    const TYPE_DROP   = 'DROP';

    const TYPES = [
        self::TYPE_SELECT,
        self::TYPE_INSERT,
        self::TYPE_UPDATE,
        self::TYPE_CREATE,
        self::TYPE_ALTER,
        self::TYPE_DELETE,
        self::TYPE_DROP,
    ];


    const RE_IDENTIFIER = '/^~?[a-z_][a-z_0-9]*$/i';

    const RE_IDENTIFIER_EXTENDED = '/^\~?[a-z_][a-z_0-9]*(?:\.[a-z_][a-z_0-9]*|\.\*)?$/i';

    const RE_IDENTIFIER_PARTS = '/(\~?[a-z_][a-z_0-9]*)\.([a-z_][a-z_0-9]*|\.\*)/i';

    const RE_IDENTIFIER_PREFIX = '/\~([a-z0-9_]+)/i';

    const RE_FUNCTION = '/[a-z_]+\(.*\)$/i';


    /**
     * Determine the query type.
     *
     * @param string $query
     * @return string|null null if invalid.
     */
    public static function getQueryType(string $query)
    {
        $matches = [];
        preg_match('/^\s*(\w+)[^\w]/', $query, $matches);
        $type = strtoupper($matches[1] ?? '');

        if (!in_array($type, self::TYPES)) return null;
        return $type;
    }


    /**
     * Normalise a column type to something a bit more consistent.
     *
     * @param string $type
     * @return string
     */
    public static function normalizeType(string $type): string
    {
        $type = trim($type);
        $matches = [];

        // Match: "name (format/values)"
        if (preg_match('/([a-z ]+)\s*(\([^)]+\))?/i', $type, $matches)) {
            $name = trim(strtoupper($matches[1]));
            $values = trim($matches[2] ?? '', ' ()');

            // Any mention of INT, strip the format.
            if (strpos($name, 'INT') !== false) {
                return strtoupper(preg_replace('/\([^)]+\)/', '', $type));
            }

            switch ($name) {
                // Tidy up values without changing their casing.
                case 'SET':
                case 'ENUM':
                    $values = preg_replace("/'\s*,\s*'/", "','", $values);
                    return "{$name}({$values})";

                // No.
                case 'DOUBLE PRECISION':
                case 'REAL':
                    return 'DOUBLE';

                // Drop the precision. A float is a float. Do your number
                // formatting elsewhere.
                // This also simplifies errors from the automatic conversion
                // to doubles for sufficiently large precision (24+).
                case 'FLOAT':
                    return 'FLOAT';

                // Always include the digit + decimals.
                // Defaults are 10 and 0, respectively.
                case 'DECIMAL':
                case 'DEC':
                case 'FIXED':
                case 'NUMERIC':
                    $parts = explode(',', $values, 2);
                    $digit = (int) ($parts[0] ?? 10);
                    $decimal = (int) ($parts[1] ?? 0);

                    return "DECIMAL({$digit},{$decimal})";
            }
        }

        // Otherwise just uppercase the whole lot.
        return strtoupper($type);
    }


    /**
     * Strips string elements from a query, e.g. 'hey', "yeah", and 'it\'s nice'.
     *
     * This function is used to support {@see Pdb::getBindSubset}.
     *
     * @param string $q The query
     * @return string The modified query
     */
    public static function stripStrings($q)
    {
        $q = preg_replace('/\'(?:\\\\\'|[^\'\\\\])*\'/', '', $q);
        $q = preg_replace('/"(?:\\\\"|[^"\\\\])*"/', '', $q);
        return $q;
    }


    /**
     * Escapes the special characters % and _ for use in a LIKE clause.
     *
     * SQL permits one to set the escape character, so the second parameter
     * here should match that of the query.
     *
     * `WHERE x LIKE '^%foo' ESCAPE '^'`
     *
     * Use this like:
     *
     * ```php
     * // Be aware this example is not safely quoted for brevity.
     * // Use prepared queries or `Pdb::quoteValue()`.
     *
     * $esc = '^';
     * $like = likeEscape('%foo', $esc);
     * $q = "WHERE x LIKE '{$like}' ESCAPE '{$esc}'";
     * ```
     *
     * This defaults to backslash, which is the default for most (all?) databases.
     *
     * @param string $str
     * @param string $escape default backslash `\`
     * @return string
     */
    public static function likeEscape(string $str, string $escape = '\\'): string
    {
        return strtr($str, [
            '%' => $escape . '%',
            '_' => $escape . '_',
        ]);
    }


    /**
     * Escape quoted fields.
     *
     * Such as; `"abc"def" => "abc""def"`
     *
     * @param string $field
     * @param array $quotes [ left, right ]
     * @return string quoted field
     */
    public static function fieldEscape(string $field, array $quotes): string
    {
        [$left, $right] = $quotes;

        return strtr($field, [
            $left => $left . $left,
            $right => $right . $right,
        ]);
    }


    /**
     * Parse an alias array or field.
     *
     * The second item is null if no alias present.
     *
     * This converts:
     * - `[column => alias]` (array)
     * - `'column as alias'` (string, full syntax)
     * - `'column alias'` (string, shorthand)
     *
     * @param string|string[] $field
     * @return array [ field, alias ] second param is null if no alias is present.
     */
    public static function parseAlias($field): array
    {
        if (is_array($field)) {
            // Pass-through.
            if (count($field) > 1) {
                return array_values($field);
            }

            $value = reset($field);
            $key = key($field);

            // Flatten it.
            if (is_numeric($key)) {
                return [$value, null];
            }

            // Convert [ column => alias ] to [ column, alias ]
            return [ $key, $value ];
        }

        // Convert 'column as alias' to [ column, alias ]
        $field = trim($field);

        $matches = [];

        if (preg_match('/^(.+?)\s+(?:AS\s+)?([a-z_][a-z_0-9]+)$/i', $field, $matches)) {
            [, $field, $alias] = $matches;
            return [$field, $alias];
        }

        return [$field, null];
    }


    /**
     * Convert SQL types into PHP types.
     *
     * This does approximate conversions, e.g.
     * ```
     * 'VARCHAR' => 'string'
     * 'DATE' => 'datetime'
     * 'SET' => 'array'
     * ```
     *
     * In 'strict' mode this will only return the real types that are returned
     * from the database, one of `string|int|float`.
     *
     * Consider 'non-strict' mode as more of a 'hint' about how to use the value.
     * Such as;
     * - 'datetime' can be jammed into `strtotime()`
     * - 'array' can be exploded into an array
     *
     * @param string $type the SQL type
     * @param bool $strict only return real types
     * @return string|null the PHP type, null if unknown
     */
    public static function convertDataType(string $type, $strict = false)
    {
        $matches = [];

        if (preg_match('/(?:
            (char|text|binary|blob|^enum|^bit$)|
            (int|year|timestamp)|
            (float|dec|real|double|fixed|numeric)|
            (date|time)|
            (^set|^json)|
            (bool)
        )/ix', $type, $matches)) {
            if ($matches[1]) return 'string';
            if ($matches[2]) return 'int';
            if ($matches[3]) return 'float';
            if ($matches[4]) return $strict ? 'string' : 'datetime';
            if ($matches[5]) return $strict ? 'string' : 'array';
            if ($matches[6]) return 'bool';
        }

        return null;
    }


    /**
     * Convert an ENUM or SET definition from MySQL into an array of values
     *
     * @param string $enum_defn The definition from MySQL, e.g. ENUM('aa','bb','cc')
     * @return array Numerically indexed
     * @throws InvalidArgumentException
     */
    public static function convertEnumArr($enum_defn)
    {
        $pattern = '/^(?:ENUM|SET)\s*\(\s*\'/i';
        if (!preg_match($pattern, $enum_defn)) {
            throw new InvalidArgumentException("Definition is not an ENUM or SET");
        }

        // Remove enclosing ENUM('...') or SET('...')
        $enum_defn = preg_replace($pattern, '', $enum_defn);
        $enum_defn = preg_replace('/\'\s*\)\s*$/', '', $enum_defn);

        // SQL escapes ' characters with ''
        // So split on all ',' which aren't followed by a ' character
        $vals = preg_split("/',\s*'(?!')/", $enum_defn);

        // Then convert any '' characters back into ' characters
        foreach ($vals as &$v) {
            $v = str_replace("''", "'", $v);
        }

        return $vals;
    }


    /**
     *
     * @param string $charset
     * @return int
     */
    public static function getCharsetSize(string $charset): int
    {
        switch ($charset) {
            case 'latin1':
            default:
                return 1;

            case 'ucs2':
            case 'utf16':
            case 'utf16le':
                return 2;

            case 'utf8':
            case 'utf8mb3':
                return 3;

            case 'utf8mb4':
            case 'utf32':
                return 4;
        }
    }


    /**
     *
     * https://dev.mysql.com/doc/refman/8.0/en/storage-requirements.html#data-types-storage-reqs-date-time
     *
     * @param string $type
     * @param string $charset
     * @return int
     * @throws InvalidArgumentException
     */
    public static function getColumnSize(string $type, string $charset = 'utf8'): int
    {
        $pattern = '/
            (tinyint)|
            (smallint)|
            (mediumint)|
            (bigint)|
            (int)|
            (float)|
            (double|real)|
            (dec|fixed|numeric)|
            (char|varchar)|
            (binary|varbinary)|
            (enum)|
            (set)|
            (year)|
            (datetime)|
            (timestamp)|
            (date|time)
        /ix';

        $matches = [];

        if (!preg_match($pattern, $type, $matches)) {
            return 0;
        }

        // tinyint
        if (!empty($matches[1])) return 1;
        // smallint
        if (!empty($matches[2])) return 2;
        // mediumint
        if (!empty($matches[3])) return 3;
        // bigint
        if (!empty($matches[4])) return 8;
        // int
        if (!empty($matches[5])) return 4;

        // float
        if (!empty($matches[6])) {
            $size = (int) preg_replace('/^.*?(\d+).*?$/', '$1', $type);
            return $size > 25 ? 8 : 4;
        }

        // double
        if (!empty($matches[7])) {
            return 8;
        }

        // decimals
        if (!empty($matches[8])) {
            $matches = [];

            if (preg_match('/(\d+),(\d+)?/', $type, $matches)) {
                [, $scale, $precision] = $matches;

                $size = 0;
                $size += (($precision - $scale) / 9) * 4;
                $size += ceil($precision - $scale % 9 / 2);
                $size += ($scale / 9) * 4;
                $size += ceil($scale % 9 / 2);

                return (int) $size;
            }

            return 0;
        }

        // char + varchar
        if (!empty($matches[9])) {
            $size = (int) preg_replace('/^.*?(\d+).*?$/', '$1', $type);
            $char_size = self::getCharsetSize($charset);
            return ($size * $char_size) + 1;
        }

        // binary varbinary
        if (!empty($matches[10])) {
            $size = (int) preg_replace('/^.*?(\d+).*?$/', '$1', $type);
            return $size + 1;
        }

        // enum
        if (!empty($matches[11])) {
            $enum = self::convertEnumArr($type);
            return count($enum) > 255 ? 2 : 1;
        }

        // set
        if (!empty($matches[12])) {
            $set = self::convertEnumArr($type);
            $size = count($set) + 7 / 8;

            if ($size > 4) return 8;
            if ($size > 3) return 4;
            if ($size > 2) return 3;
            if ($size > 1) return 2;
            return 1;
        }

        // TODO support for fractional seconds.
        // time: 3 - 6 bytes
        // datetime: 5 - 8 bytes
        // timestamp: 4 - 7 bytes

        // year
        if (!empty($matches[13])) return 1;
        // datetime
        if (!empty($matches[14])) return 8;
        // timestamp
        if (!empty($matches[15])) return 4;
        // date + time
        if (!empty($matches[16])) return 3;

        return 0;
    }


    /**
     * Create a bunch of placeholder bind thingos.
     *
     * This accepts a number or an array of keys.
     * - If a number, it will create that many placeholders.
     * - If an array of keys, it will create placeholders for each item.
     *
     * Examples:
     * - `(int) 4 => '?, ?, ?, ?'`
     * - `[a, b, c] => ':a, :b, :c'`
     *
     * @param int|string[] $data
     * @return string
     */
    public static function bindPlaceholders($data): string
    {
        if (is_array($data)) {
            $keys = [];
            foreach ($data as $key) {
                $keys[] = ':' . $key;
            }
        }
        else {
            $keys = array_fill(0, $data, '?');
        }

        return implode(', ', $keys);
    }


    /**
     * Gets the subset of bind params which are associated with a particular query from a generic list of bind params.
     * This is used to support the SQL DB tool.
     * N.B. This probably won't work if you mix named and numbered params in the same query.
     *
     * @param string $q
     * @param array $binds generic list of binds
     * @return array
     */
    public static function getBindSubset(string $q, array $binds)
    {
        $q = PdbHelpers::stripStrings($q);

        // Strip named params which aren't required
        // N.B. identifier format matches self::validateIdentifier
        $params = [];
        preg_match_all('/:[a-z_][a-z_0-9]*/i', $q, $params);
        $params = $params[0];
        foreach ($binds as $key => $val) {
            if (is_int($key)) continue;

            if (count($params) == 0) {
                unset($binds[$key]);
                continue;
            }

            $required = false;
            foreach ($params as $param) {
                if ($key[0] == ':') {
                    if ($param[0] != ':') {
                        $param = ':' . $param;
                    }
                } else {
                    $param = ltrim($param, ':');
                }
                if ($key == $param) {
                    $required = true;
                }
            }
            if (!$required) {
                unset($binds[$key]);
            }
        }

        // Strip numbered params which aren't required
        $params = [];
        preg_match_all('/\?/', $q, $params);
        $params = $params[0];
        if (count($params) == 0) {
            foreach ($binds as $key => $bind) {
                if (is_int($key)) {
                    unset($binds[$key]);
                }
            }
            return $binds;
        }

        foreach ($binds as $key => $val) {
            if (!is_int($key)) unset($binds[$key]);
        }
        while (count($params) < count($binds)) {
            array_pop($binds);
        }

        return $binds;
    }


    /**
     * Makes a query have pretty indentation.
     *
     * Typically a query is written as a multiline string embedded within PHP code, and when printed as-is, it looks
     * horrible as the PHP indentation is basically incorporated into the SQL.
     *
     * Alternatively, in PHP7.3+ heredoc strings will strip PHP indentation.
     *
     * N.B. All tabs are converted to 4 spaces each
     *
     * @param string $query
     * @return string
     */
    public static function prettyQueryIndentation(string $query): string
    {
        $lines = explode("\n", $query);
        $lowest_indent = 10000;

        foreach ($lines as $num => &$line) {
            if ($num == 0) continue;

            $line = str_replace("\t", '    ', $line);

            $matches = [];
            preg_match('/^ +/', $line, $matches);
            $lowest_indent = min($lowest_indent, strlen(@$matches[0]));
        }

        if ($lowest_indent == 0) return implode("\n", $lines);

        $pattern = '/^' . str_repeat(' ', $lowest_indent) . '/';
        foreach ($lines as $num => &$line) {
            if ($num == 0) continue;

            $line = preg_replace($pattern, '', $line);
        }
        return implode("\n", $lines);
    }

}
