<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UiController;
use Illuminate\Support\Facades\Route;

// Sanctum accepts stateful website sessions and Bearer tokens from mobile clients.
Route::post('/auth/login', [AuthController::class, 'tokenLogin'])->middleware('throttle:10,1');
Route::post('/auth/logout', [AuthController::class, 'tokenLogout'])->middleware('auth:sanctum');
Route::post('/auth/password/forgot', [AuthController::class, 'sendPasswordResetOtp'])->middleware('throttle:5,1');
Route::post('/auth/password/reset', [AuthController::class, 'resetPasswordWithOtp'])->middleware('throttle:10,1');
Route::get('/rooms', [TicketController::class, 'rooms']);
Route::get('/rooms/{code}', [TicketController::class, 'room']);
Route::get('/categories', [CategoryController::class, 'options']);
Route::post('/tickets', [TicketController::class, 'store'])->middleware(['auth:sanctum', 'account.active']);

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::post('/account/password', [AuthController::class, 'changePassword']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::patch('/tickets/{ticket}', [TicketController::class, 'updateReport']);
    Route::get('/tickets/{ticket}/logs', [TicketController::class, 'logs']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::patch('/notifications/tickets/{ticketId}/read', [NotificationController::class, 'readTicket']);

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/reports/summary', [UiController::class, 'reportSummary']);
        Route::get('/team/filter', [UserController::class, 'filterTeam']);
        Route::get('/schedules/filter', [TicketController::class, 'filterSchedules']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/team', [UserController::class, 'createTeamAccount']);
        Route::post('/staff', [UserController::class, 'createStaff']);
        Route::put('/staff/{user}', [UserController::class, 'updateStaff']);
        Route::get('/staff', [UserController::class, 'staff']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::get('/buildings', [LocationController::class, 'buildings']);
        Route::post('/buildings', [LocationController::class, 'createBuilding']);
        Route::put('/buildings/{building}', [LocationController::class, 'updateBuilding']);
        Route::patch('/buildings/{building}', [LocationController::class, 'updateBuilding']);
        Route::delete('/buildings/{building}', [LocationController::class, 'deleteBuilding']);
        Route::post('/buildings/{building}/rooms', [LocationController::class, 'createRoom']);
        Route::put('/rooms/{room}', [LocationController::class, 'updateRoom']);
        Route::patch('/rooms/{room}', [LocationController::class, 'updateRoom']);
        Route::delete('/rooms/{room}', [LocationController::class, 'deleteRoom']);
        Route::get('/tickets/filter', [TicketController::class, 'filterIncidents']);
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign']);
        Route::post('/schedules/{schedule}/cancel', [TicketController::class, 'cancelSchedule']);
        Route::post('/tickets/{ticket}/close', [TicketController::class, 'close']);
        Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen']);
    });

    Route::prefix('staff')->middleware('role:admin,staff')->group(function () {
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::get('/schedules', [TicketController::class, 'staffSchedules']);
        Route::post('/schedules/{schedule}/acknowledge', [TicketController::class, 'acknowledgeSchedule']);
        Route::patch('/tickets/{ticket}/status', [TicketController::class, 'staffUpdate']);
        Route::post('/tickets/{ticket}/status', [TicketController::class, 'staffUpdate']);
    });
});
