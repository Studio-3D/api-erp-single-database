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
        $schedule->command('app:clear-proposition-table')->dailyAt('00:00');
        $schedule->command('app:liberer_bien_pre_reserve')->everyMinute();
        $schedule->command('app:destroy_notif')->dailyAt('00:00');
        $schedule->command(command: 'emails:send-scheduled')->everyMinute(); // Exécute tous les jours à minuit
        $schedule->command(command: 'app:echeance-email')->everyMinute(); // Exécute tous les jours à minuit

       }

    /**
     * Register the commands for the application.
     */

    protected function commands(): void
    {

        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }


}
