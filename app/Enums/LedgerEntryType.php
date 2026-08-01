<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Adjustment = 'adjustment';
    case Refund = 'refund';
}
