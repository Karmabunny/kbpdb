<?php
declare(strict_types=1);
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
 *
 * @template T of PdbModelInterface
 */
class PdbModelQuery extends PdbQuery
{

    /** @var class-string<T> */
    protected string $_model;

    /** @var bool|string */
    public bool|string $_inflect = false;

    /** @var bool|null */
    public ?bool $_deleted = null;


    /**
     *
     * @param class-string<T> $model
     * @param array<string, mixed> $config
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
     * @return $this
     * @since v0.18
     */
    public function inflect(bool $inflect = true): static
    {
        $this->_inflect = $inflect;
        return $this;
    }


    /**
     *
     * @param bool|null $deleted
     * @return $this
     */
    public function deleted(?bool $deleted): static
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
