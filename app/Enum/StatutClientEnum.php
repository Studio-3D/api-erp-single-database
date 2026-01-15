<?php

namespace App\Enum;

enum StatutClientEnum: int
{
    case Nouvelle_Avance = 1;
    case Suivi_Avancement_travaux = 2;
    case Demande_des_documents = 3;
    case Autre = 4;
    case Creation_Reservation = 5;
    case Ajout_Rendez_Vous = 6;
    case Signature_Attestation_Vente = 7;
    case Signature_Contrat_Vente = 8;
    case Remise_Cle = 9;
    case Desistement_dd = 10;
    case Desistement_dp_profit = 11;
    case Desistement_dp_co = 12;
    case Desistement_dp_partiel = 13;
    case Desistement_change_bien = 14;
    case Penalite_valide = 15;
    case Remise_du_remboursement = 16;//Phase physique, le client reçoit l'argent/le chèque
    case Decaissement_effctue = 17;//Phase financière, l'argent est effectivement débité du compte
    case Penalite_rejete = 18;



    public function description(): string
    {
        return match($this) {
            self::Nouvelle_Avance => 'Le client souhaite effectuer un nouveau paiement',
            self::Suivi_Avancement_travaux => 'Le client a des questions sur l\'avancement des travaux',
            self::Demande_des_documents  => 'Le client a des questions concernant les documents',
            self::Autre => 'Autre type de question non spécifiée',
            self::Creation_Reservation => 'Processus de création de réservation',
            self::Ajout_Rendez_Vous => 'Ajout d\'un rendez-vous avec le client',
            self::Signature_Attestation_Vente => 'Signature de l\'attestation de vente',
            self::Signature_Contrat_Vente => 'Signature du contrat de vente définitif',
            self::Remise_Cle => 'Remise des clés du bien au client',
            self::Desistement_dd => 'Désistement avec dépôt de garantie non restitué',
            self::Desistement_dp_profit => 'Désistement avec dépôt de garantie à partager selon profit',
            self::Desistement_dp_co => 'Désistement avec dépôt de garantie pour co-acquéreur',
            self::Desistement_dp_partiel => 'Désistement avec dépôt de garantie partiellement restitué',
            self::Desistement_change_bien => 'Désistement pour changement de bien',
            self::Payer_penalite => 'Paiement de pénalités pour retard',
            self::Rembourser => 'Processus de remboursement en cours',
        };
    }






}
