<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credit extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='credits';
    protected $dates=['deleted_at'];
    protected $with=['banque'];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
    public function banque()
    {
        return $this->belongsTo(Banque::class,'banque_id');
    }
}
