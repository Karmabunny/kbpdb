<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;


/**
 *
 * @package karmabunny\pdb
 */
class PdbHelpers
{

    /**
     * Split an aliased field into a string pair.
     *
     * The second item is null if no alias present.
     *
     * @param string $value
     * @return array [field, alias]
     */
    public static function alias(string $value): array
    {
        $match = [];
        if (!preg_match('/([^\s]+)\s+(?:AS\s+)?([^\s]+)/i', $value, $match)) {
            return [ trim($value), null ];
        }

        // TODO also trim quotes.
        return [ trim($match[1]), trim($match[2]) ];
    }


    /**
     * Normalize an alias.
     *
     * @param string $field
     * @return string
     */
    public static function normalizeAlias(string $field): string
    {
        [$field, $alias] = self::alias($field);
        if ($alias) $field .= " AS {$alias}";
        return $field;
    }


    /**
     * Normalize all the aliases in a list.
     *
     * @param string[] $fields
     * @return string[]
     */
    public static function normalizeAliases(array $fields): array
    {
        foreach ($fields as &$field) {
            $field = self::normalizeAlias($field);
        }
        return $fields;
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
     * @param string $str
     * @return string
     */
    public static function likeEscape(string $str)
    {
        return str_replace(['_', '%'], ['\\_', '\\%'], $str);
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
