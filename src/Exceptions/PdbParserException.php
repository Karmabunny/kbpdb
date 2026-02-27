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

    protected array $errors = [];


    public function withErrors(array $errors): static
    {
        $this->errors = $errors;
        $tables = array_keys($errors);
        $this->message = 'Parse error for tables:' . implode(', ', $tables);
        return $this;
    }


    public function getErrors(): array
    {
        return $this->errors;
    }
}
