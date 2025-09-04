<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Scramble Docs API routes registration
Scramble::registerUiRoute(path: 'docs/v2', api: 'v2');
Scramble::registerJsonSpecificationRoute(path: 'docs/v2.json', api: 'v2');
