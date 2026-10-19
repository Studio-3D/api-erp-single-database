<?php

namespace App\Models\coline;

use Illuminate\Database\Eloquent\Model;
use App\Models\Reservation;   // 👈 IMPORT OBLIGATOIRE
use App\Models\User;          // 👈 IMPORT OBLIGATOIRE

class CommissionColine extends Model
{
    protected $table = 'commissions_coline';

    protected $fillable = [
        'reservation_id',
        'etat',
        'montant',
        'montant_lettre',
        'date_traitement',
        'user_id',
        'user_id_traite',
        'enable',
    ];

    protected $casts = [
        'date_traitement' => 'datetime',
    ];

    /**
     * Relation : Réservation
     */
    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /**
     * Relation : Commercial (user_id)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation : User qui a traité la commission
     */
    public function userTraite()
    {
        return $this->belongsTo(User::class, 'user_id_traite');
    }
}
