<?php

declare(strict_types=1);

namespace Tns\Epic\Resources;

/**
 * Accounts API — top-level accounts (clients, brokers, companies, employees…).
 *
 * Service: `/epic/account/v1` — see spec/applied-epic-account-v1.yml
 */
final class Accounts extends Resource
{
    protected function service(): string
    {
        return '/epic/account/v1';
    }

    protected function collection(): string
    {
        return 'accounts';
    }
}
