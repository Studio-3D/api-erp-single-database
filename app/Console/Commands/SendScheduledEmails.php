<?php

namespace App\Console\Commands;

use App\Http\Helpers\DatabaseHelper; // Modifiez pour correspondre à votre modèle utilisateur
use App\Mail\ScheduledEmail; // Mail à envoyer
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendScheduledEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    /**
     * Execute the console command.
     */

    protected $signature = 'emails:send-scheduled';
    protected $description = 'Envoyer des e-mails programmés à une date précise';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $databases = DB::table('societes')->whereNull('deleted_at')->where('id', '=', 233)->get();
        DatabaseHelper::envoyer_email_rdv_rlc($databases);
    }

}
