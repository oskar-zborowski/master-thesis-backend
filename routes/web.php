<?php

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*
|---------------------------------------------------------------------------------------------------------------
| Endpoint do wpuszczania na stronę crawlerów
|---------------------------------------------------------------------------------------------------------------
*/

Route::get('/', function (Request $request) {
    throw new ApiException(
        DefaultErrorCode::PERMISSION_DENIED(false, false, true),
        null,
        __FUNCTION__,
        false
    );
})->name('crawler')->middleware('throttle:crawlerLimit');
