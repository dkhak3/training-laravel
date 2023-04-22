<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomAuthController;
use App\Http\Controllers\ViewUserController;
use App\Http\Controllers\ListUserController;
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

Route::get('dashboard', [CustomAuthController::class,'dashboard']);
Route::get('login',[CustomAuthController::class,'index'])->name('login');
Route::post('custom-login',[CustomAuthController::class,'customLogin'])->name('login.custom');
Route::get('registration',[CustomAuthController::class,'registration'])->name('register-user');
Route::post('custom-registration',[CustomAuthController::class,'customRegistration'])->name('register.custom');
Route::get('signout',[CustomAuthController::class,'signOut'])->name('signout');
Route::get('viewUser/{id}',[ViewUserController::class,'viewUser'])->name('viewUser');
Route::get('listusers', [ListUserController::class, 'ListUser'])->name('listuser');
Route::get('listusers/{page}', [ListUserController::class, 'ListUserByPage'])->name('listusers');
