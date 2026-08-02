<?php

namespace App\OpenApi;

use App\Http\Requests\Api\V1\StoreTourRequest;
use App\Http\Requests\Api\V1\UpdateTourRequest;
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

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                if ($operation->operationId === 'v1.tours.update') {
                    $operation->requestBodyObject?->required();
                }
            }
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
