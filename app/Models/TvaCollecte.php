<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TvaCollecte extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table='tva_collectes';
    protected $dates=['deleted_at'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
    public function bien()
    {
        return $this->belongsTo(Bien::class, 'bien_id');
    }
    public function encaissement()
    {
        return $this->belongsTo(Encaissement::class, 'encaissement_id')->withTrashed();
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
