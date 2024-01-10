<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Aquereur extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $with=['client'];

    protected $table='aquereurs';
    protected $dates=['deleted_at'];

    public function client(){
        return $this->belongsTo(Client::class,'client_id');
    }
    public function reservation(){
        return $this->belongsTo(Reservation::class,'reservation_id');
    }


}
