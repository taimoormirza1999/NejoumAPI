<?php

namespace App\Helpers\MaerskApi;

use App\Enums\InvoiceType;

class InvoiceHelper
{
    public static function formatType(string $type): InvoiceType {

        $invoice_type = new InvoiceType('OPEN');

        switch($type) {

            case 'OPEN':
                $invoice_type = new InvoiceType('OPEN');
                break;
            case 'PAID':
                $invoice_type = NEW InvoiceType('PAID');
                break;
            case 'OVERDUE':
                $invoice_type = new InvoiceType('OVERDUE');
                break;
            case 'CREDITS':
                $invoice_type = new InvoiceType('CREDITS');
                break;
            default:
            $invoice_type = new InvoiceType('OPEN');

        }

        return $invoice_type;
    }

}