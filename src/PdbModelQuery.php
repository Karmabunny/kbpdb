<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;

/**
 *
 *
 * @package karmabunny\pdb
 */
class PdbModelQuery extends PdbQuery
{


    /** @var bool */
    public $_inflect = false;


    /**
     *
     * @param string $model
     * @throws InvalidArgumentException
     */
    public function __construct(string $model, array $config = [])
    {
        if (!is_subclass_of($model, PdbModelInterface::class)) {
            throw new InvalidArgumentException("{$model} must implement PdbModelInterface");
        }

        /** @var Pdb $pdb */
        $pdb = $model::getConnection();
        $table = $model::getTableName();

        parent::__construct($pdb, $config);
        $this->from($table);
        $this->as($model);
    }


    /**
     * Enable table name inflection.
     *
     * This will automatically alias table names for FROM and JOIN statements.
     * But only if the table name is not already aliased.
     *
     * Example:
     * ```sprout_sites => site```
     *
     * Note, this will also strip prefixes from tables, inflected or not.
     * Meaning table names that are already singular remain singular but lose
     * their prefix.
     *
     * Example:
     * ```sprout_file_join => file_join```
     *
     * @param bool $inflect
     * @return static
     * @since v0.18
     */
    public function inflect(bool $inflect = true)
    {
        $this->_inflect = $inflect;
        return $this;
    }


    /** @inheritdoc */
    public function build(): array
    {
        if (!$this->_inflect) {
            return parent::build();
        }

        $query = clone $this;
        $query->inflect(false);

        $query->_from = [];
        $query->_joins = [];

        if (count($this->_from) == 1) {
            $from = reset($this->_from);
            $from = $this->_inflect($from);
            $query->from($from);
        }

        foreach ($this->_joins as [$type, $table, $conditions, $combine]) {
            if (!isset($table[1])) {
                $table = reset($table);
                $table = $this->_inflect($table);
            }

            $query->_join($type, $table, $conditions, $combine);
        }

        return $query->build();
    }


    /**
     * Create an automatic alias from a field name.
     *
     * This strips the prefix and flips the pluralisation.
     *
     * Example:
     * ```sprout_sites => site```
     *
     * @param string $field
     * @return string[] [ field, alias ]
     */
    protected function _inflect(string $field): array
    {
        $inflector = $this->pdb->getInflector();
        $prefix = $this->pdb->getPrefix($field);

        if (strpos($field, $prefix) === 0) {
            $alias = substr($field, strlen($prefix));
            $alias = $inflector->singular($alias);
        }
        else {
            $alias = $inflector->singular($field);
        }

        return [$field, $alias];
    }
}
