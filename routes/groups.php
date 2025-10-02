<?php

use App\Http\Controllers\GroupsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/groups', [GroupsController::class, 'index'])->middleware('module.permission:groups,list')->name('groups.index');
    Route::get('/groups/create', [GroupsController::class, 'create'])->middleware('module.permission:groups,create')->name('groups.create');
    Route::post('/groups', [GroupsController::class, 'store'])->middleware('module.permission:groups,create')->name('groups.store');
    Route::get('/groups/{group}/edit', [GroupsController::class, 'edit'])->middleware('module.permission:groups,edit')->name('groups.edit');
    Route::put('/groups/{group}', [GroupsController::class, 'update'])->middleware('module.permission:groups,edit')->name('groups.update');
    Route::put('/groups/{group}/permissions', [GroupsController::class, 'updatePermissions'])->middleware('module.permission:groups,edit')->name('groups.permissions');
});


