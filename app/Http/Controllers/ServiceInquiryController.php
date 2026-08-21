<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceInquiryController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->status === 'active', 404);
        $data = $request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:190'],'phone'=>['required','string','max:30'],'preferred_date'=>['nullable','date','after_or_equal:today'],'message'=>['nullable','string','max:2000']]);
        $service->inquiries()->create($data);
        return back()->with('success', 'Your service request has been submitted. We will contact you shortly.');
    }
}
