<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable=['category_id','brand_id','name','slug','sku','short_description','description','price','sale_price','sale_starts_at','sale_ends_at','stock_quantity','track_stock','condition','status','featured','merchant_sync_enabled','primary_image','gallery','gtin','mpn','google_product_category','product_type','item_group_id','color','size','gender','age_group','video_url','shipping_price','shipping_weight','warranty','specifications','low_stock_threshold','meta_title','meta_description','merchant_last_synced_at','merchant_sync_status','merchant_sync_error','sort_order'];
    protected function casts():array{return['price'=>'decimal:2','sale_price'=>'decimal:2','shipping_price'=>'decimal:2','shipping_weight'=>'decimal:3','stock_quantity'=>'integer','low_stock_threshold'=>'integer','track_stock'=>'boolean','featured'=>'boolean','merchant_sync_enabled'=>'boolean','gallery'=>'array','specifications'=>'array','sale_starts_at'=>'datetime','sale_ends_at'=>'datetime','merchant_last_synced_at'=>'datetime','sort_order'=>'integer'];}
    protected $appends=['current_price','image_url','gallery_urls','image_metadata','gallery_metadata','in_stock','merchant_ready','plain_description','low_stock'];
    protected static function booted():void{static::saving(function(Product $p):void{$p->slug=$p->slug?:Str::slug($p->name);$p->sku=strtoupper(trim($p->sku));$p->meta_title=$p->meta_title?:$p->name.' | Prinx Electronics';$p->meta_description=$p->meta_description?:Str::limit(self::descriptionToPlainText((string)($p->short_description?:$p->description)),155,'');});}
    public function getRouteKeyName():string{return'slug';}public function category(){return$this->belongsTo(Category::class);}public function brand(){return$this->belongsTo(Brand::class);}public function syncLogs(){return$this->hasMany(MerchantSyncLog::class);}public function inventoryMovements(){return$this->hasMany(InventoryMovement::class);}public function getLowStockAttribute():bool{return$this->track_stock&&$this->stock_quantity>0&&$this->stock_quantity<=$this->low_stock_threshold;}
    public function scopeActive(Builder $q):Builder{return$q->where('status','active');}
    public function scopeMerchantEligible(Builder $q):Builder{return$q->active()->where('merchant_sync_enabled',true)->whereNotNull('primary_image')->whereNotNull('sku')->whereNotNull('name')->whereNotNull('description')->where('price','>',0)->where(function(Builder $i):void{$i->whereNotNull('gtin')->orWhere(function(Builder $b):void{$b->whereNotNull('brand_id')->whereNotNull('mpn');});});}
    public function getCurrentPriceAttribute():float{$now=now();$valid=$this->sale_price!==null&&(!$this->sale_starts_at||$now->gte($this->sale_starts_at))&&(!$this->sale_ends_at||$now->lte($this->sale_ends_at));return(float)($valid?$this->sale_price:$this->price);}
    public function getImageUrlAttribute():string{if(!$this->primary_image)return asset('assets/accessory.svg');if(Str::startsWith($this->primary_image,['http://','https://','/']))return$this->primary_image;return Storage::disk('public')->url($this->primary_image);}
    public function getGalleryUrlsAttribute():array{return collect($this->gallery??[])->map(fn($image)=>Str::startsWith($image,['http://','https://','/'])?$image:Storage::disk('public')->url($image))->values()->all();}
    public function getImageMetadataAttribute():array{$d=(string)($this->meta_description?:$this->short_description?:Str::limit($this->plain_description,180,''));return['@type'=>'ImageObject','contentUrl'=>$this->image_url,'url'=>$this->image_url,'name'=>$this->name,'caption'=>$d,'description'=>$d,'creditText'=>config('media.credit'),'creator'=>['@type'=>'Organization','name'=>config('media.creator'),'url'=>config('app.url')],'copyrightNotice'=>config('media.copyright_notice')?:null,'license'=>config('media.rights_url')?:null,'acquireLicensePage'=>config('media.rights_url')?:null];}
    public function getGalleryMetadataAttribute():array{return collect($this->gallery_urls)->map(fn(string $url,int $index)=>['@type'=>'ImageObject','contentUrl'=>$url,'url'=>$url,'name'=>$this->name.' product image '.($index+2),'caption'=>(string)($this->meta_description?:$this->short_description),'creditText'=>config('media.credit'),'creator'=>['@type'=>'Organization','name'=>config('media.creator'),'url'=>config('app.url')],'copyrightNotice'=>config('media.copyright_notice')?:null,'license'=>config('media.rights_url')?:null,'acquireLicensePage'=>config('media.rights_url')?:null])->values()->all();}
    public function getInStockAttribute():bool{return!$this->track_stock||$this->stock_quantity>0;}
    public static function descriptionToPlainText(string $description):string{$description=preg_replace('/^\s{0,3}#{1,6}\s*/m','',$description)??$description;$description=preg_replace('/^\s*(?:[-*+•✓✔]|\d+[.)])\s+/m','',$description)??$description;$description=preg_replace('/\[([^\]]+)\]\([^)]+\)/','$1',$description)??$description;$description=str_replace(['|','`','**','__'],' ',$description);$description=preg_replace('/\s+/',' ',strip_tags($description))??$description;return trim($description);}
    public function getPlainDescriptionAttribute():string{return self::descriptionToPlainText((string)$this->description);}
    public function getMerchantReadyAttribute():bool{return$this->status==='active'&&$this->merchant_sync_enabled&&filled($this->sku)&&filled($this->name)&&filled($this->description)&&filled($this->primary_image)&&$this->price>0&&(filled($this->gtin)||(filled($this->brand_id)&&filled($this->mpn)));}
}
