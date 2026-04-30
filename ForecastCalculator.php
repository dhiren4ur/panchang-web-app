<?php
/**
 * ForecastCalculator.php
 * Generates Vedic daily forecast based on 4 combined layers:
 *   1. Nakshatra     — Today's Moon nakshatra quality (same for all)
 *   2. Chandra Bala  — Moon sign distance from natal rashi (per-rashi)
 *   3. Surya Bala    — Sun sign distance from natal rashi (per-rashi, monthly rhythm)
 *   4. Tithi         — Today's lunar day quality (same for all)
 *   + Vara + Yoga    — Weekday and yoga modifiers
 *
 * Composite score: CB 30% + Graha 22% + SB 16% + Nak 11% + Tithi 9% + Yoga 6% + Vara 4% + Karana 2%
 * Classical rule: Ashtama Chandra (CB=1) hard-caps composite at Mixed (max 2.49)
 *
 * Input : output array from PanchangCalculator->calculate()
 * Output: forecast array with English + Gujarati for all 12 rashis
 *
 * Classical sources: Brihat Samhita, Hora Sara, Phaladeepika, Muhurta Chintamani
 */

class ForecastCalculator {

    // ── 1. NAKSHATRA DATA ─────────────────────────────────────────────────────
    private $nakshatra_data = [
        'Ashwini'           => ['lord'=>'Ketu',    'quality'=>3,
            'en' => 'Ashwini Nakshatra rules today. Governed by the Ashwini Kumaras — divine healers — this swift and energetic nakshatra favors new beginnings, travel, healing, and quick decisions. Energy levels are high but avoid recklessness.',
            'gu' => 'આજે અશ્વિની નક્ષત્ર. અ.કુ. ઊ — ન-ઉ, ય, ઔ, ઝ — ઉ. ઊ ઊ, ઉ-ટ.'],
        'Bharani'           => ['lord'=>'Venus',   'quality'=>2,
            'en' => 'Bharani Nakshatra is active. Ruled by Yama and Venus — themes of transformation, restraint, and discipline. Suitable for completing pending tasks and financial dealings. Avoid new ventures.',
            'gu' => 'આ.ભ. — ય-શ ઊ. અ-ઉ, આ-ઉ — ઉ. ન-ઉ, ઉ-ટ.'],
        'Krittika'          => ['lord'=>'Sun',     'quality'=>2,
            'en' => 'Krittika Nakshatra — ruled by Sun and Agni. Sharp focus, courage, and leadership. Good for fire-related work and courageous decisions. Avoid arguments — tempers run high.',
            'gu' => 'આ.ક. — સ-અ ઊ. ન, હ, ઊ. ઝ, ગ — ટ.'],
        'Rohini'            => ['lord'=>'Moon',    'quality'=>4,
            'en' => 'Rohini Nakshatra — the most auspicious, beloved by the Moon. Ideal for creative work, romance, agriculture, buying valuables, and material gains. An overall excellent and productive day.',
            'gu' => 'આ.ર. — ચ.પ્ર. સ, પ, ખ, ઘ, વ — ઉ. ઉ-ફ.'],
        'Mrigashira'        => ['lord'=>'Mars',    'quality'=>3,
            'en' => 'Mrigashira Nakshatra brings curiosity, adaptability, and exploration. Good for travel, learning, shopping, and social interactions. Channel restless energy into research.',
            'gu' => 'આ.મ. — જ, સ, ઊ. ય, ભ, ખ — ઉ. ઊ-ઉ.'],
        'Ardra'             => ['lord'=>'Rahu',    'quality'=>1,
            'en' => 'Ardra Nakshatra — ruled by Rahu and Rudra. Storms, sudden changes, and intensity. Avoid major decisions and risky investments. Good for deep research and inner reflection.',
            'gu' => 'આ.આ. — ર-ર ઊ. ઉ-પ, ભ. ઊ-ઉ, ઓ-ઉ — ઉ.'],
        'Punarvasu'         => ['lord'=>'Jupiter', 'quality'=>3,
            'en' => 'Punarvasu brings renewal, restoration, and hope. Excellent for returning to paused projects, healing, and spiritual activities. Travel leads to safe return.',
            'gu' => 'આ.પ. — ન, ઉ, ઉ. ઊ-ઉ — ઉ. ઉ.'],
        'Pushya'            => ['lord'=>'Saturn',  'quality'=>4,
            'en' => 'Pushya Nakshatra — the most auspicious of all nakshatras. Any important work begun today is blessed. Ideal for gold, businesses, property, weddings, spiritual rituals, and elder blessings.',
            'gu' => 'આ.પ. — સ-ઉ! સ, ધ, ઘ, ઉ, વ — ઉ. અ.ફ.'],
        'Ashlesha'          => ['lord'=>'Mercury', 'quality'=>1,
            'en' => 'Ashlesha Nakshatra — sharp intelligence but hidden dangers. Careful in business dealings. Good for strategy and research. Watch health, especially digestion.',
            'gu' => 'આ.આ. — ઊ, ઉ-ઉ. ધ-સ. વ, સ — ઉ. સ, પ — ધ.'],
        'Magha'             => ['lord'=>'Ketu',    'quality'=>2,
            'en' => 'Magha Nakshatra — authority, ancestry, and tradition. Pay respects to ancestors and elders. Good for administrative work. Avoid ego clashes. Spiritual practice benefits greatly.',
            'gu' => 'આ.મ. — ઉ, વ, પ. ઊ-ઉ — ઉ. અ-ટ. ઉ ઘ-ફ.'],
        'Purva Phalguni'    => ['lord'=>'Venus',   'quality'=>3,
            'en' => 'Purva Phalguni brings joy, pleasure, and romance. Favorable for arts, entertainment, marriages, and celebrations. Avoid harsh activities.',
            'gu' => 'આ.પૂ.ફ. — પ, ઊ, ઉ. ઉ, ઘ, ઉ — ઉ.'],
        'Uttara Phalguni'   => ['lord'=>'Sun',     'quality'=>3,
            'en' => 'Uttara Phalguni excels in partnerships, agreements, and friendships. Charitable acts are highly favored. Good for long-term commitments.',
            'gu' => 'આ.ઉ.ફ. — ભ, ઊ, ઉ — ઉ. દ, ઉ-ઉ ઘ-ફ.'],
        'Hasta'             => ['lord'=>'Moon',    'quality'=>3,
            'en' => 'Hasta Nakshatra — skilled hands, trade, and quick wit. Ideal for handicrafts, negotiation, buying and selling, and healing arts.',
            'gu' => 'આ.હ. — હ, ધ, ખ-વ, ઉ — ઉ. ઊ-ઉ.'],
        'Chitra'            => ['lord'=>'Mars',    'quality'=>3,
            'en' => 'Chitra Nakshatra — beauty, design, and brilliant intelligence. Excellent for architecture, art, fashion, jewelry, and creative work. Charisma is high.',
            'gu' => 'આ.ચ. — સ, ડ, ઘ, ફ, ઊ — ઉ. ઉ-ઊ.'],
        'Swati'             => ['lord'=>'Rahu',    'quality'=>3,
            'en' => 'Swati Nakshatra — independence, trade, and flexibility. Excellent for business and international dealings. Adaptability is key. Optimistic energy.',
            'gu' => 'આ.સ. — ધ, સ, આ — ઉ. ઉ-ઊ.'],
        'Vishakha'          => ['lord'=>'Jupiter', 'quality'=>2,
            'en' => 'Vishakha brings ambition toward goals. Focused effort yields results. Avoid jealousy. Spiritual seeking and competitive activities are supported.',
            'gu' => 'આ.વ. — ઉ-ઊ, ધ — ઉ. ઈ-ટ. ઉ-ઊ.'],
        'Anuradha'          => ['lord'=>'Saturn',  'quality'=>3,
            'en' => 'Anuradha promotes devotion, teamwork, and organization. Auspicious for group activities, friendships, spiritual clubs, and disciplined work.',
            'gu' => 'આ.અ. — ટ, ભ, સ — ઉ. ઉ, ઉ-ઉ ઉ.'],
        'Jyeshtha'          => ['lord'=>'Mercury', 'quality'=>2,
            'en' => 'Jyeshtha — seniority, responsibility, and sometimes struggle. Good for leadership and overcoming challenges. Courage and perseverance are rewarded.',
            'gu' => 'આ.જ. — ન, ઊ, ઊ-ઉ. ઘ-ટ. ઊ-ઉ.'],
        'Mula'              => ['lord'=>'Ketu',    'quality'=>1,
            'en' => 'Mula — roots, dissolution, and transformation. Avoid new beginnings today. Good for research into roots, spiritual inquiry, and letting go.',
            'gu' => 'આ.મ. — ન-ઉ ટ. ઊ-ઊ, ઉ, ઉ — ઉ.'],
        'Purva Ashadha'     => ['lord'=>'Venus',   'quality'=>3,
            'en' => 'Purva Ashadha brings purification, courage, and invincibility. Good for declaring intentions, water activities, and energetic travel.',
            'gu' => 'આ.પૂ.અ. — ઘ, ઊ, જ — ઉ. ઊ.'],
        'Uttara Ashadha'    => ['lord'=>'Sun',     'quality'=>3,
            'en' => 'Uttara Ashadha brings lasting victory and noble values. Excellent for long-term commitments and ethical decisions. Truthfulness is rewarded.',
            'gu' => 'આ.ઉ.અ. — ટ-વ, ન — ઉ. સ ફ.'],
        'Shravana'          => ['lord'=>'Moon',    'quality'=>3,
            'en' => 'Shravana — listening, learning, and divine wisdom. Excellent for education, religious study, and connecting with teachers. Charity brings blessings.',
            'gu' => 'આ.શ. — ભ, ગ, ધ — ઉ. દ-સ ઘ-ફ.'],
        'Dhanishta'         => ['lord'=>'Mars',    'quality'=>3,
            'en' => 'Dhanishta — wealth, music, and group activity. Excellent for music, real estate, group endeavors, and investments. Dynamic and productive.',
            'gu' => 'આ.ધ. — સ, ધ-ઊ, ગ, ઉ — ઉ.'],
        'Shatabhisha'       => ['lord'=>'Rahu',    'quality'=>2,
            'en' => 'Shatabhisha — mysterious, scientific, and healing. Excellent for medical treatments and research. Meditation and solitary work are productive.',
            'gu' => 'આ.શ. — ઉ, સ, ઓ — ઉ. ધ, ઉ-ઉ ઘ.'],
        'Purva Bhadrapada'  => ['lord'=>'Jupiter', 'quality'=>2,
            'en' => 'Purva Bhadrapada carries intense, transformative energy. Financial caution advised. Spiritual practice is powerfully supported. Stay grounded.',
            'gu' => 'આ.પૂ.ભ. — ઊ, ઉ-ઉ. ઓ-ખ. ઉ-ઊ. ઠ-ઉ.'],
        'Uttara Bhadrapada' => ['lord'=>'Saturn',  'quality'=>3,
            'en' => 'Uttara Bhadrapada — deeply spiritual and stable. Excellent for meditation, long-term planning, and charitable work. A quiet but powerful day.',
            'gu' => 'આ.ઉ.ભ. — ધ, જ, ઠ — ઉ. ઊ-ઉ. ગ-દ ઘ.'],
        'Revati'            => ['lord'=>'Mercury', 'quality'=>3,
            'en' => 'Revati — completion, protection, and abundance. Auspicious for finishing projects, safe travels, caring for animals, and spiritual completion.',
            'gu' => 'આ.ર. — ઉ, ય-સ, ઉ, ઉ — ઉ. ઉ, ક-ઉ.'],
    ];

    // ── 2. CHANDRA BALA ───────────────────────────────────────────────────────
    private $chandra_bala = [
        1  => ['score'=>2,'label'=>'Janma',       'label_gu'=>'જન્મ',
               'en'=>'Moon transits your sign (Janma Rashi). Emotional sensitivity is high. Attend to health. Avoid major decisions but stay alert.',
               'gu'=>'ચં.જ.રા. — ભ.સ ઊ. સ.ધ. મો.ન ટ.'],
        2  => ['score'=>4,'label'=>'Sampat',      'label_gu'=>'સંપત',
               'en'=>'Moon in 2nd — financial gains and family harmony. Excellent for monetary transactions and family gatherings. Speech is pleasant and convincing.',
               'gu'=>'ચં.૨(સ) — ધ-લ, કુ-ઉ. આ-ઉ, ઘ-ઉ. વ-મ.'],
        3  => ['score'=>1,'label'=>'Vipat',       'label_gu'=>'વિપત',
               'en'=>'Moon in 3rd (Vipat) — minor obstacles and travel challenges. Courage is high but avoid rash decisions. Physical effort is rewarded.',
               'gu'=>'ચં.૩(વ) — ન-અ, ભ. ઉ-ટ. શ-ફ.'],
        4  => ['score'=>3,'label'=>'Kshema',      'label_gu'=>'ક્ષેમ',
               'en'=>'Moon in 4th (Kshema) — comfort, domestic peace, and emotional security. Excellent for home, mother, property, and healing.',
               'gu'=>'ચં.૪(ક) — ઘ, ભ-સ. ઘ, સ, ઉ — ઉ.'],
        5  => ['score'=>1,'label'=>'Pratyak',     'label_gu'=>'પ્રત્યક',
               'en'=>'Moon in 5th (Pratyak) — mental tension possible. Creativity and intuition are high. Good for art. Avoid speculation.',
               'gu'=>'ચં.૫(પ) — ચ,ત. સ,ઉ-ઊ. સ-ટ.'],
        6  => ['score'=>4,'label'=>'Sadhana',     'label_gu'=>'સાધના',
               'en'=>'Moon in 6th (Sadhana) — enemies subdued, health improves, obstacles clear. Good for competition, legal matters, and health.',
               'gu'=>'ચં.૬(સ) — શ-ન, સ-સ, અ-ન. સ,ક,સ — ઉ.'],
        7  => ['score'=>1,'label'=>'Naidhana',    'label_gu'=>'નૈધન',
               'en'=>'Moon in 7th (Naidhana) — challenging. Caution in partnerships and contracts. Avoid long journeys. Relationships need extra care.',
               'gu'=>'ચં.૭(ન) — ભ,ક — સ. ઝ-ટ. સ-ઉ.'],
        8  => ['score'=>3,'label'=>'Mitra',       'label_gu'=>'મિત્ર',
               'en'=>'Moon in 8th (Mitra) — supportive friends and good fortune. Social life is active. Good for networking and collaborations.',
               'gu'=>'ચં.૮(મ) — સ-ઉ,ઉ. સ-ઉ. ટ,સ — ઉ.'],
        9  => ['score'=>4,'label'=>'Parama Mitra','label_gu'=>'પ.મિત્ર',
               'en'=>'Moon in 9th (Parama Mitra) — highly auspicious. Fortune, dharma, and blessings flow. Excellent for religion, travel, higher studies, and guru matters.',
               'gu'=>'ચં.૯(પ.મ) — ભ,ધ,ઉ. ધ,ગ,ઉ-ભ — ઉ.'],
        10 => ['score'=>2,'label'=>'Janma(10)',   'label_gu'=>'જ.(૧૦)',
               'en'=>'Moon in 10th — career focus with uncertainty. Stay alert and methodical. Action is better than waiting.',
               'gu'=>'ચં.૧૦ — ક,ઉ. સ,વ. ક-ઉ.'],
        11 => ['score'=>4,'label'=>'Sampat(11)',  'label_gu'=>'સ.(૧૧)',
               'en'=>'Moon in 11th — gains, income, and fulfillment of desires. Social circles expand. Excellent for group activities.',
               'gu'=>'ચં.૧૧ — ઈ-પ,આ-ઉ. ગ,ઉ — ઉ.'],
        12 => ['score'=>2,'label'=>'Vipat(12)',   'label_gu'=>'વ.(૧૨)',
               'en'=>'Moon in 12th — expenses may rise, sleep disturbed. Spiritual practices, charity, and retreat are very fruitful.',
               'gu'=>'ચં.૧૨ — ખ,ઉ-ઓ. ઉ,દ — ઘ-ફ.'],
    ];

    // ── 3. SURYA BALA ─────────────────────────────────────────────────────────
    // Sun stays ~30 days per rashi — monthly-rhythm influence per rashi
    private $surya_bala = [
        1  => ['score'=>2,'label'=>'Janma Surya',   'label_gu'=>'સૂ.જન્મ',
               'en'=>'Sun transits your own sign (Janma Surya). Vitality and visibility are high but health needs attention. Ego clashes possible. Assert yourself with dignity. Career matters are highlighted.',
               'gu'=>'સૂ.જ.રા. — ઊ-ઊ, સ.ધ. અ-ઘ. ઉ,ક-ઉ.'],
        2  => ['score'=>1,'label'=>'Dhana Surya',   'label_gu'=>'સૂ.ધન',
               'en'=>'Sun in 2nd (Dhana Surya) — financial caution this month. Expenses may exceed income. Family speech may turn harsh. Avoid lending money. Focus on savings.',
               'gu'=>'સૂ.૨(ધ) — આ-સ. ખ-ઊ. ક-વ. ઉ-ન. બ-ફ.'],
        3  => ['score'=>4,'label'=>'Vikrama Surya', 'label_gu'=>'સૂ.વિ.',
               'en'=>'Sun in 3rd (Vikrama Surya) — excellent! Courage, initiative, and communication thrive. Favorable for short travels, sibling harmony, and new endeavors. Efforts are strongly supported.',
               'gu'=>'સૂ.૩(વ) — ઊ, ઉ, ઊ. ભ,ભ — ઉ. ઉ-ઊ.'],
        4  => ['score'=>2,'label'=>'Sukha Surya',   'label_gu'=>'સૂ.સુ.',
               'en'=>'Sun in 4th (Sukha Surya) — domestic tensions possible. Conflict with home matters. Property deals can be finalized. Inner reflection and home renovation are productive.',
               'gu'=>'સૂ.૪ — ઘ-ત. ઉ-ત. ઘ-ઉ. ઘ-ઊ.'],
        5  => ['score'=>1,'label'=>'Putra Surya',   'label_gu'=>'સૂ.પ.',
               'en'=>'Sun in 5th (Putra Surya) — mental stress may arise. Avoid speculative ventures strictly. Children-related matters need attention. Creative work and spiritual studies flourish.',
               'gu'=>'સૂ.૫ — ત,ઉ. સ-ટ. બ-ધ. સ,ઉ — ઉ.'],
        6  => ['score'=>4,'label'=>'Shatru Surya',  'label_gu'=>'સૂ.શ.',
               'en'=>'Sun in 6th (Shatru Surya) — enemies defeated, diseases conquered, competition brings success. Excellent for competitive exams, legal disputes, and health recovery.',
               'gu'=>'સૂ.૬(શ) — શ-ન, ર-ઉ, સ-ઉ. સ,ક,સ — ઉ.'],
        7  => ['score'=>2,'label'=>'Kalatra Surya', 'label_gu'=>'સૂ.ક.',
               'en'=>'Sun in 7th (Kalatra Surya) — partnership and marital relations may face strain. Business partnerships need careful handling. Avoid ego conflicts. Diplomacy helps.',
               'gu'=>'સૂ.૭ — ભ,ઘ-ત. ભ-ઊ. અ-ઘ. ઉ-ફ.'],
        8  => ['score'=>1,'label'=>'Mrityu Surya',  'label_gu'=>'સૂ.મ.',
               'en'=>'Sun in 8th (Mrityu Surya) — the most challenging solar position. Health setbacks and hidden obstacles possible. Avoid risky ventures. Good for research, occult work, and inner transformation.',
               'gu'=>'સૂ.૮(મ) — ક,ઉ-ત. ઊ-ઊ. જ-ટ. સ,ઉ — ઉ.'],
        9  => ['score'=>4,'label'=>'Bhagya Surya',  'label_gu'=>'સૂ.ભ.',
               'en'=>'Sun in 9th (Bhagya Surya) — highly auspicious! Fortune shines brightly. Father\'s and guru\'s blessings flow. Excellent for pilgrimages, religious activities, higher studies, and travel.',
               'gu'=>'સૂ.૯(ભ) — ભ-ઊ,ધ,ઉ. ઉ,ય,ઉ-ભ — ઉ.'],
        10 => ['score'=>3,'label'=>'Karma Surya',   'label_gu'=>'સૂ.ક.',
               'en'=>'Sun in 10th (Karma Surya) — recognition, authority, and career advancement. Visible and respected professionally. Leadership opportunities arise. A strong month for career.',
               'gu'=>'સૂ.૧૦(ક) — ઊ,ઉ,ક-ઊ. ઉ,ન. ક-ઉ.'],
        11 => ['score'=>4,'label'=>'Labha Surya',   'label_gu'=>'સૂ.લ.',
               'en'=>'Sun in 11th (Labha Surya) — gains, fulfillment, and elder-sibling support. Income from multiple sources likely. Social networks expand. Goals have high chances of achievement.',
               'gu'=>'સૂ.૧૧(લ) — ઉ-ઊ,ઈ-પ. ઊ-ઉ. ધ-ઉ.'],
        12 => ['score'=>1,'label'=>'Vyaya Surya',   'label_gu'=>'સૂ.વ.',
               'en'=>'Sun in 12th (Vyaya Surya) — increased expenses and possible isolation. Sleep disturbances. Foreign travel, spiritual retreat, and meditation are highly productive. Turn inward.',
               'gu'=>'સૂ.૧૨(વ) — ખ,ઉ-ઓ. ઉ,ત,ધ — ઉ. અ-ઉ.'],
    ];

    // ── 4. TITHI DATA ─────────────────────────────────────────────────────────
    // Nanda(1,6,11)=celebrations; Bhadra(2,7,12)=stability; Jaya(3,8,13)=victory;
    // Rikta(4,9,14)=inauspicious; Purna(5,10,15)=complete
    private $tithi_data = [
        'Shukla' => [
            1  => ['quality'=>2,'lord_en'=>'Agni',
                   'en'=>'Shukla Pratipada — the lunar month begins. Agni rules. Energy is fresh and initiatory. Suitable for new beginnings. Moderate auspiciousness.',
                   'gu'=>'શ.પ્ર.(અ) — ઊ ઉ. ન-ઉ. ઉ-ઊ.'],
            2  => ['quality'=>3,'lord_en'=>'Brahma',
                   'en'=>'Shukla Dvitiya — Bhadra tithi, ruled by Brahma. Excellent for building, construction, and creating lasting foundations. Good for property purchases and steady decisions.',
                   'gu'=>'શ.દ્વ.(બ) — ભ-ઉ, બ, ઉ. ઘ-ઊ — ઉ.'],
            3  => ['quality'=>3,'lord_en'=>'Gauri',
                   'en'=>'Shukla Tritiya — Gauri Tritiya, auspicious for women, marriage, and creative endeavors. A Jaya tithi supporting growth, expansion, and overcoming obstacles.',
                   'gu'=>'શ.ત.(ગ) — સ, ઉ, ઊ. ઉ-ઊ. ઉ.'],
            4  => ['quality'=>1,'lord_en'=>'Ganesha',
                   'en'=>'Shukla Chaturthi — Rikta tithi, avoid new auspicious work. However, Ganesha worship removes obstacles. Good for spiritual practice and resolving stalled situations.',
                   'gu'=>'શ.ચ.(ગ) — ઊ-ટ. ઉ-ઊ. ઊ-ઓ.'],
            5  => ['quality'=>4,'lord_en'=>'Naga',
                   'en'=>'Shukla Panchami — Purna tithi, Naga Panchami. Auspicious for all work. Excellent for health, prosperity, and protection. One of the most favorable tithis.',
                   'gu'=>'શ.પ.(ન) — ઉ, સ, ઊ. સ-ઉ. ઉ-ઊ.'],
            6  => ['quality'=>3,'lord_en'=>'Skanda',
                   'en'=>'Shukla Shashthi — Nanda tithi, ruled by Skanda. Favorable for celebrations, worship, physical activities, and competitive sports. Valor is highlighted.',
                   'gu'=>'શ.ષ.(ક) — ઉ, ભ-ઊ. સ-ઉ, ઉ — ઊ.'],
            7  => ['quality'=>3,'lord_en'=>'Surya',
                   'en'=>'Shukla Saptami — Sun ruled, Bhadra tithi. Good for travel, vehicles, government dealings, and leadership. Solar worship is excellent today.',
                   'gu'=>'શ.સ.(સૂ) — ઊ, ભ, ઉ. ઉ-ઊ.'],
            8  => ['quality'=>2,'lord_en'=>'Shiva/Durga',
                   'en'=>'Shukla Ashtami — Jaya tithi, ruled by Shiva and Durga. Mixed energy. Avoid beginning new work. Excellent for deity worship. Courage must be directed wisely.',
                   'gu'=>'શ.અ.(શ/દ) — ઊ-ટ. ઉ-ઉ. ઊ, ઊ — ઉ.'],
            9  => ['quality'=>1,'lord_en'=>'Durga',
                   'en'=>'Shukla Navami — Rikta tithi. Avoid auspicious ceremonies. Powerful for Devi worship, spiritual practice, and overcoming deep-seated problems. Ram Navami is this tithi.',
                   'gu'=>'શ.ન.(દ) — ઊ-ટ. ઉ-ઉ. ઉ-ઊ.'],
            10 => ['quality'=>4,'lord_en'=>'Vishnu/Dharma',
                   'en'=>'Shukla Dashami — Purna tithi, ruled by Vishnu and Dharma. Excellent for all auspicious activities: charity, spiritual events, legal matters, and important ventures.',
                   'gu'=>'શ.દ.(વ) — ઉ-ઊ. ઉ, ઊ, ઉ — ઉ.'],
            11 => ['quality'=>4,'lord_en'=>'Vishnu',
                   'en'=>'Ekadashi — the most sacred tithi, dedicated to Vishnu. Fasting is highly meritorious. Best day for devotional practice, meditation, and scripture reading.',
                   'gu'=>'ઉ.(વ) — ઊ-ઉ, ઉ-ઉ. ધ — ઉ. ઊ-ઉ.'],
            12 => ['quality'=>4,'lord_en'=>'Vishnu',
                   'en'=>'Shukla Dvadashi — Vishnu tithi. Excellent for charity, feeding others, and Vishnu worship. Highly favorable for all auspicious work. Blessings and prosperity flow.',
                   'gu'=>'શ.દ્વ.(વ) — ઉ, ઊ, ઉ-ઊ. ઊ-ઉ.'],
            13 => ['quality'=>3,'lord_en'=>'Kama',
                   'en'=>'Shukla Trayodashi — Jaya tithi, ruled by Kama. Excellent for relationships, romance, and artistic endeavors. Pradosh Vrat — Shiva worship in evening twilight is auspicious.',
                   'gu'=>'શ.ત.(ક) — ઉ, ઊ, ઉ. ઊ-ઉ — ઉ.'],
            14 => ['quality'=>2,'lord_en'=>'Shiva',
                   'en'=>'Shukla Chaturdashi — Shiva tithi. Avoid auspicious ceremonies but Shiva worship is supremely beneficial. Meditation and spiritual practice bring transformation.',
                   'gu'=>'શ.ચ.(શ) — ઊ-ટ. ઉ-ઊ. ઊ, ધ — ઉ.'],
            15 => ['quality'=>4,'lord_en'=>'Chandra/Vishnu',
                   'en'=>'Purnima — Full Moon. The most auspicious Purna tithi. All activities blessed. Excellent for Vishnu worship, charity, and spiritual celebration. Emotions and intuition peak.',
                   'gu'=>'પૂ.(ચ/વ) — ઉ-ઊ. ઉ-ઉ. ઊ-ઉ, ઊ, ઉ — ઉ.'],
        ],
        'Krishna' => [
            1  => ['quality'=>3,'lord_en'=>'Brahma',
                   'en'=>'Krishna Pratipada — waning phase begins. Good for completing ongoing work, administrative tasks, and steady progress. Avoid major new beginnings.',
                   'gu'=>'ક.પ્ર.(બ) — ઊ-ઉ, ઉ-ઉ. ઉ-ઊ ટ.'],
            2  => ['quality'=>3,'lord_en'=>'Brahma',
                   'en'=>'Krishna Dvitiya — Bhadra tithi. Good for building and long-term work. Financial matters can be addressed steadily.',
                   'gu'=>'ક.દ્વ.(બ) — ભ-ઉ, ઊ-ઉ. આ-ઉ.'],
            3  => ['quality'=>3,'lord_en'=>'Gauri',
                   'en'=>'Krishna Tritiya — Jaya tithi. Favorable for growth and expansion. Work toward goals with steady effort. Gauri worship is beneficial.',
                   'gu'=>'ક.ત.(ગ) — ઉ-ઊ, ઉ. ઊ-ટ. ઉ-ઊ.'],
            4  => ['quality'=>1,'lord_en'=>'Ganesha',
                   'en'=>'Krishna Chaturthi — Rikta tithi. Avoid new auspicious activities. Ganesha worship removes obstacles. A day for patience and inner work.',
                   'gu'=>'ક.ચ.(ગ) — ઊ-ટ. ઉ-ઊ.'],
            5  => ['quality'=>3,'lord_en'=>'Naga',
                   'en'=>'Krishna Panchami — Purna tithi, generally favorable. Suitable for completing important tasks and health-related matters.',
                   'gu'=>'ક.પ.(ન) — ઉ-ઊ, ઊ. સ-ઉ.'],
            6  => ['quality'=>3,'lord_en'=>'Skanda',
                   'en'=>'Krishna Shashthi — Nanda tithi. Good for physical activities, sports, competitive efforts, and dedicated practice.',
                   'gu'=>'ક.ષ.(ક) — ઉ, ભ. સ-ઉ, ઉ — ઊ.'],
            7  => ['quality'=>3,'lord_en'=>'Surya',
                   'en'=>'Krishna Saptami — Bhadra tithi. Good for travel and administrative work. Steady and practical energy for routine work.',
                   'gu'=>'ક.સ.(સૂ) — ઊ, ઉ. ઉ-ઊ.'],
            8  => ['quality'=>1,'lord_en'=>'Shiva/Kali',
                   'en'=>'Krishna Ashtami — Kalashtami. Ruled by Kali and Shiva. Avoid all auspicious ceremonies. Powerful for Shiva, Kali, and Bhairava worship. Handle this transformative energy with awareness.',
                   'gu'=>'ક.અ.(ક) — ઊ-ટ. ઉ-ઉ. ઊ — ઉ.'],
            9  => ['quality'=>1,'lord_en'=>'Durga',
                   'en'=>'Krishna Navami — Rikta tithi. Avoid new activities. Ruled by Durga — Devi worship is powerful. Inner strength and ancestor prayers are beneficial.',
                   'gu'=>'ક.ન.(દ) — ઊ-ટ. ઉ-ઉ. ઊ-ઉ.'],
            10 => ['quality'=>3,'lord_en'=>'Dharmaraja',
                   'en'=>'Krishna Dashami — Purna tithi. Suitable for completing significant tasks, charity, and righteous work. A decent day for most activities.',
                   'gu'=>'ક.દ.(ધ) — ઉ-ઊ. ઉ, ઊ-ઉ.'],
            11 => ['quality'=>4,'lord_en'=>'Vishnu',
                   'en'=>'Krishna Ekadashi — equally sacred as Shukla Ekadashi. Vishnu fasting is deeply meritorious. Meditation and devotional service cleanse mind and karma.',
                   'gu'=>'ક.ઉ.(વ) — ઉ-ઊ. ઊ-ઉ, ધ — ઉ.'],
            12 => ['quality'=>3,'lord_en'=>'Vishnu',
                   'en'=>'Krishna Dvadashi — Vishnu tithi. Good for charity, spiritual activities, and completing ongoing commitments.',
                   'gu'=>'ક.દ્વ.(વ) — ઉ, ઊ-ઉ. ઊ-ઉ.'],
            13 => ['quality'=>3,'lord_en'=>'Shiva',
                   'en'=>'Krishna Trayodashi — Pradosh Vrat. Shiva worship in evening twilight is supremely meritorious. Jaya tithi — good for creative work and relationships.',
                   'gu'=>'ક.ત.(શ) — ઊ-ઉ. ઉ, ઊ — ઉ.'],
            14 => ['quality'=>2,'lord_en'=>'Shiva',
                   'en'=>'Krishna Chaturdashi — Shiva\'s most powerful tithi. Avoid ceremonies but Shiva worship is extraordinary. Night vigil and fasting are deeply transformative.',
                   'gu'=>'ક.ચ.(શ) — ઊ-ટ. ઉ-ઉ. ઊ, ધ — ઉ.'],
            15 => ['quality'=>3,'lord_en'=>'Pitru/Ancestors',
                   'en'=>'Amavasya — New Moon. Sacred for ancestor worship and Shraddha rituals. Avoid new auspicious activities. Excellent for Pitru Tarpan, prayers, charity, and deep spiritual work.',
                   'gu'=>'અ.(પ) — ઊ-ટ. ઉ-ઉ. ઊ-ઉ, ત — ઉ.'],
        ],
    ];

    // ── 5. VARA (WEEKDAY) ─────────────────────────────────────────────────────
    // Classical auspiciousness scores (Muhurta Chintamani):
    // Thursday=4 (Guru, highest), Wednesday/Friday=3 (Budh/Shukra, auspicious),
    // Monday/Sunday=2 (Chandra/Surya, moderate), Tuesday/Saturday=1 (Mars/Shani, tamasic)
    private $vara_data = [
        'Sunday'    => ['score'=>2,'en'=>'Sunday (Sun) — vitality, authority, visibility. Good for government dealings, leadership, and health.','gu'=>'ર.(સૂ) — ઊ, સ, આ, ન. ઉ.'],
        'Monday'    => ['score'=>2,'en'=>'Monday (Moon) — gentle, emotional, nurturing. Good for social interactions, water activities, travel, and family.','gu'=>'સ.(ચ) — ભ-ઉ, ઘ, ઊ. ઉ.'],
        'Tuesday'   => ['score'=>1,'en'=>'Tuesday (Mars) — energy, courage, decisiveness. Good for physical work, sports, and bold decisions. Avoid new auspicious ceremonies.','gu'=>'મ.(મ) — ઊ, હ, ઉ. ઉ.'],
        'Wednesday' => ['score'=>3,'en'=>'Wednesday (Mercury) — intellect and communication. Excellent for business, learning, writing, and all intellectual work.','gu'=>'બ.(બ) — બ, ઊ, ઉ. ઉ.'],
        'Thursday'  => ['score'=>4,'en'=>'Thursday (Jupiter) — the most auspicious weekday. Excellent for religious activities, education, new ventures, and seeking blessings.','gu'=>'ગ.(ગ) — ધ, ઉ, ઊ. ઉ. સ-ઉ.'],
        'Friday'    => ['score'=>3,'en'=>'Friday (Venus) — beauty, pleasure, prosperity. Good for arts, relationships, luxury purchases, and celebrations.','gu'=>'શ.(શ) — સ, ભ, ઊ. ઉ.'],
        'Saturday'  => ['score'=>1,'en'=>'Saturday (Saturn) — disciplined and demanding. Good for hard work, service, and agriculture. Avoid new auspicious beginnings.','gu'=>'ઘ.(ઘ) — મ, સ, ઊ. ઉ.'],
    ];

    // ── 6. KARANA DATA ───────────────────────────────────────────────────────
    // Karana = half a Tithi. 2 Karanas per day (morning + afternoon half).
    // 11 Karanas total: 4 fixed (Shakuni, Chatushpada, Naga, Kimstughna) +
    // 7 repeating (Bava, Balava, Kaulava, Taitila, Gara, Vanija, Vishti/Bhadra)
    // Vishti (Bhadra) = inauspicious. Kimstughna = auspicious opener of month.
    // Score: 4=excellent, 3=good, 2=mixed, 1=avoid
    private $karana_data = [
        'Bava'         => ['score'=>3,
            'en'=>'Bava Karana — auspicious, ruled by Indra. Favorable for all activities, especially social, financial, and new ventures.',
            'gu'=>'બ.ક. — ઉ-ઊ.'],
        'Balava'       => ['score'=>3,
            'en'=>'Balava Karana — ruled by Brahma. Good for learning, spiritual work, and all constructive activities.',
            'gu'=>'બ.ક. — ઉ.'],
        'Kaulava'      => ['score'=>3,
            'en'=>'Kaulava Karana — ruled by Mitra. Favorable for friendships, partnerships, and social interactions.',
            'gu'=>'ક.ક. — ઉ.'],
        'Taitila'      => ['score'=>2,
            'en'=>'Taitila Karana — ruled by Aryama. Moderate — suitable for routine work and steady efforts.',
            'gu'=>'ત.ક. — ઉ.'],
        'Gara'         => ['score'=>2,
            'en'=>'Gara Karana — ruled by Prithvi. Good for agriculture, land dealings, and patient long-term work.',
            'gu'=>'ગ.ક. — ઉ.'],
        'Vanija'       => ['score'=>3,
            'en'=>'Vanija Karana — ruled by Vishwakarma. Excellent for trade, commerce, and business transactions.',
            'gu'=>'વ.ક. — ઉ.'],
        'Vishti'       => ['score'=>1,
            'en'=>'Vishti (Bhadra) Karana — inauspicious. Avoid starting new work, travel, or important decisions. Good for bold, aggressive, or confrontational tasks only.',
            'gu'=>'વિ.ક. — ઊ-ટ.'],
        'Shakuni'      => ['score'=>2,
            'en'=>'Shakuni Karana — fixed, ruled by Kali. Moderate — avoid very important beginnings but suitable for ordinary work.',
            'gu'=>'શ.ક. — ઉ.'],
        'Chatushpada'  => ['score'=>2,
            'en'=>'Chatushpada Karana — fixed, ruled by Vishnu. Moderate auspiciousness. Suitable for religious and devotional work.',
            'gu'=>'ચ.ક. — ઉ.'],
        'Naga'         => ['score'=>1,
            'en'=>'Naga Karana — fixed, ruled by Naga. Less auspicious — avoid new ventures. Good for spiritual and protective practices.',
            'gu'=>'ન.ક. — ઊ-ટ.'],
        'Kimstughna'   => ['score'=>4,
            'en'=>'Kimstughna Karana — fixed, opens the bright fortnight. Highly auspicious for all new beginnings and important work.',
            'gu'=>'કિ.ક. — ઉ-ઊ.'],
    ];

    // ── 7. YOGA ───────────────────────────────────────────────────────────────
    private $good_yogas = ['Priti','Ayushman','Saubhagya','Shobhana','Sukarma','Dhriti',
                            'Vriddhi','Dhruva','Harshana','Siddhi','Siddha','Sadhya','Shubha','Brahma','Indra'];
    private $bad_yogas  = ['Vishkambha','Atiganda','Shula','Ganda','Vyaghata','Vajra',
                            'Vyatipata','Parigha','Vaidhriti'];

    // ── 7. RASHI NAMES ────────────────────────────────────────────────────────
    private $rashis = [
        0=>'Mesh',1=>'Vrishabha',2=>'Mithuna',3=>'Karka',
        4=>'Simha',5=>'Kanya',6=>'Tula',7=>'Vrishchika',
        8=>'Dhanu',9=>'Makara',10=>'Kumbha',11=>'Meena'
    ];
    private $rashis_gu = [
        'Mesh'=>'મેષ','Vrishabha'=>'વૃષભ','Mithuna'=>'મિથુન','Karka'=>'કર્ક',
        'Simha'=>'સિંહ','Kanya'=>'કન્યા','Tula'=>'તુલા','Vrishchika'=>'વૃશ્ચિક',
        'Dhanu'=>'ધન','Makara'=>'મકર','Kumbha'=>'કુંભ','Meena'=>'મીન'
    ];
    private $rashis_symbol = [
        'Mesh'=>'♈','Vrishabha'=>'♉','Mithuna'=>'♊','Karka'=>'♋',
        'Simha'=>'♌','Kanya'=>'♍','Tula'=>'♎','Vrishchika'=>'♏',
        'Dhanu'=>'♐','Makara'=>'♑','Kumbha'=>'♒','Meena'=>'♓'
    ];

    // ═════════════════════════════════════════════════════════════════════════
    // ── 8. GRAHA TRANSIT EFFECTS ──────────────────────────────────────────────
    // Planet transit effects counted from natal Moon rashi (house 1–12)
    // Classical source: Phaladeepika, Brihat Parashara Hora Shastra
    // Planets: Mangal(Mars), Budh(Mercury), Guru(Jupiter), Shukra(Venus), Shani(Saturn)
    // Weights in composite: Shani=2.5, Guru=2.0, Mangal=1.5, Shukra=1.0, Budh=0.5

    private $transit_effects = [
        'Mangal' => [ // Mars
            1  => ['score'=>1,'en'=>'Mars transits natal Moon sign. Accident-prone, conflict-heavy period. Drive carefully, avoid confrontations. Channel excess energy into physical exercise and disciplined activity.',
                   'gu'=>'મં.ઘ.ર.— ઊ-ઘ, ઝ. ઉ-ઊ. ઊ-ઉ.'],
            2  => ['score'=>1,'en'=>'Mars in 2nd — harsh speech, financial strain, family arguments. Control spending and temper. Avoid lending money or engaging in financial disputes.',
                   'gu'=>'મં.૨ — ક-વ,આ-ત,ઘ-ઝ. ખ-ઉ ટ.'],
            3  => ['score'=>4,'en'=>'Mars in 3rd — excellent! Courage, initiative, and sibling support thrive. Physical work, travel, sports, and competitive efforts all yield strong results.',
                   'gu'=>'મં.૩ — ઊ! હ,ઉ,ભ-ઉ. શ,ય,સ — ઉ.'],
            4  => ['score'=>1,'en'=>'Mars in 4th — domestic conflicts, property disputes, vehicle troubles. Keep peace at home and avoid renovation projects that may spiral into arguments.',
                   'gu'=>'મં.૪ — ઘ-ઝ, ઊ-ઘ, ઉ-ત. ઘ-ઉ ઘ.'],
            5  => ['score'=>1,'en'=>'Mars in 5th — mental tension, children-related worries, creative blocks. Avoid speculation and risky investments. Patience with children and creative pursuits.',
                   'gu'=>'મં.૫ — ત, બ-ઊ, ઊ-ઘ. સ-ટ. ઘ.'],
            6  => ['score'=>4,'en'=>'Mars in 6th — excellent for defeating enemies, competitive success, and health recovery. Sports, competitions, and legal battles go in your favor. Strong and winning period.',
                   'gu'=>'મં.૬ — શ-ઉ, સ-ઉ, ઉ-ઊ! સ,ક,ઉ — ઉ.'],
            7  => ['score'=>1,'en'=>'Mars in 7th — relationship conflicts, partner disputes, travel troubles. Exercise patience with spouse or business partners. Avoid signing contracts hastily.',
                   'gu'=>'મં.૭ — ભ-ઝ, ઊ-ત, ઉ-ઘ. ઘ. ભ-ઉ.'],
            8  => ['score'=>1,'en'=>'Mars in 8th — accident-prone, surgery possible, hidden dangers arise. Exercise extra caution in all physical activities. Watch for unexpected setbacks.',
                   'gu'=>'મં.૮ — ઊ-ઉ, ઘ-ઊ, ઉ-ઘ. ઊ-ઉ.'],
            9  => ['score'=>1,'en'=>'Mars in 9th — father\'s health concerns, dharma obstacles, fortune blockage. Pilgrimage may face hurdles. Religious work requires extra patience and effort.',
                   'gu'=>'મં.૯ — ઊ-ઘ, ધ-ઊ, ભ-ઘ. ઘ.'],
            10 => ['score'=>2,'en'=>'Mars in 10th — career activity with conflicts and pressure. Hard work brings results but disputes with authority are possible. Proceed assertively but diplomatically.',
                   'gu'=>'મં.૧૦ — ક-ઊ, ઊ-ઝ. ઉ-ઉ.'],
            11 => ['score'=>4,'en'=>'Mars in 11th — excellent for gains, ambitions fulfilled, elder sibling support. Income grows through initiative. Competitive efforts succeed gloriously.',
                   'gu'=>'મં.૧૧ — ઊ! ઈ-પ, ભ-ઉ, આ-ઉ — ઉ.'],
            12 => ['score'=>2,'en'=>'Mars in 12th — expenses on property or vehicles. Foreign travel can be beneficial. Bedroom comforts highlighted. Manage spending carefully to avoid drain.',
                   'gu'=>'મં.૧૨ — ખ-ઉ. ઊ-ઉ. ઉ-ઊ. ખ-ઘ.'],
        ],
        'Budh' => [ // Mercury
            1  => ['score'=>3,'en'=>'Mercury transits natal Moon sign. Sharp intellect, excellent communication, new learning opportunities. Excellent for writing, speaking, business negotiations, and networking.',
                   'gu'=>'બ.ઘ.ર. — ઊ, ઉ-ઊ. ઉ, બ, ઊ — ઉ.'],
            2  => ['score'=>3,'en'=>'Mercury in 2nd — financial gains through intelligence and business acumen. Family conversations are pleasant. Speech is persuasive and charming. Good for trade.',
                   'gu'=>'બ.૨ — ધ-ઉ, ક-ઉ. ઘ-ઉ. ઊ-ઉ.'],
            3  => ['score'=>1,'en'=>'Mercury in 3rd — sibling communication issues, travel document delays. Short journeys face obstacles. Double-check all paperwork and agreements carefully.',
                   'gu'=>'બ.૩ — ભ-ઊ, ય-ઘ. ક-ઊ. ઊ-ઉ.'],
            4  => ['score'=>3,'en'=>'Mercury in 4th — home education, mother\'s happiness, property paperwork flows. Excellent for studying, teaching at home, and domestic planning and organization.',
                   'gu'=>'બ.૪ — ઘ-ઊ, ભ-ઉ, ઊ-ઉ — ઉ.'],
            5  => ['score'=>3,'en'=>'Mercury in 5th — creative intelligence peak, children\'s academic success, sharp speculative insights. Good for planning, analytical thinking, and artistic work.',
                   'gu'=>'બ.૫ — ઊ-ઉ, બ-ઉ, ઉ-ઊ — ઉ.'],
            6  => ['score'=>3,'en'=>'Mercury in 6th — defeat enemies through wit and intellect. Health improvement through careful dietary analysis. Medical communication is clear and helpful.',
                   'gu'=>'બ.૬ — ઊ-ઉ, ઉ-ઊ, ઉ-ઊ — ઉ.'],
            7  => ['score'=>1,'en'=>'Mercury in 7th — communication breakdowns in partnerships. Misunderstandings with spouse or business partner. Read contracts extremely carefully. Clarify everything.',
                   'gu'=>'બ.૭ — ભ-ઊ, ઉ-ઘ. ક-ઊ. ઉ-ઘ.'],
            8  => ['score'=>3,'en'=>'Mercury in 8th — research, investigation, and occult knowledge flourish. Tax matters and insurance come to light. Hidden information surfaces beneficially.',
                   'gu'=>'બ.૮ — ઊ-ઉ, ઉ-ઊ — ઉ. ઊ-ઉ.'],
            9  => ['score'=>4,'en'=>'Mercury in 9th — excellent for higher studies, philosophical writing, dharmic discussions, and connecting with inspiring teachers. Publishing and education thrive.',
                   'gu'=>'બ.૯ — ઉ-ભ! ધ, ઉ-ઊ, ગ — ઉ.'],
            10 => ['score'=>4,'en'=>'Mercury in 10th — career excellence through communication. Business success, professional recognition, and skill demonstration. A peak career period for communication-related work.',
                   'gu'=>'બ.૧૦ — ક-ઉ! ઊ, ઉ-ઊ — ઉ.'],
            11 => ['score'=>4,'en'=>'Mercury in 11th — multiple income sources, goals achieved through networking and communication. Social and professional gains flow abundantly. Excellent period.',
                   'gu'=>'બ.૧૧ — ઊ, ઈ-પ! ઉ-ઊ — ઉ.'],
            12 => ['score'=>1,'en'=>'Mercury in 12th — hidden expenses, communication losses, isolation possible. Confidential and research work is productive but public dealings may suffer.',
                   'gu'=>'બ.૧૨ — ઉ-ખ, ઉ-ઊ. ઉ-ઊ — ઉ.'],
        ],
        'Guru' => [ // Jupiter
            1  => ['score'=>3,'en'=>'Jupiter transits natal Moon sign. Wisdom, new opportunities, health improvement, and quiet optimism. Spiritual practices bear fruit. Gentle, expansive blessings arrive.',
                   'gu'=>'ગ.ઘ.ર. — જ, ઉ, ઊ. ઉ-ઊ — ઉ.'],
            2  => ['score'=>4,'en'=>'Jupiter in 2nd — family wealth grows, speech becomes inspired, food abundance arrives. Excellent financial period with Jupiter blessing income, savings, and family harmony.',
                   'gu'=>'ગ.૨ — ધ-ઊ! ઘ-ઉ, ઉ-ઊ. ઊ-ઉ.'],
            3  => ['score'=>2,'en'=>'Jupiter in 3rd — effort required for gains. Sibling relations are mixed. Short travels need planning. Success comes through persistent and disciplined effort.',
                   'gu'=>'ગ.૩ — ઊ-ઉ, ભ-ઊ. ઉ-ઊ.'],
            4  => ['score'=>1,'en'=>'Jupiter in 4th — mother\'s health may need attention despite domestic abundance. Property matters bring mixed results. Maintain domestic harmony through wisdom.',
                   'gu'=>'ગ.૪ — ઊ-ઘ, ઉ-ઊ. ઘ-ઉ.'],
            5  => ['score'=>4,'en'=>'Jupiter in 5th — children flourish, creativity is blessed, wisdom deepens beautifully. Excellent for education, spiritual practice, investment wisdom, and joyful expression.',
                   'gu'=>'ગ.૫ — બ-ઊ! ઊ-ઉ, ઉ-ઊ — ઉ.'],
            6  => ['score'=>1,'en'=>'Jupiter in 6th — enemies may become more active and health challenges arise despite Jupiter\'s wisdom. Service and humility are the remedies. Stay helpful.',
                   'gu'=>'ગ.૬ — ઊ-ઘ, ઉ-ઊ. સ — ઉ.'],
            7  => ['score'=>4,'en'=>'Jupiter in 7th — partnerships thrive, marriages are blessed, business alliances prosper. Excellent period for all one-on-one relationships and long-term commitments.',
                   'gu'=>'ગ.૭ — ભ-ઊ! ઉ, ઊ-ઉ — ઉ.'],
            8  => ['score'=>2,'en'=>'Jupiter in 8th — longevity is supported, research and occult studies flourish. Inheritance or shared finances may arise. Some obstacles but deep wisdom grows.',
                   'gu'=>'ગ.૮ — ઊ-ઉ, ઉ-ઊ. ઉ-ઘ.'],
            9  => ['score'=>4,'en'=>'Jupiter in 9th — the best Jupiter transit! Fortune shines brightly. Guru and father\'s blessings pour. Pilgrimages, dharmic work, higher education, and travel are superbly supported.',
                   'gu'=>'ગ.૯ — ઉ-ઊ!! ભ, ધ, ઉ-ભ — ઉ.'],
            10 => ['score'=>2,'en'=>'Jupiter in 10th — career growth with responsibilities and wisdom-based challenges. Leadership requires patience. Long-term effort eventually brings recognition and rewards.',
                   'gu'=>'ગ.૧૦ — ક-ઉ, ઊ-ઉ. ઘ.'],
            11 => ['score'=>4,'en'=>'Jupiter in 11th — gains, fulfillment, and all cherished wishes accomplished. Income grows beautifully, social circles expand. An exceptionally favorable and rewarding period.',
                   'gu'=>'ગ.૧૧ — ઊ! ઈ-પ, ઉ-ઊ — ઉ.'],
            12 => ['score'=>1,'en'=>'Jupiter in 12th — expenses increase, isolation possible. However, foreign travel, spiritual retreat, and ashram service bring profound inner growth and lasting merit.',
                   'gu'=>'ગ.૧૨ — ખ-ઉ. ઊ-ઉ. ત,ઉ — ઉ.'],
        ],
        'Shukra' => [ // Venus
            1  => ['score'=>4,'en'=>'Venus transits natal Moon sign. Charm, beauty, romantic attraction, and material gains arrive naturally. Social life blossoms. A beautiful and thoroughly enjoyable period.',
                   'gu'=>'શ.ઘ.ર. — ઊ! ઉ, ભ-ઉ, ઊ — ઉ.'],
            2  => ['score'=>4,'en'=>'Venus in 2nd — family wealth grows, speech is beautiful, fine food and luxuries arrive. Financial gains through artistic or beauty-related matters. Happy family atmosphere.',
                   'gu'=>'શ.૨ — ધ-ઊ! ઘ-ઉ, ઉ-ઊ — ઉ.'],
            3  => ['score'=>3,'en'=>'Venus in 3rd — artistic communication, creative travel, sibling harmony. Good for writing, performing arts, music, and short pleasant creative journeys.',
                   'gu'=>'શ.૩ — ઉ-ઊ, ભ-ઉ, ઊ — ઉ.'],
            4  => ['score'=>4,'en'=>'Venus in 4th — domestic bliss, vehicle acquisition, mother\'s happiness, home beautification. An excellent period for home renovation, family life, and creature comforts.',
                   'gu'=>'શ.૪ — ઘ-ઊ! ઉ, ભ-ઉ — ઉ.'],
            5  => ['score'=>3,'en'=>'Venus in 5th — romance flourishes, creative expression peaks, children bring great joy. Good for arts, entertainment, sports, and all pleasurable creative activities.',
                   'gu'=>'શ.૫ — ઊ-ઉ, ઉ-ઊ, બ-ઉ — ઉ.'],
            6  => ['score'=>1,'en'=>'Venus in 6th — relationship health issues, debt from luxury spending, workplace tensions arise. Exercise moderation in all pleasures and avoid overindulgence.',
                   'gu'=>'શ.૬ — ઉ-ઘ, ઊ-ખ, ઉ-ત. ઊ.'],
            7  => ['score'=>2,'en'=>'Venus in 7th — spouse or partner is highlighted and love is intense yet mixed. One-on-one relationships need open, heartfelt communication to avoid complications.',
                   'gu'=>'શ.૭ — ઉ-ઉ, ઊ-ઉ. ઉ-ઊ.'],
            8  => ['score'=>3,'en'=>'Venus in 8th — longevity enhanced, hidden wealth surfaces, deep sensual pleasures arise. Inheritance or financial support from partner may arrive unexpectedly.',
                   'gu'=>'શ.૮ — ઊ-ઉ, ઉ-ઊ — ઉ.'],
            9  => ['score'=>4,'en'=>'Venus in 9th — fortune, beauty, and dharma combine magnificently. Luxury travel, religious celebrations, artistic pilgrimages, and father\'s blessings are all highly auspicious.',
                   'gu'=>'શ.૯ — ભ-ઊ! ઉ, ઊ-ઉ — ઉ.'],
            10 => ['score'=>1,'en'=>'Venus in 10th — career obstacles arise despite charm and effort. Professional relationships may be strained. Focus on quality of work rather than outward appearances.',
                   'gu'=>'શ.૧૦ — ક-ઘ, ઊ-ઉ. ઉ-ઊ.'],
            11 => ['score'=>4,'en'=>'Venus in 11th — social gains, income through arts or beauty, luxury fulfillment. Elder sisters bring support. An enjoyable, prosperous, and socially rewarding period.',
                   'gu'=>'શ.૧૧ — ઊ! ઉ-ઊ, ભ-ઉ — ઉ.'],
            12 => ['score'=>3,'en'=>'Venus in 12th — pleasures in privacy, spiritual sensuality, foreign connections deepen. Ashram visits or quiet retreats are pleasurable and spiritually nourishing.',
                   'gu'=>'શ.૧૨ — ઉ-ઊ, ઉ-ત, ઊ — ઉ.'],
        ],
        'Shani' => [ // Saturn
            1  => ['score'=>1,'en'=>'Saturn transits natal Moon sign (Janma Shani — part of Sade Sati). Transformative but challenging: health, identity, and burdens are all tested. Patience, service, and deep discipline are the only path through.',
                   'gu'=>'શ.ઘ.ર. (સ.સ.) — ઊ-ઘ! ઉ, ત. ઘ, ઉ-ઊ.'],
            2  => ['score'=>1,'en'=>'Saturn in 2nd (Sade Sati continues). Financial strain, family discord, and harsh speech arise. Careful budgeting and deep patience in family matters are essential now.',
                   'gu'=>'શ.૨ (સ.સ.) — ઊ-ઘ, ઘ-ઉ. ઉ-ઊ.'],
            3  => ['score'=>4,'en'=>'Saturn in 3rd — courage rewarded through persistent effort. Sibling support, communication gains, and disciplined short travels all succeed. Many consider this Saturn\'s best position.',
                   'gu'=>'શ.૩ — ઊ! ઉ-ઉ, ભ-ઉ, ઊ — ઉ.'],
            4  => ['score'=>1,'en'=>'Saturn in 4th — domestic hardship, mother\'s health concerns, property challenges. Inner work, regular charity, and service help ease this difficult transit considerably.',
                   'gu'=>'શ.૪ — ઘ-ઊ, ઉ-ઊ. ઉ, ઉ-ઉ.'],
            5  => ['score'=>1,'en'=>'Saturn in 5th — mental burdens, creative delays, challenges with children and studies. Avoid speculation entirely. Disciplined study and sincere spiritual practice help greatly.',
                   'gu'=>'શ.૫ — ત, ઉ-ઘ, બ-ઊ. ઉ-ઊ.'],
            6  => ['score'=>4,'en'=>'Saturn in 6th — excellent! Disciplined service defeats enemies, chronic health issues resolve, debts are cleared systematically. Hard work is richly and fully rewarded.',
                   'gu'=>'શ.૬ — ઊ!! ઉ-ઉ, ઉ-ઊ, ઉ — ઉ.'],
            7  => ['score'=>1,'en'=>'Saturn in 7th — relationship strain, business partner difficulties, and delayed partnerships arise. Commitment and patience are tested. Genuine service to partner helps.',
                   'gu'=>'શ.૭ — ભ-ઘ, ઊ-ઉ. ઉ-ઊ.'],
            8  => ['score'=>1,'en'=>'Saturn in 8th — chronic obstacles, hidden enemies, and long-standing challenges surface. Research, discipline, and the courage to face deep truths are the way through.',
                   'gu'=>'શ.૮ — ઊ-ઘ, ઉ-ઊ. ઉ-ઉ.'],
            9  => ['score'=>1,'en'=>'Saturn in 9th — fortune and dharma are tested severely. Father\'s health concerns, guru-related challenges, and delays in auspicious work. Deep patience and sincere prayer help.',
                   'gu'=>'શ.૯ — ભ-ઘ, ઊ-ઉ. ઘ, ઉ-ઊ.'],
            10 => ['score'=>3,'en'=>'Saturn in 10th — recognition through sheer effort and discipline. Career recognition comes slowly but solidly. Hard work is eventually rewarded with lasting, well-deserved success.',
                   'gu'=>'શ.૧૦ — ક-ઊ, ઉ-ઊ. ઉ-ઉ.'],
            11 => ['score'=>4,'en'=>'Saturn in 11th — long-delayed gains finally arrive consistently. Income grows through persistence and sustained discipline. Social goals are achieved through systematic effort.',
                   'gu'=>'શ.૧૧ — ઊ! ઉ-ઈ, ઊ-ઉ — ઉ.'],
            12 => ['score'=>2,'en'=>'Saturn in 12th (beginning of Sade Sati). Expenses increase, sleep disturbed, isolation possible. Spiritual retreat, sincere service, and consciously letting go are productive.',
                   'gu'=>'શ.૧૨ (સ.સ.શ.) — ઉ-ઘ. ઊ-ઉ. ત, ઉ — ઉ.'],
        ],
    ];

    // Planet Gujarati names for display
    private $planet_names = [
        'Mangal' => ['en'=>'Mars',    'gu'=>'મંગળ', 'sym'=>'♂'],
        'Budh'   => ['en'=>'Mercury', 'gu'=>'બુધ',  'sym'=>'☿'],
        'Guru'   => ['en'=>'Jupiter', 'gu'=>'ગુરુ', 'sym'=>'♃'],
        'Shukra' => ['en'=>'Venus',   'gu'=>'શુક્ર','sym'=>'♀'],
        'Shani'  => ['en'=>'Saturn',  'gu'=>'શનિ',  'sym'=>'♄'],
    ];

    // Planet transit weights for composite score
    // (slower = more influence in Vedic astrology)
    private $planet_weights = [
        'Shani'=>2.5, 'Guru'=>2.0, 'Mangal'=>1.5, 'Shukra'=>1.0, 'Budh'=>0.5
    ];

    // ── 9. ASTRONOMICAL HELPERS (for planet longitude calculation) ────────────

    private function gregorianToJd($year, $month, $day) {
        if ($month <= 2) { $year--; $month += 12; }
        $A = floor($year / 100);
        $B = 2 - $A + floor($A / 4);
        return floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $B - 1524.5;
    }

    // Sun geocentric tropical longitude (degrees)
    private function sunLongitude($jd) {
        $T  = ($jd - 2451545.0) / 36525;
        $L0 = fmod(280.46646 + 36000.76983 * $T + 0.0003032 * $T * $T, 360);
        $M  = fmod(357.52911 + 35999.05029 * $T - 0.0001537 * $T * $T, 360);
        $Mr = deg2rad($M);
        $C  = (1.914602 - 0.004817 * $T - 0.000014 * $T * $T) * sin($Mr)
            + (0.019993 - 0.000101 * $T) * sin(2 * $Mr)
            + 0.000289 * sin(3 * $Mr);
        $lon = fmod($L0 + $C, 360);
        return $lon < 0 ? $lon + 360 : $lon;
    }

    // Generic heliocentric ecliptic longitude (degrees)
    // L0=mean longitude at J2000, n=deg/century, M0=mean anomaly at J2000,
    // nm=anomaly deg/century, C1,C2,C3 = equation-of-center coefficients
    private function helioLon($jd, $L0, $n, $M0, $nm, $C1, $C2, $C3 = 0) {
        $T   = ($jd - 2451545.0) / 36525;
        $L   = fmod($L0 + $n  * $T, 360);
        $M   = fmod($M0 + $nm * $T, 360);
        $Mr  = deg2rad($M);
        $C   = $C1 * sin($Mr) + $C2 * sin(2 * $Mr) + $C3 * sin(3 * $Mr);
        $lon = fmod($L + $C, 360);
        return $lon < 0 ? $lon + 360 : $lon;
    }

    // Convert heliocentric to geocentric ecliptic longitude
    // Works for both inner and outer planets
    private function helioToGeo($planet_L, $planet_a, $sun_L) {
        $earth_L = fmod($sun_L + 180, 360); // Earth's heliocentric longitude
        $pL = deg2rad($planet_L);
        $eL = deg2rad($earth_L);
        $px = $planet_a * cos($pL);
        $py = $planet_a * sin($pL);
        $ex = cos($eL);  // Earth distance ≈ 1 AU
        $ey = sin($eL);
        $gx = $px - $ex;
        $gy = $py - $ey;
        $lon = rad2deg(atan2($gy, $gx));
        return fmod($lon + 360, 360);
    }

    // Get rashi index (0–11) and name from ecliptic longitude
    private function lonToRashi($lon) {
        $idx = (int)floor(fmod($lon, 360) / 30);
        return ['idx' => $idx, 'name' => $this->rashis[$idx]];
    }

    // Calculate all 5 planet geocentric rashis for a given date
    private function getPlanetRashis($year, $month, $day) {
        $jd     = $this->gregorianToJd($year, $month, $day) + 6.25 / 24; // ~6:15 AM
        $sun_L  = $this->sunLongitude($jd);

        // Heliocentric elements (Meeus Table 31.a / simplified VSOP87)
        // Mars:    a=1.5237 AU, e=0.0934 → C1≈10.69°
        $mars_L  = $this->helioLon($jd, 355.433, 19141.697, 19.373, 19140.300, 10.691, 0.623, 0.050);
        // Mercury: a=0.3871 AU, e=0.2056 → C1≈23.44°
        $merc_L  = $this->helioLon($jd, 252.251, 149474.072, 174.795, 149472.516, 23.440, 2.982, 0.526);
        // Jupiter: a=5.2026 AU, e=0.0489 → C1≈5.56°
        $jup_L   = $this->helioLon($jd, 34.352, 3036.303, 20.020, 3034.906, 5.555, 0.168);
        // Venus:   a=0.7233 AU, e=0.0068 → C1≈0.78°
        $ven_L   = $this->helioLon($jd, 181.980, 58519.213, 212.960, 58517.803, 0.776, 0.003);
        // Saturn:  a=9.5370 AU, e=0.0551 → C1≈6.36°
        $sat_L   = $this->helioLon($jd, 50.077, 1223.511, 317.021, 1222.114, 6.358, 0.220, 0.011);

        // Convert to geocentric
        $mars_geo = $this->helioToGeo($mars_L,  1.5237, $sun_L);
        $merc_geo = $this->helioToGeo($merc_L,  0.3871, $sun_L);
        $jup_geo  = $this->helioToGeo($jup_L,   5.2026, $sun_L);
        $ven_geo  = $this->helioToGeo($ven_L,   0.7233, $sun_L);
        $sat_geo  = $this->helioToGeo($sat_L,   9.5370, $sun_L);

        return [
            'Mangal' => $this->lonToRashi($mars_geo),
            'Budh'   => $this->lonToRashi($merc_geo),
            'Guru'   => $this->lonToRashi($jup_geo),
            'Shukra' => $this->lonToRashi($ven_geo),
            'Shani'  => $this->lonToRashi($sat_geo),
        ];
    }


    // ═════════════════════════════════════════════════════════════════════════
    //  MAIN: generate()
    //  Optional $year/$month/$day for non-today forecasts (defaults to today)
    // ═════════════════════════════════════════════════════════════════════════

    public function generate(array $panchang, int $year=0, int $month=0, int $day=0): array {

        // Date for planet calculations
        if ($year === 0) { $t = getdate(); $year=$t['year']; $month=$t['mon']; $day=$t['mday']; }

        $nak_name  = $panchang['nakshatra_name'];
        $moon_rashi= $panchang['moon_rashi'];
        $sun_rashi = $panchang['sun_rashi'];
        $vara      = $panchang['vara'];
        $yoga      = $panchang['yoga'];
        $tithi_num = (int)($panchang['tithi_number'] ?? 1);
        $paksha    = $panchang['paksha'];

        $moon_idx = array_search($moon_rashi, $this->rashis);
        $sun_idx  = array_search($sun_rashi,  $this->rashis);

        $nak_info    = $this->nakshatra_data[$nak_name] ?? null;
        $nak_quality = $nak_info ? $nak_info['quality'] : 2;

        $yoga_quality = 'neutral';
        if (in_array($yoga, $this->good_yogas)) $yoga_quality = 'good';
        if (in_array($yoga, $this->bad_yogas))  $yoga_quality = 'bad';

        // Yoga score: good=4, neutral=2, bad=1
        $yoga_score = match($yoga_quality) {
            'good'    => 4,
            'bad'     => 1,
            default   => 2,
        };

        $vara_info  = $this->vara_data[$vara] ?? $this->vara_data['Sunday'];
        $tithi_info = $this->tithi_data[$paksha][$tithi_num] ?? null;
        $tithi_score= $tithi_info ? $tithi_info['quality'] : 2;

        // ── Karana — use real panchang value directly ─────────────────────────
        $karana_name  = $panchang['karana'] ?? 'Bava';
        $karana_info  = $this->karana_data[$karana_name] ?? $this->karana_data['Bava'];
        $karana_score = $karana_info['score'];

        // ── Yoga note (used in sec3 forecast text) ────────────────────────────
        $yoga_note_en = '';
        $yoga_note_gu = '';
        if ($yoga_quality === 'good') {
            $yoga_note_en = " {$yoga} Yoga adds further auspiciousness today.";
            $yoga_note_gu = " {$panchang['yoga_gu']} — ઉ.";
        } elseif ($yoga_quality === 'bad') {
            $yoga_note_en = " Note: {$yoga} Yoga may add friction — stay patient.";
            $yoga_note_gu = " {$panchang['yoga_gu']} — ઘ-ઉ.";
        }

        
        // ── Layer 5: Graha Transit ────────────────────────────────────────────
        $planet_rashis = $this->getPlanetRashis($year, $month, $day);

        // ── Per-rashi forecast ────────────────────────────────────────────────
        $forecasts = [];
        foreach ($this->rashis as $idx => $rashi_name) {

            // Chandra Bala
            $cb_dist = (($moon_idx - $idx + 12) % 12) + 1;
            $cb      = $this->chandra_bala[$cb_dist];

            // Surya Bala
            $sb_dist = (($sun_idx - $idx + 12) % 12) + 1;
            $sb      = $this->surya_bala[$sb_dist];

            // Graha Transit — weighted score per planet
            $graha_score_sum = 0.0;
            $graha_weight_sum= 0.0;
            $transit_lines_en = [];
            $transit_lines_gu = [];

            foreach ($this->planet_weights as $planet => $weight) {
                $p_rashi_idx  = $planet_rashis[$planet]['idx'];
                $p_rashi_name = $planet_rashis[$planet]['name'];
                $house        = (($p_rashi_idx - $idx + 12) % 12) + 1;
                $effect       = $this->transit_effects[$planet][$house];

                $graha_score_sum  += $effect['score'] * $weight;
                $graha_weight_sum += $weight;

                $pn  = $this->planet_names[$planet];
                $prg = $this->rashis_gu[$p_rashi_name];
                $transit_lines_en[] = "{$pn['sym']} {$pn['en']} in {$p_rashi_name} (house {$house}): {$effect['en']}";
                $transit_lines_gu[] = "{$pn['sym']} {$pn['gu']} {$prg}({$house}): {$effect['gu']}";
            }

            $graha_avg   = $graha_weight_sum > 0 ? $graha_score_sum / $graha_weight_sum : 2.0;
            $graha_score = max(1, min(4, (int)round($graha_avg)));

            // ── Composite score (8 layers) ──────────────────────────────────
            // CB 30% + Graha 22% + SB 16% + Nak 11% + Tithi 9% + Yoga 6% + Vara 4% + Karana 2%
            // Classical rule: Ashtama Chandra (CB=1) caps day at Mixed max
            $vara_score = $vara_info['score'];
            $composite = ($cb['score']    * 0.30)
                       + ($graha_avg      * 0.22)
                       + ($sb['score']    * 0.16)
                       + ($nak_quality    * 0.11)
                       + ($tithi_score    * 0.09)
                       + ($yoga_score     * 0.06)
                       + ($vara_score     * 0.04)
                       + ($karana_score   * 0.02);
            // Ashtama Chandra classical cap — CB=1 cannot produce Good or Excellent
            if ($cb['score'] === 1) $composite = min($composite, 2.49);
            $score = max(1, min(4, (int)round($composite)));

            $rating_en = ['Caution','Mixed','Good','Excellent'][$score - 1];
            $rating_gu = ['સાવધ','મિશ્ર','સારો','ઉત્તમ'][$score - 1];

            // ── Build 6-section forecast text ──────────────────────────────
            $sec1_en = $nak_info ? $nak_info['en'] : 'Nakshatra energy is active today.';
            $sec1_gu = $nak_info ? $nak_info['gu'] : 'ન.ઊ. ઉ.';

            $sec2_en = $tithi_info
                ? "Tithi — {$paksha} Tithi {$tithi_num} (Lord: {$tithi_info['lord_en']}): {$tithi_info['en']}"
                : '';
            $sec2_gu = $tithi_info ? "તિ — {$tithi_info['gu']}" : '';

            $sec3_en = "Chandra Bala ({$cb['label']}): {$cb['en']}{$yoga_note_en}";
            $sec3_gu = "ચ.બ. ({$cb['label_gu']}): {$cb['gu']}{$yoga_note_gu}";

            $sec4_en = "Surya Bala ({$sb['label']}): {$sb['en']}";
            $sec4_gu = "સૂ.બ. ({$sb['label_gu']}): {$sb['gu']}";

            $sec5_en = "Graha Transit:\n" . implode("\n", $transit_lines_en);
            $sec5_gu = "ગ્રહ ગોચર:\n"  . implode("\n", $transit_lines_gu);

            $sec6_en = $vara_info['en'];
            $sec6_gu = $vara_info['gu'];

            $sec7_en = "Karana ({$karana_name}): {$karana_info['en']}";
            $sec7_gu = "ક.({$karana_name}): {$karana_info['gu']}";

            $forecasts[$rashi_name] = [
                'rashi'           => $rashi_name,
                'rashi_gu'        => $this->rashis_gu[$rashi_name],
                'symbol'          => $this->rashis_symbol[$rashi_name],
                'score'           => $score,
                'rating_en'       => $rating_en,
                'rating_gu'       => $rating_gu,
                // Factor scores
                'cb_score'        => $cb['score'],
                'sb_score'        => $sb['score'],
                'graha_score'     => $graha_score,
                'tithi_score'     => $tithi_score,
                'nak_score'       => $nak_quality,
                'vara_score'      => $vara_score,
                'yoga_score'      => $yoga_score,
                'karana_score'    => $karana_score,
                // Labels
                'chandra_bala'    => $cb['label'],
                'chandra_bala_gu' => $cb['label_gu'],
                'surya_bala'      => $sb['label'],
                'surya_bala_gu'   => $sb['label_gu'],
                'karana'          => $karana_name,
                // Full forecast (7 sections)
                'forecast_en'     => implode("\n\n", array_filter([$sec1_en,$sec2_en,$sec3_en,$sec4_en,$sec5_en,$sec6_en,$sec7_en])),
                'forecast_gu'     => implode("\n\n", array_filter([$sec1_gu,$sec2_gu,$sec3_gu,$sec4_gu,$sec5_gu,$sec6_gu,$sec7_gu])),
            ];
        }

        return [
            'date'             => $panchang['vara'] . ', ' . date('d M Y'),
            'nakshatra'        => $nak_name,
            'nakshatra_gu'     => $panchang['nakshatra_gu'],
            'nakshatra_lord'   => $nak_info['lord'] ?? '',
            'nakshatra_quality'=> $nak_quality,
            'moon_rashi'       => $moon_rashi,
            'moon_rashi_gu'    => $panchang['moon_rashi_gu'],
            'sun_rashi'        => $sun_rashi,
            'sun_rashi_gu'     => $panchang['sun_rashi_gu'],
            'tithi_name'       => $panchang['tithi_name'],
            'tithi_gu'         => $panchang['tithi_gu'],
            'tithi_number'     => $tithi_num,
            'tithi_score'      => $tithi_score,
            'tithi_lord'       => $tithi_info['lord_en'] ?? '',
            'paksha'           => $paksha,
            'paksha_gu'        => $panchang['paksha_gu'],
            'yoga'             => $yoga,
            'yoga_gu'          => $panchang['yoga_gu'],
            'yoga_quality'     => $yoga_quality,
            'vara'             => $vara,
            'vara_gu'          => $panchang['vara_gu'],
            // Planet positions (for display)
            'planet_rashis'    => array_map(fn($p) => [
                'rashi'    => $p['name'],
                'rashi_gu' => $this->rashis_gu[$p['name']],
            ], $planet_rashis),
            'forecasts'        => $forecasts,
        ];
    }
}
