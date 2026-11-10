// Runs the exact scripts supported by the original 56-request collection, without new dependencies.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const file = path.resolve(__dirname, '../../../Taller_4_-_API_Cursos_y_Matriculas.postman_collection.json');
const collection = JSON.parse(fs.readFileSync(file, 'utf8'));
const base = process.argv[2];
if (!/^http:\/\/127\.0\.0\.1:8011\/api\/v1$/.test(base || '')) throw new Error('Use the isolated test server on port 8011');
const vars = new Map(collection.variable.map(v => [v.key,v.value])); vars.set('baseUrl',base);
const resolve = value => String(value).replace(/\{\{([^}]+)\}\}/g, (_,k) => {assert(vars.has(k),'Missing collection variable '+k);return vars.get(k)});
const requests = items => items.flatMap(i => i.item ? requests(i.item) : [i]);
let tests=0;
(async () => {
  for (const item of requests(collection.item)) {
    const pm = {collectionVariables:{set:(k,v)=>vars.set(k,v),get:k=>vars.get(k)},test:(_name,fn)=>{fn();tests++}};
    const run = phase => (item.event||[]).filter(e=>e.listen===phase).forEach(e=>vm.runInNewContext(e.script.exec.join('\n'),{pm,Date,String},{timeout:1000}));
    run('prerequest');
    const req=item.request;const headers={};
    for(const h of req.header||[])if(!h.disabled)headers[h.key]=resolve(h.value);
    if(req.auth?.type==='bearer')headers.Authorization='Bearer '+resolve(req.auth.bearer.find(x=>x.key==='token').value);
    const response=await fetch(resolve(req.url.raw),{method:req.method,headers,body:req.body?.raw?resolve(req.body.raw):undefined});
    const raw=await response.text();
    pm.response={json:()=>JSON.parse(raw),to:{have:{status:expected=>assert.equal(response.status,expected,item.name)}}};
    run('test');
    process.stdout.write(`${response.status} ${item.name}\n`);
  }
  console.log(JSON.stringify({requests:requests(collection.item).length,tests,result:'passed'}));
})().catch(e=>{console.error(e.message);process.exitCode=1});
