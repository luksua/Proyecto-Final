from pathlib import Path
import re,json,zipfile,xml.etree.ElementTree as ET,sys
sys.path.insert(0,str(Path('tmp/pdf_tools').resolve()))
import pymupdf as fitz

root=Path('output/proyecto_integrador').resolve()
md=(root/'Proyecto_integrador.md').read_text(encoding='utf-8')
req=md.split('### 3.1 Ocho requisitos funcionales')[1].split('## 4 Selección')[0]
rf=re.findall(r'^\| (RF-\d\d) \|',req,re.M)
rnf=re.findall(r'^\| (RNF-\d\d) \|',req,re.M)
assert rf==[f'RF-{i:02}' for i in range(1,9)],rf
assert rnf==[f'RNF-{i:02}' for i in range(1,6)],rnf
assert len(re.findall(r'^### 7\.[1-5] ',md,re.M))==5
json_blocks=re.findall(r'```json\n(.*?)\n```',md,re.S)
for b in json_blocks:json.loads(b)
xml_blocks=re.findall(r'```xml\n(.*?)\n```',md,re.S)
for b in xml_blocks:ET.fromstring(b)
for p in (root/'diagramas').glob('*.svg'):ET.parse(p)
assert len(list((root/'diagramas').glob('*.mmd')))==3
assert len(list((root/'diagramas').glob('*.svg')))==3
for p in re.findall(r'!\[.*?\]\((.*?)\)',md):assert (root/p).is_file(),p
for name in ['Yenifer Bogotá Tapiero','Luna Sofia Ospina Rodriguez','Sara Sofia Rodriguez Hernández','Germán Eduardo Flores Santos','Semestre VI - Grupo 04']:assert name in md,name
pdf=Path('output/pdf/Proyecto_integrador.pdf').resolve()
doc=fitz.open(pdf)
assert len(doc)==26
outside=[]
for i,p in enumerate(doc):
    assert len(p.get_text().strip())>50
    assert '\ufffd' not in p.get_text(),i
    for block in p.get_text('dict')['blocks']:
        for line in block.get('lines',[]):
            x0,y0,x1,y1=line['bbox']
            if x0<35 or x1>p.rect.width-35 or y0<14 or y1>p.rect.height-14:outside.append((i+1,[x0,y0,x1,y1]))
assert not outside,outside
ht=(root/'Proyecto_integrador.html').read_text(encoding='utf-8')
assert ht.count('<svg ')==3
assert '<script' not in ht
# Remove only the three obsolete preview images made in the first build.
for name in ['arquitectura.png','componentes.png','secuencia.png']:
    p=(root/'diagramas'/name).resolve()
    assert p.parent==root/'diagramas'
    if p.exists():p.unlink()
archive=Path('output/Proyecto_integrador_completo.zip')
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
    z.write(pdf,'Proyecto_integrador/Proyecto_integrador.pdf')
    for p in sorted(root.rglob('*')):
        if p.is_file() and p.suffix in {'.md','.html','.svg','.mmd'}:z.write(p,'Proyecto_integrador/'+p.relative_to(root).as_posix())
with zipfile.ZipFile(archive,'r') as z:
    assert z.testzip() is None
result={'pages':len(doc),'RF':len(rf),'RNF':len(rnf),'representative_endpoints':5,'valid_json_examples':len(json_blocks),'well_formed_xml_examples':len(xml_blocks),'diagram_sources':3,'archive_files':len(zipfile.ZipFile(archive).namelist()),'text_outside_page_limits':len(outside)}
Path('tmp/integrador_qa/validation.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result))
