<?php

namespace  App\Http\Helpers;

use App\Models\TypeBienProjet;

class typeBienProjetHelper
{
    public static function createTypeBienProjet($type_bien_id,$projet_id,  $nbre_biens)

    {
        $TypeBienProjet = new TypeBienProjet();
        $TypeBienProjet->setConnection('temp');
        $TypeBienProjet->type_bien_id= $type_bien_id;
        $TypeBienProjet->projet_id= $projet_id;
        $TypeBienProjet->nbre_biens= $nbre_biens;
        $TypeBienProjet->save();
    }
}
