<?php

namespace App\Services;

use App\Models\SchoolEvent;
use Illuminate\Support\Collection;

final class SchoolEventContext
{
    public function __construct(private Collection $events) {}

    /**
     * @return Collection<int, SchoolEvent>
     */
    public function events(): Collection
    {
        return $this->events;
    }
}
