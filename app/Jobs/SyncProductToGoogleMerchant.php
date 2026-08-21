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

class SyncProductToGoogleMerchant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=3; public array $backoff=[60,300,900];
    public function __construct(public int $productId) {}
    public function handle(GoogleMerchantService $merchant): void
    {
        $product=Product::with(['brand','category'])->find($this->productId); if(!$product||!$product->merchant_sync_enabled||$product->status!=='active')return;
        $payload=$merchant->payload($product);$log=MerchantSyncLog::create(['product_id'=>$product->id,'action'=>$product->merchant_last_synced_at?'update':'insert','status'=>'pending','request_payload'=>$payload]);$product->forceFill(['merchant_sync_status'=>'pending','merchant_sync_error'=>null])->saveQuietly();
        try{$response=$merchant->sync($product);$log->update(['status'=>'success','response_payload'=>$response,'synced_at'=>now()]);$product->forceFill(['merchant_sync_status'=>'synced','merchant_last_synced_at'=>now(),'merchant_sync_error'=>null])->saveQuietly();}
        catch(Throwable $e){$log->update(['status'=>'failed','error_message'=>$e->getMessage(),'synced_at'=>now()]);$product->forceFill(['merchant_sync_status'=>'failed','merchant_sync_error'=>$e->getMessage()])->saveQuietly();throw $e;}
    }
}
