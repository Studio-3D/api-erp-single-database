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
    public $type; // Ajout d'une propriété pour le type

    /**
     * Create a new message instance.
     */
    public function __construct($type, $user)
    {
        $this->type = $type; // Assigner le type
        $this->user = $user; // Assigner l'utilisateur
    }

    public function build()
    {
        // Sélectionner la vue en fonction du type
        $view = $this->getViewByType($this->type);

        return $this->view($view) // Charger la vue appropriée
                    ->subject($this->getSubjectByType($this->type)) // Sujet dynamique
                    ->with([
                        'name' => $this->user->name,
                        'date' => $this->user->created_at,
                    ])
                    ->from('immo.immobilier02@gmail.com', 'Immobilier');
    }

    /**
     * Get the view based on the type.
     */
    private function getViewByType($type)
    {
        // Associer les types aux vues
        switch ($type) {
            case 1:
                return 'emails.relanceEmail';
            case 2:
                return 'emails.rdvEmail';
            case 3:
                return 'emails.echeanceEmail';
            default:
                return 'emails.default'; // Vue par défaut si le type est inconnu
        }
    }

    /**
     * Get the subject based on the type.
     */
    private function getSubjectByType($type)
    {
        // Associer les types aux sujets
        switch ($type) {
            case 1:
                return 'Votre email programmé';
            case 2:
                return 'Votre email de règlement';
            case 3:
                return 'Votre échéance approche';
            default:
                return 'Notification';
        }
    }
}
