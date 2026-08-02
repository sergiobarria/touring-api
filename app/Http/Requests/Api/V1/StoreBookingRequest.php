<?php

namespace App\Http\Requests\Api\V1;

use App\DataTransferObjects\BookingData;
use App\Http\Requests\Api\V1\Concerns\RejectsUnsupportedFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreBookingRequest extends FormRequest
{
    use RejectsUnsupportedFields;

    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() === true;
    }

    protected function prepareForValidation(): void
    {
        $travelers = $this->input('travelers');
        if (is_array($travelers)) {
            $travelers = array_map(static function (mixed $traveler): mixed {
                if (! is_array($traveler)) {
                    return $traveler;
                }

                if (isset($traveler['full_name']) && is_string($traveler['full_name'])) {
                    $traveler['full_name'] = trim($traveler['full_name']);
                }
                if (isset($traveler['email']) && is_string($traveler['email'])) {
                    $traveler['email'] = strtolower(trim($traveler['email']));
                }

                return $traveler;
            }, $travelers);
        }

        $this->merge(['idempotency_key' => $this->header('Idempotency-Key'), 'travelers' => $travelers]);
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->rejectUnsupportedFields(
            $validator,
            ['tour_start_date_id', 'travelers', 'idempotency_key'],
        )];
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'tour_start_date_id' => ['required', 'ulid'],
            'travelers' => ['required', 'array', 'min:1', 'max:255'],
            'travelers.*' => ['required', 'array:full_name,email,phone'],
            'travelers.*.full_name' => ['required', 'string', 'max:255'],
            'travelers.*.email' => ['required', 'email:rfc', 'max:255'],
            'travelers.*.phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
        ];
    }

    public function toDto(): BookingData
    {
        return BookingData::from($this->validated());
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }
}
