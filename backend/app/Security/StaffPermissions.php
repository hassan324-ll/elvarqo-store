<?php

namespace App\Security;

class StaffPermissions
{
    public const ALL = ['catalogue.view', 'catalogue.manage', 'fulfilment.view', 'fulfilment.manage', 'customers.view', 'content.view', 'content.manage', 'finance.view', 'finance.refund', 'finance.confirm', 'staff.manage', 'integrations.manage', 'audit.view'];

    public const ROLES = [
        'owner' => self::ALL,
        'operations' => ['catalogue.view', 'catalogue.manage', 'fulfilment.view', 'fulfilment.manage', 'customers.view', 'content.view'],
        'support' => ['catalogue.view', 'fulfilment.view', 'customers.view'],
        'finance' => ['finance.view', 'finance.refund', 'customers.view', 'audit.view'],
    ];
}
