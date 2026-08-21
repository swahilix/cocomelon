<?php

namespace App\Jobs;

use App\Models\MerchantSyncLog;
use App\Models\Product;
use App\Services\GoogleMerchantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeleteProductFromGoogleMerchant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3; public array $backoff = [60,300,900];
    public function __construct(public int $productId) {}
    public function handle(GoogleMerchantService $merchant): void
    {
        $product=Product::withTrashed()->find($this->productId); if(!$product||!$product->merchant_last_synced_at||!$merchant->configured()) return;
        $log=MerchantSyncLog::create(['product_id'=>$product->id,'action'=>'delete','status'=>'pending']);
        try{$response=$merchant->delete($product);$log->update(['status'=>'success','response_payload'=>$response,'synced_at'=>now()]);}
        catch(Throwable $e){$log->update(['status'=>'failed','error_message'=>$e->getMessage(),'synced_at'=>now()]);throw $e;}
    }
}
