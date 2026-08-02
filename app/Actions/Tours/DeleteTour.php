<?php

namespace App\Actions\Tours;

use App\Models\Tour;

final readonly class DeleteTour
{
    public function handle(string $tourId): void
    {
        Tour::query()->findOrFail($tourId)->delete();
    }
}
