<?php

namespace App\Enums;

enum SenderIdStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Expired = 'expired';
}
