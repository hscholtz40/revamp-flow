<?php

use App\Http\Controllers\Api\V1\NotificationsController;
use Illuminate\Support\Facades\Route;

/*
| Session-authenticated JSON endpoints for the in-app notification bell (CSRF via web middleware).
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/user/notifications', [NotificationsController::class, 'index'])->name('user.notifications.index');
    Route::patch('/user/notifications/{id}/read', [NotificationsController::class, 'markRead'])->name('user.notifications.read');
    Route::post('/user/notifications/read-all', [NotificationsController::class, 'markAllRead'])->name('user.notifications.read-all');
});
