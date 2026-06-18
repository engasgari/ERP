<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    public function edit()
    {
        return view('company-settings.edit', [
            'company' => CompanySetting::firstOrCreate([]),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'economic_code' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'default_vat_rate' => 'nullable|numeric|min:0',
        ]);

        CompanySetting::firstOrCreate([])->update($validated);

        return redirect()->route('company-settings.edit')->with('success', 'اطلاعات شرکت ذخیره شد.');
    }
}
