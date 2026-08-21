<?php
namespace App\Http\Controllers;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class CustomerOrderController extends Controller {
    public function track(Request $request): Response {
        $order=null;
        if ($request->filled('order_number') || $request->filled('phone')) {
            $data=$request->validate(['order_number'=>['required','string','max:50'],'phone'=>['required','string','max:30']]);
            $phone=preg_replace('/\D+/', '', $data['phone']);
            $order=Order::with(['items','statusHistory'=>fn($q)=>$q->oldest()])
                ->where('order_number', strtoupper(trim($data['order_number'])))
                ->get()->first(fn(Order $o)=>preg_replace('/\D+/', '', $o->phone)===$phone);
            if(!$order) abort(404, 'Order not found. Check the order number and phone number.');
        }
        return Inertia::render('Store/TrackOrder',['order'=>$order,'filters'=>$request->only('order_number','phone')]);
    }
    public function index(Request $request): Response {
        return Inertia::render('Account/Orders/Index',['orders'=>$request->user()->orders()->latest()->paginate(15)]);
    }
    public function show(Request $request, Order $order): Response {
        abort_unless($order->user_id === $request->user()->id, 404);
        return Inertia::render('Account/Orders/Show',['order'=>$order->load(['items','statusHistory'=>fn($q)=>$q->oldest()])]);
    }
}
