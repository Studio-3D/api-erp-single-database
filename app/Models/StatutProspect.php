<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatutProspect extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table='statut_prospects';
    protected $with = ['prospect','user'];
    protected $dates=['deleted_at'];
    protected $fillable = [
        'prospect_id','statut','user_id_traite','date_traitement','rdv','date_rappel','commentaire','visite_id','appel_id'
    ];

    // Coerce any incoming statut to numeric string ('0'..'9')
    public function setStatutAttribute($value)
    {
        // Accept numbers, numeric strings, and known backend string tokens (and 'nv_appel')
        $map = [
            'En_attente' => '0', 'en_attente' => '0',
            'Planification_RDV' => '1', 'planification_rdv' => '1',
            'Injoignable' => '2', 'injoignable' => '2',
            'Rappel' => '3', 'rappel' => '3',
            'Converti_en_visite' => '4', 'converti_en_visite' => '4',
            'Nouveau_appel' => '5', 'nouveau_appel' => '5', 'nv_appel' => '5',
            'Affecte' => '6', 'affecte' => '6',
            'Interesse' => '7', 'interesse' => '7',
            'Perdu' => '8', 'perdu' => '8',
            'Receptif' => '9', 'receptif' => '9',
        ];
        if (is_numeric($value)) {
            $this->attributes['statut'] = (string) $value;
            return;
        }
        $this->attributes['statut'] = $map[$value] ?? '0';
    }




    public function prospect(){
        return $this->belongsTo(Prospect::class,'prospect_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id_traite')->withTrashed();
    }

}
