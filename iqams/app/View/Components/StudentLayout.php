<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StudentLayout extends Component
{
    public function __construct(public string $title = 'Student Portal')
    {
    }

    public function render(): View
    {
        return view('layouts.student');
    }
}
