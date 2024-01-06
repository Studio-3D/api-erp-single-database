<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory;


    use SoftDeletes;

    protected $table='clients';
    protected $dates=['deleted_at'];

   


    public function reservation()
    {
       return $this->hasMany(Reservation::class);
    }
   
    public function aquereur()
    {
       return $this->hasMany(Aquereur::class);
    }
    

    public function prospect()
    {
        return $this->belongsTo(Prospect::class,'prospect_id');
    }

}
