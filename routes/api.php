<?php

use App\Http\Controllers\BienController;
use App\Http\Controllers\BlocController;
use App\Http\Controllers\CompositionBienController;
use App\Http\Controllers\FreinController;
use App\Http\Controllers\ImmeubleController;
use App\Http\Controllers\ProjetController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\SocieteController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TrancheController;
use App\Http\Controllers\TypeBienController;
use App\Http\Controllers\TypeProjetController;
use App\Http\Controllers\TypologieController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisiteController;
use App\Http\Controllers\VueController;
use Illuminate\Http\Request;
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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();

});

Route::post('login', [UserController::class, 'login'])->name('login');

//Route::post('register', [UserController::class, 'register'])->name('register');

Route::middleware('auth:api')->group(function () {

    /*************************************Société***************************** */
    Route::resource('societe', SocieteController::class);
    Route::post('restoreSociete/{id}', [SocieteController::class, 'restoreSociete'])->name('restoreSociete');
    Route::get('getTrashedSocietes', [SocieteController::class, 'getTrashedSocietes'])->name('getTrashedSocietes');
    Route::put('Switch_Societes', [SocieteController::class, 'Switch_Societes'])->name('Switch_Societes');
    Route::put('Exist_Societes', [SocieteController::class, 'Exist_Societes'])->name('Exist_Societes');
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
    Route::post('restoreTranche/{id}', [TrancheController::class, 'restoreTranche'])->name('restoreTranche');
    Route::get('getTrashedTranches', [TrancheController::class, 'getTrashedTranches'])->name('getTrashedTranches');
    Route::get('getTranchesByProjet/{id}', [TrancheController::class, 'getTranchesByProjet'])->name('getTranchesByProjet');
    Route::get('getTranchesByProjet_paginate/{id}', [TrancheController::class, 'getTranchesByProjet_paginate'])->name('getTranchesByProjet_paginate');

    /*************************************Bloc***************************** */
    Route::resource('bloc', BlocController::class);
    Route::post('restoreBloc/{id}', [BlocController::class, 'restoreBloc'])->name('restoreBloc');
    Route::get('getTrashedBlocs', [BlocController::class, 'getTrashedBlocs'])->name('getTrashedBlocs');
    Route::get('getBlocsByProjet/{id}', [BlocController::class, 'getBlocsByProjet'])->name('getBlocsByProjet');
    Route::get('getBlocsByProjet_paginate/{id}', [BlocController::class, 'getBlocsByProjet_paginate'])->name('getBlocsByProjet_paginate');
    Route::get('getBlocsByTranche/{id}', [BlocController::class, 'getBlocsByTranche'])->name('getBlocsByTranche');

    /*************************************Immeuble***************************** */
    Route::resource('immeuble', ImmeubleController::class);
    Route::post('restoreImmeuble/{id}', [ImmeubleController::class, 'restoreImmeuble'])->name('restoreImmeuble');
    Route::get('getTrashedImmeubles', [ImmeubleController::class, 'getTrashedImmeubles'])->name('getTrashedImmeubles');
    Route::get('getImmeublesByProjet/{id}', [ImmeubleController::class, 'getImmeublesByProjet'])->name('getImmeublesByProjet');
    Route::get('getImmeublesByProjet_paginate/{id}', [ImmeubleController::class, 'getImmeublesByProjet_paginate'])->name('getImmeublesByProjet_paginate');
    Route::get('getImmeublesByTranche/{id}', [ImmeubleController::class, 'getImmeublesByTranche'])->name('getImmeublesByTranche');
    Route::get('getImmeublesByBloc/{id}', [ImmeubleController::class, 'getImmeublesByBloc'])->name('getImmeublesByBloc');

    /*************************************Bien***************************** */
    Route::resource('typeBien', TypeBienController::class);
    Route::get('get_typeBiens', [TypeBienController::class, 'get_typeBiens'])->name('get_typeBiens');
    Route::resource('bien', BienController::class);
    Route::post('restoreBien/{id}', [BienController::class, 'restoreBien'])->name('restoreBien');
    Route::get('getTrashedBiens', [BienController::class, 'getTrashedBiens'])->name('getTrashedBiens');
    Route::post('restoreTypeBien/{id}', [TypeBienController::class, 'restoreTypeBien'])->name('restoreTypeBien');
    Route::get('getTrashedTypesBien', [TypeBienController::class, 'getTrashedTypesBien'])->name('getTrashedTypesBien');
    Route::put('bloquerBien/{id}', [BienController::class, 'bloquerBien'])->name('bloquerBien');
    Route::put('reserverBien/{id}', [BienController::class, 'reserverBien'])->name('reserverBien');
    Route::put('prereserverBien/{id}', [BienController::class, 'prereserverBien'])->name('prereserverBien');
    Route::put('libererBien/{id}', [BienController::class, 'libererBien'])->name('libererBien');
    Route::get('getHistoriqueBien/{id}', [BienController::class, 'getHistoriqueBien'])->name('getHistoriqueBien');
    Route::resource('compositionBien', CompositionBienController::class);
    Route::post('restoreCompositionBien/{id}', [CompositionBienController::class, 'restoreCompositionBien'])->name('restoreCompositionBien');
    Route::get('getTrashedCompositionBiens', [CompositionBienController::class, 'getTrashedCompositionBiens'])->name('getTrashedCompositionBiens');
    Route::get('getComposition/{id}', [CompositionBienController::class, 'getComposition'])->name('getComposition');
    Route::get('getBiensByProjet/{id}', [BienController::class, 'getBiensByProjet'])->name('getBiensByProjet');
    Route::get('getBiensByProjet_paginate/{id}', [BienController::class, 'getBiensByProjet_paginate'])->name('getBiensByProjet_paginate');
    Route::get('getBiensByTranche/{id}', [BienController::class, 'getBiensByTranche'])->name('getBiensByTranche');
    Route::get('getBiensByBloc/{id}', [BienController::class, 'getBiensByBloc'])->name('getBiensByBloc');
    Route::get('getBiensByImmeuble/{id}', [BienController::class, 'getBiensByImmeuble'])->name('getBiensByImmeuble');
    Route::get('getBiensDispoByImmeuble/{id}', [BienController::class, 'getBiensDispoByImmeuble'])->name('getBiensDispoByImmeuble');
    Route::get('getBiensDispoByBloc/{id}', [BienController::class, 'getBiensDispoByBloc'])->name('getBiensDispoByBloc');
    Route::get('getBiensDispoByTranche/{id}', [BienController::class, 'getBiensDispoByTranche'])->name('getBiensDispoByTranche');
    Route::get('getBiensDispoByProjet/{id}', [BienController::class, 'getBiensDispoByProjet'])->name('getBiensByDispoProjet');
    Route::put('setPropostionBien/{id}', [BienController::class, 'setPropostionBien'])->name('setPropostionBien');

    /*************************************Visite***************************** */
    Route::resource('visite',VisiteController::class);
    Route::post('addLinkedVisite/{id}',[VisiteController::class,'addLinkedVisite'])->name('addLinkedVisite');
    Route::get('getAllAttributes',[VisiteController::class,'getAllAttributes'])->name('getAllAttributes');
    /*************************************Frein***************************** */
    Route::resource('frein', FreinController::class);

    /*************************************Prospect***************************** */
    Route::resource('prospect',ProspectController::class);

    /*************************************Source***************************** */
    Route::resource('source',SourceController::class);

    /*************************************Vue***************************** */
    Route::resource('vue', VueController::class);

    /*************************************Typologie***************************** */
    Route::resource('typologie', TypologieController::class);
});
