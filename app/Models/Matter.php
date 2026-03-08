<?php

namespace App\Models;

use App\Enums\MatterStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Matter extends Model
{
    /** @use HasFactory<\Database\Factories\MatterFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'title',
        'description',
        'reference_number',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatterStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
