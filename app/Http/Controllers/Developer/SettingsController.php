<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\SaveBrandingSettingRequest;
use App\Http\Requests\Developer\SaveLabelOverrideRequest;
use App\Services\Settings\BrandingSettingsService;
use App\Services\Settings\FeatureFlagService;
use App\Services\Settings\LabelSettingsService;
use App\Services\Settings\ModuleSettingsService;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function index(
        LabelSettingsService $labels,
        ModuleSettingsService $modules,
        FeatureFlagService $features,
        BrandingSettingsService $branding,
    ) {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.settings.index', [
            'labels' => $labels->defaults(),
            'modules' => $modules->all(),
            'features' => $features->all(),
            'branding' => $branding->all(),
        ]);
    }

    public function saveLabel(SaveLabelOverrideRequest $request, LabelSettingsService $labels): RedirectResponse
    {
        $data = $request->validated();

        try {
            $labels->save($data['label_key'], $data['override_text']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('developer.settings')->with('error', $e->getMessage());
        }

        return redirect()->route('developer.settings')->with('success', 'Label override saved.');
    }

    public function resetLabel(string $key, LabelSettingsService $labels): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        try {
            $labels->reset($key);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('developer.settings')->with('error', $e->getMessage());
        }

        return redirect()->route('developer.settings')->with('success', 'Label override reset.');
    }

    public function saveBranding(SaveBrandingSettingRequest $request, BrandingSettingsService $branding): RedirectResponse
    {
        $data = $request->validated();

        try {
            $branding->save($data['key'], (string) ($data['value'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('developer.settings')->with('error', $e->getMessage());
        }

        return redirect()->route('developer.settings')->with('success', 'Branding setting saved.');
    }
}
