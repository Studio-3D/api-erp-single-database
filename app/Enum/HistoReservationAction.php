<?php

namespace App\Enum;

enum HistoReservationAction: string
{
    case EN_ATTENTE = '1';
    case VALIDATION = '2';
    case MODIFICATION_RESERVATION = '3';
    case REJET = '4';
    case REJET_RELANCE_EN_COURS = '5';
    case DESISTEMENT_DD = '6';
    case DESISTEMENT_PROCHE = '7';
    case DESISTEMENT_CO_RESERVATAIRE = '8';
    case DESISTEMENT_PARTIEL = '9';
    case CHANGEMENT_BIEN = '10';
    case ATTESTATION_VENTE = '11';
    case CONTRAT_VENTE = '12';
    case Reconstitution_dossier = '13';//Dossier Régénéré //Création Nouvelle Réservation


    public function label(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'En attente',
            self::VALIDATION => 'Validation',
            self::MODIFICATION_RESERVATION => 'Modification Réservation',
            self::REJET => 'Rejet',
            self::REJET_RELANCE_EN_COURS => 'REJET-RELANCE EN COURS',
            self::DESISTEMENT_DD => 'Desistement dd',
            self::DESISTEMENT_PROCHE => 'Desistement proche',
            self::DESISTEMENT_CO_RESERVATAIRE => 'Desistement co reservataire',
            self::DESISTEMENT_PARTIEL => 'Desistement partiel',
            self::CHANGEMENT_BIEN => 'Changement de bien',
            self::ATTESTATION_VENTE => 'Attestation de vente',
            self::CONTRAT_VENTE => 'Contrat de vente',
            self::Reconstitution_dossier => 'Reconstitution dossier',

        };
    }
}
