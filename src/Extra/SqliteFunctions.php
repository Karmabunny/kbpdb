<?php
declare(strict_types=1);
/**
 * @link      https://github.com/Karmabunny
 * @copyright Copyright (c) 2021 Karmabunny
 */

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
