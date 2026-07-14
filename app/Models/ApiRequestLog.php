<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'api_consumer_id',
        'method',
        'path',
        'query_params',
        'status_code',
        'duration_ms',
    ];

    protected $casts = [
        'query_params' => 'array',
    ];

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(ApiConsumer::class, 'api_consumer_id');
    }
}
