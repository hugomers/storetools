<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZktecoController;
use App\Http\Controllers\accessController;
use App\Http\Controllers\BackupsController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\cashierController;
use App\Http\Controllers\ProductsController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('/assist',[ZktecoController::class,'replyAssist']);
Route::get('/openingbox',[accessController::class,'OpeningBox']);
Route::get('/Withdrawals',[accessController::class,'Withdrawals']);
Route::get('/regeneration',[accessController::class,'regeneration']);
Route::get('/Backupsnigth',[BackupsController::class,'Backupsnigth']);
Route::get('/Backups',[BackupsController::class,'Backups']);

Route::prefix('/State')->group(function(){
    Route::post('/sale',[StateController::class, 'sales']);
    Route::post('/bills',[StateController::class, 'bills']);
});


Route::prefix('/Cashier')->group(function(){
    Route::post('/opencashier',[cashierController::class, 'opencashier']);
    Route::get('/retirada',[cashierController::class, 'prinret']);
});

Route::prefix('/Products')->group(function(){//regisprice
    Route::post('/registerProducts',[ProductsController::class, 'productRegis']);
    Route::post('/registerPrices',[ProductsController::class, 'regisprice']);
    Route::post('/translate',[ProductsController::class, 'translateWarehouses']);

});
//regispricepub
Route::prefix('/Stores')->group(function(){
    Route::post('/regisproduct',[ProductsController::class, 'regisProstores']);
    Route::post('/regispricesproduct',[ProductsController::class, 'regispricesstores']);
    Route::post('/regispricespub',[ProductsController::class, 'regispricepub']);
});

