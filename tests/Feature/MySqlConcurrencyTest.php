<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HistoryEvent;
use App\Models\PairingCode;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql')]
class MySqlConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Requires dedicated MySQL test database.');
        }
        parent::setUp();
    }

    private function concurrent(array $jobs): array
    {
        $directory = sys_get_temp_dir().'/portal-race-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $release = $directory.'/release';
        $processes = [];
        $pipes = [];
        $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'HISTORY_INDEX_KEY' => config('history.index_key'), 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'null']);
        foreach ($jobs as $index => $job) {
            $processes[$index] = proc_open([PHP_BINARY, base_path('tests/Support/ApiWorker.php')], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$index], base_path(), $env);
            fwrite($pipes[$index][0], json_encode($job + ['ready' => $directory.'/'.$index, 'release' => $release, 'database' => config('database.connections.mysql')]));
            fclose($pipes[$index][0]);
        }
        $until = microtime(true) + 15;
        while (! file_exists($directory.'/0') || ! file_exists($directory.'/1')) {
            if (microtime(true) > $until) {
                $this->fail('Workers did not become ready.');
            } usleep(10000);
        }
        touch($release);
        $results = [];
        foreach ($processes as $index => $process) {
            $output = stream_get_contents($pipes[$index][1]);
            $error = stream_get_contents($pipes[$index][2]);
            fclose($pipes[$index][1]);
            fclose($pipes[$index][2]);
            $exit = proc_close($process);
            $this->assertSame(0, $exit, $error);
            $this->assertLessThan(500, (int) trim($output), $error);
            $results[] = (int) trim($output);
        }
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        } rmdir($directory);
        sort($results);

        return $results;
    }

    public function test_two_requests_cannot_redeem_one_pairing_code(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'setup_pending' => false]);
        PairingCode::create(['user_id' => $owner->id, 'code_hash' => hash('sha256', '0123456789ABCDEF'), 'sources' => ['sms'], 'expires_at' => now()->addMinutes(5)]);
        $job = ['action' => 'pair', 'payload' => ['code' => '0123456789ABCDEF', 'device_name' => 'Concurrent']];
        $this->assertSame([201, 422], $this->concurrent([$job, $job]));
        $this->assertSame(1, Device::count());
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    public function test_concurrent_duplicate_events_create_one_record(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'setup_pending' => false]);
        $device = Device::create(['user_id' => $owner->id, 'name' => 'Concurrent', 'sources' => ['sms'], 'allow_ip' => false, 'allow_location' => false]);
        $token = $device->createToken('concurrency', ['events:write'], now()->addDay())->plainTextToken;
        $job = ['action' => 'events', 'token' => $token, 'payload' => ['events' => [['event_id' => (string) Str::uuid(), 'source' => 'sms', 'occurred_at' => now()->toIso8601String()]]]];
        $this->assertSame([200, 201], $this->concurrent([$job, $job]));
        $this->assertSame(1, HistoryEvent::count());
    }
}
