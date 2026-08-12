<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class InstructorLayout extends Component
{
    public function __construct(public string $title = 'Instructor Portal') {}

    public function render(): View
    {
        return view('layouts.instructor');
    }
}
