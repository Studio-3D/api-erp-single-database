<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ChroneJobHelpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;







class BulkWhatsapp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:send-bulk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Commande pour msg whatsap en masse';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')
        ->whereNull('deleted_at')
        ->get();

        DatabaseHelper::send_bulk_whatsapp($databases);

    }
}
