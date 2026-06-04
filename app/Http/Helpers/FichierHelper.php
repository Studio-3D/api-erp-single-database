<?php

namespace App\Http\Helpers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FichierHelper
{
    /**
     * Vérifier si on est sur Cloudways
     */
    private static function isCloudways()
    {
        return (isset($_SERVER['cw_allowed_ip']) ||
                file_exists('/home/master/.cloudways') ||
                getenv('CLOUDWAYS_APP_NAME') !== false ||
                strpos(__DIR__, '/home/1633242.cloudwaysapps.com/') !== false);
    }

    /**
     * Obtenir le chemin de base pour les fichiers
     * CORRIGÉ : Évite le double public_html
     */
   private static function getBasePath()
{
    if (self::isCloudways()) {
        // CORRECTION : Utiliser public_html/public/docs
        return base_path('public_html/public/docs');
    }
    return public_path('docs');
}

    /**
     * Ajouter un fichier
     */
    public static function ajouter_fichier($file, $societe, $id, $doss, $nom_file)
    {
        $relativePath = $societe . '_' . $id . '/' . $doss;
        $directory = self::getBasePath() . '/' . $relativePath;

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $file->move($directory, $nom_file);

        if (self::isCloudways()) {
            chmod($directory . '/' . $nom_file, 0644);
        }

        return $nom_file;
    }

    /**
     * Supprimer un fichier
     */
    public static function supprimer_fichier($societe, $id, $doss, $nom_file)
    {
        if (!$nom_file) {
            return true;
        }

        $filePath = self::getBasePath() . '/' . $societe . '_' . $id . '/' . $doss . '/' . $nom_file;

        if (File::exists($filePath)) {
            return File::delete($filePath);
        }

        return false;
    }

    /**
     * Récupérer l'URL d'un fichier
     */
    public static function get_file_url($societe, $id, $doss, $nom_file)
    {
        if (!$nom_file) {
            return null;
        }

        return asset('docs/' . $societe . '_' . $id . '/' . $doss . '/' . $nom_file);
    }

    /**
     * Vérifier si un fichier existe
     */
    public static function fichier_existe($societe, $id, $doss, $nom_file)
    {
        if (!$nom_file) {
            return false;
        }

        $filePath = self::getBasePath() . '/' . $societe . '_' . $id . '/' . $doss . '/' . $nom_file;
        return File::exists($filePath);
    }

    /**
     * Récupérer tous les fichiers d'un dossier
     */
    public static function get_files($societe, $id, $doss)
    {
        $directory = self::getBasePath() . '/' . $societe . '_' . $id . '/' . $doss;

        if (!File::exists($directory)) {
            return [];
        }

        $files = [];
        $allFiles = File::files($directory);

        foreach ($allFiles as $file) {
            $files[] = [
                'name' => $file->getFilename(),
                'path' => asset('docs/' . $societe . '_' . $id . '/' . $doss . '/' . $file->getFilename()),
                'size' => $file->getSize(),
                'last_modified' => date('Y-m-d H:i:s', $file->getMTime())
            ];
        }

        return $files;
    }

    /**
     * Renommer le dossier d'une société
     */
    public static function renommer_dossier_societe($ancienNom, $nouveauNom, $societeId)
    {
        $ancienDossierBase = $ancienNom . '_' . $societeId;
        $nouveauDossierBase = $nouveauNom . '_' . $societeId;

        $basePath = self::getBasePath();

        $ancienChemin = $basePath . '/' . $ancienDossierBase;
        $nouveauChemin = $basePath . '/' . $nouveauDossierBase;

        if (File::exists($ancienChemin)) {
            File::move($ancienChemin, $nouveauChemin);
            return true;
        }

        return false;
    }
}
