<?php

namespace App\Livewire\Director;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.director.home')->title(__('roles.director'));
    }
}
