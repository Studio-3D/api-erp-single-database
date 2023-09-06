<?php

namespace App\Http\Helpers;

use App\Models\FreinTranche;

class FreinTrancheHelper
{
    public static function createFreinTranche($tranche_id,$frein_id){
        $freinTranche=new FreinTranche();
        $freinTranche->setConnection('temp');
        $freinTranche->tranche_id=$tranche_id;
        $freinTranche->frein_id=$frein_id;
        $freinTranche->save();
    }
}
