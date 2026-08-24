<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . app_url('index.php#contact')); exit; }
$name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));$phone=trim((string)($_POST['phone']??''));$company=trim((string)($_POST['company']??''));$service=trim((string)($_POST['service']??''));$budget=trim((string)($_POST['budget']??''));$message=trim((string)($_POST['message']??''));$errors=[];
if($name==='')$errors[]='Please provide your name.';if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Please provide a valid email address.';if($message==='')$errors[]='Please tell us about your project.';
if($errors){http_response_code(422);echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enquiry Error | '.htmlspecialchars(APP_NAME).'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-dark text-white"><main class="container py-5"><div class="alert alert-danger"><h4>Please check your enquiry</h4><ul class="mb-0">';foreach($errors as $error)echo '<li>'.htmlspecialchars($error,ENT_QUOTES,'UTF-8').'</li>';echo '</ul></div><a href="'.htmlspecialchars(app_url('index.php#contact'),ENT_QUOTES,'UTF-8').'" class="btn btn-light">Go back</a></main></body></html>';exit;}
$stmt=db()->prepare('INSERT INTO messages (name,email,phone,company,service,budget,message,status) VALUES (?,?,?,?,?,?,?,\'new\')');$stmt->execute([$name,$email,$phone,$company,$service,$budget,$message]);header('Location: '.app_url('index.php?sent=1#contact'));exit;
