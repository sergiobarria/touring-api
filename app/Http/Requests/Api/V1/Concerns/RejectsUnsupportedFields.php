<?php

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Validation\Validator;

trait RejectsUnsupportedFields
{
    /**
     * @param  list<string>  $supportedFields
     */
    protected function rejectUnsupportedFields(Validator $validator, array $supportedFields): void
    {
        foreach (array_diff(array_keys($this->requestBody()), $supportedFields) as $field) {
            $validator->errors()->add($field, "The {$field} field is not supported.");
        }
    }

    /** @return array<string, mixed> */
    private function requestBody(): array
    {
        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }
}
