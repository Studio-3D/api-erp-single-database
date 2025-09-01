<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class   Contrat_vente extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='contrat_ventes';

    protected $dates=['deleted_at'];
    protected $with=['user','reservation'];

    public function  reservation()
    {
        return $this->belongsTo(Reservation::class,'reservation_id');
    }
    public function user(){
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }

}
