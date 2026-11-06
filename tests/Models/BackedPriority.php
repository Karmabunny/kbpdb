<?php

namespace kbtests\Models;

/**
 * Integer-backed enum for model cast tests.
 */
enum BackedPriority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
}
