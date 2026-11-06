<?php

namespace kbtests\Models;

/**
 * String-backed enum for model cast tests.
 */
enum BackedStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
