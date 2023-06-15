<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocieteController;
use App\Http\Controllers\UserController;

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

/* Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
return $request->user();
}); */
Route::post('login', [UserController::class, 'login'])->name('login');
Route::post('register', [UserController::class, 'register'])->name('register');
Route::middleware('auth:api')->group(function () {
        Route::resource('societe', SocieteController::class);
        Route::resource('user', UserController::class);
        Route::get('getUsersBySocieteId/{id}', 'App\Http\Controllers\UserController@getUsersBySocieteId')
                ->name('getUsersBySocieteId');
        Route::put('activateUser/{id}', 'App\Http\Controllers\UserController@activateUser')
                ->name('activateUser');
        Route::put('desactivateUser/{id}', 'App\Http\Controllers\UserController@desactivateUser')
                ->name('desactivateUser');
        Route::get('restoreUser/{id}', 'App\Http\Controllers\UserController@restoreUser')
                ->name('restoreUser');
        Route::get('getTrashedUsers', 'App\Http\Controllers\UserController@getTrashedUsers')
                ->name('getTrashedUsers');
        Route::get('getTrashedUsersBySociete/{id}', 'App\Http\Controllers\UserController@getTrashedUsersBySociete')
                ->name('getTrashedUsersBySociete');
            //
        Route::get('restoreSociete/{id}', 'App\Http\Controllers\SocieteController@restoreSociete')
                ->name('restoreSociete');
        Route::get('getTrashedSocietes', 'App\Http\Controllers\SocieteController@getTrashedSocietes')
                ->name('getTrashedSocietes');
            //
        Route::get('restoreBien/{id}', 'App\Http\Controllers\BienController@restoreBien')
                ->name('restoreBien');
        Route::get('getTrashedBiens', 'App\Http\Controllers\BienController@getTrashedBiens')
                ->name('getTrashedBiens');
            //
        Route::get('restoreBloc/{id}', 'App\Http\Controllers\BlocController@restoreBloc')
                ->name('restoreBloc');
        Route::get('getTrashedBlocs', 'App\Http\Controllers\BlocController@getTrashedBlocs')
                ->name('getTrashedBlocs');
            //
        Route::get('restoreImmeuble/{id}', 'App\Http\Controllers\ImmeubleController@restoreImmeuble')
                ->name('restoreImmeuble');
        Route::get('getTrashedImmeubles', 'App\Http\Controllers\ImmeubleController@getTrashedImmeubles')
                ->name('getTrashedImmeubles');
            //
        Route::get('restoreProjet/{id}', 'App\Http\Controllers\ProjetController@restoreProjet')
                ->name('restoreProjet');
        Route::get('getTrashedProjets', 'App\Http\Controllers\ProjetController@getTrashedProjets')
                ->name('getTrashedProjets');
            //
        Route::get('restoreTranche/{id}', 'App\Http\Controllers\TrancheController@restoreTranche')
                ->name('restoreTranche');
        Route::get('getTrashedTranches', 'App\Http\Controllers\TrancheController@getTrashedTranches')
                ->name('getTrashedTranches');
           //
        Route::get('restoreTranche/{id}', 'App\Http\Controllers\TrancheController@restoreTranche')
                ->name('restoreTranche');
        Route::get('getTrashedTranches', 'App\Http\Controllers\TrancheController@getTrashedTranches')
                 ->name('getTrashedTranches');
        //
        Route::get('restoreTypeBien/{id}', 'App\Http\Controllers\TypeBienController@restoreTypeBien')
                ->name('restoreTypeBien');
        Route::get('getTrashedTypesBien', 'App\Http\Controllers\TypeBienController@getTrashedTypesBien')
                ->name('getTrashedTypesBien');
    
    });
    
