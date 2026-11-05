<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state. Tests set the reportable with `for()` or
     * the reportable_type and reportable_id attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reason' => ReportReason::Spam,
            'details' => null,
        ];
    }
}
