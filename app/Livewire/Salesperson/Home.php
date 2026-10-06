<?php

namespace App\Livewire\Salesperson;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.salesperson.home')->title(__('roles.salesperson'));
    }
}
