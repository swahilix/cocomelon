<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallmentContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_generate_branded_installment_contract_in_english_and_swahili(): void
    {
        $admin = $this->admin();
        $reference = $this->installmentPlan();

        $this->actingAs($admin)
            ->get('/admin/installments/'.$reference.'/contract?language=en')
            ->assertOk()
            ->assertSee('Installment Purchase Agreement')
            ->assertSee('Prinx')
            ->assertDontSee('Ptix')
            ->assertSee('assets/prinx-logo.png')
            ->assertSee('Contract Tracking')
            ->assertSee('id="tracking-barcode"', false)
            ->assertSee($reference)
            ->assertSee('CODE128')
            ->assertSee('Contract Customer')
            ->assertSee('+255712345678')
            ->assertSee('Test Smartphone')
            ->assertSee('TZS 100,000')
            ->assertSee('another item available in the shop with a similar value');

        $this->actingAs($admin)
            ->get('/admin/installments/'.$reference.'/contract?language=sw')
            ->assertOk()
            ->assertSee('Mkataba wa Manunuzi kwa Awamu')
            ->assertSee('Prinx')
            ->assertDontSee('Ptix')
            ->assertSee('Ufuatiliaji wa Mkataba')
            ->assertSee('id="tracking-barcode"', false)
            ->assertSee($reference)
            ->assertSee('CODE128')
            ->assertSee('Contract Customer')
            ->assertSee('Test Smartphone')
            ->assertSee('bidhaa nyingine inayopatikana dukani yenye thamani inayokaribiana');
    }

    public function test_staff_can_upload_a_private_signed_contract_copy(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $reference = $this->installmentPlan();
        $file = UploadedFile::fake()->create('signed-agreement.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post('/admin/installments/'.$reference.'/contract/signed', [
                'signed_contract' => $file,
            ])
            ->assertRedirect();

        $plan = DB::table('installment_plans')->where('reference', $reference)->first();

        $this->assertNotNull($plan->signed_contract_path);
        $this->assertSame('signed-agreement.pdf', $plan->signed_contract_original_name);
        $this->assertSame($admin->id, (int) $plan->signed_contract_uploaded_by);
        Storage::disk('local')->assertExists($plan->signed_contract_path);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Contract Staff',
            'email' => 'contract-staff@example.test',
            'phone' => '+255710000097',
            'password' => 'Secure1234',
            'is_admin' => true,
        ]);
    }

    private function installmentPlan(): string
    {
        $reference = 'INS-260825-CONTRACT';
        $now = now();

        $planId = DB::table('installment_plans')->insertGetId([
            'reference' => $reference,
            'user_id' => null,
            'customer_name' => 'Contract Customer',
            'customer_phone' => '+255712345678',
            'subtotal' => 100000,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_total' => 100000,
            'paid_amount' => 20000,
            'status' => 'active',
            'notes' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('installment_plan_items')->insert([
            'installment_plan_id' => $planId,
            'product_id' => 1,
            'product_name' => 'Test Smartphone',
            'sku' => 'PHONE-001',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
            'unit_cost' => null,
            'reservation_movement_id' => null,
            'return_movement_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $reference;
    }
}
