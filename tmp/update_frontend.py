from pathlib import Path
p=Path('taller4-api/public/app')
html=(p/'index.html').read_text(encoding='utf-8')
css=html.split('<style>')[1].split('</style>')[0]
js=html.split('<script>')[1].split('</script>')[0]
html=html[:html.index('<style>')]+'<link rel="stylesheet" href="styles.css">'+html[html.index('</style>')+8:]
html=html[:html.index('<script>')]+'<script src="app.js" defer></script>\n</body>\n</html>\n'
html=html.replace('<div id="toast">','<div id="toast" role="status" aria-live="polite">')
html=html.replace('<div id="modal">','<div id="modal" role="dialog" aria-modal="true" aria-label="Formulario">')
js=js.replace("const API = '/api/v1';", "const API = '/api/v1';\nlet configuracion = { simulatedEnabled: false };\nlet idempotencias = new Map();")
js=js.replace('async function api(metodo, ruta, cuerpo) {','async function api(metodo, ruta, cuerpo, extraHeaders = {}) {')
js=js.replace("...(sesion ? { 'Authorization': 'Bearer ' + sesion.token } : {}) },", "...(sesion ? { 'Authorization': 'Bearer ' + sesion.token } : {}), ...extraHeaders },")
js=js.replace("throw new Error((datos.message || 'Error ' + r.status) + det);", "throw new Error((datos.message || 'Error ' + r.status) + det + (r.status >= 500 && datos.correlationId ? ' · Referencia: ' + datos.correlationId : ''));")
js=js.replace("function guardar() { sessionStorage.setItem('sesion', JSON.stringify(sesion)); }", "function guardar() { sessionStorage.removeItem('sesion'); } // Access tokens remain in memory.")
js=js.replace("value=\"admin@ut.edu.co\"", 'autocomplete="username"')
js=js.replace("value=\"password123\"", 'autocomplete="current-password"')
js=js.replace('<div class="rapido">','<p class="nota">Usa tu cuenta local o el acceso institucional.</p><button class="b gris" id="institucional">Acceso institucional</button><div class="rapido" style="display:${configuracion.simulatedEnabled ? \'flex\' : \'none\'}">')
js=js.replace("  $('#entrar').onclick = entrar;", "  $('#entrar').onclick = async () => { $('#entrar').disabled = true; try { await entrar(); } finally { if ($('#entrar')) $('#entrar').disabled = false; } };\n  $('#institucional').onclick = iniciarIdentidad;")
js=js.replace("  if (sesion.rol === 'ADMIN') tabs.push(['docentes', 'Docentes']);", "  if (sesion.rol === 'ADMIN') tabs.push(['docentes', 'Docentes']);\n  if (sesion.rol !== 'DOCENTE') tabs.push(['pagos', 'Pagos']);\n  tabs.push(['notificaciones', 'Avisos']);\n  if (sesion.rol === 'ADMIN') tabs.push(['operacion', 'Operación']);")
js=js.replace("docentes: vistaDocentes })[tab]();", "docentes: vistaDocentes, pagos: vistaPagos, notificaciones: vistaNotificaciones, operacion: vistaOperacion })[tab]();")
js=js.replace("try { await alGuardar(v); $('#modal').style.display = 'none'; } catch (e) { aviso(e.message, false); }", "const boton = $('#m_ok'); boton.disabled = true;\n    try { await alGuardar(v); $('#modal').style.display = 'none'; } catch (e) { aviso(e.message, false); } finally { boton.disabled = false; }")
js=js.replace("const cargar = async () => {", "const cargar = async (pagina = 1) => {")
js=js.replace("const cursos = await lista('/cursos?' + q);", "const datos = await paginaApi('/cursos?' + q, pagina); const cursos = datos.data;")
js=js.replace("      document.querySelectorAll('[data-mat]')", "      paginador(datos, cargar);\n      document.querySelectorAll('[data-mat]')")
js=js.replace("$('#buscar').onclick = cargar;", "$('#buscar').onclick = () => cargar();")
# Each existing table keeps its rendering and actions, with explicit pagination.
js=js.replace("async function vistaMatriculas() {", "async function vistaMatriculas(pagina = 1) {")
js=js.replace("const ms = await lista('/matriculas?limit=100');", "const datos = await paginaApi('/matriculas', pagina); const ms = datos.data;")
js=js.replace('<th>Estado</th><th>Fecha</th><th></th>', '<th>Estado académico</th><th>Fecha</th><th>Pago</th><th></th>')
js=js.replace("        <td>${m.estado === 'ACTIVA'", "        <td>${m.pago ? `<span class=\"tag ${esc(m.pago.estado)}\">${esc(m.pago.estado)}</span>` : 'Sin orden'}</td><td>${m.estado === 'ACTIVA' && sesion.rol !== 'DOCENTE' ? `<button class=\"b\" data-orden=\"${m.id}\">${m.pago ? 'Ver pago' : 'Generar orden'}</button> ` : ''}${m.estado === 'ACTIVA'")
js=js.replace("    document.querySelectorAll('[data-can]')", "    paginador(datos, vistaMatriculas);\n    document.querySelectorAll('[data-orden]').forEach(b => b.onclick = async () => {\n      b.disabled = true; try { await api('POST', '/pagos', { matriculaId: +b.dataset.orden }); tab = 'pagos'; pintar(); } catch (e) { aviso(e.message, false); b.disabled = false; }\n    });\n    document.querySelectorAll('[data-can]')")
js=js.replace("const es = await lista('/estudiantes?limit=100&q=' + encodeURIComponent($('#fq').value));", "const datos = await paginaApi('/estudiantes?q=' + encodeURIComponent($('#fq').value), pagina); const es = datos.data;")
js=js.replace("const ds = await lista('/docentes?limit=100');", "const datos = await paginaApi('/docentes', pagina); const ds = datos.data;")
js=js.replace("      document.querySelectorAll('[data-del]')", "      paginador(datos, cargar);\n      document.querySelectorAll('[data-del]')")
a=js.index('// ---------- Inicio ----------')
js=js[:a]
(p/'styles.css').write_text(css+'''\n  .nota { color:#526174; font-size:13px; line-height:1.5; }
  .PENDIENTE { background:#946200; } .APROBADO { background:var(--verde); }
  .RECHAZADO { background:var(--rojo); } .REEMBOLSADO { background:#6b4f91; }
  .paginacion { display:flex; gap:12px; align-items:center; justify-content:flex-end; margin-top:16px; font-size:13px; }
  button:disabled { opacity:.55; cursor:wait; } button:focus-visible,input:focus-visible,select:focus-visible { outline:3px solid #79a7e3; outline-offset:2px; }
  .resumen { display:flex; gap:12px; flex-wrap:wrap; } .resumen .card { flex:1; min-width:180px; }
  @media(max-width:600px) { header { padding:12px 16px; gap:8px; } header h1 { flex-basis:100%; } main { padding:0 10px; margin:12px auto; } .card { padding:12px; } .der { gap:12px; flex-wrap:wrap; } nav button { flex:1; padding:9px; } td,th { padding:8px 6px; } #toast { left:12px; right:12px; bottom:12px; max-width:none; } .paginacion { justify-content:center; } }
''',encoding='utf-8')
(p/'app.js').write_text(js,encoding='utf-8')
(p/'index.html').write_text(html,encoding='utf-8')
