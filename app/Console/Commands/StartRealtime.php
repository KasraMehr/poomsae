<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

#[Signature('poomsae:realtime {--hub-binary= : Path to the official Mercure executable}')]
#[Description('Run the local application, Mercure hub and broadcast queue worker')]
class StartRealtime extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $binary = $this->option('hub-binary') ?: storage_path('app/mercure/mercure.exe');
        if (! is_file($binary)) {
            $this->error('Mercure executable not found. Pass its path using --hub-binary.');

            return self::FAILURE;
        }
        if (config('broadcasting.default') !== 'mercure') {
            $this->error('Set BROADCAST_CONNECTION=mercure before starting realtime services.');

            return self::FAILURE;
        }

        $environment = [
            'APP_URL' => config('app.url'),
            'MERCURE_PUBLISHER_JWT_KEY' => config('broadcasting.connections.mercure.secret'),
            'MERCURE_SUBSCRIBER_JWT_KEY' => config('broadcasting.connections.mercure.secret'),
            'MERCURE_JWT_AUDIENCE' => config('broadcasting.connections.mercure.claims.aud') ?: 'http://127.0.0.1:8000/.well-known/mercure',
            'MERCURE_BIND' => env('MERCURE_BIND', '127.0.0.1'),
            'MERCURE_PUBLIC_URLS' => env('MERCURE_PUBLIC_URLS', 'http://127.0.0.1:8000/.well-known/mercure http://localhost:8000/.well-known/mercure'),
            'MERCURE_ORIGINS' => env('MERCURE_ORIGINS', 'http://127.0.0.1:8000 http://localhost:8000'),
        ];
        $validation = new Process([$binary, 'validate', '--config', base_path('Caddyfile'), '--adapter', 'caddyfile'], base_path(), $environment);
        $validation->run();
        if (! $validation->isSuccessful()) {
            $this->error($validation->getErrorOutput());

            return self::FAILURE;
        }

        $legacyJobIds = DB::table('failed_jobs')->where('exception', 'like', '%CompetitionUpdated::$change%')->pluck('uuid')->all();
        if ($legacyJobIds !== []) {
            $this->call('queue:retry', ['id' => $legacyJobIds, '--no-interaction' => true]);
        }

        $processes = [
            'application' => new Process([PHP_BINARY, '-S', '127.0.0.1:8001', '-t', 'public'], base_path()),
            'mercure' => new Process([$binary, 'run', '--config', base_path('Caddyfile'), '--adapter', 'caddyfile'], base_path(), $environment),
            'queue' => new Process([PHP_BINARY, 'artisan', 'queue:work', '--sleep=1', '--tries=3', '--timeout=30', '--no-interaction'], base_path()),
        ];

        try {
            foreach ($processes as $name => $process) {
                if ($name === 'queue') {
                    usleep(500000);
                    if (! $processes['mercure']->isRunning() || ! $processes['application']->isRunning()) {
                        $this->error('Application or Mercure failed to start. See storage/logs/realtime.log.');

                        return self::FAILURE;
                    }
                }
                $process->setTimeout(null);
                $process->start(function (string $type, string $output) use ($name): void {
                    file_put_contents(storage_path('logs/realtime.log'), '['.$name.'] '.$output, FILE_APPEND);
                });
            }
            $this->info('Application and Mercure: http://127.0.0.1:8000');
            $this->info('Queue worker started. Keep this command running.');

            while (true) {
                foreach ($processes as $name => $process) {
                    if (! $process->isRunning()) {
                        $this->error($name.' stopped. See storage/logs/realtime.log.');

                        return self::FAILURE;
                    }
                }
                usleep(200000);
            }
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }
}
