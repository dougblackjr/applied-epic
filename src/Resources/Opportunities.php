<?php

declare(strict_types=1);

namespace Tns\Epic\Resources;

/**
 * Opportunities API — visibility into sales opportunities.
 *
 * Service: `/epic/opportunity/v1` — see spec/applied-epic-opportunity-v1.yml
 */
final class Opportunities extends Resource
{
    protected function service(): string
    {
        return '/epic/opportunity/v1';
    }

    protected function collection(): string
    {
        return 'opportunities';
    }
}
