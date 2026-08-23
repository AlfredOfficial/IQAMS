<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StaffLayout extends Component
{
    public function __construct(public string $title = 'Staff Portal')
    {
    }

    public function render(): View
    {
        return view('layouts.staff');
    }
}
