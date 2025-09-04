<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tour;

class TourController extends Controller
{
    public function index()
    {
        $tours = Tour::all();

        return response()->json($tours);
    }

    public function show(string $id)
    {
        $tour = Tour::findOrFail($id);

        return response()->json($tour);
    }

    public function store()
    {
        return response()->json('Save tour');
    }

    public function update()
    {
        return response()->json('Update tour');
    }

    public function destroy()
    {
        return response()->json('Delete tour');
    }
}
