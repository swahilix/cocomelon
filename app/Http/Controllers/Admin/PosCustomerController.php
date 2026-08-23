<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosCustomerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
        ]);

        $customer = [
            'name' => trim($validated['name']),
            'phone' => isset($validated['phone']) && trim($validated['phone']) !== '' ? trim($validated['phone']) : null,
            'email' => isset($validated['email']) && trim($validated['email']) !== '' ? trim($validated['email']) : null,
            'tin' => isset($validated['tin']) && trim($validated['tin']) !== '' ? trim($validated['tin']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $customer['id'] = DB::table('pos_customers')->insertGetId($customer);

        return response()->json([
            'customer' => [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'tin' => $customer['tin'],
            ],
        ], 201);
    }
}
