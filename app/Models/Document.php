<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Policies\DocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[UsePolicy(DocumentPolicy::class)]
class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'matter_id',
        'uploaded_by',
        'title',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'status',
        'processing_trace_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
        ];
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function processingEvents(): HasMany
    {
        return $this->hasMany(ProcessingEvent::class);
    }

    public function extractedData(): HasOne
    {
        return $this->hasOne(ExtractedData::class);
    }

    public function classification(): HasOne
    {
        return $this->hasOne(DocumentClassification::class);
    }
}
