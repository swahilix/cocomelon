<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@prinx.co.tz')],
            [
                'name' => 'Prinx Administrator',
                'phone' => '+255 000 000 000',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $cats = collect([
            ['Smartphones', '/assets/phone.svg'],
            ['Computers', '/assets/laptop.svg'],
            ['Accessories', '/assets/accessory.svg'],
            ['Networking', '/assets/accessory.svg'],
        ])->mapWithKeys(function ($item) {
            $category = Category::withTrashed()->firstOrNew(['slug' => Str::slug($item[0])]);
            $category->fill([
                'name' => $item[0],
                'type' => 'product',
                'image' => $item[1],
                'is_active' => true,
            ]);
            $category->deleted_at = null;
            $category->save();

            return [$item[0] => $category];
        });

        $serviceCategories = collect([
            ['Software Support', '/assets/laptop.svg'],
            ['Network & Installation', '/assets/accessory.svg'],
            ['Computer Support & Repair', '/assets/laptop.svg'],
            ['Phone & Tablet Repair', '/assets/repair.svg'],
        ])->mapWithKeys(function ($item) {
            $category = Category::withTrashed()->firstOrNew(['slug' => Str::slug($item[0])]);
            $category->fill([
                'name' => $item[0],
                'type' => 'service',
                'image' => $item[1],
                'is_active' => true,
            ]);
            $category->deleted_at = null;
            $category->save();

            return [$item[0] => $category];
        });

        $brands = collect(['Samsung', 'Apple', 'HP', 'Dell', 'TP-Link', 'Prinx'])->mapWithKeys(function ($name) {
            $brand = Brand::withTrashed()->firstOrNew(['slug' => Str::slug($name)]);
            $brand->fill(['name' => $name, 'is_active' => true]);
            $brand->deleted_at = null;
            $brand->save();

            return [$name => $brand];
        });

        $products = [
            ['Galaxy A Series Smartphone', 'PRX-SAM-A001', 'Smartphones', 'Samsung', 850000, 18, '/assets/phone.svg'],
            ['HP Business Laptop 15', 'PRX-HP-L015', 'Computers', 'HP', 1650000, 9, '/assets/laptop.svg'],
            ['65W Universal USB-C Charger', 'PRX-ACC-065', 'Accessories', 'Prinx', 95000, 42, '/assets/accessory.svg'],
            ['Dual-Band Wi-Fi Router', 'PRX-TPL-R001', 'Networking', 'TP-Link', 145000, 15, '/assets/accessory.svg'],
        ];

        foreach ($products as [$name, $sku, $categoryName, $brandName, $price, $stock, $image]) {
            $product = Product::withTrashed()->firstOrNew(['sku' => $sku]);
            $product->fill([
                'name' => $name,
                'category_id' => $cats[$categoryName]->id,
                'brand_id' => $brands[$brandName]->id,
                'short_description' => 'Reliable electronics product available from Prinx Electronics.',
                'description' => 'Quality electronics product supplied by Prinx Electronics with clear product information and dependable support.',
                'price' => $price,
                'stock_quantity' => $stock,
                'primary_image' => $image,
                'mpn' => $sku.'TZ',
                'status' => 'active',
                'condition' => 'new',
                'track_stock' => true,
                'merchant_sync_enabled' => true,
                'shipping_price' => 0,
                'featured' => true,
            ]);
            $product->deleted_at = null;
            $product->save();
        }

        $services = [
            ['Software Design & Installation', 'Software Support', '/assets/laptop.svg', 'Software design, installation, troubleshooting, repair and updates.'],
            ['Network Installation', 'Network & Installation', '/assets/accessory.svg', 'Professional network setup, Wi-Fi configuration and electronics installation.'],
            ['Computer Repair & Upgrade', 'Computer Support & Repair', '/assets/laptop.svg', 'Computer diagnostics, repair, maintenance, upgrades and software support.'],
        ];

        foreach ($services as [$name, $categoryName, $image, $description]) {
            $service = Service::withTrashed()->firstOrNew(['slug' => Str::slug($name)]);
            $service->fill([
                'name' => $name,
                'category_id' => $serviceCategories[$categoryName]->id,
                'short_description' => $description,
                'description' => $description,
                'image' => $image,
                'status' => 'active',
                'featured' => true,
            ]);
            $service->deleted_at = null;
            $service->save();
        }
    }
}
