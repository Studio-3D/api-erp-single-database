<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionMontant extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'commission_montant';

    protected $dates = ['deleted_at'];
    public function projet()
    {
        return $this->belongsTo(Projet::class,'projet_id');
    }
}
