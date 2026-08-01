<?php

namespace App\Enums;

enum BillingType: string
{
    case Prepaid = 'prepaid';
    case Postpaid = 'postpaid';
}
