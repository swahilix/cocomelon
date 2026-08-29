<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(string $page): Response
    {
        $business = Schema::hasTable('business_settings')
            ? DB::table('business_settings')->where('id', 1)->first()
            : null;
        $value = static fn (string $key, mixed $fallback = null): mixed => filled($business?->{$key}) ? $business->{$key} : $fallback;
        $businessName = $value('business_name', config('app.name', 'Prinx Electronics'));
        $location = collect([
            $value('address', config('store.address')),
            $value('city'),
            $value('region'),
            $value('country', config('store.country')),
        ])->filter()->unique()->implode(', ');

        $contactSections = collect([
            ['heading' => 'Phone', 'body' => $value('phone', config('store.phone'))],
            ['heading' => 'Secondary phone', 'body' => $value('secondary_phone')],
            ['heading' => 'WhatsApp', 'body' => $value('whatsapp', config('store.phone'))],
            ['heading' => 'Email', 'body' => $value('email', config('store.email'))],
            ['heading' => 'Location', 'body' => $location],
            ['heading' => 'Working hours', 'body' => $value('working_hours')],
        ])->filter(fn (array $section) => filled($section['body']))->values()->all();

        $pages = [
            'about' => ['title' => 'About '.$businessName, 'intro' => 'Reliable electronics, professional guidance and dependable after-sales support.', 'sections' => [['heading' => 'What we do', 'body' => $value('description', $businessName.' supplies smartphones, computers, accessories and electronics solutions for individuals, institutions and businesses.')], ['heading' => 'Our standard', 'body' => 'We focus on genuine product information, transparent pricing, responsive support and practical solutions.']]],
            'contact' => ['title' => 'Contact Us', 'intro' => 'Talk to our sales and technical team.', 'sections' => $contactSections],
            'shipping' => ['title' => 'Shipping & Delivery', 'intro' => 'Clear delivery information before you place an order.', 'sections' => [['heading' => 'Delivery areas', 'body' => 'Delivery availability and timing depend on the destination and product size. Our team confirms the schedule after your order is received.'], ['heading' => 'Order tracking', 'body' => 'We provide order status updates using the phone number and email submitted at checkout.']]],
            'returns' => ['title' => 'Returns & Refunds', 'intro' => 'Straightforward support when a product has a verified issue.', 'sections' => [['heading' => 'Return eligibility', 'body' => 'Products must be returned in their original condition with supplied accessories and proof of purchase. Eligibility depends on product condition and warranty terms.'], ['heading' => 'Inspection', 'body' => 'Returned electronics are inspected before a replacement, repair or refund decision is completed.']]],
            'privacy' => ['title' => 'Privacy Policy', 'intro' => 'How '.$businessName.' handles customer information.', 'sections' => [['heading' => 'Information collected', 'body' => 'We collect information required to process orders, respond to service requests and provide customer support.'], ['heading' => 'Data protection', 'body' => 'Customer data is used only for legitimate store operations and is not sold to advertisers.']]],
            'terms' => ['title' => 'Terms & Conditions', 'intro' => 'Terms applying to use of this website and purchases.', 'sections' => [['heading' => 'Product information', 'body' => 'We work to keep prices, stock and specifications accurate. Confirmed order details take priority if an obvious listing error occurs.'], ['heading' => 'Orders', 'body' => 'An order is accepted after availability and payment or delivery arrangements are confirmed.']]],
        ];

        abort_unless(isset($pages[$page]), 404);

        $component = match ($page) {
            'about' => 'Store/About',
            'contact' => 'Store/Contact',
            default => 'Store/StaticPage',
        };
        $locator = null;

        if (
            $page === 'contact'
            && Schema::hasTable('business_settings')
            && Schema::hasColumn('business_settings', 'google_maps_api_key')
            && filled($business?->google_maps_api_key)
        ) {
            $locator = [
                'apiKey' => $business->google_maps_api_key,
                'locations' => [[
                    'title' => 'Prinx Tech',
                    'address1' => 'Uhindini Street',
                    'address2' => 'Dodoma, Tanzania',
                    'coords' => ['lat' => -6.1759075, 'lng' => 35.7490705],
                    'placeId' => 'ChIJ89WEjrrlTRgRikjhlXOgR0Q',
                ]],
            ];
        }

        return Inertia::render($component, [
            'page' => $pages[$page],
            'locator' => $locator,
        ]);
    }
}
