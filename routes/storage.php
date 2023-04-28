<?php

use App\Http\Controllers\NerestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NerRest Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('{path?}', [NerestController::class, 'view'])
    ->where('path', '(.*)');
Route::post('{path?}', [NerestController::class, 'store'])
    ->where('path', '(.*)');
Route::put('{path?}', [NerestController::class, 'update'])
    ->where('path', '(.*)');
Route::delete('{path?}', [NerestController::class, 'destroy'])
    ->where('path', '(.*)');

