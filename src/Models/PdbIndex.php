<?php

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMElement;
use DOMNode;
use karmabunny\kb\Collection;

/**
 *
 * @package karmabunny\pdb
 */
class PdbIndex extends Collection
{

    const TYPE_INDEX = 'index';
    const TYPE_UNIQUE = 'unique';

    /** @var string */
    public $name;

    /** @var string */
    public $type = 'index';

    /** @var string[] */
    public $columns = [];

    /** @var bool */
    public $has_fk = false;


    public function toXML(DOMDocument $doc): DOMNode
    {
        $index = $doc->createElement('index');

        if ($this->type !== self::TYPE_INDEX) {
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
