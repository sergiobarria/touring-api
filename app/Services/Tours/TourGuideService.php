<?php

namespace App\Services\Tours;

use App\Models\Tour;
use App\Services\Auditing\AuditService;
use Throwable;

final readonly class TourGuideService
{
    public function __construct(private AuditService $audits) {}

    /**
     * Replace a tour's supporting guides and record the relationship change.
     *
     * @param  list<string>  $guideIds
     *
     * @throws Throwable
     */
    public function sync(Tour $tour, array $guideIds): void
    {
        $oldGuideIds = $tour->guides()->pluck('users.id')->sort()->values()->all();
        $newGuideIds = collect($guideIds)->sort()->values()->all();

        if ($oldGuideIds === $newGuideIds) {
            return;
        }

        $tour->guides()->sync($newGuideIds);

        $this->audits->record(
            $tour,
            ['guide_ids' => $oldGuideIds],
            ['guide_ids' => $newGuideIds],
            'guide-assignments',
        );
    }
}
