<?php
declare(strict_types=1);

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
    public string $name;

    public string $sql;

    public string $checksum;


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
