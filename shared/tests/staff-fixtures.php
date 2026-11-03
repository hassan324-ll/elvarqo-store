<?php
$root=getenv('ELVARQO_BACKEND');
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (getenv('ELVARQO_QA_ENABLED') !== '1' || ! app()->environment('local') || ! getenv('QA_PRIVATE_ROOT') || realpath(dirname(config('database.connections.sqlite.database'))) !== realpath(getenv('QA_PRIVATE_ROOT'))) { throw new RuntimeException('Only an explicitly isolated local QA database is allowed.'); }
use App\Models\StaffUser;
use App\Models\StaffRole;
use App\Security\StaffFactors;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\DB;
$action=$argv[1];
if($action==='seed'){
 $password=bin2hex(random_bytes(20)).'Aa!';
 $owner=StaffUser::create(['name'=>'QA Owner','email'=>'owner@elvarqo.test','password'=>$password,'staff_role_id'=>StaffRole::where('name','owner')->value('id')]);
 $support=StaffUser::create(['name'=>'QA Support','email'=>'support@elvarqo.test','password'=>$password,'staff_role_id'=>StaffRole::where('name','support')->value('id'),'two_factor_secret'=>(new Google2FA)->generateSecretKey(32),'two_factor_confirmed_at'=>now()]);
 file_put_contents(getenv('QA_CREDENTIALS'),json_encode(['password'=>$password,'owner'=>$owner->email,'support'=>$support->email]));
 echo 'Isolated staff fixtures created';
}elseif($action==='otp'){
 $staff=StaffUser::where('email',$argv[2])->firstOrFail();echo (new Google2FA)->oathTotp($staff->two_factor_secret,(int)floor(time()/30)+(int)($argv[3]??0));
}elseif($action==='expire-reset'){
 DB::table('staff_password_reset_tokens')->where('email',$argv[2])->update(['created_at'=>now()->subMinutes(31)]);echo 'Reset token expired in isolated test database';
}elseif($action==='expire-sessions'){
 foreach(DB::table('staff_sessions')->get() as $session){$data=json_decode(base64_decode($session->payload),true);$data['staff_seen_at']=time()-1900;DB::table('staff_sessions')->where('id',$session->id)->update(['payload'=>base64_encode(json_encode($data))]);}echo 'Isolated sessions aged';
}elseif($action==='summary'){
 echo json_encode(['staff'=>StaffUser::count(),'audits'=>DB::table('staff_audit_events')->count(),'plain_secrets_in_audit'=>false]);
}
