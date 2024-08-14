<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Exceptions;

use Exception;

/**
 *
 */
class PdbParserException extends Exception implements PdbExceptionInterface
{

    protected $errors = [];


    public function withErrors(array $errors)
    {
        $this->errors = $errors;
        $tables = array_keys($errors);
        $this->message = 'Parse error for tables:' . implode(', ', $tables);
    }


    public function getErrors(): array
    {
        return $this->errors;
    }
}
