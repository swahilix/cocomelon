<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\SoftDeletes;
class HeroSlide extends Model{use HasFactory,SoftDeletes;protected $fillable=['item_type','product_id','service_id','eyebrow','badge','headline','description','primary_label','secondary_label','is_active','sort_order'];protected function casts():array{return['is_active'=>'boolean','sort_order'=>'integer'];}public function product(){return$this->belongsTo(Product::class);}public function service(){return$this->belongsTo(Service::class);}public function scopeActive(Builder $q):Builder{return$q->where('is_active',true);}}
