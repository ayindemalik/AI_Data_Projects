<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\View\View;

class AssistantController extends Controller
{
    public function index(): View
    {
        return view('customer.assistant', [
            'assistantEnabled' => (bool) Setting::get('assistant_enabled', true),
        ]);
    }
}
