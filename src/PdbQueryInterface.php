<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb;

use karmabunny\pdb\Exceptions\InvalidConditionException;

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
     */
    public function build(bool $validate = true): array;

}
