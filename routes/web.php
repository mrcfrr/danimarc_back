<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NasController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/nas', [NasController::class, 'index']);
Route::get('/nas/download/{filename}', [NasController::class, 'download']);
Route::get('/nas/check-permissions', [NasController::class, 'checkPermissions']);
