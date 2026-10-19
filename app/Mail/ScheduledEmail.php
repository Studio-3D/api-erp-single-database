<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $type;
    public $projet;
    public $bien;
    public $prospectName;
    public $avance;
    public $source; // Nouveau champ pour identifier la source (appel/visite)
    public $tel;
    public $rdvDate; // Ajout de la propriété pour la date du RDV

    /**
     * Create a new message instance.
     */
    public function __construct($type, $user, $projet = null, $bien = null, $prospectName = null, $avance = null, $source = null, $tel = null, $rdvDate = null)
    {
        $this->type = $type;
        $this->user = $user;
        $this->bien = $bien;
        $this->projet = $projet;
        $this->prospectName = $prospectName;
        $this->avance = $avance;
        $this->source = $source; // 'appel' ou 'visite'
        $this->tel = $tel;
        $this->rdvDate = $rdvDate; // Date du rendez-vous

    }

    public function build()
    {
        $view = $this->getViewByType($this->type);
 // Formater la date du RDV si elle existe
        $formattedRdvDate = null;
        if ($this->rdvDate) {
            try {
                $formattedRdvDate = \Carbon\Carbon::parse($this->rdvDate)->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $formattedRdvDate = $this->rdvDate;
            }
        }
        return $this->view($view)
                    ->subject($this->getSubjectByType($this->type))
                    ->with([
                       // 'name' => $this->getFirstName(), // Changé ici
                        'name' => $this->getFullName(), // Ajouté si besoin du nom complet
                        'projet' => $this->projet,
                        'bien' => $this->bien,
                        'prospectName' => $this->prospectName,
                        'tel' => $this->tel ?? null,
                        'avance' => $this->avance,
                        'date' => now()->format('d/m/Y'),
                        'rdv' => $formattedRdvDate ?? now()->format('d/m/Y H:i'), // Utiliser la date du RDV ou now()
                        'montant' => $this->avance->montant ?? null,
                        'echeance' => $this->avance->echeance ?? null,
                        'source' => $this->source,
                    ])
                    ->from(env('MAIL_USERNAME'), env('CLIENT_NOM'));
    }

    /**
     * Get only the first name (prenom) or name
     */
    private function getFirstName()
    {
        // Pour un objet User avec name et prenom
        if (isset($this->user->prenom) && !empty($this->user->prenom)) {
            return $this->user->prenom;
        }

        // Pour un objet User avec seulement name
        if (isset($this->user->name) && !empty($this->user->name)) {
            // Si name contient "prenom nom", on prend le premier mot
            $nameParts = explode(' ', trim($this->user->name));
            return $nameParts[0];
        }

        // Pour un objet Prospect ou Client avec prenom
        if (isset($this->user->prenom)) {
            return $this->user->prenom;
        }

        // Fallback
        return 'Cher client';
    }

    /**
     * Get full name (optional - if needed elsewhere)
     */
    private function getFullName()
    {
        if (isset($this->user->name) && isset($this->user->prenom)) {
            return $this->user->name . ' ' . $this->user->prenom;
        }

        if (isset($this->user->name)) {
            return $this->user->name;
        }

        if (isset($this->user->nom) && isset($this->user->prenom)) {
            return $this->user->nom . ' ' . $this->user->prenom;
        }

        return null;
    }

    /**
     * Get the view based on the type.
     */
    private function getViewByType($type)
    {
        switch ($type) {
            case 1:
                return 'emails.relanceEmail';
            case 2:
                return 'emails.rdvEmail';
            case 3:
                return 'emails.echeanceUserEmail';
            case 4:
                return 'emails.echeanceClientEmail';
            default:
                return 'emails.default';
        }
    }

    /**
     * Get the subject based on the type.
     */
    private function getSubjectByType($type)
    {
        $sourceLabel = $this->getSourceLabel();

        switch ($type) {
            case 1:
                return "📞 Rappel de relance - {$sourceLabel} - " . $this->projet;
            case 2:
                return "📅 Confirmation de rendez-vous - {$sourceLabel} - " . $this->projet;
            case 3:
                return '💰 Échéance de paiement  - ' . $this->projet;
            case 4:
                return 'Rappel d\'échéance  de paiement - ' . $this->projet;
            default:
                return 'Notification ' . env('CLIENT_NOM');
        }
    }

    /**
     * Get the source label for the subject.
     */
    private function getSourceLabel()
    {
        switch ($this->source) {
            case 'appel':
                return 'appel';
            case 'visite':
                return 'visite';
            default:
                return '';
        }
    }
}
