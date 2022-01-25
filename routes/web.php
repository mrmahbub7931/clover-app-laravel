<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TokenController;

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
Route::get('/', [TokenController::class, 'index']);

Route::get('/orders', [TokenController::class, 'get_order']);
Route::get('/export-inventory', [TokenController::class, 'post_inventory']);
Route::get('/update-inventory', [TokenController::class, 'update_inventory']);
Route::get('/delete-inventory', [TokenController::class, 'deleteItem']);
Route::get('/customer', [TokenController::class, 'getSignleCustomer']);