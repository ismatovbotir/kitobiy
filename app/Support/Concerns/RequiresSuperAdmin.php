<?php

namespace App\Support\Concerns;

/**
 * For Livewire admin pages: every request (first render and each action) must
 * come from a super admin. The /admin routes check this too (can:super-admin);
 * this keeps the components safe on their own.
 */
trait RequiresSuperAdmin
{
    public function bootRequiresSuperAdmin(): void
    {
        $this->authorize('super-admin');
    }
}
