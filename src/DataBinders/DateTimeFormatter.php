<?php

namespace karmabunny\pdb\DataBinders;

use DateTimeInterface;
use karmabunny\kb\Configurable;
use karmabunny\kb\UpdateTrait;
use karmabunny\pdb\PdbDataFormatterInterface;
use karmabunny\pdb\PdbDataFormatterTrait;

/**
 * Format any date time objects.
 *
 * Register this like:
 *
 * ```
 * 'formatters' => [
 *    DateTimeInterface::class => DateTimeFormatter:class,
 *
 *    // OR, if you want to configure the format.
 *    DateTimeInterface:class => new DateTimeFormatter('Y-m-d'),
 *
 *    // Alternatively...
 *    DateTimeInterface::class => [ DateTimeFormatter::class => [
 *       'format' => 'Y-m-d',
 *    ],
 * ],
 *
 * @package karmabunny\pdb
 */
class DateTimeFormatter implements PdbDataFormatterInterface, Configurable
{
    use PdbDataFormatterTrait;
    use UpdateTrait;


    /** @var string */
    public $format;


    /**
     * @param string $format
     */
    public function __construct(string $format = 'Y-m-d H:i:s')
    {
        $this->format = $format;
    }


    /**
     *
     * @param DateTimeInterface $value
     * @return string
     */
    public function format($value): string
    {
        return $value->format($this->format);
    }
}

