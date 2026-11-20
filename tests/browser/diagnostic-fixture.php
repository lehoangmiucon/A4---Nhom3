<?php

use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\SupportSession;
use App\Models\User;
use App\Services\Support\SupportConversationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

// Build actual service responses against an isolated in-memory database and fake AI.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:',
    'cache.default' => 'array', 'services.helpdesk_ai.provider' => 'openai', 'services.openai.key' => 'fixture-only']);
DB::purge('sqlite');
if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('The browser fixture must never use the application database.');
}
Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]);
Notification::fake();
Http::preventStrayRequests();
Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text',
    'text' => json_encode(['reply' => 'Hãy kiểm tra lại nguồn điện và dây nguồn.', 'draft' => null])]]]]])]);
$user = User::create(['name' => 'Browser fixture', 'email' => 'fixture@example.test', 'password' => 'fixture-only', 'role' => 'teacher', 'email_verified_at' => now()]);
$building = Building::create(['code' => 'A', 'name' => 'Tòa A']);
$room = Room::create(['building_id' => $building->id, 'code' => 'A201', 'name' => 'Phòng A201']);
$category = Category::create(['name' => 'Máy tính', 'color' => '#123456']);
$service = app(SupportConversationService::class);
$first = $service->execute($user, null, ['content' => 'Máy tính không mở lên', 'room_id' => $room->id, 'category_id' => $category->id], (string) Str::uuid());
$session = SupportSession::findOrFail($first['data']['session']['id']);
$second = $service->execute($user, $session, ['content' => 'nguồn điện bình thường', 'expected_version' => $session->version], (string) Str::uuid());
$session->refresh();
$third = $service->execute($user, $session, ['content' => 'hãy báo cáo lên KTV cho mình', 'expected_version' => $session->version], (string) Str::uuid());
Http::assertSentCount(2);
echo json_encode(['context' => ['room_id' => $room->id, 'category_id' => $category->id], 'responses' => [$first, $second, $third],
    'database' => 'sqlite::memory:', 'provider' => 'fake'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
