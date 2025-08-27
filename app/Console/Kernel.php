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

        $schedule->command('app:delete-societe-database-command')->everyMinute();
        $schedule->command('app:clear-proposition-table')->everyMinute();
        $schedule->command('app:liberer_bien_pre_reserve')->everyMinute();
        $schedule->command('app:destroy_notif')->dailyAt('00:00');
        $schedule->command(command: 'emails:send-scheduled')->dailyAt('00:00'); // Exécute tous les jours à minuit
        $schedule->command(command: 'app:echeance-email')->dailyAt('00:00'); // Exécute tous les jours à minuit
        $schedule->command('app:import_fichiers')->everyMinute();
        $schedule->command('app:clear-webhook_events-table')->sundays()->at('07:00'); // Runs every Sunday at midnight
        $schedule->command(command: 'whatsapp:send-reminder')->dailyAt('00:00'); // Exécute tous les jours à minuit
        $schedule->command('delete_creneau_propose')->everyMinute();//after 2 min

        // Poll LinkedIn stats every 5 minutes
        $schedule->command('linkedin:poll-stats')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();
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
