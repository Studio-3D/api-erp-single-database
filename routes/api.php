<?php

use App\Http\Controllers\Api\V1\ActualiteController as V1ActualiteController;
use App\Http\Controllers\Api\V1\AppelController as V1AppelController;
use App\Http\Controllers\Api\V1\AquereurController as V1AquereurController;
use App\Http\Controllers\Api\V1\AvanceController as V1AvanceController;
use App\Http\Controllers\Api\V1\BanqueController as V1BanqueController;
use App\Http\Controllers\Api\V1\BienController as V1BienController;
use App\Http\Controllers\Api\V1\BlocController as V1BlocController;
use App\Http\Controllers\Api\V1\ClientController as V1ClientController;
use App\Http\Controllers\Api\V1\CommissionController as V1CommissionController;
use App\Http\Controllers\Api\V1\CompositionBienController as V1CompositionBienController;
use App\Http\Controllers\Api\V1\ComptabiliteController as V1ComptabiliteController;
use App\Http\Controllers\Api\V1\CpsController as V1CpsController;
use App\Http\Controllers\Api\V1\CreditsController as V1CreditsController;
use App\Http\Controllers\Api\V1\DecompteController as V1DecompteController;
use App\Http\Controllers\Api\V1\DesistementController as V1DesistementController;
use App\Http\Controllers\Api\V1\EcheancesTrancheController as V1EchancesTrancheCleController;
use App\Http\Controllers\Api\V1\EncaissementController as V1EncaissementController;
use App\Http\Controllers\Api\V1\EtapeProjetController as V1EtapeProjetController;
use App\Http\Controllers\Api\V1\FactureController as V1FactureController;
use App\Http\Controllers\Api\V1\FournisseurController as V1FournisseurController;
use App\Http\Controllers\Api\V1\FreinController as V1FreinController;
use App\Http\Controllers\Api\V1\HomeController as V1HomeController;
use App\Http\Controllers\Api\V1\ImmeubleController as V1ImmeubleController;
use App\Http\Controllers\Api\V1\ObjectifController as V1ObjectifsController;
use App\Http\Controllers\Api\V1\PartenaireController as V1PartenaireController;
use App\Http\Controllers\Api\V1\PiecesJointeController as V1PiecesJointeController;
use App\Http\Controllers\Api\V1\PrestatairesController as V1PrestatairesController;
use App\Http\Controllers\Api\V1\ProjetController as V1ProjetController;
use App\Http\Controllers\Api\V1\ProspectController as V1ProspectController;
use App\Http\Controllers\Api\V1\ReclamationController as V1ReclamationsController;
use App\Http\Controllers\Api\V1\ReclamationSavController as V1ReclamationsSavController;
use App\Http\Controllers\Api\V1\RemboursementController as V1RemboursementController;
use App\Http\Controllers\Api\V1\RemiseCleController as V1RemiseCleController;
use App\Http\Controllers\Api\V1\ReservationController as V1ReservationController;
use App\Http\Controllers\Api\V1\ServicesPrestatairesController as V1ServicesPrestatairesController;
use App\Http\Controllers\Api\V1\SocieteController as V1SocieteController;
use App\Http\Controllers\Api\V1\SourceController as V1SourceController;
use App\Http\Controllers\Api\V1\StatistiquesController as V1StatistiquesController;
use App\Http\Controllers\Api\V1\TrancheController as V1TrancheController;
use App\Http\Controllers\Api\V1\TypeBienController as V1TypeBienController;
use App\Http\Controllers\Api\V1\TypeFreinController as V1TypeFreinController;
use App\Http\Controllers\Api\V1\TypeProjetController as V1TypeProjetController;
use App\Http\Controllers\Api\V1\TypologieController as V1TypologieController;
use App\Http\Controllers\Api\V1\UploadBienController as V1UploadBienController;
use App\Http\Controllers\Api\V1\UserController as V1UserController;
use App\Http\Controllers\Api\V1\VisiteController as V1VisiteController;
use App\Http\Controllers\Api\V1\VueController as V1VueController;
use App\Http\Controllers\Api\V1\GestionRolesController as V1GestionRolesController;
use App\Http\Controllers\Api\V1\NotaireController as V1NotaireController;

use App\Http\Controllers\EnumController;
use App\Http\Controllers\Facebook_Instagram\Facebook_InstagramController;
use App\Http\Controllers\Landing_page\Landing_pageController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SocieteController;
use App\Http\Controllers\TikTok\TikTokApiController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsApp\WhatsAppController;
use App\Http\Controllers\LinkedIn\LinkedInController;
use Illuminate\Support\Facades\Route;

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

// Health check endpoint for ALB
Route::get('/', function () {
    return response()->json(['status' => 'ok', 'service' => 'immogestion-api'], 200);
});
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'env' => app()->environment(),
    ]);
});

Route::post('login', [UserController::class, 'login'])->name('login');
Route::post('/validateToken/{token}', [UserController::class, 'validateToken']);
Route::post('/resetPassword/{token}', [V1UserController::class, 'resetPassword']);
Route::post('sendEmail', [V1UserController::class, 'sendEmail']);
Route::post('resendEmail', [V1UserController::class, 'resendEmail']);
Route::post('/resetPassword/{token}', [UserController::class, 'resetPassword']);

/*************************************APIs FROM Outside ***************************** */

Route::post('/webhook_whtsp', [WhatsAppController::class, 'webhook_whtsp']);
Route::post('/webhook_whatsapp_business', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'webhook_whatsapp_business']);
Route::get('/webhook_whatsapp_business', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'webhook_whatsapp_business']);
Route::post('/send_landing_page', [Landing_pageController::class, 'send_landing_page']);
Route::post('/webhookFcb_Insta', [Facebook_InstagramController::class, 'handleWebhook']);
Route::get('/webhookFcb_Insta', [Facebook_InstagramController::class, 'verify']);

Route::middleware('auth:api')->group(function () {
    Route::prefix('v1')->group(function () {
        /********************Gestion Roles*********************/
            Route::resource('gestion_roles', V1GestionRolesController::class);
            Route::get('gestion_roles_actives/{societe_id}',  [V1GestionRolesController::class, 'roles_actives']);

        /********************Social Netxork*********************/
        Route::post('/postTo_Social_Network', [Facebook_InstagramController::class, 'postTo_Social_Network']);
        Route::get('/configurations_social_network', [Facebook_InstagramController::class, 'configurations_social_network']);
        Route::post('store_configurations_social_network', [Facebook_InstagramController::class, 'store_configurations_social_network'])->name('');


        // Facebook configurations by project
        Route::get('/facebook-configurations', [Facebook_InstagramController::class, 'facebook_configurations']);
        Route::post('/facebook-configurations', [Facebook_InstagramController::class, 'store_facebook_configuration']);
        Route::put('/facebook-configurations/{id}', [Facebook_InstagramController::class, 'update_facebook_configuration']);
        Route::delete('/facebook-configurations/{id}', [Facebook_InstagramController::class, 'delete_facebook_configuration']);

        // Facebook webhook configurations
        Route::get('/facebook-webhooks', [Facebook_InstagramController::class, 'facebook_webhook_configurations']);
        Route::post('/facebook-configurations/{id}/webhook', [Facebook_InstagramController::class, 'store_facebook_webhook']);
        Route::delete('/facebook-configurations/{id}/webhook', [Facebook_InstagramController::class, 'delete_facebook_webhook']);

        // Instagram configurations by project
        Route::get('/instagram-configurations', [Facebook_InstagramController::class, 'instagram_configurations']);
        Route::post('/instagram-configurations', [Facebook_InstagramController::class, 'store_instagram_configuration']);
        Route::put('/instagram-configurations/{id}', [Facebook_InstagramController::class, 'update_instagram_configuration']);
        Route::delete('/instagram-configurations/{id}', [Facebook_InstagramController::class, 'delete_instagram_configuration']);

        // Instagram webhook configurations
        Route::get('/instagram-webhooks', [Facebook_InstagramController::class, 'instagram_webhook_configurations']);
        Route::post('/instagram-configurations/{id}/webhook', [Facebook_InstagramController::class, 'store_instagram_webhook']);
        Route::delete('/instagram-configurations/{id}/webhook', [Facebook_InstagramController::class, 'delete_instagram_webhook']);

        // WhatsApp Business configurations by project
        Route::get('/whatsapp-configurations', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'get_whatsapp_configurations']);
        Route::post('/whatsapp-configurations', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'store_whatsapp_configuration']);
        Route::put('/whatsapp-configurations/{id}', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'update_whatsapp_configuration']);
        Route::delete('/whatsapp-configurations/{id}', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'delete_whatsapp_configuration']);

        // WhatsApp Business webhook configurations
        Route::get('/whatsapp-webhooks', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'get_whatsapp_webhooks']);
        Route::post('/whatsapp-configurations/{id}/webhook', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'store_whatsapp_webhook_configuration']);
        Route::delete('/whatsapp-configurations/{id}/webhook', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'delete_whatsapp_webhook']);
        Route::put('/whatsapp-configurations/{id}/webhook/toggle', [\App\Http\Controllers\WhatsApp\WhatsAppBusinessController::class, 'toggle_whatsapp_webhook']);

        // Webhook configuration routes
        Route::get('/webhook_configuration', [Facebook_InstagramController::class, 'webhook_configuration']);
        Route::post('/store_webhook_configuration', [Facebook_InstagramController::class, 'store_webhook_configuration']);
        Route::post('/test_webhook_verification', [Facebook_InstagramController::class, 'test_webhook_verification']);


        // Routes de la version numero 1
        // l'API utilisateurs
        Route::resource('/utilisateurs', V1UserController::class);
        Route::put('activateUser/{id}', [V1UserController::class, 'activateUser'])->name('activateUser');
        Route::put('desactivateUser/{id}', [V1UserController::class, 'desactivateUser'])->name('desactivateUser');
        Route::get('commerciaux_objectif/{projet_id}', [V1UserController::class, 'list_commerciaux_objectif'])->name('');
        Route::get('commerciaux/{projet_id}', [V1UserController::class, 'list_commerciaux'])->name('');
        Route::get('get_commerciaux/{projet_id}', [V1UserController::class, 'get_commerciaux'])->name('get_commerciaux');
        Route::post('/utilisateurs/{id}', [V1UserController::class, 'update']);
        Route::put('/update_personal_info/{id}', [V1UserController::class, 'update_personal_info']);
        Route::put('/update_password/{id}', [V1UserController::class, 'update_password']);

        // l'API societes
        Route::resource('societes', V1SocieteController::class);
        // l'API typeProjets
        Route::resource('typeProjets', V1TypeProjetController::class);
        // l'API typeBiens
        Route::resource('typeBiens', V1TypeBienController::class);
        Route::get('projets/{idprojet}/typeBiens', [V1TypeBienController::class, 'indexByProjet']);
        Route::get('get_typeBiensByProjet/{id}', [V1TypeBienController::class, 'get_typeBiensByProjet'])->name('');
        Route::post('store_multiple_type_biens', [V1TypeBienController::class, 'store_multiple_type_biens'])->name('');

        //l'API banques
        Route::resource('banques', V1BanqueController::class);
        //   Route::get('get_banques', [V1BanqueController::class, 'get_banques'])->name('get_banques');

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
        Route::get('get_sources', [V1SourceController::class, 'index'])->name('get_sources');

        //l'API partenaire
        Route::resource('partenaires', V1PartenaireController::class);
        Route::get('projets/{idprojet}/partenaires', [V1PartenaireController::class, 'indexByProjet']);
        Route::get('get_partenaires/{projet_id}', [V1PartenaireController::class, 'get_partenaires'])->name('get_partenaires');

        //l'API partenare
        Route::resource('projets', V1ProjetController::class);
        Route::get('get_projets', [V1ProjetController::class, 'get_projets'])->name('get_projets');
        // Route::get('get_projets_users/{societe_id}/{user_id}', [V1ProjetController::class, 'get_projets_user'])->name('');
        Route::get('get_projets_users/{user_id}', [V1ProjetController::class, 'get_projets_user'])->name('');
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
        Route::get('getBiensByProjet_Concat_for_reservation_visite/{bien_id}/{projet_id}', [V1BienController::class, 'getBiensByProjet_Concat_for_reservation_visite'])->name('');
        Route::delete('libererBien/{id}', [V1BienController::class, 'libererBien_function'])->name('libererBien');
        Route::put('setPropostionBien/{id}/{old_id}', [V1BienController::class, 'setPropostionBien'])->name('');
        Route::get('projets/{idprojet}/getBiensByTranche_tva', [V1BienController::class, 'getBiensByTranche_tva'])->name('');

        // endpoints Media pour biens
        Route::post('biens/{id}/media', [V1BienController::class, 'uploadMedia']);
        Route::get('biens/{id}/media', [V1BienController::class, 'getMedia']);
        Route::delete('biens/{id}/media/{mediaId}', [V1BienController::class, 'deleteMedia']);
        Route::put('biens/{id}/description', [V1BienController::class, 'updateDescription']);

        Route::get('projets/{idprojet}/pre_reservations', [V1BienController::class, 'pre_reservations_index']);
        Route::get('getEtatBien_ByType/{idprojet}/{type_id}', [V1BienController::class, 'getEtatBien_ByType'])->name('getEtatBien_ByType');
        Route::get('/getTotalsStatistique', [V1BienController::class, 'getTotalsStatistique'])->name('getTotalsStatistique');

        //l'API compositionbiens
        Route::resource('compositionBiens', V1CompositionBienController::class);

        //l'API visite
        Route::resource('visites', V1VisiteController::class);
        Route::get('edit_visite/{id}', [V1VisiteController::class, 'edit_visite']);
        Route::get('projets/{idprojet}/visites', [V1VisiteController::class, 'indexByProjet']);
        Route::put('update_visite_bien_pre_reserve/{origin_id}', [V1VisiteController::class, 'update_visite_bien_pre_reserve'])->name('');
        Route::post('store_n_visite/{id}', [V1VisiteController::class, 'store_n_visite'])->name('store_n_visite');
        Route::get('get_oldBien_visite_pre_reserve/{origin_id}', [V1VisiteController::class, 'get_oldBien_visite_pre_reserve'])->name('');
        Route::get('projets/{idprojet}/relances_rdv_visites', [V1VisiteController::class, 'get_relances_rdv_visites'])->name('');
        Route::put('traiter_relance_rdv_visite/{id}', [V1VisiteController::class, 'traiter_relance_rdv_visite'])->name('');
        Route::get('relance_rdv_by_visite/{id}', [V1VisiteController::class, 'relance_rdv_by_visite'])->name('');
        Route::get('get_historiques_visite/{origin_id}', [V1VisiteController::class, 'get_historiques'])->name('get_historiques');

        /****************************Frein*****************************/
        Route::resource('frein', V1FreinController::class);
        Route::get('projets/{idprojet}/get_clients_freins', [V1FreinController::class, 'get_clients_freins'])->name('');
        Route::get('biens_by_frein/{id}', [V1FreinController::class, 'biens_by_frein'])->name('');
        Route::put('traiter_bien_frein/{frein_id}', [V1FreinController::class, 'traiter_bien_frein'])->name('');
        Route::put('desactiver_freins/{id}', [V1FreinController::class, 'desactiver_freins'])->name('');

        //l'API prospect

        Route::resource('prospects', V1ProspectController::class);
        Route::get('projets/{idprojet}/prospects', [V1ProspectController::class, 'indexByProjet']);
        Route::get('search_prospect_by_param/{param_1}/{value}/{projet_id}', [V1ProspectController::class, 'search_prospect_by_param']);
        Route::get('search_prospect_by_cin/{cin}', [V1ProspectController::class, 'search_prospect_by_cin']);
        Route::get('search_prospect_by_phone/{phone}', [V1ProspectController::class, 'search_prospect_by_phone']);
        Route::post('upload_excel_prospect', [V1ProspectController::class, 'upload'])->name('');
        Route::put('traiter_prospect/{id}', [V1ProspectController::class, 'traiter_prospect'])->name('');
        Route::get('historiques_prospects/{id}', [V1ProspectController::class, 'get_Historiques_by_prospect'])->name('');
        Route::get('projets/{idprojet}/prospects', [V1ProspectController::class, 'indexByProjet']);
        Route::post('prospects/auto-assign', [V1ProspectController::class, 'autoAssignProspects'])->name('auto_assign_prospects');
        //l'API client
        Route::get('historiques_clients/{id}', [V1ClientController::class, 'get_Historiques_by_client'])->name('');
        Route::resource('clients', V1ClientController::class);
        Route::get('show_client/{id}', [V1ClientController::class, 'show_client']);
        Route::get('search_client_by_cin/{cin}/{projet_id}', [V1ClientController::class, 'search_client_by_cin']);
        Route::get('search_client_by_phone/{phone}/{projet_id}', [V1ClientController::class, 'search_client_by_phone']);
        Route::get('search_client_by_email/{email}/{projet_id}', [V1ClientController::class, 'search_client_by_email']);
        Route::get('projets/{idprojet}/clients', [V1ClientController::class, 'indexByProjet']);

        //l'API Aquerreur
        Route::resource('aquereurs', V1AquereurController::class);
      //  Route::get('getAquereurByReservation/{reservation_id}', [V1AquereurController::class, 'getAquereurByReservation'])->name('getAquereurByReservation');

        //l'API Avance
        Route::resource('avances', V1AvanceController::class);
        Route::get('getAvancesByReservation/{reservation_id}', [V1AvanceController::class, 'getAvancesByReservation'])->name('getAvancesByReservation');
        Route::get('getAvanceHistory/{id}', [V1AvanceController::class, 'getAvanceHistory'])->name('getAvanceHistory');
        Route::put('traiter_avance/{id}', [V1AvanceController::class, 'traiter_avance'])->name('');
        Route::get('get_notif_avances_att_validation/{projet_id}', [V1AvanceController::class, 'get_notif_avances_att_validation'])->name('');
        Route::get('avances_by_etat/{projet_id}/{etat}', [V1AvanceController::class, 'get_avances_by_etat'])->name('');
        Route::get('get_echeances/{projet_id}', [V1AvanceController::class, 'get_echeances'])->name('');
        Route::get('get_echeances_menu/{projet_id}', [V1AvanceController::class, 'get_echeances_menu'])->name('');

        //Route::get('historiques_avance/{date}/{id}', [AvanceController::class, 'historiques_avance'])->name('');

        //lapi reservaton
        Route::resource('reservations', V1ReservationController::class);
        Route::get('show_dossier_in_dd/{id}', [V1ReservationController::class, 'show_dossier_in_dd']);
        Route::get('projets/{idprojet}/reservations', [V1ReservationController::class, 'indexByProjet']);
        Route::get('search_reservation_by_code/{code_res}', [V1ReservationController::class, 'search_reservation_by_code']);
        Route::get('reservations_by_etat/{projet_id}/{etat}', [V1ReservationController::class, 'get_reservations_by_etat'])->name('');
        Route::get('get_Historiques_by_reservation/{id}', [V1ReservationController::class, 'get_Historiques_by_reservation'])->name('');
        Route::get('info_reservation/{projet_id}', [V1ReservationController::class, 'info_reservation'])->name('');
        Route::get('get_notif_reservation_att_validation/{projet_id}', [V1ReservationController::class, 'get_notif_reservation_att_validation'])->name('');
        Route::put('traiter_reservation/{id}', [V1ReservationController::class, 'traiter_reservation'])->name('');
        Route::get('relancer_reservation/{id}', [V1ReservationController::class, 'relancer_reservation'])->name('');
        Route::get('get_pj_res/{id}', [V1ReservationController::class, 'get_pj_res'])->name('');
        Route::get('getDossiers/{projet_id}/{dos_id}', [v1ReservationController::class, 'get_dossiers'])->name('');
        Route::get('projets/{idprojet}/etat-dossiers', [V1ReservationController::class, 'indexByProjet']);
        Route::get('etat_dossier/{dos_id}', [v1ReservationController::class, 'get_etat_dossier'])->name('');

        //l'api desistement
        Route::resource('desistements', V1DesistementController::class);
        Route::get('projets/{idprojet}/desistements', [V1DesistementController::class, 'indexByProjet']);
        Route::get('penalites/{projet_id}/{etat}', [V1DesistementController::class, 'get_all_penalites'])->name('');
        Route::put('traiter_penalite/{id}', [V1DesistementController::class, 'traiter_penalite'])->name('');
        Route::get('get_notif_penalite_admin/{projet_id}', [V1DesistementController::class, 'get_notif_pen_admin'])->name('');
        Route::get('get_notif_penalite_commercial/{projet_id}', [V1DesistementController::class, 'get_notif_pen_commercial'])->name('');
        Route::get('show_penalite/{id}', [V1DesistementController::class, 'show_penalite'])->name('');
        Route::get('get_historiques_penalites/{desistement_id}', [V1DesistementController::class, 'get_historiques_penalites_by_desId'])->name('');
        Route::post('penalites/corriger_penalite', [V1DesistementController::class, 'corriger_penalite'])->name('');
        Route::put('update_sr_penalite/{id}', [V1DesistementController::class, 'update_sr_penalite'])->name('');
        Route::post('desistement/corriger_desistement', [V1DesistementController::class, 'store'])->name('');
        Route::put('validation_desistement/{id}', [V1DesistementController::class, 'validation_desitement'])->name('');
        Route::get('get_notif_dst_att_validation_par_type/{projet_id}', [V1DesistementController::class, 'get_notif_dst_att_validation_par_type'])->name('');
        Route::get('get_notif_dst_att_validation_menu/{projet_id}', [V1DesistementController::class, 'get_notif_dst_att_validation_menu'])->name('');
        Route::get('get_historiques_desistement_by_reservation/{code_desistement}', [V1DesistementController::class, 'get_historiques_desistement_by_reservation'])->name('');
        Route::get('get_dossiers_by_bien/{bien_id}', [V1DesistementController::class, 'get_dossiers_by_bien'])->name('');
        //l'Api relationClients
        Route::resource('appels', V1AppelController::class);
        Route::get('projets/{idprojet}/appels', [V1AppelController::class, 'indexByProjet']);
        Route::get('show_t_appel/{id}', [V1AppelController::class, 'show_t_appel']);
        Route::get('index_traitement_appel/{id}', [V1AppelController::class, 'index_traitement_appel']);
        Route::delete('destroy_t_appel/{id}/{number}', [V1AppelController::class, 'destroy_t_appel'])->name('');

        Route::put('traiter_relance_rdv_appel/{id}', [V1AppelController::class, 'traiter_relance_rdv_appel'])->name('');
        Route::get('get_info_cin_unique/{prospect_id}/{cin}', [V1AppelController::class, 'get_info_cin_unique']);
        //RELANCES RDV APPELS
        Route::get('projets/{idprojet}/relances_rdv_appels', [V1AppelController::class, 'get_relances_rdv_appels'])->name('');
        Route::get('get_nb_rdv_appels/{projet_id}', [V1AppelController::class, 'get_nb_rdv_appels'])->name('');
        Route::get('get_nb_relances_appels/{projet_id}', [V1AppelController::class, 'get_nb_relances_appels'])->name('');

        //Enumeartion
        //Route::get('InteretEnum_appel', [V1EnumController::class, 'InteretEnum__appel_get'])->name('');

        //Encaissements
        Route::resource('encaissements', V1EncaissementController::class);
        Route::get('projets/{idprojet}/encaissements', [V1EncaissementController::class, 'indexByProjet']);

        //Tva
        Route::put('calculer_tva/{id}', [V1ComptabiliteController::class, 'calculer_tva'])->name('');
        Route::get('get_totaux/{tranche_id}', [V1ComptabiliteController::class, 'get_totaux'])->name('');
        Route::get('projets/{idprojet}/get_tva_collecte_par_bien', [V1ComptabiliteController::class, 'get_tva_collecte_par_bien'])->name('');
        Route::get('projets/{idprojet}/get_tva_collecte_mensuelle', [V1ComptabiliteController::class, 'get_tva_collecte_mensuelle'])->name('');
        Route::put('ajouter_modifier_coeff/{id}', [V1ComptabiliteController::class, 'ajouter_modifier_coeff'])->name('');
        //fournisseurs
        Route::resource('fournisseurs', V1FournisseurController::class);
        Route::get('projets/{idprojet}/fournisseurs', [V1FournisseurController::class, 'indexByProjet']);
        Route::get('get_info_ice_unique/{id}/{ice}', [V1FournisseurController::class, 'get_info_ice_unique']);
        Route::get('projets/{idprojet}/rapports', [V1ComptabiliteController::class, 'get_rapport'])->name('');

        //decomptes
        Route::resource('decomptes', V1DecompteController::class);
        Route::get('projets/{idprojet}/decomptes', [V1DecompteController::class, 'indexByProjet']);
        Route::get('decomptes_in_facture/{projet_id}', [V1DecompteController::class, 'decomptes_in_facture']);
        Route::get('get_info_numero_decompte_unique/{id}/{num}', [V1DecompteController::class, 'get_info_numero_decompte_unique']);

        //factures
        Route::resource('factures', V1FactureController::class);
        Route::get('projets/{idprojet}/factures', [V1FactureController::class, 'indexByProjet']);
        Route::get('get_info_numero_facture_unique/{id}/{num}', [V1FactureController::class, 'get_info_numero_facture_unique']);

        //cps
        Route::resource('cps', V1CpsController::class);
        Route::get('projets/{idprojet}/cps', [V1CpsController::class, 'indexByProjet']);
        //credits
        Route::resource('credits', V1CreditsController::class);
        Route::get('projets/{idprojet}/credits', [V1CreditsController::class, 'indexByProjet']);
        Route::put('update_credit/{id}', [V1CreditsController::class, 'update']);
        Route::get('get_info_numero_credit_unique/{id}/{num}', [V1CreditsController::class, 'get_info_numero_credit_unique']);

        //Statistiques
        Route::get('statistiques_admin/{projet_id}/{de}/{a}', [V1StatistiquesController::class, 'index_admin'])->name('');

        //fullcalendar home
        Route::get('fullcalendar/{projet_id}/{user_id}', [V1HomeController::class, 'fullcalendar'])->name('');
        //objectifs
        Route::resource('objectifs', V1ObjectifsController::class);
        Route::get('projets/{idprojet}/objectifs', [V1ObjectifsController::class, 'indexByProjet']);
        //piecejointe

        Route::get('files_docs_by_code/{docs}/{code}', [V1PiecesJointeController::class, 'files_docs_by_code'])->name('files_docs_by_code');

        Route::get('files_docs/{docs}', [V1PiecesJointeController::class, 'files_docs'])->name('files_docs');
        Route::post('scanner_file', [V1PiecesJointeController::class, 'scanner_file'])->name('scanner_file');

        //sav
        Route::resource('ServicesPrestataires', V1ServicesPrestatairesController::class);
        Route::get('services', [V1ServicesPrestatairesController::class, 'get_services']);
        Route::get('projets/{idprojet}/ServicesPrestataires', [V1ServicesPrestatairesController::class, 'index']);

        Route::resource('/Prestataires', V1PrestatairesController::class);
        Route::get('projets/{idprojet}/Prestataires', [V1PrestatairesController::class, 'index']);
        Route::get('search_prestataire_by_param/{param_1}/{value}', [V1PrestatairesController::class, 'search_prestataire_by_param']);
        Route::get('get_info_cin_prestataire_unique/{prospect_id}/{cin}', [V1PrestatairesController::class, 'get_info_cin_prestataire_unique']);

        //ReclamationsSav
        Route::resource('/ReclamationsSav', V1ReclamationsSavController::class);
        Route::get('projets/{idprojet}/ReclamationsSav', [V1ReclamationsSavController::class, 'indexByProjet']);
        Route::get('getBiens_Vendu_ByProjet_Concat/{id}/{text}', [V1BienController::class, 'getBiens_Vendu_ByProjet_Concat'])->name('');
        Route::post('traiter_reclamation_sav/{id}', [V1ReclamationsSavController::class, 'traiter_reclamation'])->name('');
        Route::post('resoudre_reclamation_sav/{id}', [V1ReclamationsSavController::class, 'resoudre_reclamation'])->name('');

        //Remise Cles
        Route::resource('/RemiseCles', V1RemiseCleController::class);
        Route::get('projets/{idprojet}/RemiseCles', [V1RemiseCleController::class, 'indexByProjet']);

        //ReclamationsClients
        Route::resource('ReclamationsClients', V1ReclamationsController::class);
        // Route::get('projets/{idprojet}/ReclamationsClients ', [V1ReclamationsController::class, 'indexByProjet']);
        Route::post('traiter_reclamation_client/{id}', [V1ReclamationsController::class, 'traiter_reclamation_client'])->name('');

        //Echéances Tranche

        Route::get('projets/{idprojet}/get_tranches_without_echeances', [V1EchancesTrancheCleController::class, 'get_tranches_without_echeances']);
        Route::resource('/EcheancesTranche', V1EchancesTrancheCleController::class);
        Route::get('projets/{idprojet}/EcheancesTranche', [V1EchancesTrancheCleController::class, 'indexByProjet']);
        Route::get('list_echeances_byTrancheId/{id}', [V1EchancesTrancheCleController::class, 'list_echeances_byTrancheId']);

        //Remboursement

        Route::get('get_detail_transfert/{reservation_id}', [V1RemboursementController::class, 'get_detail_transfert'])->name('');
        Route::get('get_remboursements/{projet_id}/{etat}', [V1RemboursementController::class, 'indexByProjet'])->name('');
        Route::post('traiter_demande_pre_rembourse/{id}', [v1RemboursementController::class, 'traiter_demande_pre_rembourse'])->name('');
        Route::post('traiter_accuse/{id}', [V1RemboursementController::class, 'traiter_accuse'])->name('');
        Route::get('get_notif_demande_pre_remboursement/{projet_id}', [V1RemboursementController::class, 'get_notif_demande_pre_remboursement'])->name('');
        Route::post('traiter_decaissement/{id}', [V1RemboursementController::class, 'traiter_decaissement'])->name('');
        Route::get('get_remboursements_dos_transfert/{projet_id}', [V1RemboursementController::class, 'get_remboursements_dos_transfert'])->name('');

        //IMPORT Bien by Excel
        Route::resource('/histo_importation', V1UploadBienController::class);
        Route::post('upload_excel_bien', [V1UploadBienController::class, 'upload'])->name('');
        Route::get('projets/{idprojet}/histo_importation', [V1UploadBienController::class, 'histo_importation']);
        Route::delete('delete_fichier_import/{id}', [V1UploadBienController::class, 'delete_fichier_import'])->name('');

        Route::post('upload_excel_bien_modif_en_masse', [V1UploadBienController::class, 'upload_excel_bien_modif_en_masse'])->name('');
        Route::post('upload_excel_titre_foncier_en_masse', [V1UploadBienController::class, 'upload_excel_titre_foncier_en_masse'])->name('');


        //Dashboad
        Route::get('dashboard/{projet_id}/{de}/{a}', [V1HomeController::class, 'dashboard'])->name('');
        //EtapesProjet
        Route::resource('etapesProjet', V1EtapeProjetController::class);
        Route::get('projets/{idprojet}/etapesProjet', [V1EtapeProjetController::class, 'indexByProjet']);
        //Actualite
        /*************************************Actualites***************************** */
        Route::get('historiques/{date}/{id}/{type}', [v1ActualiteController::class, 'get_historique'])->name('');
        Route::get('actualites/{projet_id}/{user_id}/{de_date}/{a_date}', [V1ActualiteController::class, 'index'])->name('');

        //Commission
        //Configurations
        Route::resource('commissionsConfigurations', V1CommissionController::class);
        Route::get('configurations_commissions/{idprojet}', [V1CommissionController::class, 'configurations_commissions']);
        // Montant Fixe
        Route::get('commission_montant/{idprojet}', [V1CommissionController::class, 'commission_montant']);
        //Mensuelle
        Route::get('projets/{idprojet}/commissions_mensuelle_en_attente', [V1CommissionController::class, 'commissions_mensuelle_en_attente']);
        Route::get('cummulles_commissions/{user_id}', [V1CommissionController::class, 'cummulles_commissions']);
        Route::post('traiter_commission/{comm_id}', [V1CommissionController::class, 'traiter_commission'])->name('');
        Route::get('projets/{idprojet}/commissions_traites', [V1CommissionController::class, 'commissions_traites']);
        Route::get('projets/{idprojet}/commissions_cumuls_by_projet', [V1CommissionController::class, 'commissions_cumuls_by_projet']);

               /***********************************Notaire*******************/

        Route::get('projets/{idprojet}/notaires', [V1NotaireController::class, 'get_notaires'])->name('');
        Route::put('/affecter_notaire/{id}', [V1NotaireController::class, 'affecter_notaire'])->name('');
        Route::get('projets/{idprojet}/new_dossiers_notaire', [V1NotaireController::class, 'get_new_dossier_notaire'])->name('');
        Route::get('projets/{idprojet}/rdvs_notaire', [V1NotaireController::class, 'get_rdvs_notaire'])->name('');
        Route::get('projets/{idprojet}/relances_notaire', [V1NotaireController::class, 'get_relances_notaire'])->name('');
        Route::put('add_prochaine_relance/{rdv_id}', [V1NotaireController::class, 'add_prochaine_relance'])->name('');
        Route::get('get_relances_history/{rdv_id}', [V1NotaireController::class, 'get_relances_history'])->name('');
        Route::get('projets/{idprojet}/get_attestations_ventes', [V1NotaireController::class, 'get_attestations_ventes'])->name('');
        Route::get('projets/{idprojet}/get_contrats_ventes', [V1NotaireController::class, 'get_contrats_ventes'])->name('');
         // Nouvelles routes pour gérer les créneaux
            // Route POST pour un seul créneau (MÊME URL que GET)
        Route::post('storeCreneau', [V1NotaireController::class, 'storeCreneau']);
        Route::get('creaneau_occupes_by_user_id', [V1NotaireController::class, 'getCreneauxOccupes_by_User']);
            // Route DELETE
        Route::delete('creaneau_occupes_by_user_id/{id}', [V1NotaireController::class, 'deleteCreneau']);
        Route::put('/update-creneau-by-user/{id}', [V1NotaireController::class, 'updateCreneau']);
        Route::post('/update-agenda-by-user', [V1NotaireController::class, 'updateAgendaByUser']);

        /****************************Fin NotaireController************************ */
            // TikTok API Integration - updated with OAuth flow
        Route::get('/tiktok/auth-url', [TikTokApiController::class, 'getAuthUrl']);
        Route::post('/tiktok/callback', [TikTokApiController::class, 'handleCallback']);
        Route::post('/tiktok/publish', [TikTokApiController::class, 'publishContent']);
        Route::get('/tiktok/status', [TikTokApiController::class, 'checkPublishStatus']);

        // LinkedIn integration
        Route::post('/linkedin/share', [LinkedInController::class, 'sharePost']);

        // LinkedIn configurations by project
        Route::get('/linkedin-configurations', [LinkedInController::class, 'linkedin_configurations']);
        Route::post('/linkedin-configurations', [LinkedInController::class, 'store_linkedin_configuration']);
        Route::delete('/linkedin-configurations/{id}', [LinkedInController::class, 'delete_linkedin_configuration']);
        Route::get('/linkedin-config/project/{projectId}', [LinkedInController::class, 'get_linkedin_config_by_project']);

        // LinkedIn auth endpoints
        Route::get('/linkedin-config/auth-url', [LinkedInController::class, 'getAuthUrl']);
        Route::post('/linkedin-config/callback', [LinkedInController::class, 'handleCallback']);

        // Add the missing webhook toggle routes inside the v1 prefix
        Route::put('/facebook-configurations/{configId}/webhook/toggle', [Facebook_InstagramController::class, 'toggle_facebook_webhook']);
        Route::put('/instagram-configurations/{configId}/webhook/toggle', [Facebook_InstagramController::class, 'toggle_instagram_webhook']);
    });

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
    Route::put('activateUser/{id}', [UserController::class, 'activateUser'])->name('api.activateUser');
    Route::post('restoreUser/{id}', [UserController::class, 'restoreUser'])->name('restoreUser');
    Route::get('getTrashedUsers', [UserController::class, 'getTrashedUsers'])->name('getTrashedUsers');
    Route::get('dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::get('getTrashedUsersBySociete/{id}', [UserController::class, 'getTrashedUsersBySociete'])->name('getTrashedUsersBySociete');
    Route::post('logout', [UserController::class, 'logout'])->name('logout');
    Route::post('addUserProjet/{id}', [UserController::class, 'addUserProjet'])->name('addUserProjet');
    Route::get('get_users', [UserController::class, 'get_users'])->name('get_users');
    /*  Route::post('sendEmail', [UserController::class, 'sendEmail']);
    Route::post('resendEmail', [UserController::class, 'resendEmail']);
     */

    Route::post('/reset', [UserController::class, 'reset']);

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
    Route::get('get_nb_relances_visites/{projet_id}', [NotificationController::class, 'get_nb_relances_visites'])->name('');
    Route::get('get_nb_rdv_visites/{projet_id}', [NotificationController::class, 'get_nb_rdv_visites'])->name('');
    Route::get('get_nb_frein_client_visite/{projet_id}', [NotificationController::class, 'get_nb_frein_client_visite'])->name('');

    Route::get('notifications_menu_horizontal_crm/{projet_id}', [NotificationController::class, 'get_notifications_menu_horizontal_crm'])->name('');
    Route::get('get_notifications/{projet_id}', [NotificationController::class, 'get_notifications'])->name('');

    Route::post('mark_notification_seen', [NotificationController::class, 'mark_notification_seen'])->name('');
    Route::post('mark_all_notifications_seen', [NotificationController::class, 'mark_all_notifications_seen'])->name('');
    Route::get('DestroyNotif/{id}', [NotificationController::class, 'DestroyNotif'])->name('');
    Route::get('notifications/{projet_id}', [NotificationController::class, 'index'])->name('');
    Route::get('get_notif_rejete_commercial/{projet_id}', [NotificationController::class, 'get_notif_rejete_commercial'])->name('');
    Route::get('notifications_menu_horizontal_vente_admin/{projet_id}', [NotificationController::class, 'get_notif_menu_horizontal_vente_admin'])->name('');
    Route::get('notifications_menu_horizontal_vente_commercial/{projet_id}', [NotificationController::class, 'get_notif_menu_horizontal_vente_comm'])->name('');

    /********************************DesistemenController*********** */

    /***********************************Livraison*******************/
    /*******rdv notaire*** */

    Route::get('get_rdvs_reservation/{res_id}', [LivraisonController::class, 'get_rdvs_reservation'])->name('');
    Route::put('update_rdv_reservation/{rdv_id}', [LivraisonController::class, 'update_rdv_reservation'])->name('');
    Route::post('store_rdv_reservation/{rdv_id}', [LivraisonController::class, 'store_rdv_reservation'])->name('');
    Route::put('traiter_rdv_reservation/{rdv_id}', [LivraisonController::class, 'traiter_rdv_reservation'])->name('');
    Route::delete('destroy_rdv_reservation/{id}', [LivraisonController::class, 'destroy_rdv_reservation'])->name('');
    Route::get('get_rdv_notaire_menu/{projet_id}', [LivraisonController::class, 'get_rdv_notaire_menu'])->name('');


    //Rendez Vous
    Route::get('creneaux-occupes', [LivraisonController::class, 'getCreneauxOccupes']);
    Route::post('/update-reservation-creneau/{reservation_id}', [LivraisonController::class, 'updateReservationCreneau']);
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
