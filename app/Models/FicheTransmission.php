<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FicheTransmission extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'fiche_transmissions';

    protected $dates = ['deleted_at'];
    protected $with = ['user', 'avance'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function avance()
    {
        return $this->belongsTo(Avance::class, 'avance_id');
    }
}
