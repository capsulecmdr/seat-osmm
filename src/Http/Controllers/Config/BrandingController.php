<?php

namespace CapsuleCmdr\SeatOsmm\Http\Controllers\Config;

use CapsuleCmdr\SeatOsmm\Models\OsmmSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class BrandingController extends Controller
{
    public function index()
    {
        // Permission gate handled by route middleware; just render values
        return view('seat-osmm::config.branding', [
            'favicon_override_html'       => osmm_setting('favicon_override_html', ''),
            'sidebar_branding_override'   => osmm_setting('sidebar_branding_override', ''),
            'footer_branding_override'    => osmm_setting('footer_branding_override', ''),
            'manifest_override'           => osmm_setting('manifest_override', ''),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'favicon_override_html'     => ['nullable','string'],
            'sidebar_branding_override' => ['nullable','string'],
            'footer_branding_override'  => ['nullable','string'],
            'manifest_override'         => ['nullable','string'], // validate JSON below
        ]);

        // JSON validation (don’t mutate formatting; just check that it parses)
        if (strlen($data['manifest_override'] ?? '') > 0) {
            json_decode($data['manifest_override']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['manifest_override' => 'Manifest must be valid JSON.'])->withInput();
            }
        }

        $uid = Auth::id();

        OsmmSetting::put('favicon_override_html',     $data['favicon_override_html'] ?? null,     'html', $uid);
        OsmmSetting::put('sidebar_branding_override', $data['sidebar_branding_override'] ?? null, 'html', $uid);
        OsmmSetting::put('footer_branding_override',  $data['footer_branding_override'] ?? null,  'html', $uid);
        OsmmSetting::put('manifest_override',         $data['manifest_override'] ?? null,         'json', $uid);

        return redirect()->route('seat-osmm.config.branding')->with('status', 'Branding settings saved.');
    }

    // Serve manifest from DB (used by our favicon include)
    public function manifest()
    {
        $raw = osmm_setting('manifest_override');
        if (! $raw) {
            // Fallback: empty basic manifest to avoid 404s
            $raw = json_encode(['name' => 'SeAT', 'short_name' => 'SeAT']);
        }
        return response($raw, 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
