import {createRequire} from 'node:module';
import fs from 'node:fs';import path from 'node:path';import http from 'node:http';import {fileURLToPath,pathToFileURL} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const require=createRequire(path.join(root,'tmp/php-check/package.json'));
const {PHP,PHPRequestHandler,loadPHPRuntime}=require('@php-wasm/universal');
const {getPHPLoaderModule}=await import(pathToFileURL(path.join(root,'tmp/php-check/node_modules/.pnpm/@php-wasm+node-8-3@3.1.53/node_modules/@php-wasm/node-8-3/index.js')).href);
const php=new PHP(await loadPHPRuntime(await getPHPLoaderModule(),{quit:(c,e)=>{throw e;}}));
function copy(from,to){php.mkdirTree(to);for(const e of fs.readdirSync(from,{withFileTypes:true})){if(e.name==='var')continue;const src=path.join(from,e.name),dst=to+'/'+e.name;if(e.isDirectory())copy(src,dst);else php.writeFile(dst,fs.readFileSync(src));}}
copy(path.join(root,'insurance'),'/www/insurance');
const lint=await php.run({code:`<?php $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/www/insurance'));foreach($it as $f)if($f->getExtension()==='php'){token_get_all(file_get_contents($f->getPathname()),TOKEN_PARSE);echo 'Syntax OK: '.$f->getFilename()."\\n";}`});
if(lint.errors||lint.exitCode)throw Error(lint.errors||lint.text);console.log(lint.text);
const domain=await php.run({scriptPath:'/www/insurance/tests/domain.php'});console.log(domain.text);if(domain.errors||domain.exitCode)throw Error(domain.errors||domain.text);
const handler=new PHPRequestHandler({php,documentRoot:'/www',absoluteUrl:'http://127.0.0.1:8766',cookieStore:false});
let cookie='';
async function request(url,body){const response=await handler.request({url,method:body?'POST':'GET',headers:{...(cookie?{cookie}:{}),...(body?{'content-type':'application/x-www-form-urlencoded'}:{})},...(body?{body:new TextEncoder().encode(new URLSearchParams(body).toString())}:{})});const set=response.headers['set-cookie'];if(set)cookie=set.map(v=>v.split(';')[0]).join('; ');if(response.errors)throw Error(response.errors);return response;}
let r=await request('/insurance/');if(!r.text.includes('Sign in'))throw Error('Login missing');const csrf=r.text.match(/name="csrf" value="([^"]+)"/)[1];
r=await request('/insurance/',{csrf,action:'login',email:'admin-a@example.test',password:'Test-only-password-9283'});if(r.httpStatusCode!==302)throw Error('Login failed: '+r.text);
for(const page of ['dashboard','clients','policies','investments','dues','plans','calculators','leads','documents','reports','settings']){r=await request('/insurance/?page='+page);if(r.httpStatusCode!==200||r.text.includes('Unable to display')||r.text.includes('request could not be completed'))throw Error('Page failed '+page);console.log('HTTP OK '+page);}
r=await request('/insurance/?page=clients&id=2');if(r.text.includes('Edit Bob'))throw Error('Tenant leak');console.log('HTTP cross-agency ID blocked');
r=await request('/insurance/?page=clients&id=1');if(r.text.includes('Unable to display')||!r.text.includes('CLIENT OVERVIEW')||!r.text.includes('History · previous coverage periods'))throw Error('Client hub failed');console.log('HTTP client-first profile with renewal history OK');
const versionResult=await php.run({code:`<?php require '/www/insurance/src/domain.php';echo ins_query('SELECT version FROM records WHERE id=3')->fetchColumn();`});
r=await request('/insurance/?page=clients&id=1',{csrf,action:'renew_period',id:'3',version:versionResult.text.trim(),start:'2025-01-01',expiry:'2025-12-31',premium:'1250',next_due:'2025-01-31',return_client:'1'});if(r.httpStatusCode!==302)throw Error('Renewal failed '+r.text);
r=await request('/insurance/?page=clients&id=1');if(!r.text.includes('2025-12-31')||!r.text.includes('2024-12-31'))throw Error('Renewal history missing');console.log('HTTP renewal saved and prior coverage history retained');
r=await request('/insurance/?page=policies&id=3');if(!r.text.includes('Open client workspace')||r.text.includes('>Save record</button>'))throw Error('Policy should open read-only');console.log('HTTP policy details are read-first');
r=await request('/insurance/?page=clients',{csrf:'wrong',action:'save',kind:'clients',name:'CSRF test'});if(!r.text.includes('Session expired'))throw Error('CSRF not blocked');console.log('HTTP CSRF blocked');
if(!process.argv.includes('--serve'))process.exit(0);
// Persist the isolated demo filesystem across local preview requests. Never mounts production data.
let queue=Promise.resolve();
http.createServer((req,res)=>{queue=queue.then(async()=>{try{if(!/^\/insurance\/(?:\?.*)?$/.test(req.url)&&!/^\/insurance\/assets\/[a-z.]+$/.test(req.url)){res.writeHead(403);res.end('Forbidden');return;}const chunks=[];for await(const c of req)chunks.push(c);const out=await handler.request({url:req.url,method:req.method,headers:req.headers,body:Buffer.concat(chunks)});res.writeHead(out.httpStatusCode,out.headers);res.end(out.bytes);}catch(e){console.error(e);res.writeHead(500);res.end('Local preview error');}})}).listen(8766,'127.0.0.1',()=>console.log('Isolated Insurance demo: http://127.0.0.1:8766/insurance/ — admin-a@example.test / Test-only-password-9283 (in-memory demo only)'));
