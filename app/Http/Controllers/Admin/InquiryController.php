<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\ServiceInquiry; use Illuminate\Http\{Request,RedirectResponse}; use Inertia\{Inertia,Response};
class InquiryController extends Controller {
 public function index(Request $r): Response {return Inertia::render('Admin/Inquiries',['inquiries'=>ServiceInquiry::with('service:id,name,slug')->when($r->filled('status'),fn($q)=>$q->where('status',$r->status))->latest()->paginate(20)->withQueryString(),'filters'=>$r->only('status')]);}
 public function update(Request $r,ServiceInquiry $inquiry): RedirectResponse {$d=$r->validate(['status'=>['required','in:new,contacted,scheduled,completed,closed'],'admin_notes'=>['nullable','string','max:2000']]);$inquiry->update($d);return back()->with('success','Inquiry updated.');}
}
