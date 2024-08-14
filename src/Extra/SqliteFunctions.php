<?php

namespace karmabunny\pdb\Extra;

/**
 *
 * @package karmabunny\pdb\Extra
 */
class SqliteFunctions
{
    /**
     *
     * @param mixed $date
     * @param mixed $format
     * @return string|false
     */
    public static function dateFormat($date, $format)
    {
        // TODO https://github.com/alphp/strftime
        return strftime($format, strtotime($date));
    }
}
