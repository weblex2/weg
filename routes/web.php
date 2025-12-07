<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\HomeAssistantController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\HomeAssistantWebServiceController;
use App\Http\Controllers\ScheduledJobController;


Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::controller(EmailController::class)->group(function () {
    Route::get('/import', 'importEmailsFromImap')->middleware(['auth'])->name('email.import');
    Route::get('/emails', 'showMails')->middleware(['auth'])->name('email.show');
});

Route::prefix('homeassistant')->group(function () {
    Route::post('/dashboard/save', [HomeAssistantController::class, 'saveDashboard']);
    Route::get('/dashboard/load', [HomeAssistantController::class, 'loadDashboard']);
    Route::get('/dashboard', [HomeAssistantController::class, 'dashboard']);
    Route::get('switches', [App\Http\Controllers\HomeAssistantController::class, 'listSwitches']);
    Route::get('state/{entityId}', [App\Http\Controllers\HomeAssistantController::class, 'getState']);
    Route::post('turn-on/{entityId}', [App\Http\Controllers\HomeAssistantController::class, 'turnOn']);
    Route::post('turn-off/{entityId}', [App\Http\Controllers\HomeAssistantController::class, 'turnOff']);
    Route::post('toggle/{entityId}', [App\Http\Controllers\HomeAssistantController::class, 'toggle']);

    // DUPLIKAT - diese beiden Zeilen kannst du löschen:
    // Route::post('/homeassistant/toggle/{entityId}', [HomeAssistantController::class, 'toggle']);
    // Route::get('/homeassistant/state/{entityId}', [HomeAssistantController::class, 'getState']);

    // Light Controls
    Route::post('light/brightness/{entityId}', [HomeAssistantController::class, 'setBrightness']);
    Route::post('light/color-temp/{entityId}', [HomeAssistantController::class, 'setColorTemp']);
    Route::post('light/color/{entityId}', [HomeAssistantController::class, 'setColor']);

    // Webservices
    Route::get('/ws/devices', [HomeAssistantWebServiceController::class, 'listDevices']);
    Route::get('/ws/entities', [HomeAssistantWebServiceController::class, 'listEntities']);
    Route::get('/ws/monitor', function () {
        return view('homeassistant.monitor');
    })->name('monitor');
    Route::post('/ws/service', [HomeAssistantWebServiceController::class, 'callService']);
});

Route::middleware(['auth'])->group(function () {
    Route::post('/deploy', [DeployController::class, 'deploy'])->name('deploy');
    Route::get('/deploy/status', [DeployController::class, 'status'])->name('deploy.status');
    Route::get('/deploy-page', function () {
    return view('homeassistant.deploy');
    })->name('deploy.page');
});


Route::middleware(['auth'])->prefix('scheduled-jobs')->group(function () {
    Route::get('/', [ScheduledJobController::class, 'index'])->name('scheduled-jobs.index');
    Route::get('/create', [ScheduledJobController::class, 'create'])->name('scheduled-jobs.create');
    Route::post('/', [ScheduledJobController::class, 'store'])->name('scheduled-jobs.store');
    Route::delete('/{scheduledJob}', [ScheduledJobController::class, 'destroy'])->name('scheduled-jobs.destroy');
    Route::post('/{scheduledJob}/toggle', [ScheduledJobController::class, 'toggle'])->name('scheduled-jobs.toggle');
    //Route::resource('scheduled-jobs', ScheduledJobController::class);
    Route::patch('scheduled-jobs/{scheduledJob}/toggle', [ScheduledJobController::class, 'toggle'])->name('scheduled-jobs.toggle2');
});

Route::get('/homeassistant', [HomeAssistantController::class, 'dashboard'])
    ->name('homeassistant.dashboard');

// Monitor
Route::get('/homeassistant/monitor', [HomeAssistantController::class, 'monitor'])->name('homeassistant.monitor');

Route::get('/homeassistant/queue/worker-status', [ScheduledJobController::class, 'workerStatus']);
