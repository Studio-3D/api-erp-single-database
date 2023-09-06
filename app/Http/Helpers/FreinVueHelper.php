<?php

namespace App\Http\Helpers;

use App\Models\FreinVue;

class FreinVueHelper
{
    public static function createFreinVue($vue_id,$frein_id){
        $freinVue=new FreinVue();
        $freinVue->setConnection('temp');
        $freinVue->vue_id=$vue_id;
        $freinVue->frein_id=$frein_id;
        $freinVue->save();
    }
}
