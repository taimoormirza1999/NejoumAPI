<?php

namespace App\Enums;

use Illuminate\Validation\Rules\Enum;

class InvoiceType extends Enum
{
    const OPEN = 'OPEN';
    const PAID = 'PAID';
    const OVERDUE = 'OVERDUE';
    const CREDITS = 'CREDITS';

}