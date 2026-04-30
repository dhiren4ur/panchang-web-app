<?php
/**
 * ForecastTiers.php
 * Filters ForecastCalculator output based on user tier.
 *
 * Input  : full $forecast array from ForecastCalculator->generate()
 * Output : filtered array — only what the tier is allowed to see
 *
 * Tier 0 — Guest  : day rating + nakshatra + one-line tip (no rashi needed)
 * Tier 1 — Free   : full forecast for user's own rashi only
 * Tier 2 — Paid   : all 12 rashis + weekly/monthly/yearly forecast access flag
 *
 * Usage:
 *   $fc     = new ForecastCalculator();
 *   $full   = $fc->generate($panchang);
 *   $tier   = new UserTier();
 *   $filter = new ForecastTiers($tier);
 *   $output = $filter->filter($full);
 */

require_once 'UserTier.php';

class ForecastTiers {

    private UserTier $tier;

    public function __construct(UserTier $tier) {
        $this->tier = $tier;
    }

    // ── Main filter ───────────────────────────────────────────────────────────

    public function filter(array $forecast, ?string $userRashi = null): array {
        return match($this->tier->getTier()) {
            UserTier::PAID  => $this->paidView($forecast),
            UserTier::FREE  => $this->freeView($forecast, $userRashi ?? $this->tier->getUserRashi()),
            default         => $this->guestView($forecast),
        };
    }

    // ── Tier 0: Guest ─────────────────────────────────────────────────────────
    // Shows: day rating, nakshatra, one-line tip, teaser only
    // Hides: all rashi-specific forecast, factor scores

    private function guestView(array $f): array {
        $score = $this->dayOverallScore($f['forecasts']);
        return [
            'tier'           => UserTier::GUEST,
            'tier_label'     => 'Guest',
            'date'           => $f['date'],
            // Day-level info (same for everyone)
            'nakshatra'      => $f['nakshatra'],
            'nakshatra_gu'   => $f['nakshatra_gu'],
            'vara'           => $f['vara'],
            'vara_gu'        => $f['vara_gu'],
            'tithi_name'     => $f['tithi_name'],
            'tithi_gu'       => $f['tithi_gu'],
            'paksha'         => $f['paksha'],
            // Day score (average across all 12 rashis)
            'day_score'      => $score['avg'],
            'day_rating_en'  => $score['rating_en'],
            'day_rating_gu'  => $score['rating_gu'],
            'day_emoji'      => $score['emoji'],
            // One-line tip based on nakshatra quality
            'tip_en'         => $this->oneLiner($f, 'en'),
            'tip_gu'         => $this->oneLiner($f, 'gu'),
            // Upgrade prompt
            'upgrade'        => [
                'message_en' => 'Login free to see your personalised ' . $f['nakshatra'] . ' forecast for your rashi.',
                'message_gu' => 'તમારા રાશિ માટે વ્યક્તિગત આગાહી જોવા માટે મફત લૉગિન કરો.',
            ],
        ];
    }

    // ── Tier 1: Free (logged in) ──────────────────────────────────────────────
    // Shows: full forecast for own rashi only, all 8 factor scores, shareable card data
    // Hides: other rashis, weekly/monthly forecast

    private function freeView(array $f, ?string $userRashi): array {
        if (!$userRashi) {
            // Rashi not set — ask user to set it
            return array_merge($this->guestView($f), [
                'tier'       => UserTier::FREE,
                'tier_label' => 'Free',
                'needs_rashi'=> true,
                'upgrade'    => [
                    'message_en' => 'Please set your birth rashi in your profile to see your forecast.',
                    'message_gu' => 'તમારી આગાહી જોવા માટે પ્રોફાઇલમાં તમારી જન્મ રાશિ સેટ કરો.',
                ],
            ]);
        }

        $rashiForecast = $f['forecasts'][$userRashi] ?? null;

        return [
            'tier'              => UserTier::FREE,
            'tier_label'        => 'Free Member',
            'date'              => $f['date'],
            // Day-level info
            'nakshatra'         => $f['nakshatra'],
            'nakshatra_gu'      => $f['nakshatra_gu'],
            'nakshatra_lord'    => $f['nakshatra_lord'],
            'vara'              => $f['vara'],
            'vara_gu'           => $f['vara_gu'],
            'tithi_name'        => $f['tithi_name'],
            'tithi_gu'          => $f['tithi_gu'],
            'paksha'            => $f['paksha'],
            'yoga'              => $f['yoga'],
            'yoga_gu'           => $f['yoga_gu'],
            'yoga_quality'      => $f['yoga_quality'],
            // User's rashi forecast (full)
            'user_rashi'        => $userRashi,
            'forecast'          => $rashiForecast ? $this->fullRashiForecast($rashiForecast) : null,
            // Shareable card data
            'share'             => $rashiForecast ? $this->shareCard($f, $rashiForecast) : null,
            // Upgrade prompt
            'upgrade'           => [
                'message_en' => 'Upgrade to see all 12 rashis, weekly & monthly forecasts.',
                'message_gu' => 'બધી ૧૨ રાશિઓ, સાપ્તાહિક અને માસિક આગાહી જોવા અપગ્રેડ કરો.',
                'plans'      => $this->planOptions(),
            ],
        ];
    }

    // ── Tier 2: Paid ─────────────────────────────────────────────────────────
    // Shows: all 12 rashis, weekly/monthly/yearly flags, plan details

    private function paidView(array $f): array {
        $allForecasts = [];
        foreach ($f['forecasts'] as $rashi => $rf) {
            $allForecasts[$rashi] = $this->fullRashiForecast($rf);
        }

        return [
            'tier'              => UserTier::PAID,
            'tier_label'        => $this->tier->getLabel(),
            'plan'              => $this->tier->getPlan(),
            'expires_at'        => $this->tier->getExpiresAt(),
            'days_remaining'    => $this->tier->getDaysRemaining(),
            'date'              => $f['date'],
            // Full day-level info
            'nakshatra'         => $f['nakshatra'],
            'nakshatra_gu'      => $f['nakshatra_gu'],
            'nakshatra_lord'    => $f['nakshatra_lord'],
            'nakshatra_quality' => $f['nakshatra_quality'],
            'vara'              => $f['vara'],
            'vara_gu'           => $f['vara_gu'],
            'tithi_name'        => $f['tithi_name'],
            'tithi_gu'          => $f['tithi_gu'],
            'tithi_number'      => $f['tithi_number'],
            'tithi_score'       => $f['tithi_score'],
            'tithi_lord'        => $f['tithi_lord'],
            'paksha'            => $f['paksha'],
            'yoga'              => $f['yoga'],
            'yoga_gu'           => $f['yoga_gu'],
            'yoga_quality'      => $f['yoga_quality'],
            'moon_rashi'        => $f['moon_rashi'],
            'sun_rashi'         => $f['sun_rashi'],
            'planet_rashis'     => $f['planet_rashis'],
            // All 12 rashi forecasts
            'user_rashi'        => $this->tier->getUserRashi(),
            'forecasts'         => $allForecasts,
            // Extended forecast access flags
            'weekly_access'     => true,
            'monthly_access'    => in_array($this->tier->getPlan(), ['monthly','yearly']),
            'yearly_access'     => $this->tier->getPlan() === 'yearly',
            // PDF export flag
            'pdf_export'        => true,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Build full rashi forecast block */
    private function fullRashiForecast(array $rf): array {
        return [
            'rashi'          => $rf['rashi'],
            'rashi_gu'       => $rf['rashi_gu'],
            'symbol'         => $rf['symbol'],
            'score'          => $rf['score'],
            'rating_en'      => $rf['rating_en'],
            'rating_gu'      => $rf['rating_gu'],
            'emoji'          => $this->scoreEmoji($rf['score']),
            // All 8 factor scores
            'factors'        => [
                'chandra_bala' => ['score'=>$rf['cb_score'],     'label'=>$rf['chandra_bala'],    'label_gu'=>$rf['chandra_bala_gu']],
                'surya_bala'   => ['score'=>$rf['sb_score'],     'label'=>$rf['surya_bala'],      'label_gu'=>$rf['surya_bala_gu']],
                'graha'        => ['score'=>$rf['graha_score']],
                'nakshatra'    => ['score'=>$rf['nak_score']],
                'tithi'        => ['score'=>$rf['tithi_score']],
                'yoga'         => ['score'=>$rf['yoga_score']],
                'vara'         => ['score'=>$rf['vara_score']],
                'karana'       => ['score'=>$rf['karana_score'], 'label'=>$rf['karana']],
            ],
            // Full text
            'forecast_en'    => $rf['forecast_en'],
            'forecast_gu'    => $rf['forecast_gu'],
        ];
    }

    /** Build shareable card data for free/paid users */
    private function shareCard(array $f, array $rf): array {
        return [
            'rashi'      => $rf['rashi'],
            'rashi_gu'   => $rf['rashi_gu'],
            'symbol'     => $rf['symbol'],
            'score'      => $rf['score'],
            'rating_en'  => $rf['rating_en'],
            'rating_gu'  => $rf['rating_gu'],
            'emoji'      => $this->scoreEmoji($rf['score']),
            'date'       => $f['date'],
            'nakshatra'  => $f['nakshatra'],
            'tithi'      => $f['tithi_name'],
            'vara'       => $f['vara'],
            // Short caption for WhatsApp/Instagram share
            'caption_en' => "{$rf['symbol']} {$rf['rashi']} — {$rf['rating_en']} day {$this->scoreEmoji($rf['score'])}\n{$f['nakshatra']} Nakshatra | {$f['vara']} | {$f['tithi_name']}\ngujaraticalendar.com",
            'caption_gu' => "{$rf['symbol']} {$rf['rashi_gu']} — {$rf['rating_gu']} {$this->scoreEmoji($rf['score'])}\ngujaraticalendar.com",
        ];
    }

    /** Average score across all 12 rashis for guest day rating */
    private function dayOverallScore(array $forecasts): array {
        if (empty($forecasts)) return ['avg'=>2,'rating_en'=>'Mixed','rating_gu'=>'મિશ્ર','emoji'=>'🟡'];
        $sum = array_sum(array_column($forecasts, 'score'));
        $avg = (int)round($sum / count($forecasts));
        $avg = max(1, min(4, $avg));
        $ratings_en = ['Caution','Mixed','Good','Excellent'];
        $ratings_gu = ['સાવધ','મિશ્ર','સારો','ઉત્તમ'];
        return [
            'avg'       => $avg,
            'rating_en' => $ratings_en[$avg-1],
            'rating_gu' => $ratings_gu[$avg-1],
            'emoji'     => $this->scoreEmoji($avg),
        ];
    }

    /** One-line tip based on nakshatra quality for guest */
    private function oneLiner(array $f, string $lang): string {
        $q = $f['nakshatra_quality'] ?? 2;
        $tips_en = [
            1 => "Challenging energies today — stay calm and avoid major decisions.",
            2 => "Mixed day — proceed with awareness and patience.",
            3 => "Positive energies — a good day for steady efforts.",
            4 => "Highly auspicious — an excellent day for important work.",
        ];
        $tips_gu = [
            1 => "આજે સ્થિર રહો, મોટા નિર્ણયો ટાળો.",
            2 => "મિશ્ર દિવસ — ધ્યાન સાથે આગળ વધો.",
            3 => "સકારાત્મક ઊર્જા — સ્થિર પ્રયત્નો માટે સારો દિવસ.",
            4 => "અત્યંત શુભ — મહત્વના કાર્ય માટે ઉત્તમ દિવસ.",
        ];
        return ($lang === 'gu' ? $tips_gu : $tips_en)[$q] ?? '';
    }

    /** Score to emoji */
    private function scoreEmoji(int $score): string {
        return ['🔴','🟡','🟢','✨'][$score - 1] ?? '🟡';
    }

    /** Subscription plan options for upgrade prompt */
    private function planOptions(): array {
        return [
            ['plan'=>'weekly',  'price_inr'=>29,  'label_en'=>'Weekly — ₹29',   'label_gu'=>'સાપ્તાહિક — ₹૨૯',  'days'=>7],
            ['plan'=>'monthly', 'price_inr'=>99,  'label_en'=>'Monthly — ₹99',  'label_gu'=>'માસિક — ₹૯૯',      'days'=>30],
            ['plan'=>'yearly',  'price_inr'=>499, 'label_en'=>'Yearly — ₹499',  'label_gu'=>'વાર્ષિક — ₹૪૯૯',   'days'=>365],
        ];
    }
}
