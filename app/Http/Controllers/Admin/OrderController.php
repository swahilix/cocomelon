<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\Order; use Illuminate\Http\{Request,RedirectResponse}; use Inertia\{Inertia,Response};
class OrderController extends Controller {
 public function index(Request $r): Response {return Inertia::render('Admin/Orders/Index',['orders'=>Order::when($r->filled('search'),fn($q)=>$q->where(fn($s)=>$s->where('order_number','like','%'.$r->search.'%')->orWhere('customer_name','like','%'.$r->search.'%')->orWhere('phone','like','%'.$r->search.'%')))->when($r->filled('status'),fn($q)=>$q->where('status',$r->status))->latest()->paginate(20)->withQueryString(),'filters'=>$r->only('search','status')]);}
 public function show(Order $order): Response {return Inertia::render('Admin/Orders/Show',['order'=>$order->load(['items','statusHistory'=>fn($q)=>$q->oldest()])]);}
 public function update(Request $r,Order $order): RedirectResponse {$d=$r->validate(['status'=>['required','in:pending,confirmed,processing,ready,shipped,completed,cancelled'],'payment_status'=>['required','in:pending,paid,failed,refunded'],'note'=>['nullable','string','max:500']]);$changed=$order->status!==$d['status']||$order->payment_status!==$d['payment_status'];$order->update(['status'=>$d['status'],'payment_status'=>$d['payment_status']]);if($changed||filled($d['note']??null)){$order->statusHistory()->create(['user_id'=>$r->user()?->id,'status'=>$order->status,'payment_status'=>$order->payment_status,'note'=>$d['note']??null]);}return back()->with('success','Order updated.');}
}
