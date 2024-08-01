<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;

use App\Enum\InteretEnum;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FreinEtageHelper;
use App\Http\Helpers\FreinOrientationHelper;
use App\Http\Helpers\FreinTrancheHelper;
use App\Http\Helpers\FreinTypologieHelper;
use App\Http\Helpers\FreinVueHelper;
use App\Http\Helpers\FreinBienHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreFreinRequest;
use App\Http\Requests\Traite_Bien_freinRequest;
use App\Http\Requests\UpdateFreinRequest;
use App\Models\Frein;
use App\Models\FreinEtage;
use App\Models\FreinOrientation;
use App\Models\FreinTranche;
use App\Models\FreinTypologie;
use App\Models\Frein_Bien;
use App\Models\Bien;
use App\Models\FreinVue;
use App\Models\Visite;
use Illuminate\Http\Request;
use App\Http\Helpers\PaginationHelper;
use App\Models\Notification;
use App\Http\Helpers\NotificationHelper;
use App\Events\NotifMenuEvent;
use Illuminate\Support\Facades\Config;






use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\countOf;

class FreinController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $freins= Frein::on('temp')->get();
            return response()->json(['freins' => $freins]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFreinRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $frein=new Frein();
            $frein->setConnection('temp');
            $frein->prix_min=$request->prix_min;
            $frein->prix_max=$request->prix_max;
            $frein->superficie_min=$request->sup_min;
            $frein->superficie_max=$request->sup_max;
            $frein->etat=$request->etat;
            $frein->avance=$request->avance;
            $frein->visite_id=$request->visite_id;
            $frein->tranche=empty($request->selectedTranches)?false:true;
            $frein->etage= empty($request->selectedEtages)?false:true ;
            $frein->orientation= empty($request->selectedOrientations) ?false:true;
            $frein->vue= empty($request->selectedVues) ?false:true;
            $frein->typologie= empty($request->selectedTypologies) ?false:true;
            if($frein->save()){
                if(!empty($request->selectedTranches)){
                    foreach($request->selectedTranches as $valeur){
                            FreinTrancheHelper::createFreinTranche($valeur['id'],$frein->id);
                    }
                }
                if(!empty($request->selectedEtages)){
                    foreach($request->selectedEtages as $valeur){
                        FreinEtageHelper::createFreinEtage($valeur,$frein->id);
                    }
                }
                if(!empty($request->selectedOrientations)){
                    foreach($request->selectedOrientations as $valeur){
                        FreinOrientationHelper::createFreinOrientation($valeur,$frein->id);
                    }
                }
                if(!empty($request->selectedTypologies)){
                    foreach($request->selectedTypologies as $valeur){
                        FreinTypologieHelper::createFreinTypologie($valeur['id'],$frein->id);
                    }
                }
                if(!empty($request->selectedVues)){
                    foreach($request->selectedVues as $valeur){
                        FreinVueHelper::createFreinVue($valeur['id'],$frein->id);
                    }
                }
                return response()->json(['frein' => $frein], 200);
            }
            return response()->json(['error' => "Cette visite n'est pas du type perdu."], 520);
        }
        else return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $frein= Frein::on('temp')->findOrfail($id);
            if($frein->exists()) {
                if ($frein->value('tranche') == true) {
                    $frein_tranches = FreinTranche::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_tranches'] = $frein_tranches;
                }
                if ($frein->value('etage') == true) {
                    $frein_etages = FreinEtage::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_etages'] = $frein_etages;
                }
                if ($frein->value('vue') == true) {
                    $frein_vues = FreinVue::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_vues'] = $frein_vues;
                }
                if ($frein->value('typologie') == true) {
                    $frein_typologies = FreinVue::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_typologies'] = $frein_typologies;
                }
                if ($frein->value('orientation') == true) {
                    $frein_orientations = FreinOrientation::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_orientations'] = $frein_orientations;
                }
            }
            return response()->json(['frein'=>$frein], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFreinRequest $request, $id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $frein=Frein::on('temp')->findOrFail($id);

            if(in_array("SUPERFICIE", $request->frein)=='true'){
                $frein->superficie_min=$request->sup_min;
                $frein->superficie_max=$request->sup_max;
            }
            else{
                $frein->superficie_min=null;
                $frein->superficie_max=null;
            }

            if(in_array("PRIX", $request->frein)=='true'){
                $frein->prix_min=$request->prix_min;
                $frein->prix_max=$request->prix_max;
            }
            else{
                $frein->prix_min=null;
                $frein->prix_max=null;
            }
            if(in_array("AVANCE", $request->frein)=='true'){
                $frein->avance=$request->avance;
            }else{
                $frein->avance=null;
            }
            $frein->etat=$request->etat;
            $frein->tranche=in_array("TRANCHE", $request->frein)?false:true;
            $frein->etage=in_array("ETAGE", $request->frein)?false:true ;
            $frein->orientation= in_array("ORIENTATION", $request->frein)?false:true;
            $frein->vue=in_array("VUE", $request->frein)?false:true;
            $frein->typologie= in_array("TYPOLOGIE", $request->frein) ?false:true;
            $frein->save();
            FreinTrancheHelper::destroyFreinTranche($frein->id);
            if(!empty($request->selectedTranches) && in_array("TRANCHE", $request->frein) ){
                foreach($request->selectedTranches as $valeur){
                        FreinTrancheHelper::createFreinTranche($valeur['id'],$frein->id);
                }
            }
            FreinEtageHelper::destroyFreinEtage($frein->id);
            if(!empty($request->selectedEtages) && in_array("ETAGE", $request->frein)){
                foreach($request->selectedEtages as $valeur){
                    FreinEtageHelper::createFreinEtage($valeur,$frein->id);
                }
            }
            FreinOrientationHelper::destroyFreinOrientation($frein->id);
            if(!empty($request->selectedOrientations) && in_array("ORIENTATION", $request->frein)){
                foreach($request->selectedOrientations as $valeur){
                    FreinOrientationHelper::createFreinOrientation($valeur,$frein->id);
                }
            }
            FreinTypologieHelper::destroyFreinTypologie($frein->id);
            if(!empty($request->selectedTypologies) && in_array("TYPOLOGIE", $request->frein)){
                foreach($request->selectedTypologies as $valeur){
                    FreinTypologieHelper::createFreinTypologie($valeur['id'],$frein->id);
                }
            }
            FreinVueHelper::destroyFreinVue($frein->id);
            if(!empty($request->selectedVues) && in_array("VUE", $request->frein)){
                foreach($request->selectedVues as $valeur){
                    FreinVueHelper::createFreinVue($valeur['id'],$frein->id);
                }
            }
             //destroy frein bien dispo
             FreinBienHelper::destroyFreinBien($frein->id);
             //notification des biens disponible pour ce frein
             NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);
            return response()->json(['frein'=>$id]);
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $frein=Frein::on('temp')->findOrFail($id);
            if($frein->tranche){
                $freinTranches=FreinTranche::on('temp')->where('frein_id',$id)->get();
                foreach ($freinTranches as $freinTranche){
                    $freinTranche->delete();
                }
            }
            if($frein->etage){
                $freinEtages=FreinEtage::on('temp')->where('frein_id',$id)->get();
                foreach ( $freinEtages as    $freinEtage){
                    $freinEtage->delete();
                }
            }
            if($frein->orientation){
                $freinOrientations=FreinOrientation::on('temp')->where('frein_id',$id)->get();
                foreach ( $freinOrientations as    $freinOrientation){
                    $freinOrientation->delete();
                }
            }
            if($frein->typologie){
                $freinTypologies=FreinTypologie::on('temp')->where('frein_id',$id)->get();
                foreach ( $freinTypologies as    $freinTypologie){
                    $freinTypologie->delete();
                }
            }
            if($frein->vue){
                $freinVues=FreinVue::on('temp')->where('frein_id',$id)->get();
                foreach ( $freinVues as     $freinVue){
                    $freinVue->delete();
                }

            }
                //destroy frein bien dispo
                FreinBienHelper::destroyFreinBien($id);
                //notification des biens disponible pour ce frein
                NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);

            if($frein->delete()){
                return response()->json(['message'=>'Frein supprimé avec succès.'],200);
            }
            else return response()->json(['error'=>"Le frein n'a pas été supprimé."],404);
        }
        else return response()->json(['error' => 'Unauthorized'], 401);
    }

    private function syncRelationship($frein, $request, $relation, $modelClass,$pluckAtt)
    {
        if (!empty($frein->$relation)) {
              $frein->$relation()->sync($request);
        } else {
            $existingItems = $modelClass::on('temp')->where('frein_id', $frein->id)->pluck($pluckAtt)->toArray();
            if (!empty($existingItems)) {
                $frein->$relation()->detach($existingItems);
            }
        }
    }

    public function searchFreinByVisiteId($id,$text){
        if($text=='without_row_deleted'){
            $frein=Frein::on('temp')->where('visite_id',$id)->first();
            if($frein){

                    $frein_tranches=FreinTranche::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_tranches)>0){
                        $frein['frein_tranches']=$frein_tranches;
                    }

                    $frein_etages=FreinEtage::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_etages)){
                        $frein['frein_etages']=$frein_etages;
                    }


                    $frein_vues=FreinVue::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_vues)){
                        $frein['frein_vues']=$frein_vues;
                    }



                    $frein_typologies=FreinTypologie::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_typologies)){
                        $frein['frein_typologies']=$frein_typologies;
                    }

                    $frein_orientations=FreinOrientation::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_orientations)){
                        $frein['frein_orientations']=$frein_orientations;
                    }


                return $frein;
            }
            else
            {
                return null;
            }

        }
        else{

            $frein=Frein::on('temp')->withTrashed()->where('visite_id',$id)->first();
            if($frein){

                    $frein_tranches=FreinTranche::on('temp')->withTrashed()->where('frein_id',$frein->id)->get();
                    if(count($frein_tranches)>0){
                        $frein['frein_tranches']=$frein_tranches;
                    }

                    $frein_etages=FreinEtage::on('temp')->withTrashed()->where('frein_id',$frein->id)->get();
                    if(count($frein_etages)){
                        $frein['frein_etages']=$frein_etages;
                    }


                    $frein_vues=FreinVue::on('temp')->withTrashed()->where('frein_id',$frein->id)->get();
                    if(count($frein_vues)){
                        $frein['frein_vues']=$frein_vues;
                    }



                    $frein_typologies=FreinTypologie::on('temp')->withTrashed()->where('frein_id',$frein->id)->get();
                    if(count($frein_typologies)){
                        $frein['frein_typologies']=$frein_typologies;
                    }

                    $frein_orientations=FreinOrientation::on('temp')->withTrashed()->where('frein_id',$frein->id)->get();
                    if(count($frein_orientations)){
                        $frein['frein_orientations']=$frein_orientations;
                    }


                return $frein;
            }
            else
            {
                return null;
            }

        }
    }

    public function get_clients_freins($projet_id,Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            if(RoleHelper::AdminSup()){
                $freins= Frein::on('temp')
                ->join('visites', 'visites.id', '=', 'freins.visite_id')
                ->join('prospects', 'prospects.id', '=', 'visites.prospect_id')
                ->select('freins.tranche','freins.etage','freins.orientation','freins.typologie','freins.vue','freins.prix_min','freins.prix_max','freins.superficie_min','freins.superficie_max','freins.avance','freins.id','freins.created_at','visites.origin_id as id_origin','prospects.cin','prospects.nom', 'prospects.prenom', 'prospects.telephone','prospects.telephone_num2','visites.origin_id')
                ->where('visites.projet_id', $projet_id)
                ->where('freins.etat', 2)
                ->where('visites.etat', 1)
                ->get();
                }
                else{
                    $freins= Frein::on('temp')
                    ->join('visites', 'visites.id', '=', 'freins.visite_id')
                    ->join('prospects', 'prospects.id', '=', 'visites.prospect_id')
                    ->select('freins.tranche','freins.etage','freins.orientation','freins.typologie','freins.vue','freins.prix_min','freins.prix_max','freins.superficie_min','freins.superficie_max','freins.avance','freins.id','freins.created_at','visites.origin_id as id_origin','prospects.cin','prospects.nom', 'prospects.prenom', 'prospects.telephone','prospects.telephone_num2','visites.origin_id')
                    ->where('visites.projet_id', $projet_id)
                    ->where('visites.user_id', Auth::guard('api')->user()->id)
                    ->where('freins.etat', 2)
                    ->where('visites.etat', 1)
                    ->get();
                }


            $clients=array();

            if(($freins->count())>0) {
                foreach ($freins as $fr) {
                    $fr_type=null;

                    //TRANCHE
                    if ($fr->tranche==1) {

                        if($fr_type==null){
                            $fr_type.='TRANCHE';
                           }else{
                            $fr_type.=',TRANCHE';
                        }
                    }

                    //ETAGES
                    if ($fr->etage==1) {
                        if($fr_type==null){
                            $fr_type.='ETAGE';
                           }else{
                            $fr_type.=',ETAGE';
                           }
                    }
                    //orientation
                    if ($fr->orientation==1) {
                        if($fr_type==null){
                            $fr_type.='ORIENTATION';
                           }else{
                            $fr_type.=',ORIENTATION';
                           }
                    }
                    //TYPOLOGIE
                    if ($fr->typologie==1) {
                        if($fr_type==null){
                            $fr_type.='TYPOLOGIE';
                           }else{
                            $fr_type.=',TYPOLOGIE';
                           }
                    }
                    //VUE
                    if ($fr->vue==1) {
                        if($fr_type==null){
                            $fr_type.='VUE';
                           }else{
                            $fr_type.=',VUE';
                           }
                    }
                    //avance
                    if ($fr->avance!=null) {
                        if($fr_type==null){
                            $fr_type.='AVANCE';
                           }else{
                            $fr_type.=',AVANCE';
                        }
                    }
                    //PRIX
                    if ($fr->prix_min!=null ||  $fr->prix_max!=null) {
                        if($fr_type==null){
                            $fr_type.='PRIX';
                           }else{
                            $fr_type.=',PRIX';
                           }
                    }

                    //SUPERFICIE
                    if ($fr->superficie_min!=null && $fr->superficie_max!=null) {
                        if($fr_type==null){
                            $fr_type.='SUPERFICIE';
                           }else{
                            $fr_type.=',SUPERFICIE';
                           }
                    }


                    array_push($clients,array('id' => $fr->id,'date' => $fr->created_at,'nom' => $fr->nom,'prenom' => $fr->prenom,'telephone' => $fr->telephone,'telephone_2' => $fr->telephone_num2,'id_origin' => $fr->origin_id,'frein'=>$fr_type));
                 }
                }
                    $data = PaginationHelper::paginate_array($clients,$perPage,$page,$request->url());
                    return response()->json(['clients' => $data,'count_clients'=>count($clients)]);

              }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    public function biens_by_frein(Request $request,$id)
    {
        if(Auth::guard('api')->check()){
            DatabaseHelper::Config();
            $perPage=$request->input('pageSize',config('app.default_item_number_perpage'));
            $page=$request->input('page',1);
            $biens= Frein_Bien::on('temp')->where('frein_id',$id)->with('is_proposed')->paginate($perPage, ['*'], 'page', $page);;
            return response()->json(['biens'=>$biens],200);
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function traiter_bien_frein(Traite_Bien_freinRequest $request,$bien_id,$frein_id){

        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $frein = Frein::on('temp')->findOrFail($frein_id);
            if($request->pre_reserve==1){
                $bien=new BienController();
                $bien->prereserverBien($bien_id,null,1);
            }
            $frein->etat=3;
            $frein->commentaire=$request->commentaire;
            if($frein->save()){
                //destroy frein bien
                FreinBienHelper::destroyFreinBien($frein_id);
                //notification des biens disponible pour ce frein
                NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);
            }
            Config::set('broadcasting.default', 'pusher_5');
            broadcast(new NotifMenuEvent('C'));


         return response()->json(['message' => $frein], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }


}
