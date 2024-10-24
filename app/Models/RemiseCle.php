<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RemiseCle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='remise_cles';
    protected $dates=['deleted_at'];
    protected $with=['bien','user','userRemis'];

    public function bien()
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function userRemis()
    {
        return $this->belongsTo(User::class, 'user_id_remis');
    }
}
