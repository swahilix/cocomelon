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
        $businessName = $value('business_name', config('app.name', 'Prinx Tech'));
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

        $deliveryEnabled = Schema::hasColumn('business_settings', 'delivery_enabled')
            ? (bool) ($business?->delivery_enabled ?? true)
            : true;
        $pickupEnabled = Schema::hasColumn('business_settings', 'pickup_enabled')
            ? (bool) ($business?->pickup_enabled ?? false)
            : false;
        $deliveryArea = Schema::hasColumn('business_settings', 'delivery_area')
            ? $value('delivery_area', $value('city', $value('region')))
            : $value('city', $value('region'));
        $deliveryEta = Schema::hasColumn('business_settings', 'delivery_eta')
            ? $value('delivery_eta')
            : null;
        $pickupLocation = Schema::hasColumn('business_settings', 'pickup_location')
            ? $value('pickup_location', $value('address'))
            : $value('address');
        $pickupInstructions = Schema::hasColumn('business_settings', 'pickup_instructions')
            ? $value('pickup_instructions')
            : null;

        $pages = [
            'about' => [
                'title' => 'About '.$businessName,
                'intro' => 'Reliable electronics, professional guidance and dependable after-sales support.',
                'sections' => [
                    ['heading' => 'What we do', 'body' => $value('description', $businessName.' supplies smartphones, computers, accessories and electronics solutions for individuals, institutions and businesses.')],
                    ['heading' => 'Our standard', 'body' => 'We focus on genuine product information, transparent pricing, responsive support and practical solutions.'],
                ],
            ],
            'contact' => [
                'title' => 'Contact Us',
                'intro' => 'Talk to our sales and technical team.',
                'sections' => $contactSections,
            ],
            'faq' => [
                'title' => 'Frequently Asked Questions',
                'intro' => 'Quick answers about ordering, delivery, pickup, warranty and product support.',
                'sections' => [
                    ['heading' => 'How do I know whether a product is available?', 'body' => 'Product pages use live store inventory where stock tracking is enabled. If a product shows a low-stock message, the displayed quantity comes from current inventory. You can also use the WhatsApp button on the product page to confirm availability before ordering.'],
                    ['heading' => 'Can I order through WhatsApp?', 'body' => 'Yes. Product pages include an Ask on WhatsApp option that sends the product name, current price and product link so our team can help you quickly.'],
                    ['heading' => 'Do you offer delivery?', 'body' => $deliveryEnabled
                        ? 'Yes. Delivery is currently available'.($deliveryArea ? ' in '.$deliveryArea : '').'. '.($deliveryEta ? 'Current estimate: '.$deliveryEta.'.' : 'The delivery estimate is shown at checkout and confirmed with your order.')
                        : 'Delivery is not currently enabled for online checkout. Contact our team to discuss available options.'],
                    ['heading' => 'Can I collect my order from the store?', 'body' => $pickupEnabled
                        ? 'Yes. Store pickup can be selected at checkout. '.($pickupLocation ? 'Pickup location: '.$pickupLocation.'. ' : '').($pickupInstructions ?: 'We will confirm when the order is ready.')
                        : 'Store pickup is not currently enabled for online checkout.'],
                    ['heading' => 'How do warranties work?', 'body' => 'Warranty coverage depends on the specific product and manufacturer. Where a product has a stated warranty, the product page shows it. Keep your proof of purchase and contact us if you need warranty support.'],
                    ['heading' => 'How do I request a return or refund?', 'body' => 'Contact Prinx Tech with your order details and the reason for the request. We will inspect the product and confirm the available remedy based on its condition, the issue reported and any applicable warranty terms.'],
                ],
            ],
            'shipping' => [
                'title' => 'Delivery & Store Pickup',
                'intro' => 'Know your fulfillment options before placing an order.',
                'sections' => array_values(array_filter([
                    $deliveryEnabled ? ['heading' => 'Delivery availability', 'body' => 'Delivery is available'.($deliveryArea ? ' in '.$deliveryArea : '').'. '.($deliveryEta ? 'Current estimated timing: '.$deliveryEta.'.' : 'Timing is confirmed with your order.')] : ['heading' => 'Delivery availability', 'body' => 'Online delivery is currently disabled. Contact our team if you need help with an order.'],
                    $pickupEnabled ? ['heading' => 'Store pickup', 'body' => 'Choose Store Pickup at checkout to collect without a delivery charge. '.($pickupLocation ? 'Pickup location: '.$pickupLocation.'. ' : '').($pickupInstructions ?: '')] : null,
                    ['heading' => 'Order confirmation', 'body' => 'Availability, fulfillment details and any special delivery arrangements are confirmed using the contact information submitted with the order.'],
                    ['heading' => 'Order tracking', 'body' => 'Use the order status tools on the website or contact our team with your order number for assistance.'],
                ])),
            ],
            'returns' => [
                'title' => 'Returns & Refunds',
                'intro' => 'A clear process for products that arrive with a verified issue or need support.',
                'sections' => [
                    ['heading' => 'Start a return request', 'body' => 'Contact Prinx Tech with your order number, product details and the reason for the return request. Keep the product, supplied accessories, packaging where available and proof of purchase for inspection.'],
                    ['heading' => 'Eligibility and inspection', 'body' => 'Eligibility depends on the product condition, the issue reported, signs of misuse or physical damage, and any applicable product or manufacturer warranty terms. Electronics may need inspection before a remedy is approved.'],
                    ['heading' => 'Possible remedies', 'body' => 'After inspection, the appropriate remedy may be troubleshooting, repair, replacement, store support or refund where applicable. We will explain the outcome before completing the request.'],
                    ['heading' => 'Change-of-mind requests', 'body' => 'Contact us before returning a product. Opened, activated, used or damaged electronics may not qualify for a change-of-mind return, especially where resale condition or manufacturer restrictions are affected.'],
                ],
            ],
            'warranty' => [
                'title' => 'Warranty Information',
                'intro' => 'Understand how product warranty support works at Prinx Tech.',
                'sections' => [
                    ['heading' => 'Product-specific coverage', 'body' => 'Warranty coverage varies by product and manufacturer. When a product has a stated warranty, that information appears on its product page and should be read together with the manufacturer or supplier terms.'],
                    ['heading' => 'What to keep', 'body' => 'Keep your order number, receipt or other proof of purchase, plus the product accessories and warranty documentation supplied with the item.'],
                    ['heading' => 'Getting warranty support', 'body' => 'Contact Prinx Tech and describe the issue. We may first help with troubleshooting. If inspection is required, our team will explain where and how to bring or send the product.'],
                    ['heading' => 'Common exclusions', 'body' => 'Warranty support may be affected by accidental or physical damage, liquid damage, unauthorized repair or modification, misuse, or other conditions excluded by the applicable manufacturer or supplier warranty. The specific product terms take priority.'],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'intro' => 'How '.$businessName.' handles customer information.',
                'sections' => [
                    ['heading' => 'Information collected', 'body' => 'We collect information required to process orders, respond to service requests and provide customer support.'],
                    ['heading' => 'Data protection', 'body' => 'Customer data is used for legitimate store operations and support and is not sold to advertisers.'],
                ],
            ],
            'terms' => [
                'title' => 'Terms & Conditions',
                'intro' => 'Terms applying to use of this website and purchases.',
                'sections' => [
                    ['heading' => 'Product information', 'body' => 'We work to keep prices, stock and specifications accurate. Confirmed order details take priority if an obvious listing error occurs.'],
                    ['heading' => 'Orders', 'body' => 'An order is accepted after availability and payment or fulfillment arrangements are confirmed.'],
                ],
            ],
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
