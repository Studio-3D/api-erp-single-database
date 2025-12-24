<?php

namespace App\Http\Helpers;
use Illuminate\Support\Facades\File;



class FichierHelper
{
    public static function ajouter_fichier($file, $societe, $id, $doss, $nom_file)
    {
        $directory = public_path('docs/' . $societe . '_' . $id . '/' . $doss);

        // Create directory if it doesn't exist
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $file->move($directory, $nom_file);
    }

    /**
     * Rename a societe's folder structure
     */
    public static function renommer_dossier_societe($ancienNom, $nouveauNom, $societeId)
    {
        $ancienDossierBase = $ancienNom . '_' . $societeId;
        $nouveauDossierBase = $nouveauNom . '_' . $societeId;

        $dossiers = ['docs', 'img'];

        foreach ($dossiers as $dossierType) {
            $ancienChemin = public_path($dossierType . '/' . $ancienDossierBase);
            $nouveauChemin = public_path($dossierType . '/' . $nouveauDossierBase);

            if (File::exists($ancienChemin)) {
                File::move($ancienChemin, $nouveauChemin);
            }
        }

        return true;
    }
}
