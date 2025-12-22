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
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\InvoiceRecevivedController;
use App\Http\Controllers\salesController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\SaeController;




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
    Route::get('/retirada',[cashierController::class, 'prinret']);
    Route::post('/opencashier',[cashierController::class, 'opencash']);
    Route::post('/changewithdrawal',[cashierController::class, 'changewithdrawal']);
    Route::post('/addWithdrawal',[cashierController::class, 'addWithdrawal']);
    Route::post('/addIngress',[cashierController::class, 'addIngress']);
    Route::post('/addAdvance',[cashierController::class, 'addAdvance']);
    Route::post('/repliedSales',[cashierController::class, 'repliedSales']);
});

Route::prefix('/Products')->group(function(){//regisprice
    Route::get('/getReceived',[ProductsController::class, 'getReceived']);
    Route::get('/reportDepure',[ProductsController::class, 'reportDepure']);
    Route::post('/registerProducts',[ProductsController::class, 'productRegis']);
    Route::post('/registerPrices',[ProductsController::class, 'regisprice']);
    Route::post('/translate',[ProductsController::class, 'translateWarehouses']);
    Route::post('/dev',[ProductsController::class, 'refund']);
    Route::post('/abo',[ProductsController::class, 'abono']);
    Route::post('/inv',[ProductsController::class, 'invice']);
    Route::post('/invr',[ProductsController::class, 'invoiceReceived']);
    Route::post('/replacecode',[ProductsController::class, 'replacecode']);
    Route::post('/getdev',[ProductsController::class, 'getdev']);
    Route::post('/getinvoice',[ProductsController::class, 'getinvoice']);
    Route::post('/highProducts',[ProductsController::class, 'highProducts']);
    Route::post('/highPrices',[ProductsController::class, 'highPrices']);
    Route::post('/regispricefor',[ProductsController::class, 'regispricefor']);

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
    Route::post('/getPartition',[accessController::class,'getPartition']);

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
    Route::post('/editRefund',[RefundController::class, 'editRefund']);
    Route::post('/editEntry',[RefundController::class, 'editEntry']);
    Route::post('/editSeason',[RefundController::class, 'editSeason']);

});




Route::prefix('/reports')->group(function(){
    Route::get('/getCuts',[ReportController::class, 'getCuts']);
    Route::get('/getSales',[ReportController::class, 'getSales']);
    Route::get('/getWithdrawals',[ReportController::class, 'getWithdrawals']);
    Route::get('/getAdvances',[ReportController::class, 'getAdvances']);
    Route::post('/printCut',[ReportController::class, 'printCut']);
    Route::post('/getRefunds',[ReportController::class, 'getRefunds']);
    Route::post('/modifyWithdrawal',[ReportController::class, 'modifyWithdrawal']);
    Route::post('/modifyWithdrawalOficina',[ReportController::class, 'modifyWithdrawalOficina']);

    Route::post('/modifyAdvances',[ReportController::class, 'modifyAdvances']);
    Route::post('/printWitrawal',[ReportController::class, 'printWitrawal']);
    Route::post('/printAdvance',[ReportController::class, 'printAdvance']);
    Route::post('/addAdvances',[ReportController::class, 'addAdvances']);
    Route::post('/addWithdrawal',[ReportController::class, 'addWithdrawal']);
    Route::post('/getSalesPerMonth',[ReportController::class, 'getSalesPerMonth']);

});


Route::prefix('/invoice')->group(function(){
    Route::post('/addInvoice',[InvoicesController::class, 'invoice']);
    Route::post('/addTransfer',[InvoicesController::class, 'transfer']);
    Route::post('/endTransfer',[InvoicesController::class, 'endtransfer']);
    Route::post('/addEntry',[InvoicesController::class, 'entry']);
});

Route::prefix('/invoiceReceived')->group(function(){
    Route::post('/getIndex',[InvoiceRecevivedController::class, 'getIndex']);
    Route::post('/replyInvoices',[InvoiceRecevivedController::class, 'replyInvoices']);

});
Route::prefix('/billing')->group(function(){
    Route::post('/validateTck',[BillingController::class, 'validateTck']);
    Route::post('/getTckBilling',[BillingController::class, 'getTckBilling']);
    Route::post('/getServerFac',[SaeController::class, 'getServerFac']);
    Route::post('/getFolio',[SaeController::class, 'getFolio']);
    Route::post('/readRFC',[SaeController::class, 'readRFC']);
    Route::post('/crearFacturaInterna',[SaeController::class, 'crearFacturaInterna']);

});



Route::prefix('/sales')->group(function(){
    Route::post('/getCredits',[salesController::class, 'getCredits']);
    Route::post('/printWitrawal',[salesController::class, 'printWitrawal']);
    Route::post('/addWithdrawal',[salesController::class, 'addWithdrawal']);
    Route::post('/addSale',[salesController::class, 'addSale']);
    Route::post('/reprintSale',[salesController::class, 'reprintSale']);
    Route::post('/getWithdrawals',[salesController::class, 'getWithdrawals']);
    Route::post('/openCash',[salesController::class, 'openCash']);
    Route::post('/closeCash',[salesController::class, 'closeCash']);
    Route::post('/getTicket',[salesController::class, 'getTicket']);
});

Route::prefix('/sae')->group(function(){
    Route::get('/testFirebird',[SaeController::class, 'testFirebird']);
});



