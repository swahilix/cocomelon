<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstallmentContractController extends Controller
{
    public function show(Request $request, string $reference): View
    {
        $language = strtolower(trim((string) $request->query('language', 'en')));
        abort_unless(in_array($language, ['en', 'sw'], true), 422);

        $plan = $this->planByReference($reference);
        $items = DB::table('installment_plan_items')
            ->where('installment_plan_id', $plan->id)
            ->orderBy('id')
            ->get();

        return view('admin.installments.contract', [
            'plan' => $plan,
            'items' => $items,
            'language' => $language,
            'staffName' => $request->user()?->name,
            'generatedAt' => now(),
        ]);
    }

    public function upload(Request $request, string $reference): RedirectResponse
    {
        $validated = $request->validate([
            'signed_contract' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $plan = $this->planByReference($reference);
        $file = $validated['signed_contract'];

        if ($plan->signed_contract_path && Storage::disk('local')->exists($plan->signed_contract_path)) {
            Storage::disk('local')->delete($plan->signed_contract_path);
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $filename = Str::slug($plan->reference).'-signed-'.now()->format('YmdHis').'.'.$extension;
        $path = $file->storeAs('installment-contracts', $filename, 'local');

        DB::table('installment_plans')->where('id', $plan->id)->update([
            'signed_contract_path' => $path,
            'signed_contract_original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'signed_contract_mime' => mb_substr((string) ($file->getMimeType() ?: $file->getClientMimeType()), 0, 120),
            'signed_contract_uploaded_at' => now(),
            'signed_contract_uploaded_by' => $request->user()?->id,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Signed installment contract uploaded and saved to this plan.');
    }

    public function signed(string $reference): BinaryFileResponse
    {
        $plan = $this->planByReference($reference);
        abort_unless($plan->signed_contract_path, 404);
        abort_unless(Storage::disk('local')->exists($plan->signed_contract_path), 404);

        $name = trim((string) ($plan->signed_contract_original_name ?: basename($plan->signed_contract_path)));
        $safeName = str_replace(['"', "\r", "\n"], '', $name);
        $safeName = str_replace('"', '', $safeName);

        return response()->file(Storage::disk('local')->path($plan->signed_contract_path), [
            'Content-Type' => $plan->signed_contract_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function planByReference(string $reference): object
    {
        $plan = DB::table('installment_plans')->where('reference', $reference)->first();
        abort_unless($plan, 404);

        return $plan;
    }
}
