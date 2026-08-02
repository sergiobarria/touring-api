<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Validation\Validator;

trait ValidatesTourGuides
{
    private function validateGuideRoles(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['lead_guide_id', 'guide_ids', 'guide_ids.*'])) {
            return;
        }

        $tour = $this->route('tour');
        $tour = $tour instanceof Tour ? $tour : Tour::query()->find($tour);
        $leadGuideId = $this->input('lead_guide_id', $tour?->lead_guide_id);
        $guideIds = $this->has('guide_ids')
            ? $this->input('guide_ids', [])
            : $tour?->guides()->pluck('users.id')->all() ?? [];

        if (in_array($leadGuideId, $guideIds, strict: true)) {
            $validator->errors()->add('guide_ids', 'The lead guide cannot also be a supporting guide.');
        }

        $candidateIds = collect([$leadGuideId, ...$guideIds])
            ->filter(fn (mixed $id): bool => is_string($id))
            ->unique()
            ->values();
        $users = User::query()
            ->with('roles:id,name')
            ->whereKey($candidateIds)
            ->get()
            ->keyBy('id');

        if (is_string($leadGuideId)
            && ! $users->get($leadGuideId)?->hasExactRoles(UserRole::LEAD_GUIDE->value)) {
            $validator->errors()->add('lead_guide_id', 'The selected user must have the lead-guide role.');
        }

        foreach ($guideIds as $index => $guideId) {
            if (is_string($guideId)
                && ! $users->get($guideId)?->hasExactRoles(UserRole::GUIDE->value)) {
                $validator->errors()->add("guide_ids.{$index}", 'The selected user must have the guide role.');
            }
        }
    }
}
