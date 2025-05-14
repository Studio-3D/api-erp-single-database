<?php

namespace App\Console\Commands;

use App\Http\Helpers\DatabaseHelper; // Modifiez pour correspondre à votre modèle utilisateur
use App\Mail\ScheduledEmail; // Mail à envoyer
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendWhatsAppReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    /**
     * Execute the console command.
     */

    protected $signature = 'whatsapp:send-reminder';
    protected $description = 'Send WhatsApp reminder 1 day before appointment';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $databases = DB::table('societes')->whereNull('deleted_at')->get();
        DatabaseHelper::envoyer_whatsapp_rdv_rlc($databases);
    }

}
