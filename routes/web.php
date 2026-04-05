<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Ce projet est une API pure. Les routes web ne sont pas utilisées.
| Ce fichier est requis par Laravel.
*/

Route::get('/', fn () => response()->json([
    'name'    => 'AvocatiqueWebX API',
    'version' => '1.0.0',
    'docs'    => url('/api/auth/login'),
]));
