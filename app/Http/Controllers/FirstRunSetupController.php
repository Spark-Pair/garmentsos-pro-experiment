<?php

namespace App\Http\Controllers;

use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\InstallationIdentityService;
use App\Services\Licensing\LicenseService;
use App\Services\Setup\FirstRunSetupService;
use App\Services\Setup\SetupStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class FirstRunSetupController extends Controller
{
    public function index(
        FirstRunSetupService $setup,
        LicenseService $licenses,
        SetupStatusService $setupStatus,
        InstallationIdentityService $identity,
        InstallationFingerprintService $fingerprints,
    ): View {
        return view('setup.index', [
            'system' => $setup->systemStatus(),
            'existing' => $setup->existingInstallContext(),
            'licensingEnabled' => $licenses->enabled(),
            'setupForceEnabled' => $setupStatus->isForceEnabled(),
            'installationPreview' => $this->safeValue(fn () => $identity->maskedUuid(), 'Pending'),
            'fingerprintPreview' => $this->safeValue(fn () => $fingerprints->fingerprintPreview(), 'Pending'),
        ]);
    }

    public function store(Request $request, FirstRunSetupService $setup): RedirectResponse
    {
        $existing = $setup->existingInstallContext();

        $rules = [
            'dev_password' => ['required', 'string', 'min:4', 'confirmed'],
            'admin_password' => ['required', 'string', 'min:4', 'confirmed'],
            'company_name' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'phone' => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'address' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
        ];

        if (!empty($existing['has_existing_data'])) {
            $rules['existing_install_confirmed'] = ['accepted'];
        }

        $data = $request->validate($rules, [
            'existing_install_confirmed.accepted' => 'Existing users/data were found. Confirm safe recovery setup before continuing.',
        ]);

        try {
            $setup->complete($data);
        } catch (Throwable $e) {
            Log::error('First-run setup failed.', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return back()
                ->withInput($request->except([
                    'dev_password',
                    'dev_password_confirmation',
                    'admin_password',
                    'admin_password_confirmation',
                ]))
                ->with('error', 'Setup could not be completed. Please check the form and try again, or contact support if the problem continues.');
        }

        return redirect()
            ->route('login')
            ->with('success', 'First-run setup completed. Sign in with dev or defuser.');
    }

    protected function safeValue(callable $callback, string $fallback): string
    {
        try {
            return (string) $callback();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
