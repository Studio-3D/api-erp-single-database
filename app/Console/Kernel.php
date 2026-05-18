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
        $schedule->command('app:liberer_bien_pre_reserve')
            ->everyMinute()
            ->withoutOverlapping(300)
            ->runInBackground();
        $schedule->command('app:destroy_notif')->dailyAt('00:00');
        $schedule->command(command: 'emails:send-scheduled')->dailyAt('07:00'); // Exécute tous les jours à minuit
        $schedule->command(command: 'app:echeance-email')->dailyAt('07:00'); // Exécute tous les jours à minuit
        $schedule->command('app:import_fichiers')
            ->everyTwoMinutes()
            ->withoutOverlapping(300)
            ->runInBackground();
        $schedule->command('app:edit_biens_et_titre_foncier_en_masse')
            ->everyTwoMinutes()
            ->withoutOverlapping(300)
            ->runInBackground();
        $schedule->command('app:clear-webhook_events-table')->sundays()->at('07:00'); // Runs every Sunday at midnight
        $schedule->command(command: 'whatsapp:send-reminder')->dailyAt('00:00'); // Exécute tous les jours à minuit
        $schedule->command('delete_creneau_propose')->everyThreeMinutes();//3min
        $schedule->command('annuler_rdv_automatique')->everyFifteenMinutes();//15min


       /* // Poll LinkedIn stats every 5 minutes
        $schedule->command('linkedin:poll-stats')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->call(function () {
        app()->make(\App\Http\Controllers\Facebook_Instagram\Facebook_InstagramController::class)->checkExpiredPhoneReminders();
        })->everyMinute(); // or ->everyFiveMinutes() depending on your needs*/

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
