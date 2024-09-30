<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContractController;

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

Route::group(['middleware' => 'auth:api'], function () {
    Route::prefix("config")->controller(ConfigController::class)->group(function () {
        Route::get("/initial", "initial");
    });

    Route::prefix("contracts")->controller(ContractController::class)->group(function () {
        Route::get("/", "all");
        Route::get("/{contract}/for-signing", "getForSigning");
        Route::post("/upload", "upload");
        Route::post("/{contract}/sign", "sign");
        Route::get("/{contract}/download", "download");
        Route::get("/{contract}/download/signed", "downloadSigned");
        Route::get("/{contract}/download/signature", action: "downloadSignature");
        Route::post("/{contract}", "reject");
    });
});

Route::controller(AuthController::class)->group(function () {
    Route::post("register", "register");
    Route::post("login", "login");
    Route::post("logout", "logout");
});
