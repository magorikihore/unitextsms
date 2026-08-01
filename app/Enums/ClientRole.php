<?php

namespace App\Enums;

enum ClientRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case CampaignOperator = 'campaign_operator';
    case Developer = 'developer';
    case ReportViewer = 'report_viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::CampaignOperator => 'Campaign Operator',
            self::Developer => 'Developer',
            self::ReportViewer => 'Report Viewer',
        };
    }

    public function canSend(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::CampaignOperator], true);
    }

    public function canManageTeam(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }
}
