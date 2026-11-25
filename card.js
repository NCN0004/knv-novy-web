window.renderCards=function(E,root){
 const esc=s=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;');
 const C={SK:['SLOVENSKO','#E4F1E2','#1F6A1E'],CZ:['ČESKO','#FBE3E4','#A8222A']};
 const pill=(t,bg,fg,b)=>`<span style="display:inline-block;font-size:12.5px;font-weight:600;line-height:1.25;padding:5px 10px;border-radius:999px;background:${bg};color:${fg};border:1px solid ${b||bg};margin:0 6px 6px 0">${esc(t)}</span>`;
 const lab=t=>`<div style="font-size:11.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#8A8484;margin:14px 0 8px">${t}</div>`;
 root.innerHTML='<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px;align-items:start">'+E.map(e=>{
  const c=C[e.c];
  let h=`<div style="background:#fff;border:1.5px solid #E6E3E1;border-radius:20px;padding:18px 20px 16px;box-shadow:0 1px 0 #E6E3E1">
   <div style="display:flex;gap:14px;align-items:center">
    <div style="flex:none;width:64px;height:64px;border-radius:14px;border:1.5px solid #EFECEA;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden"><img src="${e.logo}" alt="" style="max-width:52px;max-height:52px;object-fit:contain"></div>
    <div style="min-width:0"><div style="font-weight:800;font-size:17px;line-height:1.25;color:#393536">${esc(e.n)}</div>${e.sub?`<div style="font-size:13.5px;color:#575152;margin-top:2px">${esc(e.sub)}</div>`:''}
     <div style="margin-top:6px">${pill(c[0],c[1],c[2])}${e.tag?pill(e.tag,'#F2F0EE','#575152'):''}</div></div>
   </div>`;
  if(e.ex&&e.ex.length){h+=lab('V stánku sa dozviete o fakultách')+'<div>'+e.ex.map(f=>pill(f,'#2B7F29','#fff')).join('')+'</div>';
   if(e.oth&&e.oth.length)h+=`<details style="margin-top:4px"><summary style="cursor:pointer;font-size:13px;font-weight:700;color:#1F6A1E;list-style:none">+ ďalšie fakulty univerzity (${e.oth.length})</summary><div style="margin-top:8px">${e.oth.map(f=>pill(f,'#fff','#575152','#DCD8D6')).join('')}</div></details>`;}
  else if(e.oth&&e.oth.length){h+=lab('V stánku sa dozviete o fakultách')+'<div>'+e.oth.map(f=>pill(f,'#F4F8F3','#2F4A2E','#D6E6D3')).join('')+'</div>';}
  if(e.countries)h+=lab('Štúdium na univerzitách v krajinách')+'<div>'+e.countries.map(f=>pill(f,'#F3EEF8','#4C2880','#E3D8EF')).join('')+'</div>';
  if(e.desc)h+=`<p style="font-size:14px;line-height:1.55;color:#4A4546;margin:14px 0 2px">${esc(e.desc)}</p>`;
  return h+'</div>';}).join('')+'</div>';
};
