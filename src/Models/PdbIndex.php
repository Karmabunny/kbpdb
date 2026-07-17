<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMNode;
use karmabunny\kb\Collection;
use karmabunny\pdb\PdbStructWriterInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbIndex extends Collection implements PdbStructWriterInterface
{

    const TYPE_INDEX = 'index';
    const TYPE_UNIQUE = 'unique';

    public string $name;

    public string $type = self::TYPE_INDEX;

    /** @var string[] */
    public array $columns = [];


    /** @inheritdoc */
    public function toXml(DOMDocument $doc): DOMNode
    {
        $index = $doc->createElement('index');

        if ($this->type !== 'index') {
            $index->setAttribute('type', $this->type);
        }

        foreach ($this->columns as $column) {
            $node = $doc->createElement('col');
            $node->setAttribute('name', $column);
            $index->appendChild($node);
        }

        return $index;
    }

}
