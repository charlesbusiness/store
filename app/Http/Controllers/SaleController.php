<?php

namespace App\Http\Controllers;

use App\Enums\RecordType;

class SaleController extends RecordController
{
    protected function type(): RecordType
    {
        return RecordType::Sale;
    }
}
