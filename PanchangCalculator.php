<?php
/**
 * Pure PHP Panchang Calculator
 * Calculates tithi, nakshatra, sunrise/sunset for Ahmedabad
 * No external API dependencies
 */

class PanchangCalculator {
    private $lat = 23.0225;  // Ahmedabad
    private $lon = 72.5714;
    private $tz = 5.5;       // Asia/Kolkata

    private $tithis = [
        'Pratipada', 'Dvitiya', 'Tritiya', 'Chaturthi', 'Panchami',
        'Shashthi', 'Saptami', 'Ashtami', 'Navami', 'Dashami',
        'Ekadashi', 'Dvadashi', 'Trayodashi', 'Chaturdashi', 'Purnima',
        'Pratipada', 'Dvitiya', 'Tritiya', 'Chaturthi', 'Panchami',
        'Shashthi', 'Saptami', 'Ashtami', 'Navami', 'Dashami',
        'Ekadashi', 'Dvadashi', 'Trayodashi', 'Chaturdashi', 'Amavasya'
    ];

    private $nakshatras = [
        'Ashwini', 'Bharani', 'Krittika', 'Rohini', 'Mrigashira', 'Ardra',
        'Punarvasu', 'Pushya', 'Ashlesha', 'Magha', 'Purva Phalguni', 'Uttara Phalguni',
        'Hasta', 'Chitra', 'Swati', 'Vishakha', 'Anuradha', 'Jyeshtha',
        'Mula', 'Purva Ashadha', 'Uttara Ashadha', 'Shravana', 'Dhanishta', 'Shatabhisha',
        'Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati'
    ];

    public function calculate($year, $month, $day) {
        // Step 1: get JD at midnight
        $jd_midnight = $this->gregorianToJd($year, $month, $day);

        // Step 2: calculate sunrise first (needed for correct panchang timing)
        // Hindu panchang day starts at sunrise — all values must be at sunrise
        $sunrise_str = $this->usnoSunTime($jd_midnight, 'rise');
        list($srH, $srM) = explode(':', $sunrise_str);
        $jd = $jd_midnight + ((int)$srH * 60 + (int)$srM) / 1440.0; // JD at sunrise

        $sun_long  = $this->sunLongitude($jd);
        $moon_long = $this->moonLongitude($jd);

        // ── Option B: find exact tithi/nakshatra transition times ──
        //
        // CORRECT RULE (Hindu panchang):
        //   The tithi that "touches" sunrise = the day's PRIMARY tithi.
        //   So we must search from PREVIOUS midnight through NEXT midnight
        //   to find ALL transitions, then identify which tithi was active at sunrise.
        //
        $jd_prev_midnight = $jd_midnight - 1.0; // previous midnight
        $jd_next_midnight = $jd_midnight + 1.0; // next midnight

        // Search previous midnight → next midnight for tithi transitions
        // This catches transitions that happened BEFORE sunrise (like Ekadashi ending at 07:46)
        $tithi_trans_pre  = $this->findTithiTransition($jd_prev_midnight, $jd_midnight);
        $tithi_trans_post = $this->findTithiTransition($jd_midnight, $jd_next_midnight);

        // What tithi was active at sunrise?
        $diff       = fmod($moon_long - $sun_long + 360, 360);
        $tithi_idx  = (int)floor($diff / 12);           // 0–29

        // KEY FIX: Did a transition happen between midnight and sunrise?
        // If yes → the tithi BEFORE that transition is the PRIMARY tithi of the day
        $jd_sunrise = $jd; // JD at sunrise (already calculated)
        $primary_tithi_idx = $tithi_idx;

        if ($tithi_trans_post['two_tithis'] && $tithi_trans_post['end_time'] !== null) {
            // Convert transition time to JD to compare with sunrise
            list($tH, $tM) = explode(':', $tithi_trans_post['end_time']);
            $trans_minutes   = (int)$tH * 60 + (int)$tM;
            $sunrise_minutes = (int)$srH * 60 + (int)$srM;

            if ($trans_minutes > $sunrise_minutes) {
                // Transition happens AFTER sunrise → sunrise tithi is correct primary
                $primary_tithi_idx = $tithi_idx;
                $tithi_trans = $tithi_trans_post;
                // Fix: next tithi must be one step AFTER primary, not after midnight's tithi
                $next_after_primary = ($primary_tithi_idx + 1) % 30;
                $tithi_trans['next_name']   = $this->tithis[$next_after_primary];
                $tithi_trans['next_paksha'] = $next_after_primary < 15 ? 'Shukla' : 'Krishna';
            
            } else {
                // Transition happens BEFORE or AT sunrise → previous tithi is primary
                // e.g. Ekadashi ended at 07:07, sunrise at 06:36 → show Ekadashi as primary
                $primary_tithi_idx = ($tithi_idx - 1 + 30) % 30;
                // The "transition" to show is: primary ended at trans_time, current started
                $tithi_trans = [
                    'end_time'    => $tithi_trans_post['end_time'],
                    'next_name'   => $this->tithis[$tithi_idx],
                    'next_paksha' => $tithi_idx < 15 ? 'Shukla' : 'Krishna',
                    'two_tithis'  => true,
                ];
            }
        } else {
            // No transition today (midnight→next midnight)
            // Check if previous day had a transition that carries into today
            $tithi_trans = ['end_time'=>null,'next_name'=>'','next_paksha'=>'','two_tithis'=>false];
        }

        $paksha     = $primary_tithi_idx < 15 ? 'Shukla' : 'Krishna';
        $tithi_name = $this->tithis[$primary_tithi_idx];
        $tithi_num  = ($primary_tithi_idx % 15) + 1;   // 1–15

        // Nakshatra: same approach
        $nak_trans_post = $this->findNakshatraTransition($jd_midnight, $jd_next_midnight);
        $nak_idx_sunrise = (int)floor($moon_long / 13.3333) % 27;
        $primary_nak_idx = $nak_idx_sunrise;

        if ($nak_trans_post['two_tithis'] && $nak_trans_post['end_time'] !== null) {
            list($tH, $tM) = explode(':', $nak_trans_post['end_time']);
            $trans_minutes   = (int)$tH * 60 + (int)$tM;
            $sunrise_minutes = (int)$srH * 60 + (int)$srM;

            if ($trans_minutes > $sunrise_minutes) {
                $nak_trans = $nak_trans_post;
            } else {
                // Nakshatra transition before sunrise — previous nak is primary
                $primary_nak_idx = ($nak_idx_sunrise - 1 + 27) % 27;
                $nak_trans = [
                    'end_time'   => $nak_trans_post['end_time'],
                    'next_name'  => $this->nakshatras[$nak_idx_sunrise],
                    'two_tithis' => true,
                ];
            }
        } else {
            $nak_trans = ['end_time'=>null,'next_name'=>'','two_tithis'=>false];
        }

        // Gujarati tithi names
        $tithis_gu = [
            'Pratipada'=>'પ્રતિપદા','Dvitiya'=>'દ્વિતીયા','Tritiya'=>'તૃતીયા',
            'Chaturthi'=>'ચતુર્થી','Panchami'=>'પંચમી','Shashthi'=>'ષષ્ઠી',
            'Saptami'=>'સપ્તમી','Ashtami'=>'અષ્ટમી','Navami'=>'નવમી',
            'Dashami'=>'દશમી','Ekadashi'=>'એકાદશી','Dvadashi'=>'દ્વાદશી',
            'Trayodashi'=>'ત્રયોદશી','Chaturdashi'=>'ચૌદસ',
            'Purnima'=>'પૂર્ણિમા','Amavasya'=>'અમાવસ્યા'
        ];
        $paksha_gu = ['Shukla'=>'શુક્લ','Krishna'=>'કૃષ્ણ'];

        // Nakshatra — use primary (corrected) index
        $nak_idx  = $primary_nak_idx;
        $nak_name = $this->nakshatras[$nak_idx];
        $nakshatras_gu = [
            'Ashwini'=>'અશ્વિની','Bharani'=>'ભરણી','Krittika'=>'કૃત્તિકા',
            'Rohini'=>'રોહિણી','Mrigashira'=>'મૃગશિરા','Ardra'=>'આર્દ્રા',
            'Punarvasu'=>'પુનર્વસુ','Pushya'=>'પુષ્ય','Ashlesha'=>'આશ્લેષા',
            'Magha'=>'મઘા','Purva Phalguni'=>'પૂર્વ ફાલ્ગુની','Uttara Phalguni'=>'ઉત્તર ફાલ્ગુની',
            'Hasta'=>'હસ્ત','Chitra'=>'ચિત્રા','Swati'=>'સ્વાતિ',
            'Vishakha'=>'વિશાખા','Anuradha'=>'અનુરાધા','Jyeshtha'=>'જ્યેષ્ઠા',
            'Mula'=>'મૂળ','Purva Ashadha'=>'પૂર્વ અષાઢા','Uttara Ashadha'=>'ઉત્તર અષાઢા',
            'Shravana'=>'શ્રવણ','Dhanishta'=>'ધનિષ્ઠા','Shatabhisha'=>'શતભિષા',
            'Purva Bhadrapada'=>'પૂર્વ ભાદ્રપદ','Uttara Bhadrapada'=>'ઉત્તર ભાદ્રપદ','Revati'=>'રેવતી'
        ];

        // Yoga (27 yogas based on sum of sun+moon longitudes)
        $yogas = [
            'Vishkambha','Priti','Ayushman','Saubhagya','Shobhana',
            'Atiganda','Sukarma','Dhriti','Shula','Ganda','Vriddhi',
            'Dhruva','Vyaghata','Harshana','Vajra','Siddhi','Vyatipata',
            'Variyana','Parigha','Shiva','Siddha','Sadhya','Shubha',
            'Shukla','Brahma','Indra','Vaidhriti'
        ];
        $yoga_idx  = (int)floor(fmod($sun_long + $moon_long, 360) / 13.3333) % 27;
        $yoga_name = $yogas[$yoga_idx];
        $yogas_gu  = [
            'Vishkambha'=>'વિષ્કંભ','Priti'=>'પ્રીતિ','Ayushman'=>'આયુષ્માન',
            'Saubhagya'=>'સૌભાગ્ય','Shobhana'=>'શોભન','Atiganda'=>'અતિગંડ',
            'Sukarma'=>'સુકર્મા','Dhriti'=>'ધ્રિતિ','Shula'=>'શૂળ',
            'Ganda'=>'ગંડ','Vriddhi'=>'વૃદ્ધિ','Dhruva'=>'ધ્રુવ',
            'Vyaghata'=>'વ્યાઘાત','Harshana'=>'હર્ષણ','Vajra'=>'વજ્ર',
            'Siddhi'=>'સિદ્ધિ','Vyatipata'=>'વ્યતિપાત','Variyana'=>'વરીયાન',
            'Parigha'=>'પરિઘ','Shiva'=>'શિવ','Siddha'=>'સિદ્ધ',
            'Sadhya'=>'સાધ્ય','Shubha'=>'શુભ','Shukla'=>'શુક્લ',
            'Brahma'=>'બ્રહ્મ','Indra'=>'ઇન્દ્ર','Vaidhriti'=>'વૈધ્રિતિ'
        ];

        // Karana (half-tithi)
        $karanas = [
            'Bava','Balava','Kaulava','Taitila','Gara',
            'Vanija','Vishti','Bava','Balava','Kaulava','Taitila'
        ];
        $karanas_gu = [
            'Bava'=>'બવ','Balava'=>'બાલવ','Kaulava'=>'કૌલવ',
            'Taitila'=>'તૈતિલ','Gara'=>'ગર','Vanija'=>'વણિજ',
            'Vishti'=>'ભદ્રા'
        ];
        $karana_idx  = (int)floor($diff / 6) % 11;
        $karana_name = $karanas[$karana_idx];

        // Sunsign / Rashi
        $rashis    = ['Mesh','Vrishabha','Mithuna','Karka','Simha','Kanya',
                      'Tula','Vrishchika','Dhanu','Makara','Kumbha','Meena'];
        $rashis_gu = ['Mesh'=>'મેષ','Vrishabha'=>'વૃષભ','Mithuna'=>'મિથુન',
                      'Karka'=>'કર્ક','Simha'=>'સિંહ','Kanya'=>'કન્યા',
                      'Tula'=>'તુલા','Vrishchika'=>'વૃશ્ચિક','Dhanu'=>'ધન',
                      'Makara'=>'મકર','Kumbha'=>'કુંભ','Meena'=>'મીન'];
        $sun_rashi_idx  = (int)floor($sun_long  / 30) % 12;
        $moon_rashi_idx = (int)floor($moon_long / 30) % 12;
        $sun_rashi      = $rashis[$sun_rashi_idx];
        $moon_rashi     = $rashis[$moon_rashi_idx];

        // Vara (weekday)
        $varas    = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        $varas_gu = ['Sunday'=>'રવિ','Monday'=>'સોમ','Tuesday'=>'મંગળ',
                     'Wednesday'=>'બુધ','Thursday'=>'ગુરુ','Friday'=>'શુક્ર','Saturday'=>'શનિ'];
        $wd       = (int)date('w', strtotime("$year-$month-$day"));
        $vara     = $varas[$wd];

        // Sunrise already computed above; compute sunset from midnight JD
        $sunrise = $sunrise_str;
        $sunset  = $this->usnoSunTime($jd_midnight, 'set');

        // Rahu Kalam
        $weekday_iso = (int)date('N', strtotime("$year-$month-$day")); // 1=Mon..7=Sun
        $rahu_kalam  = $this->rahuKalam($weekday_iso, $sunrise, $sunset);

        // Gulikai Kalam (slot per weekday, 1=Mon..7=Sun)
        $gulikaiPeriods = [1=>6, 2=>5, 3=>4, 4=>3, 5=>2, 6=>1, 7=>7];
        $gulikai_kalam  = $this->timeSlot($weekday_iso, $gulikaiPeriods, $sunrise, $sunset);

        // Yamaganda (slot per weekday)
        $yamagandaPeriods = [1=>4, 2=>3, 3=>2, 4=>1, 5=>8, 6=>7, 7=>6];
        $yamaganda        = $this->timeSlot($weekday_iso, $yamagandaPeriods, $sunrise, $sunset);

        // Abhijit Muhurat (auspicious midday window — 8th of 15 equal parts)
        $abhijit = $this->abhijitMuhurat($sunrise, $sunset);

        // Samvat & Gujarati month
        $samvat = $this->getVikramSamvat($year, $month, $day);
        $gu_months = [
            1=>'ચૈત્ર',2=>'વૈશાખ',3=>'જ્યેષ્ઠ',4=>'અષાઢ',
            5=>'શ્રાવણ',6=>'ભાદ્રપદ',7=>'આસો',8=>'કારતક',
            9=>'માગશર',10=>'પોષ',11=>'મહા',12=>'ફાગણ'
        ];
        $lunar_month_num = $this->getLunarMonth($sun_long);
        $lunar_month_gu  = $gu_months[$lunar_month_num] ?? 'ફાગણ';
        $lunar_month_en_arr = ['Chaitra','Vaishakha','Jyeshtha','Ashadha',
            'Shravana','Bhadrapada','Ashwin','Kartika',
            'Margashirsha','Pausha','Magha','Phalguna'];
        $lunar_month_en = $lunar_month_en_arr[$lunar_month_num - 1] ?? 'Phalguna';

        return [
            // Tithi nested (backward compat for festival detection)
            'tithi' => [
                'name'   => $tithi_name,
                'gu'     => ($tithis_gu[$tithi_name] ?? $tithi_name),
                'number' => $tithi_num,
                'paksha' => $paksha,
            ],

            // Flat aliases used by panchang.html, muhurat, etc.
            'tithi_name'    => $tithi_name,
            'tithi_gu'      => ($tithis_gu[$tithi_name] ?? $tithi_name),
            'tithi_number'  => $tithi_num,
            'paksha'        => $paksha,
            'paksha_gu'     => ($paksha_gu[$paksha] ?? $paksha),

            // Option B: tithi transition info
            'tithi_end_time'  => $tithi_trans['end_time'],   // HH:MM when current tithi ends
            'tithi_next'      => $tithi_trans['next_name'],  // next tithi name (English)
            'tithi_next_gu'   => ($tithis_gu[$tithi_trans['next_name']] ?? $tithi_trans['next_name']),
            'tithi_next_paksha'    => $tithi_trans['next_paksha'],
            'tithi_next_paksha_gu' => ($paksha_gu[$tithi_trans['next_paksha']] ?? $tithi_trans['next_paksha']),
            'two_tithis'      => $tithi_trans['two_tithis'], // bool: does another tithi start today?

            // Nakshatra — nested for frontend reads (.name, .gu) + flat aliases
            'nakshatra' => [
                'name'   => $nak_name,
                'gu'     => ($nakshatras_gu[$nak_name] ?? $nak_name),
                'number' => $nak_idx + 1,
            ],
            'nakshatra_name'   => $nak_name,
            'nakshatra_gu'     => ($nakshatras_gu[$nak_name] ?? $nak_name),
            'nakshatra_number' => $nak_idx + 1,

            // Option B: nakshatra transition info
            'nakshatra_end_time' => $nak_trans['end_time'],
            'nakshatra_next'     => $nak_trans['next_name'],
            'nakshatra_next_gu'  => ($nakshatras_gu[$nak_trans['next_name']] ?? $nak_trans['next_name']),
            'two_nakshatras'     => $nak_trans['two_tithis'],

            // Yoga
            'yoga'    => $yoga_name,
            'yoga_gu' => ($yogas_gu[$yoga_name] ?? $yoga_name),

            // Karana
            'karana'    => $karana_name,
            'karana_gu' => ($karanas_gu[$karana_name] ?? $karana_name),

            // Rashi (Sunsign / Moonsign)
            'sun_rashi'      => $sun_rashi,
            'sun_rashi_gu'   => ($rashis_gu[$sun_rashi]  ?? $sun_rashi),
            'moon_rashi'     => $moon_rashi,
            'moon_rashi_gu'  => ($rashis_gu[$moon_rashi] ?? $moon_rashi),

            // Gulikai Kalam
            'gulikai_kalam' => $gulikai_kalam,

            // Yamaganda
            'yamaganda' => $yamaganda,

            // Abhijit Muhurat
            'abhijit' => $abhijit,

            // Vara
            'vara'    => $vara,
            'vara_gu' => ($varas_gu[$vara] ?? $vara),

            // Sun times
            'sunrise' => $sunrise,
            'sunset'  => $sunset,

            // Rahu Kalam
            'rahu_kalam' => $rahu_kalam,

            // Calendar
            'samvat'        => $samvat,
            'lunar_month'   => $lunar_month_en,
            'lunar_month_gu'=> $lunar_month_gu,
            'location'      => 'Ahmedabad',
        ];
    }

    private function getLunarMonth($sun_long) {
        // Sun in Mesha (Aries, 0°–30°)   = Chaitra   (month 1)
        // Sun in Vrishabha (30°–60°)      = Vaishakha (month 2)
        // ...
        // Sun in Meena (Pisces, 330°–360°)= Phalguna  (month 12)
        // March 19: sun ≈ 358° → rashi 11 (Meena) → month 12 = Phalguna ✅
        $rashi  = (int)floor(fmod($sun_long, 360) / 30); // 0=Mesha .. 11=Meena
        $lunar  = $rashi + 1;                             // 1=Chaitra .. 12=Phalguna
        if ($lunar > 12) $lunar = 1;
        return $lunar;
    }

    private function getVikramSamvat($year, $month, $day) {
        // Vikram Samvat new year = Kartik Shukla Pratipada (day after Diwali)
        // Diwali falls in Oct or Nov each year.
        // VS 2082: Oct 21 2025 → Nov 8 2026
        // VS 2083: Nov 9 2026 → ...
        // Safe rule: month >= 11 (Nov–Dec) → year+57, else → year+56
        // This covers the vast majority of cases correctly.
        return ($month >= 11) ? $year + 57 : $year + 56;
    }

    private function gregorianToJd($year, $month, $day) {
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        $A = floor($year / 100);
        $B = 2 - $A + floor($A / 4);
        $jd = floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $B - 1524.5;
        return $jd;
    }

    private function sunLongitude($jd) {
        $T = ($jd - 2451545.0) / 36525;
        $L0 = 280.46646 + $T * (36000.76983 + $T * 0.0003032);
        $M = 357.52911 + $T * (35999.05029 - 0.0001537 * $T);
        $M_rad = deg2rad($M);
        $C = (1.914602 - $T * (0.004817 + 0.000014 * $T)) * sin($M_rad)
           + (0.019993 - 0.000101 * $T) * sin(2 * $M_rad)
           + 0.000289 * sin(3 * $M_rad);
        $L = fmod($L0 + $C, 360);
        return $L < 0 ? $L + 360 : $L;
    }

    private function moonLongitude($jd) {
        $T = ($jd - 2451545.0) / 36525;

        // Moon's mean longitude
        $L = 218.3164477 + 481267.88123421 * $T
           - 0.0015786 * $T * $T
           + $T * $T * $T / 538841;
        $L = fmod($L, 360); if ($L < 0) $L += 360;

        // Moon's mean elongation
        $D = 297.8501921 + 445267.1114034 * $T
           - 0.0018819 * $T * $T
           + $T * $T * $T / 545868;
        $D = fmod($D, 360); if ($D < 0) $D += 360;

        // Sun's mean anomaly
        $M = 357.5291092 + 35999.0502909 * $T
           - 0.0001536 * $T * $T;
        $M = fmod($M, 360); if ($M < 0) $M += 360;

        // Moon's mean anomaly
        $Mp = 134.9633964 + 477198.8675055 * $T
            + 0.0087414 * $T * $T
            + $T * $T * $T / 69699;
        $Mp = fmod($Mp, 360); if ($Mp < 0) $Mp += 360;

        // Moon's argument of latitude
        $F = 93.2720950 + 483202.0175233 * $T
           - 0.0036539 * $T * $T;
        $F = fmod($F, 360); if ($F < 0) $F += 360;

        // Convert to radians
        $Dr  = deg2rad($D);
        $Mr  = deg2rad($M);
        $Mpr = deg2rad($Mp);
        $Fr  = deg2rad($F);
        $Lr  = deg2rad($L);

        // Longitude perturbations — 15 main terms (Meeus Ch.47)
        $lon = $L
            + 6.288774 * sin($Mpr)
            + 1.274027 * sin(2*$Dr - $Mpr)
            + 0.658314 * sin(2*$Dr)
            + 0.213618 * sin(2*$Mpr)
            - 0.185116 * sin($Mr)
            - 0.114332 * sin(2*$Fr)
            + 0.058793 * sin(2*$Dr - 2*$Mpr)
            + 0.057066 * sin(2*$Dr - $Mr - $Mpr)
            + 0.053322 * sin(2*$Dr + $Mpr)
            + 0.045758 * sin(2*$Dr - $Mr)
            - 0.040923 * sin($Mr - $Mpr)
            - 0.034720 * sin($Dr)
            - 0.030383 * sin($Mr + $Mpr)
            + 0.015327 * sin(2*$Dr - 2*$Fr)
            - 0.012528 * sin($Mpr + 2*$Fr)
            + 0.010980 * sin($Mpr - 2*$Fr);

        $lon = fmod($lon, 360);
        return $lon < 0 ? $lon + 360 : $lon;
    }

    private function calculateSunrise($jd) {
        return $this->usnoSunTime($jd, 'rise');
    }

    private function calculateSunset($jd) {
        return $this->usnoSunTime($jd, 'set');
    }

    /**
     * USNO Sunrise/Sunset algorithm
     * Accurate to within ~1 minute for Ahmedabad
     * Reference: https://edwilliams.org/sunrise_sunset_algorithm.htm
     */
    private function usnoSunTime($jd, $type) {
        // Convert JD back to Y/M/D
        $z = floor($jd + 0.5);
        $f = ($jd + 0.5) - $z;
        if ($z < 2299161) {
            $a = $z;
        } else {
            $alpha = floor(($z - 1867216.25) / 36524.25);
            $a = $z + 1 + $alpha - floor($alpha / 4);
        }
        $b    = $a + 1524;
        $c    = floor(($b - 122.1) / 365.25);
        $d    = floor(365.25 * $c);
        $e    = floor(($b - $d) / 30.6001);
        $day  = $b - $d - floor(30.6001 * $e);
        $month= $e < 14 ? $e - 1 : $e - 13;
        $year = $month > 2 ? $c - 4716 : $c - 4715;

        $lat = $this->lat;
        $lon = $this->lon;
        $tz  = $this->tz;

        // Day of year
        $N1 = floor(275 * $month / 9);
        $N2 = floor(($month + 9) / 12);
        $N3 = 1 + floor(($year - 4 * floor($year / 4) + 2) / 3);
        $N  = $N1 - ($N2 * $N3) + $day - 30;

        // Longitude hour & approximate time
        $lngHour = $lon / 15;
        $t = ($type === 'rise')
            ? $N + ((6  - $lngHour) / 24)
            : $N + ((18 - $lngHour) / 24);

        // Sun mean anomaly
        $M = (0.9856 * $t) - 3.289;

        // Sun true longitude
        $L = $M + (1.916 * sin(deg2rad($M)))
               + (0.020 * sin(deg2rad(2 * $M)))
               + 282.634;
        $L = fmod($L, 360);
        if ($L < 0) $L += 360;

        // Sun right ascension
        $RA = rad2deg(atan(0.91764 * tan(deg2rad($L))));
        $RA = fmod($RA, 360);
        if ($RA < 0) $RA += 360;

        // RA in same quadrant as L
        $Lq  = floor($L  / 90) * 90;
        $RAq = floor($RA / 90) * 90;
        $RA  = ($RA + ($Lq - $RAq)) / 15;

        // Sun declination
        $sinDec = 0.39782 * sin(deg2rad($L));
        $cosDec = cos(asin($sinDec));

        // Local hour angle (zenith = 90.833 includes refraction + disc)
        $cosH = (cos(deg2rad(90.833)) - ($sinDec * sin(deg2rad($lat))))
              / ($cosDec * cos(deg2rad($lat)));

        // Sun never rises/sets — fallback
        if ($cosH > 1 || $cosH < -1) {
            return $type === 'rise' ? '06:00' : '18:30';
        }

        $H = ($type === 'rise')
            ? (360 - rad2deg(acos($cosH))) / 15
            :        rad2deg(acos($cosH))  / 15;

        // Local mean time of rising/setting
        $T  = $H + $RA - (0.06571 * $t) - 6.622;

        // UTC → local
        $UT    = fmod($T - $lngHour, 24);
        if ($UT < 0) $UT += 24;
        $local = fmod($UT + $tz, 24);
        if ($local < 0) $local += 24;

        $hours   = (int)floor($local);
        $minutes = (int)round(($local - $hours) * 60);
        if ($minutes === 60) { $hours++; $minutes = 0; }

        return sprintf("%02d:%02d", $hours, $minutes);
    }


    /**
     * Option B: Binary search for exact tithi transition time
     * Searches between jd_start and jd_end for when tithi changes
     * Returns: end_time (HH:MM), next_name, next_paksha, two_tithis (bool)
     */
    private function findTithiTransition($jd_start, $jd_end) {
        $sl_start  = $this->sunLongitude($jd_start);
        $ml_start  = $this->moonLongitude($jd_start);
        $diff_start = fmod($ml_start - $sl_start + 360, 360);
        $idx_start  = (int)floor($diff_start / 12);

        $sl_end   = $this->sunLongitude($jd_end);
        $ml_end   = $this->moonLongitude($jd_end);
        $diff_end  = fmod($ml_end - $sl_end + 360, 360);
        $idx_end   = (int)floor($diff_end / 12);

        // No tithi change during this day
        if ($idx_start === $idx_end) {
            return [
                'end_time'    => null,
                'next_name'   => '',
                'next_paksha' => '',
                'two_tithis'  => false,
            ];
        }

        // Binary search — find exact JD of transition (precision: ~1 minute)
        $lo = $jd_start;
        $hi = $jd_end;
        for ($i = 0; $i < 30; $i++) {
            $mid = ($lo + $hi) / 2.0;
            $sl_mid  = $this->sunLongitude($mid);
            $ml_mid  = $this->moonLongitude($mid);
            $diff_mid = fmod($ml_mid - $sl_mid + 360, 360);
            $idx_mid  = (int)floor($diff_mid / 12);
            if ($idx_mid === $idx_start) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        // $hi is now the transition JD — convert to local time
        $transition_jd = $hi;
        $end_time = $this->jdToLocalTime($transition_jd);

        // Get next tithi info
        $next_idx    = ($idx_start + 1) % 30;
        $next_name   = $this->tithis[$next_idx];
        $next_paksha = $next_idx < 15 ? 'Shukla' : 'Krishna';

        return [
            'end_time'    => $end_time,
            'next_name'   => $next_name,
            'next_paksha' => $next_paksha,
            'two_tithis'  => true,
        ];
    }

    /**
     * Option B: Binary search for exact nakshatra transition time
     */
    private function findNakshatraTransition($jd_start, $jd_end) {
        $ml_start  = $this->moonLongitude($jd_start);
        $idx_start = (int)floor($ml_start / 13.3333) % 27;

        $ml_end   = $this->moonLongitude($jd_end);
        $idx_end  = (int)floor($ml_end / 13.3333) % 27;

        if ($idx_start === $idx_end) {
            return [
                'end_time'   => null,
                'next_name'  => '',
                'two_tithis' => false,
            ];
        }

        $lo = $jd_start;
        $hi = $jd_end;
        for ($i = 0; $i < 30; $i++) {
            $mid = ($lo + $hi) / 2.0;
            $ml_mid  = $this->moonLongitude($mid);
            $idx_mid = (int)floor($ml_mid / 13.3333) % 27;
            if ($idx_mid === $idx_start) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        $end_time  = $this->jdToLocalTime($hi);
        $next_idx  = ($idx_start + 1) % 27;
        $next_name = $this->nakshatras[$next_idx];

        return [
            'end_time'   => $end_time,
            'next_name'  => $next_name,
            'two_tithis' => true,
        ];
    }

    /**
     * Convert a Julian Day Number to local time string HH:MM (IST)
     */
    private function jdToLocalTime($jd) {
        // Fractional day after noon (JD epoch is noon)
        $frac = fmod($jd + 0.5, 1.0);  // 0.0 = midnight UTC
        $utc_hours = $frac * 24.0;
        $local_hours = fmod($utc_hours + $this->tz, 24.0);
        if ($local_hours < 0) $local_hours += 24.0;
        $h = (int)floor($local_hours);
        $m = (int)round(($local_hours - $h) * 60);
        if ($m === 60) { $h++; $m = 0; }
        return sprintf('%02d:%02d', $h, $m);
    }

    // Generic time-slot helper (same math as Rahu Kalam)
    private function timeSlot($weekday, $periods, $sunrise, $sunset) {
        $period = $periods[$weekday] ?? 1;
        list($sH, $sM) = explode(':', $sunrise);
        list($eH, $eM) = explode(':', $sunset);
        $startMin  = (int)$sH * 60 + (int)$sM;
        $endMin    = (int)$eH * 60 + (int)$eM;
        $slotLen   = ($endMin - $startMin) / 8;
        $slotStart = $startMin + ($period - 1) * $slotLen;
        $slotEnd   = $slotStart + $slotLen;
        return [
            'start' => sprintf('%02d:%02d', (int)floor($slotStart/60), (int)round(fmod($slotStart,60))),
            'end'   => sprintf('%02d:%02d', (int)floor($slotEnd/60),   (int)round(fmod($slotEnd,60)))
        ];
    }

    // Abhijit Muhurat — auspicious midday window
    private function abhijitMuhurat($sunrise, $sunset) {
        list($sH, $sM) = explode(':', $sunrise);
        list($eH, $eM) = explode(':', $sunset);
        $startMin = (int)$sH * 60 + (int)$sM;
        $endMin   = (int)$eH * 60 + (int)$eM;
        $duration = $endMin - $startMin;
        $slotLen  = $duration / 15;          // 15 muhurtas in a day
        $abStart  = $startMin + 7 * $slotLen; // 8th muhurta (0-indexed = 7)
        $abEnd    = $abStart  + $slotLen;
        return [
            'start' => sprintf('%02d:%02d', (int)floor($abStart/60), (int)round(fmod($abStart,60))),
            'end'   => sprintf('%02d:%02d', (int)floor($abEnd/60),   (int)round(fmod($abEnd,60)))
        ];
    }

    private function rahuKalam($weekday, $sunrise, $sunset) {
        // Rahu Kalam slot number (1–8) verified against DrikPanchang Ahmedabad
        // 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun
        $rahuPeriods = [
            1 => 2,  // Monday:    2nd slot  → verified ✅
            2 => 7,  // Tuesday:   7th slot  → verified ✅
            3 => 5,  // Wednesday: 5th slot  → verified ✅
            4 => 6,  // Thursday:  6th slot  → verified ✅
            5 => 4,  // Friday:    4th slot  → verified ✅
            6 => 3,  // Saturday:  3rd slot  → verified ✅
            7 => 8   // Sunday:    8th slot  → verified ✅
        ];

        $period = $rahuPeriods[$weekday];

        list($sH, $sM) = explode(':', $sunrise);
        list($eH, $eM) = explode(':', $sunset);
        $startMin = $sH * 60 + $sM;
        $endMin = $eH * 60 + $eM;
        $duration = $endMin - $startMin;
        $periodLen = $duration / 8;

        $rahuStart = $startMin + ($period - 1) * $periodLen;
        $rahuEnd = $rahuStart + $periodLen;

        $rStartH = floor($rahuStart / 60);
        $rStartM = round(fmod($rahuStart, 60));
        $rEndH = floor($rahuEnd / 60);
        $rEndM = round(fmod($rahuEnd, 60));

        return [
            'start' => sprintf("%02d:%02d", $rStartH, $rStartM),
            'end' => sprintf("%02d:%02d", $rEndH, $rEndM)
        ];
    }
}
?>