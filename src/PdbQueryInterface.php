<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use InvalidArgumentException;

/**
 *
 * @package karmabunny\pdb
 */
interface PdbQueryInterface
{

    /**
     *
     * @return void
     * @throws InvalidConditionException
     */
    public function validate();


    /**
     *
     * @param bool $validate
     * @return array [ sql, params ]
     * @throws InvalidConditionException
     * @throws InvalidArgumentException
     */
    public function build(bool $validate = true): array;

}
