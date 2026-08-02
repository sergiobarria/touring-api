<?php

namespace App\OpenApi\Types;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;

final class StrictObjectType extends ObjectType
{
    private ?int $minimumProperties = null;

    public static function from(ObjectType $type, ?int $minimumProperties = null): self
    {
        $strictType = new self;
        $strictType->addProperties($type);
        $strictType->properties = $type->properties;
        $strictType->required = $type->required;
        $strictType->additionalProperties = $type->additionalProperties;
        $strictType->minimumProperties = $minimumProperties;

        return $strictType;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $schema = parent::toArray();
        $schema['additionalProperties'] = false;

        if ($this->minimumProperties !== null) {
            $schema['minProperties'] = $this->minimumProperties;
        }

        return $schema;
    }
}
