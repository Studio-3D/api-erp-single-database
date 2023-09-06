<?php

namespace App\Http\Helpers;

use App\Models\FreinEtage;

class FreinEtageHelper
{
    public static function createFreinEtage($etage,$frein_id){
        $freinEtage=new FreinEtage();
        $freinEtage->setConnection('temp');
        $freinEtage->etage=$etage;
        $freinEtage->frein_id=$frein_id;
        $freinEtage->save();
    }
}
