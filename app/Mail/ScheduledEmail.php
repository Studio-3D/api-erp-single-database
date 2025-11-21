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

    /**
     * Create a new message instance.
     */
    public function __construct($type, $user, $projet = null, $bien = null, $prospectName = null, $avance = null, $source = null)
    {
        $this->type = $type;
        $this->user = $user;
        $this->bien = $bien;
        $this->projet = $projet;
        $this->prospectName = $prospectName;
        $this->avance = $avance;
        $this->source = $source; // 'appel' ou 'visite'
    }

    public function build()
    {
        $view = $this->getViewByType($this->type);

        return $this->view($view)
                    ->subject($this->getSubjectByType($this->type))
                    ->with([
                        'name' => $this->user->name ?? $this->user->nom,
                        'projet' => $this->projet,
                        'bien' => $this->bien,
                        'prospectName' => $this->prospectName,
                        'avance' => $this->avance,
                        'date' => now()->format('d/m/Y'),
                        'montant' => $this->avance->montant ?? null,
                        'echeance' => $this->avance->echeance ?? null,
                        'source' => $this->source, // Ajouter la source au template si besoin
                    ])
                    ->from(env('MAIL_USERNAME'), 'Immobilier Immo');
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
                return "Rappel de relance {$sourceLabel} - " . $this->projet;
            case 2:
                return "Confirmation de rendez-vous {$sourceLabel} - " . $this->projet;
            case 3:
                return 'Échéance à venir - ' . $this->projet;
            case 4:
                return 'Rappel d\'échéance - ' . $this->projet;
            default:
                return 'Notification Immobilier';
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
