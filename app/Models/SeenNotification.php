<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeenNotification extends Model
{
    use HasFactory;

    protected $table = 'seen_notifications';

    protected $fillable = [
        'user_id',
        'notification_id',
        'projet_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
