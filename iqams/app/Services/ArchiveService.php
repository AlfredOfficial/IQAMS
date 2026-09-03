<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArchiveService
{
    public function archive(Model $model, ?Model $actor = null, ?Request $request = null): Model
    {
        if (! $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'archived_at')) {
            throw new \InvalidArgumentException('This record does not support archival.');
        }

        return DB::transaction(function () use ($model, $actor, $request): Model {
            $locked = $model::query()->lockForUpdate()->findOrFail($model->getKey());

            if ($locked->archived_at) {
                return $locked;
            }

            $locked->forceFill(['archived_at' => now()])->save();
            if ($locked instanceof Section) {
                Schedule::query()->where('section_id', $locked->getKey())->update(['active_identity_key' => null]);
            } elseif ($locked instanceof Subject) {
                Schedule::query()->where('subject_id', $locked->getKey())->update(['active_identity_key' => null]);
            } elseif ($locked instanceof Course) {
                Schedule::query()
                    ->whereIn('section_id', $locked->sections()->pluck('id'))
                    ->update(['active_identity_key' => null]);
            }
            app(AuditLogger::class)->record('record.archived', $locked, [
                'record' => $locked::class,
            ], $actor, $request);

            return $locked;
        });
    }
}
