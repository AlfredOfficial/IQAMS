<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with([
                'actor',
                'subject' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Schedule::class => ['subject', 'section'],
                    ]);
                },
            ])
            ->when($filters['action'] ?? null, fn ($query, $value) => $query->where('action', $value))
            ->when($filters['actor_id'] ?? null, fn ($query, $value) => $query->where('actor_id', $value))
            ->when($filters['subject_type'] ?? null, fn ($query, $value) => $query->where('subject_type', $value))
            ->when($filters['subject_id'] ?? null, fn ($query, $value) => $query->where('subject_id', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $actions = AuditLog::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->mapWithKeys(fn (string $action): array => [
                $action => $this->humanize($action),
            ]);

        $actors = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $subjectTypes = AuditLog::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type): array => [
                $type => $this->humanize(class_basename($type)),
            ]);

        return view('audit-logs.index', compact('logs', 'actions', 'actors', 'subjectTypes', 'filters'));
    }

    private function humanize(string $value): string
    {
        return Str::of($value)->replace(['.', '_', '-'], ' ')->headline()->toString();
    }
}
