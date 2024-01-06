<?php

namespace  App\Http\Helpers;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\HistoriqueBienHelper;
use Illuminate\Support\Facades\Auth;
use App\Models\Bien;
use App\Models\Frein_Bien;
use App\Models\Frein;
use App\Enum\EtatBien;


class Bien_Helper
{
    public static function libererBien($id,$text)

    {
        $bien = Bien::on('temp')->findOrfail($id);
        $bien->etat = EtatBien::DISPONIBLE->value;
        if($bien->save()){
            Bien_Helper::store_bien_frein($bien->id);
        }
        if($text=='console'){
            HistoriqueBienHelper::createHistoriqueBien(1, "liberer automatique",$id,NULL,NULL,NULL);
        }
        else{
            HistoriqueBienHelper::createHistoriqueBien(4, "liberer", $id, Auth::guard('api')->user()->id,NULL,NULL);

        }
    }
    public static function store_bien_frein($id)
    {

        $bien=Bien::on('temp')->findorfail($id);
        $array_fr_id=array();
        $freins= Frein::on('temp')
        ->join('visites', 'visites.id', '=', 'freins.visite_id')
        ->leftjoin('frein_tranches', 'frein_tranches.frein_id', '=', 'freins.id')
        ->leftjoin('frein_etages', 'frein_etages.frein_id', '=', 'freins.id')
        ->leftjoin('frein_orientations', 'frein_orientations.frein_id', '=', 'freins.id')
        ->leftjoin('frein_typologies', 'frein_typologies.frein_id', '=', 'freins.id')
        ->leftjoin('frein_vues', 'frein_vues.frein_id', '=', 'freins.id')
        ->select('freins.id','freins.tranche as fr_tranche','freins.etage as fr_etage',
        'freins.orientation as fr_orientation','freins.typologie as fr_typologie',
       'freins.vue as fr_vue','freins.prix_min as fr_prix_min','freins.prix_max as fr_prix_max',
        'freins.superficie_min as fr_superficie_min','freins.superficie_max as fr_superficie_max',
        'frein_tranches.tranche_id','frein_etages.etage',
        'frein_orientations.orientation','frein_typologies.typologie_id','frein_vues.vue_id','freins.avance as fr_avance'
        )
        ->where('visites.projet_id', $bien->projet_id)
        ->whereIN('freins.etat', [1,2])
        ->where('visites.etat', 1)
        ->get();

        foreach($freins as $fr){
            if( ($fr->fr_tranche==1 && $fr->tranche_id==$bien->tranche_id)
            && ($fr->fr_etage==1 && $fr->etage==$bien->niveau)
            && ($fr->fr_orientation==1 && $fr->orientation==$bien->orientation)
            && ($fr->fr_typologie==1 && $fr->typologie_id==$bien->typologie_id)
            && ($fr->fr_vue==1 && $fr->vue_id==$bien->vue_id)
            && ($fr->fr_prix_min!=null && $fr->fr_prix_min<=$bien->prix)
            && ($fr->fr_prix_max!=null && $fr->fr_prix_max>=$bien->prix)
            && ($fr->fr_superficie_min!=null && $fr->fr_superficie_min<=$bien->superficie_habitable)
            && ($fr->fr_superficie_max!=null && $fr->fr_superficie_max>=$bien->superficie_habitable)
            && (($fr->fr_avance!=null ||$fr->fr_avance!=0 ) && $fr->fr_avance<=$bien->avance_minimale)
            ){
                $exist=0;
                     //test si id du frein exist dans array
                    if(count($array_fr_id)==0){
                        array_push($array_fr_id,$fr->id);
                    }
                    else {
                        //si array.lenght!=0 test si id du frein exist dans array
                        for($i=0;$i<=sizeof($array_fr_id)-1;$i++){
                            if($array_fr_id[$i]==$fr->id){
                                $exist=1;
                            }
                        }
                        if($exist==0){
                            array_push($array_fr_id,$fr->id);
                        }
                    }

            }

        }
        //store to table frein_bien
        if(count($array_fr_id)>0){
            foreach($array_fr_id as $id_fr){
                //if bien_id already exist with this frein (en cas d update bien ->disponible)
                $count_exist_fr_bien=Frein_Bien::on('temp')->where('bien_id',$id)->where('frein_id',$id_fr)->count();
                if($count_exist_fr_bien==0){
                    FreinBienHelper::createFreinBien($bien->id,$id_fr);
                }
            }
        }
        return response()->json(['message' => $bien], 200);

    }

}
