<?php

namespace App\Models\Concerns;

use App\Support\PublicCatalog;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * For people and publishers: they get a public page while a super admin has not
 * hidden them and at least one of their books is public. Only checked records
 * (is_verified) are offered to search engines.
 */
trait AppearsInCatalog
{
    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function publiclyListed(Builder $query): void
    {
        $query->where($this->qualifyColumn('hidden_from_catalog'), false)
            ->whereHas('editions', fn (Builder $editions) => $editions->publiclyListed());
    }

    public function isPubliclyListed(): bool
    {
        return ! $this->hidden_from_catalog && $this->editions()->publiclyListed()->exists();
    }

    public function isIndexable(): bool
    {
        return $this->is_verified;
    }

    /**
     * The public page address. Uses the `catalogRoute` of the model.
     */
    public function publicUrl(): string
    {
        return route($this->catalogRoute(), [$this, PublicCatalog::slug($this->name)]);
    }

    abstract protected function catalogRoute(): string;
}
