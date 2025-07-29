<?php

namespace App\Models;

use App\Enum\InteretEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TraitementFrein extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * table associer a le model
     */
    protected $table='traitement_freins';
    protected $dates=['deleted_at'];
    protected $with=['visite','frein','user'];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id')->withTrashed();
    }

    public function visite()
    {
        return $this->belongsTo(Visite::class,'visite_id');
    }
    public function frein()
    {
        return $this->belongsTo(Frein::class,'frein_id')->withTrashed();
    }
    public function bien()
    {
        return $this->belongsTo(Bien::class,'bien_id');
    }
    public function rdv_relation()
    {
        return $this->belongsTo(Relance_Rdv_Visite::class,'relance_rdv_id')->withTrashed();
    }

}
