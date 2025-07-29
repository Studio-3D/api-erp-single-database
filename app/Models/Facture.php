<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facture extends Model
{
    use HasFactory;


    use SoftDeletes;

    protected $table='factures';
    protected $dates=['deleted_at'];
    protected $with = ['decompte','fournisseur','user'];


    public function decompte()
    {
        return $this->belongsTo(Decompte::class,'decompte_id');
    }
    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class,'fournisseur_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }

}
