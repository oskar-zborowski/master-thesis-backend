<?php

use App\Http\Controllers\Api\GitHubController;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------------------------------------------
| API Routes
|---------------------------------------------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
|---------------------------------------------------------------------------------------------------------------
| Endpointy do komunikacji z serwisem GitHub
|---------------------------------------------------------------------------------------------------------------
*/

Route::middleware('throttle:githubLimit')->group(function () {
    Route::post('/v1/github/pull', [GitHubController::class, 'pull'])->name('github-pull');
});
