<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function index()
    {
        $this->authorize('manage_settings');
        $settings = [
            'organization_name' => Setting::get('organization_name', config('app.name')),
            'organization_email' => Setting::get('organization_email', config('app.email')),
            'logo' => Setting::get('organization_logo'),
        ];
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $this->authorize('manage_settings');

        // Separate validation for logo and organization info
        if ($request->hasFile('logo')) {
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            // Handle logo upload
            $logo = $request->file('logo');
            $logoPath = $logo->store('logos', 'public');
            Setting::set('organization_logo', $logoPath);

            return back()->with('success', 'تم تحديث الشعار بنجاح');
        }

        // Otherwise update organization info
        $request->validate([
            'organization_name' => 'required|string|max:255',
            'organization_email' => 'required|email',
        ]);

        Setting::set('organization_name', $request->organization_name);
        Setting::set('organization_email', $request->organization_email);

        return back()->with('success', 'تم تحديث معلومات المؤسسة بنجاح');
    }
}
