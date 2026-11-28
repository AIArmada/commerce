<?php

declare(strict_types=1);

use AIArmada\Jnt\Features\Waybills\EntryPoints\Http\AwbController;
use Illuminate\Support\Facades\Route;

// The signed URL and cached owner/user authorization are both required.
Route::get('jnt/awb/{orderId}', [AwbController::class, 'show'])
    ->name('jnt.awb.show')
    ->middleware('web');
