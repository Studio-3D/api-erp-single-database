<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreTvaRequest;
use App\Models\Tranche;
use App\Models\Bien_Tva;
use App\Models\TvaCollecte;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Coefficient_tranche;
use Carbon\Carbon;

class ComptabiliteController extends Controller

{
    public function store_coefficient_tva(Request $request){
        DatabaseHelper::Config();
        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        $tranche = Tranche::on('temp')->findorfail($request->tranche_id);
               //store coefficient
               $coef_tranche=new Coefficient_tranche();
               $coef_tranche->setConnection('temp');
               $coef_tranche->tranche_id=$request->tranche_id;
               $coef_tranche->coefficient=$request->coefficient;
               $coef_tranche->annee=Carbon::now()->year;
               $coef_tranche->user_id=$userAuth->value('id');
               if($coef_tranche->save()){
                   if($request->qp_bati!=0 && $request->prix_aq!=0){
                      $prix_terrain=$request->coefficient*$request->prix_aq;
                      $surface_terrain_projet=$request->surface_terrain;
                      $QP_bati_tranche=$request->qp_bati;
                      $QP_percent=$QP_bati_tranche/$surface_terrain_projet;//207920;
                      $QP_valeur=$QP_percent*$prix_terrain;
                      $tranche->valeur_terrain_reevalue=$prix_terrain;
                      $tranche->qp_terrain_tranche_percent=$QP_percent;
                      $tranche->qp_terrain_tranche_valeur=$QP_valeur;
                      if($tranche->save()){
                        //delete ancien bien tva
                        if(count($tranche->biens_tva)>0){
                            foreach($tranche->biens_tva as $b_tva){
                                $b_tva->delete();
                            }
                        }
                       //if biens count>0
                       if(count($tranche->bien)>0){
                               foreach($tranche->bien as $b_get){
                               $QP_ter_percent=$b_get->superficie_total/$request->somme_sup_par_tranche;
                               $QP_ter_val=$QP_ter_percent*$QP_valeur;
                               $prix_HT_hors_terrain=($b_get->prix-$QP_ter_val)/(1+$request->taux_tva);
                               $tva=$prix_HT_hors_terrain*$request->taux_tva;
                               $ttc=$b_get->prix;
                               //store to bien comptable
                                       $bien_comptable=new Bien_Tva();
                                       $bien_comptable->setConnection('temp');
                                       $bien_comptable->bien_id=$b_get->id;
                                       $bien_comptable->tranche_id=$request->tranche_id;
                                       $bien_comptable->tva=$tva;
                                       $bien_comptable->prix_ttc=$ttc;
                                       //added
                                       $bien_comptable->qp_terrain_percent=$QP_ter_percent;
                                       $bien_comptable->qp_terrain_valeur=$QP_ter_val;
                                       $bien_comptable->prix_vente_ht_hors_terrain=$prix_HT_hors_terrain;
                                       $bien_comptable->user_id=$userAuth->value('id');
                                       $bien_comptable->save();
                               }
                           }
                       }
                    }

                   }
    }






    public function calculer_tva(StoreTvaRequest $request,$tranche_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $tranche = Tranche::on('temp')->withSum('bien', 'superficie_total')->findorfail($tranche_id);
            $somme_sup_par_tranche=23183;//$tranche->bien_sum_superficie_total;
            $taux_tva=$tranche->projet->taux_tva;//0.20
            $prix_aq=$tranche->projet->prix_acquisition;//109500000.00
            $coeff=$request->coefficient;//1.009
            $surface_terrain=$tranche->projet->surface_terrain;//207920.00
            if($request->action==0){
                //ajouter
                $tranche->setConnection('temp');
                $tranche->qp_bati=$request->qp_bati;//22295
                if($tranche->save()){
                    $data = [
                        'coeffcient' => $request->coefficient,
                        'qp_bati' =>$tranche->qp_bati,
                        'prix_aq'=>$prix_aq,
                        'tranche_id'=>$tranche_id,
                        'surface_terrain'=>$tranche->projet->surface_terrain,
                        'somme_sup_par_tranche'=>$somme_sup_par_tranche,
                        'taux_tva'=>$taux_tva,

                    ];
                    $this->store_coefficient_tva($request->merge($data));
                    return response()->json('Ajouter succuess');

                }

            }else{
                //modifier
                //delete olds bien tva
                if(count($tranche->biens_tva)>0){
                    foreach($tranche->biens_tva as $b_t){
                        $b_t->delete();
                    }
                }

                //delete old coeffcient
                if($tranche->Coefficient_tranche!=null){
                    $tranche->Coefficient_tranche->delete();
                }
                $tranche->setConnection('temp');
                $tranche->qp_bati=$request->qp_bati;
                if($tranche->save()){

                    $data = [
                        'coeffcient' => $request->coefficient,
                        'qp_bati' =>$request->qp_bati,
                        'prix_aq'=>$prix_aq,
                        'tranche_id'=>$tranche_id,
                        'surface_terrain'=>$tranche->projet->surface_terrain,
                        'somme_sup_par_tranche'=>$somme_sup_par_tranche,
                        'taux_tva'=>$taux_tva,
                    ];
                    $this->store_coefficient_tva($request->merge($data));
                    return response()->json( $this->store_coefficient_tva($request->merge($data)));
                }
                return response()->json('Modifier succuess');

            }







        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function get_totaux($tranche_id){
        DatabaseHelper::Config();
        $total_tva = Bien_Tva::on('temp')->where('tranche_id',$tranche_id)->sum('tva');
        $total_prix_ttc = Bien_Tva::on('temp')->where('tranche_id',$tranche_id)->sum('prix_ttc');
        return response()->json(['total_tva' => $total_tva,'total_prix_ttc'=>$total_prix_ttc]);
    }
    public function get_tva_collecte_par_bien (Request $request,$projet_id){
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            $query = TvaCollecte::on('temp')->with('encaissement','reservation','bien');

            if ($request->filled('bien_id')) {
                $query->where('bien_id', $request->input('bien_id'));
            }
            if ($request->input('ancien')==0) {
                 //tva collecte active
                $query->where('etat', 1);
            }else{
                //tva collecte ancien reservation
                $query->where('etat', 4);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $tva_collectes = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $tva_collectes->currentPage(),
                    'totalItems' => $tva_collectes->total(),
                    'totalPages' => $tva_collectes->lastPage(),
                ];

                $tva_collectes = $tva_collectes->items();

                return response()->json([
                    'data' => $tva_collectes,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function get_tva_collecte_mensuelle(Request $request,$projet_id){
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();
            $query = TvaCollecte::on('temp')->with('encaissement','reservation','bien','encaissement.remboursement');
            $query->where('etat', 1);

            if ($request->filled('de')) {
                $start = Carbon::parse($request->input('de'));
                $query->whereDate('created_at','>=', $start);
            }
            if ($request->filled('a')) {
                $end = Carbon::parse($request->input('a'));
                $query->whereDate('created_at','<=', $end);
            }
            if(!$request->filled('de') &&!$request->filled('de')){
                $query->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $tva_collectes = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $tva_collectes->currentPage(),
                    'totalItems' => $tva_collectes->total(),
                    'totalPages' => $tva_collectes->lastPage(),
                ];

                $tva_collectes = $tva_collectes->items();

                return response()->json([
                    'data' => $tva_collectes,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function destroyTvaCollectesByReservationId($reservation_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $tva_c =  TvaCollecte::on('temp')->where('reservation_id', $reservation_id)->get();
            foreach ($tva_c as $t) {
                $t->forceDelete();
            }
            return response()->json(['message' => 'tva deleted successfully'], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

}
