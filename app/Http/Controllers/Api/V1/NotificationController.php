<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Relance_Rdv_visite;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\PaginationHelper;
use DB;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Avance;
use App\Models\Reservation;
use App\Enum\StatutReservationEnum;
use App\Models\Desistement;
use App\Models\PenaliteDesistement;
use App\Models\Remboursement;
use App\Enum\RoleEnum;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request,$projet_id)
    {
        if(Auth::guard('api')->check()){
            DatabaseHelper::Config();
            $perPage=$request->input('pageSize',config('app.default_item_number_perpage'));
            $page=$request->input('page',1);
            $all_notifications=[];
            $new_notifications_count=0;
            $data_get=$this->get_notifications($request,$projet_id);
            foreach($data_get->original as $key => $v){
                if($key=='all_notifications'){
                    $all_notifications = PaginationHelper::paginate_array($v->toArray(),$perPage,$page,$request->url());
                }
                if($key=='new_notifications_count'){
                    $new_notifications_count=$v;
                }
            }

            return response()->json(['all_notifications'=>$all_notifications,'new_notifications_count'=>$new_notifications_count],200);
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    public function get_relances_visites(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::AdminSup()){


                $rel_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->select('relances_rdv_visites.*')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('visites.etat',1)
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)
                ->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);


            }else{

                $rel_visites=Relance_Rdv_visite::on('temp')->select('relances_rdv_visites.*')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id') ->select('relances_rdv_visites.*')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('relances_rdv_visites.user_id', $userAuth->value('id'))->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);
            }
           return response()->json(['relance_visites' => $rel_visites]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }
    public function get_nb_relances_visites(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::AdminSup()){
                $nb_rel_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->select('relances_rdv_visites.*')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('visites.etat',1)
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)
                ->orderby('relances_rdv_visites.date_relance', 'asc') ->count();
            }else{

                $nb_rel_visites=Relance_Rdv_visite::on('temp')->select('relances_rdv_visites.*')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id') ->select('relances_rdv_visites.*')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('relances_rdv_visites.user_id', $userAuth->value('id'))->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)->orderby('relances_rdv_visites.date_relance', 'asc') ->count();
            }
           return response()->json(['nb' => $nb_rel_visites]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }

    }
    public function get_rdv_visites(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::AdminSup()){
                $rdv_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->select('relances_rdv_visites.*')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=', Carbon::now())
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->where('visites.etat',1)
                ->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);


            }else{
                $rdv_visites=Relance_Rdv_visite::on('temp')->select('relances_rdv_visites.*')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=', Carbon::now())->where('relances_rdv_visites.user_id', $userAuth->value('id'))->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);
            }
           return response()->json(['rdv_visites' => $rdv_visites]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }
    public function get_nb_rdv_visites(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::AdminSup()){
                $nb_rdv_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->select('relances_rdv_visites.*')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=', Carbon::now())
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->where('visites.etat',1)
                ->orderby('relances_rdv_visites.date_relance', 'asc') ->count();


            }else{
                $nb_rdv_visites=Relance_Rdv_visite::on('temp')->select('relances_rdv_visites.*')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=', Carbon::now())->where('relances_rdv_visites.user_id', $userAuth->value('id'))->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->orderby('relances_rdv_visites.date_relance', 'asc') ->count();
            }
           return response()->json(['nb' => $nb_rdv_visites]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }
    public function get_notifications_menu_horizontal_crm(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {

            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::AdminSup()){

                $rel_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)->where('visites.etat',1)
                ->orderby('relances_rdv_visites.date_relance', 'asc')->count();
                $rdv_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=',Carbon::now())->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->orderby('relances_rdv_visites.rdv', 'asc')
                ->count();
                $rel_client_freins=0;
                $frein=new FreinController();
                $data_get=$frein->get_clients_freins($projet_id,$request);
                foreach($data_get->original as $key => $v){
                    if($key=='count_clients'){
                        $rel_client_freins = $v;
                    }

                }

            }else{

                $rel_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('relances_rdv_visites.user_id', $userAuth->value('id'))->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)->orderby('relances_rdv_visites.date_relance', 'asc') ->count();
                $rdv_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=',Carbon::now())->where('relances_rdv_visites.user_id', $userAuth->value('id'))->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->orderby('relances_rdv_visites.rdv', 'asc')->count();
                $rel_client_freins=0;
                $frein=new FreinController();
                $data_get=$frein->get_clients_freins($projet_id,$request);
                foreach($data_get->original as $key => $v){
                    if($key=='count_clients'){
                        $rel_client_freins = $v;
                    }

                }
            }
           return response()->json(['relance_visites' => $rel_visites,'rdv_visites' => $rdv_visites,'rel_client_freins' => $rel_client_freins]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }
    public function get_nb_frein_client_visite(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {

            DatabaseHelper::Config();
            if(RoleHelper::AdminSup()){
                $rel_client_freins=0;
                $frein=new FreinController();
                $data_get=$frein->get_clients_freins($projet_id,$request);
                foreach($data_get->original as $key => $v){
                    if($key=='count_clients'){
                        $rel_client_freins = $v;
                    }

                }
            }else{

             $rel_client_freins=0;
                $frein=new FreinController();
                $data_get=$frein->get_clients_freins($projet_id,$request);
                foreach($data_get->original as $key => $v){
                    if($key=='count_clients'){
                        $rel_client_freins = $v;
                    }
                }
            }
           return response()->json(['nb' => $rel_client_freins]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }

    public function get_notifications(Request $request,$projet_id){
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $i=0;
            if(RoleHelper::AdminSup()){

               $all_notifications=Notification::on('temp')->with('prospect','user','reservation','avance','bien')
               ->where(function ($query) {
                $query->where('role',RoleEnum::ADMIN->value)
                    ->orwhere('user_id',Auth::guard('api')->user()->id)
                ;})
               ->where('projet_id',$projet_id)->withTrashed()->whereDate('date', '<=', Carbon::now())->orderBy('id','desc')->get();
               $new_notifications_count=Notification::on('temp')->where('projet_id',$projet_id)->where('role',RoleEnum::ADMIN->value)->where('deleted_at',null)->count();

            }else{
                $all_notifications=Notification::on('temp')->with('prospect','user','reservation','avance','bien')->where('projet_id',$projet_id)->where('user_id',Auth::guard('api')->user()->id)->withTrashed()->whereDate('date', '<=', Carbon::now())->orderBy('date','desc')->get();
                $new_notifications_count=Notification::on('temp')->where('projet_id',$projet_id)->where('deleted_at',null)->where('user_id',Auth::guard('api')->user()->id)->count();
                }
           return response()->json(['all_notifications' => $all_notifications,'new_notifications_count'=>$new_notifications_count]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }

    public function DestroyNotif($id){
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $notif = Notification::on('temp')->withTrashed()->findOrFail($id);
            $notif->delete();
            return response()->json(['notification' => $notif]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }
    public function destory_force_by_column_id($text,$id){
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            if($text=='visite'){
                $notifications=Notification::on('temp')->where('visite_id',$id)->get();
            }
            elseif($text=='reservation'){
                $notifications=Notification::on('temp')->where('reservation_id',$id)->get();
            }
            foreach($notifications as $notif){
                $notif->forceDelete();
            }
            return response()->json('done');
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }

    /*public function get_notif_rejete_commercial($projet_id){
        $user = Auth::user();
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            //reservation
            $avances = Avance::on('temp')->select('reservation_id', DB::raw('SUM(avances.montant) as sum_avances'))
            ->groupby('reservation_id');
            $nb_res_rejete = Reservation::on('temp')->with('last_statut')
            ->joinSub($avances, 'avances_req', function ($join) {
                $join->on('avances_req.reservation_id', '=', 'reservations.id');
            })
            ->where('reservations.projet_id', $projet_id)
            ->where('reservations.statut', 2)
            ->where('reservations.user_id',  $userAuth->value('id'))
            ->where('reservations.etat', 1)->count();
                //avances
            $nb_avance_rejete = Avance::on('temp')->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
            ->where('reservations.etat', 1)
                        ->whereNull('reservations.deleted_at')
            ->where('avances.statut',2)
            ->where('avances.user_id',  $userAuth->value('id'))
            ->where('reservations.projet_id',$projet_id)->count();

            return response()->json(['nb_res_rejete'=>$nb_res_rejete,
            'nb_avance_rejete'=>$nb_avance_rejete,
        ]);
        } else  return response()->json(['error'=>'Unauthorized'], 401);
    }*/


    public function get_notif_menu_horizontal_vente_admin(Request $request,$projet_id){
        if (Auth::guard('api')->check() ) {
            DatabaseHelper::Config();
            if(RoleHelper::AdminSup()){
                $nb_desistement_att_valide = Desistement::on('temp')->where('archive',0)->where('projet_id',$projet_id)->where('statut',0)->count();
                $nb_pen_att_valide = PenaliteDesistement::on('temp')->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                ->where('penalites_desistements.archive',0)
                ->where('desistements.archive',0)
                ->where('desistements.deleted_at',NULL)
                ->where('desistements.projet_id',$projet_id)->where('penalites_desistements.statut',0)->count();
                $nb_av_att_validation = Avance::on('temp')->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                ->whereNull('reservations.deleted_at')
                ->where('reservations.etat', 1)
                ->where('reservations.statut', StatutReservationEnum::Validé->value)
                ->where('avances.statut',3)
                ->where('reservations.projet_id',$projet_id)->count();
                $nb_res_att_validation = Reservation::on('temp')->with('last_statut')
                ->where('projet_id', $projet_id)
                ->where('statut', 3)
                ->where('etat', 1)->count();
                $nb_demande = Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',0)->where('remboursements.etat',1)
                ->where(function ($query) {
                    $query->where('remboursements.mode_rembourse', 'apres_vente')
                        ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                    ;})->count();

                $nb_echeance = Avance::on('temp')
                    ->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.projet_id', $projet_id)
                    //->where('avances.sr', 1)
                    ->whereDate('avances.echeance', '<=', Carbon::now())
                    ->where('reservations.etat', 1)->count();
            }
           return response()->json(['nb_dst_att_valide' => $nb_desistement_att_valide,'nb_pen_att_valide'=>$nb_pen_att_valide,'nb_av_att_validation'=>$nb_av_att_validation,'nb_res_att_validation'=>$nb_res_att_validation,'nb_demande_pre_remourse'=>$nb_demande,'nb_echeance'=>$nb_echeance]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }
    public function get_notif_menu_horizontal_vente_comm(Request $request,$projet_id){
        if (Auth::guard('api')->check() ) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::Com()){
                $nb_desistement_encours = Desistement::on('temp')->where('archive',0)->where('projet_id',$projet_id)->where('statut',0)->where('user_id', $userAuth->value('id'))->count();

                $nb_pen_en_cours = PenaliteDesistement::on('temp')->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                ->where('penalites_desistements.archive',0)
                ->where('desistements.archive',0)
                ->where('desistements.projet_id',$projet_id)
                ->where('penalites_desistements.statut',0)
                ->where('desistements.user_id', $userAuth->value('id'))
                ->where('desistements.deleted_at',NULL)
                ->count();
                $nb_av_en_cours = Avance::on('temp')->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                ->whereNull('reservations.deleted_at')
                ->where('reservations.etat', 1)
                ->where('reservations.statut', StatutReservationEnum::Validé->value)
                ->where('avances.statut',3)
                ->where('avances.user_id',  $userAuth->value('id'))
                ->where('reservations.projet_id',$projet_id)->count();
                $nb_res_en_cours = Reservation::on('temp')->with('last_statut')
                ->where('projet_id', $projet_id)
                ->where('statut', 3)
                ->where('etat', 1)->where('user_id',  $userAuth->value('id'))->count();

                 $nb_demande = Remboursement::on('temp')->join('desistements', 'desistements.id', '=', 'remboursements.desistement_id')
                ->where('desistements.projet_id',$projet_id)->where('remboursements.statut',0)->where('remboursements.etat',1)
                ->where('desistements.user_id', $userAuth->value('id'))
                ->where(function ($query) {
                    $query->where('remboursements.mode_rembourse', 'apres_vente')
                        ->orwhere('remboursements.mode_rembourse', 'transfert_rem_apres_vente')
                    ;})->count();

                $nb_echeance = Avance::on('temp')
                    ->join('reservations', 'avances.reservation_id', '=', 'reservations.id')
                    ->whereNull('reservations.deleted_at')
                    ->where('reservations.projet_id', $projet_id)
                   // ->where('avances.sr', 1)
                    ->whereDate('avances.echeance', '<=', Carbon::now())
                    ->where('reservations.etat', 1)->where('avances.user_id',  $userAuth->value('id'))->count();

            }
           return response()->json(['nb_dst_en_cours' => $nb_desistement_encours,'nb_pen_en_cours'=>$nb_pen_en_cours,'nb_av_en_cours'=>$nb_av_en_cours,'nb_res_en_cours'=>$nb_res_en_cours,'nb_demande_pre_remourse'=>$nb_demande,'nb_echeance'=>$nb_echeance]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }

}
