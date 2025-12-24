<?php
namespace App\Http\Helpers;

use App\Enum\EtatBien;
use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FreinBienHelper;
use App\Http\Helpers\HistoriqueBienHelper;
use App\Models\Bien;
use App\Models\CompositionBien;
use App\Models\Frein;
use App\Models\Frein_Bien;
use App\Models\Notification;
use App\Models\Relance_Rdv_Visite;
use App\Models\TraitementFrein;
use App\Models\TypeBien;
use App\Models\User;
use App\Models\Visite;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Bien_Helper
{
  public static function validateNumericValue($value, $fieldName) {
    if ($value !== null && $value !== '') {
        if (is_numeric($value)) {
            return (float) $value;
        } else {
            throw new \Exception("La valeur de '$fieldName' doit être un nombre. Valeur trouvée : " . $value);
        }
    }
    return 0;
    }

  public static function checkAndCreateBienByExcel($projet_id, $tranche_id, $bloc_id, $immeuble_id, $row)
{
    $bien_count = Bien::on('temp')->where(function ($query) use ($row, $projet_id, $tranche_id, $bloc_id, $immeuble_id) {
        if (!empty($row['Numero'])) {
            $query->where('propriete_dite_bien', $row['Numero']);
        }
        if ($immeuble_id !== null) {
            $query->where('immeuble_id', $immeuble_id);
        }
        if ($bloc_id !== null) {
            $query->where('bloc_id', $bloc_id);
        }
        // Updated condition for tranche_id
        $trancheIdToUse = $row['tranche_id'] ?? $tranche_id ?? null;
        if ($trancheIdToUse !== null) {
            $query->where('tranche_id', $trancheIdToUse);
        }
        $query->where('projet_id', $projet_id);
    })->count();

    if ($bien_count == 0) {
        $bien = new Bien();
        $bien->setConnection('temp');
        $bien->immeuble_id = $immeuble_id ?? null;
        $bien->bloc_id     = $bloc_id ?? null;
        $bien->tranche_id = $row['tranche_id'] ?? $tranche_id ?? null;
        $bien->projet_id   = $projet_id;

        // Collect ALL errors for this row
        $errors = [];

        // 1. Champs requis avec messages personnalisés
        $requiredFields = [
            "Numero" => "Numéro du bien manquant",
            "Type bien" => "Type de bien manquant",
            "Prix unitaire" => "Prix Unitaire manquant",
        ];

        // Ajouter "Etage" aux champs requis si un des IDs parent est présent
        if ($bloc_id || $tranche_id || $immeuble_id) {
            $requiredFields["Etage"] = "Niveau (étage) manquant";
        }

        // Vérification des champs requis - collect all missing fields
        foreach ($requiredFields as $key => $message) {
            if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                $errors[] = $message;
            }
        }

        // 2. Vérification des champs numériques - collect all numeric errors
       // Add this in the numeric fields validation section, around line where you validate numeric fields:
            $numericFields = [
                'Prix unitaire',
                'Prix',
                'Prix parking',
                'Prix box',
                'Avance mininmale',
                'Superficie architecte',
                'Superficie habitable',
                'Superficie parking',
                'Superficie box',
                'Superficie terrasse',
                'Superficie terrasse calculée',
                'Superficie balcon',
                'Superficie balcon calculée',
                'Superficie jardin',
                'Superficie jardin calculée',
                'Superficie totale',
                'Superficie vendable',
                'Nombre facades',
            ];

            foreach ($numericFields as $key) {
                // Si la clé est présente et non vide (on autorise null/empty)
                if (isset($row[$key]) && $row[$key] !== '') {
                    // S'il ne s'agit pas d'un nombre => erreur
                    if (!is_numeric($row[$key])) {
                        $errors[] = "Le champ '$key' doit être un nombre (ou vide/null). Trouvé : " . $row[$key];
                    }
                }
            }

            // ADD THIS VALIDATION FOR TYPE BIEN - RIGHT AFTER THE NUMERIC FIELDS VALIDATION
            // Validate that Type bien is numeric
            if (array_key_exists("Type bien", $row) && $row['Type bien'] !== null && $row['Type bien'] !== '') {
                if (!is_numeric($row['Type bien'])) {
                    $errors[] = "Le champ 'Type bien' doit être un ID numérique. Valeur trouvée : " . $row['Type bien'];
                }
            }

        // 3. Validation boolean conventionne
        if (array_key_exists("conventionne", $row) && !in_array($row['conventionne'], [0, 1, '0', '1', true, false, 'true', 'false'], true)) {
            $errors[] = "Valeur invalide pour le champ 'conventionné'. Doit être 0 ou 1 (booléen)";
        }

        // 4. Vérification projet_id s'il est requis pour la relation avec type bien
        if (!$projet_id) {
            $errors[] = "Projet ID manquant";
        }

        // If there are any errors, throw exception with ALL errors
        if (!empty($errors)) {
            throw new \Exception(implode(' | ', $errors));
        }

        // Continue with processing if no errors...
        if (array_key_exists("Numero", $row) && $row['Numero'] != null) {
            $bien->propriete_dite_bien = $row['Numero'];
        }

        if (str_contains($row['Numero'], 'Appt')) {
            $explode_numero = explode("Appt", $row['Numero']);
            $num            = $explode_numero[1];
        } elseif (str_contains($row['Numero'], 'APP')) {
            $explode_numero = explode("APP", $row['Numero']);
            $num            = $explode_numero[1];
        } else {
            $num = $row['Numero'];
        }
        $bien->numero = $num;

        $nv = 0;
        if (array_key_exists("Etage", $row)) {
            if (str_contains($row['Etage'], 'er etage')) {
                $explode_niveau_1 = explode("er etage", $row['Etage']);
                $nv               = $explode_niveau_1[0];
            } elseif (str_contains($row['Etage'], 'er étage')) {
                $explode_niveau_5 = explode("er étage", $row['Etage']);
                $nv               = $explode_niveau_5[0];
            } elseif (str_contains($row['Etage'], 'eme etage')) {
                $explode_niveau_2 = explode("eme etage", $row['Etage']);
                $nv               = $explode_niveau_2[0];
            } elseif (str_contains($row['Etage'], 'ème etage')) {
                $explode_niveau_3 = explode("ème etage", $row['Etage']);
                $nv               = $explode_niveau_3[0];
            } elseif (str_contains($row['Etage'], 'ème étage')) {
                $explode_niveau_4 = explode("ème étage", $row['Etage']);
                $nv               = $explode_niveau_4[0];
            } elseif (str_contains($row['Etage'], 'RDC')) {
                $nv = 0;
            }
        } else {
            $nv = NULL;
        }

        $bien->niveau = $nv;

        if (array_key_exists("Type bien", $row) && $row['Type bien'] != null) {
            $type      = TypeBien::on('temp')->where('projet_id', $projet_id)->get();
            $typeFound = false;
            foreach ($type as $key => $value) {
                if ($value->id == intval($row['Type bien'])) {
                    $bien->type_id = $value->id;
                    $typeFound     = true;
                    break;
                }
            }
            if (!$typeFound) {
                throw new \Exception("Type de bien invalide ou non trouvé");
            }
        }

        if (array_key_exists("Prix parking", $row) && $row['Prix parking'] != null) {
            $bien->prix_parking = self::validateNumericValue($row['Prix parking'], "Prix parking");
        } else {
            $bien->prix_parking = 0;
        }

        if (array_key_exists("Prix box", $row) && $row['Prix box'] != null) {
            $bien->prix_box = self::validateNumericValue($row['Prix box'], "Prix box");
        } else {
            $bien->prix_box = 0;
        }

        // Handle Superficie balcon
        if (array_key_exists("Superficie balcon", $row)) {
            $superficie_balcon = $row['Superficie balcon'];
            if ($superficie_balcon === null || $superficie_balcon === 'SYNDIC PROPOSE' || $superficie_balcon === 'SYNDIC PLAN') {
                $bien->superficie_balcon = 0;
            } else {
                $bien->superficie_balcon = self::validateNumericValue($row['Superficie balcon'], "Superficie balcon");
            }
        }

        // Handle Superficie balcon calculée
        if (array_key_exists("Superficie balcon calculée", $row)) {
            $superficie_balcon_calculee = $row['Superficie balcon calculée'];
            if ($superficie_balcon_calculee === null || $superficie_balcon_calculee === 'SYNDIC PROPOSE' || $superficie_balcon_calculee === 'SYNDIC PLAN') {
                $bien->superficie_balcon_calculer = 0;
            } else {
                $bien->superficie_balcon_calculer = self::validateNumericValue($superficie_balcon_calculee, "Superficie balcon calculée");
            }
        } else {
            // If not provided, use half the value of superficie_balcon
            $bien->superficie_balcon_calculer = ($bien->superficie_balcon ?? 0) / 2;
        }

        // Handle Superficie jardin
        if (array_key_exists("Superficie jardin", $row)) {
            $superficie_jardin = $row['Superficie jardin'];
            if ($superficie_jardin === null || $superficie_jardin === 'SYNDIC PROPOSE' || $superficie_jardin === 'SYNDIC PLAN') {
                $bien->superficie_jardin = 0;
            } else {
                $bien->superficie_jardin = self::validateNumericValue($superficie_jardin, "Superficie jardin");
            }
        }

        // Handle Superficie jardin calculée
        if (array_key_exists("Superficie jardin calculée", $row)) {
            $superficie_jardin_calculee = $row['Superficie jardin calculée'];
            if ($superficie_jardin_calculee === null || $superficie_jardin_calculee === 'SYNDIC PROPOSE' || $superficie_jardin_calculee === 'SYNDIC PLAN') {
                $bien->superficie_jardin_calculer = 0;
            } else {
                $bien->superficie_jardin_calculer = self::validateNumericValue($superficie_jardin_calculee, "Superficie jardin calculée");
            }
        } else {
            // If not provided, use half the value of superficie_jardin
            $bien->superficie_jardin_calculer = ($bien->superficie_jardin ?? 0) / 2;
        }

        // Handle Superficie terrasse
        if (array_key_exists("Superficie terrasse", $row)) {
            $superficie_terrasse = $row['Superficie terrasse'];
            if ($superficie_terrasse === null || $superficie_terrasse === 'SYNDIC PROPOSE' || $superficie_terrasse === 'SYNDIC PLAN') {
                $bien->superficie_terrasse = 0;
            } else {
                $bien->superficie_terrasse = self::validateNumericValue($superficie_terrasse, "Superficie terrasse");
            }
        }

        // Handle Superficie terrasse calculée
        if (array_key_exists("Superficie terrasse calculée", $row)) {
            $superficie_terrasse_calculee = $row['Superficie terrasse calculée'];
            if ($superficie_terrasse_calculee === null || $superficie_terrasse_calculee === 'SYNDIC PROPOSE' || $superficie_terrasse_calculee === 'SYNDIC PLAN') {
                $bien->superficie_terrasse_calculer = 0;
            } else {
                $bien->superficie_terrasse_calculer = self::validateNumericValue($superficie_terrasse_calculee, "Superficie terrasse calculée");
            }
        } else {
            // If not provided, use half the value of superficie_terrasse
            $bien->superficie_terrasse_calculer = ($bien->superficie_terrasse ?? 0) / 2;
        }

        if (array_key_exists("Superficie architecte", $row) && $row['Superficie architecte'] != null) {
            $bien->superficie_architecte = self::validateNumericValue($row['Superficie architecte'], "Superficie architecte");
        } else {
            $bien->superficie_architecte = 0;
        }

        if (array_key_exists("Superficie habitable", $row) && $row['Superficie habitable'] != null) {
            $bien->superficie_habitable = self::validateNumericValue($row['Superficie habitable'], "Superficie habitable");
        } else {
            $bien->superficie_habitable = 0;
        }

        if (array_key_exists("Prix unitaire", $row) && $row['Prix unitaire'] != null) {
            $bien->prix_unitaire = self::validateNumericValue($row['Prix unitaire'], "Prix unitaire");
        } else {
            $bien->prix_unitaire = 0;
        }

        if (array_key_exists("Orientation", $row) && $row['Orientation'] != null) {
            $bien->orientation = $row['Orientation'];
        } else {
            $bien->orientation = 'N';
        }

        if (array_key_exists("Avance minimale", $row) && $row['Avance minimale'] != null) {
            $bien->avance_minimale = self::validateNumericValue($row['Avance minimale'], "Avance minimale");
        } else {
            $bien->avance_minimale = 0;
        }

        if (array_key_exists("Nombre facades", $row) && $row['Nombre facades'] != null) {
            $bien->nbre_facades = self::validateNumericValue($row['Nombre facades'], "Nombre facades");
        } else {
            $bien->nbre_facades = 0;
        }

        if (array_key_exists("Superficie totale", $row) && $row['Superficie totale'] != null) {
            $bien->superficie_total = $row['Superficie totale'];
        } else {
            $bien->superficie_total = $bien->superficie_habitable + $bien->superficie_balcon + $bien->superficie_terrasse + $bien->superficie_jardin;
        }

        $bien->superficie_vendable = $bien->superficie_habitable + $bien->superficie_balcon_calculer + $bien->superficie_terrasse_calculer + $bien->superficie_jardin_calculer;
        $bien->prix                = $bien->prix_unitaire * $bien->superficie_total + $bien->prix_parking + $bien->prix_box;
        $bien->etat                = 'disponible';

        if ($bien->save()) {
            // Utilisation de validateNumericValue avec array_key_exists comme dans votre code original
            $nb_chambre   = (array_key_exists("Nombre chambre", $row) && $row['Nombre chambre'] != null) ?
                            self::validateNumericValue($row['Nombre chambre'], "Nombre chambre") : 0;

            $nb_salon     = (array_key_exists("Nombre salon", $row) && $row['Nombre salon'] != null) ?
                            self::validateNumericValue($row['Nombre salon'], "Nombre salon") : 0;

            $nb_cuisine   = (array_key_exists("Nombre cuisine", $row) && $row['Nombre cuisine'] != null) ?
                            self::validateNumericValue($row['Nombre cuisine'], "Nombre cuisine") : 0;

            $nb_sdb       = (array_key_exists("Nombre sdb", $row) && $row['Nombre sdb'] != null) ?
                            self::validateNumericValue($row['Nombre sdb'], "Nombre sdb") : 0;

            $nb_hall      = (array_key_exists("Nombre hall", $row) && $row['Nombre hall'] != null) ?
                            self::validateNumericValue($row['Nombre hall'], "Nombre hall") : 0;

            $nb_placard   = (array_key_exists("Nombre placard", $row) && $row['Nombre placard'] != null) ?
                            self::validateNumericValue($row['Nombre placard'], "Nombre placard") : 0;

            $nb_balcon    = (array_key_exists("Nombre balcon", $row) && $row['Nombre balcon'] != null) ?
                            self::validateNumericValue($row['Nombre balcon'], "Nombre balcon") : 0;

            $nb_terasse   = (array_key_exists("Nombre terasse", $row) && $row['Nombre terasse'] != null) ?
                            self::validateNumericValue($row['Nombre terasse'], "Nombre terasse") : 0;

            $nb_buanderie = (array_key_exists("Nombre buanderie", $row) && $row['Nombre buanderie'] != null) ?
                            self::validateNumericValue($row['Nombre buanderie'], "Nombre buanderie") : 0;

            $nb_reception = (array_key_exists("Nombre reception", $row) && $row['Nombre reception'] != null) ?
                            self::validateNumericValue($row['Nombre reception'], "Nombre reception") : 0;

            // Vérifier si au moins une composition a une valeur non nulle
            if ($nb_chambre != 0 || $nb_salon != 0 || $nb_cuisine != 0 || $nb_sdb != 0 ||
                $nb_hall != 0 || $nb_placard != 0 || $nb_balcon != 0 || $nb_terasse != 0 ||
                $nb_buanderie != 0 || $nb_reception != 0) {

                $compo = new CompositionBien();
                $compo->setConnection('temp');
                $compo->bien_id         = $bien->id;
                $compo->nbre_chambres   = $nb_chambre;
                $compo->nbre_salons     = $nb_salon;
                $compo->nbre_sdb        = $nb_sdb;
                $compo->nbre_cuisines   = $nb_cuisine;
                $compo->nbre_balcons    = $nb_balcon;
                $compo->nbre_terasses   = $nb_terasse;
                $compo->nbre_placards   = $nb_placard;
                $compo->nbre_halls      = $nb_hall;
                $compo->nbre_buanderies = $nb_buanderie;
                $compo->nbre_receptions = $nb_reception;
                $compo->save();
            }

            Bien_Helper::store_bien_frein($bien->id, 'import');
        }
    }
}

    /*if (array_key_exists("composition",$row)){
                    $pattern = "/[,\s.]/";
                    $exp=preg_split($pattern, $row['composition']);
                    $balcon=0;
                    $chambre=0;
                    $salon=0;
                    $cuisin=0;
                    $sdb=0;
                    $placard=0;
                    $terasse=0;
                    for($i=0;$i<=count($exp)-1;$i++){
                        if (str_contains($exp[$i], 'CHAMBRES')) {
                            $chambre=explode("CHAMBRES",$exp[$i]);
                            $chambre=$chambre[0];
                        }elseif (str_contains($exp[$i], 'PLACARDS')) {
                            $placard=explode("PLACARDS",$exp[$i]);
                            $placard=$placard[0];
                        }
                        elseif (str_contains($exp[$i], 'SALON')) {
                            $salon=explode("SALON",$exp[$i]);
                            $salon=$salon[0];
                        }
                        elseif (str_contains($exp[$i], 'CUISINE')) {
                            $cuisin=explode("CUISINE",$exp[$i]);
                            $cuisin=$cuisin[0];
                        }
                        elseif (str_contains($exp[$i], 'SDB')) {
                            $sdb=explode("SDB",$exp[$i]);
                            $sdb=$sdb[0];
                        }
                        elseif (str_contains($exp[$i], 'TERRASSE')) {
                            $terassse=explode("TERRASSE",$exp[$i]);
                            $terassse=$terassse[0];
                        }
                        elseif (str_contains($exp[$i], 'BALCON')) {
                            $balcon=explode("BALCON",$exp[$i]);
                            $balcon=$balcon[0];
                        }

                    }
                                    $compo=new CompositionBien();
                                    $compo->setConnection('temp');
                                    $compo->bien_id=$bien->id;
                                    $compo->nbre_chambres=$chambre;
                                    $compo->nbre_salons=$salon;
                                    $compo->nbre_sdb=$sdb;
                                    $compo->nbre_cuisines=$cuisin;
                                    $compo->nbre_balcons=$balcon;
                                    $compo->nbre_terasses=$terasse;
                                    $compo->nbre_placards=$placard;
                                    $compo->save();

    }*/


   public static function updateBienByExcel($projet_id, $row)
    {
        \Log::info("🔄 updateBienByExcel called for ID: " . ($row['ID'] ?? 'Unknown'));

        // Add field mapping to handle different field names
        $fieldMapping = [
            'Nombre chambres' => 'Nombre chambre',
            'Nombre salons' => 'Nombre salon',
            'Nombre salles de bain' => 'Nombre sdb',
            'Nombre cuisines' => 'Nombre cuisine',
            'Nombre halls' => 'Nombre hall',
            'Nombre terrasses' => 'Nombre terasse',
            'Nombre balcons' => 'Nombre balcon',
            'Nombre buanderies' => 'Nombre buanderie',
            'Nombre placards' => 'Nombre placard',
            'Nombre réceptions' => 'Nombre reception'
        ];

        // Map fields to expected names
        foreach ($fieldMapping as $jsonField => $expectedField) {
            if (array_key_exists($jsonField, $row) && !array_key_exists($expectedField, $row)) {
                $row[$expectedField] = $row[$jsonField];
            }
        }

        // Validate that ID exists and is numeric
        if (!isset($row['ID']) || $row['ID'] === null || $row['ID'] === '') {
            throw new \Exception("ID manquant dans la ligne");
        }


        if (!is_numeric($row['ID'])) {
            throw new \Exception("ID doit être numérique. Trouvé: " . $row['ID']);
        }

        $bien = Bien::on('temp')->where('id', $row['ID'])->where(function ($query) use ($row, $projet_id) {
            $query->where('projet_id', $projet_id);
        })->first();

        if (!$bien) {
            \Log::warning("Bien not found with ID: {$row['ID']} in projet: {$projet_id}");
            throw new \Exception("Bien non trouvé avec ID: {$row['ID']} dans le projet {$projet_id}");
        }
            \Log::info("✅ Bien found: ID {$bien->id}, Numéro: {$bien->numero}");


        // Collect ALL errors for this row
        $errors = [];

        // 1. Champs requis avec messages personnalisés
        $requiredFields = [
            "Numero" => "Numéro du bien manquant",
            "Type" => "Type de bien manquant",
             "Prix" => "Prix manquant",
            "Prix unitaire" => "Prix Unitaire manquant",
        ];

        // In updateBienByExcel, add debugging for required fields
        foreach ($requiredFields as $key => $message) {
            if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                $errors[] = $message;
                    \Log::info("✅ Bien found: ID {$bien->id}, Numéro: {$bien->numero}");

            }
        }

        // 2. Vérification des champs numériques - collect all numeric errors
        $numericFields = [
            'Prix unitaire',
            'Prix',
            'Prix parking',
            'Prix box',
            'Avance mininmale',
            'Superficie architecte',
            'Superficie habitable',
            'Superficie parking',
            'Superficie box',
            'Superficie terrasse',
            'Superficie terrasse calculée',
            'Superficie balcon',
            'Superficie balcon calculée',
            'Superficie jardin',
            'Superficie jardin calculée',
            'Superficie totale',
            'Superficie vendable',
            'Nombre facades',
        ];

     foreach ($numericFields as $key) {
            if (isset($row[$key]) && $row[$key] !== '' && !is_numeric($row[$key])) {
                $errors[] = "Le champ '{$key}' doit être un nombre. Trouvé: " . $row[$key];
                \Log::warning("Non-numeric value for {$key}: " . $row[$key]);
            }
        }
        // If there are any errors, throw exception with ALL errors
        if (!empty($errors)) {
            $errorMessage = "Erreurs validation pour ID {$row['ID']}: " . implode(' | ', $errors);
            \Log::error($errorMessage);
            throw new \Exception($errorMessage);
        }

        \Log::info("✅ All validations passed for ID: {$row['ID']}");

        // Validate that Type bien is numeric
        if (array_key_exists("Type", $row) && $row['Type'] !== null && $row['Type'] !== '') {
            if (!is_numeric($row['Type'])) {
                $errors[] = "Le champ 'Type bien' doit être un ID numérique. Valeur trouvée : " . $row['Type'];
            }
        }

        // 3. Validation Orientation Enum
        if (array_key_exists("Orientation", $row) && $row['Orientation'] !== null && $row['Orientation'] !== '') {
            $validOrientations = [
                'N' => 1, 'E' => 2, 'S' => 3, 'O' => 4,
                'N_E' => 5, 'N_O' => 6, 'S_E' => 7, 'S_O' => 8
            ];

            if (!array_key_exists($row['Orientation'], $validOrientations)) {
                $errors[] = "Orientation invalide. Valeurs acceptées: " . implode(', ', array_keys($validOrientations)) . ". Trouvé: " . $row['Orientation'];
            }
        }

        // 4. Validation Statut Enum
        if (array_key_exists("Statut", $row) && $row['Statut'] !== null && $row['Statut'] !== '') {
            $validStatus = [
                'Disponible' => 1,
                'Pré-réservé' => 2,
                'Réservé' => 3,
                'Bloqué' => 4,
                'Vendu' => 5,
                'En cours de proposition' => 6
            ];

            if (!array_key_exists($row['Statut'], $validStatus)) {
                $errors[] = "Statut invalide. Valeurs acceptées: " . implode(', ', array_keys($validStatus)) . ". Trouvé: " . $row['Statut'];
            }
        }

        // 5. Validation boolean conventionne
        if (array_key_exists("conventionne", $row) && !in_array($row['conventionne'], [0, 1, '0', '1', true, false, 'true', 'false'], true)) {
            $errors[] = "Valeur invalide pour le champ 'conventionné'. Doit être 0 ou 1 (booléen)";
        }

        // 6. Vérification projet_id s'il est requis pour la relation avec type bien
        if (!$projet_id) {
            $errors[] = "Projet ID manquant";
        }

        // If there are any errors, throw exception with ALL errors
        if (!empty($errors)) {
            throw new \Exception(implode(' | ', $errors));
        }

        // Continue with processing if no errors...
        if (array_key_exists("Nom", $row) && $row['Nom'] != null) {
            $bien->propriete_dite_bien = $row['Nom'];
        }

        if (str_contains($row['Numero'], 'Appt')) {
            $explode_numero = explode("Appt", $row['Numero']);
            $num            = $explode_numero[1];
        } elseif (str_contains($row['Numero'], 'APP')) {
            $explode_numero = explode("APP", $row['Numero']);
            $num            = $explode_numero[1];
        } else {
            $num = $row['Numero'];
        }
        $bien->numero = $num;

        $nv = 0;
        if (array_key_exists("Etage", $row)) {
            if (str_contains($row['Etage'], 'er etage')) {
                $explode_niveau_1 = explode("er etage", $row['Etage']);
                $nv               = $explode_niveau_1[0];
            } elseif (str_contains($row['Etage'], 'er étage')) {
                $explode_niveau_5 = explode("er étage", $row['Etage']);
                $nv               = $explode_niveau_5[0];
            } elseif (str_contains($row['Etage'], 'eme etage')) {
                $explode_niveau_2 = explode("eme etage", $row['Etage']);
                $nv               = $explode_niveau_2[0];
            } elseif (str_contains($row['Etage'], 'ème etage')) {
                $explode_niveau_3 = explode("ème etage", $row['Etage']);
                $nv               = $explode_niveau_3[0];
            } elseif (str_contains($row['Etage'], 'ème étage')) {
                $explode_niveau_4 = explode("ème étage", $row['Etage']);
                $nv               = $explode_niveau_4[0];
            } elseif (str_contains($row['Etage'], 'RDC')) {
                $nv = 0;
            }
        } else {
            $nv = NULL;
        }

        $bien->niveau = $nv;

        if (array_key_exists("Type", $row) && $row['Type'] != null) {
            $type      = TypeBien::on('temp')->where('projet_id', $projet_id)->get();
            $typeFound = false;
            foreach ($type as $key => $value) {
                if ($value->id == intval($row['Type'])) {
                    $bien->type_id = $value->id;
                    $typeFound     = true;
                    break;
                }
            }
            if (!$typeFound) {
                throw new \Exception("Type de bien invalide ou non trouvé");
            }
        }

        if (array_key_exists("Prix parking", $row) && $row['Prix parking'] != null) {
            $bien->prix_parking = self::validateNumericValue($row['Prix parking'], "Prix parking");
        } else {
            $bien->prix_parking = 0;
        }

        if (array_key_exists("Prix box", $row) && $row['Prix box'] != null) {
            $bien->prix_box = self::validateNumericValue($row['Prix box'], "Prix box");
        } else {
            $bien->prix_box = 0;
        }

        // Handle Superficie balcon
        if (array_key_exists("Superficie balcon", $row)) {
            $superficie_balcon = $row['Superficie balcon'];
            if ($superficie_balcon === null || $superficie_balcon === 'SYNDIC PROPOSE' || $superficie_balcon === 'SYNDIC PLAN') {
                $bien->superficie_balcon = 0;
            } else {
                $bien->superficie_balcon = self::validateNumericValue($row['Superficie balcon'], "Superficie balcon");
            }
        }

        // Handle Superficie balcon calculée
        if (array_key_exists("Superficie balcon calculée", $row)) {
            $superficie_balcon_calculee = $row['Superficie balcon calculée'];
            if ($superficie_balcon_calculee === null || $superficie_balcon_calculee === 'SYNDIC PROPOSE' || $superficie_balcon_calculee === 'SYNDIC PLAN') {
                $bien->superficie_balcon_calculer = 0;
            } else {
                $bien->superficie_balcon_calculer = self::validateNumericValue($superficie_balcon_calculee, "Superficie balcon calculée");
            }
        } else {
            // If not provided, use half the value of superficie_balcon
            $bien->superficie_balcon_calculer = ($bien->superficie_balcon ?? 0) / 2;
        }

        // Handle Superficie jardin
        if (array_key_exists("Superficie jardin", $row)) {
            $superficie_jardin = $row['Superficie jardin'];
            if ($superficie_jardin === null || $superficie_jardin === 'SYNDIC PROPOSE' || $superficie_jardin === 'SYNDIC PLAN') {
                $bien->superficie_jardin = 0;
            } else {
                $bien->superficie_jardin = self::validateNumericValue($superficie_jardin, "Superficie jardin");
            }
        }

        // Handle Superficie jardin calculée
        if (array_key_exists("Superficie jardin calculée", $row)) {
            $superficie_jardin_calculee = $row['Superficie jardin calculée'];
            if ($superficie_jardin_calculee === null || $superficie_jardin_calculee === 'SYNDIC PROPOSE' || $superficie_jardin_calculee === 'SYNDIC PLAN') {
                $bien->superficie_jardin_calculer = 0;
            } else {
                $bien->superficie_jardin_calculer = self::validateNumericValue($superficie_jardin_calculee, "Superficie jardin calculée");
            }
        } else {
            // If not provided, use half the value of superficie_jardin
            $bien->superficie_jardin_calculer = ($bien->superficie_jardin ?? 0) / 2;
        }

        // Handle Superficie terrasse
        if (array_key_exists("Superficie terrasse", $row)) {
            $superficie_terrasse = $row['Superficie terrasse'];
            if ($superficie_terrasse === null || $superficie_terrasse === 'SYNDIC PROPOSE' || $superficie_terrasse === 'SYNDIC PLAN') {
                $bien->superficie_terrasse = 0;
            } else {
                $bien->superficie_terrasse = self::validateNumericValue($superficie_terrasse, "Superficie terrasse");
            }
        }

        // Handle Superficie terrasse calculée
        if (array_key_exists("Superficie terrasse calculée", $row)) {
            $superficie_terrasse_calculee = $row['Superficie terrasse calculée'];
            if ($superficie_terrasse_calculee === null || $superficie_terrasse_calculee === 'SYNDIC PROPOSE' || $superficie_terrasse_calculee === 'SYNDIC PLAN') {
                $bien->superficie_terrasse_calculer = 0;
            } else {
                $bien->superficie_terrasse_calculer = self::validateNumericValue($superficie_terrasse_calculee, "Superficie terrasse calculée");
            }
        } else {
            // If not provided, use half the value of superficie_terrasse
            $bien->superficie_terrasse_calculer = ($bien->superficie_terrasse ?? 0) / 2;
        }

        if (array_key_exists("Superficie architecte", $row) && $row['Superficie architecte'] != null) {
            $bien->superficie_architecte = self::validateNumericValue($row['Superficie architecte'], "Superficie architecte");
        } else {
            $bien->superficie_architecte = 0;
        }

        if (array_key_exists("Superficie habitable", $row) && $row['Superficie habitable'] != null) {
            $bien->superficie_habitable = self::validateNumericValue($row['Superficie habitable'], "Superficie habitable");
        } else {
            $bien->superficie_habitable = 0;
        }

        if (array_key_exists("Prix unitaire", $row) && $row['Prix unitaire'] != null) {
            $bien->prix_unitaire = self::validateNumericValue($row['Prix unitaire'], "Prix unitaire");
        } else {
            $bien->prix_unitaire = 0;
        }

        // Set Orientation with enum validation
        if (array_key_exists("Orientation", $row) && $row['Orientation'] != null) {
            $bien->orientation = $row['Orientation'];
        } else {
            $bien->orientation = 'N';
        }

        // Set Statut with enum validation
        if (array_key_exists("Statut", $row) && $row['Statut'] != null) {
            $statusMapping = [
                'Disponible' => 'disponible',
                'Pré-réservé' => 'pre_reservation',
                'Réservé' => 'reservation',
                'Bloqué' => 'bloque',
                'Vendu' => 'vendu',
                'En cours de proposition' => 'encours_de_proposition'
            ];
            $bien->etat = $statusMapping[$row['Statut']] ?? 'disponible';
        } else {
            $bien->etat = 'disponible';
        }

        if (array_key_exists("Avance minimale", $row) && $row['Avance minimale'] != null) {
            $bien->avance_minimale = self::validateNumericValue($row['Avance minimale'], "Avance minimale");
        } else {
            $bien->avance_minimale = 0;
        }

        if (array_key_exists("Nombre facades", $row) && $row['Nombre facades'] != null) {
            $bien->nbre_facades = self::validateNumericValue($row['Nombre facades'], "Nombre facades");
        } else {
            $bien->nbre_facades = 0;
        }

        if (array_key_exists("Superficie totale", $row) && $row['Superficie totale'] != null) {
            $bien->superficie_total = $row['Superficie totale'];
        } else {
            $bien->superficie_total = $bien->superficie_habitable + $bien->superficie_balcon + $bien->superficie_terrasse + $bien->superficie_jardin;
        }

        $bien->superficie_vendable = $bien->superficie_habitable + $bien->superficie_balcon_calculer + $bien->superficie_terrasse_calculer + $bien->superficie_jardin_calculer;
       // $bien->prix                = $bien->prix_unitaire * $bien->superficie_total + $bien->prix_parking + $bien->prix_box;
        $bien->prix                = $row['Prix'];

        if ($bien->save()) {
            \Log::info("✅ Bien saved successfully: ID {$bien->id}");

            // Handle composition...
            $compo = CompositionBien::on('temp')->where('bien_id', $bien->id)->first();
            if ($compo) {
                \Log::info("✅ Composition found and updated for bien: {$bien->id}");
            } else {
                \Log::info("✅ New composition created for bien: {$bien->id}");
            }
            if (!$compo) {
                $compo = new CompositionBien();
                $compo->setConnection('temp');
                $compo->bien_id = $bien->id;
            }

            // Utilisation de validateNumericValue avec array_key_exists
            $nb_chambre   = (array_key_exists("Nombre chambre", $row) && $row['Nombre chambre'] != null) ?
                            self::validateNumericValue($row['Nombre chambre'], "Nombre chambre") : 0;

            $nb_salon     = (array_key_exists("Nombre salon", $row) && $row['Nombre salon'] != null) ?
                            self::validateNumericValue($row['Nombre salon'], "Nombre salon") : 0;

            $nb_cuisine   = (array_key_exists("Nombre cuisine", $row) && $row['Nombre cuisine'] != null) ?
                            self::validateNumericValue($row['Nombre cuisine'], "Nombre cuisine") : 0;

            $nb_sdb       = (array_key_exists("Nombre sdb", $row) && $row['Nombre sdb'] != null) ?
                            self::validateNumericValue($row['Nombre sdb'], "Nombre sdb") : 0;

            $nb_hall      = (array_key_exists("Nombre hall", $row) && $row['Nombre hall'] != null) ?
                            self::validateNumericValue($row['Nombre hall'], "Nombre hall") : 0;

            $nb_placard   = (array_key_exists("Nombre placard", $row) && $row['Nombre placard'] != null) ?
                            self::validateNumericValue($row['Nombre placard'], "Nombre placard") : 0;

            $nb_balcon    = (array_key_exists("Nombre balcon", $row) && $row['Nombre balcon'] != null) ?
                            self::validateNumericValue($row['Nombre balcon'], "Nombre balcon") : 0;

            $nb_terasse   = (array_key_exists("Nombre terasse", $row) && $row['Nombre terasse'] != null) ?
                            self::validateNumericValue($row['Nombre terasse'], "Nombre terasse") : 0;

            $nb_buanderie = (array_key_exists("Nombre buanderie", $row) && $row['Nombre buanderie'] != null) ?
                            self::validateNumericValue($row['Nombre buanderie'], "Nombre buanderie") : 0;

            $nb_reception = (array_key_exists("Nombre reception", $row) && $row['Nombre reception'] != null) ?
                            self::validateNumericValue($row['Nombre reception'], "Nombre reception") : 0;

            // Update composition values
            $compo->nbre_chambres   = $nb_chambre;
            $compo->nbre_salons     = $nb_salon;
            $compo->nbre_sdb        = $nb_sdb;
            $compo->nbre_cuisines   = $nb_cuisine;
            $compo->nbre_balcons    = $nb_balcon;
            $compo->nbre_terasses   = $nb_terasse;
            $compo->nbre_placards   = $nb_placard;
            $compo->nbre_halls      = $nb_hall;
            $compo->nbre_buanderies = $nb_buanderie;
            $compo->nbre_receptions = $nb_reception;
            $compo->save();

            // Store bien frein only if status is "disponible"
            if ($bien->etat == 'disponible') {
                Bien_Helper::store_bien_frein($bien->id, 'import');
            }
            return $bien;
        } else {
            \Log::error("❌ Failed to save bien: ID {$bien->id}");
            throw new \Exception("Échec de sauvegarde du bien ID: {$bien->id}");
        }

    }




    public static function libererBien($id, $text, $dst_id,$mode_proposition)
    {
        //bu default $mode_proposition =false
        if ($text != 'console') {
            $user     = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        }
        $bien       = Bien::on('temp')->findOrfail($id);
        $reservedStates = ['RESERVATION', 'PRE_RESERVATION'];
        //on cas de proposition  si bien n'est pas reserve et pre rserve ou le mode false !$mode_proposition (le cas normal sans proposition )
        $shouldLiberate = !$mode_proposition ||
                     ($mode_proposition && !in_array($bien->etat, $reservedStates));

        if ($shouldLiberate) {
                $bien->etat = EtatBien::DISPONIBLE->value;
                //  $bien->desistement_id=$dst_id;
                if ($bien->save()) {
                    Bien_Helper::store_bien_frein($bien->id, $text);
                    //UPDATE DERNIER VISITE pre reserve=>pre reserve_perdu // vendu==>reservation_perdu
                    $visite = Visite::on('temp')->where('bien_id', $id)->where('interet', InteretEnum::Intéressé->value)->orderBy('created_at', 'DESC')->first();
                    if ($visite != null) {
                        if ($text == 'console') {
                            //pre reserve
                            if ($visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                $visite->statut = StatutVisiteEnum::Pré_Réservation_Perdu->value;
                            } elseif ($visite->statut == StatutVisiteEnum::Vendu->value) {
                                $visite->statut = StatutVisiteEnum::Réservation_Perdu->value;
                            }
                            $visite->save();
                        }

                        //SUPPRIMER LES OLDS NOTIF
                        $notif_old_relance = Notification::on('temp')->where(function ($query) {
                            $query->where('type', 1)
                                ->orwhere('type', 2);
                        })
                            ->where(function ($query_2) use ($visite) {
                                $query_2->where('visite_id', $visite->id);
                            })
                            ->get();
                        if (($notif_old_relance->count()) > 0) {
                            foreach ($notif_old_relance as $nt_r) {
                                $nt_r->delete();
                            }
                        }
                        /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                        $old_relances_rdv = Relance_Rdv_Visite::on('temp')->where('visite_id', $visite->id)->where('type_traitement', 0)->get();
                        if (count($old_relances_rdv) > 0) {
                            foreach ($old_relances_rdv as $old) {
                                $old->type_traitement = 2; //auto
                                $old->date_traitement = Carbon::now();
                                //si old visite pre reserve en suite n visite vendu ==>user_id_traite(l'ancien user)
                                if ($old->visite->statut == StatutVisiteEnum::Pré_Réservation->value) {
                                    if ($visite->statut == StatutVisiteEnum::Vendu->value) {
                                        $old->user_id_traite = $visite->user_id;
                                    } else {
                                        $old->user_id_traite = $text != 'console' ? $userAuth->value('id') : null;
                                    }
                                } else {
                                    $old->user_id_traite = $text != 'console' ? $userAuth->value('id') : null;
                                }
                                $old->save();
                            }

                        }
                    }
                    //traitement Frein
                    $traitement_frein = TraitementFrein::on('temp')->where('bien_id', $id)->where('interet', InteretEnum::Intéressé->value)->where('statut', 1)->orderBy('created_at', 'DESC')->first();
                    if ($traitement_frein != null) {
                        $traitement_frein->statut = StatutVisiteEnum::Pré_Réservation_Perdu->value;
                        $traitement_frein->save();
                        //SUPPRIMER LES OLDS NOTIF
                        $notif_old_relance = Notification::on('temp')->where(function ($query) {
                            $query->where('type', 1)
                                ->orwhere('type', 2);
                        })
                            ->where(function ($query_2) use ($traitement_frein) {
                                $query_2->where('visite_id', $traitement_frein->visite_id);
                            })
                            ->get();
                        if (($notif_old_relance->count()) > 0) {
                            foreach ($notif_old_relance as $nt_r) {
                                $nt_r->delete();
                            }
                        }
                        /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                        $old_relances_rdv = Relance_Rdv_Visite::on('temp')->where('visite_id', $traitement_frein->visite_id)->where('type_traitement', 0)->get();
                        if (count($old_relances_rdv) > 0) {
                            foreach ($old_relances_rdv as $old) {
                                $old->type_traitement = 2; //auto
                                $old->date_traitement = Carbon::now();
                                $old->user_id_traite  = null;
                                $old->save();
                            }

                        }
                    }
                }
                if ($text == 'console') {
                    HistoriqueBienHelper::createHistoriqueBien(1, "liberation automatique", $id, null, null, null, null, null);
                } else {
                    HistoriqueBienHelper::createHistoriqueBien(4, "liberer", $id, Auth::guard('api')->user()->id, null, null, null, null);

                }
            }

        }
    public static function store_bien_frein($id, $text)
    {
                \Log::info("im inside bien frein: " . ($id));

        if ($text != 'console' && $text != 'import') {
            DatabaseHelper::Config();
        }
        $bien        = Bien::on('temp')->findorfail($id);
        $array_fr_id = [];
        $freins      = Frein::on('temp')
            ->join('visites', 'visites.id', '=', 'freins.visite_id')
            ->leftjoin('frein_tranches', 'frein_tranches.frein_id', '=', 'freins.id')
            ->leftjoin('frein_etages', 'frein_etages.frein_id', '=', 'freins.id')
            ->leftjoin('frein_orientations', 'frein_orientations.frein_id', '=', 'freins.id')
            ->leftjoin('frein_typologies', 'frein_typologies.frein_id', '=', 'freins.id')
            ->leftjoin('frein_vues', 'frein_vues.frein_id', '=', 'freins.id')
            ->select('freins.id', 'freins.tranche as fr_tranche', 'freins.etage as fr_etage',
                'freins.orientation as fr_orientation', 'freins.typologie as fr_typologie',
                'freins.vue as fr_vue', 'freins.prix_min as fr_prix_min', 'freins.prix_max as fr_prix_max',
                'freins.superficie_min as fr_superficie_min', 'freins.superficie_max as fr_superficie_max',
                'frein_tranches.tranche_id', 'frein_etages.etage',
                'frein_orientations.orientation', 'frein_typologies.typologie_id', 'frein_vues.vue_id', 'freins.avance as fr_avance'
            )
            ->where('visites.projet_id', $bien->projet_id)
            ->whereIN('freins.etat', [1, 2, 6])
            ->where('visites.etat', 1)
            ->get();

        foreach ($freins as $fr) {

            if (($fr->fr_tranche == 1 && $fr->tranche_id == $bien->tranche_id)
                || ($fr->fr_etage == 1 && $fr->etage == $bien->niveau)
                || ($fr->fr_orientation == 1 && $fr->orientation == $bien->orientation)
                || ($fr->fr_typologie == 1 && $fr->typologie_id == $bien->typologie_id)
                || ($fr->fr_vue == 1 && $fr->vue_id == $bien->vue_id)
                || ($fr->fr_prix_min != null && $fr->fr_prix_min <= $bien->prix)
                || ($fr->fr_prix_max != null && $fr->fr_prix_max >= $bien->prix)
                || ($fr->fr_superficie_min != null && $fr->fr_superficie_min <= $bien->superficie_habitable)
                || ($fr->fr_superficie_max != null && $fr->fr_superficie_max >= $bien->superficie_habitable)
                || (($fr->fr_avance != null || $fr->fr_avance != 0) && $fr->fr_avance <= $bien->avance_minimale)
            ) {
                $exist = 0;
                //test si id du frein exist dans array
                if (count($array_fr_id) == 0) {
                    array_push($array_fr_id, $fr->id);
                } else {
                    //si array.lenght!=0 test si id du frein exist dans array
                    for ($i = 0; $i <= sizeof($array_fr_id) - 1; $i++) {
                        if ($array_fr_id[$i] == $fr->id) {
                            $exist = 1;
                        }
                    }
                    if ($exist == 0) {
                        array_push($array_fr_id, $fr->id);
                    }
                }

            }

        }
        //store to table frein_bien
        if (count($array_fr_id) > 0) {
            foreach ($array_fr_id as $id_fr) {
                //if bien_id already exist with this frein (en cas d update bien ->disponible)
                $count_exist_fr_bien = Frein_Bien::on('temp')->where('bien_id', $id)->where('frein_id', $id_fr)->count();
                if ($count_exist_fr_bien == 0) {
                    FreinBienHelper::createFreinBien($bien->id, $id_fr);
                }
            }
        }
        return response()->json(['message' => $bien], 200);

    }

}
