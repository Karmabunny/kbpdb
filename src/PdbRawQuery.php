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
class PdbRawQuery implements PdbQueryInterface
{

    /**
     * @var string
     */
    public $sql;

    /**
     * @var array
     */
    public $params;


    public function __construct(string $sql, array $params = [])
    {
        $this->sql = $sql;
        $this->params = $params;
    }


    /**
     *
     * @return void
     * @throws InvalidConditionException
     */
    public function validate()
    {
        // no-op.
    }


    /**
     *
     * @param bool $validate
     * @return array [ sql, params ]
     */
    public function build(bool $validate = true): array
    {
        return [ $this->sql, $this->params ];
    }

}
