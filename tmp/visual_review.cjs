const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
  const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const page=await browser.newPage({viewport:{width:1440,height:1000}}); const errors=[];
  page.on('pageerror',e=>errors.push(e.message));fs.mkdirSync('tmp/registro_preview',{recursive:true});
  try{
    await page.goto('http://127.0.0.1:8000');
    await page.getByRole('button',{name:'Admin',exact:true}).waitFor();
    await page.screenshot({path:'tmp/registro_preview/login.png',fullPage:true});
    await page.getByRole('button',{name:'Admin',exact:true}).click();
    await page.getByRole('button',{name:'Entrar',exact:true}).click();
    await page.getByRole('heading',{name:'Registro de estudiantes',exact:true}).waitFor();
    await page.locator('.tabla-estudiantes').waitFor();
    await page.screenshot({path:'tmp/registro_preview/directorio.png',fullPage:true});
    await page.getByRole('button',{name:'+ Registrar estudiante',exact:true}).click();
    await page.getByRole('heading',{name:'Registrar estudiante',exact:true}).waitFor();
    await page.getByLabel('Nombres',{exact:true}).fill('Prueba visual');
    await page.screenshot({path:'tmp/registro_preview/formulario.png',fullPage:true});
    await page.getByRole('button',{name:'Cancelar',exact:true}).click();
    await page.setViewportSize({width:390,height:844});
    await page.screenshot({path:'tmp/registro_preview/movil.png',fullPage:true});
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow móvil');
    await page.getByRole('button',{name:'Cursos',exact:true}).click();
    await page.getByRole('heading',{name:'Oferta académica',exact:true}).waitFor();
    await page.getByRole('button',{name:'Pagos',exact:true}).click();
    await page.getByRole('heading',{name:'Pagos y transacciones',exact:true}).waitFor();
    assert.deepEqual(errors,[]);console.log(JSON.stringify({desktop:true,mobile:true,registrationForm:true,preservedNavigation:true,pageErrors:errors}));
  } finally {await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
