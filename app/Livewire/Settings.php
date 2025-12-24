<?php
// app/Livewire/Settings.php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Redis;
use App\Services\SettingsService;
use App\Models\Setting;

class Settings extends Component
{
    public $ha_url = '';
    public $ha_token = '';
    public $imap_host = '';
    public $imap_port = '993';
    public $imap_username = '';
    public $imap_password = '';
    public $imap_encryption = 'ssl';

    public $showHaToken = false;
    public $showImapPassword = false;
    public $successMessage = false;

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $this->ha_url = SettingsService::get('ha_url', '');
        $this->ha_token = SettingsService::get('ha_token', '');

        $this->imap_host = SettingsService::get('imap_host', '');
        $this->imap_port = SettingsService::get('imap_port', '993');
        $this->imap_username = SettingsService::get('imap_username', '');
        $this->imap_password = SettingsService::get('imap_password', '');
        $this->imap_encryption = SettingsService::get('imap_encryption', 'ssl');
    }

    public function save()
    {
        $this->validate([
            'ha_url' => 'required|url',
            'ha_token' => 'required|string|min:10',
            'imap_host' => 'nullable|string',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'imap_username' => 'nullable|email',
            'imap_password' => 'nullable|string',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
        ]);

        $settings = [
            'ha_url' => $this->ha_url,
            'ha_token' => $this->ha_token,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'imap_username' => $this->imap_username,
            'imap_password' => $this->imap_password,
            'imap_encryption' => $this->imap_encryption,
        ];

        foreach ($settings as $key => $value) {
            // 1️⃣ DB (persistenter Speicher)
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );

            // 2️⃣ Redis (Runtime Cache)
            Redis::set("settings:$key", $value);
        }

        $this->successMessage = true;
        $this->dispatch('settings-saved');
    }


    public function testConnection()
    {
        // Validiere erst die Felder
        $this->validate([
            'ha_url' => 'required|url',
            'ha_token' => 'required|string|min:10',
        ]);

        // Teste Home Assistant Verbindung
        try {
            // URL bereinigen (trailing slash entfernen)
            $url = rtrim($this->ha_url, '/');

            $response = \Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->ha_token,
                    'Content-Type' => 'application/json',
                ])
                ->get($url . '/api/');

            if ($response->successful()) {
                $data = $response->json();
                $message = $data['message'] ?? 'API running';
                session()->flash('test_success', 'Home Assistant Verbindung erfolgreich! (' . $message . ')');
            } else {
                session()->flash('test_error', 'Verbindung fehlgeschlagen: HTTP ' . $response->status() . ' - ' . $response->body());
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            session()->flash('test_error', 'Verbindungsfehler: Server nicht erreichbar. Prüfe URL und Netzwerk.');
        } catch (\Exception $e) {
            session()->flash('test_error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
