<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of a Wikidata import (publishers or people), with its counts or error.
 */
#[Fillable(['type', 'status', 'countries', 'created_count', 'updated_count', 'error', 'triggered_by', 'started_at', 'finished_at'])]
class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    /** A run still "running" after this long is treated as crashed. */
    public const int STALE_AFTER_HOURS = 2;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'status' => ImportStatus::class,
            'countries' => 'array',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Whether an import of this type is running now (so another one should not start).
     */
    public static function isRunning(ImportType $type): bool
    {
        return static::query()
            ->where('type', $type)
            ->where('status', ImportStatus::Running)
            ->where('started_at', '>', now()->subHours(self::STALE_AFTER_HOURS))
            ->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
