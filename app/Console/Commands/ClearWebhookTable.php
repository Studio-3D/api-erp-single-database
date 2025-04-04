<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ChroneJobHelpers;
use Illuminate\Support\Facades\Auth;
use App\Models\Proposition;
use App\Models\User;
use App\Models\Societe;
use Illuminate\Support\Facades\DB;







class ClearWebhookTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-webhook_events-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear webhook event every sunday at midnight ';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')->whereNull('deleted_at')->get();
        DatabaseHelper::deleteWebhookTable($databases);
    }
}
