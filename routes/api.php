<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZktecoController;
use App\Http\Controllers\accessController;
use App\Http\Controllers\BackupsController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\cashierController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ReceivedController;
use App\Http\Controllers\RequiredController;
use App\Http\Controllers\UserController;





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
    Route::post('/opencashier',[cashierController::class, 'opencash']);
    Route::post('/changewithdrawal',[cashierController::class, 'changewithdrawal']);
    Route::get('/retirada',[cashierController::class, 'prinret']);
});

Route::prefix('/Products')->group(function(){//regisprice
    Route::post('/registerProducts',[ProductsController::class, 'productRegis']);
    Route::post('/registerPrices',[ProductsController::class, 'regisprice']);
    Route::post('/translate',[ProductsController::class, 'translateWarehouses']);
    Route::post('/dev',[ProductsController::class, 'refund']);
    Route::post('/abo',[ProductsController::class, 'abono']);
    Route::post('/inv',[ProductsController::class, 'invice']);
    Route::post('/invr',[ProductsController::class, 'invoiceReceived']);
    Route::get('/reportDepure',[ProductsController::class, 'reportDepure']);
    Route::post('/replacecode',[ProductsController::class, 'replacecode']);
    Route::post('/getdev',[ProductsController::class, 'getdev']);
    Route::post('/getinvoice',[ProductsController::class, 'getinvoice']);

});
//regispricepub
Route::prefix('/Stores')->group(function(){
    Route::post('/regisproduct',[ProductsController::class, 'regisProstores']);
    Route::post('/regispricesproduct',[ProductsController::class, 'regispricesstores']);
    Route::post('/regispricespub',[ProductsController::class, 'regispricepub']);
});

Route::prefix('/Received')->group(function(){
    Route::post('/Received',[ReceivedController::class, 'invoice']);
});

Route::prefix('/Required')->group(function(){
    Route::post('/Required',[RequiredController::class, 'invoice_received']);
});

Route::prefix('/Resources')->group(function(){
    Route::post('/createClient',[accessController::class, 'createClient']);
    Route::post('/createClientSuc',[accessController::class, 'createClientSuc']);
    Route::get('/getsal',[accessController::class, 'getsal']);
    Route::post('/updsal',[accessController::class, 'updsal']);
    Route::get('/getclient',[accessController::class, 'getclient']);
    Route::get('/getdev',[accessController::class, 'getdev']);
    Route::post('/upddev',[accessController::class, 'upddev']);
    Route::get('/gettras',[accessController::class, 'gettras']);
    Route::post('/returndev',[accessController::class, 'returndev']);
    Route::post('/returnFac',[accessController::class, 'returnFac']);
    Route::post('/createAbono',[accessController::class, 'createAbono']);
    Route::post('/createSalidas',[accessController::class, 'createSalidas']);
    Route::post('/createEntradas',[accessController::class, 'createEntradas']);
});

Route::prefix('/Users')->group(function(){
    Route::post('/create',[UserController::class, 'createUser']);
    Route::post('/reply',[UserController::class, 'replyUser']);
    Route::post('/insuc',[UserController::class, 'insuc']);
});
