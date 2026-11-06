<?php

namespace kbtests\Models;

/**
 * Unit enum for model cast tests.
 *
 * Cases are stored and loaded by name.
 */
enum UnitDirection
{
    case North;
    case East;
    case South;
    case West;
}
