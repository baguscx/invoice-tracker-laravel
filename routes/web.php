<?php

use App\Http\Controllers\AdminActivityController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceTransitionController;
use App\Http\Controllers\PublicTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicTrackingController::class, 'index'])->name('tracking.index');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware('role:ADMIN,RESEPSIONIS')->name('invoices.store');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])
        ->middleware('role:ADMIN,RESEPSIONIS')->name('invoices.update');
    Route::patch('/invoices/{invoice}/work', [InvoiceController::class, 'updateWork'])->name('invoices.work');
    Route::patch('/invoices/{invoice}/transition', [InvoiceTransitionController::class, 'update'])->name('invoices.transition');
    Route::get('/invoices/{invoice}/history', [InvoiceController::class, 'history'])->name('invoices.history');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])
        ->middleware('role:ADMIN')->name('invoices.destroy');

    Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/activity', [AdminActivityController::class, 'recent'])->name('activity.index');
        Route::get('/cancelled-invoices', [AdminActivityController::class, 'cancelled'])->name('cancelled.index');
    });
});
