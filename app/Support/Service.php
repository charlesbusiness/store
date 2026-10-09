<?php

namespace App\Support;

use App\Support\Concerns\FormatsValues;
use App\Support\Concerns\ResolvesUnits;

/**
 * Global parent for application services.
 *
 * Extending this gives a service money/qty formatting and unit arithmetic for
 * free, and marks it as a service rather than a model or controller. Any class
 * that is not a service can `use` either trait on its own instead.
 */
abstract class Service
{
    use FormatsValues;
    use ResolvesUnits;
}
