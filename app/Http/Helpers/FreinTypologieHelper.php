<?php

namespace App\Http\Helpers;

use App\Models\FreinTypologie;

class FreinTypologieHelper
{
    public static function createFreinTypologie($typlogie,$frein_id){
        $freinTypologie=new FreinTypologie();
        $freinTypologie->setConnection('temp');
        $freinTypologie->typlogie=$typlogie;
        $freinTypologie->frein_id=$frein_id;
        $freinTypologie->save();
    }
}
