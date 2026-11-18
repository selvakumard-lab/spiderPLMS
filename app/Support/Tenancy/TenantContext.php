<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * Holds the tenant for the current request / job. Registered as a scoped
 * singleton so it is reset between requests (Octane safe) and queued jobs.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($tenant?->id);
        }
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Run a callback inside another tenant's context, then restore the previous one.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }
}
