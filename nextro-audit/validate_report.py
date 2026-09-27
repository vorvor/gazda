from pathlib import Path
import fitz, json, re
from PIL import Image, ImageDraw
R=Path('/workspace/nextro-audit'); P=Path('/workspace/nextro.hu_full_website_audit.pdf'); out=R/'pdf-validation';out.mkdir(exist_ok=True)
d=fitz.open(P); rows=[]; bad=[]; full=[]
for i,p in enumerate(d):
 t=p.get_text();full.append(t); blocks=p.get_text('dict')['blocks']
 for block in blocks:
  if block.get('type')!=0:continue
  for line in block['lines']:
   for span in line['spans']:
    x0,y0,x1,y1=span['bbox']
    if x0<0 or y0<0 or x1>p.rect.width+.5 or y1>p.rect.height+.5:bad.append({'page':i+1,'text':span['text'],'bbox':span['bbox']})
 rows.append({'page':i+1,'words':len(t.split()),'start':' | '.join(t.splitlines()[2:6]),'chars':len(t)})
 pix=p.get_pixmap(matrix=fitz.Matrix(.35,.35),alpha=False);pix.save(str(out/f'thumb-{i+1:02}.png'))
 for n in [1,2,3,7,16,21,30,35,40]:
  if i+1==n:p.get_pixmap(matrix=fitz.Matrix(1.4,1.4),alpha=False).save(str(out/f'page-{n:02}.png'))
(R/'report-extracted.txt').write_text('\n\n'.join(f'PAGE {i+1}\n{t}' for i,t in enumerate(full)))
cols=5;tw=230;th=335;canvas=Image.new('RGB',(cols*tw,((len(d)+cols-1)//cols)*th),'#dce3e8');draw=ImageDraw.Draw(canvas)
for i in range(len(d)):
 im=Image.open(out/f'thumb-{i+1:02}.png');im.thumbnail((215,303));x=(i%cols)*tw+7;y=(i//cols)*th+23;canvas.paste(im,(x,y));draw.text((x,y-17),str(i+1),fill='black')
canvas.save(out/'contact-sheet.jpg')
alltext='\n'.join(full)
result={'page_count':len(d),'file_bytes':P.stat().st_size,'metadata':d.metadata,'bookmarks':d.get_toc(),'out_of_page_text':bad,'pages':rows,'finding_ids_present':sorted(set(re.findall(r'F\d{2}',alltext))),'replacement_characters':alltext.count('\ufffd'),'word_count':len(alltext.split())}
(out/'validation.json').write_text(json.dumps(result,ensure_ascii=False,indent=2));print(json.dumps(result,ensure_ascii=False,indent=2))
