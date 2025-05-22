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
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ModificationController;
use App\Http\Controllers\TransferBWController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OutputsController;
use App\Http\Controllers\RefundController;









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
    Route::get('/getReceived',[ProductsController::class, 'getReceived']);
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

Route::prefix('/Modification')->group(function(){
    Route::post('/deleteProduct',[ModificationController::class, 'deleteProduct']);
    Route::post('/changeDelivered',[ModificationController::class, 'changeDelivered']);
    Route::post('/changeReceived',[ModificationController::class, 'changeReceived']);
});

Route::prefix('/Transfer')->group(function(){
    Route::post('/Transfer',[TransferController::class, 'transfer']);
    Route::post('/TransferRec',[TransferController::class, 'transferRec']);

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
    Route::post('/returnTra',[accessController::class, 'returnTra']);
    Route::post('/createAbono',[accessController::class, 'createAbono']);
    Route::post('/createSalidas',[accessController::class, 'createSalidas']);
    Route::post('/createEntradas',[accessController::class, 'createEntradas']);
    Route::get('/Invoices',[accessController::class, 'Invoices']);
    Route::post('/getInvoices',[accessController::class, 'getInvoices']);
    Route::post('/getEntries',[accessController::class, 'getEntries']);
    Route::get('/Entries',[accessController::class, 'Entries']);
    Route::post('/getInvoiceBudget',[accessController::class,'getInvoiceBudget']);
    Route::post('/getCommand',[accessController::class,'getCommand']);
    Route::post('/createBudget',[accessController::class,'createBudget']);

});

Route::prefix('/Users')->group(function(){
    Route::post('/create',[UserController::class, 'createUser']);
    Route::post('/reply',[UserController::class, 'replyUser']);
    Route::post('/insuc',[UserController::class, 'insuc']);
});

Route::prefix('/TransferBW')->group(function(){
    Route::post('/addTransfer',[TransferBWController::class, 'addTransfer']);
    Route::post('/endTransfer',[TransferBWController::class, 'endTransfer']);
});

Route::prefix('/outsInternal')->group(function(){
    Route::post('/addOuts',[OutputsController::class, 'addOutputs']);
    Route::post('/endOuts',[OutputsController::class, 'endOutput']);
});

Route::prefix('/refunds')->group(function(){
    Route::post('/addRefund',[RefundController::class, 'addRefund']);
    Route::post('/genAbono',[RefundController::class, 'genAbono']);
    Route::post('/genAbonoTras',[RefundController::class, 'genAbonoTras']);
    Route::post('/genEntry',[RefundController::class, 'genEntry']);



});




Route::prefix('/reports')->group(function(){
    Route::get('/getCuts',[ReportController::class, 'getCuts']);
    Route::get('/getWithdrawals',[ReportController::class, 'getWithdrawals']);
    Route::get('/getAdvances',[ReportController::class, 'getAdvances']);
    Route::post('/printCut',[ReportController::class, 'printCut']);
    Route::post('/modifyWithdrawal',[ReportController::class, 'modifyWithdrawal']);
    Route::post('/modifyAdvances',[ReportController::class, 'modifyAdvances']);
    Route::post('/printWitrawal',[ReportController::class, 'printWitrawal']);
    Route::post('/printAdvance',[ReportController::class, 'printAdvance']);
    Route::post('/addAdvances',[ReportController::class, 'addAdvances']);
    Route::post('/addWithdrawal',[ReportController::class, 'addWithdrawal']);
});
