<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function created(Model $model): void
    {
        $this->audit->record('created', $model, null, $model->attributesToArray());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if ($changes === []) {
            return;
        }
        $before = [];
        foreach (array_keys($changes) as $key) {
            $before[$key] = $model->getOriginal($key);
        }
        $this->audit->record('updated', $model, $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->audit->record('deleted', $model, $model->attributesToArray(), null);
    }
}
