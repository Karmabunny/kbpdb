<?php

namespace karmabunny\pdb\Models;

use DOMDocument;
use DOMNode;
use karmabunny\kb\Collection;
use karmabunny\pdb\PdbStructWriterInterface;

/**
 *
 * @package karmabunny\pdb
 */
class PdbView extends Collection implements PdbStructWriterInterface
{
    /** @var string */
    public $name;

    /** @var string */
    public $sql;

    /** @var string */
    public $checksum;


    /** @inheritdoc */
    public function toXml(DOMDocument $doc): DOMNode
    {
        $view = $doc->createElement('view');
        $view->setAttribute('name', $this->name);

        $sql = $doc->createTextNode($this->sql);
        $view->appendChild($sql);

        return $view;
    }
}
