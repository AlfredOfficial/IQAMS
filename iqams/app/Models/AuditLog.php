<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use LogicException;

class AuditLog extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'route_name',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit logs are append-only.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit logs are append-only.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getActionLabelAttribute(): string
    {
        return $this->humanize($this->action, 'System event');
    }

    public function getActorLabelAttribute(): string
    {
        return $this->actor?->name ?? ($this->actor_id ? 'Deleted account' : 'System / Console');
    }

    public function getSubjectTypeLabelAttribute(): string
    {
        if (! $this->subject_type) {
            return 'No affected record';
        }

        return $this->humanize(class_basename($this->subject_type), 'Affected record');
    }

    public function getSubjectLabelAttribute(): string
    {
        $subject = $this->subject;

        if (! $subject) {
            return $this->subject_type ? 'Record no longer available' : 'System event';
        }

        return match (true) {
            $subject instanceof User => $subject->name ?: $subject->username,
            $subject instanceof Student => $subject->fullName() ?: 'Student record',
            $subject instanceof Instructor => $subject->fullName() ?: 'Instructor record',
            $subject instanceof NonTeachingStaff => $subject->fullName() ?: 'Staff record',
            $subject instanceof Department => $subject->department_name ?: 'Department record',
            $subject instanceof Course => trim(($subject->course_code ? $subject->course_code.' — ' : '').(string) $subject->course_name) ?: 'Course record',
            $subject instanceof OfficeUnit => trim(($subject->code ? $subject->code.' — ' : '').(string) $subject->name) ?: 'Office/unit record',
            $subject instanceof Subject => trim(($subject->subject_code ? $subject->subject_code.' — ' : '').(string) $subject->subject_name) ?: 'Subject record',
            $subject instanceof Section => $subject->section_name ?: 'Section record',
            $subject instanceof Schedule => $this->scheduleLabel($subject),
            $subject instanceof SchoolEvent => $subject->title ?: 'School event',
            $subject instanceof LeaveRequest => 'Leave request',
            $subject instanceof AttendanceLog => 'Attendance record',
            $subject instanceof ScannerTerminal => $subject->name ?: 'Scanner terminal',
            $subject instanceof SecurityFlag => $subject->category ? $this->humanize($subject->category) : 'Security flag',
            default => $this->subjectTypeLabel,
        };
    }

    public function getRouteLabelAttribute(): string
    {
        return $this->route_name
            ? $this->humanize($this->route_name)
            : 'Not recorded';
    }

    /** @return array<int, array{label:string,value:string}> */
    public function getMetadataItemsAttribute(): array
    {
        return collect($this->metadata ?? [])
            ->map(fn ($value, $key): array => [
                'label' => $this->metadataLabel((string) $key),
                'value' => $this->formatMetadataValue($value, (string) $key),
            ])
            ->values()
            ->all();
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        $subject = $schedule->relationLoaded('subject') ? $schedule->subject?->subject_code : null;
        $section = $schedule->relationLoaded('section') ? $schedule->section?->section_name : null;

        return collect([$subject, $section, $schedule->room ? 'Room '.$schedule->room : null])
            ->filter()
            ->implode(' · ') ?: 'Class schedule';
    }

    private function formatMetadataValue(mixed $value, ?string $key = null): string
    {
        if ($key !== null && $this->isIdentifierKey($key)) {
            return is_array($value)
                ? count($value).' related record(s)'
                : 'Recorded reference';
        }

        if ($value === null) {
            return 'Not provided';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            if ($value === []) {
                return 'None';
            }

            if (array_is_list($value)) {
                return collect($value)->map(fn ($item) => $this->formatMetadataValue($item))->implode(', ');
            }

            return collect($value)
                ->map(fn ($item, $key) => $this->metadataLabel((string) $key).': '.$this->formatMetadataValue($item, (string) $key))
                ->implode('; ');
        }

        return trim((string) $value) === '' ? 'Blank' : (string) $value;
    }

    private function metadataLabel(string $key): string
    {
        if ($this->isIdentifierKey($key)) {
            $key = preg_replace('/_ids?$/i', '', $key) ?: 'record';

            return $this->humanize($key).' reference';
        }

        return $this->humanize($key, 'Detail');
    }

    private function isIdentifierKey(string $key): bool
    {
        $key = strtolower(str_replace('-', '_', trim($key)));

        return $key === 'id'
            || $key === 'ids'
            || str_ends_with($key, '_id')
            || str_ends_with($key, '_ids');
    }

    private function humanize(?string $value, string $fallback = 'Not recorded'): string
    {
        $value = trim((string) $value);

        return $value === ''
            ? $fallback
            : Str::of($value)->replace(['.', '_', '-'], ' ')->headline()->toString();
    }
}
