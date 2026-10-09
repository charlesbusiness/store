<?php

namespace App\Http\Controllers;

use App\Enums\RecordType;

class PurchaseController extends RecordController
{
    protected function type(): RecordType
    {
        return RecordType::Purchase;
    }
}
