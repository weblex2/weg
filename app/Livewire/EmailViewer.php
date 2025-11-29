<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Email;

class EmailViewer extends Component
{
    public $emails;
    public $selectedEmail = null;

    public function mount()
    {
        $this->emails = Email::orderBy('received_at', 'desc')->get();
        $this->emails = Email::all();
        \Log::info($this->emails->toArray()); // in laravel.log prüfen
    }

    public function selectEmail($id)
    {
        $this->selectedEmail = Email::find($id);
    }

    public function render()
    {
        return view('livewire.email-viewer');
    }
}
