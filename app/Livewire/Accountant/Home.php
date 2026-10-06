<?php

namespace App\Livewire\Accountant;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.accountant.home')->title(__('roles.accountant'));
    }
}
