<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bien_Tva extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='biens_tva';
    protected $dates=['deleted_at'];

    public function bien()
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }
    public function tranche_id()
    {
        return $this->belongsTo(Tranche::class, 'tranche_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
