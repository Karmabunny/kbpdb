<?php
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2022 Karmabunny
 */

namespace karmabunny\pdb;

use DOMDocument;
use DOMNode;

/**
 * An interface for writing db_struct components in XML.
 *
 * @package karmabunny\pdb
 */
interface PdbStructWriterInterface
{

    /**
     * Create an XML node for this object.
     *
     * @param DOMDocument $doc
     * @return DOMNode
     */
    public function toXml(DOMDocument $doc): DOMNode;
}
