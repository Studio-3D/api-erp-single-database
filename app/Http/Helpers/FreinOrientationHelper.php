<?php

namespace App\Http\Helpers;

use App\Models\FreinOrientation;

class FreinOrientationHelper
{
    public static function createFreinOrientation($orientation,$frein_id){
        $freinOrientation=new FreinOrientation();
        $freinOrientation->setConnection('temp');
        $freinOrientation->orientation=$orientation;
        $freinOrientation->frein_id=$frein_id;
        $freinOrientation->save();
    }
}
