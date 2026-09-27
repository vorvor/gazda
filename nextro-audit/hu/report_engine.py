from pathlib import Path
import json, re, html
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle, PageBreak, Image, KeepTogether
from reportlab.platypus.tableofcontents import TableOfContents
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from PIL import Image as PILImage, ImageDraw, ImageFont

ROOT=Path('/workspace/nextro-audit'); E=ROOT/'evidence'; OUTPUT=Path('/workspace/nextro.hu_full_website_audit_hu.pdf')
FONT='/usr/share/fonts/truetype/dejavu/'
pdfmetrics.registerFont(TTFont('Audit',FONT+'DejaVuSans.ttf'))
pdfmetrics.registerFont(TTFont('AuditBold',FONT+'DejaVuSans-Bold.ttf'))
pdfmetrics.registerFontFamily('Audit',normal='Audit',bold='AuditBold',italic='Audit',boldItalic='AuditBold')
NAVY=colors.HexColor('#142b3b'); GREEN=colors.HexColor('#376344'); TEAL=colors.HexColor('#147d89'); GRAY=colors.HexColor('#52616c'); LIGHT=colors.HexColor('#edf2f5'); RED=colors.HexColor('#a52b39')
W,H=595.2756,841.8898; M=44; CW=W-2*M
S={}
S['body']=ParagraphStyle('body',fontName='Audit',fontSize=9.0,leading=13.0,textColor=NAVY,spaceAfter=7)
S['small']=ParagraphStyle('small',parent=S['body'],fontSize=8,leading=11,spaceAfter=5)
S['tiny']=ParagraphStyle('tiny',parent=S['body'],fontSize=7.1,leading=9.5,spaceAfter=4,wordWrap='CJK')
S['h1']=ParagraphStyle('h1',parent=S['body'],fontName='AuditBold',fontSize=22,leading=27,spaceAfter=16)
S['h2']=ParagraphStyle('h2',parent=S['body'],fontName='AuditBold',fontSize=13,leading=18,spaceBefore=10,spaceAfter=7,keepWithNext=True)
S['h3']=ParagraphStyle('h3',parent=S['body'],fontName='AuditBold',fontSize=10.1,leading=14,spaceBefore=7,spaceAfter=5,keepWithNext=True)
S['kicker']=ParagraphStyle('kicker',parent=S['body'],fontName='AuditBold',fontSize=8.6,leading=12,textColor=TEAL,spaceAfter=8)
S['caption']=ParagraphStyle('caption',parent=S['small'],fontSize=7.8,leading=11,textColor=GRAY,spaceBefore=5,spaceAfter=10)
S['table']=ParagraphStyle('table',parent=S['small'],fontSize=7.8,leading=10.2,spaceAfter=0)
S['tablehead']=ParagraphStyle('tablehead',parent=S['table'],fontName='AuditBold',textColor=colors.white)
S['cover']=ParagraphStyle('cover',parent=S['h1'],fontSize=35,leading=43,textColor=colors.white)
S['coverbody']=ParagraphStyle('coverbody',parent=S['body'],fontSize=12,leading=19,textColor=colors.HexColor('#dce8ed'))
S['bullet']=ParagraphStyle('bullet',parent=S['body'],leftIndent=11,firstLineIndent=-9,spaceAfter=6)

class AuditDoc(BaseDocTemplate):
 def __init__(self,path):
  super().__init__(str(path),pagesize=(W,H),leftMargin=M,rightMargin=M,topMargin=62,bottomMargin=47,title='nextro.hu | Teljes körű weboldalaudit',author='Független weboldalaudit',subject='SEO-, UX/UI-, akadálymentességi, teljesítmény-, tartalmi és konverziós audit — 2026. szeptember 22.')
  self.addPageTemplates(PageTemplate(id='Audit',frames=[Frame(M,47,CW,H-109,id='normal',leftPadding=0,rightPadding=0,topPadding=0,bottomPadding=0)],onPage=self.decorate))
 def decorate(self,c,doc):
  c.saveState()
  if doc.page==1:
   c.setFillColor(NAVY);c.rect(0,0,W,H,fill=1,stroke=0)
   c.setFillColor(TEAL);c.rect(0,H-17,W,17,fill=1,stroke=0)
   c.setStrokeColor(colors.HexColor('#325063'))
   for x in range(-300,700,80):c.line(x,0,x+500,H)
  else:
   c.setStrokeColor(colors.HexColor('#cfdce3'));c.line(M,H-40,W-M,H-40)
   c.setFont('AuditBold',8);c.setFillColor(NAVY);c.drawString(M,H-30,'NEXTRO.HU  /  WEBOLDALAUDIT')
   c.setFont('Audit',7.3);c.setFillColor(GRAY);c.drawRightString(W-M,H-30,'Nyilvános weboldal vizsgálata · 2026. szeptember 22.')
   c.setStrokeColor(colors.HexColor('#cfdce3'));c.line(M,34,W-M,34)
   c.setFont('Audit',7);c.drawString(M,22,'Bizonyítékokon alapuló megállapítások • Nem minősül jogi tanácsadásnak')
   c.drawRightString(W-M,22,str(doc.page))
  c.restoreState()
 def afterFlowable(self,f):
  if isinstance(f,Paragraph) and getattr(f,'toc_title',None):
   key='section-'+str(getattr(f,'section_id',''))
   self.canv.bookmarkPage(key);self.canv.addOutlineEntry(f.toc_title,key,0,False)
   self.notify('TOCEntry',(0,f.toc_title,self.page,key))

story=[]
def esc(t):return html.escape(str(t))
def p(t,style='body'):story.append(Paragraph(t,S[style]))
def title(t,section=None):
 q=Paragraph(t,S['h1'])
 if section is not None:q.toc_title=t;q.section_id=section
 story.append(q)
def page(t,section=None,kicker=None):
 story.append(PageBreak())
 if kicker:p(kicker,'kicker')
 title(t,section)
def h(t):p(t,'h2')
def sub(t):p(t,'h3')
def b(t):p('• '+t,'bullet')
def table(headers,rows,widths=None):
 data=[[Paragraph(esc(x),S['tablehead']) for x in headers]]
 data += [[Paragraph(str(x),S['table']) for x in r] for r in rows]
 widths=[CW*w/sum(widths) for w in widths] if widths else [CW/len(headers)]*len(headers)
 t=Table(data,colWidths=widths,repeatRows=1,hAlign='LEFT')
 t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,LIGHT]),('LINEBELOW',(0,0),(-1,0),.5,NAVY),('LINEBELOW',(0,1),(-1,-1),.25,colors.HexColor('#d7e1e7'))]))
 story.append(t);story.append(Spacer(1,10))
def callout(label,text,color=TEAL):
 t=Table([[Paragraph(esc(label),ParagraphStyle('callouthead',parent=S['small'],fontName='AuditBold',textColor=color))],[Paragraph(text,S['body'])]],colWidths=[CW])
 t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),LIGHT),('BOX',(0,0),(-1,-1),.5,colors.HexColor('#d6e1e7')),('LINEBEFORE',(0,0),(0,-1),3,color),('LEFTPADDING',(0,0),(-1,-1),12),('RIGHTPADDING',(0,0),(-1,-1),12),('TOPPADDING',(0,0),(-1,0),9),('BOTTOMPADDING',(0,-1),(-1,-1),8)]))
 story.append(KeepTogether([t]));story.append(Spacer(1,12))
def image(name,caption,maxheight=270,width=None):
 path=E/name
 with PILImage.open(path) as im:iw,ih=im.size
 width=width or CW; scale=min(width/iw,(maxheight-20)/ih)
 story.append(Image(str(path),width=iw*scale,height=ih*scale,hAlign='CENTER'));p(caption,'caption')
def imagepair(names,captions,maxheight=290):
 cells=[]
 for name in names:
  path=E/name
  with PILImage.open(path) as im:iw,ih=im.size
  sc=min((CW/2-8)/iw,maxheight/ih);cells.append(Image(str(path),width=iw*sc,height=ih*sc,hAlign='CENTER'))
 t=Table([cells,[Paragraph(c,S['caption']) for c in captions]],colWidths=[CW/2,CW/2]);t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),5),('RIGHTPADDING',(0,0),(-1,-1),5)]));story.append(t)
def annotate(src,dst,boxes):
 im=PILImage.open(E/src).convert('RGB');draw=ImageDraw.Draw(im);font=ImageFont.truetype(FONT+'DejaVuSans-Bold.ttf',18)
 for box,label in boxes:
  draw.rectangle(box,outline='#c62636',width=4);x,y=box[:2];bb=draw.textbbox((0,0),label,font=font);tw=bb[2]-bb[0]+14
  draw.rectangle((x,y-27,x+tw,y),fill='#c62636');draw.text((x+7,y-26),label,fill='white',font=font)
 im.save(E/dst)
def url(t):p(esc(t),'tiny')
def build():
 doc=AuditDoc(OUTPUT);doc.multiBuild(story);return OUTPUT
