import sys, io, json, struct, numpy as np
from PIL import Image
S=sys.argv[1]; out=sys.argv[2]
L,W,H=0.30,0.20,0.02   # produkta 79 izmēri (30 x 20 x 2 cm)
top=Image.open(S+'/ozola-delis-no-augsas.jpg').convert('RGB').crop((68,214,1476,919)).resize((1024,683),Image.LANCZOS)
side=Image.open(S+'/ozola-delis-sani.jpg').convert('RGB').crop((553,538,1014,814)).resize((1024,128),Image.LANCZOS)
atlas=Image.new('RGB',(1024,1024),(120,90,60)); atlas.paste(top,(0,0)); atlas.paste(side,(0,700))
buf=io.BytesIO(); atlas.save(buf,'JPEG',quality=85); img=buf.getvalue()
atlas.save(S+'/atlas.jpg')
tv=(0,683/1024); sv=(700/1024,828/1024)
x0,x1,y0,y1,z0,z1=-L/2,L/2,0,H,-W/2,W/2
P=[];N=[];T=[];I=[]
def quad(c,n,uv):
    b=len(P); P.extend(c); N.extend([n]*4); T.extend(uv); I.extend([b,b+1,b+2,b,b+2,b+3])
# top (y1): looking down, x right, z toward viewer = image down
quad([(x0,y1,z0),(x0,y1,z1),(x1,y1,z1),(x1,y1,z0)],(0,1,0),[(0,tv[0]),(0,tv[1]),(1,tv[1]),(1,tv[0])])
# bottom (y0)
quad([(x0,y0,z0),(x1,y0,z0),(x1,y0,z1),(x0,y0,z1)],(0,-1,0),[(0,tv[0]),(1,tv[0]),(1,tv[1]),(0,tv[1])])
S0,S1=sv
# front z1
quad([(x0,y1,z1),(x0,y0,z1),(x1,y0,z1),(x1,y1,z1)],(0,0,1),[(0,S0),(0,S1),(1,S1),(1,S0)])
# back z0
quad([(x1,y1,z0),(x1,y0,z0),(x0,y0,z0),(x0,y1,z0)],(0,0,-1),[(0,S0),(0,S1),(1,S1),(1,S0)])
# right x1
quad([(x1,y1,z1),(x1,y0,z1),(x1,y0,z0),(x1,y1,z0)],(1,0,0),[(0,S0),(0,S1),(.66,S1),(.66,S0)])
# left x0
quad([(x0,y1,z0),(x0,y0,z0),(x0,y0,z1),(x0,y1,z1)],(-1,0,0),[(0,S0),(0,S1),(.66,S1),(.66,S0)])
p=np.array(P,np.float32); n=np.array(N,np.float32); t=np.array(T,np.float32); i=np.array(I,np.uint16)
def pad(b,c=b'\0'): return b+c*((4-len(b)%4)%4)
chunks=[]; views=[]; off=0
for data,target in ((i.tobytes(),34963),(p.tobytes(),34962),(n.tobytes(),34962),(t.tobytes(),34962),(img,None)):
    d=pad(data); v={'buffer':0,'byteOffset':off,'byteLength':len(data)}
    if target: v['target']=target
    views.append(v); chunks.append(d); off+=len(d)
bin_=b''.join(chunks)
g={'asset':{'version':'2.0','generator':'3dp.lv board from product photos'},
 'scene':0,'scenes':[{'nodes':[0]}],'nodes':[{'mesh':0,'name':'Oak cutting board'}],
 'meshes':[{'primitives':[{'attributes':{'POSITION':1,'NORMAL':2,'TEXCOORD_0':3},'indices':0,'material':0}]}],
 'materials':[{'name':'oak','pbrMetallicRoughness':{'baseColorTexture':{'index':0},'metallicFactor':0,'roughnessFactor':0.75}}],
 'textures':[{'source':0,'sampler':0}],'samplers':[{'magFilter':9729,'minFilter':9987,'wrapS':33071,'wrapT':33071}],
 'images':[{'bufferView':4,'mimeType':'image/jpeg'}],
 'accessors':[{'bufferView':0,'componentType':5123,'count':len(i),'type':'SCALAR'},
  {'bufferView':1,'componentType':5126,'count':len(p),'type':'VEC3','min':p.min(0).tolist(),'max':p.max(0).tolist()},
  {'bufferView':2,'componentType':5126,'count':len(n),'type':'VEC3'},
  {'bufferView':3,'componentType':5126,'count':len(t),'type':'VEC2'}],
 'bufferViews':views,'buffers':[{'byteLength':len(bin_)}]}
js=pad(json.dumps(g,separators=(',',':')).encode(),b' ')
glb=struct.pack('<III',0x46546C67,2,12+8+len(js)+8+len(bin_))+struct.pack('<II',len(js),0x4E4F534A)+js+struct.pack('<II',len(bin_),0x004E4942)+bin_
open(out,'wb').write(glb); print(out,len(glb),'baiti')
