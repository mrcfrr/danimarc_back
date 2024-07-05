<?php

use App\Http\Controllers\DocumentsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/documents', [DocumentsController::class, 'index']);
Route::get('/documents/download/{filename}', [DocumentsController::class, 'download']);
Route::get('/documents/{path}', [DocumentsController::class, 'getFolderStructure'])->where('path', '.*');
Route::get('/generate-qrcode/{path?}', [DocumentsController::class, 'generateQrCode'])->where('path', '.*');

Route::get('/check-permissions', [DocumentsController::class, 'checkPermissions']);
