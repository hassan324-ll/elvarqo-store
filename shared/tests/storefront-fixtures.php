<?php
$root=getenv('ELVARQO_BACKEND');require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(getenv('ELVARQO_QA_ENABLED')!=='1'||!app()->environment('local')||!getenv('QA_PRIVATE_ROOT')||realpath(dirname(config('database.connections.sqlite.database')))!==realpath(getenv('QA_PRIVATE_ROOT')))throw new RuntimeException('An explicitly isolated local QA database is required.');
use App\Models\CatalogueProduct as Product;use App\Models\CatalogueVariant as Variant;use App\Models\CatalogueMedia as Media;use App\Models\CatalogueCollection as Collection;use App\Models\StockLocation;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\File;use Illuminate\Support\Str;use Symfony\Component\Process\Process;
$action=$argv[1]??'';
if($action==='seed'){
 $collection=Collection::create(['title'=>'QA Everyday','slug'=>'qa-everyday','kind'=>'collection','active'=>true,'position'=>1,'description'=>'Isolated browser acceptance collection.']);Collection::create(['title'=>'QA Empty','slug'=>'qa-empty','active'=>true]);$location=StockLocation::create(['name'=>'QA warehouse','code'=>'QA']);
 $templates=[];foreach(['polo','overshirt'] as $name){$dir=getenv('QA_PRIVATE_ROOT').'/'.$name;File::ensureDirectoryExists($dir);File::copy(dirname($root).'/storefront/public/concept/'.$name.'.jpg',$dir.'/original');$p=new Process([config('catalogue.node_binary'),base_path('media/process.cjs'),$dir.'/original',$dir]);$p->mustRun();$templates[$name]=['dir'=>$dir,'meta'=>json_decode($p->getOutput(),true)];}
 $ids=[];$mainVariant=null;
 for($n=0;$n<15;$n++){
  $draft=$n===14;$title=$n===0?'QA Knit Polo':($draft?'QA Hidden Product':'QA Piece '.str_pad((string)$n,2,'0',STR_PAD_LEFT));$slug=$n===0?'qa-knit-polo':($draft?'qa-hidden-product':'qa-piece-'.$n);
  $p=Product::create(['title'=>$title,'slug'=>$slug,'product_type'=>'Knitwear','status'=>$draft?'draft':'published','description'=>'Isolated browser fixture, not merchant stock.','material'=>'QA material information','fit'=>'QA regular fit','care'=>'QA garment care','measurements'=>[['size'=>'M','chest_cm'=>104,'length_cm'=>70,'shoulder_cm'=>44],['size'=>'L','chest_cm'=>110,'length_cm'=>72,'shoulder_cm'=>46]],'seo_title'=>$title.' | ELVARQO','seo_description'=>'QA supplier-entered product summary.','published_at'=>now(),'first_published_at'=>now()->subMinutes($n)]);$ids[]=$p->id;DB::table('catalogue_slugs')->insert(['slug'=>$slug,'product_id'=>$p->id]);DB::table('catalogue_collection_product')->insert(['product_id'=>$p->id,'collection_id'=>$collection->id,'position'=>$n]);$options=[];foreach(['colour','size'] as $name)$options[$name]=DB::table('catalogue_options')->insertGetId(['product_id'=>$p->id,'name'=>$name]);$values=[];
  foreach([['Navy','M'],['Navy','L'],['Sand','M'],['Sand','L']] as $i=>[$colour,$size]){
   foreach(['colour'=>$colour,'size'=>$size] as $name=>$value)if(!isset($values[$name][$value]))$values[$name][$value]=DB::table('catalogue_option_values')->insertGetId(['option_id'=>$options[$name],'value'=>$value]);
   $v=Variant::create(['product_id'=>$p->id,'colour_value_id'=>$values['colour'][$colour],'size_value_id'=>$values['size'][$size],'sku'=>'QA-'.$n.'-'.$i,'price_minor'=>$n===0?6800+$i*200:4500+$n*100+$i*100,'currency'=>'GBP','active'=>true,'fit'=>$i===0?'QA Navy fit override':null,'care'=>$i===0?'QA Navy care override':null,'measurements'=>$i===0?['chest_cm'=>106]:null]);
   DB::table('stock_balances')->insert(['variant_id'=>$v->id,'location_id'=>$location->id,'on_hand'=>$i===1?0:10,'reserved'=>$i===0?2:0]);if($n===0&&$i===0)$mainVariant=$v->id;
   if($n===0&&in_array($i,[0,2])){$name=$i===0?'polo':'overshirt';$uuid=(string)Str::uuid();$dir=config('catalogue.media_root').'/'.$uuid;File::copyDirectory($templates[$name]['dir'],$dir);$m=$templates[$name]['meta'];Media::create(['id'=>$uuid,'product_id'=>$p->id,'variant_id'=>$v->id,'alt'=>'QA '.$colour.' product view','position'=>$i,'width'=>$m['width'],'height'=>$m['height'],'sizes'=>$m['sizes'],'original_format'=>$m['format'],'sha256'=>hash_file('sha256',$dir.'/original')]);}
  }
  $uuid=(string)Str::uuid();$dir=config('catalogue.media_root').'/'.$uuid;File::copyDirectory($templates['polo']['dir'],$dir);$m=$templates['polo']['meta'];Media::create(['id'=>$uuid,'product_id'=>$p->id,'alt'=>'QA shared product image','position'=>3,'width'=>$m['width'],'height'=>$m['height'],'sizes'=>$m['sizes'],'original_format'=>$m['format'],'sha256'=>hash_file('sha256',$dir.'/original')]);
 }
 file_put_contents(getenv('QA_PRIVATE_ROOT').'/fixture-ids.json',json_encode(['product_id'=>$ids[0],'variant_id'=>$mainVariant,'draft_id'=>end($ids)]));echo 'Isolated storefront fixtures created';
}else{
 $ids=json_decode(file_get_contents(getenv('QA_PRIVATE_ROOT').'/fixture-ids.json'),true);$p=Product::findOrFail($ids['product_id']);
 if($action==='price'){Variant::where('id',$ids['variant_id'])->update(['price_minor'=>6900]);}
 elseif($action==='unpublish'){$p->status='draft';$p->save();}
 elseif($action==='publish'){$p->status='published';$p->save();}
 elseif($action==='rename'){$p->slug='qa-polo-renamed';$p->save();DB::table('catalogue_slugs')->updateOrInsert(['slug'=>$p->slug],['product_id'=>$p->id]);}
 elseif($action==='summary'){echo json_encode(['stock_movements'=>DB::table('stock_movements')->count(),'reserved_total'=>DB::table('stock_balances')->sum('reserved')]);}
 else throw new RuntimeException('Unknown fixture action');
}
