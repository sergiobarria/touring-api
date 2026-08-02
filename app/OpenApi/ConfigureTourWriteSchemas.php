<?php

namespace App\OpenApi;

use App\Http\Requests\Api\V1\StoreTourImagesRequest;
use App\Http\Requests\Api\V1\StoreTourRequest;
use App\Http\Requests\Api\V1\StoreTourStartDateRequest;
use App\Http\Requests\Api\V1\UpdateTourRequest;
use App\Http\Requests\Api\V1\UpdateTourStartDateRequest;
use App\Http\Resources\TopTourResource;
use App\OpenApi\Types\StrictObjectType;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;

final class ConfigureTourWriteSchemas implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $this->makeRequestSchemaStrict($document, StoreTourRequest::class);
        $this->makeRequestSchemaStrict($document, UpdateTourRequest::class, minimumProperties: 1);
        $this->makeRequestSchemaStrict($document, StoreTourStartDateRequest::class);
        $this->makeRequestSchemaStrict($document, UpdateTourStartDateRequest::class, minimumProperties: 1);
        $this->makeRequestSchemaStrict($document, StoreTourImagesRequest::class);
        $this->requireTopTourAttributes($document);

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                if (in_array($operation->operationId, [
                    'v1.tours.update',
                    'v1.tours.start-dates.update',
                ], strict: true)) {
                    $operation->requestBodyObject?->required();
                }
            }
        }
    }

    private function requireTopTourAttributes(OpenApi $document): void
    {
        $schema = $document->components->schemas[class_basename(TopTourResource::class)] ?? null;

        if (! $schema?->type instanceof ObjectType) {
            return;
        }

        $schema->type->addRequired(['attributes']);
        $attributes = $schema->type->getProperty('attributes');

        if ($attributes instanceof ObjectType) {
            $attributes->setRequired([
                'name',
                'price',
                'rating_avg',
                'summary',
                'difficulty',
                'images',
            ]);
        }
    }

    private function makeRequestSchemaStrict(
        OpenApi $document,
        string $schemaName,
        ?int $minimumProperties = null,
    ): void {
        $schema = $document->components->schemas[$schemaName] ?? null;

        if ($schema?->type instanceof ObjectType) {
            $schema->type = StrictObjectType::from($schema->type, $minimumProperties);
        }
    }
}
