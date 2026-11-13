<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalAttachment extends Model
{
    protected $fillable = [
        'medical_record_id',
        'path',
        'kind',
        'original_name',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function url(): ?string
    {
        if (! $this->path || ! $this->id || ! $this->medical_record_id) {
            return null;
        }

        return url('/api/v1/medical-records/'.$this->medical_record_id.'/attachments/'.$this->id);
    }
}
