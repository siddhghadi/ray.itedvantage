<?php
// PHP built-in development server only. Block private paths before static serving.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
if(str_contains($path,'..')||preg_match('~/(?:src|var|bin|tests|storage|\.git|tmp)(?:/|$)~',$path)||str_contains($path,'config.php')){http_response_code(403);exit('Forbidden');}
if(str_starts_with($path,'/insurance/assets/')){return false;}
if($path==='/insurance/'||$path==='/insurance/index.php'){require __DIR__.'/index.php';return true;}
http_response_code(404);echo 'Open /insurance/ for the local Insurance CRM.';
