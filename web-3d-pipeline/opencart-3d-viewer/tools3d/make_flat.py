"""3dp.lv: plakana produkta .glb no paša produkta foto (virspuse no augšas, perspektīvi iztaisnota).
Lietošana: python make_flat.py spec.json  — katram ierakstam: src, quad (TL,TR,BR,BL px), L,W,H (m), r (stūra rādiuss, daļa no īsākās malas), ring (gredzena ārējais diametrs m vai 0), out"""
import sys, io, json, struct, math, numpy as np
from PIL import Image, ImageStat


def fixwind(P,N,I):
    P=np.array(P); N=np.array(N); out=[]
    for k in range(0,len(I),3):
        a,b,c=I[k:k+3]; n=np.cross(P[b]-P[a],P[c]-P[a])
        out.extend([a,b,c] if np.dot(n,N[a]+N[b]+N[c])>=0 else [a,c,b])
    return out

def glb(prims, images, out, name):
    bins=[]; views=[]; acc=[]; off=0
    def add(data, target=None):
        nonlocal off
        pad=(4-len(data)%4)%4; v={'buffer':0,'byteOffset':off,'byteLength':len(data)}
        if target: v['target']=target
        views.append(v); bins.append(data+b'\0'*pad); off+=len(data)+pad; return len(views)-1
    gprims=[]; mats=[]; texs=[]; imgs=[]
    for img in images:
        imgs.append({'bufferView':add(img),'mimeType':'image/jpeg'}); texs.append({'source':len(imgs)-1,'sampler':0})
    for p in prims:
        I=np.array(fixwind(p['P'],p['N'],p['I']),np.uint16); P=np.array(p['P'],np.float32); N=np.array(p['N'],np.float32)
        a={'POSITION':len(acc)}; acc.append({'bufferView':add(P.tobytes(),34962),'componentType':5126,'count':len(P),'type':'VEC3','min':P.min(0).tolist(),'max':P.max(0).tolist()})
        a['NORMAL']=len(acc); acc.append({'bufferView':add(N.tobytes(),34962),'componentType':5126,'count':len(N),'type':'VEC3'})
        if 'T' in p:
            T=np.array(p['T'],np.float32); a['TEXCOORD_0']=len(acc); acc.append({'bufferView':add(T.tobytes(),34962),'componentType':5126,'count':len(T),'type':'VEC2'})
        ii=len(acc); acc.append({'bufferView':add(I.tobytes(),34963),'componentType':5123,'count':len(I),'type':'SCALAR'})
        mats.append(p['mat']); gprims.append({'attributes':a,'indices':ii,'material':len(mats)-1})
    bin_=b''.join(bins)
    g={'asset':{'version':'2.0','generator':'3dp.lv make_flat.py (no produkta foto)'},'scene':0,'scenes':[{'nodes':[0]}],
       'nodes':[{'mesh':0,'name':name}],'meshes':[{'primitives':gprims}],'materials':mats,
       'samplers':[{'magFilter':9729,'minFilter':9987,'wrapS':33071,'wrapT':33071}],'accessors':acc,'bufferViews':views,'buffers':[{'byteLength':len(bin_)}]}
    if texs: g['textures']=texs; g['images']=imgs
    js=json.dumps(g,separators=(',',':')).encode(); js+=b' '*((4-len(js)%4)%4)
    data=struct.pack('<III',0x46546C67,2,12+8+len(js)+8+len(bin_))+struct.pack('<II',len(js),0x4E4F534A)+js+struct.pack('<II',len(bin_),0x004E4942)+bin_
    open(out,'wb').write(data); return len(data)

def outline(L,W,r,seg=10):
    pts=[]
    for cx,cy,a0 in ((L/2-r,W/2-r,0),(-L/2+r,W/2-r,90),(-L/2+r,-W/2+r,180),(L/2-r,-W/2+r,270)):
        for k in range(seg+1):
            a=math.radians(a0+90*k/seg); pts.append((cx+r*math.cos(a),cy+r*math.sin(a)))
    return pts

def slab(L,W,H,r):
    pts=outline(L,W,r); P=[];N=[];T=[];I=[]
    # top (y=H): model x = photo x (right), model z = photo y (down)
    for y,ny,flip in ((H,1,False),(0,-1,True)):
        b=len(P); P.append((0,y,0)); N.append((0,ny,0)); T.append((0.5,0.5))
        for (x,z) in pts: P.append((x,y,-z)); N.append((0,ny,0)); T.append((x/L+0.5, z*-1/W+0.5 if False else (-z)/W+0.5))
        n=len(pts)
        for k in range(n):
            a,c=b+1+k,b+1+(k+1)%n
            I.extend([b,c,a] if not flip else [b,a,c])
    PT,NT,TT,IT=P,N,T,I; P=[];N=[];T=[];I=[]
    n=len(pts)
    for k in range(n):
        (x0,z0),(x1,z1)=pts[k],pts[(k+1)%n]
        nx,nz=(z1-z0),-(x1-x0); l=math.hypot(nx,nz); nx/=l; nz/=l
        b=len(P)
        for (x,z) in ((x0,z0),(x1,z1)):
            for y in (H,0):
                P.append((x,y,-z)); N.append((nx,0,-nz)); T.append((x/L+0.5,(-z)/W+0.5))
        I.extend([b,b+1,b+3,b,b+3,b+2])
    return (PT,NT,TT,IT),(P,N,I)

def torus(R,rr,cx,cy,cz,seg=36,sides=12):
    P=[];N=[];I=[]
    for i in range(seg):
        u=2*math.pi*i/seg
        for j in range(sides):
            v=2*math.pi*j/sides
            nx,ny,nz=math.cos(u)*math.cos(v),math.sin(v),math.sin(u)*math.cos(v)
            # ring lies in the XZ plane (flat on the table)
            P.append((cx+(R+rr*math.cos(v))*math.cos(u), cy+rr*math.sin(v), cz+(R+rr*math.cos(v))*math.sin(u))); N.append((nx,ny,nz))
    for i in range(seg):
        for j in range(sides):
            a=i*sides+j; b=((i+1)%seg)*sides+j; c=((i+1)%seg)*sides+(j+1)%sides; d=i*sides+(j+1)%sides
            I.extend([a,d,c,a,c,b])
    return P,N,I

for s in json.load(open(sys.argv[1],encoding='utf-8')):
    L,W,H,r=s['L'],s['W'],s['H'],s['r']*min(s['L'],s['W'])
    im=Image.open(s['src']).convert('RGB')
    q=s['quad']; sc=s.get('scale',1)
    q=[(x/sc,y/sc) for x,y in q]
    tw=1024; th=int(round(tw*W/L)) if L>=W else 1024
    if W>L: tw=int(round(1024*L/W))
    # PIL QUAD: data = TL, BL, BR, TR corners in source
    top=im.transform((tw,th),Image.QUAD,(q[0][0],q[0][1],q[3][0],q[3][1],q[2][0],q[2][1],q[1][0],q[1][1]),Image.BICUBIC)
    buf=io.BytesIO(); top.save(buf,'JPEG',quality=86); top.save(s['out'].replace('.glb','-top.jpg'),quality=86)
    edge=tuple(int(c*0.82) for c in ImageStat.Stat(top.crop((0,0,tw,max(4,th//40)))).median)
    (P,N,T,I),(SP,SN,SI)=slab(L,W,H,r)
    rough=s.get('rough',0.8)
    band=top.crop((int(tw*.06),int(th*.06),int(tw*.94),int(th*.94)))
    inner=top.crop((int(tw*.14),int(th*.14),int(tw*.86),int(th*.86)))
    m=Image.new('L',band.size,255); m.paste(0,(inner.size[0]//1 and int(tw*.08),int(th*.08),int(tw*.08)+inner.size[0],int(th*.08)+inner.size[1]))
    med=ImageStat.Stat(band,mask=m).median
    sc_=s.get('edge',0.85); col=[min(1,(c/255)**2.2*sc_) for c in med]+[1]
    prims=[{'P':P,'N':N,'T':T,'I':I,'mat':{'name':'surface','pbrMetallicRoughness':{'baseColorTexture':{'index':0},'metallicFactor':s.get('metal',0),'roughnessFactor':rough}}},
           {'P':SP,'N':SN,'I':SI,'mat':{'name':'edge','pbrMetallicRoughness':{'baseColorFactor':col,'metallicFactor':s.get('metal',0),'roughnessFactor':rough}}}]
    if s.get('ring'):
        R=s['ring']/2; rr=R*0.09
        tp,tn,ti=torus(R-rr,rr,0,rr,-(W/2)-R*0.75)
        prims.append({'P':tp,'N':tn,'I':ti,'mat':{'name':'steel ring','pbrMetallicRoughness':{'baseColorFactor':[0.78,0.79,0.8,1],'metallicFactor':1,'roughnessFactor':0.3}}})
    size=glb(prims,[buf.getvalue()],s['out'],s['name'])
    print(s['out'].split('/')[-1],size,'B', f'{L*100:.1f}x{W*100:.1f}x{H*100:.2f} cm')
