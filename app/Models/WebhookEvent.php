<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'webhook_events';
    protected $dates = ['deleted_at'];
    protected $fillable = ['platform', 'event_type', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}
