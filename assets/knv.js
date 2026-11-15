(function(){
  var C=window.KNV||{};
  var BURGER='<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';
  function initMenu(){
    var h=document.querySelector('header'); if(!h||h.getAttribute('data-knv'))return;
    var nav=h.querySelector('nav[aria-label="Hlavné menu"]'); if(!nav)return;
    h.setAttribute('data-knv','1');
    var row=nav.parentElement;
    var btn=h.querySelector('button[aria-label="Otvoriť menu"]');
    if(!btn){btn=document.createElement('button');btn.type='button';btn.setAttribute('aria-label','Otvoriť menu');btn.innerHTML=BURGER;row.appendChild(btn);}
    btn.classList.add('knv-burger');
    var p=document.createElement('div');p.className='knv-panel';p.setAttribute('role','dialog');p.setAttribute('aria-label','Menu');
    var html='<div class="knv-panel-top"><strong>Kam na vysokú</strong><button type="button" class="knv-burger" style="display:flex" aria-label="Zavrieť menu"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
    nav.querySelectorAll('a').forEach(function(a){html+='<a class="knv-l" href="'+a.getAttribute('href')+'"'+(a.getAttribute('aria-current')?' aria-current="page"':'')+'>'+a.textContent+'</a>';});
    html+='<div class="knv-ctas"><a class="knv-cta" style="background:#2B7F29;color:#fff" href="'+(C.zastavka||'#')+'">Chcem bezplatnú vstupenku na Roadshow</a><a class="knv-cta" style="border:2px solid #D42A33;color:#C8232C" href="'+(C.vystavovat||'#')+'">Chcem byť vystavovateľ</a></div>';
    p.innerHTML=html;document.body.appendChild(p);
    btn.addEventListener('click',function(){p.classList.add('open');document.documentElement.style.overflow='hidden';});
    p.querySelector('button').addEventListener('click',function(){p.classList.remove('open');document.documentElement.style.overflow='';});
  }
  var SE='https://app.smartemailing.cz/public/web-forms-v2/display-form/139946-';
  var SE_STU=SE+'0nnfx1xcsxehvjo6omo3xtlm31pbqhkirs54qvgt9ha2jf5fmcgx13dktpq3k2xiukyqt1sfvfc3wxamv7wuk3kf25dwg1buz0xf';
  var SE_CLS=SE+'9wmfbxs43nrd4r2uxchgkb2ajc43jkgcrm0zfwv8semxk2gjfxz1p51i557dzex225zpy4m1i6i3vbqbooajxygnjha5h2vgqsfc';
  var SE_NEWS=SE+'gfs8vhso6z0oygw9ibssd1thromutdmf0k8wdfe31s32af0ef7dyflv1z0qpx9wfbkzz5m2nas3u8kt91as2bk9lk8kduihnt6wy';
  var SE_STU_BA=SE+'04w8k27mddwxaexzhzyr2jhvxt513xnv11bitd7mfr0foo3rcsiu7i4b7w6t1u1e74ks91k4mps4e4qb5w6x6bzqp00izmikbgdu';
  var SE_CLS_BA=SE+'bin4awc56hlarrm3vv1kxrye521neouai11x2gvon9ib4431wfqxx9w9skoxc80hd01nd7c594n687dh7yekce3ipew8exmuurg3';
  function initReg(){}
  function val(f,id){var el=f.querySelector('#'+id);return el?String(el.value||'').trim():'';}
  function split(n){n=n.replace(/\s+/g,' ').trim();var i=n.indexOf(' ');return i<0?[n,'']:[n.slice(0,i),n.slice(i+1)];}
  function seSubmit(url,fields){
    var fm=document.createElement('form');fm.method='post';fm.action=url;fm.target='_blank';fm.style.display='none';
    var add=function(k,v){var i=document.createElement('input');i.type='hidden';i.name=k;i.value=v;fm.appendChild(i);};
    Object.keys(fields).forEach(function(k){if(fields[k]!==null&&fields[k]!==undefined&&fields[k]!=='')add('fields['+k+']',fields[k]);});
    add('_submit','Registrovať sa');add('referrer',location.href);add('sessionid','');add('sessionUid','');add('_do','webFormHtmlRenderer-webFormForm-submit');
    document.body.appendChild(fm);fm.submit();setTimeout(function(){fm.remove();},3000);
  }
  function regSubmit(e){
    var f=e.target; if(!f||f.tagName!=='FORM')return;
    var cb=f.querySelector('input[type=checkbox]'); var news=cb&&cb.checked?'on':null;
    var stop=function(el,msg){e.preventDefault();e.stopImmediatePropagation();if(el){el.focus();el.style.borderColor='#C8232C';}if(msg)alert(msg);};
    if(f.querySelector('#r-email')){
      var em=val(f,'r-email'),nm=val(f,'r-meno');
      if(!nm)return stop(f.querySelector('#r-meno'),'Vyplň prosím meno a priezvisko.');
      if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em))return stop(f.querySelector('#r-email'),'Skontroluj prosím e-mail – príde naň vstupenka.');
      var r=val(f,'r-rocnik'),n=split(nm);
      seSubmit(C.stopCode==='29'?SE_STU_BA:SE_STU,{df_name:n[0],df_surname:n[1],df_emailaddress:em,cf_4:val(f,'r-skola')||'-',cf_41:r.indexOf('3')===0?'38':(r.indexOf('4')===0?'41':'44'),cf_35:C.stopCode||'26',cf_38:'32',cf_44:val(f,'r-spolu')||'1',cf_50:news});
      (window.knvTrack||function(){})('CompleteRegistration',{content_name:'Roadshow '+(C.stopCode==='29'?'Bratislava':'Prievidza'),status:'student'});
    } else if(f.querySelector('#t-email')||f.querySelector('#p-email')){
      var P=!!f.querySelector('#p-email');
      var em2=P?val(f,'p-email'):val(f,'t-email'),nm2=P?val(f,'p-meno'):val(f,'t-ucitel');
      if(!nm2)return stop(f.querySelector(P?'#p-meno':'#t-ucitel'),'Vyplňte prosím meno pedagóga.');
      if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em2))return stop(f.querySelector(P?'#p-email':'#t-email'),'Skontrolujte prosím e-mail.');
      var sk=P?[val(f,'p-skola'),val(f,'p-mesto')].filter(Boolean).join(', '):val(f,'t-skola');
      var pc=P?val(f,'p-pocet'):val(f,'t-pocet');if(!pc)return stop(f.querySelector(P?'#p-pocet':'#t-pocet'),'Vyplňte prosím počet žiakov spolu.');
      var tr=val(f,P?'p-tried':'t-tried')||'1';
      var info=['tried: '+tr,P?val(f,'p-cas'):val(f,'t-prichod')];
      if(P){if(val(f,'p-doprava'))info.push('doprava: '+val(f,'p-doprava'));if(val(f,'p-pozn'))info.push('pozn.: '+val(f,'p-pozn'));}
      var zs=P?val(f,'p-zastavka'):'';var n2=split(nm2);
      var code2=P?(/bratislav/i.test(zs)?'29':'26'):(C.stopCode||'26');
      seSubmit(code2==='29'?SE_CLS_BA:SE_CLS,{df_name:n2[0],df_surname:n2[1],df_emailaddress:em2,cf_4:sk||'-',cf_44:pc,cf_47:info.filter(Boolean).join(' · '),cf_35:code2,cf_38:'35',cf_50:news});
      (window.knvTrack||function(){})('Lead',{content_name:'Trieda Roadshow '+(code2==='29'?'Bratislava':'Prievidza'),lead_type:'trieda',classes:tr,students:pc});
    }
  }
  function collect(f){
    var out=[];
    f.querySelectorAll('input,select,textarea').forEach(function(el){
      if(el.type==='submit'||el.type==='button'||el.name==='knv_hp')return;
      if((el.type==='checkbox'||el.type==='radio')&&!el.checked)return;
      var v=el.value; if(!v)return;
      var lab='';var l=el.closest('label');
      if(el.id){var L=document.querySelector('label[for="'+el.id+'"]');if(L)lab=L.textContent;}
      if(!lab&&l)lab=l.textContent.replace(v,'');
      if(!lab)lab=el.getAttribute('aria-label')||el.placeholder||el.name||'pole';
      out.push([lab.trim().replace(/\s+/g,' ').slice(0,80),v]);
    });
    f.querySelectorAll('button').forEach(function(b){var t=b.textContent.trim();if(b.getAttribute('aria-pressed')==='true'||/^✓/.test(t))out.push(['Vybraté',t.replace(/^✓\s*/,'')]);});
    return out;
  }
  window.addEventListener('submit',function(e){
    var f=e.target; if(C.regForm){regSubmit(e);return;}
    var nl=f.querySelector&&(f.querySelector('#nl-email')||f.querySelector('#r-nl')||f.querySelector('#kv-email')||(/^Vysledky-/.test(C.page||'')&&f.querySelector('input[type=email]')));
    if(nl){var em=String(nl.value||'').trim();
      if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em)){e.preventDefault();e.stopImmediatePropagation();nl.focus();nl.style.borderColor='#C8232C';alert('Skontroluj prosím e-mail.');return;}
      var sh=f.querySelector&&f.querySelector('#nl-suhlas');if(sh&&!sh.checked){e.preventDefault();e.stopImmediatePropagation();alert('Potvrď prosím súhlas so zasielaním informácií.');return;}
      if(C.fak){e.preventDefault();try{var lf=new FormData();lf.append('action','knv_lead');lf.append('email',em);lf.append('fak',C.fak);lf.append('url',location.href);fetch(C.ajax,{method:'POST',body:lf,credentials:'same-origin',keepalive:true});}catch(x){}
        var bt=f.querySelector('button');if(bt){bt.textContent='Hotovo, ozveme sa ✓';bt.disabled=true;}}
      seSubmit(SE_NEWS,{df_emailaddress:em});(window.knvTrack||function(){})('Subscribe',{content_name:'Newsletter '+(C.page||'')});return;}
    var fields=collect(f); if(!fields.length)return;
    var fd=new FormData(); fd.append('action','knv_form'); fd.append('page',C.page||''); fd.append('url',location.href);
    fd.append('hp',(f.querySelector('[name=knv_hp]')||{}).value||'');
    fields.forEach(function(x,i){fd.append('f['+i+'][0]',x[0]);fd.append('f['+i+'][1]',x[1]);});
    try{fetch(C.ajax,{method:'POST',body:fd,credentials:'same-origin',keepalive:true});}catch(err){}
    var EV={'Kontakt':['Contact',{}],'Chcem-vystavovat':['Lead',{content_name:'Vystavovateľ',lead_type:'vystavovatel'}],'Casopis':['Lead',{content_name:'Časopis',lead_type:'casopis'}]}[C.page]||['Lead',{content_name:C.page,lead_type:'formular'}];
    (window.knvTrack||function(){})(EV[0],EV[1]);
  },true);
  function esc(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}
  function initPost(){
    var P=C.post; if(!P)return;
    var body=document.querySelector('[data-knv="body"]'); if(!body||body.getAttribute('data-done'))return;
    body.setAttribute('data-done','1');
    var q=function(k){return document.querySelector('[data-knv="'+k+'"]');};
    if(q('title'))q('title').textContent=P.title;
    if(q('cat'))q('cat').textContent=P.cat; if(q('crumb'))q('crumb').textContent=P.cat;
    if(q('lead')){if(P.lead)q('lead').textContent=P.lead;else q('lead').style.display='none';}
    if(q('meta'))q('meta').innerHTML='<strong style="color:#393536">Kam na vysokú</strong> · '+esc(P.meta);
    if(q('avatar'))q('avatar').textContent='K';
    var ph=q('photo'); if(ph){if(P.img){ph.innerHTML='';ph.style.padding='0';ph.style.overflow='hidden';ph.style.background='#F0F5EE url('+P.img+') center/cover no-repeat';ph.setAttribute('role','img');ph.setAttribute('aria-label',P.title);}else{ph.style.display='none';}}
    body.innerHTML=P.html;
    body.querySelectorAll('img').forEach(function(i){i.style.maxWidth='100%';i.style.height='auto';i.style.borderRadius='16px';});
    body.querySelectorAll('iframe').forEach(function(i){i.style.width='100%';i.style.aspectRatio='16/9';i.style.height='auto';i.style.border='0';i.style.borderRadius='16px';});
    var toc=q('toc'),hs=body.querySelectorAll('h2,h3');
    if(toc){if(hs.length){toc.innerHTML='';hs.forEach(function(h,i){h.id='k'+(i+1);var a=document.createElement('a');a.href='#k'+(i+1);a.textContent=h.textContent;a.style.textDecoration='none';toc.appendChild(a);});}else{var box=toc.parentElement;if(box)box.style.display='none';}}
    var wa=q('wa'); if(wa){wa.href='https://wa.me/?text='+encodeURIComponent(P.title+' '+P.url);wa.target='_blank';wa.rel='noopener';}
    var cp=q('copy'); if(cp){cp.addEventListener('click',function(e){e.preventDefault();try{navigator.clipboard.writeText(P.url);cp.textContent='Skopírované ✓';}catch(x){}});}
  }
  function run(){initMenu();initReg();initPost();}
  var mo=new MutationObserver(function(){run();});
  mo.observe(document.documentElement,{childList:true,subtree:true});
  document.addEventListener('DOMContentLoaded',run);setTimeout(run,800);setTimeout(run,2500);
})();
