<?php

namespace App\Enum;

enum StatutProspectEnum: int
{
    case En_attente           = 0;
    case Planification_RDV    = 1;
    case Injoignable          = 2;
    case Rappel               = 3;
    case Converti_en_visite   = 4;
    case Nouveau_appel        = 5;
    case Affecte              = 6;
    case Interesse            = 7;
    case Perdu                = 8;
    case Receptif             = 9;
    case Converti_en_client   = 10;
    case WhatsApp_Envoye      = 11;
    case pre_reservation      = 12;
    case Reaffecte            = 13;

    // 🆕 Nouveaux statuts
    case Qualifie             = 14;
    case En_veille            = 15;
    case Invalide             = 16;
    case Visite_non_honoree   = 17;
    case En_cours = 18;// en cours de discussion whatsapp

    public function label(): string
    {
        return match($this) {
            self::En_attente         => 'Nouveau',
            self::Planification_RDV  => 'Visite programmée',
            self::Injoignable        => 'Injoignable',
            self::Rappel             => 'A rappeler',
            self::Converti_en_visite => 'Converti en Visite',
            self::Nouveau_appel      => 'Nouveau',
            self::Affecte            => 'Affecté',
            self::Interesse          => 'Intéressé',
            self::Perdu              => 'Perdu',
            self::Receptif           => 'Réceptif',
            self::Converti_en_client => 'Visite effectuée',
            self::WhatsApp_Envoye    => 'WhatsApp Envoyé',
            self::pre_reservation    => 'Pré réservation',
            self::Reaffecte          => 'Réaffecté',

            // 🆕 Nouveaux labels
            self::Qualifie           => 'Qualifié',
            self::En_veille          => 'En veille',
            self::Invalide           => 'Invalide',
            self::Visite_non_honoree => 'Visite non honorée',
        };
    }
}
