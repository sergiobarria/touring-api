<?php

namespace App\Http\Services;

use App\Http\Requests\ToursListRequest;
use App\Models\Tour;
use Illuminate\Pagination\LengthAwarePaginator;

class TourService
{
    public function getTourList(ToursListRequest $request): LengthAwarePaginator
    {
        return Tour::where('is_public', true)
            ->when($request->priceFrom, fn($query) => $query->where('price', '>=', $request->priceFrom * 100))
            ->when($request->priceTo, fn($query) => $query->where('price', '<=', $request->priceTo * 100))
            ->when($request->sortBy && $request->sortOrder, fn($query) => $query->orderBy($request->sortBy, $request->sortOrder))
            ->orderBy('created_at')
            ->paginate(10);
    }
}
