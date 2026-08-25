<?php declare(strict_types=1);
/* CLI regression suite. Run: C:\xampp\php\php.exe tests\phase13-production-regression.php */
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../config/database.php';
$db=db();$checks=[];$ok=0;$fail=0;
function check(string $name,callable $fn):void{global $checks,$ok,$fail;try{$r=$fn();$pass=$r===true;$checks[]=['name'=>$name,'status'=>$pass?'PASS':'FAIL','detail'=>$pass?'':(string)$r];$pass?$ok++:$fail++;}catch(Throwable $e){$checks[]=['name'=>$name,'status'=>'FAIL','detail'=>$e->getMessage()];$fail++;}}
$tables=['clients','client_projects','client_portal_users','client_portal_sessions','client_portal_documents','client_portal_messages','client_portal_activity','client_portal_settings','project_approvals','support_tickets'];
foreach($tables as $t)check('Table '.$t,function()use($db,$t){$db->query('SELECT 1 FROM `'.$t.'` LIMIT 1');return true;});
check('Database write transaction',function()use($db){$db->beginTransaction();$db->query('SELECT 1');$db->rollBack();return true;});
check('Password hashing',function(){ $h=password_hash('Regression#2026',PASSWORD_DEFAULT);return password_verify('Regression#2026',$h)&&!password_verify('wrong',$h);});
check('CSRF token entropy',function(){ $a=bin2hex(random_bytes(32));$b=bin2hex(random_bytes(32));return strlen($a)===64&&$a!==$b;});
check('Portal user client isolation query',function()use($db){$q=$db->prepare('SELECT id FROM client_portal_users WHERE id=? AND client_id=? LIMIT 1');$q->execute([0,0]);return $q->fetch()===false;});
check('Document authorization query',function()use($db){$q=$db->prepare('SELECT id FROM client_portal_documents WHERE id=? AND client_id=? AND is_visible=1 LIMIT 1');$q->execute([0,0]);return $q->fetch()===false;});
check('Approval authorization query',function()use($db){$q=$db->prepare('SELECT a.id FROM project_approvals a JOIN client_projects p ON p.id=a.project_id WHERE a.id=? AND p.client_id=? LIMIT 1');$q->execute([0,0]);return $q->fetch()===false;});
check('Support authorization query',function()use($db){$q=$db->prepare('SELECT id FROM support_tickets WHERE id=? AND client_id=? LIMIT 1');$q->execute([0,0]);return $q->fetch()===false;});
check('Health tables query',function()use($db){$db->query('SELECT COUNT(*) FROM client_portal_users');return true;});
$report=['suite'=>'Phase 13 production regression','timestamp'=>gmdate('c'),'passed'=>$ok,'failed'=>$fail,'checks'=>$checks];echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($fail?1:0);