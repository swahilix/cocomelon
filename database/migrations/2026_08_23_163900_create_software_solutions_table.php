<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_solutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('demo_url', 2048)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('meta_title', 190)->nullable();
            $table->string('meta_description', 180)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $starter = [
            ['POS & Inventory Management System', 'Manage sales, stock, purchases and reports in real time.'],
            ['Microfinance Management System', 'Manage loans, savings, clients and repayments efficiently.'],
            ['School Management System', 'Admissions, student records, fees, exams and communication.'],
            ['Hospital / Clinic Management System', 'Patient records, appointments, billing and pharmacy management.'],
            ['HR & Payroll Management System', 'Manage employees, payroll, leaves and performance.'],
            ['SACCO / Loan Management System', 'Member management, savings, shares and loan processing.'],
            ['Hotel / Booking Management System', 'Reservations, rooms, guests, billing and housekeeping.'],
            ['Custom Business Website / ERP Solutions', 'Tailored web and ERP solutions for your unique business.'],
        ];

        foreach ($starter as $index => [$name, $shortDescription]) {
            DB::table('software_solutions')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'short_description' => $shortDescription,
                'description' => $shortDescription,
                'status' => 'active',
                'featured' => $index < 4,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('software_solutions');
    }
};
