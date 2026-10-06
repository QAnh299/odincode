<?php

namespace App\Livewire\SaleLeader;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.sale-leader.home')->title(__('roles.sale_leader'));
    }
}
