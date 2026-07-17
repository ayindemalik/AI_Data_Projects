<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * The known settings this screen manages, with how each should be
     * rendered/saved. Add a new line here whenever a later phase needs a
     * new application-wide toggle or value — this is the ONLY place the
     * Settings screen's fields are defined.
     */
    private array $fields = [
        'default_locale' => [
            'label' => 'Default Language',
            'type' => 'select',
            'options' => ['tr' => 'Türkçe', 'en' => 'English'],
        ],
        'assistant_enabled' => [
            'label' => 'AI Assistant Enabled',
            'type' => 'boolean',
        ],
        'dealer_contact_note' => [
            'label' => 'Dealer Contact Note (shown to customers instead of pricing)',
            'type' => 'text',
        ],
    ];

    public function edit(): View
    {
        $this->authorize('manage', Setting::class);

        $values = [];
        foreach (array_keys($this->fields) as $key) {
            $values[$key] = Setting::get($key);
        }

        return view('admin.settings.edit', ['fields' => $this->fields, 'values' => $values]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage', Setting::class);

        foreach ($this->fields as $key => $field) {
            if ($field['type'] === 'boolean') {
                Setting::set($key, $request->boolean($key) ? '1' : '0', 'boolean');
            } else {
                Setting::set($key, $request->input($key), $field['type'] === 'text' ? 'text' : 'string');
            }
        }

        return back()->with('status', 'Settings updated successfully.');
    }
}
