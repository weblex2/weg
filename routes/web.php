<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\HomeAssistantController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\HomeAssistantWebServiceController;
use App\Http\Controllers\ScheduledJobController;
use App\Livewire\DashboardManager;
use App\Http\Controllers\WebSocketStreamController;




Route::get('/', function () {
    return view('welcome');
});

Route::get('/homeassistant/websocket/events', [WebSocketStreamController::class, 'index'])
    ->name('homeassistant.websocket.events');

Route::get('/homeassistant/websocket/stream', [WebSocketStreamController::class, 'stream'])
    ->name('homeassistant.websocket.stream');

/* Route::get('/homeassistant/websocket/test', [WebSocketStreamController::class, 'test'])
    ->name('homeassistant.websocket.test'); */

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
    Route::get('/list', [HomeAssistantController::class, 'listEntities']);
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

Route::get('/homeassistant/dashboard2', function () {
    return view('homeassistant.dashboard2');
})->name('homeassistant.dashboard2');


Route::prefix('homeassistant/scheduled-jobs')->name('scheduled-jobs.')->group(function () {
    // Resource Routes ZUERST (außer create/edit die bleiben vor show)
    Route::get('/', [ScheduledJobController::class, 'index'])->name('index');
    Route::get('create', [ScheduledJobController::class, 'create'])->name('create');
    Route::post('/', [ScheduledJobController::class, 'store'])->name('store');
    Route::get('{scheduledJob}/edit', [ScheduledJobController::class, 'edit'])->name('edit');
    Route::put('{scheduledJob}', [ScheduledJobController::class, 'update'])->name('update');
    Route::delete('{scheduledJob}', [ScheduledJobController::class, 'destroy'])->name('destroy');

    // Custom routes NACH den Resource-Routes (spezifischere Routen)
    Route::post('{scheduledJob}/toggle', [ScheduledJobController::class, 'toggle'])->name('toggle');
});

// Worker Status außerhalb der Gruppe (eigener Pfad)
Route::get('/homeassistant/queue/worker-status', [ScheduledJobController::class, 'workerStatus'])->name('scheduled-jobs.worker-status');

Route::get('/homeassistant', [HomeAssistantController::class, 'dashboard'])
    ->name('homeassistant.dashboard');

// Monitor
Route::get('/homeassistant/monitor', [HomeAssistantController::class, 'monitor'])->name('homeassistant.monitor');

Route::get('/homeassistant/queue/worker-status', [ScheduledJobController::class, 'workerStatus']);
