<?php

namespace Tests\Feature;

use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\CommerceFixture;
use Tests\TestCase;

class GdUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_and_category_upload_apis_use_gd_without_a_child_process(): void
    {
        if (! extension_loaded('gd') || ! (imagetypes() & IMG_WEBP)) {
            $this->markTestSkipped('GD/WebP runtime required.');
        }
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'elvarqo-gd-api-'.Str::uuid();
        config(['catalogue.image_driver' => 'gd', 'catalogue.node_binary' => 'deliberately-missing-node', 'catalogue.media_root' => $directory]);
        $staff = StaffUser::create(['name' => 'GD QA', 'email' => 'gd@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', 'owner')->value('id'), 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $staff->refresh();
        $this->actingAs($staff, 'staff');
        $this->withSession(['staff_version' => $staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp, '_token' => 'gd-token']);
        $headers = ['Accept' => 'application/json', 'Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'gd-token'];
        $variant = CommerceFixture::seed();
        $id = DB::table('catalogue_variants')->where('id', $variant)->value('product_id');
        DB::table('catalogue_products')->where('id', $id)->update(['status' => 'draft']);
        $png = imagecreatetruecolor(800, 1000);
        ob_start();
        imagepng($png);
        $bytes = ob_get_clean();
        try {
            $saved = $this->post('/api/v1/staff/catalogue/products/'.$id.'/media', ['version' => 1, 'alt' => 'GD API photo', 'file' => UploadedFile::fake()->createWithContent('photo.png', $bytes)], $headers)->assertCreated()->json('data');
            $media = $saved['product']['media'][0];
            $this->assertFileExists($directory.'/'.$media['id'].'/640.webp');
            $this->assertFileExists($directory.'/'.$media['id'].'/1280.webp');
            $this->assertFileExists($directory.'/'.$media['id'].'/1920.webp');
            $parent = DB::table('catalogue_collections')->insertGetId(['title' => 'Women', 'slug' => 'women', 'kind' => 'collection', 'active' => true]);
            $category = $this->post('/api/v1/staff/catalogue/collections', ['title' => 'Knitwear', 'slug' => 'womens-knitwear', 'kind' => 'category', 'parent_id' => $parent, 'position' => 1, 'active' => true, 'image' => UploadedFile::fake()->createWithContent('category.png', $bytes)], $headers)->assertCreated()->json('data.collection');
            $this->get('/api/v1/catalogue/category-images/'.$category['id'].'/'.$category['image_id'])->assertOk()->assertHeader('Content-Type', 'image/webp');
            $this->assertNotEmpty(DB::table('catalogue_collections')->where('id', $category['id'])->value('image_webp'));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
