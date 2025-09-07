<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers\Config;

use CapsuleCmdr\SeatOsmm\Models\OsmmSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BrandingController extends Controller
{
    /**
     * Show the Branding settings page.
     */
    public function index()
    {
        $values = OsmmSetting::query()
            ->whereIn('key', [
                'osmm_use_enhanced_home',
                'osmm_override_sidebar',
                'osmm_override_footer',
                'osmm_override_manifest',
                'osmm_override_favicon',                
                'osmm_branding_footer_html',
                'osmm_branding_sidebar_html',
                'osmm_branding_manifest_json',
                'osmm_branding_favicon_html',
            ])
            ->pluck('value', 'key');

        return view('seat-osmm::config.branding', [
            'osmm_use_enhanced_home'      => (int)($values['osmm_use_enhanced_home'] ?? 0),
            'osmm_override_sidebar'       => (int)($values['osmm_override_sidebar'] ?? 0),
            'osmm_override_footer'        => (int)($values['osmm_override_footer'] ?? 0),
            'osmm_override_manifest'      => (int)($values['osmm_override_manifest'] ?? 0),
            'osmm_override_favicon'       => (int)($values['osmm_override_favicon'] ?? 0), // NEW

            'osmm_branding_sidebar_html'  => (string)($values['osmm_branding_sidebar_html'] ?? ''),
            'osmm_branding_footer_html'   => (string)($values['osmm_branding_footer_html'] ?? ''),
            'osmm_branding_favicon_html'  => (string)($values['osmm_branding_favicon_html'] ?? ''), // NEW
            'osmm_branding_manifest_json' => (string)($values['osmm_branding_manifest_json'] ?? ''),
        ]);
    }

    /**
     * Persist Branding settings.
     */
    public function save(Request $request)
    {
        // Hidden inputs ensure 0 arrives when checkboxes are unchecked
        $data = $request->validate([
            'osmm_use_enhanced_home'     => ['nullable','boolean'],
            'osmm_override_sidebar'       => ['required', 'boolean'],
            'osmm_override_footer'        => ['required', 'boolean'],
            'osmm_override_manifest'      => ['required', 'boolean'],
            'osmm_override_favicon'      => ['required', 'boolean'],
            'osmm_branding_footer_html'   => ['nullable', 'string'],
            'osmm_branding_sidebar_html'  => ['nullable', 'string'],
            'osmm_branding_manifest_json' => ['nullable', 'string'], // validate as JSON below
            'osmm_branding_favicon_html'  => ['nullable', 'string'],
        ]);

        // Validate the manifest JSON if provided (preserve formatting; just check parsability)
        if (!empty($data['osmm_branding_manifest_json'])) {
            json_decode($data['osmm_branding_manifest_json']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()
                    ->withErrors(['osmm_branding_manifest_json' => 'Manifest must be valid JSON.'])
                    ->withInput();
            }
        }

        $uid = Auth::id();

        // Store booleans as 1/0
        OsmmSetting::put('osmm_use_enhanced_home',  $request->boolean('osmm_use_enhanced_home')  ? 1 : 0, 'text', $uid);
        OsmmSetting::put('osmm_override_sidebar',  $request->boolean('osmm_override_sidebar')  ? 1 : 0, 'text', $uid);
        OsmmSetting::put('osmm_override_footer',   $request->boolean('osmm_override_footer')   ? 1 : 0, 'text', $uid);
        OsmmSetting::put('osmm_override_manifest', $request->boolean('osmm_override_manifest') ? 1 : 0, 'text', $uid);
        OsmmSetting::put('osmm_override_favicon', $request->boolean('osmm_override_favicon') ? 1 : 0, 'text', $uid);


        // Store content blobs
        OsmmSetting::put('osmm_branding_footer_html',   $data['osmm_branding_footer_html']   ?? '', 'html', $uid);
        OsmmSetting::put('osmm_branding_sidebar_html',  $data['osmm_branding_sidebar_html']  ?? '', 'html', $uid);
        OsmmSetting::put('osmm_branding_manifest_json', $data['osmm_branding_manifest_json'] ?? '', 'json', $uid);
        OsmmSetting::put('osmm_branding_favicon_html',  $data['osmm_branding_favicon_html']  ?? '', 'html', $uid);

        return redirect()
            ->route('osmm.config.branding')
            ->with('status', 'Branding settings saved.');
    }

    /**
     * Serve the manifest JSON stored in settings.
     * Route this endpoint in web.php and point your <link rel="manifest"> at it.
     */
    public function manifest()
    {
        $raw = osmm_setting('osmm_branding_manifest_json');

        if ($raw === null || $raw === '') {
            // Minimal fallback to avoid 404s; customize if you like
            $raw = json_encode([
                'name'       => 'SeAT',
                'short_name' => 'SeAT',
                'start_url'  => '/',
                'display'    => 'standalone',
            ]);
        }

        return response($raw, 200, [
            'Content-Type'  => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
