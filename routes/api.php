<?php

use App\Http\Controllers\Api\V1\BanqueController as V1BanqueController;
use App\Http\Controllers\Api\V1\BienController as V1BienController;
use App\Http\Controllers\Api\V1\BlocController as V1BlocController;
use App\Http\Controllers\Api\V1\CompositionBienController as V1CompositionBienController;
use App\Http\Controllers\Api\V1\ImmeubleController as V1ImmeubleController;
use App\Http\Controllers\Api\V1\PartenaireController as V1PartenaireController;
use App\Http\Controllers\Api\V1\ProjetController as V1ProjetController;
use App\Http\Controllers\Api\V1\SocieteController as V1SocieteController;
use App\Http\Controllers\Api\V1\SourceController as V1SourceController;
use App\Http\Controllers\Api\V1\TrancheController as V1TrancheController;
use App\Http\Controllers\Api\V1\TypeBienController as V1TypeBienController;
use App\Http\Controllers\Api\V1\TypeFreinController as V1TypeFreinController;
use App\Http\Controllers\Api\V1\TypeProjetController as V1TypeProjetController;
use App\Http\Controllers\Api\V1\TypologieController as V1TypologieController;
use App\Http\Controllers\Api\V1\UserController as V1UserController;
use App\Http\Controllers\Api\V1\VueController as V1VueController;
use App\Http\Controllers\Api\V1\VisiteController as V1VisiteController;
use App\Http\Controllers\Api\V1\ProspectController as V1ProspectController;
use App\Http\Controllers\Api\V1\ClientController as V1ClientController;
use App\Http\Controllers\Api\V1\AquereurController as V1AquereurController;
use App\Http\Controllers\Api\V1\ReservationController as V1ReservationController;
use App\Http\Controllers\Api\V1\AquereurController as V1AvanceController;
use App\Http\Controllers\Api\V1\AppelController as V1AppelController;
use App\Http\Controllers\Api\V1\EnumController as V1EnumController;
use App\Http\Controllers\AquereurController;
use App\Http\Controllers\AvanceController;
use App\Http\Controllers\BanqueController;
use App\Http\Controllers\BienController;
use App\Http\Controllers\BlocController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompositionBienController;
use App\Http\Controllers\DesistementController;
use App\Http\Controllers\EnumController;
use App\Http\Controllers\ExcelDataController;
use App\Http\Controllers\Facebook\FacebookController;
use App\Http\Controllers\FreinController;
use App\Http\Controllers\ImmeubleController;
use App\Http\Controllers\Landing_page\Landing_pageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartenaireController;
use App\Http\Controllers\PiecesJointeController;
use App\Http\Controllers\ProjetController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\RemboursementController;
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
use App\Http\Controllers\VueController;
use App\Http\Controllers\ActualiteController;
use App\Http\Controllers\WhatsApp\WhatsAppController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LivraisonController;


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

Route::post('login', [UserController::class, 'login'])->name('login');
Route::post('/validateToken/{token}', [UserController::class, 'validateToken']);

/*************************************APIs FROM Outside ***************************** */

Route::post('handlemessage', [FacebookController::class, 'handleMessage']);
Route::get('get_pivacy_policy', [FacebookController::class, 'get_pivacy_policy']);
Route::post('/webhooks', [WhatsAppController::class, 'webhooks']);
Route::post('/send_landing_page', [Landing_pageController::class, 'send_landing_page']);

Route::middleware('auth:api')->group(function () {
    //Il est nécessaire de versionner l'API pour garantir son évolutivité et une gestion efficace des modifications futures.
    Route::prefix('v1')->group(function () {
        // Routes de la version numero 1
        // l'API utilisateurs
        Route::resource('/utilisateurs', V1UserController::class);
        Route::put('activateUser/{id}', [V1UserController::class, 'activateUser'])->name('activateUser');
        Route::put('desactivateUser/{id}', [V1UserController::class, 'desactivateUser'])->name('desactivateUser');

        // l'API societes
        Route::resource('societes', V1SocieteController::class);
        // l'API typeProjets
        Route::resource('typeProjets', V1TypeProjetController::class);
        // l'API typeBiens
        Route::resource('typeBiens', V1TypeBienController::class);
        Route::get('projets/{idprojet}/typeBiens', [V1TypeBienController::class, 'indexByProjet']);
        Route::get('get_typeBiensByProjet/{id}', [V1TypeBienController::class, 'get_typeBiensByProjet'])->name('');

        //l'API banques
        Route::resource('banques', V1BanqueController::class);
        //l'API VUES
        Route::resource('vues', V1VueController::class);
        Route::get('projets/{idprojet}/vues', [V1VueController::class, 'indexByProjet']);
        Route::get('get_vuesByProjet/{id}', [V1VueController::class, 'get_vuesByProjet'])->name('get_vuesByProjet');

        //l'API Typologie
        Route::resource('typologies', V1TypologieController::class);
        Route::get('projets/{idprojet}/typologies', [V1TypologieController::class, 'indexByProjet']);
        Route::get('get_typologiesByProjet/{id}', [V1TypologieController::class, 'get_typologiesByProjet'])->name('get_typologiesByProjet');

        //l'API Typefrins
        Route::resource('typefreins', V1TypeFreinController::class);
        Route::get('get_typeFreins', [V1TypeFreinController::class, 'get_typeFreins'])->name('');

        //l'API source
        Route::resource('sources', V1SourceController::class);
        Route::get('get_sources', [V1SourceController::class, 'get_sources'])->name('get_sources');

        //l'API partenaire
        Route::resource('partenaires', V1PartenaireController::class);
        Route::get('projets/{idprojet}/partenaires', [V1PartenaireController::class, 'indexByProjet']);
        Route::get('get_partenaires/{projet_id}', [V1PartenaireController::class, 'get_partenaires'])->name('get_partenaires');

        //l'API partenare
        Route::resource('projets', V1ProjetController::class);

        //l'API tranches
        Route::resource('tranches', V1TrancheController::class);
        Route::get('projets/{idprojet}/tranches', [V1TrancheController::class, 'indexByProjet']);

        //l'API blocs
        Route::resource('blocs', V1BlocController::class);
        Route::get('projets/{idprojet}/blocs', [V1BlocController::class, 'indexByProjet']);

        //l'API immeubles
        Route::resource('immeubles', V1ImmeubleController::class);
        Route::get('projets/{idprojet}/immeubles', [V1ImmeubleController::class, 'indexByProjet']);

        //l'API biens
        Route::resource('biens', V1BienController::class);
        Route::get('projets/{idprojet}/biens', [V1BienController::class, 'indexByProjet']);
        Route::get('getBiensByProjet_Concat/{id}', [V1BienController::class, 'getBiensByProjet_Concat'])->name('getBiensByProjet_Concat');
        Route::delete('libererBien/{id}', [V1BienController::class, 'libererBien_function'])->name('libererBien');
        Route::put('setPropostionBien/{id}/{old_id}', [V1BienController::class, 'setPropostionBien'])->name('');
        //l'API compositionbiens
        Route::resource('compositionBiens', V1CompositionBienController::class);

        //l'API visite
        Route::resource('visites', V1VisiteController::class);
        Route::get('projets/{idprojet}/visites', [V1VisiteController::class, 'indexByProjet']);
        Route::put('update_visite_bien_pre_reserve/{origin_id}', [V1VisiteController::class, 'update_visite_bien_pre_reserve'])->name('');
        Route::post('store_n_visite/{id}', [V1VisiteController::class, 'store_n_visite'])->name('store_n_visite');
        Route::get('get_oldBien_visite_pre_reserve/{origin_id}', [V1VisiteController::class, 'get_oldBien_visite_pre_reserve'])->name('');

        //l'API prospect
        Route::resource('prospects', V1ProspectController::class);
        Route::get('search_prospect_by_param/{param_1}/{value}', [V1ProspectController::class, 'search_prospect_by_param']);
       //l'API client
        Route::resource('clients', V1ClientController::class);
        //l'API Aquerreur
        Route::resource('aquereurs', V1AquereurController::class);
        //l'API Avance
        Route::resource('avances', V1AvanceController::class);
        //lapi reservaton
        Route::resource('reservations', V1ReservationController::class);
        Route::get('search_reservation_by_code/{code_res}', [V1ReservationController::class, 'search_reservation_by_code']);

        //l'Api relationClients
        Route::resource('appels', V1AppelController::class);
        Route::get('projets/{idprojet}/appels', [V1AppelController::class, 'indexByProjet']);
        Route::get('show_t_appel/{id}', [V1AppelController::class, 'show_t_appel']);
        Route::get('index_traitement_appel/{id}', [V1AppelController::class, 'index_traitement_appel']);
        Route::delete('destroy_t_appel/{id}', [V1AppelController::class, 'destroy_t_appel'])->name('');
        Route::put('traiter_relance_rdv_appel/{id}', [V1AppelController::class, 'traiter_relance_rdv_appel'])->name('');
        Route::get('get_info_cin_unique/{prospect_id}/{cin}', [V1AppelController::class, 'get_info_cin_unique']);


        //Enumeartion
        Route::get('InteretEnum_appel', [V1EnumController::class, 'InteretEnum__appel_get'])->name('');



    });

    Route::post('upload-excel-data', [ExcelDataController::class, 'UploadDataExcel'])->name('upload-excel-data');
    Route::post('testfunction', [ExcelDataController::class, 'testfunction'])->name('upload-excel-data');

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
    Route::post('restoreUser/{id}', [UserController::class, 'restoreUser'])->name('restoreUser');
    Route::get('getTrashedUsers', [UserController::class, 'getTrashedUsers'])->name('getTrashedUsers');
    Route::get('dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::get('getTrashedUsersBySociete/{id}', [UserController::class, 'getTrashedUsersBySociete'])->name('getTrashedUsersBySociete');
    Route::post('logout', [UserController::class, 'logout'])->name('logout');
    Route::post('addUserProjet/{id}', [UserController::class, 'addUserProjet'])->name('addUserProjet');
    Route::get('get_users', [UserController::class, 'get_users'])->name('get_users');
    Route::get('get_commerciaux', [UserController::class, 'get_commerciaux'])->name('get_users');
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
    Route::get('biens/{projet_id}', [BienController::class, 'index'])->name('biens');
    Route::get('biensProposition/{projet_id}', [BienController::class, 'biens_proposition'])->name('');
    Route::post('restoreBien/{id}', [BienController::class, 'restoreBien'])->name('restoreBien');
    Route::get('getTrashedBiens', [BienController::class, 'getTrashedBiens'])->name('getTrashedBiens');
    Route::put('bloquerBien/{id}', [BienController::class, 'bloquerBien'])->name('bloquerBien');
    //Route::put('reserverBien/{id}', [BienController::class, 'reserverBien'])->name('reserverBienff');
    Route::put('prereserverBien/{id}/{visite_id}/{appel_id}', [BienController::class, 'prereserverBien'])->name('prereserverBien');
    Route::delete('libererBien/{id}', [BienController::class, 'libererBien_function'])->name('libererBien');
    Route::get('getHistoriqueBien/{id}', [BienController::class, 'getHistoriqueBien'])->name('getHistoriqueBien');
    Route::resource('compositionBien', CompositionBienController::class);
    Route::get('compositionBiens/{bien_id}', [CompositionBienController::class, 'index'])->name('compositionBiens');
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
    Route::get('TypeBiens/{projet_id}', [TypeBienController::class, 'index'])->name('TypeBiens');

    /*************************************Visite***************************** */
    Route::resource('visite', VisiteController::class);
    Route::get('visites/{projet_id}', [VisiteController::class, 'index'])->name('visites');
    Route::get('relance_rdv_by_visite/{id}', [VisiteController::class, 'relance_rdv_by_visite'])->name('');
    Route::post('store_n_visite/{id}', [VisiteController::class, 'store_n_visite'])->name('store_n_visite');
    Route::get('getAllAttributes', [VisiteController::class, 'getAllAttributes'])->name('getAllAttributes');
    Route::get('get_historiques_visite/{origin_id}', [VisiteController::class, 'get_historiques'])->name('get_historiques');
    Route::put('traiter_relance_rdv_visite/{id}', [VisiteController::class, 'traiter_relance_rdv_visite'])->name('');
    Route::get('get_oldBien_visite_pre_reserve/{origin_id}', [VisiteController::class, 'get_oldBien_visite_pre_reserve'])->name('');
    Route::put('update_visite_bien_pre_reserve/{origin_id}', [VisiteController::class, 'update_visite_bien_pre_reserve'])->name('');

    /*************************************type_Freins***************************** */
    Route::resource('type_freins', TypeFreinController::class);
    Route::get('get_typeFreins', [TypeFreinController::class, 'get_typeFreins'])->name('get_typeFreins');
    Route::post('restoreTypeFrein/{id}', [TypeFreinController::class, 'restoreTypeFrein'])->name('restoreTypeFrein');

    /*************************************Prospect***************************** */
    /*************************************Frein***************************** */
    Route::resource('frein', FreinController::class);
    Route::get('get_clients_freins/{projet_id}', [FreinController::class, 'get_clients_freins'])->name('');
    Route::get('biens_by_frein/{id}', [FreinController::class, 'biens_by_frein'])->name('');
    Route::put('traiter_bien_frein/{bien_id}/{frein_id}', [FreinController::class, 'traiter_bien_frein'])->name('');
    /*************************************Prospect***************************** */
    Route::resource('prospect', ProspectController::class);
    Route::get('search_prospect_by_email/{email}', [ProspectController::class, 'search_prospect_by_email']);
    Route::get('search_prospect_by_cin/{cin}', [ProspectController::class, 'search_prospect_by_cin']);
    Route::get('search_prospect_by_phone/{phone}', [ProspectController::class, 'search_prospect_by_phone']);
    Route::get('get_prospects', [ProspectController::class, 'get_prospects']);
    // Route::post('Store_WhatsApp', [ProspectController::class, 'Store_WhatsApp']);
    Route::get('VisitesByprospect/{prospect_id}', [ProspectController::class, 'VisitesByprospect']);

    /*************************************Source***************************** */
    Route::resource('sources', SourceController::class);
    Route::get('get_sources', [SourceController::class, 'get_sources'])->name('get_sources');

    /*************************************Vue***************************** */
    Route::resource('vue', VueController::class);
    Route::get('get_vuesByProjet/{id}', [VueController::class, 'get_vuesByProjet'])->name('get_vuesByProjet');
    Route::get('vues/{projet_id}', [VueController::class, 'index'])->name('vues');

    /*************************************Partenaires***************************** */
    Route::resource('partenaire', PartenaireController::class);
    Route::get('partenaires/{projet_id}', [PartenaireController::class, 'index'])->name('');
    Route::get('get_partenaires/{projet_id}', [PartenaireController::class, 'get_partenaires'])->name('get_partenaires');

    /*************************************Typologie***************************** */
    Route::resource('typologie', TypologieController::class);
    Route::get('get_typologiesByProjet/{id}', [TypologieController::class, 'get_typologiesByProjet'])->name('get_typologiesByProjet');
    Route::get('typologies/{projet_id}', [TypologieController::class, 'index'])->name('typologies');
    /*************************************Banque***************************** */
    Route::resource('banque', BanqueController::class);
    Route::get('get_banques', [BanqueController::class, 'get_banques'])->name('get_banques');

    /*************************************Client***************************** */
    Route::resource('client', ClientController::class);
    Route::get('get_clients', [ClientController::class, 'get_clients'])->name('get_clients');
    Route::get('getClient_by_projet/{projet_id}', [ClientController::class, 'getClient_by_projet'])->name('getClient_by_projet');

    Route::get('search_client_by_cin/{cin}', [ClientController::class, 'search_client_by_cin']);
    Route::get('search_client_by_phone/{phone}', [ClientController::class, 'search_client_by_phone']);
    Route::get('ReservationsByClient/{client_id}', [ClientController::class, 'ReservationsByClient']);
    Route::get('VisitesByClient/{client_id}', [ClientController::class, 'VisitesByClient']);

    /*************************************Aquereurs***************************** */
    Route::resource('aquereur', AquereurController::class);
    Route::get('aquereurs/{projet_id}', [AquereurController::class, 'index'])->name('aquereurs');
    Route::delete('destoryAquereurUsingReservationId/{reservation_id}', [AquereurController::class, 'destroyAquerreursByReservationId'])->name('destoryAquereurUsingReservationId');
    Route::get('getAquereur_by_Reservation/{reservation_id}', [AquereurController::class, 'getAquereur_by_Reservation'])->name('getAquereur_by_Reservation');

    /*************************************Avances***************************** */
    Route::resource('avance', AvanceController::class);
    Route::get('avances/{projet_id}', [AvanceController::class, 'index'])->name('avances');
    Route::delete('destoryUsingReservationId/{reservation_id}', [AvanceController::class, 'destoryUsingReservationId'])->name('destoryUsingReservationId');
    Route::put('valideAvance/{id}', [AvanceController::class, 'valideAvance'])->name('valideAvance');
    Route::put('refuseAvance/{id}', [AvanceController::class, 'refuseAvance'])->name('refuseAvance');
    Route::get('getAvances_by_Reservation/{reservation_id}', [AvanceController::class, 'getAvances_by_Reservation'])->name('getAvances_by_Reservation');
    Route::get('historiques_avance/{date}/{id}', [AvanceController::class, 'historiques_avance'])->name('');
    Route::get('get_notif_avances_att_validation/{projet_id}', [AvanceController::class, 'get_notif_avances_att_validation'])->name('');
    Route::get('avances_by_etat/{projet_id}/{etat}', [AvanceController::class, 'get_avances_by_etat'])->name('');
    Route::put('traiter_avance/{id}', [AvanceController::class, 'traiter_avance'])->name('');
    Route::get('avances_rejets/{projet_id}', [AvanceController::class, 'get_avances_rejets'])->name('');
    Route::get('get_echeances/{projet_id}', [AvanceController::class, 'get_echeances'])->name('');
    Route::get('get_echeances_menu/{projet_id}', [AvanceController::class, 'get_echeances_menu'])->name('');

    /*************************************PiecesJointe***************************** */
    Route::resource('piecesjointe', PiecesJointeController::class);
    Route::get('piecesjointes/{projet_id}', [PiecesJointeController::class, 'index'])->name('piecesjointes');
    Route::delete('destoryFileUsingReservationId/{reservation_id}', [PiecesJointeController::class, 'destoryFileUsingReservationId'])->name('destoryFileUsingReservationId');
    Route::get('getFileUsingReservationId/{reservation_id}', [PiecesJointeController::class, 'getFileUsingReservationId'])->name('getFileUsingReservationId');
    Route::post('scanner_file', [PiecesJointeController::class, 'scanner_file'])->name('scanner_file');
    Route::get('files_docs/{docs}', [PiecesJointeController::class, 'files_docs'])->name('files_docs');

    /*************************************Reservation***************************** */
    Route::resource('reservation', ReservationController::class);
    Route::get('info_reservation/{projet_id}', [ReservationController::class, 'info_reservation'])->name('');
    Route::get('reservations/{projet_id}', [ReservationController::class, 'index'])->name('reservations');
    Route::get('getAllInformationsReservation/{id}', [ReservationController::class, 'getAllInformationsReservation'])->name('getAllInformationsReservation');
    Route::get('getReservationssByProjet/{id}', [ReservationController::class, 'getReservationssByProjet'])->name('getReservationssByProjet');
    Route::get('get_Historiques_by_reservation/{id}', [ReservationController::class, 'get_Historiques_by_reservation'])->name('');
    Route::get('getDossiers/{projet_id}/{dos_id}', [ReservationController::class, 'get_dossiers'])->name('');
    Route::get('search_reservation_by_code/{code_res}', [ReservationController::class, 'search_reservation_by_code']);
    Route::get('reservations_by_etat/{projet_id}/{etat}', [ReservationController::class, 'get_reservations_by_etat'])->name('');
    Route::put('traiter_reservation/{id}', [ReservationController::class, 'traiter_reservation'])->name('');
    Route::get('get_notif_reservation_att_validation/{projet_id}', [ReservationController::class, 'get_notif_reservation_att_validation'])->name('');
    Route::get('reservations_rejets/{projet_id}', [ReservationController::class, 'get_reservations_rejets'])->name('');
    Route::get('relancer_reservation/{id}', [ReservationController::class, 'relancer_reservation'])->name('');

    /******************************Typologie **********************/
    Route::get('get_typologiesByProjet/{id}', [TypologieController::class, 'get_typologiesByProjet'])->name('get_typologiesByProjet');
    Route::get('typologies/{projet_id}', [TypologieController::class, 'index'])->name('typologies');

    /*************************************EnumController***************************** */
    Route::get('Enums', [EnumController::class, 'get_enums'])->name('');
    Route::get('InteretEnum', [EnumController::class, 'InteretEnum_get'])->name('');
    Route::get('OrientationEnum', [EnumController::class, 'OrientationEnum_get'])->name('');
    Route::get('TypeNotificationEnum', [EnumController::class, 'TypeNotificationEnum_get'])->name('');
    Route::get('StatutVisiteEnum', [EnumController::class, 'StatutVisiteEnum_get'])->name('');
    Route::get('Mode_finance_Enum', [EnumController::class, 'ModefinanceEnum_get'])->name('');
    Route::get('Mode_paiement_Enum', [EnumController::class, 'ModePaiementEnum_get'])->name('');
    Route::get('StatutReservationEnum', [EnumController::class, 'StatutReservationEnum_get'])->name('');
    Route::get('TypesClient_Enum', [EnumController::class, 'TypesClientEnum_get'])->name('');
    Route::get('Civilite_Enum', [EnumController::class, 'CiviliteEnum_get'])->name('');
    Route::get('StatutFamilleEnum', [EnumController::class, 'StatutFamilleEnum_get'])->name('');
    Route::get('EtatBien', [EnumController::class, 'EtatBien_get'])->name('');
    Route::get('Enums_desistements', [EnumController::class, 'get_enums_desistements'])->name('');
    Route::get('StatutRdv_Enum', [EnumController::class, 'StatutRdvEnum_get'])->name('');


    /************************NotificationController********************* */
    Route::get('get_relances_visites/{projet_id}', [NotificationController::class, 'get_relances_visites'])->name('');
    Route::get('get_nb_relances_visites/{projet_id}', [NotificationController::class, 'get_nb_relances_visites'])->name('');
    Route::get('get_rdv_visites/{projet_id}', [NotificationController::class, 'get_rdv_visites'])->name('');
    Route::get('get_nb_rdv_visites/{projet_id}', [NotificationController::class, 'get_nb_rdv_visites'])->name('');
    Route::get('get_nb_frein_client_visite/{projet_id}', [NotificationController::class, 'get_nb_frein_client_visite'])->name('');

    Route::get('notifications_menu_horizontal_crm/{projet_id}', [NotificationController::class, 'get_notifications_menu_horizontal_crm'])->name('');
    Route::get('get_notifications/{projet_id}', [NotificationController::class, 'get_notifications'])->name('');
    Route::get('DestroyNotif/{id}', [NotificationController::class, 'DestroyNotif'])->name('');
    Route::get('notifications/{projet_id}', [NotificationController::class, 'index'])->name('');
    Route::get('get_notif_rejete_commercial/{projet_id}', [NotificationController::class, 'get_notif_rejete_commercial'])->name('');
    Route::get('notifications_menu_horizontal_vente_admin/{projet_id}', [NotificationController::class, 'get_notif_menu_horizontal_vente_admin'])->name('');
    Route::get('notifications_menu_horizontal_vente_commercial/{projet_id}', [NotificationController::class, 'get_notif_menu_horizontal_vente_comm'])->name('');

    /********************************DesistemenController*********** */
    Route::resource('desistement', DesistementController::class);
    Route::get('get_historiques_desistement_by_reservation/{code_desistement}', [DesistementController::class, 'get_historiques_desistement_by_reservation'])->name('');
    Route::put('validation_desistement/{id}', [DesistementController::class, 'validation_desitement'])->name('');
    Route::get('get_notif_dst_commercial/{projet_id}', [DesistementController::class, 'get_notif_dst_commercial'])->name('');
    Route::get('get_notif_dst_admin/{projet_id}', [DesistementController::class, 'get_notif_dst_admin'])->name('');
    Route::get('get_notif_dst_att_validation_par_type/{projet_id}', [DesistementController::class, 'get_notif_dst_att_validation_par_type'])->name('');
    Route::get('get_desistements/{projet_id}/{type}/{etat}', [DesistementController::class, 'get_desistements'])->name('');
    Route::post('desistement/corriger_desistement', [DesistementController::class, 'store'])->name('');
    Route::get('get_dossiers_by_bien/{bien_id}', [DesistementController::class, 'get_dossiers_by_bien'])->name('');

    //penalites
    Route::get('penalites/{projet_id}/{etat}', [DesistementController::class, 'get_all_penalites'])->name('');
    Route::put('traiter_penalite/{id}', [DesistementController::class, 'traiter_penalite'])->name('');
    Route::get('show_penalite/{id}', [DesistementController::class, 'show_penalite'])->name('');
    Route::post('penalites/corriger_penalite', [DesistementController::class, 'corriger_penalite'])->name('');
    Route::get('get_notif_penalite_admin/{projet_id}', [DesistementController::class, 'get_notif_pen_admin'])->name('');
    Route::get('get_notif_penalite_commercial/{projet_id}', [DesistementController::class, 'get_notif_pen_commercial'])->name('');
    Route::get('get_historiques_penalites/{desistement_id}', [DesistementController::class, 'get_historiques_penalites_by_desId'])->name('');

    /******************************************* */

    Route::resource('remboursement', RemboursementController::class);
    Route::get('get_remboursements/{projet_id}/{etat}', [RemboursementController::class, 'index'])->name('');
    Route::get('get_detail_transfert/{reservation_id}', [RemboursementController::class, 'get_detail_transfert'])->name('');
    Route::post('traiter_demande_pre_rembourse/{id}', [RemboursementController::class, 'traiter_demande_pre_rembourse'])->name('');
    Route::get('get_notif_demande_pre_remboursement/{projet_id}', [RemboursementController::class, 'get_notif_demande_pre_remboursement'])->name('');
    Route::post('traiter_accuse/{id}', [RemboursementController::class, 'traiter_accuse'])->name('');
    Route::post('traiter_decaissement/{id}', [RemboursementController::class, 'traiter_decaissement'])->name('');
    Route::get('get_remboursements_dos_transfert/{projet_id}', [RemboursementController::class, 'get_remboursements_dos_transfert'])->name('');

     /*************************************Actualites***************************** */
     Route::get('historiques/{date}/{id}/{type}', [ActualiteController::class, 'get_historique'])->name('');
     Route::get('actualites/{projet_id}/{user_id}/{de_date}/{a_date}', [ActualiteController::class, 'index'])->name('');
     /***********************************Livraison*******************/
                /*******rdv notaire*** */

     Route::get('get_rdvs_reservation/{res_id}', [LivraisonController::class, 'get_rdvs_reservation'])->name('');
     Route::put('update_rdv_reservation/{rdv_id}', [LivraisonController::class, 'update_rdv_reservation'])->name('');
     Route::post('store_rdv_reservation/{rdv_id}', [LivraisonController::class, 'store_rdv_reservation'])->name('');
     Route::put('traiter_rdv_reservation/{rdv_id}', [LivraisonController::class, 'traiter_rdv_reservation'])->name('');
     Route::delete('destroy_rdv_reservation/{id}', [LivraisonController::class, 'destroy_rdv_reservation'])->name('');
     Route::get('get_rdv_notaire_menu/{projet_id}', [LivraisonController::class, 'get_rdv_notaire_menu'])->name('');

            /************compromis vente******/

    Route::post('store_compromis_vente/{rdv_id}', [LivraisonController::class, 'store_compromis_vente'])->name('');
    Route::get('show_compromis/{id}', [LivraisonController::class, 'show_compromis'])->name('');
    Route::put('update_compromis/{comp_id}', [LivraisonController::class, 'update_compromis'])->name('');
    Route::get('print_compromis/{id}', [LivraisonController::class, 'print_compromis'])->name('');
    Route::get('get_compromis_by_reservation/{id}', [LivraisonController::class, 'get_compromis_by_reservation'])->name('');
    Route::get('get_compromis_annules_by_reservation/{id}', [LivraisonController::class, 'get_compromis_annules_by_reservation'])->name('');
    Route::post('scanner_compromis', [LivraisonController::class, 'scanner_compromis'])->name('scanner_compromis');

          /****************************Contrat de vente*****************/

    Route::get('get_contrat_by_reservation/{id}', [LivraisonController::class, 'get_contrat_by_reservation'])->name('');
    Route::post('store_contrat_vente/{rdv_id}', [LivraisonController::class, 'store_contrat_vente'])->name('');
    Route::get('show_contrat/{id}', [LivraisonController::class, 'show_contrat'])->name('');
    Route::put('update_contrat/{cont_id}', [LivraisonController::class, 'update_contrat'])->name('');
    Route::post('scanner_contrat', [LivraisonController::class, 'scanner_contrat'])->name('');

    });
Route::get('sendResetPasswordEmail', [UserController::class, 'sendResetPasswordEmail']);
