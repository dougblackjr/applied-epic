<?php

declare(strict_types=1);

namespace Tns\Epic\Resources;

/**
 * Policies API — core policy and plan information for P&C and Benefits.
 *
 * Service: `/epic/policy/v2` — see spec/applied-epic-policy-v2-3.yaml
 */
final class Policies extends Resource
{
    protected function service(): string
    {
        return '/epic/policy/v2';
    }

    protected function collection(): string
    {
        return 'policies';
    }
}
