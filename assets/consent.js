(function(){
  var C=window.KNV||window.KNV_CC||{};
  /* ---------- Súhlas s cookies + GA4 + Meta pixel ---------- */
  var CK='knv_consent';
  function getC(){try{var m=document.cookie.match(/(?:^|; )knv_consent=([^;]+)/);return m?JSON.parse(decodeURIComponent(m[1])):null;}catch(e){return null;}}
  function setC(c){c.t=Date.now();document.cookie=CK+'='+encodeURIComponent(JSON.stringify(c))+';path=/;max-age='+(180*864e2)+';SameSite=Lax'+(location.protocol==='https:'?';Secure':'');}
  var loaded={};
  function loadTrackers(c){
    if(!C.track||!c)return;
    if(c.s&&C.ga&&!loaded.ga){loaded.ga=1;window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments);};
      gtag('consent','default',{analytics_storage:'granted',ad_storage:c.m?'granted':'denied',ad_user_data:c.m?'granted':'denied',ad_personalization:c.m?'granted':'denied'});
      gtag('js',new Date());gtag('config',C.ga,{page_title:document.title});if(C.ga2&&C.ga2!==C.ga)gtag('config',C.ga2,{page_title:document.title});
      var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id='+C.ga;document.head.appendChild(s);}
    if((c.s||c.m)&&C.gtm&&!loaded.gtm){loaded.gtm=1;window.dataLayer=window.dataLayer||[];if(!window.gtag)window.gtag=function(){dataLayer.push(arguments);};
      if(!loaded.ga)gtag('consent','default',{analytics_storage:c.s?'granted':'denied',ad_storage:c.m?'granted':'denied',ad_user_data:c.m?'granted':'denied',ad_personalization:c.m?'granted':'denied'});
      dataLayer.push({'gtm.start':Date.now(),event:'gtm.js',knv_consent_stats:!!c.s,knv_consent_marketing:!!c.m});
      var g=document.createElement('script');g.async=true;g.src='https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(C.gtm);document.head.appendChild(g);}
    if(c.m&&C.pixel&&!loaded.fb){loaded.fb=1;
      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
      fbq('init',C.pixel);fbq('track','PageView',{content_name:C.page||''});}
  }
  var GA_EV={CompleteRegistration:'sign_up',Lead:'generate_lead',Contact:'contact',Subscribe:'newsletter_signup'};
  window.knvTrack=function(ev,p){p=p||{};try{if(window.fbq&&loaded.fb)fbq('track',ev,p);if(window.gtag&&loaded.ga)gtag('event',GA_EV[ev]||ev,p);}catch(e){}};
  function banner(force){
    var c=getC(); if(c&&!force){loadTrackers(c);return;}
    if(document.getElementById('knv-cc'))return;
    var d=document.createElement('div');d.id='knv-cc';d.setAttribute('role','dialog');d.setAttribute('aria-label','Nastavenie cookies');
    d.innerHTML='<div class="knv-cc-box"><strong class="knv-cc-h">Cookies na Kam na vysokú</strong>'+
      '<p>Nevyhnutné cookies potrebujeme, aby web fungoval. So štatistickými (Google Analytics) zistíme, čo vás zaujíma, a s marketingovými (Meta) vám ukážeme pripomienku Roadshow aj na sociálnych sieťach. <a href="'+(C.cookies||'#')+'">Viac o cookies</a></p>'+
      '<div class="knv-cc-opts" hidden><label><input type="checkbox" checked disabled> Nevyhnutné</label><label><input type="checkbox" id="knv-cc-s"'+(c&&c.s?' checked':'')+'> Štatistické</label><label><input type="checkbox" id="knv-cc-m"'+(c&&c.m?' checked':'')+'> Marketingové</label></div>'+
      '<div class="knv-cc-btns"><button type="button" data-a="all" class="knv-cc-p">Prijať všetko</button><button type="button" data-a="none">Len nevyhnutné</button><button type="button" data-a="set">Nastavenia</button></div></div>';
    document.body.appendChild(d);
    d.addEventListener('click',function(e){var a=e.target.getAttribute&&e.target.getAttribute('data-a');if(!a)return;
      if(a==='set'){var o=d.querySelector('.knv-cc-opts');o.hidden=false;e.target.textContent='Uložiť výber';e.target.setAttribute('data-a','save');return;}
      var v=a==='all'?{s:1,m:1}:a==='none'?{s:0,m:0}:{s:d.querySelector('#knv-cc-s').checked?1:0,m:d.querySelector('#knv-cc-m').checked?1:0};
      var prev=getC();setC(v);d.remove();
      if(prev&&((prev.s&&!v.s)||(prev.m&&!v.m))){location.reload();return;}
      if(C.old&&v.m&&!(prev&&prev.m)){location.reload();return;}
      loadTrackers(v);});
  }
  window.knvCookies=function(){banner(true);};
  function initCookieLink(){
    if(document.getElementById('knv-cc-link'))return;
    var a=[].slice.call(document.querySelectorAll('footer a')).filter(function(x){return x.textContent.trim()==='Cookies';})[0];
    if(!a)return;var b=document.createElement('a');b.id='knv-cc-link';b.href='#';b.textContent='Nastavenia cookies';b.style.cssText=a.getAttribute('style')||'';
    b.addEventListener('click',function(e){e.preventDefault();banner(true);});a.parentNode.insertBefore(b,a.nextSibling);
    if(a.previousSibling&&a.previousSibling.nodeType===3){a.parentNode.insertBefore(document.createTextNode(a.previousSibling.nodeValue),b);}
  }
  var ccStarted=false;
  function initConsent(){if(ccStarted||!document.body)return;ccStarted=true;banner(false);}
  function run(){initConsent();initCookieLink();}
  if(document.readyState!=='loading')run();else document.addEventListener('DOMContentLoaded',run);
  setTimeout(initCookieLink,1500);setTimeout(initCookieLink,4000);
})();
