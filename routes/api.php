<?php

use App\Http\Controllers\AquereurController;
use App\Http\Controllers\AvanceController;
use App\Http\Controllers\BanqueController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\BlocController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompositionBienController;
use App\Http\Controllers\FreinController;
use App\Http\Controllers\ImmeubleController;
use App\Http\Controllers\PiecesJointeController;
use App\Http\Controllers\ProjetController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SocieteController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TrancheController;
use App\Http\Controllers\TypeBienController;
use App\Http\Controllers\TypeFreinController;
use App\Http\Controllers\TypeProjetController;
use App\Http\Controllers\TypologieController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisiteController;
use App\Http\Controllers\PartenaireController;
use App\Http\Controllers\VueController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EnumController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
 */

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', [UserController::class, 'login'])->name('login');
Route::post('/validateToken/{token}', [UserController::class, 'validateToken']);

//Route::post('register', [UserController::class, 'register'])->name('register');

Route::middleware('auth:api')->group(function () {

    /*************************************Société***************************** */
    Route::resource('societe', SocieteController::class);
    Route::post('restoreSociete/{id}', [SocieteController::class, 'restoreSociete'])->name('restoreSociete');
    Route::get('getTrashedSocietes', [SocieteController::class, 'getTrashedSocietes'])->name('getTrashedSocietes');
    Route::put('Switch_Societes', [SocieteController::class, 'Switch_Societes'])->name('Switch_Societes');
    Route::put('ExitSocietes', [SocieteController::class, 'ExitSocietes'])->name('ExitSocietes');
    Route::get('get_societes', [SocieteController::class, 'get_societes'])->name('get_societes');

    /*************************************User***************************** */
    Route::resource('user', UserController::class);
    Route::get('getUsersBySocieteId/{id}', [UserController::class, 'getUsersBySocieteId'])->name('getUsersBySocieteId');
    Route::put('activateUser/{id}', [UserController::class, 'activateUser'])->name('activateUser');
    Route::put('desactivateUser/{id}', [UserController::class, 'desactivateUser'])->name('desactivateUser');
    Route::post('restoreUser/{id}', [UserController::class, 'restoreUser'])->name('restoreUser');
    Route::get('getTrashedUsers', [UserController::class, 'getTrashedUsers'])->name('getTrashedUsers');
    Route::get('dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::get('getTrashedUsersBySociete/{id}', [UserController::class, 'getTrashedUsersBySociete'])->name('getTrashedUsersBySociete');
    Route::post('logout', [UserController::class, 'logout'])->name('logout');
    Route::post('addUserProjet/{id}', [UserController::class, 'addUserProjet'])->name('addUserProjet');
    Route::get('get_users', [UserController::class, 'get_users'])->name('get_users');
    Route::post('sendEmail', [UserController::class, 'sendEmail']);
    Route::post('resendEmail', [UserController::class, 'resendEmail']);

    Route::post('/resetPassword/{token}', [UserController::class, 'resetPassword']);


    /*************************************Projet***************************** */
    Route::resource('projet', ProjetController::class);
    Route::resource('typeProjet', TypeProjetController::class);
    Route::get('get_typeProjets', [TypeProjetController::class, 'get_typeProjets'])->name('get_typeProjets');
    Route::post('restoreProjet/{id}', [ProjetController::class, 'restoreProjet'])->name('restoreProjet');
    Route::get('getTrashedProjets', [ProjetController::class, 'getTrashedProjets'])->name('getTrashedProjets');
    Route::post('restoreTypeProjet/{id}', [TypeProjetController::class, 'restoreTypeProjet'])->name('restoreTypeBien');
    Route::get('getTrashedTypesProjet', [TypeProjetController::class, 'getTrashedTypesProjet'])->name('getTrashedTypesProjet');
    Route::get('get_projets', [ProjetController::class, 'get_projets'])->name('get_projets');
    /*************************************Tranche***************************** */
    Route::resource('tranche', TrancheController::class);
    Route::get('tranches/{projet_id}', [TrancheController::class, 'index'])->name('tranches');
    Route::post('restoreTranche/{id}', [TrancheController::class, 'restoreTranche'])->name('restoreTranche');
    Route::get('getTrashedTranches', [TrancheController::class, 'getTrashedTranches'])->name('getTrashedTranches');
    Route::get('getTranchesByProjet/{id}', [TrancheController::class, 'getTranchesByProjet'])->name('getTranchesByProjet');

    /*************************************Bloc***************************** */
    Route::resource('bloc', BlocController::class);
    Route::get('blocs/{projet_id}', [BlocController::class, 'index'])->name('blocs');
    Route::post('restoreBloc/{id}', [BlocController::class, 'restoreBloc'])->name('restoreBloc');
    Route::get('getTrashedBlocs', [BlocController::class, 'getTrashedBlocs'])->name('getTrashedBlocs');
    Route::get('getBlocsByProjet/{id}', [BlocController::class, 'getBlocsByProjet'])->name('getBlocsByProjet');
    Route::get('getBlocsByTranche/{id}', [BlocController::class, 'getBlocsByTranche'])->name('getBlocsByTranche');
    Route::get('getBlocsByTranchepaginate/{id}', [BlocController::class, 'getBlocsByTranchepaginate'])->name('getBlocsByTranchepaginate');


    /*************************************Immeuble***************************** */
    Route::resource('immeuble', ImmeubleController::class);
    Route::get('immeubles/{projet_id}', [ImmeubleController::class, 'index'])->name('immeubles');
    Route::post('restoreImmeuble/{id}', [ImmeubleController::class, 'restoreImmeuble'])->name('restoreImmeuble');
    Route::get('getTrashedImmeubles', [ImmeubleController::class, 'getTrashedImmeubles'])->name('getTrashedImmeubles');
    Route::get('getImmeublesByBloc/{id}', [ImmeubleController::class, 'getImmeublesByBloc'])->name('getImmeublesByBloc');
    Route::get('getImmeublesByProjet/{id}', [ImmeubleController::class, 'getImmeublesByProjet'])->name('getImmeublesByProjet');
    Route::get('getImmeublesByTranchepaginate/{id}', [ImmeubleController::class, 'getImmeublesByTranchepaginate'])->name('getImmeublesByTranchepaginate');
    Route::get('getImmeublesByTranche/{id}', [ImmeubleController::class, 'getImmeublesByTranche'])->name('getImmeublesByTranche');
    Route::get('getImmeublesByBlocpaginate/{id}', [ImmeubleController::class, 'getImmeublesByBlocpaginate'])->name('getImmeublesByBlocpaginate');

    /*************************************Bien***************************** */
    Route::resource('bien', BienController::class);
    Route::get('biens/{projet_id}', [BienController::class,'index'])->name('biens');
    Route::get('biensProposition/{projet_id}', [BienController::class,'biens_proposition'])->name('');
    Route::post('restoreBien/{id}', [BienController::class, 'restoreBien'])->name('restoreBien');
    Route::get('getTrashedBiens', [BienController::class, 'getTrashedBiens'])->name('getTrashedBiens');
    Route::put('bloquerBien/{id}', [BienController::class, 'bloquerBien'])->name('bloquerBien');
    //Route::put('reserverBien/{id}', [BienController::class, 'reserverBien'])->name('reserverBien');
    Route::put('prereserverBien/{id}/{visite_id}/{appel_id}', [BienController::class, 'prereserverBien'])->name('prereserverBien');
    Route::delete('libererBien/{id}', [BienController::class, 'libererBien'])->name('libererBien');
    Route::get('getHistoriqueBien/{id}', [BienController::class, 'getHistoriqueBien'])->name('getHistoriqueBien');
    Route::resource('compositionBien', CompositionBienController::class);
    Route::get('compositionBiens/{bien_id}', [CompositionBienController::class,'index'])->name('compositionBiens');
    Route::post('restoreCompositionBien/{id}', [CompositionBienController::class, 'restoreCompositionBien'])->name('restoreCompositionBien');
    Route::get('getTrashedCompositionBiens', [CompositionBienController::class, 'getTrashedCompositionBiens'])->name('getTrashedCompositionBiens');
    Route::get('getCompositionBybien/{id}', [CompositionBienController::class, 'getComposition'])->name('getComposition');
    Route::get('getBiensByProjet/{id}', [BienController::class, 'getBiensByProjet'])->name('getBiensByProjet');
    Route::get('getBiensByProjet_Concat/{id}', [BienController::class, 'getBiensByProjet_Concat'])->name('getBiensByProjet_Concat');
    Route::get('getBiensByTranche/{id}', [BienController::class, 'getBiensByTranche'])->name('getBiensByTranche');
    Route::get('getBiensByBloc/{id}', [BienController::class, 'getBiensByBloc'])->name('getBiensByBloc');
    Route::get('getBiensByImmeuble/{id}', [BienController::class, 'getBiensByImmeuble'])->name('getBiensByImmeuble');
    Route::get('getBiensDispoByImmeuble/{id}', [BienController::class, 'getBiensDispoByImmeuble'])->name('getBiensDispoByImmeuble');
    Route::get('getBiensDispoByBloc/{id}', [BienController::class, 'getBiensDispoByBloc'])->name('getBiensDispoByBloc');
    Route::get('getBiensDispoByTranche/{id}', [BienController::class, 'getBiensDispoByTranche'])->name('getBiensDispoByTranche');
    Route::get('getBiensDispoByProjet/{id}', [BienController::class, 'getBiensDispoByProjet'])->name('getBiensByDispoProjet');
    Route::put('setPropostionBien/{id}/{old_id}', [BienController::class, 'setPropostionBien'])->name('');
    Route::get('getEtatBien/{id}', [BienController::class, 'getEtatBien'])->name('getEtatBien');
    Route::get('getBiensByTranchepaginate/{id}', [BienController::class, 'getBiensByTranchepaginate'])->name('getBiensByTranchepaginate');
    Route::get('getBiensByBlocpaginate/{id}', [BienController::class, 'getBiensByBlocpaginate'])->name('getBiensByBlocpaginate');
    Route::get('getBiensByImmeublepaginate/{id}', [BienController::class, 'getBiensByImmeublepaginate'])->name('getBiensByImmeublepaginate');
    /***********************************Type biens******************************** */
    Route::resource('typeBien', TypeBienController::class);
    Route::get('get_typeBiens', [TypeBienController::class, 'get_typeBiens'])->name('get_typeBiens');
    Route::get('get_typeBiensByProjet/{id}', [TypeBienController::class, 'get_typeBiensByProjet'])->name('get_typeBiensByProjet');
    Route::post('restoreTypeBien/{id}', [TypeBienController::class, 'restoreTypeBien'])->name('restoreTypeBien');
    Route::get('getTrashedTypesBien', [TypeBienController::class, 'getTrashedTypesBien'])->name('getTrashedTypesBien');
    Route::get('TypeBiens/{projet_id}', [TypeBienController::class,'index'])->name('TypeBiens');

    /*************************************Visite***************************** */
    Route::resource('visite',VisiteController::class);
    Route::get('visites/{projet_id}', [VisiteController::class,'index'])->name('visites');
    Route::post('store_n_visite/{id}',[VisiteController::class,'store_n_visite'])->name('store_n_visite');
    Route::get('getAllAttributes',[VisiteController::class,'getAllAttributes'])->name('getAllAttributes');
    Route::get('get_historiques_visite/{origin_id}', [VisiteController::class, 'get_historiques'])->name('get_historiques');
    Route::put('traiter_relance_rdv_visite/{id}',[VisiteController::class,'traiter_relance_rdv_visite'])->name('');

    /*************************************type_Freins***************************** */
    Route::resource('type_freins', TypeFreinController::class);
    Route::get('get_typeFreins', [TypeFreinController::class, 'get_typeFreins'])->name('get_typeFreins');
    Route::post('restoreTypeFrein/{id}', [TypeFreinController::class, 'restoreTypeFrein'])->name('restoreTypeFrein');

    /*************************************Prospect***************************** */

    /*************************************Frein***************************** */
    Route::resource('frein', FreinController::class);
    Route::get('get_clients_freins/{projet_id}', [FreinController::class, 'get_clients_freins'])->name('');
    Route::get('biens_by_frein/{id}', [FreinController::class,'biens_by_frein'])->name('');
    Route::put('traiter_bien_frein/{bien_id}/{frein_id}', [FreinController::class,'traiter_bien_frein'])->name('');
    /*************************************Prospect***************************** */
    Route::resource('prospect',ProspectController::class);
    Route::get('search_prospect_by_cin/{cin}', [ProspectController::class, 'search_prospect_by_cin']);
    Route::get('search_prospect_by_phone/{phone}', [ProspectController::class, 'search_prospect_by_phone']);


    /*************************************Source***************************** */
    Route::resource('sources',SourceController::class);
    Route::get('get_sources', [SourceController::class, 'get_sources'])->name('get_sources');


    /*************************************Vue***************************** */
    Route::resource('vue', VueController::class);
    Route::get('get_vuesByProjet/{id}', [VueController::class, 'get_vuesByProjet'])->name('get_vuesByProjet');
    Route::get('vues/{projet_id}', [VueController::class,'index'])->name('vues');

   /*************************************Partenaires***************************** */
   Route::resource('partenaire', PartenaireController::class);
   Route::get('partenaires/{projet_id}', [PartenaireController::class,'index'])->name('');
   Route::get('get_partenaires/{projet_id}', [PartenaireController::class, 'get_partenaires'])->name('get_partenaires');


    /*************************************Typologie***************************** */
    Route::resource('typologie', TypologieController::class);
    Route::get('get_typologiesByProjet/{id}', [TypologieController::class, 'get_typologiesByProjet'])->name('get_typologiesByProjet');
    Route::get('typologies/{projet_id}', [TypologieController::class,'index'])->name('typologies');
    /*************************************Banque***************************** */
    Route::resource('banque',BanqueController::class);
    Route::get('get_banques', [BanqueController::class, 'get_banques'])->name('get_banques');

    /*************************************Client***************************** */
    Route::resource('client',ClientController::class);
    Route::get('get_clients', [ClientController::class, 'get_clients'])->name('get_clients');

    Route::get('getClient_by_projet/{projet_id}', [ClientController::class, 'getClient_by_projet'])->name('getClient_by_projet');
    

    Route::get('search_client_by_cin/{cin}', [ClientController::class, 'search_client_by_cin']);
    Route::get('search_client_by_phone/{phone}', [ClientController::class, 'search_client_by_phone']);



    /*************************************Aquereurs***************************** */
    Route::resource('aquereur',AquereurController::class);
    Route::get('aquereurs/{projet_id}', [AquereurController::class,'index'])->name('aquereurs');
    Route::delete('destoryAquereurUsingReservationId/{reservation_id}',[AquereurController::class, 'destroyAquerreursByReservationId'])->name('destoryAquereurUsingReservationId');
    Route::get('getAcquirerOfReservation/{reservation_id}',[AquereurController::class, 'getAquerreursByReservationId'])->name('getAcquirerOfReservation');
    Route::get('nbOfAcquirersInReservation/{reservation_id}',[AquereurController::class, 'nbAquerreursByReservation'])->name('nbOfAcquirersInReservation');
    Route::get('getAquereur_by_Reservation/{reservation_id}',[AquereurController::class, 'getAquereur_by_Reservation'])->name('getAquereur_by_Reservation');

    /*************************************Avances***************************** */
    Route::resource('avance', AvanceController::class);
    Route::get('avances/{projet_id}', [AvanceController::class,'index'])->name('avances');
    Route::delete('destoryUsingReservationId/{reservation_id}',[AvanceController::class,'destoryUsingReservationId'])->name('destoryUsingReservationId');
    Route::put('valideAvance/{id}',[AvanceController::class,'valideAvance'])->name('valideAvance');
    Route::put('refuseAvance/{id}',[AvanceController::class,'refuseAvance'])->name('refuseAvance');
    Route::get('getAvances_by_Reservation/{reservation_id}', [AvanceController::class,'getAvances_by_Reservation'])->name('getAvances_by_Reservation');

    /*************************************PiecesJointe***************************** */
    Route::resource('piecesjointe',PiecesJointeController::class);
    Route::get('piecesjointes/{projet_id}', [PiecesJointeController::class,'index'])->name('piecesjointes');
    Route::delete('destoryFileUsingReservationId/{reservation_id}',[PiecesJointeController::class,'destoryFileUsingReservationId'])->name('destoryFileUsingReservationId');
    Route::get('getFileUsingReservationId/{reservation_id}',[PiecesJointeController::class,'getFileUsingReservationId'])->name('getFileUsingReservationId');

    /*************************************Reservation***************************** */
    Route::resource('reservation',ReservationController::class);
    Route::get('reservations/{projet_id}', [ReservationController::class,'index'])->name('reservations');
    Route::get('getAllInformationsReservation/{id}',[ReservationController::class,'getAllInformationsReservation'])->name('getAllInformationsReservation');
    Route::get('getReservationssByProjet/{id}',[ReservationController::class,'getReservationssByProjet'])->name('getReservationssByProjet');
    Route::get('get_typologiesByProjet/{id}', [TypologieController::class, 'get_typologiesByProjet'])->name('get_typologiesByProjet');
    Route::get('typologies/{projet_id}', [TypologieController::class,'index'])->name('typologies');
    Route::get('getreservation_by_client/{client_id}',[ReservationController::class, 'getreservation_by_client'])->name('getreservation_by_client');

    /*************************************EnumController***************************** */
    Route::get('Enums', [EnumController::class,'get_enums'])->name('');
    Route::get('InteretEnum', [EnumController::class,'InteretEnum_get'])->name('');
    Route::get('OrientationEnum', [EnumController::class,'OrientationEnum_get'])->name('');
    Route::get('TypeNotificationEnum', [EnumController::class,'TypeNotificationEnum_get'])->name('');
    Route::get('StatutVisiteEnum', [EnumController::class,'StatutVisiteEnum_get'])->name('');
    /************************NotificationController********************* */
    Route::get('get_relances_visites/{projet_id}', [NotificationController::class,'get_relances_visites'])->name('');
    Route::get('get_rdv_visites/{projet_id}', [NotificationController::class,'get_rdv_visites'])->name('');
    Route::get('get_relances_menu/{projet_id}', [NotificationController::class,'get_relances_menu'])->name('');
    Route::get('get_relances_visites/{projet_id}', [NotificationController::class,'get_relances_visites'])->name('');
    Route::get('get_notifications/{projet_id}', [NotificationController::class,'get_notifications'])->name('');
    Route::get('DestroyNotif/{id}', [NotificationController::class,'DestroyNotif'])->name('');
    Route::get('notifications/{projet_id}', [NotificationController::class,'index'])->name('');


});
Route::get('sendResetPasswordEmail', [UserController::class, 'sendResetPasswordEmail']);
