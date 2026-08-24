<?php
declare(strict_types=1);
function mail_config(): array {
    return [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int)(getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from_email' => getenv('MAIL_FROM_EMAIL') ?: (getenv('SMTP_USERNAME') ?: 'no-reply@example.com'),
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Distinguished Web Services',
    ];
}
function send_smtp_email(string $to,string $subject,string $html): array {
    $c=mail_config();
    if($c['username']===''||$c['password']==='') return ['success'=>false,'error'=>'SMTP credentials are not configured.'];
    $host=$c['host'];$port=$c['port'];$fp=@fsockopen(($c['encryption']==='ssl'?'ssl://':'').$host,$port,$errno,$errstr,15);
    if(!$fp)return ['success'=>false,'error'=>'SMTP connection failed: '.$errstr];
    $read=function()use($fp){return fgets($fp,2048)?:'';};$write=function(string $s)use($fp){fwrite($fp,$s."\r\n");};$expect=function(string $code)use($read){$r=$read();if(strpos($r,$code)!==0)throw new RuntimeException(trim($r));};
    try{$expect('220');$write('EHLO distinguished-web-services.local');$expect('250');if($c['encryption']==='tls'){$write('STARTTLS');$expect('220');if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('TLS negotiation failed.');$write('EHLO distinguished-web-services.local');$expect('250');}$write('AUTH LOGIN');$expect('334');$write(base64_encode($c['username']));$expect('334');$write(base64_encode($c['password']));$expect('235');$write('MAIL FROM:<'.$c['from_email'].'>');$expect('250');$write('RCPT TO:<'.$to.'>');$expect('250');$write('DATA');$expect('354');$headers='From: '.mb_encode_mimeheader($c['from_name']).' <'.$c['from_email'].'>\r\nTo: <'.$to.'>\r\nSubject: '.mb_encode_mimeheader($subject).'\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n';$write($headers."\r\n".$html."\r\n.");$expect('250');$write('QUIT');fclose($fp);return ['success'=>true,'error'=>null];}catch(Throwable $e){fclose($fp);return ['success'=>false,'error'=>$e->getMessage()];}}
