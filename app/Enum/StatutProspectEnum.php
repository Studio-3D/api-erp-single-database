<?php

namespace App\Enum;

enum StatutProspectEnum: int
{
    case En_attente = 0;
    case Planification_RDV = 1;
    case Injoignable = 2;
    case Rappel = 3;
    case Converti_en_visite = 4;
    case Nouveau_appel = 5;
    case Affecte = 6;
    case Interesse = 7;
    case Perdu = 8;
    case Receptif = 9;
    case Converti_en_client = 10;

    public function label(): string
    {
        return match($this) {
            self::En_attente => 'En attente',
            self::Planification_RDV => 'Planification Rendez Vous',
            self::Injoignable => 'Injoignable',
            self::Rappel => 'Rappel',
            self::Converti_en_visite => 'Converti en Visite',
            self::Nouveau_appel => 'Nouveau Appel',
            self::Affecte => 'Affecté',
            self::Interesse => 'Intéressé',
            self::Perdu => 'Perdu',
            self::Receptif => 'Réceptif',
            self::Converti_en_client => 'Converti en client',
        };
    }
}
