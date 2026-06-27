<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupService;
use App\Services\BackupStorageService;
use App\Services\RestoreService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class RestoreFlowTest extends TestCase
{
    protected string $databasePath;

    protected string $backupPath;

    protected function setUp(): void
    {
        parent::setUp();

        $id = Str::random(12);
        $this->databasePath = storage_path("framework/testing/restore-db-{$id}.sqlite");
        $this->backupPath = "framework/testing/restore-backups-{$id}";

        File::ensureDirectoryExists(dirname($this->databasePath));
        File::put($this->databasePath, '');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'backup.path' => $this->backupPath,
            'backup.restore.enabled' => false,
            'licensing.identity_path' => storage_path("framework/testing/license-{$id}/installation.json"),
        ]);

        DB::purge('sqlite');
        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');

        File::delete($this->databasePath);
        File::delete($this->databasePath . '-wal');
        File::delete($this->databasePath . '-shm');
        File::deleteDirectory(storage_path('app/' . $this->backupPath));

        parent::tearDown();
    }

    public function test_restore_disabled_by_default_refuses_safely(): void
    {
        $user = $this->actingDeveloper();
        $backup = $this->createManagedBackup();

        User::whereKey($user->id)->update(['name' => 'Changed Name']);

        $response = $this->post(route('developer.backups.restore.store', $backup), [
            'confirmation_phrase' => app(RestoreService::class)->confirmationPhrase($backup),
            'staging_tested' => '1',
        ]);

        $response->assertRedirect(route('developer.backups.restore.show', $backup));
        $response->assertSessionHas('error');
        $this->assertSame('Changed Name', User::find($user->id)->name);
        $this->assertSame(0, BackupLog::where('action', 'restore')->count());
        $this->assertSame(0, AuditLog::where('event_type', 'like', 'restore.%')->count());
    }

    public function test_developer_and_admin_can_view_restore_details(): void
    {
        $backup = $this->createManagedBackup();

        $this->actingAs($this->user('developer'));
        $this->get(route('developer.backups.restore.show', $backup))
            ->assertOk()
            ->assertSee('Restore disabled')
            ->assertSee('Restore is disabled by configuration')
            ->assertSee('disabled', false);

        $this->actingAs($this->user('admin'));
        $this->get(route('developer.backups.restore.show', $backup))->assertOk();
    }

    public function test_unauthorized_user_is_blocked_from_restore_routes(): void
    {
        $backup = $this->createManagedBackup();
        $this->actingAs($this->user('guest'));

        $this->get(route('developer.backups.restore.show', $backup))->assertRedirect(route('home'));
        $this->post(route('developer.backups.restore.store', $backup))->assertRedirect(route('home'));
    }

    public function test_restore_requires_typed_confirmation_and_staging_checkbox(): void
    {
        config(['backup.restore.enabled' => true]);
        $backup = $this->createManagedBackup();
        $this->actingDeveloper();

        $badPhrase = $this->post(route('developer.backups.restore.store', $backup), [
            'confirmation_phrase' => 'RESTORE',
            'staging_tested' => '1',
        ]);
        $badPhrase->assertSessionHas('error');
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'restore.confirmation_failed']);

        $missingCheckbox = $this->post(route('developer.backups.restore.store', $backup), [
            'confirmation_phrase' => app(RestoreService::class)->confirmationPhrase($backup),
        ]);
        $missingCheckbox->assertSessionHas('error');
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'restore.staging_confirmation_failed']);
    }

    public function test_invalid_backup_is_rejected_before_restore(): void
    {
        config(['backup.restore.enabled' => true]);
        $this->actingDeveloper();

        $storage = app(BackupStorageService::class);
        $storage->ensureDirectoryExists();
        $path = $storage->finalFilePath('garmentsos_backup_invalid.sqlite');
        File::put($path, 'not sqlite');

        $backup = BackupLog::create([
            'action' => 'manual_backup',
            'status' => 'success',
            'path' => $storage->relativePath($path),
            'filename' => basename($path),
            'checksum' => hash_file('sha256', $path),
            'started_at' => now(),
        ]);

        $response = $this->post(route('developer.backups.restore.store', $backup), [
            'confirmation_phrase' => app(RestoreService::class)->confirmationPhrase($backup),
            'staging_tested' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'restore.backup_verification_failed']);
    }

    public function test_path_traversal_backup_log_is_rejected(): void
    {
        config(['backup.restore.enabled' => true]);
        $this->actingDeveloper();

        $backup = BackupLog::create([
            'action' => 'manual_backup',
            'status' => 'success',
            'path' => '../database.sqlite',
            'filename' => 'database.sqlite',
            'checksum' => hash('sha256', 'x'),
            'started_at' => now(),
        ]);

        $response = $this->post(route('developer.backups.restore.store', $backup), [
            'confirmation_phrase' => app(RestoreService::class)->confirmationPhrase($backup),
            'staging_tested' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'restore.path_rejected']);
    }

    public function test_enabled_restore_creates_emergency_backup_before_replacing_database(): void
    {
        config(['backup.restore.enabled' => true]);
        $user = $this->actingDeveloper();
        $backup = $this->createManagedBackup();

        User::whereKey($user->id)->update(['name' => 'Changed Before Restore']);

        $response = $this->post(route('developer.backups.restore.store', $backup), [
            'confirmation_phrase' => app(RestoreService::class)->confirmationPhrase($backup),
            'staging_tested' => '1',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('Developer User', User::find($user->id)->name);
        $this->assertDatabaseHas('backup_logs', ['action' => 'emergency_restore_backup', 'status' => 'success']);
        $this->assertDatabaseHas('backup_logs', ['action' => 'restore', 'status' => 'success']);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'restore.succeeded']);
    }

    public function test_overlapping_restore_is_blocked(): void
    {
        config(['backup.restore.enabled' => true]);
        $this->actingDeveloper();
        $backup = $this->createManagedBackup();

        $lock = Cache::lock((string) config('backup.lock_key'), (int) config('backup.lock_seconds'));
        $this->assertTrue($lock->get());

        try {
            $response = $this->post(route('developer.backups.restore.store', $backup), [
                'confirmation_phrase' => app(RestoreService::class)->confirmationPhrase($backup),
                'staging_tested' => '1',
            ]);
        } finally {
            $lock->release();
        }

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'restore.lock_blocked']);
    }

    public function test_restore_aborts_if_emergency_backup_fails(): void
    {
        config(['backup.restore.enabled' => true]);
        $user = $this->actingDeveloper();
        $backup = $this->createManagedBackup();

        User::whereKey($user->id)->update(['name' => 'Changed But Preserved']);

        $mock = Mockery::mock(BackupService::class);
        $mock->shouldReceive('createLog')->andReturnUsing(function (string $action, string $status = 'pending', array $attributes = []) {
            return BackupLog::create([
                'action' => $action,
                'status' => $status,
                'filename' => $attributes['filename'] ?? null,
                'path' => $attributes['path'] ?? null,
                'started_at' => now(),
                'context' => $attributes['context'] ?? null,
            ]);
        });
        $mock->shouldReceive('createManualBackup')
            ->once()
            ->with('emergency_restore_backup', false)
            ->andReturn(['success' => false, 'message' => 'Emergency backup failed.']);

        $this->app->instance(BackupService::class, $mock);

        $response = $this->post(route('developer.backups.restore.store', $backup), [
            'confirmation_phrase' => app(RestoreService::class)->confirmationPhrase($backup),
            'staging_tested' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('Changed But Preserved', User::find($user->id)->name);
        $this->assertDatabaseHas('backup_logs', ['action' => 'restore', 'status' => 'failed']);
        $this->assertDatabaseHas('audit_logs', ['event_type' => 'restore.failed']);
    }

    public function test_no_arbitrary_path_restore_route_exists(): void
    {
        $restoreRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getName(), 'restore'))
            ->map(fn ($route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'developer/backups/{backupLog}/restore',
            'developer/backups/{backupLog}/restore',
        ], $restoreRoutes);
    }

    protected function actingDeveloper(): User
    {
        $user = $this->user('developer');
        $this->actingAs($user);

        return $user;
    }

    protected function user(string $role): User
    {
        return User::create([
            'name' => Str::title($role) . ' User',
            'username' => $role . '_' . Str::random(8),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    protected function createManagedBackup(): BackupLog
    {
        $result = app(BackupService::class)->createManualBackup();

        $this->assertTrue($result['success'], $result['message']);

        return $result['backup_log'];
    }
}
