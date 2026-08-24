<?php
declare(strict_types=1);
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Method not allowed']);exit;}
$id=(int)($_POST['project_id']??0);$kind=(string)($_POST['kind']??'gallery');
if($id<1||!in_array($kind,['hero','gallery'],true)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Invalid project or upload type']);exit;}
if(empty($_FILES['image'])||!is_array($_FILES['image'])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'No image uploaded']);exit;}
$file=$_FILES['image'];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Upload failed']);exit;}
$max=5*1024*1024;if((int)$file['size']>$max){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Maximum image size is 5 MB']);exit;}
$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file((string)$file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Only JPG, PNG and WebP images are allowed']);exit;}
$root=dirname(__DIR__);$dir=$root.'/uploads/projects/'.$id;if(!is_dir($dir)&&!mkdir($dir,0755,true)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Unable to create upload directory']);exit;}
$name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];$target=$dir.'/'.$name;if(!move_uploaded_file((string)$file['tmp_name'],$target)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Unable to save image']);exit;}
$relative='uploads/projects/'.$id.'/'.$name;
try{$stmt=db()->prepare('SELECT featured_image,gallery FROM projects WHERE d=? LIMIT 1');$stmt->execute([$id]);$project=$stmt->fetch();if(!$project){@unlink($target);http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Project not found']);exit;}if($kind==='hero'){db()->prepare('UPDATE projects SET featured_image=? WHERE d=?')->execute([$relative,$id]);}else{$gallery=[];$decoded=json_decode((string)($project['gallery']??''),true);if(is_array($decoded))$gallery=array_values(array_filter($decoded,'is_string'));$gallery[]=$relative;db()->prepare('UPDATE projects SET gallery=? WHERE d=?')->execute([json_encode(array_values(array_unique($gallery)),JSON_UNESCAPED_SLASHES),$id]);}echo json_encode(['ok'=>true,'path'=>$relative,'kind'=>$kind]);}catch(Throwable $e){@unlink($target);http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Database update failed']);}