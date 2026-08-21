<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(string $page): Response
    {
        $pages = [
            'about' => ['title'=>'About Prinx Electronics','intro'=>'Reliable electronics, professional guidance and dependable after-sales support.','sections'=>[['heading'=>'What we do','body'=>'Prinx Electronics supplies smartphones, computers, accessories and electronics solutions for individuals, institutions and businesses.'],['heading'=>'Our standard','body'=>'We focus on genuine product information, transparent pricing, responsive support and practical solutions.']]],
            'contact' => ['title'=>'Contact Us','intro'=>'Talk to our sales and technical team.','sections'=>[['heading'=>'Phone','body'=>config('store.phone')],['heading'=>'Email','body'=>config('store.email')],['heading'=>'Location','body'=>config('store.address')]]],
            'shipping' => ['title'=>'Shipping & Delivery','intro'=>'Clear delivery information before you place an order.','sections'=>[['heading'=>'Delivery areas','body'=>'Delivery availability and timing depend on the destination and product size. Our team confirms the schedule after your order is received.'],['heading'=>'Order tracking','body'=>'We provide order status updates using the phone number and email submitted at checkout.']]],
            'returns' => ['title'=>'Returns & Refunds','intro'=>'Straightforward support when a product has a verified issue.','sections'=>[['heading'=>'Return eligibility','body'=>'Products must be returned in their original condition with supplied accessories and proof of purchase. Eligibility depends on product condition and warranty terms.'],['heading'=>'Inspection','body'=>'Returned electronics are inspected before a replacement, repair or refund decision is completed.']]],
            'privacy' => ['title'=>'Privacy Policy','intro'=>'How Prinx Electronics handles customer information.','sections'=>[['heading'=>'Information collected','body'=>'We collect information required to process orders, respond to service requests and provide customer support.'],['heading'=>'Data protection','body'=>'Customer data is used only for legitimate store operations and is not sold to advertisers.']]],
            'terms' => ['title'=>'Terms & Conditions','intro'=>'Terms applying to use of this website and purchases.','sections'=>[['heading'=>'Product information','body'=>'We work to keep prices, stock and specifications accurate. Confirmed order details take priority if an obvious listing error occurs.'],['heading'=>'Orders','body'=>'An order is accepted after availability and payment or delivery arrangements are confirmed.']]],
        ];
        abort_unless(isset($pages[$page]),404);
        return Inertia::render('Store/StaticPage', ['page'=>$pages[$page]]);
    }
}
