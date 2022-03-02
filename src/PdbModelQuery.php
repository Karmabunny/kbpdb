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

    /**
     *
     * @param string $model
     * @throws InvalidArgumentException
     */
    public function __construct(string $model)
    {
        if (!is_subclass_of($model, PdbModelInterface::class)) {
            throw new InvalidArgumentException("{$model} must implement PdbModelInterface");
        }

        parent::__construct($model::getConnection());
        $this->from($model::getTableName());
        $this->as($model);
    }

}
