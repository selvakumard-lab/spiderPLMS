<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — React single-page application
|--------------------------------------------------------------------------
| Laravel serves one Blade shell (resources/views/app.blade.php) for every
| browser URL; React Router renders the page (landing page on "/").
| JSON API routes live in routes/api.php under /api/v1.
*/

Route::view('/', 'app')->name('home');

Route::view('/{path}', 'app')
    ->where('path', '^(?!api(/|$)|up$|storage/|build/).*$')
    ->name('spa');
