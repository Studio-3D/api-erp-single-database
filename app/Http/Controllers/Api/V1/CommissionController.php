<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\CommissionConfiguration;
use App\Models\CommissionMontant;

use App\Models\CommissionCumule;
use App\Models\CommissionMensuelle;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Societe;
use App\Models\UserProjet;
use App\Models\Reservation;
use App\Http\Helpers\PaginationHelper;


use Illuminate\Support\Facades\File;
class CommissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    //liste des Configurations
    public function configurations_commissions(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $query = CommissionConfiguration::on('temp')->where('projet_id', $projet_id);
            $configurations = $query->orderBy('created_at', 'asc')
                    ->get();
            return response()->json(['configurations' => $configurations], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }



    //store les Configurations
    public function store(Request $request)
    {

        if (RoleHelper::AdminSup()) {
            $user = Auth::user();
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $commission=CommissionMontant::on('temp')->where('projet_id',$request->projet_id)->first();
            if($commission!=null){
                if($request->montant!=$commission->montant){
                    $commission->setConnection('temp');
                    $commission->montant=$request->commission_montant;
                    $commission->user_id=$userAuth->value('id');
                    $commission->save();
                }
            }else{
                $com = new CommissionMontant();
                    $com->setConnection('temp');
                    $com->montant = $request->commission_montant;
                    $com->projet_id=$request->projet_id;
                    $com->user_id=$userAuth->value('id');
                    $com->save();
            }


            $dataArray_config = json_decode($request->input('configuration'), true);

            if ($dataArray_config) {
            //delete old configuration

                $old_configurations=CommissionConfiguration::on('temp')->where('projet_id',$request->projet_id)->get();
                if(count($old_configurations)>0){
                    foreach($old_configurations as $old){
                        $old->delete();
                    }
                }
            //store les news configurations

                foreach ($dataArray_config as $inputs) {
                    $com = new CommissionConfiguration();
                    $com->setConnection('temp');
                    $com->projet_id = $request->projet_id;
                    $com->de = $inputs['de'];
                    $com->a =$inputs['a'];
                    $com->pourcentage =$inputs['pourcentage'];
                    $com->user_id=$userAuth->value('id');
                    $com->save();
                }
                return response()->json(['commission' => 'done'], 200);

            }

        }else{
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    }

    // get montant fixe par projet
    public function commission_montant($projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $commission = CommissionMontant::on('temp')->where('projet_id',$projet_id)->orderBy('created_at','desc')->first();
            return response()->json(['commission_montant' => $commission], 200);
        }
        return response()->json(['error', 'Unauthorized'], 401);
    }

    //liste des Commissions Mensuelle en Attente
    public function commissions_mensuelle_en_attente(Request $request, $projet_id)
    {
        if (RoleHelper::AdminSup()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            DatabaseHelper::Config();

            $commission_montant=CommissionMontant::on('temp')->where('projet_id',$projet_id)->orderBy('created_at','desc')->first();
            $commission_configurations=CommissionConfiguration::on('temp')->where('projet_id',$projet_id)->get();

            //get users_id des users had commission mensueele cette mois
            $users_has_commission_mensuelle_cette_mois=CommissionMensuelle::on('temp')->whereYear('date_traitement', Carbon::now()->year)->whereMonth('date_traitement', Carbon::now()->month)->get();
            $array1=array();
            if(count($users_has_commission_mensuelle_cette_mois)>0){
                    foreach($users_has_commission_mensuelle_cette_mois as $us){
                        array_push($array1,array('user_id' => $us->user_id));
                    }
            }
            //get all users by projet id
            $users = UserProjet::on('temp')->with('user')
            ->where('projet_id', $projet_id)
            ->whereHas('user', function ($q) {
                $q->where('role', 3);
            })->distinct()->get();
            //users by projet id
            $array2=array();
            if(count($users)>0){
                foreach($users as $us){
                    array_push($array2,array('user_id' => $us->user_id));
                }
            }
            //comapare les deux array and get les non doublons
            // Extract user_ids from both collections
            $user_ids_1 = collect($array1)->pluck('user_id');
            $user_ids_2 = collect($array2)->pluck('user_id');

            // Find user_ids that are unique in either array
            $unique_ids_1 = $user_ids_1->diff($user_ids_2); // user_ids in array1 but not in array2
            $unique_ids_2 = $user_ids_2->diff($user_ids_1); // user_ids in array2 but not in array1

            // Combine the unique user_ids from both arrays
            $unique_user_ids = $unique_ids_1->merge($unique_ids_2);


            //les users has commission mensuelle

            $users_with_data_vente=array();
            if(count($unique_user_ids)>0){
                foreach($unique_user_ids as $us_id){

                    $nb_vente=Reservation::on('temp')->where('etat',1)->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month)->where('user_id',$us_id)->count();
                    $commission=0;
                    if($nb_vente>0){
                     // calcul de commension Mensuelle

                    $commission=0;
                    if(count($commission_configurations)>0){
                        foreach($commission_configurations as $conf){
                            //de<nb_vente<a
                            if($nb_vente>=$conf->de && $nb_vente<=$conf->a){
                                //(montant*nb_vente)+ le pourcentage de configuration
                                $montant_fois_nb_vente=$nb_vente*$commission_montant->montant;
                                $montant_par_percent=$montant_fois_nb_vente*($conf->pourcentage/100);
                                $commission=$montant_fois_nb_vente+$montant_par_percent;
                            }
                        }
                    }
                    //get nome prenom fron array users
                    $name='';
                    $prenom='';
                    foreach ($users as $item) {
                        if ($item->user_id == $us_id) {
                            $name=$item->user->name;
                            $prenom=$item->user->prenom;
                            break; // Stop the loop if you found the user
                        }
                    }
                    //njib les noms w prenom nchof kif ndir nakhod nom w prenom mn dik array d users
                    array_push($users_with_data_vente,array('id' => $us_id,'name' =>$name ,'prenom' => $prenom,'nb_vente' => $nb_vente,'commission' => $commission));
                }
                }
            }

             // Paginate the array of visites
             $data = PaginationHelper::paginate_array($users_with_data_vente, $size, $page, $request->url());

             $items = $data->items();

             $pagination = [
                 'currentPage' => $data->currentPage(),
                 'totalItems' => $data->total(),
                 'totalPages' => $data->lastPage(),
             ];

             return response()->json([
                 'data' => $items,
                 'pagination' => $pagination,
             ], 200);

           // return response()->json(['users_with_data_vente' => $users_with_data_vente], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function cummulles_commissions($user_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $cummules_commission = CommissionCumule::on('temp')->where('user_id',$user_id)->where('etat','1')->orderBy('created_at','desc')->get();
            return response()->json(['cummules_commission' => $cummules_commission], 200);
        }
        return response()->json(['error', 'Unauthorized'], 401);
    }
    public function traiter_commission($id,Request $request)
    {
        if (RoleHelper::AdminSup() ) {
            DatabaseHelper::Config();
            $com = new CommissionMensuelle();

            $com->setConnection('temp');

            $com->user_id=$id;
            $com->projet_id=$request->projet_id;
            $com->montant=$request->montant;
            $com->date_traitement=$request->date_traitement;
            $com->mode_paiement=$request->mode_paiement;
            if ($com->save()) {
                //desactive les anciens cumul w store les new cumul si la somme des montant !=0
                $somme=$request->montant-$request->total_commission_cumul_et_now;
                if($somme!=0){
                    //desactive old cumul
                    $cummules_commission = CommissionCumule::on('temp')->where('user_id',$id)->where('etat','1')->orderBy('created_at','desc')->get();
                    foreach($cummules_commission as $com){
                        $com->etat='0';
                        $com->save();
                    }
                    //store new Cumul
                    $comul = new CommissionCumule();
                    $comul->setConnection('temp');
                    $comul->montant=abs($somme);
                    $comul->user_id=$id;
                    $comul->projet_id=$request->projet_id;
                    $comul->commission_id=$com->id;
                    $comul->etat='1';
                    $comul->save();
                }
                return response()->json(['message' => 'commission Traite avec succés'], 200);
            } else {
                return response()->json(['message' => 'Commission Non Traité'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function commissions_traites(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = CommissionMensuelle::on('temp')->where('projet_id', $projet_id);

           /* if ($request->filled('nature_travaux')) {
                $query->where('nature_travaux', 'like', '%' . $request->input('nature_travaux') . '%');
            }

            if ($request->filled('cout')) {
                $query->where('cout',  $request->input('cout') );
            }
            if ($request->filled('date_validation')) {
                $start = Carbon::parse($request->input('date_validation'));
                $query->whereDate('date_validation', $start);
            }*/

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $comm = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $comm->currentPage(),
                    'totalItems' => $comm->total(),
                    'totalPages' => $comm->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $comm = $comm->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $comm,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function commissions_cumuls_by_projet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = CommissionCumule::on('temp')->where('projet_id', $projet_id)->where('etat','1');

           /* if ($request->filled('nature_travaux')) {
                $query->where('nature_travaux', 'like', '%' . $request->input('nature_travaux') . '%');
            }

            if ($request->filled('cout')) {
                $query->where('cout',  $request->input('cout') );
            }
            if ($request->filled('date_validation')) {
                $start = Carbon::parse($request->input('date_validation'));
                $query->whereDate('date_validation', $start);
            }*/

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $comm = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $comm->currentPage(),
                    'totalItems' => $comm->total(),
                    'totalPages' => $comm->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $comm = $comm->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $comm,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

}

