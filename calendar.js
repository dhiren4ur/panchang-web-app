// calendar.js  –  GU / EN / HI  +  quick-access bar  +  hardcoded suvichar

const apiBase = 'api.php';
let currentYear, currentMonth;
let panchangMonthCache = {};
let currentLang = 'gu';

const GUJ_NUM  = ['૦','૧','૨','૩','૪','૫','૬','૭','૮','૯'];
const DEVA_NUM = ['०','१','२','३','४','५','६','७','८','९'];

const PAKSHA_SHORT = {
    gu: { Shukla: 'શુ', Krishna: 'કૃ' },
    en: { Shukla: 'S',  Krishna: 'K'  },
    hi: { Shukla: 'शु', Krishna: 'कृ' }
};
const PAKSHA_FULL = {
    gu: { Shukla: 'શુક્લ',  Krishna: 'કૃષ્ણ'  },
    en: { Shukla: 'Shukla', Krishna: 'Krishna' },
    hi: { Shukla: 'शुक्ल', Krishna: 'कृष्ण'  }
};

/* ══════════════════════════════════════════════════════════════════
   TRANSLATIONS
══════════════════════════════════════════════════════════════════ */
const translations = {
    gu: {
        prevBtn:'← પાછળ', todayBtn:'આજે', nextBtn:'આગળ →',
        weekdays:['રવિ','સોમ','મંગળ','બુધ','ગુરુ','શુક્ર','શનિ'],
        months:['જાન્યુઆરી','ફેબ્રુઆરી','માર્ચ','એપ્રિલ','મે','જૂન','જુલાઈ','ઓગસ્ટ','સપ્ટેમ્બર','ઓક્ટોબર','નવેમ્બર','ડિસેમ્બર'],
        viewDetails:'વિગત જુઓ', addExpense:'ખર્ચ ઉમેરો', addExpenseTitle:'ખર્ચ ઉમેરો',
        modalTitle:'તારીખની વિગત', festival:'ઉત્સવ', tithi:'તિથિ', nakshatra:'નક્ષત્ર',
        yoga:'યોગ', karana:'કરણ', vara:'વાર', sunrise:'સૂર્યોદય', sunset:'સૂર્યાસ્ત',
        rahuKalam:'રાહુ કાળ', gulikaiKalam:'ગુલિકાઈ કાળ', upto:'સુધી', after:'પછી', yamaganda:'યમઘંટ', abhijit:'અભિજિત મુહૂર્ત', sunRashi:'સૂર્ય રાશિ', moonRashi:'ચંદ્ર રાશિ', noData:'પંચાંગ માહિતી ઉપલબ્ધ નથી',
        loginPromptSummary:'કૃપા કરીને Summary જોવા માટે Login કરો.',
        loginPromptCsv:'કૃપા કરીને CSV export માટે Login કરો.',
        loginPromptClear:'કૃપા કરીને ખર્ચ કાઢવા માટે Login કરો.',
        loginPromptAdd:'કૃપા કરીને ખર્ચ ઉમેરવા માટે પહેલા Login કરો.',
        confirmClear:'શું તમને ખાતરી છે? આ માસ નહીં, બધાં જ તમારા ખર્ચ ડિલીટ થશે.',
        clearDone:'તમારા બધા ખર્ચ કાઢી નાખ્યા.', expenseAdded:'ખર્ચ સફળતાપૂર્વક ઉમેરાયો.',
        monthlySummary:'માસિક ખર્ચ',
        choghadiaTitle:'આજ નાં ચોઘડિયાં', choghadiaDay:'દિવસ', choghadiaNight:'રાત',
        qaChoghadia:'ચોઘડિયાં', qaPanchang:'પંચાંગ', qaNakshatra:'નક્ષત્ર',
        qaSuvichar:'સુવિચાર', qaMore:'વધુ જુઓ', appTitle:'ગુજરાતી કૅલેન્ડર',
        suvicharTitle:'સુવિચાર', suvicharRefresh:'નવો વિચાર',
        choghadiaNames:{ Amrit:'અમૃત', Shubh:'શુભ', Labh:'લાભ', Char:'ચર', Udveg:'ઉદ્વેગ', Kaal:'કાળ', Rog:'રોગ' },
        numeralFn: n => String(n).split('').map(d => GUJ_NUM[+d]).join(''),
        tithiMap:{
            'Pratipada':'પ્રતિપદા','Dvitiya':'દ્વિતીયા','Dwitiya':'દ્વિતીયા',
            'Tritiya':'તૃતીયા','Chaturthi':'ચતુર્થી','Panchami':'પંચમી',
            'Shashthi':'ષષ્ઠી','Saptami':'સપ્તમી','Ashtami':'અષ્ટમી',
            'Navami':'નવમી','Dashami':'દશમી','Ekadashi':'એકાદશી',
            'Dvadashi':'દ્વાદશી','Dwadashi':'દ્વાદશી','Trayodashi':'ત્રયોદશી',
            'Chaturdashi':'ચતુર્દશી','Purnima':'પૂર્ણિમા','Amavasya':'અમાવસ્યા',
            'Republic Day':'પ્રજાસત્તાક દિવસ','Independence Day':'સ્વતંત્રતા દિવસ',
            'Gandhi Jayanti':'ગાંધી જયંતી','Makar Sankranti':'મકર સંક્રાંતિ',
            'Holi':'હોળી','Diwali':'દિવાળી','Navratri':'નવરાત્રી','Dussehra':'દશેરા',
            'Janmashtami':'જન્માષ્ટમી','Krishna Janmashtami':'કૃષ્ણ જન્માષ્ટમી',
            'Raksha Bandhan':'રક્ષાબંધન','Ganesh Chaturthi':'ગણેશ ચતુર્થી',
            'Maha Shivaratri':'મહા શિવરાત્રી','Ram Navami':'રામ નવમી',
            'Hanuman Jayanti':'હનુમાન જયંતી','Guru Purnima':'ગુરુ પૂર્ણિમા',
            'Karwa Chauth':'કરવા ચોથ','Dhanteras':'ધનતેરસ','Bhai Dooj':'ભાઈ દૂજ',
            'Christmas':'નાતાલ','Good Friday':'ગુડ ફ્રાઇડે','Easter':'ઇસ્ટર',
            'Baisakhi':'બૈસાખી',"Children's Day":'બાળ દિવસ','Labour Day':'મજૂર દિવસ',
            'Pongal':'પોંગલ','Guru Nanak Jayanti':'ગુરુ નાનક જયંતી',
            'Eid ul-Fitr':'ઇદ ઉલ-ફિત્ર','Eid ul-Adha':'ઇદ ઉલ-અઝહા'
        }
    },
    en: {
        prevBtn:'← Previous', todayBtn:'Today', nextBtn:'Next →',
        weekdays:['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
        months:['January','February','March','April','May','June','July','August','September','October','November','December'],
        viewDetails:'View Details', addExpense:'Add Expense', addExpenseTitle:'Add Expense',
        modalTitle:'Date Details', festival:'Festival', tithi:'Tithi', nakshatra:'Nakshatra',
        yoga:'Yoga', karana:'Karana', vara:'Day', sunrise:'Sunrise', sunset:'Sunset',
        rahuKalam:'Rahu Kalam', gulikaiKalam:'Gulikai Kalam', upto:'upto', after:'after', yamaganda:'Yamaganda', abhijit:'Abhijit Muhurat', sunRashi:'Sun Sign', moonRashi:'Moon Sign', noData:'Panchang data not available',
        loginPromptSummary:'Please login to view summary.',
        loginPromptCsv:'Please login to export CSV.',
        loginPromptClear:'Please login to clear expenses.',
        loginPromptAdd:'Please login to add expenses.',
        confirmClear:'Are you sure? This will delete ALL your expenses.',
        clearDone:'All your expenses have been cleared.', expenseAdded:'Expense added successfully.',
        monthlySummary:'Monthly Summary',
        choghadiaTitle:'Choghadia', choghadiaDay:'Day', choghadiaNight:'Night',
        qaChoghadia:'Choghadia', qaPanchang:'Panchang', qaNakshatra:'Nakshatra',
        qaSuvichar:'Suvichar', qaMore:'More', appTitle:'Gujarati Calendar',
        suvicharTitle:'Thought of the Day', suvicharRefresh:'New Quote',
        choghadiaNames:{ Amrit:'Amrit', Shubh:'Shubh', Labh:'Labh', Char:'Char', Udveg:'Udveg', Kaal:'Kaal', Rog:'Rog' },
        numeralFn: n => String(n),
        tithiMap:{}
    },
    hi: {
        prevBtn:'← पिछला', todayBtn:'आज', nextBtn:'अगला →',
        weekdays:['रवि','सोम','मंगल','बुध','गुरु','शुक्र','शनि'],
        months:['जनवरी','फरवरी','मार्च','अप्रैल','मई','जून','जुलाई','अगस्त','सितंबर','अक्टूबर','नवंबर','दिसंबर'],
        viewDetails:'विवरण देखें', addExpense:'खर्च जोड़ें', addExpenseTitle:'खर्च जोड़ें',
        modalTitle:'तारीख विवरण', festival:'उत्सव', tithi:'तिथि', nakshatra:'नक्षत्र',
        yoga:'योग', karana:'करण', vara:'वार', sunrise:'सूर्योदय', sunset:'सूर्यास्त',
        rahuKalam:'राहु काल', gulikaiKalam:'गुलिकाई काल', upto:'तक', after:'बाद', yamaganda:'यमगण्ड', abhijit:'अभिजित मुहूर्त', sunRashi:'सूर्य राशि', moonRashi:'चंद्र राशि', noData:'पंचांग जानकारी उपलब्ध नहीं',
        loginPromptSummary:'कृपया सारांश देखने के लिए लॉगिन करें।',
        loginPromptCsv:'कृपया CSV निर्यात के लिए लॉगिन करें।',
        loginPromptClear:'कृपया खर्च हटाने के लिए लॉगिन करें।',
        loginPromptAdd:'कृपया खर्च जोड़ने के लिए पहले लॉगिन करें।',
        confirmClear:'क्या आप निश्चित हैं? आपके सभी खर्च हटा दिए जाएंगे।',
        clearDone:'आपके सभी खर्च हटा दिए गए।', expenseAdded:'खर्च सफलतापूर्वक जोड़ा गया।',
        monthlySummary:'मासिक सारांश',
        choghadiaTitle:'आज के चौघड़िया', choghadiaDay:'दिन', choghadiaNight:'रात',
        qaChoghadia:'चौघड़िया', qaPanchang:'पंचांग', qaNakshatra:'नक्षत्र',
        qaSuvichar:'सुविचार', qaMore:'अधिक', appTitle:'गुजराती कैलेंडर',
        suvicharTitle:'सुविचार', suvicharRefresh:'नया विचार',
        choghadiaNames:{ Amrit:'अमृत', Shubh:'शुभ', Labh:'लाभ', Char:'चर', Udveg:'उद्वेग', Kaal:'काल', Rog:'रोग' },
        numeralFn: n => String(n).split('').map(d => DEVA_NUM[+d]).join(''),
        tithiMap:{
            'Pratipada':'प्रतिपदा','Dvitiya':'द्वितीया','Dwitiya':'द्वितीया',
            'Tritiya':'तृतीया','Chaturthi':'चतुर्थी','Panchami':'पंचमी',
            'Shashthi':'षष्ठी','Saptami':'सप्तमी','Ashtami':'अष्टमी',
            'Navami':'नवमी','Dashami':'दशमी','Ekadashi':'एकादशी',
            'Dvadashi':'द्वादशी','Dwadashi':'द्वादशी','Trayodashi':'त्रयोदशी',
            'Chaturdashi':'चतुर्दशी','Purnima':'पूर्णिमा','Amavasya':'अमावस्या',
            'Republic Day':'गणतंत्र दिवस','Independence Day':'स्वतंत्रता दिवस',
            'Gandhi Jayanti':'गांधी जयंती','Makar Sankranti':'मकर संक्रांति',
            'Holi':'होली','Diwali':'दीवाली','Navratri':'नवरात्री','Dussehra':'दशहरा',
            'Janmashtami':'जन्माष्टमी','Krishna Janmashtami':'कृष्ण जन्माष्टमी',
            'Raksha Bandhan':'रक्षाबंधन','Ganesh Chaturthi':'गणेश चतुर्थी',
            'Maha Shivaratri':'महा शिवरात्रि','Ram Navami':'राम नवमी',
            'Hanuman Jayanti':'हनुमान जयंती','Guru Purnima':'गुरु पूर्णिमा',
            'Karwa Chauth':'करवा चौथ','Dhanteras':'धनतेरस','Bhai Dooj':'भाई दूज',
            'Christmas':'क्रिसमस','Good Friday':'गुड फ्राइडे','Easter':'ईस्टर',
            'Baisakhi':'बैसाखी',"Children's Day":'बाल दिवस','Labour Day':'मजदूर दिवस',
            'Pongal':'पोंगल','Guru Nanak Jayanti':'गुरु नानक जयंती',
            'Eid ul-Fitr':'ईद उल-फित्र','Eid ul-Adha':'ईद उल-अज़हा'
        }
    }
};

/* ── Helpers ── */
function toNumeral(num) { return translations[currentLang].numeralFn(num); }
function translateText(text) {
    if (!text) return text;
    if (currentLang === 'en') return text;
    return translations[currentLang].tithiMap[text] || text;
}
function pakshaShortLabel(p) { return (PAKSHA_SHORT[currentLang]||PAKSHA_SHORT.en)[p]||p.charAt(0); }
function pakshaFullLabel(p)  { return (PAKSHA_FULL[currentLang] ||PAKSHA_FULL.en)[p] ||p; }
function updateLangToggleLabel() {
    const next = { gu:'EN', en:'HI', hi:'ગુ' };
    document.getElementById('lang-label').textContent = next[currentLang]||'EN';
}
function applyBodyTheme() {
    document.body.classList.remove('lang-en','lang-hi');
    if (currentLang==='en') document.body.classList.add('lang-en');
    if (currentLang==='hi') document.body.classList.add('lang-hi');
}
function pad2(n) { return String(n).padStart(2,'0'); }
function formatDate(d) { return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; }

// RASHI FORECAST BAR
let rashiForecast = null;

async function loadRashiForecast() {
    try {
        const res = await fetch('horoscope_api.php');
        const data = await res.json();
        rashiForecast = data.forecasts;
        renderRashiBar();
    } catch (e) {
        console.log('Horoscope API failed:', e);
    }
}

function renderRashiBar() {
    if (!rashiForecast) return;

    const scroll = document.getElementById('rfb-scroll');
    if (!scroll) return;

    scroll.innerHTML = Object.keys(rashiForecast).map(rashiKey => {
        const f = rashiForecast[rashiKey];
        const stars =
            '<span class="rfb-star">★</span>'.repeat(f.score || 0) +
            '<span class="rfb-star-off">☆</span>'.repeat(4 - (f.score || 0));

        const cb = currentLang === 'gu'
            ? (f.chandra_bala_gu || f.chandra_bala || '')
            : (f.chandra_bala || f.chandra_bala_gu || '');

        const name = currentLang === 'gu'
            ? (f.rashi_gu || f.rashi || rashiKey)
            : (f.rashi || rashiKey);

        return `
            <div class="rfb-card" onclick="window.location.href='horoscope.html'">
                <div class="rfb-sym">
                    <img src="rashi/${rashiKey.toLowerCase()}.png" alt="${name}" class="rfb-sym-img">
                </div>
                <div class="rfb-name">${name}</div>
                <div class="rfb-stars">${stars}</div>
                <div class="rfb-cb">${cb}</div>
            </div>
        `;
    }).join('');
}

function toggleRashiBar() {
    const bar = document.getElementById('rashi-forecast-bar');
    bar.classList.toggle('show');
}

// Auto-load on page init
document.addEventListener('DOMContentLoaded', () => {
    loadRashiForecast();
});

// ── SHARE HOROSCOPE ──────────────────────────────────────────────────────────
function shareHoroscope() {
    // Build URL dynamically — works on any domain
    const base = window.location.origin + window.location.pathname.replace(/[^/]*$/, '');
    const siteUrl = base + 'horoscope.html';

    // If data not loaded yet, load it first then retry
    if (!rashiForecast) {
        loadRashiForecast().then(() => {
            setTimeout(shareHoroscope, 800);
        });
        return;
    }

    // Rashi name + stars + chandra bala — all 12 in one line with || separator
    const RASHI_ORDER = ['Mesh','Vrishabha','Mithuna','Karka','Simha','Kanya',
                         'Tula','Vrishchika','Dhanu','Makara','Kumbha','Meena'];

    const rashiParts = RASHI_ORDER.map(key => {
        const f = rashiForecast[key];
        if (!f) return null;
        const name = currentLang === 'gu'
            ? (f.rashi_gu || f.rashi || key)
            : (f.rashi || key);
        const stars = '★'.repeat(f.score || 0) + '☆'.repeat(4 - (f.score || 0));
        const cb = currentLang === 'gu'
            ? (f.chandra_bala_gu || f.chandra_bala || '')
            : (f.chandra_bala || f.chandra_bala_gu || '');
        return `${name} ${stars} ${cb}`;
    }).filter(Boolean);

    // Date string
    const today = new Date();
    const dateStr = today.toLocaleDateString('en-GB', { day:'numeric', month:'long', year:'numeric' });

    // Samvat line from page
    const samvatEl = document.getElementById('samvat-subtitle');
    const samvatText = samvatEl ? samvatEl.textContent.trim() : '';

    const shareText =
`🔮 આજનું રાશિ ભવિષ્ય — ${dateStr}
${samvatText}

${rashiParts.join(' || ')}

🌐 ${siteUrl}`;

    if (navigator.share) {
        navigator.share({
            title: 'આજનું રાશિ ભવિષ્ય',
            text: shareText
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(shareText)
            .then(() => alert('✅ કૉપિ થઈ ગયો! WhatsApp / Facebook પર પેસ્ટ કરો.'))
            .catch(() => {
                window.open('mailto:?subject=આજનું રાશિ ભવિષ્ય&body=' + encodeURIComponent(shareText));
            });
    }
}


/* ══════════════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('calendarLang');
    if (saved && translations[saved]) currentLang = saved;
    updateLangToggleLabel();
    applyBodyTheme();

    const today = new Date();
    currentYear  = today.getFullYear();
    currentMonth = today.getMonth() + 1;

    // prev-month / next-month use onclick in HTML — no addEventListener needed
    // Tap month title to go back to today
    document.getElementById('month-title').addEventListener('click', () => {
        const now = new Date();
        currentYear = now.getFullYear(); currentMonth = now.getMonth() + 1;
        loadMonth(currentYear, currentMonth, now);
    });
    document.getElementById('lang-toggle').addEventListener('click', () => {
        const cycle = { gu:'en', en:'hi', hi:'gu' };
        currentLang = cycle[currentLang]||'gu';
        updateLangToggleLabel(); applyBodyTheme();
        localStorage.setItem('calendarLang', currentLang);
        updateGuestNotice(); updateQaLabels();
        loadMonth(currentYear, currentMonth);
    });

    setupContextMenu();
    document.getElementById('summary-btn').addEventListener('click', openSummaryModal);
    document.getElementById('export-btn').addEventListener('click', exportMonthCsv);
    document.getElementById('clear-expenses-btn').addEventListener('click', clearAllExpenses);
    setupModal(); setupQuickAccess(); updateQaLabels();
    loadWallpaperPreview();
    loadMonth(currentYear, currentMonth, today);
    checkSession();
});

/* ══════════════════════════════════════════════════════════════════
   MONTH + GRID
══════════════════════════════════════════════════════════════════ */
function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth < 1)  { currentMonth = 12; currentYear--; }
    if (currentMonth > 12) { currentMonth =  1; currentYear++; }
    loadMonth(currentYear, currentMonth);
}

function loadMonth(year, month, selectedDate = null) {
    const t = translations[currentLang];
    document.getElementById('month-title').textContent = `${t.months[month-1]} ${toNumeral(year)}`;
    updateSamvatSubtitle(year, month);
    // arrows have no text — just update lunar months label
    updateLunarMonthsLabel(year, month);
    const wh = document.getElementById('weekday-header');
    wh.innerHTML = '';
    t.weekdays.forEach(day => { const d = document.createElement('div'); d.textContent = day; wh.appendChild(d); });
    const items = document.querySelectorAll('.context-menu-item');
    items[0].textContent = t.viewDetails; items[1].textContent = t.addExpense;

    fetch(`${apiBase}?action=panchang_month&year=${year}&month=${month}`)
        .then(r => r.json())
        .then(json => {
            if (!json.success) { renderCalendarGrid(year, month, {}); return; }
            panchangMonthCache = json.data.days || {};
            const mf = json.data.festivals || {};
            Object.keys(mf).forEach(d => {
                if (!panchangMonthCache[d]) panchangMonthCache[d] = {};
                panchangMonthCache[d].festivals = mf[d].map(f => f.name);
            });
            renderCalendarGrid(year, month, panchangMonthCache, selectedDate);
        })
        .catch(() => renderCalendarGrid(year, month, {}));
}

function updateSamvatSubtitle(year, month) {
    const el = document.getElementById('samvat-subtitle');
    if (!el) return;
    const samvat = month <= 3 ? year + 56 : year + 57;
    const guM = ['મહા','ફાગણ','ચૈત્ર','વૈશાખ','જ્યેષ્ઠ','અષાઢ','શ્રાવણ','ભાદ્રપદ','આસો','કારતક','માગશર','પોષ'];
    const enM = ['Maha','Phalguna','Chaitra','Vaishakha','Jyeshtha','Ashadha','Shravana','Bhadrapada','Ashwin','Kartika','Margashirsha','Pausha'];
    const hiM = ['माघ','फाल्गुन','चैत्र','वैशाख','ज्येष्ठ','आषाढ','श्रावण','भाद्रपद','आश्विन','कार्तिक','मार्गशीर्ष','पौष'];
    const labels = {
        gu: `સંવત ${toNumeral(samvat)} · ${guM[month-1]}`,
        en: `Samvat ${samvat} · ${enM[month-1]}`,
        hi: `संवत ${toNumeral(samvat)} · ${hiM[month-1]}`
    };
    el.textContent = labels[currentLang]||labels.en;
}

function goToToday() {
    const now = new Date();
    currentYear = now.getFullYear();
    currentMonth = now.getMonth() + 1;
    loadMonth(currentYear, currentMonth, now);
}

function updateLunarMonthsRow(month) {
    const el = document.getElementById('lunar-months-row');
    if (!el) return;
    const gu = [
        ['મહા','ફાગણ'],['ફાગણ','ચૈત્ર'],['ચૈત્ર','વૈશાખ'],
        ['વૈશાખ','જ્યેષ્ઠ'],['જ્યેષ્ઠ','અષાઢ'],['અષાઢ','શ્રાવણ'],
        ['શ્રાવણ','ભાદ્રપદ'],['ભાદ્રપદ','આસો'],['આસો','કારતક'],
        ['કારતક','માગશર'],['માગશર','પોષ'],['પોષ','મહા']
    ];
    const en = [
        ['Maha','Phalguna'],['Phalguna','Chaitra'],['Chaitra','Vaishakha'],
        ['Vaishakha','Jyeshtha'],['Jyeshtha','Ashadha'],['Ashadha','Shravana'],
        ['Shravana','Bhadrapada'],['Bhadrapada','Ashwin'],['Ashwin','Kartika'],
        ['Kartika','Margashirsha'],['Margashirsha','Pausha'],['Pausha','Maha']
    ];
    const hi = [
        ['माघ','फाल्गुन'],['फाल्गुन','चैत्र'],['चैत्र','वैशाख'],
        ['वैशाख','ज्येष्ठ'],['ज्येष्ठ','आषाढ'],['आषाढ','श्रावण'],
        ['श्रावण','भाद्रपद'],['भाद्रपद','आश्विन'],['आश्विन','कार्तिक'],
        ['कार्तिक','मार्गशीर्ष'],['मार्गशीर्ष','पौष'],['पौष','माघ']
    ];
    const map = {gu, en, hi};
    const pair = (map[currentLang] || gu)[month - 1];
    el.textContent = pair[0] + ' · ' + pair[1];
}

function updateLunarMonthsLabel(year, month) {
    const el = document.getElementById('lunar-months-label');
    if (!el) return;
    const gu = [
        'મહા · ફાગણ','ફાગણ · ચૈત્ર','ચૈત્ર · વૈશાખ','વૈશાખ · જ્યેષ્ઠ',
        'જ્યેષ્ઠ · અષાઢ','અષાઢ · શ્રાવણ','શ્રાવણ · ભાદ્રપદ','ભાદ્રપદ · આસો',
        'આસો · કારતક','કારતક · માગશર','માગશર · પોષ','પોષ · મહા'
    ];
    const en = [
        'Maha · Phalguna','Phalguna · Chaitra','Chaitra · Vaishakha','Vaishakha · Jyeshtha',
        'Jyeshtha · Ashadha','Ashadha · Shravana','Shravana · Bhadrapada','Bhadrapada · Ashwin',
        'Ashwin · Kartika','Kartika · Margashirsha','Margashirsha · Pausha','Pausha · Maha'
    ];
    const hi = [
        'माघ · फाल्गुन','फाल्गुन · चैत्र','चैत्र · वैशाख','वैशाख · ज्येष्ठ',
        'ज्येष्ठ · आषाढ','आषाढ · श्रावण','श्रावण · भाद्रपद','भाद्रपद · आश्विन',
        'आश्विन · कार्तिक','कार्तिक · मार्गशीर्ष','मार्गशीर्ष · पौष','पौष · माघ'
    ];
    const map = { gu, en, hi };
    el.textContent = (map[currentLang] || gu)[month - 1];
}

function renderCalendarGrid(year, month, panchangDays) {
    const grid = document.getElementById('date-grid');
    grid.innerHTML = '';
    const first = new Date(year, month-1, 1);
    const startingDay = first.getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const todayStr = formatDate(new Date());
    let dayNumber = 1;
    for (let i = 0; i < 42; i++) {
        const cell = document.createElement('div');
        cell.className = 'date-cell';
        if (i >= startingDay && dayNumber <= daysInMonth) {
            const d = dayNumber;
            const dateStr = `${year}-${pad2(month)}-${pad2(d)}`;
            cell.dataset.date = dateStr;
            const inner = document.createElement('div'); inner.className = 'date-cell-inner';
            const dateNum = document.createElement('div'); dateNum.className = 'date-number';
            dateNum.textContent = toNumeral(d); inner.appendChild(dateNum);
            const dayData = panchangDays[dateStr];
            if (dayData) {
                const tithiNum = dayData.tithi?.number || dayData.tithi?.index || 0;
                const tithiName = (dayData.tithi?.name || '').toLowerCase();
                if (tithiName === 'purnima') cell.classList.add('cell-purnima');
                else if (tithiName === 'amavasya') cell.classList.add('cell-amavasya');
                if (tithiNum !== 0) {
                    const tithiEl = document.createElement('div'); tithiEl.className = 'guj-tithi';
                    let tithiText = pakshaShortLabel(dayData.tithi?.paksha||'') + toNumeral(tithiNum);
                    // Option B: show two tithis in cell if transition happens today
                    if (dayData.two_tithis && dayData.tithi_next) {
                        const nextNum = dayData.tithis ? dayData.tithis[1]?.number : null;
                        const nextPaksha = dayData.tithi_next_paksha || '';
                        // Calculate next tithi number from name
                        const tithiNames = ['Pratipada','Dvitiya','Tritiya','Chaturthi','Panchami',
                            'Shashthi','Saptami','Ashtami','Navami','Dashami','Ekadashi','Dvadashi',
                            'Trayodashi','Chaturdashi','Purnima','Pratipada','Dvitiya','Tritiya',
                            'Chaturthi','Panchami','Shashthi','Saptami','Ashtami','Navami','Dashami',
                            'Ekadashi','Dvadashi','Trayodashi','Chaturdashi','Amavasya'];
                        const nextIdx = tithiNames.indexOf(dayData.tithi_next);
                        const nextTithiNum = nextIdx >= 0 ? (nextIdx % 15) + 1 : 0;
                        if (nextTithiNum) {
                            tithiText += '/' + pakshaShortLabel(nextPaksha) + toNumeral(nextTithiNum);
                        }
                    }
                    tithiEl.textContent = tithiText;
                    inner.appendChild(tithiEl);
                }
                if (dayData.festivals && dayData.festivals.length > 0) {
                    cell.classList.add('cell-festival');
                    const festEl = document.createElement('div'); festEl.className = 'festival-name';
                    festEl.textContent = translateText(dayData.festivals[0]); inner.appendChild(festEl);
                    const ind = document.createElement('div'); ind.className = 'festival-indicator'; inner.appendChild(ind);
                }
            }
            cell.appendChild(inner);
            if (dateStr === todayStr) cell.classList.add('today');
            cell.addEventListener('click', e => { if (!e.ctrlKey && !e.metaKey) showDetailModal(dateStr); });
            dayNumber++;
        }
        grid.appendChild(cell);
    }
}

/* ══════════════════════════════════════════════════════════════════
   DETAIL MODAL
══════════════════════════════════════════════════════════════════ */
function showDetailModal(dateStr) {
    const t = translations[currentLang];
    const modal = document.getElementById('detail-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const data = panchangMonthCache[dateStr];
    const d = new Date(dateStr);

    modalTitle.textContent = `${toNumeral(d.getDate())} ${t.months[d.getMonth()]}, ${toNumeral(d.getFullYear())}`;

    let html = '';
    let details = [];

    if (data) {
        if (data.festivals && data.festivals.length > 0) {
            html += `<div class="detail-row"><div class="detail-label">${t.festival}:</div>
                <div class="detail-value">${data.festivals.map(f => translateText(f)).join(', ')}</div></div>`;
        }

        const tithiRaw = data.tithi?.name || '';
        const paksha = data.tithi?.paksha || '';

        let tithiDisplay = paksha ? `${pakshaFullLabel(paksha)} – ${translateText(tithiRaw)}` : translateText(tithiRaw);
        if (data.two_tithis && data.tithi_end_time) {
            const nextPakshaLabel = data.tithi_next_paksha ? pakshaFullLabel(data.tithi_next_paksha) + ' – ' : '';
            const nextTithiLabel = currentLang === 'gu'
                ? (data.tithi_next_gu || data.tithi_next || '')
                : (data.tithi_next || '');
            tithiDisplay += ` (${t.upto} ${data.tithi_end_time})`;
            tithiDisplay += `\n${nextPakshaLabel}${nextTithiLabel} (${t.after} ${data.tithi_end_time})`;
        }

        let nakDisplay = data.nakshatra_gu || data.nakshatra?.gu || data.nakshatra?.name || '';
        if (data.two_nakshatras && data.nakshatra_end_time) {
            const nextNakLabel = currentLang === 'gu'
                ? (data.nakshatra_next_gu || data.nakshatra_next || '')
                : (data.nakshatra_next || '');
            nakDisplay += ` (${t.upto} ${data.nakshatra_end_time})`;
            nakDisplay += `\n${nextNakLabel} (${t.after} ${data.nakshatra_end_time})`;
        }

        const yogaDisplay = data.yoga_gu || data.yoga?.gu || data.yoga?.name || data.yoga || '';
        const karDisplay = data.karana_gu || data.karana?.gu || data.karana?.name || data.karana || '';
        const sunRashi = data.sun_rashi_gu || data.sun_rashi || '';
        const moonRashi = data.moon_rashi_gu || data.moon_rashi || '';
        const gulikaiStr = data.gulikai_kalam ? `${data.gulikai_kalam.start || ''} – ${data.gulikai_kalam.end || ''}` : '';
        const yamStr = data.yamaganda ? `${data.yamaganda.start || ''} – ${data.yamaganda.end || ''}` : '';
        const abhijitStr = data.abhijit ? `${data.abhijit.start || ''} – ${data.abhijit.end || ''}` : '';

        details = [
            { label: t.tithi, value: tithiDisplay },
            { label: t.nakshatra, value: nakDisplay },
            { label: t.yoga, value: yogaDisplay },
            { label: t.karana, value: karDisplay },
            { label: t.vara, value: data.vara_gu || data.weekday?.name || '' },
            { label: t.sunRashi, value: sunRashi },
            { label: t.moonRashi, value: moonRashi },
            { label: t.sunrise, value: data.sunrise },
            { label: t.sunset, value: data.sunset },
            { label: t.rahuKalam, value: data.rahu_kalam ? `${data.rahu_kalam.start || ''} – ${data.rahu_kalam.end || ''}` : '' },
            { label: t.gulikaiKalam, value: gulikaiStr },
            { label: t.yamaganda, value: yamStr },
            { label: t.abhijit, value: abhijitStr }
        ];

        details.forEach(item => {
            if (item.value) {
                const lines = String(item.value).split('\n');
                const valueHtml = lines.map((line, i) =>
                    i === 0
                        ? `<span>${translateText(line)}</span>`
                        : `<span style="display:block;font-size:12px;color:var(--primary);margin-top:2px">${translateText(line)}</span>`
                ).join('');

                html += `<div class="detail-row"><div class="detail-label">${item.label}:</div>
                    <div class="detail-value">${valueHtml}</div></div>`;
            }
        });

        html += `<div class="detail-row choghadia-toggle-row" id="choghadia-toggle-row"
            style="cursor:pointer;border-top:1.5px solid var(--detail-border);margin-top:6px;padding-top:8px;">
            <div class="detail-label" style="color:var(--primary);font-weight:700;">${t.choghadiaTitle}</div>
            <div class="detail-value" style="color:var(--primary);">▾ ${t.choghadiaDay}</div>
        </div><div id="choghadia-panel" style="display:none;margin-top:4px;"></div>`;
    } else {
        html = `<div class="detail-row"><div class="detail-value">${t.noData}</div></div>`;
    }

    const shareLines = [modalTitle.textContent];

    if (data?.festivals && data.festivals.length > 0) {
        shareLines.push(`${t.festival}: ${data.festivals.map(f => translateText(f)).join(', ')}`);
    }

    details.forEach(item => {
        if (item.value) {
            const cleanValue = String(item.value)
                .replace(/<br\s*\/?>/gi, ' | ')
                .replace(/\n+/g, ' | ');
            shareLines.push(`${item.label}: ${cleanValue}`);
        }
    });

    const appLink = window.location.origin + window.location.pathname;
    shareLines.push('');
    shareLines.push(`Click for more: ${appLink}`);

    const shareText = shareLines.join('\n');

    modalBody.innerHTML = html;

    const shareBtn = document.createElement('button');
    shareBtn.className = 'nav-btn';
    shareBtn.style.width = '100%';
    shareBtn.style.marginTop = '12px';
    shareBtn.textContent =
        currentLang === 'gu' ? 'શેર કરો' :
        currentLang === 'hi' ? 'शेयर करें' :
        'Share';

    shareBtn.addEventListener('click', async () => {
        try {
            if (navigator.share) {
                await navigator.share({
                    title: modalTitle.textContent,
                    text: shareText
                });
            } else {
                await navigator.clipboard.writeText(shareText);
                alert(
                    currentLang === 'gu' ? 'માહિતી કૉપી થઈ ગઈ.' :
                    currentLang === 'hi' ? 'जानकारी कॉपी हो गई.' :
                    'Details copied to clipboard.'
                );
            }
        } catch (err) {
            console.log('Share cancelled or failed', err);
        }
    });

    modalBody.appendChild(shareBtn);

    modal.style.display = 'flex';
    choghadiaOpen = false;

    const toggleRow = document.getElementById('choghadia-toggle-row');
    if (toggleRow) toggleRow.addEventListener('click', () => toggleChoghadiaPanel(dateStr));
}

/* ══════════════════════════════════════════════════════════════════
   SUMMARY / CSV / CLEAR
══════════════════════════════════════════════════════════════════ */
async function openSummaryModal() {
    if (!window.isLoggedIn) { alert(translations[currentLang].loginPromptSummary); return; }
    const modal = document.getElementById('summary-modal');
    const title = document.getElementById('summary-modal-title');
    const tbody = document.querySelector('#summary-table tbody');
    const grandEl = document.getElementById('summary-grand-total');
    const err = document.getElementById('summary-error');
    title.textContent = translations[currentLang].monthlySummary;
    err.textContent = ''; tbody.innerHTML = ''; grandEl.textContent = '0';
    try {
        const res = await fetch(`${apiBase}?action=month_summary&year=${currentYear}&month=${currentMonth}`,{credentials:'include'});
        const data = await res.json();
        if (!data.success) { err.textContent = data.message||'Failed.'; }
        else {
            let grand = 0;
            (data.data.rows||[]).forEach(r => {
                const tr=document.createElement('tr');
                const tdC=document.createElement('td'); tdC.textContent=r.category; tdC.style.cssText='padding:6px;border-bottom:1px solid #f0e0c8';
                const tdT=document.createElement('td'); tdT.textContent=r.total.toFixed(2); tdT.style.cssText='text-align:right;padding:6px;border-bottom:1px solid #f0e0c8';
                tr.appendChild(tdC); tr.appendChild(tdT); tbody.appendChild(tr);
                grand += parseFloat(r.total);
            });
            grandEl.textContent = grand.toFixed(2);
        }
    } catch(e) { err.textContent = 'Network error.'; }
    modal.style.display = 'flex';
}

function exportMonthCsv() {
    if (!window.isLoggedIn) { alert(translations[currentLang].loginPromptCsv); return; }
    const a = document.createElement('a');
    a.href = `${apiBase}?action=month_csv&year=${currentYear}&month=${currentMonth}`;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

async function clearAllExpenses() {
    const t = translations[currentLang];
    if (!window.isLoggedIn) { alert(t.loginPromptClear); return; }
    if (!confirm(t.confirmClear)) return;
    try {
        const fd = new URLSearchParams(); fd.append('action','clear_expenses');
        const res = await fetch(apiBase,{method:'POST',credentials:'include',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:fd.toString()});
        const data = await res.json();
        alert(data.success ? t.clearDone : (data.message||'Failed.'));
        if (data.success) loadMonth(currentYear, currentMonth);
    } catch(e) { alert('Network error.'); }
}

function placeMenu(cx, cy) {
    const menu = document.getElementById('context-menu');
    menu.style.display = 'block';
    const mw = menu.offsetWidth  || 160;
    const mh = menu.offsetHeight || 80;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    let left = cx + 6;
    let top  = cy + 6;
    if (left + mw > vw - 8) left = cx - mw - 6;
    if (top  + mh > vh - 8) top  = cy - mh - 6;
    menu.style.left = Math.max(8, left) + 'px';
    menu.style.top  = Math.max(8, top)  + 'px';
}

/* ══════════════════════════════════════════════════════════════════
   CONTEXT MENU
══════════════════════════════════════════════════════════════════ */
function setupContextMenu() {
    const menu = document.getElementById('context-menu');
    let currentDateForMenu = null;
    document.addEventListener('contextmenu', e => {
        const cell = e.target.closest('.date-cell');
        if (!cell || !cell.dataset.date) return;
        e.preventDefault(); currentDateForMenu = cell.dataset.date;
        placeMenu(e.clientX, e.clientY);
    });
    let pressTimer = null;
    document.addEventListener('touchstart', e => {
        const cell = e.target.closest('.date-cell');
        if (!cell || !cell.dataset.date) return;
        // Snapshot coordinates at touchstart — they're gone by touchend
        const tx = e.touches[0].clientX;
        const ty = e.touches[0].clientY;
        pressTimer = setTimeout(() => {
            currentDateForMenu = cell.dataset.date;
            placeMenu(tx, ty);
        }, 500);
    }, {passive:true});
    document.addEventListener('touchmove', () => clearTimeout(pressTimer), {passive:true});
    document.addEventListener('touchend', () => clearTimeout(pressTimer));
    menu.addEventListener('click', e => {
        const action = e.target.dataset.action; if (!action) return;
        if (action==='view' && currentDateForMenu) showDetailModal(currentDateForMenu);
        else if (action==='add') {
            if (!window.isLoggedIn) { alert(translations[currentLang].loginPromptAdd); menu.style.display='none'; return; }
            if (currentDateForMenu) openExpenseModal(currentDateForMenu);
        }
        menu.style.display='none';
    });
    document.addEventListener('click', e => { if (!menu.contains(e.target)) menu.style.display='none'; });
}

/* ══════════════════════════════════════════════════════════════════
   MODALS SETUP
══════════════════════════════════════════════════════════════════ */
function setupModal() {
    const modal = document.getElementById('detail-modal');
    document.getElementById('modal-close').addEventListener('click', () => { modal.style.display='none'; });
    modal.addEventListener('click', e => { if (e.target===modal) modal.style.display='none'; });
    const expModal = document.getElementById('expense-modal');
    document.getElementById('expense-modal-close').addEventListener('click', closeExpenseModal);
    expModal.addEventListener('click', e => { if (e.target===expModal) closeExpenseModal(); });
    const sumModal = document.getElementById('summary-modal');
    document.getElementById('summary-modal-close').addEventListener('click', () => { sumModal.style.display='none'; });
    sumModal.addEventListener('click', e => { if (e.target===sumModal) sumModal.style.display='none'; });
    document.getElementById('expense-form').addEventListener('submit', async e => {
        e.preventDefault();
        const err = document.getElementById('expense-error'); err.textContent = '';
        if (!currentExpenseDate) { err.textContent = 'Invalid date.'; return; }
        const catId = document.getElementById('expense-category').value;
        const amt   = document.getElementById('expense-amount').value;
        const note  = document.getElementById('expense-note').value;
        if (!catId || !amt || Number(amt) <= 0) { err.textContent = 'Please enter valid amount and category.'; return; }
        try {
            const fd = new URLSearchParams();
            fd.append('action','add_expense'); fd.append('date',currentExpenseDate);
            fd.append('category_id',catId); fd.append('amount',amt); fd.append('note',note);
            const res = await fetch(apiBase,{method:'POST',credentials:'include',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:fd.toString()});
            const data = await res.json();
            if (!data.success) { err.textContent = data.message||'Failed.'; return; }
            closeExpenseModal(); alert(translations[currentLang].expenseAdded);
        } catch(e2) { err.textContent = 'Network error.'; }
    });
}

let currentExpenseDate = null;
function openExpenseModal(dateStr) {
    currentExpenseDate = dateStr;
    const t = translations[currentLang]; const d = new Date(dateStr);
    document.getElementById('expense-modal-title').textContent = t.addExpenseTitle;
    document.getElementById('expense-date-display').textContent = `${toNumeral(d.getDate())} ${t.months[d.getMonth()]} ${toNumeral(d.getFullYear())}`;
    document.getElementById('expense-amount').value = '';
    document.getElementById('expense-note').value = '';
    document.getElementById('expense-error').textContent = '';
    document.getElementById('expense-modal').style.display = 'flex';
}
function closeExpenseModal() { document.getElementById('expense-modal').style.display = 'none'; }

/* ══════════════════════════════════════════════════════════════════
   SESSION / AUTH
══════════════════════════════════════════════════════════════════ */
async function checkSession() {
    try {
        const res = await fetch(`${apiBase}?action=session`,{credentials:'include'});
        const data = await res.json();
        const loggedIn = !!(data && data.data && data.data.loggedIn);
        window.isLoggedIn = loggedIn;
        const lb = document.getElementById('loginBtn'); const lo = document.getElementById('logoutBtn');
        if (lb && lo) { lb.style.display = loggedIn?'none':'inline-block'; lo.style.display = loggedIn?'inline-block':'none'; }
        const bar = document.getElementById('summary-controls-bar');
        const gn  = document.getElementById('guest-notice');
        if (bar && gn) { bar.style.display = loggedIn?'flex':'none'; gn.style.display = loggedIn?'none':'flex'; }
        updateGuestNotice();
    } catch(e) { window.isLoggedIn = false; }
}

function updateGuestNotice() {
    const nt = document.getElementById('guest-notice-text');
    const nl = document.getElementById('guest-login-link');
    if (!nt || !nl) return;
    const msgs = {
        gu:{ text:'ખર્ચ ટ્રૅક કરવા માંગો છો?', link:'Login / Register' },
        en:{ text:'Want to track expenses?',     link:'Login / Register' },
        hi:{ text:'खर्च ट्रैक करना चाहते हैं?', link:'लॉगिन / रजिस्टर' }
    };
    const m = msgs[currentLang]||msgs.en; nt.textContent = m.text; nl.textContent = m.link;
}

document.getElementById('loginBtn').addEventListener('click', () => { window.location.href = 'login.html'; });
document.getElementById('logoutBtn').addEventListener('click', async () => {
    try {
        await fetch(apiBase,{method:'POST',credentials:'include',body:new URLSearchParams({action:'logout'})});
        window.isLoggedIn = false; await checkSession();
    } catch(e) { location.reload(); }
});
// checkSession() is called inside DOMContentLoaded — no need to call it again here

/* ══════════════════════════════════════════════════════════════════
   QUICK ACCESS BAR
══════════════════════════════════════════════════════════════════ */
function setupQuickAccess() {
    const qaChoghadia = document.getElementById('qa-choghadia');
    const qaSuvichar = document.getElementById('qa-suvichar');
    const qaMore = document.getElementById('qa-more');

    if (qaChoghadia) qaChoghadia.addEventListener('click', openChoghadiaPopup);
    if (qaSuvichar) qaSuvichar.addEventListener('click', openSuvicharPopup);
    if (qaMore) qaMore.addEventListener('click', toggleVadhuMenu);

    const chModal = document.getElementById('choghadia-modal');
    const chClose = document.getElementById('choghadia-modal-close');
    if (chModal && chClose) {
        chClose.addEventListener('click', () => chModal.style.display = 'none');
        chModal.addEventListener('click', e => {
            if (e.target === chModal) chModal.style.display = 'none';
        });
    }

    const svModal = document.getElementById('suvichar-modal');
    const svClose = document.getElementById('suvichar-modal-close');
    if (svModal && svClose) {
        svClose.addEventListener('click', () => svModal.style.display = 'none');
        svModal.addEventListener('click', e => {
            if (e.target === svModal) svModal.style.display = 'none';
        });
    }
}

function updateQaLabels() {
    const t = translations[currentLang];
    const s = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    s('qa-lbl-choghadia', t.qaChoghadia); s('qa-lbl-panchang',  t.qaPanchang);
    s('qa-lbl-nakshatra', t.qaNakshatra); s('qa-lbl-suvichar',  t.qaSuvichar);
    s('qa-lbl-more',      t.qaMore);
    s('choghadia-modal-title', t.choghadiaTitle);
    s('suvichar-modal-title',  t.suvicharTitle);
}

/* ══════════════════════════════════════════════════════════════════
   CHOGHADIA POPUP + MODAL EXPAND
══════════════════════════════════════════════════════════════════ */
const choghadiaCache = {};

async function openChoghadiaPopup() {
    const modal = document.getElementById('choghadia-modal');
    const body  = document.getElementById('choghadia-modal-body');
    const t     = translations[currentLang];
    const today = formatDate(new Date());
    modal.style.display = 'flex';
    body.innerHTML = '<div style="padding:16px;text-align:center;color:var(--tithi-color)">Loading...</div>';
    try {
        if (!choghadiaCache[today]) {
            const res  = await fetch(`${apiBase}?action=choghadia&date=${today}`);
            const data = await res.json();
            if (data.success) choghadiaCache[today] = data.data;
        }
        const cd = choghadiaCache[today];
        if (!cd) { body.innerHTML = '<div style="color:#c62828;padding:12px">Failed to load.</div>'; return; }
        body.innerHTML = buildChoghadiaHtml(cd, today, t);
    } catch(e) { body.innerHTML = '<div style="color:#c62828;padding:12px">Network error.</div>'; }
}

function buildChoghadiaHtml(cd, dateStr, t) {
    const slots    = cd.choghadia || [];
    const daySlots = slots.filter(s => s.period === 'day');
    const ngtSlots = slots.filter(s => s.period === 'night');
    const nowMins  = new Date().getHours()*60 + new Date().getMinutes();
    function rowHtml(s) {
        const name   = t.choghadiaNames[s.name]||s.name;
        const isCurr = timeToMins(s.start) <= nowMins && nowMins < timeToMins(s.end);
        const isGood = s.quality === 'good'; const isBad = s.quality === 'bad';
        const dotClr = isGood ? '#2e7d32' : (isBad ? '#c62828' : '#888');
        const nameClr= isGood ? '#2e7d32' : (isBad ? '#c62828' : 'var(--tithi-color)');
        const currStyle = isCurr ? 'background:var(--today-bg);border:2px solid var(--today-border);' : '';
        const badge  = isCurr ? '<span class="chogh-now">▶ Now</span>' : '';
        return `<div class="chogh-row" style="${currStyle}">
            <span class="chogh-dot" style="background:${dotClr}"></span>
            <span class="chogh-name" style="color:${nameClr}">${name}</span>
            <span class="chogh-time">${s.start} – ${s.end}</span>${badge}</div>`;
    }
    return `<div class="chogh-popup-panel">
        <div class="chogh-section-hdr">☀ ${t.choghadiaDay}</div>${daySlots.map(rowHtml).join('')}
        <div class="chogh-section-hdr">🌙 ${t.choghadiaNight}</div>${ngtSlots.map(rowHtml).join('')}
    </div>`;
}

function timeToMins(ts) { const [h,m]=(ts||'0:0').split(':').map(Number); return h*60+m; }

let choghadiaOpen = false;
async function toggleChoghadiaPanel(dateStr) {
    const panel     = document.getElementById('choghadia-panel');
    const toggleRow = document.getElementById('choghadia-toggle-row');
    const t         = translations[currentLang];
    if (!panel) return;
    if (choghadiaOpen) {
        panel.style.display='none'; choghadiaOpen=false;
        toggleRow.querySelector('.detail-value').textContent = `▾ ${t.choghadiaDay}`; return;
    }
    panel.style.display='block';
    panel.innerHTML = '<div style="padding:8px;font-size:13px;color:var(--tithi-color)">Loading...</div>';
    choghadiaOpen=true;
    toggleRow.querySelector('.detail-value').textContent = `▴ ${t.choghadiaDay}`;
    try {
        if (!choghadiaCache[dateStr]) {
            const res  = await fetch(`${apiBase}?action=choghadia&date=${dateStr}`);
            const data = await res.json();
            if (data.success) choghadiaCache[dateStr] = data.data;
        }
        const cd = choghadiaCache[dateStr];
        panel.innerHTML = cd ? buildChoghadiaHtml(cd, dateStr, t) : '<div style="color:#c62828;padding:8px">Failed.</div>';
    } catch(e) { panel.innerHTML = '<div style="color:#c62828;padding:8px">Network error.</div>'; }
}

/* ══════════════════════════════════════════════════════════════════
   SUVICHAR  (60 Gujarati quotes, rotate by day-of-year)
══════════════════════════════════════════════════════════════════ */
const GUJARATI_QUOTES = [
  {q:'ઊઠો, જાગો અને ધ્યેય ન મળે ત્યાં સુધી અટકો નહીં.',a:'સ્વામી વિવેકાનંદ'},
  {q:'જ્ઞાન એ એવી સંપત્તિ છે જે ક્યારેય ચોરાઈ શકતી નથી.',a:'ચાણક્ય'},
  {q:'સ્વ-વિશ્વાસ સફળતાની પ્રથમ સીઢી છે.',a:''},
  {q:'ધૈર્ય ધારણ કરનારી વ્યક્તિ ક્યારેય નિષ્ફળ જતી નથી.',a:'ગૌતમ બુદ્ધ'},
  {q:'અહિંસા પરમ ધર્મ.',a:'ભારતીય ઉક્તિ'},
  {q:'પ્રેમ જ ઈશ્વર છે અને ઈશ્વર જ પ્રેમ છે.',a:'ગાંધીજી'},
  {q:'ક્ષમા આપવી એ સૌ મહાન બળ છે.',a:'ગાંધીજી'},
  {q:'મહેનત ક્યારેય નિષ્ફળ જતી નથી.',a:'ભારતીય ઉક્તિ'},
  {q:'આળસ માણસનો સૌ મોટો દુશ્મન છે.',a:'ભારતીય ઉક્તિ'},
  {q:'સ્નેહ સૌ ઉપચારોમાં ઉત્તમ ઉપચાર છે.',a:'ભારતીય ઉક્તિ'},
  {q:'સત્ય ક્યારેય હારતું નથી.',a:'ભારતીય ઉક્તિ'},
  {q:'ઉદ્યોગ એ સૌ ઉત્તમ ધન છે.',a:'ચાણક્ય'},
  {q:'ક્ષમા વીરોનો ગુણ છે.',a:'ભારતીય ઉક્તિ'},
  {q:'સંસ્કાર જ સૌ ઉત્તમ વારસો છે.',a:'ભારતીય ઉક્તિ'},
  {q:'ઈશ્વર ઉપર શ્રદ્ધા રાખો, બધું સારું થશે.',a:'ભારતીય ઉક્તિ'},
  {q:'જ્ઞાન વિના ભક્તિ અને ભક્તિ વિના જ્ઞાન અધૂરું છે.',a:'ભારતીય ઉક્તિ'},
  {q:'ત્યાગ એ સૌ ઉત્તમ ધર્મ છે.',a:'ભારતીય ઉક્તિ'},
  {q:'નિષ્ઠા અને પ્રામાણિકતા ભ્રષ્ટ ગુણ ઉપર વિજય પામે.',a:'ગાંધીજી'},
  {q:'ભૂતકાળ ભૂલો, ભવિષ્ય સ્વીકારો, વર્તમાનમાં જ જીવો.',a:''},
  {q:'દરેક દિવસ એ નવી શરૂઆત છે.',a:''},
  {q:'જે ઉઠે છે, ચાલે છે, તે ક્યારેય ગભરાતો નથી.',a:'ભારતીય ઉક્તિ'},
  {q:'ઊઠો, ચાલો, ઠોકર ખાઓ — પણ આગળ વધો.',a:'ભારતીય ઉક્તિ'},
  {q:'ઉચ્ચ વિચાર, સાદું જીવન.',a:'ભારતીય ઉક્તિ'},
  {q:'ઉઠ, ઉઠ, ઉઠ — ક્ષણ ન ગુમાવ.',a:'ભારતીય ઉક્તિ'},
  {q:'ટૂંકો માર્ગ — ઈમાનદારી.',a:'ભારતીય ઉક્તિ'},
];

let _suvicharIdx = -1;
function getDailyQuoteIdx() {
    if (_suvicharIdx < 0) {
        const now = new Date();
        const dayOfYear = Math.floor((now - new Date(now.getFullYear(),0,0)) / 86400000);
        _suvicharIdx = dayOfYear % GUJARATI_QUOTES.length;
    }
    return _suvicharIdx;
}

function openSuvicharPopup() {
    const modal = document.getElementById('suvichar-modal');
    modal.style.display = 'flex';
    renderSuvichar(document.getElementById('suvichar-modal-body'));
}

function renderSuvichar(body) {
    const q = GUJARATI_QUOTES[getDailyQuoteIdx()];
    const t = translations[currentLang];
    body.innerHTML = `
        <div class="suvichar-quote">"${q.q}"</div>
        <div class="suvichar-author">${q.a ? '— ' + q.a : ''}</div>
        <button class="suvichar-refresh" onclick="nextSuvichar(this.parentElement)">${t.suvicharRefresh}</button>`;
}

function nextSuvichar(body) {
    _suvicharIdx = (_suvicharIdx + 1) % GUJARATI_QUOTES.length;
    renderSuvichar(body);
}

/* ══════════════════════════════════════════════════════════════════
   WALLPAPER PREVIEW STRIP  (4 random images from wallpapers.php)
══════════════════════════════════════════════════════════════════ */
async function loadWallpaperPreview() {
    try {
        const res  = await fetch('wallpapers.php');
        const data = await res.json();
        if (!data.success || !data.images.length) return;

        // Pick 4 random images
        const imgs   = data.images.slice();
        const picked = [];
        while (picked.length < 4 && imgs.length > 0) {
            const i = Math.floor(Math.random() * imgs.length);
            picked.push(imgs.splice(i, 1)[0]);
        }

        const container = document.getElementById('wp-thumbs');
        if (!container) return;
        window._wpPicked = picked;
        container.innerHTML = picked.map((f, i) => `
            <div class="wp-thumb" onclick="openWpLb(${i})">
                <img src="wallpapers/${f.file}" alt="" loading="eager"
                    onerror="this.parentElement.innerHTML='<div class=wp-thumb-ph>🙏</div>'">
                <div class="wp-thumb-ov"></div>
            </div>`).join('');
            window._wpPicked = picked;
    } catch(e) {
        // wallpapers.php not available — strip stays with placeholder icons
    }
}