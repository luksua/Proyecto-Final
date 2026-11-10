from pathlib import Path
import sys, re, html, math, textwrap, json
sys.path.insert(0, str(Path('tmp/pdf_tools').resolve()))
import pymupdf as fitz
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, LongTable, TableStyle, Image, Preformatted, KeepTogether
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

ROOT=Path('output/proyecto_integrador')
D=ROOT/'diagramas'; D.mkdir(parents=True,exist_ok=True)
QA=Path('tmp/integrador_qa'); QA.mkdir(parents=True,exist_ok=True)
PDF=Path('output/pdf/Proyecto_integrador.pdf'); PDF.parent.mkdir(parents=True,exist_ok=True)
E='#eaf2f9'; P='#fff2dc'; INK='#203447'; BLUE='#315b7d'; AMBER='#956221'

class SVG:
    def __init__(self,w,h,title):
        self.w,self.h=w,h
        self.a=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}" role="img" aria-label="{html.escape(title)}">',
        '<defs><marker id="a" markerWidth="9" markerHeight="9" refX="8" refY="4.5" orient="auto"><path d="M0,0 L9,4.5 L0,9" fill="none" stroke="#536878" stroke-width="1.3"/></marker></defs>',f'<rect width="{w}" height="{h}" fill="white"/>']
    def text(self,x,y,lines,size=20,bold=False,anchor='middle',fill=INK):
        if isinstance(lines,str): lines=lines.split('\n')
        self.a.append(f'<text x="{x}" y="{y}" text-anchor="{anchor}" font-family="Arial, sans-serif" font-size="{size}" font-weight="{"bold" if bold else "normal"}" fill="{fill}">')
        for i,t in enumerate(lines):self.a.append(f'<tspan x="{x}" dy="{0 if i==0 else size*1.3}">{html.escape(t)}</tspan>')
        self.a.append('</text>')
    def box(self,x,y,w,h,lines,proposed=False,size=20):
        self.a.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="10" fill="{P if proposed else E}" stroke="{AMBER if proposed else BLUE}" stroke-width="2" {"stroke-dasharray=\"7 5\"" if proposed else ""}/>')
        if isinstance(lines,str):lines=lines.split('\n')
        self.text(x+w/2,y+h/2-(len(lines)-1)*size*.65+size*.32,lines,size)
    def arrow(self,points,label=None,dashed=False,size=18):
        self.a.append(f'<polyline points="{" ".join(f"{x},{y}" for x,y in points)}" fill="none" stroke="#536878" stroke-width="2" {"stroke-dasharray=\"6 5\"" if dashed else ""}/>')
        ax,ay=points[-2]; bx,by=points[-1]; length=math.hypot(bx-ax,by-ay)
        ux,uy=(bx-ax)/length,(by-ay)/length
        hx,hy=bx-12*ux,by-12*uy
        self.a.append(f'<polygon points="{bx},{by} {hx-5*uy},{hy+5*ux} {hx+5*uy},{hy-5*ux}" fill="#536878"/>')
        if label:
            a,b=points[0],points[-1];self.text((a[0]+b[0])/2,(a[1]+b[1])/2-9,label,size)
    def save(self,name):
        self.a.append('</svg>'); p=D/f'{name}.svg';p.write_text(''.join(self.a),encoding='utf-8')
        doc=fitz.open(stream=p.read_bytes(),filetype='svg'); data=doc.convert_to_pdf(); doc.close()
        doc=fitz.open(stream=data,filetype='pdf'); pix=doc[0].get_pixmap(matrix=fitz.Matrix(1.8,1.8));pix.save(str(QA/f'{name}.png'));doc.close()

s=SVG(1000,1050,'Arquitectura lógica del monolito universitario')
s.text(500,35,'Arquitectura Cliente Servidor multicapas',27,True)
s.box(200,70,600,90,['E Cliente web HTML y JavaScript','P acceso institucional y pagos'],False,22)
s.a.append('<rect x="40" y="225" width="920" height="555" rx="16" fill="#fafbfc" stroke="#afbac3" stroke-width="2"/>')
s.text(500,255,'Servidor Laravel 12 · un monolito · capas lógicas',23,True)
s.box(120,280,760,95,['E Acceso y API','rutas v1 · middleware · controladores · resources'],False,21)
s.arrow([(500,160),(500,280)])
s.text(500,200,'REST y JSON · JWT · P TLS verificado',19)
s.box(120,430,760,105,['P Servicios de negocio','estudiantes · docentes · cursos · matrículas · pagos · identidad','Reutilizan reglas académicas actuales'],True,21)
s.arrow([(500,375),(500,430)])
s.box(120,590,350,90,['E Persistencia Eloquent','P modelos nuevos'],False,21)
s.box(530,590,350,90,['P Integraciones','BancoSoapAdapter · OidcAdapter'],True,19)
s.arrow([(350,535),(295,590)])
s.arrow([(650,535),(705,590)])
s.box(120,710,760,45,'P Auditoría y métricas · registros con correlación',True,19)
s.arrow([(880,480),(925,480),(925,732),(880,732)],dashed=True)
s.box(60,840,410,115,['E Datos académicos · InnoDB','MariaDB en el respaldo local','P MySQL objetivo y nuevas tablas'],False,21)
s.arrow([(295,680),(295,705),(85,705),(85,810),(265,810),(265,840)])
s.box(525,840,220,115,['P Banco SOAP','HTTPS · WSDL','WS-Security acordado'],True,18)
s.box(770,840,210,115,['P Identidad OIDC','Authorization Code','PKCE'],True,18)
s.arrow([(665,680),(665,805),(635,805),(635,840)])
s.arrow([(745,680),(905,680),(905,805),(875,805),(875,840)])
s.text(500,1005,['E Existente documentado     P Propuesto','Las capas no representan servidores físicos independientes'],19)
s.save('arquitectura')

s=SVG(1100,1210,'Componentes del monolito y adaptadores externos')
s.text(550,35,'Componentes y dependencias',28,True)
s.box(40,70,340,75,'E Cliente web',False,23)
s.box(690,70,370,75,['E JwtAuth y CheckRole','P políticas por propiedad'],False,21)
s.box(340,220,420,90,['E Controladores y resources','P controladores de integración'],False,22)
s.arrow([(210,145),(210,180),(450,180),(450,220)])
s.arrow([(875,145),(875,180),(650,180),(650,220)])
for x,lines in [(40,['P Servicios académicos','reglas actuales extraídas']),(400,['P PagoService','órdenes y conciliación']),(760,['P IdentidadService','vínculos y JWT local'])]:s.box(x,385,300,90,lines,True,21)
s.arrow([(440,310),(190,385)])
s.arrow([(550,310),(550,385)])
s.arrow([(660,310),(910,385)])
s.box(40,565,300,85,['E Eloquent','P modelos nuevos'],False,22)
s.box(400,565,300,85,['P Puerto BancoGateway','contrato interno'],True,21)
s.box(760,565,300,85,['P IdentidadGateway','contrato interno'],True,21)
s.arrow([(190,475),(190,565)])
s.arrow([(550,475),(550,565)])
s.arrow([(910,475),(910,565)])
s.arrow([(400,430),(365,430),(365,535),(260,535),(260,565)])
s.arrow([(760,430),(730,430),(730,510),(290,510),(290,565)])
s.box(40,740,300,130,['E Datos académicos','P pagos · identidad','auditoría · outbox'],False,22)
s.arrow([(190,650),(190,740)])
s.box(400,755,300,90,['P BancoSoapAdapter','implementa el puerto'],True,22)
s.box(760,755,300,90,['P OidcAdapter','implementa el puerto'],True,22)
s.arrow([(550,755),(550,650)],dashed=True)
s.arrow([(910,755),(910,650)],dashed=True)
s.box(400,945,300,75,['Banco externo SOAP'],False,22)
s.box(760,945,300,75,['Proveedor externo OIDC'],False,21)
s.arrow([(550,845),(550,945)])
s.arrow([(910,845),(910,945)])
s.box(40,945,300,75,['P Worker y scheduler'],True,22)
s.arrow([(190,945),(190,870)])
s.arrow([(40,980),(15,980),(15,350),(550,350),(550,385)])
s.box(40,1090,1020,65,'P Auditoría y observabilidad reciben eventos de los servicios',True,22)
s.arrow([(1060,430),(1080,430),(1080,1060),(910,1060),(910,1090)],dashed=True)
s.text(550,1185,'E Existente     P Propuesto     Flechas continuas: uso · discontinuas: contrato o eventos',18)
s.save('componentes')

s=SVG(1000,1580,'Secuencia de matrícula y pago con conciliación')
s.text(500,30,'Matrícula y pago con conciliación',26,True)
xs=[80,248,416,584,752,920]
labs=[['Cliente','E/P'],['API y servicios','E/P'],['InnoDB/outbox','E/P'],['Worker','P'],['Gateway y','adaptador P'],['Banco','externo']]
for x,l in zip(xs,labs):
    s.box(x-72,65,144,68,l,False,18)
    s.a.append(f'<line x1="{x}" y1="133" x2="{x}" y2="1510" stroke="#b2bcc4" stroke-dasharray="6 6"/>')
def msg(y,a,b,txt,dashed=False):
    s.arrow([(xs[a],y),(xs[b],y)],dashed=dashed)
    s.text((xs[a]+xs[b])/2,y-13,txt,18)
msg(190,0,1,['1 POST matriculas','JWT y propiedad'])
msg(260,1,2,['2 BEGIN · bloquear curso','validar · crear ACTIVA · cupo · COMMIT'])
msg(325,1,0,'3 HTTP 201 · matrícula académica',True)
s.box(105,355,790,40,'ACTIVA no implica pago confirmado',True,18)
msg(460,0,1,['4 POST pagos','Idempotency-Key'])
msg(530,1,2,['5 Bloquear obligación','orden EN_PROCESO + outbox · COMMIT'])
msg(595,1,0,'6 HTTP 202 · orden aceptada',True)
msg(675,3,2,'7 Recuperar orden y outbox')
msg(735,3,4,'8 RegistrarPago')
msg(795,4,5,['9 SOAP XML','HTTPS y seguridad acordada'])
msg(865,5,4,'10 Respuesta o timeout',True)
msg(925,4,3,'11 Resultado de dominio',True)
s.a.append('<rect x="330" y="955" width="330" height="225" fill="#fafafa" stroke="#956221" stroke-width="2"/>')
s.text(350,980,'Alternativas',19,True,anchor='start')
msg(1035,3,2,'CONFIRMADO y auditoría')
s.a.append('<line x1="330" y1="1050" x2="660" y2="1050" stroke="#d0c0a8"/>')
msg(1095,3,2,'RECHAZADO y auditoría')
s.a.append('<line x1="330" y1="1110" x2="660" y2="1110" stroke="#d0c0a8"/>')
msg(1155,3,2,'POR_CONCILIAR si es desconocido')
msg(1235,3,4,['12 ConsultarPago','misma referencia'])
msg(1295,4,5,'Consultar antes de reenviar')
msg(1355,5,3,'Resultado validado o pendiente',True)
msg(1415,3,2,'Actualizar una sola vez')
msg(1475,0,1,'13 GET pago · JWT y propiedad')
s.text(500,1535,['Consulta final: HTTP 200 con estado financiero actual','No se mantiene una transacción SQL abierta durante la comunicación bancaria'],18)
s.save('secuencia')

def inline(text, pdf=False):
    text=html.escape(text,quote=False)
    text=re.sub(r'`([^`]+)`', lambda m: f'<font name="Mono" size="8.8">{m[1]}</font>' if pdf else f'<code>{m[1]}</code>',text)
    text=re.sub(r'\*\*([^*]+)\*\*',r'<b>\1</b>',text)
    text=re.sub(r'(?<!\*)\*([^*]+)\*(?!\*)',r'<i>\1</i>',text)
    if pdf:
        text=text.replace('≤','&#8804;').replace('≥','&#8805;')
    return text

def parse(md):
    lines=md.splitlines(); i=0; blocks=[]
    while i<len(lines):
        line=lines[i]
        if not line.strip():i+=1;continue
        if line.startswith('```'):
            lang=line[3:];i+=1; code=[]
            while i<len(lines) and not lines[i].startswith('```'):code.append(lines[i]);i+=1
            blocks.append(('code',lang,'\n'.join(code)));i+=1;continue
        m=re.match(r'^(#{1,3}) (.+)$',line)
        if m:blocks.append(('heading',len(m[1]),m[2]));i+=1;continue
        m=re.match(r'!\[(.*?)\]\((.*?)\)',line)
        if m:blocks.append(('image',m[1],m[2]));i+=1;continue
        if line.startswith('|'):
            rows=[]
            while i<len(lines) and lines[i].startswith('|'):
                r=[c.strip() for c in lines[i].strip().strip('|').split('|')]
                if not all(re.match(r'^:?-+:?$',v) for v in r):rows.append(r)
                i+=1
            blocks.append(('table',rows));continue
        txt=[line];i+=1
        while i<len(lines) and lines[i].strip() and not re.match(r'^(#{1,3} |```|\||!\[)',lines[i]):txt.append(lines[i]);i+=1
        blocks.append(('para',' '.join(txt)))
    return blocks

md=(ROOT/'Proyecto_integrador.md').read_text(encoding='utf-8')
blocks=parse(md)
# Standalone HTML with inline SVG figures and local source companions.
css='''
@page {size:A4;margin:19mm 17mm;} *{box-sizing:border-box} body{font-family:Georgia,"Times New Roman",serif;color:#172635;line-height:1.58;font-size:16px;background:#e8edf2;margin:0} main{max-width:1100px;margin:24px auto;background:white;padding:70px 76px;box-shadow:0 3px 18px #0001} .cover{text-align:center;min-height:850px;display:flex;flex-direction:column;justify-content:center;page-break-after:always}.cover h1{font-size:28px;line-height:1.4;margin-bottom:35px;color:#111}.cover p{margin:10px 0} h2{font:700 25px/1.3 Arial,sans-serif;color:#203e55;margin-top:44px;break-after:avoid}h3{font:700 19px/1.4 Arial,sans-serif;color:#203e55;margin-top:27px;break-after:avoid}p{margin:11px 0}table{width:100%;border-collapse:collapse;font:13px/1.45 Arial,sans-serif;margin:20px 0}thead{display:table-header-group}th{background:#e9eff4;color:#172e41;text-align:left}td,th{padding:9px 8px;border:1px solid #c5ced6;vertical-align:top;overflow-wrap:anywhere}tr{break-inside:avoid}pre{font:12px/1.5 Consolas,monospace;white-space:pre-wrap;overflow-wrap:anywhere;padding:15px;background:#f4f6f8;border:1px solid #dce2e7;break-inside:auto}code{font:0.86em Consolas,monospace;overflow-wrap:anywhere}figure{margin:24px 0;text-align:center;break-inside:avoid}figure svg{max-width:100%;height:auto;max-height:1050px} .caption{font:12px/1.45 Arial,sans-serif;color:#455869} .toolbar{max-width:1100px;margin:20px auto 0;font:14px Arial,sans-serif;color:#273d4f;padding:0 16px}a{color:#225c86} @media print{body{background:white;font-size:10.5pt}main{padding:0;margin:0;max-width:none;box-shadow:none}.toolbar{display:none}.cover{min-height:245mm}h2{font-size:16pt}h3{font-size:12pt}table{font-size:8.2pt}td,th{padding:5px}pre{font-size:8pt}figure svg{max-height:230mm}a{color:inherit;text-decoration:none}}'''
parts=['<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Proyecto integrador de arquitectura de software</title><style>'+css+'</style></head><body><div class="toolbar">Documento académico · Versión de revisión · Las figuras están incorporadas y no requieren conexión a Internet.</div><main><section class="cover">']
in_cover=True
for b in blocks:
    kind=b[0]
    if kind=='heading':
        if b[1]==2 and in_cover:parts.append('</section>');in_cover=False
        parts.append(f'<h{b[1]}>{inline(b[2])}</h{b[1]}>')
    elif kind=='para':parts.append(f'<p class="{"caption" if b[1].startswith("Figura ") else "body"}">{inline(b[1])}</p>')
    elif kind=='code':parts.append('<pre><code>'+html.escape(b[2])+'</code></pre>')
    elif kind=='table':
        parts.append('<table><thead><tr>'+''.join('<th>'+inline(c)+'</th>' for c in b[1][0])+'</tr></thead><tbody>')
        for r in b[1][1:]:parts.append('<tr>'+''.join('<td>'+inline(c)+'</td>' for c in r)+'</tr>')
        parts.append('</tbody></table>')
    elif kind=='image':parts.append('<figure>'+(ROOT/b[2]).read_text(encoding='utf-8')+'</figure>')
parts.append('</main></body></html>')
(ROOT/'Proyecto_integrador.html').write_text('\n'.join(parts),encoding='utf-8')

fonts=Path('C:/Windows/Fonts')
for n,f in [('Body','times.ttf'),('Body-Bold','timesbd.ttf'),('Body-Italic','timesi.ttf'),('Body-BoldItalic','timesbi.ttf'),('Sans','arial.ttf'),('Sans-Bold','arialbd.ttf'),('Mono','consola.ttf')]:pdfmetrics.registerFont(TTFont(n,str(fonts/f)))
pdfmetrics.registerFontFamily('Body',normal='Body',bold='Body-Bold',italic='Body-Italic',boldItalic='Body-BoldItalic')
pdfmetrics.registerFontFamily('Sans',normal='Sans',bold='Sans-Bold',italic='Sans',boldItalic='Sans-Bold')
pdfmetrics.registerFontFamily('Mono',normal='Mono',bold='Mono',italic='Mono',boldItalic='Mono')
styles=getSampleStyleSheet()
styles.add(ParagraphStyle(name='BodyP',fontName='Body',fontSize=10.4,leading=14.5,spaceAfter=8,splitLongWords=True,allowWidows=0,allowOrphans=0))
styles.add(ParagraphStyle(name='H1P',fontName='Sans-Bold',fontSize=16,leading=20,spaceBefore=20,spaceAfter=10,keepWithNext=True))
styles.add(ParagraphStyle(name='H2P',fontName='Sans-Bold',fontSize=12,leading=16,spaceBefore=13,spaceAfter=7,keepWithNext=True))
styles.add(ParagraphStyle(name='CoverTitle',fontName='Body-Bold',fontSize=19,leading=26,alignment=TA_CENTER,spaceAfter=30))
styles.add(ParagraphStyle(name='CoverBody',fontName='Body',fontSize=12,leading=18,alignment=TA_CENTER,spaceAfter=12))
styles.add(ParagraphStyle(name='CellP',fontName='Sans',fontSize=8.1,leading=10.6,spaceAfter=0,splitLongWords=True))
styles.add(ParagraphStyle(name='CellHead',fontName='Sans-Bold',fontSize=8.2,leading=10.6,spaceAfter=0))
styles.add(ParagraphStyle(name='CaptionP',fontName='Sans',fontSize=8.4,leading=11.2,spaceBefore=4,spaceAfter=10))
styles.add(ParagraphStyle(name='CodeP',fontName='Mono',fontSize=8,leading=10.5,spaceAfter=10,backColor=colors.HexColor('#f3f5f7'),borderPadding=7))
styles.add(ParagraphStyle(name='LabelP',parent=styles['BodyP'],keepWithNext=True))
styles.add(ParagraphStyle(name='ReferenceP',parent=styles['BodyP'],leftIndent=18,firstLineIndent=-18,spaceAfter=10))
W,H=A4; WIDTH=W-96
story=[Spacer(1,120)]; cover=True; references=False

def widths(rows):
    n=len(rows[0]); head=rows[0]
    if n==2:return [WIDTH*.24,WIDTH*.76]
    if head[0]=='Elemento del integrador':return [WIDTH*x for x in [.19,.38,.12,.31]]
    if head[0]=='Código' and n==4:return [WIDTH*x for x in [.09,.29,.10,.52]]
    if head[0]=='Componente y estado':return [WIDTH*x for x in [.19,.13,.26,.16,.26]]
    if head[0]=='Decisión':return [WIDTH*x for x in [.12,.23,.31,.34]]
    if head[0]=='Capa':return [WIDTH*x for x in [.17,.49,.34]]
    if head[0]=='Número del Taller':return [WIDTH*x for x in [.20,.15,.65]]
    if head[0]=='Tiempo':return [WIDTH*x for x in [.14,.25,.61]]
    if n==3:return [WIDTH*x for x in [.23,.37,.40]]
    return [WIDTH/n]*n

for b in blocks:
    if b[0]=='heading':
        if b[1]==1:story.append(Paragraph(inline(b[2],True),styles['CoverTitle']))
        else:
            if cover:story.append(PageBreak());cover=False
            references=b[2]=='Referencias' if b[1]==2 else references
            story.append(Paragraph(inline(b[2],True),styles['H1P' if b[1]==2 else 'H2P']))
    elif b[0]=='para':
        st=styles['CoverBody'] if cover else styles['CaptionP'] if b[1].startswith('Figura ') else styles['ReferenceP'] if references else styles['LabelP'] if b[1].endswith(':') else styles['BodyP']
        story.append(Paragraph(inline(b[1],True),st))
    elif b[0]=='code':
        ls=[]
        for line in b[2].splitlines():
            ls.extend(textwrap.wrap(line,width=102,replace_whitespace=False,drop_whitespace=False,subsequent_indent='  ') or [''])
        story.append(KeepTogether([Preformatted('\n'.join(ls),styles['CodeP'])]))
    elif b[0]=='table':
        rows=b[1]
        rr=[[Paragraph(inline(c,True),styles['CellHead' if i==0 else 'CellP']) for c in r] for i,r in enumerate(rows)]
        t=LongTable(rr,colWidths=widths(rows),repeatRows=1,hAlign='LEFT',splitByRow=1,splitInRow=1)
        t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),colors.HexColor('#e9eff4')),('TEXTCOLOR',(0,0),(-1,0),colors.HexColor('#183348')),('GRID',(0,0),(-1,-1),.4,colors.HexColor('#bac6ce')),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),5),('RIGHTPADDING',(0,0),(-1,-1),5),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,colors.HexColor('#fafbfc')])]))
        story.extend([t,Spacer(1,11)])
    elif b[0]=='image':
        path=QA/(Path(b[2]).stem+'.png'); im=Image(str(path)); scale=min(WIDTH/im.imageWidth,620/im.imageHeight)
        im.drawWidth=im.imageWidth*scale;im.drawHeight=im.imageHeight*scale
        im.keepWithNext=True
        story.append(im)

class AcademicDoc(SimpleDocTemplate):
    def afterFlowable(self,flowable):
        if isinstance(flowable,Paragraph) and flowable.style.name in ['H1P','H2P']:
            txt=flowable.getPlainText(); key='h'+str(self.seq.nextf('h'))
            self.canv.bookmarkPage(key)
            self.canv.addOutlineEntry(txt,key,level=0 if flowable.style.name=='H1P' else 1,closed=False)

def page(canv,doc):
    canv.saveState();canv.setFont('Sans',7.7);canv.setFillColor(colors.HexColor('#647281'))
    if doc.page>1:
        canv.drawString(48,H-28,'Arquitectura de Software · Universidad del Tolima · Proyecto integrador')
        canv.drawRightString(W-48,27,str(doc.page))
    canv.restoreState()

grouped=[];i=0
while i<len(story):
    if isinstance(story[i],Paragraph) and story[i].style.name=='LabelP' and i+1<len(story) and isinstance(story[i+1],KeepTogether):
        grouped.append(KeepTogether([story[i],*story[i+1]._content]));i+=2
    else:grouped.append(story[i]);i+=1
story=grouped
doc=AcademicDoc(str(PDF),pagesize=A4,leftMargin=48,rightMargin=48,topMargin=46,bottomMargin=46,title='Proyecto integrador de arquitectura de software para la gestión universitaria de cursos matrículas y pagos',author='Yenifer Bogotá Tapiero; Luna Sofia Ospina Rodriguez; Sara Sofia Rodriguez Hernández',subject='Evolución del Taller 4 hacia una arquitectura Cliente Servidor multicapas')
doc.build(story,onFirstPage=page,onLaterPages=page)
out=fitz.open(str(PDF))
for i,p in enumerate(out):p.get_pixmap(matrix=fitz.Matrix(1.3,1.3)).save(str(QA/f'page-{i+1:02}.png'))
stats={'pdf_pages':len(out),'words':len(md.split()),'tables':sum(b[0]=='table' for b in blocks),'figures':sum(b[0]=='image' for b in blocks),'pdf':str(PDF.resolve())}
(QA/'stats.json').write_text(json.dumps(stats,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(stats,ensure_ascii=False))
