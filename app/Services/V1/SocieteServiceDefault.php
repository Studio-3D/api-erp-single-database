<?php
namespace App\Services\V1;

use App\Events\NewSocieteEvent;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FichierHelper;
use App\Repositories\V1\Contracts\SocieteRepository;
use App\Services\V1\Contracts\SocieteService;
use Illuminate\Support\Facades\Config;

class SocieteServiceDefault implements SocieteService
{
    private $societeRepository;
    private $databaseHelper;
    public function __construct(SocieteRepository $societeRepository)
    {
        $this->societeRepository = $societeRepository;
        $this->databaseHelper = new DatabaseHelper(); // This is bad, unnecessary coupling
        //TODO: Define static methods in DatabaseHelper class instead
    }
    public function createSociete($request)
    {
        // Assure-toi que $request est bien un objet Request
        $raison_sociale_concatene = str_replace(' ', '', $request->input('raison_sociale'));
        $data = $request->all(); // Convertit les données en tableau pour les traitements
        $data['raison_sociale_concatene'] = $raison_sociale_concatene;

        // Création de la société
        $societe = $this->societeRepository->create($data);

        // Gestion du fichier logo
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $logo = time() . '.' . $raison_sociale_concatene . '.' . $file->extension();

            // Ajouter le fichier
            FichierHelper::ajouter_fichier($file, $raison_sociale_concatene, $societe->id, 'logos', $logo);

            // Mettre à jour le logo dans la base
            $this->societeRepository->update($societe->id, ['logo' => $logo]);
        }

        // Création de la base de données client
        $response = $this->databaseHelper->createNewClientDatabase($raison_sociale_concatene, $societe->id);

        // Émettre un événement
        Config::set('broadcasting.default', 'pusher_1');
        broadcast(event: new NewSocieteEvent($societe->id));

        return response()->json(['societe' => $societe], 200);
    }

    public function getSocieteById(int $id)
    {
        return $this->societeRepository->find($id);
    }

    public function getSocietes(array $filters, int $size, int $page)
    {
        return $this->societeRepository->all($filters, $size, $page);
    }
}
