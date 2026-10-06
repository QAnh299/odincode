<?php

namespace App\Livewire\SaleAdmin;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.sale-admin.home')->title(__('roles.sale_admin'));
    }
}
