<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Pending = 'pending';
}
