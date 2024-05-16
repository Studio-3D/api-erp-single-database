<?php

namespace  App\Http\Helpers;


class FichierHelper
{
    public static function ajouter_fichier($file,$societe,$id,$doss,$nom_file)
    {
        
            $file->move(public_path('Docs/' . $societe . '_' . $id . '/'.$doss), $nom_file);
    }
    
}
