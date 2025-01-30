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

    /** @var class-string<PdbModelInterface> */
    protected $_model;

    /** @var bool|string */
    public $_inflect = false;

    /** @var bool|null */
    public $_deleted = null;


    /**
     *
     * @param class-string<PdbModelInterface> $model
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

        $this->_model = $model;
    }


    /**
     * Enable table name inflection.
     *
     * This will automatically alias table names for FROM statements.
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


    /**
     *
     * @param bool|null $deleted
     * @return static
     */
    public function deleted(?bool $deleted)
    {
        $this->_deleted = $deleted;
        return $this;
    }


    /** @inheritdoc */
    protected function _beforeBuild(PdbQuery &$query)
    {
        parent::_beforeBuild($query);

        if (
            $this->_inflect
            and !empty($this->_from)
            and empty($this->_from[1])
        ) {
            $alias = $this->_from[0];

            $prefix = $this->pdb->getPrefix($alias);
            if (strpos($alias, $prefix) === 0) {
                $alias = substr($alias, strlen($prefix));
            }

            $inflector = $this->pdb->getInflector();
            $alias = $inflector->singular($alias);
            Pdb::validateAlias($alias);

            $query->alias($alias);
        }

        if ($this->_deleted !== null) {
            if ($this->_deleted) {
                $query->andWhere([
                    'NOT' => ['date_deleted' => null],
                ]);
            }
            else {
                $query->andWhere([
                    ['date_deleted' => null],
                ]);
            }
        }
    }
}
