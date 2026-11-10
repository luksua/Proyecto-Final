
const API = '/api/v1';
let configuracion = { simulatedEnabled: false };
let idempotencias = new Map();
let sesion = null;           // { token, rol, nombre, estudianteId, docenteId }
let tab = 'cursos';

const $ = (s) => document.querySelector(s);
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function icono(nombre) {
  const trazos = {
    estudiantes:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3a4 4 0 0 1 0 8"/><circle cx="9" cy="7" r="4"/>',
    cursos:'<path d="M4 3h6a3 3 0 0 1 3 3v15a4 4 0 0 0-4-3H4zm16 0h-4a3 3 0 0 0-3 3m7-3v15h-3a4 4 0 0 0-4 3"/>',
    matriculas:'<rect x="4" y="4" width="16" height="17" rx="2"/><path d="M8 2v4m8-4v4M4 10h16m-12 5 3 3 5-5"/>',
    docentes:'<path d="m2 8 10-5 10 5-10 5zm4 3v6c4 3 8 3 12 0v-6m4-3v8"/>',
    pagos:'<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/>',
    notificaciones:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    operacion:'<path d="M4 20h17M7 16V9m5 7V4m5 12v-5"/>',
    seguridad:'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zm-4 9 3 3 5-6"/>'
  };
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${trazos[nombre] || trazos.cursos}</svg>`;
}

function aviso(msg, ok = true) {
  const t = $('#toast');
  t.textContent = msg; t.style.background = ok ? 'var(--verde)' : 'var(--rojo)'; t.style.display = 'block';
  clearTimeout(aviso.t); aviso.t = setTimeout(() => t.style.display = 'none', 4000);
}

async function api(metodo, ruta, cuerpo, extraHeaders = {}) {
  const r = await fetch(API + ruta, {
    method: metodo,
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
               ...(sesion ? { 'Authorization': 'Bearer ' + sesion.token } : {}), ...extraHeaders },
    body: cuerpo ? JSON.stringify(cuerpo) : undefined,
  });
  if (r.status === 204) return null;
  const datos = await r.json().catch(() => ({}));
  if (!r.ok) {
    if (r.status === 401 && sesion) { cerrar(); aviso('Tu sesión venció, entra de nuevo', false); }
    const det = datos.errors ? ' ' + Object.values(datos.errors).flat().join(' ') : '';
    throw new Error((datos.message || 'Error ' + r.status) + det + (r.status >= 500 && datos.correlationId ? ' · Referencia: ' + datos.correlationId : ''));
  }
  return datos;
}
const lista = async (ruta) => (await api('GET', ruta)).data || [];

// ---------- Sesión ----------
function guardar() { sessionStorage.removeItem('sesion'); } // Access tokens remain in memory.
function cerrar() { sesion = null; sessionStorage.removeItem('sesion'); pintar(); }
$('#salir').onclick = cerrar;
$('.marca').onclick = e => { if (sesion) { e.preventDefault(); tab = sesion.rol === 'ADMIN' ? 'estudiantes' : 'cursos'; pintar(); } };

function vistaLogin() {
  $('#main').innerHTML = `
    <section class="bienvenida-login"><span class="eyebrow">SERVICIOS ACADÉMICOS</span><h1>La información de tus<br>estudiantes, organizada.</h1><p>Gestiona el registro de estudiantes y acompaña cada etapa de su proceso académico.</p>
      <div class="login-servicios"><div><span>01</span><div><strong>Registro estudiantil</strong><p>Información personal y académica organizada.</p></div></div><div><span>02</span><div><strong>Oferta y matrículas</strong><p>Cursos, docentes y asignaturas en un solo espacio.</p></div></div><div><span>03</span><div><strong>Seguimiento de pagos</strong><p>Consulta de órdenes e historial de transacciones.</p></div></div></div><div class="login-pie">Universidad del Tolima <span>Ingeniería de Sistemas · IDEAD</span></div></section>
    <div class="card login">
      <span class="eyebrow">BIENVENIDO AL PORTAL</span>
      <h2 style="margin-top:0">Iniciar sesión</h2>
      <p class="login-descripcion">Ingresa con tu cuenta para acceder a los servicios académicos.</p>
      <div class="campo"><label for="email">Correo institucional</label><input id="email" type="email" placeholder="nombre@ut.edu.co" autocomplete="username"></div>
      <div class="campo"><label for="pass">Contraseña</label><input id="pass" type="password" placeholder="Ingresa tu contraseña" autocomplete="current-password"></div>
      <button class="b" id="entrar" style="width:100%;padding:9px">Entrar</button>
      <div class="separador-login"><span>o continúa con</span></div><button class="b gris" id="institucional">Acceso institucional</button><div class="demo-label" style="display:${configuracion.simulatedEnabled ? 'block' : 'none'}">Cuentas de demostración</div><div class="rapido" style="display:${configuracion.simulatedEnabled ? 'flex' : 'none'}">
        <button class="b gris" data-u="admin@ut.edu.co">Admin</button>
        <button class="b gris" data-u="carlos.rojas@ut.edu.co">Docente</button>
        <button class="b gris" data-u="ana.martinez@ut.edu.co">Estudiante</button>
      </div>
    </div>`;
  document.querySelectorAll('[data-u]').forEach(b => b.onclick = () => { $('#email').value = b.dataset.u; $('#pass').value = 'password123'; });
  const entrar = async () => {
    try {
      const d = await api('POST', '/auth/login', { email: $('#email').value.trim(), password: $('#pass').value });
      sesion = { token: d.accessToken, rol: d.rol, nombre: d.nombre, estudianteId: d.estudianteId, docenteId: d.docenteId };
      guardar(); tab = sesion.rol === 'ADMIN' ? 'estudiantes' : 'cursos'; pintar();
    } catch (e) { aviso(e.message, false); }
  };
  $('#entrar').onclick = async () => { $('#entrar').disabled = true; try { await entrar(); } finally { if ($('#entrar')) $('#entrar').disabled = false; } };
  $('#institucional').onclick = iniciarIdentidad;
  $('#pass').onkeydown = (e) => e.key === 'Enter' && entrar();
}

// ---------- Estructura ----------
function pintar() {
  document.body.classList.toggle('autenticado', !!sesion);
  $('#salir').style.display = sesion ? '' : 'none';
  $('#quien').innerHTML = sesion ? `<span class="avatar-cuenta">${esc(sesion.nombre.charAt(0))}</span><span><strong>${esc(sesion.nombre)}</strong><small>${({ADMIN:'Administrador',DOCENTE:'Docente',ESTUDIANTE:'Estudiante'})[sesion.rol] || ''}</small></span>` : '';
  if (!sesion) return vistaLogin();
  const tabs = [];
  if (sesion.rol !== 'ESTUDIANTE') tabs.push(['estudiantes', 'Estudiantes']);
  tabs.push(['cursos', 'Cursos'], ['matriculas', 'Matrículas']);
  if (sesion.rol === 'ADMIN') tabs.push(['docentes', 'Docentes']);
  if (sesion.rol !== 'DOCENTE') tabs.push(['pagos', 'Pagos']);
  tabs.push(['notificaciones', 'Avisos']);
  if (sesion.rol === 'ADMIN') tabs.push(['operacion', 'Operación']);
  const info = {
    estudiantes:['Registro de estudiantes','Administra la información de los estudiantes y sus datos académicos.'],
    cursos:['Oferta académica','Consulta y organiza los cursos disponibles para cada periodo.'],
    matriculas:['Gestión de matrículas','Acompaña el proceso de inscripción y consulta las asignaturas matriculadas.'],
    docentes:['Directorio de docentes','Gestiona los profesores que acompañan la formación académica.'],
    pagos:['Pagos y transacciones','Consulta las órdenes de pago y su historial de procesamiento.'],
    notificaciones:['Centro de avisos','Encuentra las novedades de tus procesos académicos.'],
    operacion:['Operación del sistema','Consulta los indicadores de la aplicación y la trazabilidad de sus cambios.']
  }[tab];
  $('#main').innerHTML = `<aside class="barra-lateral"><span class="nav-titulo">GESTIÓN ACADÉMICA</span><nav aria-label="Navegación principal">${tabs.map(([k, n]) => `<button data-t="${k}" class="${k === tab ? 'on' : ''}" ${k === tab ? 'aria-current="page"' : ''}>${icono(k)}<span>${n}</span>${k === tab ? '<i></i>' : ''}</button>`).join('')}</nav><div class="lateral-pie"><span class="lateral-emblema">RA</span><strong>Información conectada</strong><p>Registro, formación y seguimiento académico.</p><small>Portal universitario</small></div></aside>
    <div class="contenido"><div class="ruta-pagina">Portal académico <span>/</span> ${esc(tabs.find(t => t[0] === tab)?.[1] || '')}</div><section class="encabezado-pagina"><div><span class="eyebrow">GESTIÓN UNIVERSITARIA</span><h1>${info[0]}</h1><p>${info[1]}</p></div><span class="etiqueta-acceso">${icono('seguridad')} Acceso por rol</span></section><div id="vista"></div><footer>Registro académico <span>Universidad del Tolima · IDEAD</span></footer></div>`;
  document.querySelectorAll('nav button').forEach(b => b.onclick = () => { tab = b.dataset.t; pintar(); });
  ({ cursos: vistaCursos, matriculas: vistaMatriculas, estudiantes: vistaEstudiantes, docentes: vistaDocentes, pagos: vistaPagos, notificaciones: vistaNotificaciones, operacion: vistaOperacion })[tab]();
}

function modal(titulo, campos, textoOk, alGuardar) {
  const registro = titulo === 'Registrar estudiante';
  $('#box').classList.toggle('form-registro', registro);
  $('#box').innerHTML = `<div class="modal-encabezado"><span class="eyebrow">${registro ? 'REGISTRO ESTUDIANTIL' : 'GESTIÓN ACADÉMICA'}</span><h3>${esc(titulo)}</h3><p class="nota">${registro ? 'Completa la información para crear la ficha del estudiante.' : 'Completa los campos y confirma para continuar.'}</p></div><div class="campos-formulario">
    ${campos.map(c => `<div class="campo"><label for="f_${c.id}">${esc(c.label)}</label>${c.opciones
      ? `<select id="f_${c.id}">${c.opciones.map(o => `<option value="${esc(o[0])}" ${String(o[0]) === String(c.valor ?? '') ? 'selected' : ''}>${esc(o[1])}</option>`).join('')}</select>`
      : `<input id="f_${c.id}" type="${c.tipo || 'text'}" value="${esc(c.valor ?? '')}">`}</div>`).join('')}
    </div><div class="modal-acciones" style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
      <button class="b gris" id="m_no">Cancelar</button><button class="b" id="m_ok">${esc(textoOk)}</button></div>`;
  $('#modal').style.display = 'flex';
  $('#box input, #box select')?.focus();
  $('#m_no').onclick = () => $('#modal').style.display = 'none';
  $('#m_ok').onclick = async () => {
    const v = {}; campos.forEach(c => v[c.id] = $('#f_' + c.id).value);
    const boton = $('#m_ok'); boton.disabled = true;
    try { await alGuardar(v); $('#modal').style.display = 'none'; } catch (e) { aviso(e.message, false); } finally { boton.disabled = false; }
  };
}

// ---------- Cursos ----------
async function vistaCursos() {
  const esAdmin = sesion.rol === 'ADMIN';
  $('#vista').innerHTML = `<div class="card">
    <div class="der"><h2 style="margin:0">Cursos</h2>${esAdmin ? '<button class="b" id="nuevo">+ Nuevo curso</button>' : ''}</div>
    <div class="filtros">
      <div><label>Periodo</label><input id="fp" placeholder="2026-2"></div>
      <div><label>Estado</label><select id="fe"><option value="">Todos</option><option>ABIERTO</option><option>CERRADO</option></select></div>
      <div><label>Ordenar por</label><select id="fs"><option value="nombre,asc">Nombre (A-Z)</option><option value="nombre,desc">Nombre (Z-A)</option><option value="creditos,desc">Más créditos</option><option value="cupoDisponible,desc">Más cupos</option></select></div>
      <button class="b" id="buscar">Filtrar</button>
    </div><div class="scroll" id="tabla">Cargando…</div></div>`;
  const cargar = async (pagina = 1) => {
    const q = new URLSearchParams({ limit: 100, sort: $('#fs').value });
    if ($('#fp').value) q.set('periodo', $('#fp').value.trim());
    if ($('#fe').value) q.set('estado', $('#fe').value);
    try {
      const datos = await paginaApi('/cursos?' + q, pagina); const cursos = datos.data;
      $('#tabla').innerHTML = !cursos.length ? '<div class="vacio">No hay cursos con esos filtros.</div>' : `<table>
        <tr><th>Código</th><th>Nombre</th><th>Créd.</th><th>Docente</th><th>Periodo</th><th>Cupos</th><th>Estado</th><th></th></tr>
        ${cursos.map(c => `<tr><td>${esc(c.codigo)}</td><td>${esc(c.nombre)}</td><td>${c.creditos}</td>
          <td>${c.docente ? esc(c.docente.nombres + ' ' + c.docente.apellidos) : ''}</td><td>${esc(c.periodo)}</td>
          <td>${c.cupoDisponible} / ${c.cupoMaximo}</td><td><span class="tag ${esc(c.estado)}">${esc(c.estado)}</span></td>
          <td style="white-space:nowrap">
            ${sesion.rol !== 'DOCENTE' ? `<button class="b" data-mat="${c.id}" data-per="${esc(c.periodo)}">Matricular</button>` : ''}
            ${esAdmin ? `<button class="b gris" data-ed="${c.id}">Editar</button> <button class="b rojo" data-del="${c.id}">Eliminar</button>` : ''}
          </td></tr>`).join('')}</table>`;
      document.querySelectorAll('[data-mat]').forEach(b => b.onclick = () => matricular(b.dataset.mat, b.dataset.per));
      document.querySelectorAll('[data-ed]').forEach(b => b.onclick = () => formCurso(cursos.find(c => c.id == b.dataset.ed), cargar));
      paginador(datos, cargar);
      document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
        if (!confirm('¿Eliminar este curso?')) return;
        try { await api('DELETE', '/cursos/' + b.dataset.del); aviso('Curso eliminado'); cargar(); } catch (e) { aviso(e.message, false); }
      });
    } catch (e) { $('#tabla').textContent = e.message; }
  };
  $('#buscar').onclick = () => cargar();
  if (esAdmin) $('#nuevo').onclick = () => formCurso(null, cargar);
  cargar();
}

async function formCurso(c, recargar) {
  const docentes = await lista('/docentes?limit=100');
  modal(c ? 'Editar curso' : 'Nuevo curso', [
    { id: 'codigo', label: 'Código', valor: c?.codigo },
    { id: 'nombre', label: 'Nombre', valor: c?.nombre },
    { id: 'creditos', label: 'Créditos (1-10)', tipo: 'number', valor: c?.creditos ?? 3 },
    { id: 'cupoMaximo', label: 'Cupo máximo', tipo: 'number', valor: c?.cupoMaximo ?? 30 },
    { id: 'periodo', label: 'Periodo (ej. 2026-2)', valor: c?.periodo ?? '2026-2' },
    { id: 'docenteId', label: 'Docente', valor: c?.docente?.id, opciones: docentes.map(d => [d.id, d.nombres + ' ' + d.apellidos]) },
    { id: 'estado', label: 'Estado', valor: c?.estado ?? 'ABIERTO', opciones: [['ABIERTO', 'ABIERTO'], ['CERRADO', 'CERRADO']] },
  ], 'Guardar', async (v) => {
    const cuerpo = { codigo: v.codigo, nombre: v.nombre, creditos: +v.creditos, cupoMaximo: +v.cupoMaximo,
                     periodo: v.periodo, docenteId: +v.docenteId, estado: v.estado };
    if (c) await api('PUT', '/cursos/' + c.id, cuerpo); else await api('POST', '/cursos', cuerpo);
    aviso(c ? 'Curso actualizado' : 'Curso creado'); recargar();
  });
}

async function matricular(cursoId, periodo) {
  const hacer = async (estudianteId) => {
    await api('POST', '/matriculas', { estudianteId: +estudianteId, cursoId: +cursoId, periodo });
    aviso('Matrícula realizada'); pintar();
  };
  if (sesion.rol === 'ESTUDIANTE') {
    try { await hacer(sesion.estudianteId); } catch (e) { aviso(e.message, false); }
  } else {
    const ests = await lista('/estudiantes?limit=100');
    modal('Matricular estudiante', [{ id: 'est', label: 'Estudiante', opciones: ests.map(e => [e.id, `${e.nombres} ${e.apellidos} (${e.documento})`]) }],
      'Matricular', (v) => hacer(v.est));
  }
}

// ---------- Matrículas ----------
async function vistaMatriculas(pagina = 1) {
  $('#vista').innerHTML = `<div class="card"><h2 style="margin-top:0">${sesion.rol === 'ESTUDIANTE' ? 'Mis matrículas' : sesion.rol === 'DOCENTE' ? 'Matrículas de mis cursos' : 'Matrículas'}</h2><div class="scroll" id="tabla">Cargando…</div></div>`;
  try {
    const datos = await paginaApi('/matriculas', pagina); const ms = datos.data;
    $('#tabla').innerHTML = !ms.length ? '<div class="vacio">No hay matrículas.</div>' : `<table>
      <tr><th>#</th><th>Estudiante</th><th>Curso</th><th>Periodo</th><th>Estado académico</th><th>Fecha</th><th>Pago</th><th></th></tr>
      ${ms.map(m => `<tr><td>${m.id}</td><td>${m.estudiante ? esc(m.estudiante.nombres + ' ' + m.estudiante.apellidos) : ''}</td>
        <td>${m.curso ? esc(m.curso.codigo + ' · ' + m.curso.nombre) : ''}</td><td>${esc(m.periodo)}</td>
        <td><span class="tag ${esc(m.estado)}">${esc(m.estado)}</span></td><td>${m.fechaMatricula ? new Date(m.fechaMatricula).toLocaleString('es-CO') : ''}</td>
        <td>${m.pago ? `<span class="tag ${esc(m.pago.estado)}">${esc(m.pago.estado)}</span>` : 'Sin orden'}</td><td>${m.estado === 'ACTIVA' && sesion.rol !== 'DOCENTE' ? `<button class="b" data-orden="${m.id}">${m.pago ? 'Ver pago' : 'Generar orden'}</button> ` : ''}${m.estado === 'ACTIVA' && sesion.rol !== 'DOCENTE' ? `<button class="b rojo" data-can="${m.id}">Cancelar</button>` : ''}</td></tr>`).join('')}</table>`;
    paginador(datos, vistaMatriculas);
    document.querySelectorAll('[data-orden]').forEach(b => b.onclick = async () => {
      b.disabled = true; try { await api('POST', '/pagos', { matriculaId: +b.dataset.orden }); tab = 'pagos'; pintar(); } catch (e) { aviso(e.message, false); b.disabled = false; }
    });
    document.querySelectorAll('[data-can]').forEach(b => b.onclick = async () => {
      if (!confirm('¿Cancelar esta matrícula?')) return;
      try { await api('DELETE', '/matriculas/' + b.dataset.can); aviso('Matrícula cancelada'); vistaMatriculas(); } catch (e) { aviso(e.message, false); }
    });
  } catch (e) { $('#tabla').textContent = e.message; }
}

// ---------- Estudiantes ----------
async function vistaEstudiantes() {
  const esAdmin = sesion.rol === 'ADMIN';
  $('#vista').innerHTML = `<div class="registro-resumen"><div class="resumen-principal"><span class="resumen-icono">${icono('estudiantes')}</span><div><span>Resultados del directorio</span><strong id="total-estudiantes">—</strong><small>Estudiantes en la consulta actual</small></div></div><div class="registro-ayuda"><span class="eyebrow">UN REGISTRO, TODA SU INFORMACIÓN</span><h3>El proceso académico comienza aquí.</h3><p>Registra los datos personales, el correo y el programa de cada estudiante.</p></div></div>
    <div class="card directorio"><div class="der"><div><h2 style="margin:0">Directorio estudiantil</h2><p class="nota">Consulta, busca y organiza las fichas de tus estudiantes.</p></div>${esAdmin ? '<button class="b" id="nuevo">+ Registrar estudiante</button>' : ''}</div>
    <div class="filtros filtros-directorio"><div><label for="fq">Buscar estudiante</label><input id="fq" placeholder="Buscar por nombre, apellido o documento"></div><button class="b gris" id="buscar">Buscar</button></div>
    <div class="scroll" id="tabla">Cargando…</div></div>`;
  const cargar = async (pagina = 1) => {
    try {
      const datos = await paginaApi('/estudiantes?q=' + encodeURIComponent($('#fq').value), pagina); const es = datos.data;
      $('#total-estudiantes').textContent = datos.meta?.total ?? es.length;
      $('#tabla').innerHTML = !es.length ? '<div class="vacio"><strong>No encontramos estudiantes en esta consulta.</strong><p>Prueba con otro nombre o documento, o registra un estudiante nuevo.</p></div>' : `<table class="tabla-estudiantes">
        <tr><th>Estudiante</th><th>Documento</th><th>Programa académico</th><th>Estado</th>${esAdmin ? '<th class="acciones-columna">Acciones</th>' : ''}</tr>
        ${es.map(e => `<tr><td><div class="persona"><span class="avatar-estudiante">${esc(e.nombres.charAt(0) + e.apellidos.charAt(0))}</span><div><strong>${esc(e.nombres + ' ' + e.apellidos)}</strong><small>${esc(e.correo)}</small></div></div></td><td class="documento">${esc(e.documento)}</td><td><span class="programa">${esc(e.programa)}</span></td>
        <td><span class="tag ${esc(e.estado)}">${esc(e.estado)}</span></td>
        ${esAdmin ? `<td><button class="b rojo" data-del="${e.id}">Eliminar</button></td>` : ''}</tr>`).join('')}</table>`;
      paginador(datos, cargar);
      document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
        if (!confirm('¿Eliminar este estudiante?')) return;
        try { await api('DELETE', '/estudiantes/' + b.dataset.del); aviso('Estudiante eliminado'); cargar(); } catch (e) { aviso(e.message, false); }
      });
    } catch (e) { $('#tabla').textContent = e.message; }
  };
  $('#buscar').onclick = () => cargar();
  $('#fq').onkeydown = e => e.key === 'Enter' && cargar();
  if (esAdmin) $('#nuevo').onclick = () => modal('Registrar estudiante', [
    { id: 'documento', label: 'Documento' }, { id: 'nombres', label: 'Nombres' }, { id: 'apellidos', label: 'Apellidos' },
    { id: 'correo', label: 'Correo', tipo: 'email' }, { id: 'programa', label: 'Programa', valor: 'Ingeniería de Sistemas' },
  ], 'Registrar estudiante', async (v) => { await api('POST', '/estudiantes', v); aviso('Estudiante registrado correctamente'); cargar(); });
  cargar();
}

// ---------- Docentes (solo ADMIN) ----------
async function vistaDocentes() {
  $('#vista').innerHTML = `<div class="card"><div class="der"><h2 style="margin:0">Docentes</h2><button class="b" id="nuevo">+ Nuevo docente</button></div>
    <div class="scroll" id="tabla">Cargando…</div></div>`;
  const cargar = async (pagina = 1) => {
    try {
      const datos = await paginaApi('/docentes', pagina); const ds = datos.data;
      $('#tabla').innerHTML = !ds.length ? '<div class="vacio">No hay docentes.</div>' : `<table>
        <tr><th>#</th><th>Nombre</th><th>Correo</th><th>Departamento</th><th></th></tr>
        ${ds.map(d => `<tr><td>${d.id}</td><td>${esc(d.nombres + ' ' + d.apellidos)}</td><td>${esc(d.correo)}</td><td>${esc(d.departamento)}</td>
        <td><button class="b rojo" data-del="${d.id}">Eliminar</button></td></tr>`).join('')}</table>`;
      paginador(datos, cargar);
      document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
        if (!confirm('¿Eliminar este docente?')) return;
        try { await api('DELETE', '/docentes/' + b.dataset.del); aviso('Docente eliminado'); cargar(); } catch (e) { aviso(e.message, false); }
      });
    } catch (e) { $('#tabla').textContent = e.message; }
  };
  $('#nuevo').onclick = () => modal('Nuevo docente', [
    { id: 'nombres', label: 'Nombres' }, { id: 'apellidos', label: 'Apellidos' },
    { id: 'correo', label: 'Correo', tipo: 'email' }, { id: 'departamento', label: 'Departamento', valor: 'Ingeniería de Sistemas' },
  ], 'Crear', async (v) => { await api('POST', '/docentes', v); aviso('Docente creado'); cargar(); });
  cargar();
}

async function paginaApi(ruta, pagina = 1) {
  const url = new URL(API + ruta, location.origin);
  url.searchParams.set('page', pagina);
  url.searchParams.set('limit', 15);
  return api('GET', url.pathname.slice(API.length) + url.search);
}

function paginador(datos, cargar) {
  $('#paginacion')?.remove();
  const meta = datos.meta || datos;
  if (!meta.last_page || meta.last_page <= 1) return;
  const el = document.createElement('div'); el.id = 'paginacion'; el.className = 'paginacion';
  el.innerHTML = `<button class="b gris" id="anterior" ${meta.current_page <= 1 ? 'disabled' : ''}>Anterior</button>
    <span>Página ${esc(meta.current_page)} de ${esc(meta.last_page)}</span>
    <button class="b gris" id="siguiente" ${meta.current_page >= meta.last_page ? 'disabled' : ''}>Siguiente</button>`;
  $('#tabla').parentElement.append(el);
  $('#anterior').onclick = () => cargar(meta.current_page - 1);
  $('#siguiente').onclick = () => cargar(meta.current_page + 1);
}

const dinero = (valor, moneda) => new Intl.NumberFormat('es-CO', {style: 'currency', currency: moneda || 'COP'}).format(Number(valor));

async function vistaPagos(pagina = 1, estado = '') {
  $('#vista').innerHTML = `<div class="card"><h2>Pagos de matrículas</h2>
    <p class="nota">${configuracion.simulatedEnabled ? 'Demostración: no se cobran fondos reales. La tarifa es de ejemplo y se calcula en el servidor.' : 'Consulta de órdenes y estado financiero.'} Una matrícula ACTIVA no implica pago aprobado.</p>
    <div class="filtros"><div><label for="pestado">Estado</label><select id="pestado"><option value="">Todos</option>${['PENDIENTE','APROBADO','RECHAZADO','REEMBOLSADO'].map(x => `<option ${x === estado ? 'selected' : ''}>${x}</option>`).join('')}</select></div><button class="b" id="pfiltrar">Filtrar</button></div>
    <p class="nota">Para generar una orden, abre Matrículas y selecciona Generar orden.</p><div id="tabla" class="scroll">Cargando…</div></div>`;
  $('#pfiltrar').onclick = () => vistaPagos(1, $('#pestado').value);
  try {
    const datos = await paginaApi('/pagos' + (estado ? '?estado=' + encodeURIComponent(estado) : ''), pagina);
    $('#tabla').innerHTML = !datos.data.length ? '<p class="vacio">No hay pagos en esta consulta.</p>' : `<table><tr><th>Orden</th><th>Matrícula y curso</th><th>Valor</th><th>Estado</th><th>Proveedor</th><th>Acciones</th></tr>
      ${datos.data.map(p => `<tr><td>#${p.id}</td><td>#${p.matriculaId} · ${esc(p.matricula?.curso?.nombre || '')}</td><td>${esc(dinero(p.valor,p.moneda))}</td>
      <td><span class="tag ${esc(p.estado)}">${esc(p.estado)}</span></td><td>${esc(p.proveedor)}${p.simulado ? ' (simulación)' : ''}</td>
      <td><button class="b gris" data-historia="${p.id}">Historial</button>
      ${configuracion.simulatedEnabled && p.matricula?.estado === 'ACTIVA' && ['PENDIENTE','RECHAZADO'].includes(p.estado) ? `<button class="b" data-pagar="${p.id}">Simular pago</button>` : ''}
      ${configuracion.simulatedEnabled && sesion.rol === 'ADMIN' && p.estado === 'APROBADO' ? `<button class="b rojo" data-reembolso="${p.id}">Reembolsar</button>` : ''}</td></tr>`).join('')}</table>`;
    paginador(datos, p => vistaPagos(p, estado));
    document.querySelectorAll('[data-historia]').forEach(b => b.onclick = () => historialPago(b.dataset.historia).catch(e => aviso(e.message, false)));
    document.querySelectorAll('[data-pagar]').forEach(b => b.onclick = () => modal('Pago simulado · sin cobro real', [
      { id:'resultado', label:'Resultado de demostración', opciones:[['APROBADO','Aprobar'],['RECHAZADO','Rechazar'],['ERROR','Simular indisponibilidad']] }
    ], 'Simular', async v => {
      await accionPago(b.dataset.pagar, 'procesar', v.resultado); aviso('Resultado de simulación registrado'); vistaPagos(pagina,estado);
    }));
    document.querySelectorAll('[data-reembolso]').forEach(b => b.onclick = async () => {
      if (!confirm('¿Registrar un reembolso simulado de esta orden?')) return;
      b.disabled = true;
      try { await accionPago(b.dataset.reembolso, 'reembolsar'); aviso('Reembolso simulado registrado'); vistaPagos(pagina,estado); }
      catch(e) { aviso(e.message,false); b.disabled=false; }
    });
  } catch(e) { if ($('#tabla')) $('#tabla').textContent=e.message; }
}

async function accionPago(id, accion, resultado) {
  const intento = `${id}:${accion}:${resultado || ''}`;
  if (!idempotencias.has(intento)) idempotencias.set(intento, crypto.randomUUID());
  const r = await api('POST', `/pagos/${id}/${accion}`, resultado ? {resultado} : {}, { 'Idempotency-Key':idempotencias.get(intento) });
  idempotencias.delete(intento);
  return r;
}

async function historialPago(id) {
  const p = await api('GET', '/pagos/' + id);
  $('#box').classList.remove('form-registro');
  $('#box').innerHTML = `<h3>Historial de orden #${esc(p.id)}</h3><p>${esc(dinero(p.valor,p.moneda))} · ${esc(p.estado)}</p>
    <p class="nota">${p.simulado ? 'Integración simulada; no representa un cobro bancario real.' : ''}</p>
    ${(p.transacciones || []).map(t => `<p><b>${esc(t.accion)} · ${esc(t.estado)}</b><br>${esc(new Date(t.fecha).toLocaleString('es-CO'))}<br><small>${esc(t.referencia)}</small></p>`).join('') || '<p>La orden aún no tiene transacciones.</p>'}
    <button class="b" id="cerrar-historia">Cerrar</button>`;
  $('#modal').style.display = 'flex';
  $('#cerrar-historia').onclick = () => $('#modal').style.display = 'none';
}

async function vistaNotificaciones(pagina = 1) {
  $('#vista').innerHTML = '<div class="card"><h2>Avisos</h2><p class="nota">Los avisos se entregan mediante la cola de la aplicación.</p><div id="tabla">Cargando…</div></div>';
  try {
    const datos = await paginaApi('/notificaciones', pagina);
    $('#tabla').innerHTML = datos.data.map(n => `<p><b>${esc(n.tipo)}</b> · recurso #${esc(n.resource_id)}<br><small>${esc(n.created_at)}</small></p>`).join('') || '<p class="vacio">Aún no hay avisos entregados.</p>';
    paginador(datos,vistaNotificaciones);
  } catch(e) { if ($('#tabla')) $('#tabla').textContent=e.message; }
}

async function vistaOperacion() {
  $('#vista').innerHTML='<div class="card"><h2>Operación y auditoría</h2><div id="tabla" class="scroll">Cargando…</div></div>';
  try {
    const [m,a] = await Promise.all([api('GET','/metricas'), api('GET','/auditoria?limit=20')]);
    $('#tabla').innerHTML=`<p>Solicitudes: <b>${esc(m.solicitudes)}</b> · Errores 5xx: <b>${esc(m.errores5xx)}</b> · Promedio: <b>${esc(m.duracionPromedioMs)} ms</b></p>
      <p class="nota">${esc(m.nota)} Se muestran los últimos 20 eventos administrativos.</p>
      <table><tr><th>Fecha</th><th>Actor</th><th>Acción</th><th>Recurso</th><th>Correlación</th></tr>${a.data.map(e => `<tr><td>${esc(e.created_at)}</td><td>${esc(e.actor_id ?? 'Sistema')}</td><td>${esc(e.action)}</td><td>${esc(e.resource)} #${esc(e.resource_id)}</td><td><small>${esc(e.correlation_id || '')}</small></td></tr>`).join('')}</table>`;
  } catch(e) { if ($('#tabla')) $('#tabla').textContent=e.message; }
}

function aceptarSesion(d) {
  sesion={token:d.accessToken,rol:d.rol,nombre:d.nombre,estudianteId:d.estudianteId,docenteId:d.docenteId};
  guardar(); tab=sesion.rol === 'ADMIN' ? 'estudiantes' : 'cursos'; pintar();
}

async function iniciarIdentidad() {
  try {
    const b64 = bytes => btoa(String.fromCharCode(...bytes)).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
    const verifier = b64(crypto.getRandomValues(new Uint8Array(32)));
    const challenge = b64(new Uint8Array(await crypto.subtle.digest('SHA-256',new TextEncoder().encode(verifier))));
    const d = await api('POST','/auth/oidc/inicio',{codeChallenge:challenge});
    const flow={state:d.state,verifier,created:Date.now()};
    sessionStorage.setItem('oidc',JSON.stringify(flow));
    if (d.simulado) {
      modal('Identidad simulada · desarrollo', [
        {id:'email',label:'Cuenta local usada para simular al proveedor',tipo:'email',valor:$('#email')?.value},
        {id:'password',label:'Contraseña de la cuenta local',tipo:'password'}
      ], 'Autenticar simulación', async v => {
        const result=await api('POST','/auth/oidc/simulada',{...v,state:flow.state});
        await completarIdentidad(result.code,flow);
      });
    } else if (d.authorizationUrl && new URL(d.authorizationUrl).protocol === 'https:') location.assign(d.authorizationUrl);
    else throw new Error('El proveedor institucional no está configurado.');
  } catch(e) { aviso(e.message,false); }
}

async function completarIdentidad(code,flow) {
  if (!flow || Date.now()-flow.created>300000) throw new Error('El inicio institucional venció. Vuelve a intentarlo.');
  try { aceptarSesion(await api('POST','/auth/oidc/intercambio',{code,state:flow.state,codeVerifier:flow.verifier})); }
  finally { sessionStorage.removeItem('oidc'); }
}

async function inicio() {
  sessionStorage.removeItem('sesion');
  try { configuracion=await api('GET','/configuracion'); } catch(e) { aviso('No se pudo consultar la configuración del servidor.',false); }
  pintar();
  const url=new URL(location.href);
  if (url.searchParams.has('code') || url.searchParams.has('error')) {
    const code=url.searchParams.get('code'),state=url.searchParams.get('state'),error=url.searchParams.get('error');
    url.searchParams.delete('code'); url.searchParams.delete('state'); url.searchParams.delete('error');url.searchParams.delete('error_description');
    history.replaceState(null,'',url.pathname+url.search+url.hash);
    try {
      const flow=JSON.parse(sessionStorage.getItem('oidc'));
      if (error || !flow || flow.state!==state) throw new Error('El proveedor no completó el acceso o el estado no coincide.');
      await completarIdentidad(code,flow);
    } catch(e) { sessionStorage.removeItem('oidc');aviso(e.message,false); }
  }
}
inicio();

