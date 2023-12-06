<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Relance_Rdv_visite;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\PaginationHelper;
use DB;
use Carbon\Carbon;
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
            if(RoleHelper::AdminSup()){
                $rel_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->select('relances_rdv_visites.*')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('visites.etat',1)
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)
                ->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);

            }else{

                $rel_visites=Relance_Rdv_visite::on('temp')->select('relances_rdv_visites.*')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id') ->select('relances_rdv_visites.*')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('relances_rdv_visites.user_id', Auth::guard('api')->user()->id)->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);
            }
           return response()->json(['relance_visites' => $rel_visites]);
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
            if(RoleHelper::AdminSup()){
                $rdv_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->select('relances_rdv_visites.*')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=', Carbon::now())
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->where('visites.etat',1)
                ->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);


            }else{
                $rdv_visites=Relance_Rdv_visite::on('temp')->select('relances_rdv_visites.*')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=', Carbon::now())->where('relances_rdv_visites.user_id', Auth::guard('api')->user()->id)->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->orderby('relances_rdv_visites.date_relance', 'asc') ->paginate($perPage, ['*'], 'page', $page);
            }
           return response()->json(['rdv_visites' => $rdv_visites]);
        }
         else{
            return response()->json(['error' => 'Unauthorized'], 401);
         }
    }
    public function get_relances_menu(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check() && RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if(RoleHelper::AdminSup()){

                $rel_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')
                ->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())
                ->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)->where('visites.etat',1)
                ->orderby('relances_rdv_visites.date_relance', 'asc')->count();
                $rdv_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=',Carbon::now())->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->orderby('relances_rdv_visites.rdv', 'asc')
                ->count();

            }else{

                $rel_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.date_relance', '<=', Carbon::now())->where('relances_rdv_visites.user_id', Auth::guard('api')->user()->id)->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 1)->orderby('relances_rdv_visites.date_relance', 'asc') ->count();
                $rdv_visites=Relance_Rdv_visite::on('temp')->join('visites','visites.id', '=', 'relances_rdv_visites.visite_id')->where('visites.etat',1)->where('visites.projet_id',$projet_id)->whereDate('relances_rdv_visites.rdv', '<=',Carbon::now())->where('relances_rdv_visites.user_id', Auth::guard('api')->user()->id)->where('relances_rdv_visites.type_traitement', 0)->where('relances_rdv_visites.type', 2)->orderby('relances_rdv_visites.rdv', 'asc')->count();
            }
           return response()->json(['relance_visites' => $rel_visites,'rdv_visites' => $rdv_visites]);
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

               $all_notifications=Notification::on('temp')->with('prospect','user')->where('projet_id',$projet_id)->withTrashed()->orderBy('date','desc')->get();
               $new_notifications_count=Notification::on('temp')->where('projet_id',$projet_id)->where('deleted_at',null)->count();
               /*foreach($all_notifications as $notif){
                if($notif->deleted_at==null){
                    $i+=1;
                }
               }
               $new_notifications_count =$i;*/
            }else{
                $all_notifications=Notification::on('temp')->with('prospect','user')->where('projet_id',$projet_id)->where('user_id',Auth::guard('api')->user()->id)->withTrashed()->orderBy('date','desc')->get();
                $new_notifications_count=Notification::on('temp')->where('projet_id',$projet_id)->where('deleted_at',null)->where('user_id',Auth::guard('api')->user()->id)->count();
                /* foreach($all_notifications as $notif){
                    if($notif->deleted_at==null){
                        $i+=1;
                    }
                }
               $new_notifications_count =$i;*/
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

}
