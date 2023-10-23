<?php

namespace App\Http\Controllers;

use App\Enum\EtatBien;
use App\Enum\InteretEnum;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreFreinRequest;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\StoreVisiteRequest;
use App\Http\Requests\UpdateFreinRequest;
use App\Http\Requests\UpdateVisiteRequest;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\Frein;
use App\Models\Immeuble;
use App\Models\Prospect;
use App\Models\Tranche;
use App\Models\Typologie;
use App\Models\User;
use App\Models\Visite;
use App\Models\Vue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class VisiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $visites = Visite::on('temp')->where('origin_id',null)->get();
            return response()->json(['visites' => $visites]);
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

    public function store(StoreVisiteRequest $request)
    {
        $user = Auth::user();
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $prospectExist = Prospect::on('temp')->where('cin', $request->cin)->orWhere([['nom',$request->nom],['prenom',$request->prenom],['telephone',$request->telephone]])->get();
            if ($prospectExist->isEmpty()) {
                $validatedData = $request->validated();
                $validatedData['source']='visite';
                $prospectController = new ProspectController();
                $prospectExist = $prospectController->store(new StoreProspectRequest($validatedData));
            }
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $visite = new Visite();
            $visite->setConnection('temp');
            $visite->user_id = $userAuth->value('id');
            $visite->prospect_id = $prospectExist->value('id');
            $visite->commentaire = $request->commentaire;
            $visite->source_id = $request->source_id;
            $visite->notifie = $request->notifie;
            $visite->type_notification = $request->type_notification;
            $visite->interet = $request->interet;
            $visite->bien_id = $request->bien_id;
            $visite->rdv = $request->rdv;
            $visite->statut = $request->statut;
            $visite->mode_relance = $request->mode_relance;
            $visite->date_relance = $request->date_relance;
            $visite->save();
            if($visite->interet == InteretEnum::INTERESSE->value){
                if($visite->bien_id){
                    $bienEncoursPropo=new BienController();
                    $bienEncoursPropo->setPropostionBien($visite->bien_id);
                }
            }
            if ($visite->interet == InteretEnum::PERDU->value) {
                $freinRequest=$request->validated();
                $freinRequest['visite_id']=$visite->getAttribute('id');
                $freinRequest['selectedTranches']=$request->selectedTranches;
                $freinRequest['selectedEtages']=$request->selectedEtages;
                $freinRequest['selectedOrientations']=$request->selectedOrientations;
                $freinRequest['selectedTypologies']=$request->selectedTypologies;
                $freinRequest['selectedVues']=$request->selectedVues;
                $freinController = new FreinController();
                $freinController->store(new StoreFreinRequest($freinRequest));
            }
            return response()->json(['visite' => $visite], 200);
        }
        else
        {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $frein=new FreinController();
            $visite = Visite::on('temp')->findOrfail($id);
            if($visite->interet==InteretEnum::PERDU->name) {
                $visite['frein']=$frein->searchFreinByVisiteId($visite->id);
            }
            $relatedVisites=Visite::on('temp')->where('origin_id',$visite->id)->get();
            foreach ($relatedVisites as $relatedVisite) {
                if ($relatedVisite->interet == InteretEnum::PERDU->name) {
                    $frein = $frein->searchFreinByVisiteId($relatedVisite->id);
                    $relatedVisite['frein'] = $frein;
                }
            }
            $prospect=Prospect::on('temp')->find($visite->value('prospect_id'));
            return response()->json(['prospect'=>$prospect,'visite' => $visite,'realtedViste'=>$relatedVisites], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Visite $visite)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVisiteRequest $request,$id)
    {
        $user = Auth::user();
        if(RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $visite = Visite::on('temp')->findOrFail($id);
            if($visite->get()->value('bien_id') && $request->interet != InteretEnum::INTERESSE->value){
                $bienEncoursPropo=new BienController();
                $bienEncoursPropo->libererBien($visite->get()->value('bien_id'));
            }
            $visite->user_id = $userAuth->value('id');
            $visite->commentaire = $request->commentaire;
            $visite->source_id = $request->source_id;
            $visite->notifie = $request->notifie;
            $visite->type_notification = $request->type_notification;
            $visite->interet = $request->interet;
            $visite->bien_id = $request->bien_id;
            $visite->rdv = $request->rdv;
            $visite->statut = $request->statut;
            $visite->mode_relance = $request->mode_relance;
            $visite->date_relance = $request->date_relance;
            $visite->save();
            if($visite->interet == InteretEnum::INTERESSE->value){
                if($visite->bien_id){
                    $bienEncoursPropo=new BienController();
                    $bienEncoursPropo->setPropostionBien($visite->bien_id);
                }
            }
            if ($visite->interet == InteretEnum::PERDU->value) {
                $frein_id=Frein::on('temp')->where('visite_id', $visite->id)->get();
                $freinRequest=$request->validated();
                $freinRequest['selectedTranches']=$request->selectedTranches;
                $freinRequest['selectedEtages']=$request->selectedEtages;
                $freinRequest['selectedOrientations']=$request->selectedOrientations;
                $freinRequest['selectedTypologies']=$request->selectedTypologies;
                $freinRequest['selectedVues']=$request->selectedVues;
                $freinController = new FreinController();
                if(!$frein_id->isEmpty()){
                    $freinController->update(new UpdateFreinRequest($freinRequest),$frein_id->value('id'));
                }
                else{
                    $freinRequest['visite_id']=$visite->id;
                    $freinController->store(new StoreFreinRequest($freinRequest));
                }
            }
            else {

                $frein=Frein::on('temp')->where('visite_id', $id)->get();
                if(!$frein->isEmpty()){
                    $freinController=new FreinController();
                    $freinController->destroy($frein->value('id'));
                }
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $visite=Visite::on('temp')->findOrFail($id);
            if($visite->interet== InteretEnum::INTERESSE->value){
                if($visite->bien_id){
                    $bienEncoursPropo=new BienController();
                    $bienEncoursPropo->libererBien($visite->bien_id);
                }
            }
            if($visite->interet == InteretEnum::PERDU->name){
                $frein=Frein::on('temp')->where('visite_id',$visite->id)->get();
                $freinController= new FreinController();
                $freinController->destroy($frein->value('id'));
            }
            if($visite->delete()){
                return response()->json(['message'=>'Visite supprimée avec succès.'],200);
            }
            else return response()->json(['error'=>"La visite n'a pas été supprimée."],404);
        }
        else return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function addLinkedVisite($id, StoreVisiteRequest $request){
        DatabaseHelper::Config();
        $originalVisite=Visite::on('temp')->find($id);
        if (!$originalVisite) return response()->json(['error'=>"L'original de la visite n'a pas été trouvé."]);
        $user = Auth::user();
        if(RoleHelper::ACSup()){
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $newVisit=new Visite();
            $newVisit->setConnection('temp');
            $newVisit->user_id=$userAuth->value('id');
            $newVisit->prospect_id=$originalVisite->prospect_id;
            $newVisit->origin_id=$id;
            $newVisit->commentaire = $request->commentaire;
            $newVisit->notifie = $request->notifie;
            $newVisit->type_notification = $request->type_notification;
            $newVisit->interet = $request->interet;
            $newVisit->bien_id = $request->bien_id;
            $newVisit->rdv = $request->rdv;
            $newVisit->statut = $request->statut;
            $newVisit->mode_relance = $request->mode_relance;
            $newVisit->date_relance = $request->date_relance;
            $newVisit->save();
            if($newVisit->interet== InteretEnum::INTERESSE->value){
                if($newVisit->bien_id){
                    $bienEncoursPropo=new BienController();
                    $bienEncoursPropo->setPropostionBien($newVisit->bien_id);
                }
            }
            if ($newVisit->interet == InteretEnum::PERDU->value) {
                $freinRequest=$request->validated();
                $freinRequest['visite_id']=$newVisit->getAttribute('id');
                $freinRequest['selectedTranches']=$request->selectedTranches;
                $freinRequest['selectedEtages']=$request->selectedEtages;
                $freinRequest['selectedOrientations']=$request->selectedOrientations;
                $freinRequest['selectedTypologies']=$request->selectedTypologies;
                $freinRequest['selectedVues']=$request->selectedVues;
                $freinController = new FreinController();
                $freinController->store(new StoreFreinRequest($freinRequest));
            }
            return response()->json(['visite' => $newVisit], 200);
        }
        else
        {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public  function getAllAttributes(){
        $tranches = Tranche::where('projet_id', Session::get('projet_id'))->get();
        $etages= Tranche::where('projet_id', Session::get('projet_id'))->max('niveau_etage')->value();;
        $blocs = Bloc::where('projet_id', Session::get('projet_id'))->get();
        $immeubles = Immeuble::where('projet_id', Session::get('projet_id'))->get();
        $biens = Bien::where([['projet_id', Session::get('projet_id')],['etat',EtatBien::DISPONIBLE->name]])->get();
        $typologies=Typologie::where('projet_id', Session::get('projet_id'))->get();
        $vues=Vue::where('projet_id', Session::get('projet_id'))->get();
        $formData = [
            'tranches' => $tranches,
            'etages' => $etages,
            'blocs' => $blocs,
            'immeubles' => $immeubles,
            'biens' => $biens,
            'typologies' => $typologies,
            'vues' => $vues
        ];

        return response()->json($formData);
    }

    public function getProspectById($visite_id){
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $visite=Visite::on('temp')->findOrFail($visite_id);
            $prospect=Prospect::on('temp')->where('id',$visite->prospect_id)->get();
            if(!$prospect->isEmpty()){
                return response()->json(['message'=>'Any prospect exists in this visit.'],400);
            }
            else{
                return response()->json(['prospect'=>$prospect],200);
            }
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }
}
