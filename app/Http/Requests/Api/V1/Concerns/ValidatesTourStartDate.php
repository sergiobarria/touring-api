<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\DataTransferObjects\TourStartDateData;
use App\Models\Tour;
use App\Models\TourStartDate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

trait ValidatesTourStartDate
{
    /** @return array<string, mixed> */
    private function body(): array
    {
        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }

    private function rejectUnsupportedFields(Validator $validator, bool $requireField = false): void
    {
        $body = $this->body();

        foreach (array_diff(array_keys($body), TourStartDateData::WRITABLE_FIELDS) as $field) {
            $validator->errors()->add($field, "The {$field} field is not supported.");
        }

        if ($requireField && array_intersect(array_keys($body), TourStartDateData::WRITABLE_FIELDS) === []) {
            $validator->errors()->add('request', 'At least one writable tour start-date field is required.');
        }
    }

    private function validateStartDateInvariants(Validator $validator, ?TourStartDate $ignored = null): void
    {
        if ($validator->errors()->hasAny(['start_datetime_utc', 'available_spots'])) {
            return;
        }

        $tour = $this->route('tour');

        if (! $tour instanceof Tour) {
            return;
        }

        if ($this->has('available_spots') && $this->integer('available_spots') > $tour->max_group_size) {
            $validator->errors()->add(
                'available_spots',
                'The available spots field must not be greater than the tour maximum group size.',
            );
        }

        if (! $this->has('start_datetime_utc')) {
            return;
        }

        $instant = CarbonImmutable::parse($this->input('start_datetime_utc'))->utc();
        $duplicate = $tour->startDates()
            ->withTrashed()
            ->where('start_datetime_utc', $instant)
            ->when($ignored, fn ($query) => $query->whereKeyNot($ignored->getKey()))
            ->exists();

        if ($duplicate) {
            $validator->errors()->add(
                'start_datetime_utc',
                'The tour already has a start date at this instant.',
            );
        }
    }
}
