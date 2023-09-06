<?php

namespace  App\Http\Helpers;

use Illuminate\Support\Facades\Auth;
use App\Models\HistoriqueBien;


class HistoriqueBienHelper
{
    public static function createHistoriqueBien($action, $description, $bienId, $user_id)

    {
        $HistoriqueBien = new HistoriqueBien();
        $HistoriqueBien->setConnection('temp');
        $HistoriqueBien->action= $action;
        $HistoriqueBien->description= $description;
        $HistoriqueBien->user_id= $user_id;
        $HistoriqueBien->bien_id= $bienId;
        $HistoriqueBien->save();
        //$HistoriqueBien = HistoriqueBien::on('temp')->create($historiqueBienData);
    }
}
