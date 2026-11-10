import sys, pathlib, json
sys.path.insert(0, str(pathlib.Path('tmp/pdf_tools').resolve()))
import pymupdf as fitz
base=pathlib.Path('tmp/source_review'); base.mkdir(parents=True,exist_ok=True)
doc=fitz.open(r'C:\Users\lunas\Downloads\Taller 4 API Cursos Matriculas.pdf')
texts=[]
for i,p in enumerate(doc):
    texts.append(f'\n\n===== PÁGINA {i+1} =====\n'+p.get_text(sort=True))
    p.get_pixmap(matrix=fitz.Matrix(1.35,1.35)).save(str(base/f'page-{i+1:02}.png'))
(base/'extracted.txt').write_text(''.join(texts),encoding='utf-8')
print(json.dumps({'pages':len(doc),'characters':sum(map(len,texts))}))
