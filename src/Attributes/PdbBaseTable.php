<?php

namespace karmabunny\pdb\Attributes;

use karmabunny\pdb\PdbModelInterface;

/**
 *
 * @package karmabunny\pdb
 */
abstract class PdbBaseTable implements PdbTableInterface
{

    /** @var string[] */
    protected $errors = [];


    /**
     *
     * @return class-string<PdbModelInterface>
     */
    public function getModelClass(): string
    {
        return $this->model->getName();
    }


    /**
     *
     * @return string
     */
    public function getTableName(): string
    {
        $class = $this->getModelClass();
        return $class::getTableName();
    }


    public function getErrors(): array
    {
        return $this->errors;
    }
}
