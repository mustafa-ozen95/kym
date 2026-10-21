/*
 * Sistem listesi SUNUCUDAN gelir.
 *
 * Kayıtlar veritabanında; bu dosyanın işi yalnızca çizim. Sayfaya bir
 * kutucuk eklemek ya da çıkarmak kod düzenlemek demek değildir.
 *
 * Veri, sayfadaki application/json etiketinden okunur — ayrı bir istek
 * yapılmaz, sayfa açılır açılmaz elimizdedir.
 */
const CRMS = JSON.parse(document.getElementById("sistem-verisi")?.textContent || "[]");

/* ---------- kart altındaki şeritler, her kartta ayrı ---------- */
const TAPE = [5,11,7,14,4,9,13,6,10,16,5,8,12,7,15,6,10,4,13,9];
const STRIPS = {
  s1: `${Array.from({length:26},(_,i)=>`<rect class="eq" x="${i*7.7}" y="2" width="4.4" height="12" rx="1.2" fill="currentColor" data-gecikme="${(i*.075).toFixed(2)}s"/>`).join("")}`,
  s2: `<g class="run"><path d="M0 8 q 12.5 -6.5 25 0 t 25 0 t 25 0 t 25 0 t 25 0 t 25 0 t 25 0 t 25 0 t 25 0 t 25 0 t 25 0 t 25 0" fill="none" stroke="currentColor" stroke-width="1.8" opacity=".7"/></g>`,
  s3: `<line class="ln" pathLength="100" x1="0" y1="3" x2="150" y2="3"/>
       <line class="ln" pathLength="100" x1="0" y1="8" x2="196" y2="8" data-gecikme=".5s"/>
       <line class="ln" pathLength="100" x1="0" y1="13" x2="104" y2="13" data-gecikme="1s"/>`,
  s4: `${Array.from({length:11},(_,i)=>`<rect class="fld" x="${i*18.5}" y="4" width="${[13,9,15,11,7,14,10,16,8,12,9][i]}" height="8" rx="1.4" fill="currentColor" data-gecikme="${(i*.16).toFixed(2)}s"/>`).join("")}`,
  s5: `<line class="wire" x1="0" y1="8" x2="200" y2="8" stroke="currentColor" stroke-width="1.4" stroke-dasharray="3 5" opacity=".3"/>
       ${[8,52,100,148,192].map((x,i)=>`<circle class="hop" cx="${x}" cy="8" r="3" fill="currentColor" data-gecikme="${(i*.52).toFixed(2)}s"/>`).join("")}
       <circle class="pkt" cx="0" cy="8" r="2.4" fill="currentColor"/>`,
  s11: `${[0,1,2].map(i=>`<rect class="frame" x="${i*68}" y="1" width="64" height="14" rx="2" fill="currentColor" data-gecikme="${(i*1.1).toFixed(1)}s"/>`).join("")}`,
  s12: `<line x1="0" y1="8" x2="200" y2="8" stroke="currentColor" stroke-width="1.2" opacity=".18"/>
        ${Array.from({length:21},(_,i)=>`<rect x="${i*10}" y="${i%5?5.5:3.5}" width="1.3" height="${i%5?5:9}" rx=".6" fill="currentColor" opacity=".28"/>`).join("")}
        <rect class="fill" x="0" y="6.5" width="200" height="3" rx="1.5" fill="currentColor" opacity=".55"/>
        <circle class="mark" cx="0" cy="8" r="3.4" fill="currentColor"/>`,
  s10: `<line x1="0" y1="8" x2="200" y2="8" stroke="currentColor" stroke-width="1.2" opacity=".2"/>
        ${[26,74,122,170].map((x,i)=>`<circle class="ping" cx="${x}" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.5" data-gecikme="${(i*.68).toFixed(2)}s"/>
           <circle cx="${x}" cy="8" r="2.2" fill="currentColor" opacity=".5"/>`).join("")}`,
  s9: `<g class="tape">${[0,200].map(off => TAPE.map((h,i) =>
         `<rect x="${off + i*10}" y="${8 - h/2}" width="3.2" height="${h}" rx="1.2" fill="currentColor" opacity=".5"/>`).join("")).join("")}</g>`,
  s8: `<path class="ekg" pathLength="100" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round"
         d="M0 8 H54 l5 -5 l4 9 l4 -8 l5 4 H124 l5 -5 l4 9 l4 -8 l5 4 H200"/>`,
  s7: `${Array.from({length:20},(_,i)=>`<rect x="${i*10}" y="4.5" width="7" height="7" rx="1.2" fill="currentColor" opacity=".13"/>`).join("")}
       <rect class="win" x="0" y="2" width="36" height="12" rx="2.5" fill="currentColor" opacity=".3"/>`,
  s6: `<line x1="0" y1="8" x2="200" y2="8" stroke="currentColor" stroke-width="1.2" opacity=".22"/>
       <rect class="ask" x="0" y="5" width="16" height="6" rx="3" fill="currentColor"/>
       <rect class="ans" x="184" y="5" width="16" height="6" rx="3" fill="currentColor"/>`,
  s13: `${Array.from({length:14},(_,i)=>{const h=3+i*.72; return `<rect class="grow" x="${i*14.4}" y="${14-h}" width="6" height="${h}" rx="1" fill="currentColor" opacity=".3" data-gecikme="${(i*.11).toFixed(2)}s"/>`;}).join("")}
        <path class="trend" pathLength="100" d="M2 13 L40 10 L78 11 L116 6 L154 5 L196 1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>`,
};

/* ---------- kabin gövdesi: 3/4 görünüm, derinlikli ---------- */
const F = { x:8, y:10, w:60, h:104 };          // ön yüz
const D = 13;                                   // yan derinlik

function shell(id){ return `
<defs>
  <linearGradient id="face-${id}" x1="0" y1="0" x2="1" y2="0">
    <stop offset="0" stop-color="var(--m4)"/><stop offset=".55" stop-color="var(--m4)"/><stop offset="1" stop-color="var(--m3)"/>
  </linearGradient>
  <linearGradient id="side-${id}" x1="0" y1="0" x2="1" y2="0">
    <stop offset="0" stop-color="var(--m3)"/><stop offset="1" stop-color="var(--m2)"/>
  </linearGradient>
  <linearGradient id="glass-${id}" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#ffffff" stop-opacity=".10"/>
    <stop offset=".45" stop-color="#ffffff" stop-opacity=".02"/>
    <stop offset=".55" stop-color="#ffffff" stop-opacity=".07"/>
    <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
  </linearGradient>
  <pattern id="mesh-${id}" width="3.4" height="3.4" patternUnits="userSpaceOnUse">
    <rect width="3.4" height="3.4" fill="var(--m1)" fill-opacity=".55"/>
    <circle cx="1.7" cy="1.7" r="1.1" fill="#000" fill-opacity=".55"/>
  </pattern>
  <clipPath id="bay-${id}"><rect x="12" y="16" width="52" height="92" rx="1.5"/></clipPath>
</defs>

<!-- zemin gölgesi -->
<ellipse cx="42" cy="118" rx="36" ry="4.5" fill="#000" opacity=".35"/>
<!-- yan panel (derinlik) -->
<path d="M68 14 L81 21 L81 111 L68 118 Z" fill="url(#side-${id})"/>
<path d="M68 14 L81 21" stroke="var(--m5)" stroke-width=".8" fill="none" opacity=".7"/>
<!-- ön gövde -->
<rect x="${F.x}" y="${F.y}" width="${F.w}" height="${F.h}" rx="3" fill="url(#face-${id})" stroke="var(--m1)" stroke-width="1.2"/>
<rect x="${F.x+1}" y="${F.y+1}" width="${F.w-2}" height="1" fill="var(--m5)" opacity=".8"/>
<!-- üst etiket -->
<rect x="14" y="4.5" width="48" height="7" rx="1.5" fill="var(--m3)" stroke="var(--m1)" stroke-width=".8"/>
<!-- iç bölme -->
<rect x="12" y="16" width="52" height="92" rx="1.5" fill="var(--m2)"/>
<!-- montaj rayları -->
<line x1="14.4" y1="18" x2="14.4" y2="106" stroke="var(--m1)" stroke-width="1.7" stroke-dasharray="1.7 4.6"/>
<line x1="61.6" y1="18" x2="61.6" y2="106" stroke="var(--m1)" stroke-width="1.7" stroke-dasharray="1.7 4.6"/>
<!-- köşe vidaları -->
${[[10.6,12.6],[65.4,12.6],[10.6,111.4],[65.4,111.4]].map(p=>`<circle cx="${p[0]}" cy="${p[1]}" r="1.1" fill="var(--m3)" stroke="var(--m5)" stroke-width=".4"/>`).join("")}`;
}

/*
 * Kapı çizimi.
 *
 * PLAKA METNİ BURADA BASILMAZ; kutucuk kurulurken textContent ile yazılır
 * (aşağıda, ad ve açıklamayla aynı yerde). Kod alanı serbest metindir ve
 * şablona olduğu gibi girerse innerHTML onu biçimlendirme sayar: `<text>`
 * içinden çıkıp olay işleyicisi taşıyan bir eleman eklenebilirdi. Şu anki
 * 20 karakter sınırı çalışan bir yükü sığdırmıyor ama o sınır güvenlik
 * için konmamıştı — bir gün gevşetilseydi açık sessizce doğardı. Aynı
 * dosyadaki ad, açıklama ve adres zaten öyle yazılıyor; plaka da katıldı.
 *
 * `id` serbest DEĞİL: BUILD nesnesinin anahtarlarından biri ya da yedeği
 * (bkz. çağıran taraf), o yüzden şablonda kalabilir.
 */
function door(id){ return `
<g class="door">
  <rect x="10" y="14" width="56" height="96" rx="2" fill="url(#mesh-${id})" stroke="var(--m5)" stroke-width="1.2"/>
  <rect x="10" y="14" width="56" height="96" rx="2" fill="url(#glass-${id})"/>
  <rect class="handle" x="62.2" y="55" width="2.4" height="14" rx="1.2" fill="var(--m6)"/>
</g>
<rect class="doorshade" x="12" y="16" width="52" height="92" rx="1.5" fill="#000"/>
<text class="plate" x="42" y="9.6" text-anchor="middle"></text>`;
}

/* ---------- yardımcılar ---------- */
const bez  = (y,h) => `<rect x="16" y="${y}" width="44" height="${h}" rx="1.2" fill="var(--m4)"/><rect x="16" y="${y}" width="44" height=".9" fill="var(--m5)"/>`;
const vent = (x,y,n,w=1.3) => Array.from({length:n},(_,i)=>`<rect x="${x+i*2.6}" y="${y}" width="${w}" height="3.6" rx=".5" fill="var(--m3)"/>`).join("");

/* 1 — sunucu + disk rafı */
const k1 = (id)=>`<g class="gear">
${Array.from({length:6},(_,i)=>{const y=18+i*10.5; return `<g>${bez(y,9)}${vent(38,y+2.6,7)}
  <circle class="led" cx="20" cy="${y+4.5}" r="1.5" fill="currentColor" data-gecikme="${(5-i)*.15}s"/>
  <circle cx="24.5" cy="${y+4.5}" r="1.5" fill="var(--m3)"/></g>`;}).join("")}
<g>${bez(82,24)}
${Array.from({length:8},(_,i)=>{const x=18.5+(i%4)*11, y=84.5+Math.floor(i/4)*10.5;
  return `<rect x="${x}" y="${y}" width="9.5" height="8.5" rx="1" fill="var(--m3)"/>
          <rect class="disk" x="${x+1.2}" y="${y+6}" width="7" height="1.4" rx=".7" fill="currentColor" data-gecikme="${(i*97%17)/11}s"/>
          <rect x="${x+1.2}" y="${y+1.5}" width="7" height="3" rx=".6" fill="var(--m3)"/>`;}).join("")}</g></g>`;

/* 2 — patch panel + switch + kablo kolu */
const k2 = (id)=>`<g class="gear">
<g>${bez(18,18)}
${Array.from({length:16},(_,i)=>{const x=18+(i%8)*5.5, y=20.5+Math.floor(i/8)*7.5;
  return `<rect x="${x}" y="${y}" width="4.4" height="5.4" rx=".7" fill="var(--m2)"/>
          <rect class="port" x="${x+.9}" y="${y+1.4}" width="2.6" height="1.8" rx=".4" fill="currentColor" data-gecikme="${(i*131%19)/13}s"/>`;}).join("")}</g>
${[39,51].map((y,k)=>`<g>${bez(y,10)}
  ${Array.from({length:10},(_,i)=>`<rect class="port" x="${18+i*4.3}" y="${y+3}" width="3" height="4" rx=".5" fill="currentColor" data-gecikme="${(i*67%23)/15+k*.28}s"/>`).join("")}
  <circle cx="57" cy="${y+5}" r="1.3" fill="currentColor" opacity=".5"/></g>`).join("")}
<rect x="16" y="64" width="44" height="42" rx="1.2" fill="var(--m2)"/>
${[0,1,2,3].map(i=>`<path class="patch" d="M20 ${69+i*9} q 11 6 20 0 t 16 2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".55" data-gecikme="${i*.3}s"/>`).join("")}
</g>`;

/* 3 — blade şasi + omurga hattı */
const k3 = (id)=>`<g class="gear">
<g>${bez(18,8)}${vent(40,20,7)}<circle cx="20" cy="22" r="1.4" fill="currentColor" opacity=".6"/></g>
<rect x="16" y="29" width="44" height="54" rx="1.5" fill="var(--m4)"/><rect x="16" y="29" width="44" height=".9" fill="var(--m5)"/>
<rect x="18" y="32" width="40" height="48" rx="1" fill="var(--m2)"/>
${Array.from({length:7},(_,i)=>{const x=19+i*5.6; return `<rect x="${x}" y="33.5" width="4.4" height="45" rx=".8" fill="var(--m4)"/>
  <rect class="blade" x="${x+1}" y="36" width="2.4" height="1.8" rx=".5" fill="currentColor" data-gecikme="${i*.2}s"/>
  <rect x="${x+1}" y="71" width="2.4" height="4" rx=".6" fill="var(--m3)"/>`;}).join("")}
<line class="bus" x1="18" y1="84.5" x2="58" y2="84.5" stroke="currentColor" stroke-width="1.4" opacity=".7"/>
<g>${bez(88,18)}${vent(21,93,13)}${vent(21,98,13)}</g></g>`;

/* 4 — fan ünitesi + sıcaklık göstergesi */
const fan = (cx,cy,cls)=>`<g class="fan ${cls}">
  <circle cx="${cx}" cy="${cy}" r="8.4" fill="var(--m2)"/>
  ${[0,51,102,153,204,255,306].map(a=>`<path d="M${cx} ${cy} l 7.6 1.7 a 8 8 0 0 1 -5.2 3.9 z" fill="currentColor" opacity=".5" transform="rotate(${a} ${cx} ${cy})"/>`).join("")}
  <circle cx="${cx}" cy="${cy}" r="2.4" fill="var(--m4)" stroke="var(--m5)" stroke-width=".5"/></g>`;
const k4 = (id)=>`<g class="gear">
<g>${bez(18,22)}${fan(28,29,"")}${fan(48,29,"b")}</g>
<g>${bez(43,12)}
  <rect x="19" y="47" width="38" height="4.6" rx="2.3" fill="var(--m2)"/>
  <rect class="heat" x="20" y="48.2" width="36" height="2.2" rx="1.1" fill="currentColor"/>
  <circle cx="57" cy="46" r="1.2" fill="currentColor" opacity=".6"/></g>
${[58,70,82,94].map((y,i)=>`<g>${bez(y,10)}${vent(38,y+3,7)}
  <circle cx="20" cy="${y+5}" r="1.5" fill="currentColor" opacity=".${7-i}"/></g>`).join("")}</g>`;

/* 5 — kampanya dalgası, arkadan geçen ışık */
const k5 = (id)=>`<g class="gear">
<defs><linearGradient id="glow-${id}" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0" stop-color="currentColor" stop-opacity="0"/>
  <stop offset=".5" stop-color="currentColor" stop-opacity=".9"/>
  <stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>
<g clip-path="url(#bay-${id})">
  ${[18,31,44,57,70,83,96].map((y,i)=>`<g>${bez(y,11)}
    ${Array.from({length:5},(_,j)=>`<rect class="queue" x="${19+j*8.4}" y="${y+4}" width="6" height="3" rx=".7" fill="currentColor" data-gecikme="${(i*.18+j*.09).toFixed(2)}s"/>`).join("")}</g>`).join("")}
  <rect class="wave" x="12" y="16" width="52" height="26" fill="url(#glow-${id})"/>
</g></g>`;

/* 6 — konsol, klavye rafı, rapor ekranı */
const k6 = (id)=>`<g class="gear">
<g>${bez(18,30)}
  <rect x="20" y="21" width="36" height="24" rx="1" fill="var(--m1)"/>
  <line x1="21.5" y1="41" x2="54.5" y2="41" stroke="var(--m3)" stroke-width=".9"/>
  ${[26,32,38].map(y=>`<line x1="21.5" y1="${y}" x2="54.5" y2="${y}" stroke="var(--m2)" stroke-width=".6"/>`).join("")}
  <polyline class="trace" points="22,38 28,31 33,34 39,25 45,29 54,23" fill="none" stroke="currentColor"
    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
  <rect class="cursor" x="52" y="41.6" width="2.6" height="1.6" fill="currentColor"/></g>
<g>${bez(51,9)}
  ${Array.from({length:16},(_,i)=>`<rect x="${18.5+i*2.7}" y="53.5" width="1.9" height="1.7" rx=".4" fill="var(--m3)"/>`).join("")}
  <rect x="18.5" y="56" width="14" height="1.7" rx=".4" fill="var(--m3)"/>
  <rect x="34" y="56" width="24" height="1.7" rx=".4" fill="var(--m3)"/></g>
${[63,74,85,96].map(y=>`<g>${bez(y,9)}${vent(38,y+2.6,7)}<circle cx="20" cy="${y+4.5}" r="1.4" fill="var(--m3)"/></g>`).join("")}</g>`;

/* 7 — yönetim kabini: PDU + management switch */
const k7 = (id)=>`<g class="gear">
<rect x="16" y="18" width="8" height="88" rx="1.2" fill="var(--m4)"/>
<rect x="16" y="18" width="8" height=".9" fill="var(--m5)"/>
${Array.from({length:11},(_,i)=>`<rect class="outlet" x="17.6" y="${21+i*7.7}" width="4.8" height="5.4" rx="1" fill="currentColor" data-gecikme="${((10-i)*.15).toFixed(2)}s"/>`).join("")}
<g><rect x="27" y="18" width="33" height="13" rx="1.2" fill="var(--m4)"/><rect x="27" y="18" width="33" height=".9" fill="var(--m5)"/>
  ${Array.from({length:8},(_,i)=>`<rect class="sfp" x="${29+i*3.9}" y="21" width="2.8" height="4.4" rx=".5" fill="currentColor" data-gecikme="${(i*89%17)/11}s"/>`).join("")}
  <rect x="29" y="27" width="28" height="2" rx="1" fill="var(--m3)"/></g>
<g><rect x="27" y="34" width="33" height="11" rx="1.2" fill="var(--m4)"/><rect x="27" y="34" width="33" height=".9" fill="var(--m5)"/>
  <circle cx="31" cy="39.5" r="1.5" fill="currentColor" opacity=".7"/><circle cx="35" cy="39.5" r="1.5" fill="var(--m3)"/>
  ${Array.from({length:6},(_,i)=>`<rect x="${42+i*2.7}" y="37.5" width="1.4" height="4" rx=".5" fill="var(--m3)"/>`).join("")}</g>
<g><rect x="27" y="48" width="33" height="14" rx="1.2" fill="var(--m4)"/><rect x="27" y="48" width="33" height=".9" fill="var(--m5)"/>
  <rect x="29" y="51" width="29" height="4.4" rx="1" fill="var(--m2)"/>
  <rect class="amp" x="29.8" y="52" width="27" height="2.6" rx="1.3" fill="currentColor"/>
  ${Array.from({length:5},(_,i)=>`<rect x="${30+i*6.4}" y="57" width="4.6" height="3" rx=".6" fill="var(--m3)"/>`).join("")}</g>
${[65,77,89].map(y=>`<g><rect x="27" y="${y}" width="33" height="10" rx="1.2" fill="var(--m4)"/><rect x="27" y="${y}" width="33" height=".9" fill="var(--m5)"/>
  ${Array.from({length:9},(_,i)=>`<rect x="${29+i*3.5}" y="${y+3}" width="1.6" height="4" rx=".5" fill="var(--m3)"/>`).join("")}</g>`).join("")}
</g>`;
/* 8 — izleme kabini: LED matris duvarı */
const k8 = (id)=>`<g class="gear">
<g>${bez(18,10)}<circle cx="20" cy="23" r="1.5" fill="currentColor" opacity=".75"/>
  <circle cx="24.5" cy="23" r="1.5" fill="var(--m3)"/>${vent(38,20.5,7)}</g>
<rect x="16" y="31" width="44" height="53" rx="1.5" fill="var(--m4)"/><rect x="16" y="31" width="44" height=".9" fill="var(--m5)"/>
<rect x="18.5" y="33.5" width="39" height="48" rx="1" fill="var(--m1)"/>
${Array.from({length:13},(_,i)=>`<rect class="col" x="${20+i*2.9}" y="36" width="2.3" height="43" rx=".3" fill="currentColor" data-gecikme="${(i*.17).toFixed(2)}s"/>`).join("")}
${Array.from({length:10},(_,j)=>`<rect x="18.5" y="${37.6+j*4.4}" width="39" height="1.5" fill="var(--m1)"/>`).join("")}
${Array.from({length:13},(_,i)=>`<rect x="${22.3+i*2.9}" y="36" width=".6" height="43" fill="var(--m1)"/>`).join("")}
${[87,98].map(y=>`<g>${bez(y,9)}${vent(21,y+2.6,13)}</g>`).join("")}
</g>`;
/* 9 — canlı akış kabini: kayıtlar yukarı doğru kayıyor */
const LOGW = [23,14,30,18,26,11,25,16,28,20,13,24];
const logRows = (off) => LOGW.map((w,i) =>
  `<rect x="21" y="${36 + off + i*4}" width="${w}" height="1.7" rx=".7" fill="currentColor" opacity="${i % 3 ? ".4" : ".85"}"/>`).join("");
const k9 = (id)=>`<g class="gear">
<defs><clipPath id="log-${id}"><rect x="19" y="34" width="38" height="46" rx="1"/></clipPath></defs>
<g>${bez(18,10)}<circle cx="20" cy="23" r="1.5" fill="currentColor" opacity=".8"/>
  <circle cx="24.5" cy="23" r="1.5" fill="var(--m3)"/>${vent(38,20.5,7)}</g>
<rect x="16" y="31" width="44" height="52" rx="1.5" fill="var(--m4)"/><rect x="16" y="31" width="44" height=".9" fill="var(--m5)"/>
<rect x="19" y="34" width="38" height="46" rx="1" fill="var(--m1)"/>
<g clip-path="url(#log-${id})"><g class="feed">${logRows(0)}${logRows(48)}</g></g>
${[86,97].map(y=>`<g>${bez(y,9)}${vent(21,y+2.6,13)}<circle cx="55" cy="${y+4.5}" r="1.3" fill="var(--m3)"/></g>`).join("")}
</g>`;
/* 10 — alarm panosu ve gösterge kabini */
const k10 = (id)=>`<g class="gear">
<g>${bez(18,10)}<circle cx="20" cy="23" r="1.5" fill="currentColor" opacity=".75"/>
  <circle cx="24.5" cy="23" r="1.5" fill="var(--m3)"/>${vent(38,20.5,7)}</g>
<rect x="16" y="31" width="44" height="34" rx="1.5" fill="var(--m4)"/><rect x="16" y="31" width="44" height=".9" fill="var(--m5)"/>
<rect x="18.5" y="33.5" width="39" height="29" rx="1" fill="var(--m1)"/>
${Array.from({length:12},(_,n)=>{const i=n%4, j=Math.floor(n/4);
  return `<circle class="lamp" cx="${23+i*10.4}" cy="${38.5+j*9}" r="3" fill="currentColor" data-gecikme="${(n*97%23)/9}s"/>`;}).join("")}
<g>${bez(68,20)}
  <rect x="19" y="71" width="38" height="14" rx="1" fill="var(--m1)"/>
  <path d="M25 83 A 13 13 0 0 1 51 83" fill="none" stroke="var(--m3)" stroke-width="1.4"/>
  <line class="needle" x1="38" y1="83" x2="38" y2="73" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
  <circle cx="38" cy="83" r="1.7" fill="var(--m5)"/></g>
<g>${bez(91,15)}${vent(21,95,13)}${vent(21,100,13)}</g>
</g>`;
/* 11 — dörtlü kamera ekranı ve kayıt ünitesi */
const k11 = (id)=>`<g class="gear">
<g>${bez(18,10)}<circle cx="20" cy="23" r="1.5" fill="currentColor" opacity=".75"/>
  <circle cx="24.5" cy="23" r="1.5" fill="var(--m3)"/>${vent(38,20.5,7)}</g>
<rect x="16" y="31" width="44" height="40" rx="1.5" fill="var(--m4)"/><rect x="16" y="31" width="44" height=".9" fill="var(--m5)"/>
<rect x="18.5" y="33.5" width="39" height="35" rx="1" fill="var(--m1)"/>
${Array.from({length:4},(_,n)=>{const i=n%2, j=Math.floor(n/2), x=19.5+i*19.5, y=34.5+j*17.5;
  return `<rect class="quad" x="${x}" y="${y}" width="18.5" height="16.5" rx=".8" fill="currentColor" data-gecikme="${(n*.85).toFixed(2)}s"/>
          ${[3,6,9,12].map(o=>`<rect x="${x}" y="${y+o}" width="18.5" height="1.1" fill="var(--m1)" opacity=".8"/>`).join("")}`;}).join("")}
<g>${bez(74,14)}
  <circle class="rec" cx="21.5" cy="81" r="2.2" fill="currentColor"/>
  ${Array.from({length:5},(_,i)=>`<rect x="${27+i*6.2}" y="77.5" width="4.6" height="7" rx=".8" fill="var(--m3)"/>`).join("")}</g>
<g>${bez(91,15)}${vent(21,95,13)}${vent(21,100,13)}</g>
</g>`;

/* 12 — sensör panosu: sıcaklık ve nem sütunları */
const k12 = (id)=>`<g class="gear">
<g>${bez(18,10)}<circle cx="20" cy="23" r="1.5" fill="currentColor" opacity=".7"/>
  <circle cx="24.5" cy="23" r="1.5" fill="var(--m3)"/>${vent(38,20.5,7)}</g>
<rect x="16" y="31" width="44" height="44" rx="1.5" fill="var(--m4)"/><rect x="16" y="31" width="44" height=".9" fill="var(--m5)"/>
<rect x="18.5" y="33.5" width="39" height="39" rx="1" fill="var(--m1)"/>
${[26,36].map((x,k)=>`<rect x="${x}" y="36" width="7" height="34" rx="1.4" fill="var(--m2)"/>
  <rect class="col2 ${k ? "b" : ""}" x="${x+1.2}" y="37.2" width="4.6" height="31.6" rx="1.2" fill="currentColor"/>`).join("")}
${Array.from({length:7},(_,i)=>`<rect x="46" y="${38+i*4.6}" width="8" height="1.4" rx=".7" fill="var(--m3)"/>`).join("")}
${Array.from({length:6},(_,i)=>`<rect x="20.5" y="${39+i*5.4}" width="3.4" height="1.2" rx=".6" fill="var(--m3)"/>`).join("")}
<g>${bez(78,12)}
  ${Array.from({length:3},(_,i)=>`<circle class="probe" cx="${23+i*11}" cy="84" r="2.4" fill="currentColor" data-gecikme="${(i*.6).toFixed(1)}s"/>
     <rect x="${22.2+i*11}" y="79.5" width="1.6" height="3" rx=".7" fill="var(--m3)"/>`).join("")}
  ${vent(46,81,5)}</g>
<g>${bez(93,13)}${vent(21,97,13)}${vent(21,102,13)}</g>
</g>`;
/* 13 — satış: teklif sayacı + ciro kolonları */
const k13 = (id)=>`<g class="gear">
<g>${bez(18,9)}<circle class="tick" cx="20" cy="22.5" r="1.5" fill="currentColor"/>
  <circle cx="24.5" cy="22.5" r="1.5" fill="var(--m3)"/>${vent(38,20,7)}</g>
<g>${bez(30,13)}<rect x="19" y="32.5" width="38" height="8" rx="1" fill="var(--m1)"/>
  ${Array.from({length:6},(_,i)=>`<rect class="digit" x="${20.5+i*6.2}" y="34" width="4.4" height="5" rx=".6" fill="currentColor" data-gecikme="${(i*.28).toFixed(2)}s"/>`).join("")}</g>
<rect x="16" y="46" width="44" height="37" rx="1.5" fill="var(--m4)"/><rect x="16" y="46" width="44" height=".9" fill="var(--m5)"/>
<rect x="18.5" y="48.5" width="39" height="32" rx="1" fill="var(--m1)"/>
<line x1="19.5" y1="77.5" x2="56.5" y2="77.5" stroke="var(--m3)" stroke-width=".9"/>
${Array.from({length:6},(_,i)=>{const h=7+i*3.4; return `<rect class="bar" x="${21+i*6.2}" y="${77-h}" width="4" height="${h}" rx=".8" fill="currentColor" data-gecikme="${(i*.22).toFixed(2)}s"/>`;}).join("")}
<g>${bez(86,9)}${vent(21,89,13)}</g>
<g>${bez(97,9)}${vent(21,100,13)}</g></g>`;
const BUILD = { k1, k2, k3, k4, k5, k6, k7, k8, k9, k10, k11, k12, k13 };

/* ---------- kartları kur ---------- */
/*
 * Salonlar da SUNUCUDAN gelir ve yalnızca DOLU olanlar gönderilir.
 *
 * Liste burada sabitken yeni bir salon açmak bu dosyayı düzenlemek demekti;
 * üstelik kutucuklar yetkiye göre süzüldüğü için bir salon boş kalabiliyor ve
 * geriye yalnız bir başlık kalıyordu.
 */
const HALLS = JSON.parse(document.getElementById("grup-verisi")?.textContent || "[]");
const halls = document.getElementById("halls");
const bins = {};
const sections = {};
HALLS.forEach(h => {
  const sec = document.createElement("section");
  sec.className = "hall";
  /* Başlıksız salon: kendi sayfasında, sayfa başlığı zaten o salonun adı. */
  sec.innerHTML = h.label
    ? `<h2 class="rowlabel"><span></span><em></em></h2><div class="grid"></div>`
    : `<div class="grid"></div>`;

  if (h.label) {
    sec.querySelector("span").textContent = h.label;
    sec.querySelector("em").textContent = h.note ?? "";
  }
  halls.appendChild(sec);
  bins[h.id] = sec.querySelector(".grid");
  sections[h.id] = sec;
});

/*
 * Kutucuğun altındaki adres satırı.
 *
 * Dış sistemlerde ana bilgisayar okunur (crm.ornek.test). İÇ bağlantılarda
 * ise host bu sayfanın kendi adresidir ve hiçbir şey söylemez — orada yol
 * gösterilir (/salon/salon-a).
 */
const hostOf = (u) => {
  if (!u || u === "#") return "adres bekleniyor";

  try {
    const adres = new URL(u, window.location.origin);

    return adres.host === window.location.host ? adres.pathname : adres.host;
  } catch {
    return u;
  }
};

/*
 * Kabin çizimi bilinmiyorsa SAYFA ÖLMEZ.
 *
 * `BUILD[id]` tanımsız kaldığında betik burada patlıyordu ve arkasından gelen
 * her şey — kalan kutucuklar, tema düğmesi, hesap menüsü — hiç kurulmuyordu.
 * Tek bir kaydın eksik alanı bütün sayfayı kilitlememeli; bilinmeyen kabin
 * yerine listedeki ilk çizim kullanılır.
 */
const CIZIMLER = Object.keys(BUILD);
const cizim = (id) => BUILD[id] ?? BUILD[CIZIMLER[0]];

CRMS.forEach((c, i) => {
  const id = BUILD[c.cab] ? c.cab : CIZIMLER[i % CIZIMLER.length];
  const idle = !c.url || c.url === "#";
  const ext = /^https?:/.test(c.url);

  const a = document.createElement("div");
  a.className = "tile" + (idle ? " slot" : "");
  a.style.setProperty("--a", idle ? "var(--slot)" : c.color);
  a.style.animationDelay = (i * 60) + "ms";
  a.innerHTML = `
    <span class="cab ${id}">
      <svg viewBox="0 0 90 124" xmlns="http://www.w3.org/2000/svg">
        ${shell(id)}
        ${cizim(id)(id)}
        ${door(id)}
      </svg>
    </span>
    <span class="body">
      <a class="name"></a>
      <p class="desc"></p>
      <span class="addr"></span>
      <svg class="strip ${c.strip}" viewBox="0 0 200 16" preserveAspectRatio="none">${STRIPS[c.strip]}</svg>
    </span>`;

  /*
   * ANİMASYON GECİKMELERİ CSSOM İLE UYGULANIR.
   *
   * Gecikmeler şablona `style="animation-delay:…"` olarak yazılmaz: içerik
   * CSP'si satır içi stil özniteliklerini engeller ve hiçbiri uygulanmaz
   * (Chromium konsolunda: "Applying inline style violates ... style-src").
   * Buradan yazmak CSP'ye takılmaz.
   */
  a.querySelectorAll("[data-gecikme]").forEach((n) => {
    n.style.animationDelay = n.dataset.gecikme;
  });

  const link = a.querySelector(".name");
  link.textContent = c.name;
  link.href = c.url || "#";
  if (ext) { link.target = "_blank"; link.rel = "noopener"; }   // dış adresler yeni sekmede
  if (idle) link.addEventListener("click", e => e.preventDefault());

  /* Plaka: şablona değil metin düğümüne yazılır (bkz. door). */
  const plaka = a.querySelector(".plate");
  if (plaka) plaka.textContent = c.code ?? "";

  a.querySelector(".desc").textContent = c.desc;
  a.querySelector(".addr").textContent = hostOf(c.url);
  if (c.group === "dis") a.querySelector(".addr").insertAdjacentHTML("beforeend",
    `<svg class="ext" width="9" height="9" viewBox="0 0 10 10"><path d="M3.5 1h5.5v5.5M9 1L1 9"/></svg>`);

  /* karta ikinci bir hedef: küçük yan bağlantı */
  if (c.side) {
    const side = document.createElement("a");
    side.className = "side";
    side.href = c.side.url;
    side.target = "_blank";
    side.rel = "noopener";
    side.innerHTML = `<svg width="9" height="9" viewBox="0 0 10 10"><path d="M3.5 1h5.5v5.5M9 1L1 9"/></svg>`;
    side.prepend(c.side.label);
    a.appendChild(side);
  }

  /*
   * Salonu olmayan kutucuk ÇİZİLMEZ.
   *
   * Sunucu yalnızca çizilecek salonların kutucuklarını gönderir; buraya bir
   * şey düşerse yanlış yerde (ilk salonun altında) göstermektense hiç
   * göstermemek doğru.
   */
  bins[c.group]?.appendChild(a);
});

/*
 * BOŞ SALON GİZLENİR — sunucu zaten göndermiyor, bu son emniyet.
 *
 * Bir kutucuk artık var olmayan bir salonu gösteriyorsa listeye hiç
 * giremez ve salonu boş kalır; başlık tek başına "burada bir şey var ama
 * sana yok" demenin en açık hâli olurdu.
 */
Object.entries(sections).forEach(([id, sec]) => {
  if (!bins[id].children.length) sec.remove();
});

/* ---------- gece ve gündüz ----------
   Seçim ÇEREZDE saklanır ve üst alan adına yazılır (bkz. config/theme.php):
   merkez ile paneller ayrı köklerdir, localStorage kök başına olduğu için
   birinde yapılan seçim diğerinde görünmüyordu. Artık hangi ekranda
   değiştirilirse hepsinde geçerli.

   Hiç seçim yapılmadıysa TARAYICI TERCİHİ geçerlidir ve değişirse anında
   takip edilir; sabit bir varsayılan, cihazını gündüz moduna almış birine
   paneli gece modunda gösteriyordu. */
const root = document.documentElement;
const media = window.matchMedia("(prefers-color-scheme: light)");
const btn = document.getElementById("theme");

const alanAdi = () => document.querySelector('meta[name="tema-alan"]')?.content?.trim() ?? "";
const secim = () => (document.cookie.match(/(?:^|;\s*)dc-tema=(light|dark)(?:;|$)/) ?? [])[1] ?? null;

const remember = (mode) => {
  const alan = alanAdi();
  const guvenli = location.protocol === "https:" ? "; Secure" : "";

  document.cookie = `dc-tema=${mode}; Path=/; Max-Age=${60 * 60 * 24 * 365}; SameSite=Lax${guvenli}`
    + (alan === "" ? "" : `; Domain=${alan}`);
};

const setTheme = (mode) => {
  root.dataset.theme = mode;
  btn.title = mode === "light" ? "Gece moduna geç" : "Gündüz moduna geç";
};

/* Kullanıcı kendi seçimini yaptıysa sistem tercihi onu EZMEZ. */
media.addEventListener("change", e => { if (secim() === null) setTheme(e.matches ? "light" : "dark"); });

btn.addEventListener("click", () => {
  const next = root.dataset.theme === "light" ? "dark" : "light";
  remember(next);
  setTheme(next);
});

setTheme(root.dataset.theme);

const clock = document.getElementById("clock");
const paint = () => clock.textContent = new Date().toLocaleTimeString("tr-TR",
  { hour:"2-digit", minute:"2-digit", second:"2-digit" });
paint(); setInterval(paint, 1000);
