<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CallApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'call:api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Call an external API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Hacer la llamada a la API
        $response = Http::get('https://jovita-web.com/backend/public/api/valorizado');

        // Manejar la respuesta de la API
        if ($response->successful()) {
            $this->info('Se realizo el registro en la tabla valorizado.');
        } else {
            $this->error('Failed to call API.');
        }

        return 0;
    }
}
