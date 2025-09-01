<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Decompte extends Model
{
    use HasFactory;


    use SoftDeletes;

    protected $table='decomptes';
    protected $dates=['deleted_at'];
    protected $with = ['user'];


    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }
    public function factures()
    {
        return $this->hasMany(Facture::class,'decompte_id');
    }

}
