<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreReservation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='pre_reservations';
    protected $dates=['deleted_at'];
    protected $with = ['bien','visite'];
    public function bien()
    {
        return $this->belongsTo(Bien::class,  'bien_id');
    }
    public function visite()
    {
        return $this->belongsTo(Visite::class,  'visite_id');
    }
    public function t_appel()
    {
        return $this->belongsTo(Appel::class,  'appel_id');
    }
    public function desistement()
    {
        return $this->belongsTo(Desistement::class,  'desistement_id');
    }
}
