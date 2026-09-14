<?php

declare(strict_types=1);

use AIArmada\Authz\Http\Controllers\LeaveImpersonationController;
use Illuminate\Support\Facades\Route;

Route::post('authz/leave-impersonation', LeaveImpersonationController::class)
    ->middleware(['web', 'auth'])
    ->name('authz.leave-impersonation');
