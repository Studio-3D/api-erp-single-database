<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\V1\Societe;
use App\Http\Helpers\DatabaseHelper;

class MigrateTenantDatabases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:tenants {--fresh : Drop all tables and re-run all migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations on all tenant databases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to run migrations on all tenant databases...');
        
        $fresh = $this->option('fresh');
        
        try {
            // Get all societes
            $societes = Societe::all();
            $this->info("Found {$societes->count()} tenant database(s) to migrate.");
            
            foreach ($societes as $societe) {
                $databaseName = 'Erp_' . $societe->raison_sociale_concatene . '_' . $societe->id;
                $this->info("Migrating tenant database: {$databaseName} (Societe: {$societe->raison_sociale})");
                
                try {
                    // Set up the database connection
                    $connection = DatabaseHelper::Connection_database($databaseName);
                    config(['database.connections.temp' => $connection]);
                    DB::purge('temp');
                    DB::reconnect('temp');
                    
                    // Verify connection
                    $actualDbName = DB::connection('temp')->getDatabaseName();
                    $this->info("  Connected to database: {$actualDbName}");
                    
                    // Run migrations
                    if ($fresh) {
                        $this->info("  Running fresh migrations...");
                        $exitCode = Artisan::call('migrate:fresh', [
                            '--database' => 'temp',
                            '--path' => 'database/migrations/migrations_societe',
                            '--force' => true,
                        ]);
                    } else {
                        $this->info("  Running migrations...");
                        $exitCode = Artisan::call('migrate', [
                            '--database' => 'temp',
                            '--path' => 'database/migrations/migrations_societe',
                            '--force' => true,
                        ]);
                    }
                    
                    if ($exitCode === 0) {
                        $this->info("  ✓ Migrations completed successfully");
                    } else {
                        $this->error("  ✗ Migrations failed with exit code: {$exitCode}");
                    }
                    
                } catch (\Exception $e) {
                    $this->error("  ✗ Error migrating {$databaseName}: " . $e->getMessage());
                }
            }
            
            $this->info('Completed migrating all tenant databases.');
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
