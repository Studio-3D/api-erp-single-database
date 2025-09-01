<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionCumule extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'commission_cumule';
    protected $with='user';

    protected $dates = ['deleted_at'];

    public function commission()
    {
        return $this->belongsTo(Commission::class,'commission_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
    public function projet()
    {
        return $this->belongsTo(Projet::class,'projet_id');
    }
}
