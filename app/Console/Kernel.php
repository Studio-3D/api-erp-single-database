<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {

        $schedule->command('app:delete-societe-database-command')->dailyAt('01:00');
        $schedule->command('app:clear-proposition-table')
            ->everyTenMinutes()
            ->withoutOverlapping(300)
            ->runInBackground();
        $schedule->command('app:liberer_bien_pre_reserve')->dailyAt('00:00');
           /* ->everyMinute()
            ->withoutOverlapping(300)
            ->runInBackground();*/
        $schedule->command('app:destroy_notif')->dailyAt('00:00');
        $schedule->command('app:send_scheduled_emails_whatsapp')->dailyAt('08:40'); // Exécute tous les jours à minuit
        $schedule->command('app:send_email_whatsapp_echeance')->dailyAt('08:40'); // Exécute tous les jours à minuit
        $schedule->command('app:import_fichiers')
            ->everyTwoMinutes()
            ->withoutOverlapping(300)
            ->runInBackground();
        $schedule->command('app:edit_biens_et_titre_foncier_en_masse')
            ->everyTwoMinutes()
            ->withoutOverlapping(300)
            ->runInBackground();
        $schedule->command('app:clear-webhook_events-table')->sundays()->at('07:00'); // Runs every Sunday at midnight
        $schedule->command('delete_creneau_propose')->everyThreeMinutes();//3min
        $schedule->command('annuler_rdv_automatique')->everyFifteenMinutes();//15min

      $schedule->command('whatsapp:send-bulk')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
        //cron job for relance whtsap
     $schedule->command('follow-ups:send')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    // ✅ NOUVEAU : Affecter les prospects après 2 relances sans réponse
    //    - Vérifie toutes les 30 minutes
    //    - Délai de 2h après la 2e relance
    $schedule->command('prospects:assign-after-followups --delay-after-followup2=2')
        ->everyThirtyMinutes()
        ->withoutOverlapping()
        ->onOneServer()
        ->runInBackground();
    // ✅ Cron Réceptif Avito/Mubawab
    // Toutes les minutes entre 13:45 et 20:00
    // ✅ Cron Réceptif Avito/Mubawab (10h00 -> 20h00)
   $schedule->command('prospects:assign-statut-prospect --limit=50 --per-run=1')
    ->everyFiveMinutes()
    ->between('10:00', '20:00')
    ->withoutOverlapping()
    ->runInBackground();
         // Nettoyage 5 min après la fin
    $schedule->command('prospects:clean-cron-markers')
    ->dailyAt('20:05')
    ->withoutOverlapping()->runInBackground();

    }



    /**
     * Register the commands for the application.
     */

    protected function commands(): void
    {

        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    protected $commands = [
    ];

}
