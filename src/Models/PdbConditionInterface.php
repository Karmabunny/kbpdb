<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use karmabunny\pdb\Exceptions\InvalidConditionException;
use karmabunny\pdb\Pdb;
use PDOException;

/**
 *
 * @package karmabunny\pdb
 */
interface PdbConditionInterface
{

    /**
     * Build an appropriate SQL clause for this condition.
     *
     * The values will be created as ? and added to the $values parameter to
     * permit one to bind the values later in an safe manner.
     *
     * @param Pdb $pdb
     * @param array $values
     * @return string
     * @throws PDOException
     * @throws InvalidConditionException
     */
    public function build(Pdb $pdb, array &$values): string;


    /**
     * Validate this condition.
     *
     * @return void
     * @throws InvalidConditionException
     */
    public function validate();


    /**
     *
     * @return string
     */
    public function getPreviewSql(): string;

}
