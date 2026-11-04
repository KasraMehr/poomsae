<?php

namespace Tests\Feature;

use App\Actions\RealtimeHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionSystemHealthTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_system_panel_reports_the_hub_response_instead_of_its_configuration(): void
    {
        $fixture = $this->competition();
        config([
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure.url' => 'http://127.0.0.1:3000/.well-known/mercure',
            'queue.default' => 'database',
        ]);
        Http::fakeSequence()->push('Not found', 404)->push('', 400, ['Server' => 'Mercure.rocks Caddy']);

        $this->actingAs($fixture['admin'])->get(route('competition.system', $fixture['tournament']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Competition/System')
            ->where('system.realtime', 'offline')->where('system.queue_worker', 'unknown'));

        $this->get(route('competition.system', $fixture['tournament']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('system.realtime', 'online'));
    }

    public function test_queue_worker_heartbeat_expires_without_a_running_worker(): void
    {
        config(['queue.default' => 'database', 'cache.default' => 'database']);
        $health = app(RealtimeHealth::class);

        $this->assertSame('offline', $health->queueWorkerStatus());
        Event::dispatch(new Looping('database', 'default'));
        $this->assertSame('online', $health->queueWorkerStatus());

        $this->travel(46)->seconds();
        $this->assertSame('offline', $health->queueWorkerStatus());
    }

    public function test_realtime_command_rejects_a_hub_url_on_the_wrong_port(): void
    {
        config([
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure.url' => 'http://127.0.0.1:3000/.well-known/mercure',
            'broadcasting.connections.mercure.public_url' => 'http://127.0.0.1:3000/.well-known/mercure',
        ]);

        $this->artisan('poomsae:realtime', ['--hub-binary' => PHP_BINARY])
            ->expectsOutputToContain('This command serves Mercure on port 8000')
            ->assertExitCode(1);
    }
}
