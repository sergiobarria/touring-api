<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\StoreTourData;
use App\Data\TourData;
use App\Data\TourListData;
use App\Data\UpdateTourData;
use App\Http\Controllers\Controller;
use App\Http\Requests\TourListRequest;
use App\Models\Tour;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

#[Group('Tours')]
class TourController extends Controller
{
    /** List tours. */
    public function index(TourListRequest $request)
    {
        $tours = QueryBuilder::for(Tour::class)
            ->with('media')
            ->allowedFields(Tour::ALLOWED_SELECT_FIELDS)
            ->allowedIncludes(Tour::ALLOWED_INCLUDES)
            ->allowedSorts(Tour::ALLOWED_SORTS)
            ->allowedFilters([
                'name', 'slug', 'difficulty',
                AllowedFilter::exact('duration_days'),
                AllowedFilter::exact('max_group_size'),
                AllowedFilter::scope('min_price'),
                AllowedFilter::scope('max_price'),
            ])
            ->where('is_active', true)
            ->defaultSort(['created_at', 'name'])
            ->paginate();

        return TourListData::collect($tours);
    }

    /** Get Tour by ID. */
    public function show(string $id)
    {
        $tour = Tour::with('dates')->findOrFail($id);

        return TourData::from($tour);
    }

    /** Create a tour
     * @throws Throwable
     */
    public function store(StoreTourData $data)
    {
        return DB::transaction(function () use ($data) {
            $tour = Tour::create($data->except('images')->toArray());

            $errors = [];
            if ($data->images) {
                foreach ($data->images as $index => $image) {
                    try {
                        $tour->addMedia($image)->toMediaCollection('tours', 'r2');
                    } catch (Exception $e) {
                        Log::error("Failed to upload image for Tour {$tour->id} : {$e->getMessage()}");
                        $errors["images.{$index}"] = "Failed to upload image: " . $e->getMessage();
                    }
                }
            }

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            $tour->refresh();

            return TourData::from($tour)->toResponse(request())->setStatusCode(201);
        });
    }

    /** Update tour
     * @throws Throwable
     */
    public function update(UpdateTourData $data, Tour $tour)
    {
        return DB::transaction(function () use ($data, $tour) {
            $tour->update($data->except('add_images', 'remove_image_ids')->toArray());

            $errors = [];

            if ($data->add_images) {
                foreach ($data->add_images as $index => $imageFile) {
                    try {
                        $tour->addMedia($imageFile)
                            ->toMediaCollection('images', 'r2');
                    } catch (Exception $e) {
                        Log::error("Failed to add image for Tour {$tour->id} (index {$index}): " . $e->getMessage());
                        $errors["add_images.{$index}"] = "Failed to add image: " . $e->getMessage();
                    }
                }
            }

            if ($data->remove_image_ids) {
                try {
                    $tour->media()->whereIn('id', $data->remove_image_ids)->delete();
                } catch (Exception $e) {
                    Log::error("Failed to delete images for Tour {$tour->id}: " . $e->getMessage());
                    throw ValidationException::withMessages(['remove_image_ids' => "Error deleting images: " . $e->getMessage()]);
                }
            }

            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }

            $tour->refresh();

            return TourData::from($tour)->toResponse(request());
        });
    }

    /** Delete tour */
    public function destroy(Tour $tour)
    {
        $tour->delete();

        return response()->noContent();
    }
}
