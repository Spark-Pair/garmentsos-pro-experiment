<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Models\User;
use App\Services\Backup\BackupCleanupService;
use App\Services\Backup\SQLiteBackupService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class BackupDownloadTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryFiles = [];

    /** @var array<int, string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach ($this->temporaryDirectories as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }

        parent::tearDown();
    }

    public function test_unauthenticated_user_is_rejected_by_auth_middleware(): void
    {
        $this->get('/backup-db')
            ->assertRedirect(route('login'));
    }

    public function test_developer_can_download_timestamped_backup(): void
    {
        $backupPath = $this->temporaryBackupFile('database_backup_20260611_153045_123456.sqlite');
        $configuredPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'configured-backup-path';
        Config::set('backup.temporary_backup_path', $configuredPath);

        $cleanup = $this->mock(BackupCleanupService::class);
        $cleanup->shouldReceive('cleanTemporary')
            ->once()
            ->ordered()
            ->andReturn($this->cleanupSummary());

        $service = $this->mock(SQLiteBackupService::class);
        $service->shouldReceive('createBackup')
            ->once()
            ->with($configuredPath)
            ->ordered()
            ->andReturn($backupPath);

        $response = $this->actingAsRole('developer')->get('/backup-db');

        $response->assertOk()
            ->assertDownload(basename($backupPath))
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Cache-Control');

        $cacheControl = $response->headers->get('Cache-Control', '');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertMatchesRegularExpression(
            '/^database_backup_\d{8}_\d{6}_\d{6}\.sqlite$/',
            $this->downloadFilename($response->headers->get('Content-Disposition', ''))
        );
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertTrue($this->deletesFileAfterSend($response->baseResponse));
    }

    public function test_admin_can_download_backup(): void
    {
        $backupPath = $this->temporaryBackupFile('database_backup_20260611_153046_123456.sqlite');
        $this->mockSuccessfulCleanup();
        $service = $this->mock(SQLiteBackupService::class);
        $service->shouldReceive('createBackup')->once()->andReturn($backupPath);

        $this->actingAsRole('admin')
            ->get('/backup-db')
            ->assertOk()
            ->assertDownload(basename($backupPath));
    }

    public function test_other_authenticated_role_receives_existing_forbidden_response(): void
    {
        $service = $this->mock(SQLiteBackupService::class);
        $service->shouldNotReceive('createBackup');

        $this->actingAsRole('owner')
            ->get('/backup-db')
            ->assertStatus(403)
            ->assertSeeText('You do not have permission to download database backup.');
    }

    public function test_download_query_parameter_remains_compatible(): void
    {
        $backupPath = $this->temporaryBackupFile('database_backup_20260611_153047_123456.sqlite');
        $this->mockSuccessfulCleanup();
        $service = $this->mock(SQLiteBackupService::class);
        $service->shouldReceive('createBackup')->once()->andReturn($backupPath);

        $this->actingAsRole('developer')
            ->get('/backup-db?download=1')
            ->assertOk()
            ->assertDownload(basename($backupPath));
    }

    public function test_service_failure_returns_generic_error_and_logs_details(): void
    {
        $this->mockSuccessfulCleanup();
        $service = $this->mock(SQLiteBackupService::class);
        $service->shouldReceive('createBackup')
            ->once()
            ->andThrow(new RuntimeException(
                'Could not open C:\\private\\client\\database.sqlite with secret details.'
            ));

        Log::shouldReceive('error')
            ->once()
            ->with('DB backup failed', Mockery::on(function (array $context): bool {
                return $context['user_id'] === 100
                    && str_contains($context['message'], 'C:\\private\\client\\database.sqlite')
                    && isset($context['trace']);
            }));

        $response = $this->actingAsRole('developer')->get('/backup-db');

        $response->assertStatus(500)
            ->assertSeeText('Backup generation failed.')
            ->assertDontSee('C:\\private\\client\\database.sqlite')
            ->assertDontSee('secret details');
    }

    public function test_cleanup_failure_is_logged_but_download_continues(): void
    {
        $backupPath = $this->temporaryBackupFile('database_backup_20260611_153048_123456.sqlite');
        $cleanup = $this->mock(BackupCleanupService::class);
        $cleanup->shouldReceive('cleanTemporary')
            ->once()
            ->andThrow(new RuntimeException('Temporary cleanup path is unavailable.'));

        $service = $this->mock(SQLiteBackupService::class);
        $service->shouldReceive('createBackup')->once()->andReturn($backupPath);

        Log::shouldReceive('warning')
            ->once()
            ->with('Temporary backup cleanup failed', Mockery::on(function (array $context): bool {
                return $context['user_id'] === 100
                    && $context['message'] === 'Temporary cleanup path is unavailable.'
                    && isset($context['trace']);
            }));

        $this->actingAsRole('developer')
            ->get('/backup-db')
            ->assertOk()
            ->assertDownload(basename($backupPath));
    }

    private function actingAsRole(string $role): self
    {
        $user = new User([
            'name' => ucfirst($role).' User',
            'username' => $role.'_backup_test',
            'role' => $role,
            'status' => 'active',
        ]);
        $user->id = 100;

        $this->actingAs($user);
        $this->withoutMiddleware([
            CheckActiveSession::class,
            SubscriptionExpiry::class,
        ]);

        return $this;
    }

    private function temporaryBackupFile(string $filename): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR
            .'garmentsos-backup-route-'.bin2hex(random_bytes(6));
        mkdir($directory, 0775, true);

        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        file_put_contents($path, "SQLite format 3\0".str_repeat("\0", 16384));
        $this->temporaryFiles[] = $path;
        $this->temporaryDirectories[] = $directory;

        return $path;
    }

    private function mockSuccessfulCleanup(): void
    {
        $cleanup = $this->mock(BackupCleanupService::class);
        $cleanup->shouldReceive('cleanTemporary')
            ->once()
            ->andReturn($this->cleanupSummary());
    }

    /**
     * @return array<string, int>
     */
    private function cleanupSummary(): array
    {
        return [
            'scanned' => 0,
            'deleted' => 0,
            'kept_recent' => 0,
            'ignored' => 0,
            'skipped_links' => 0,
            'failed' => 0,
        ];
    }

    private function downloadFilename(string $contentDisposition): string
    {
        if (preg_match('/filename="?([^";]+)"?/', $contentDisposition, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    private function deletesFileAfterSend(BinaryFileResponse $response): bool
    {
        $property = new ReflectionProperty(BinaryFileResponse::class, 'deleteFileAfterSend');
        $property->setAccessible(true);

        return $property->getValue($response) === true;
    }
}
