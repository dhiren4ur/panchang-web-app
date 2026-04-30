<?php
/**
 * UserTier.php
 * Single source of truth for user access level.
 *
 * Tiers:
 *   0 = Guest     — not logged in
 *   1 = Free      — logged in, no subscription
 *   2 = Paid      — active subscription
 *
 * Subscription plans: 'weekly' | 'monthly' | 'yearly'
 *
 * HOW TO INTEGRATE YOUR LOGIN SYSTEM:
 * ------------------------------------
 * This file uses a simple session-based approach.
 * When your login system is ready, replace the two
 * marked sections below with your actual logic.
 *
 * Database table needed (when ready):
 * ------------------------------------
 *   CREATE TABLE subscriptions (
 *     id           INT AUTO_INCREMENT PRIMARY KEY,
 *     user_id      INT NOT NULL,
 *     plan         ENUM('weekly','monthly','yearly') NOT NULL,
 *     started_at   DATETIME NOT NULL,
 *     expires_at   DATETIME NOT NULL,
 *     payment_ref  VARCHAR(100),               -- Razorpay/Stripe order ID
 *     status       ENUM('active','expired','cancelled') DEFAULT 'active',
 *     created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
 *   );
 */

class UserTier {

    // ── Tier constants ────────────────────────────────────────────────────────
    const GUEST = 0;
    const FREE  = 1;
    const PAID  = 2;

    // ── Subscription plan durations (days) ───────────────────────────────────
    const PLAN_DAYS = [
        'weekly'  => 7,
        'monthly' => 30,
        'yearly'  => 365,
    ];

    // ── Subscription prices (INR) — update when payment is ready ─────────────
    const PLAN_PRICES_INR = [
        'weekly'  => 29,
        'monthly' => 99,
        'yearly'  => 499,
    ];

    // ─────────────────────────────────────────────────────────────────────────

    private int    $tier;
    private ?array $user;
    private ?array $subscription;

    public function __construct() {
        // ── REPLACE THIS SECTION with your login system ───────────────────
        // Currently reads from PHP session.
        // When MySQL login is ready, query your users table here instead.
        if (session_status() === PHP_SESSION_NONE) session_start();

        $this->user         = $_SESSION['user']         ?? null;
        $this->subscription = $_SESSION['subscription'] ?? null;
        // ── END REPLACE ───────────────────────────────────────────────────

        $this->tier = $this->resolveTier();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /** Current tier: UserTier::GUEST | FREE | PAID */
    public function getTier(): int { return $this->tier; }

    public function isGuest(): bool { return $this->tier === self::GUEST; }
    public function isFree():  bool { return $this->tier === self::FREE;  }
    public function isPaid():  bool { return $this->tier === self::PAID;  }

    /** Logged in at any level (free or paid) */
    public function isLoggedIn(): bool { return $this->tier >= self::FREE; }

    /** Subscription plan name or null */
    public function getPlan(): ?string {
        return $this->subscription['plan'] ?? null;
    }

    /** Subscription expiry date string or null */
    public function getExpiresAt(): ?string {
        return $this->subscription['expires_at'] ?? null;
    }

    /** Days remaining in subscription (0 if none) */
    public function getDaysRemaining(): int {
        if (!$this->isPaid()) return 0;
        $exp = strtotime($this->subscription['expires_at'] ?? '');
        if (!$exp) return 0;
        return max(0, (int)ceil(($exp - time()) / 86400));
    }

    /** Logged-in user ID or null */
    public function getUserId(): ?int {
        return $this->user['id'] ?? null;
    }

    /** Logged-in user rashi or null */
    public function getUserRashi(): ?string {
        return $this->user['rashi'] ?? null;
    }

    /** Human-readable tier label */
    public function getLabel(): string {
        return match($this->tier) {
            self::PAID  => ucfirst($this->getPlan() ?? 'Paid') . ' Member',
            self::FREE  => 'Free Member',
            default     => 'Guest',
        };
    }

    // ── Tier resolution logic ─────────────────────────────────────────────────

    private function resolveTier(): int {
        // Not logged in
        if (!$this->user) return self::GUEST;

        // Logged in — check subscription
        if (!$this->subscription) return self::FREE;

        // Has subscription — check if still active
        $expires = strtotime($this->subscription['expires_at'] ?? '');
        if (!$expires || $expires < time()) return self::FREE;

        return self::PAID;
    }

    // ── MOCK LOGIN HELPERS (for testing — remove in production) ───────────────

    /** Simulate a guest session (for testing) */
    public static function mockGuest(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        unset($_SESSION['user'], $_SESSION['subscription']);
    }

    /** Simulate a free user session (for testing) */
    public static function mockFree(int $userId = 1, string $rashi = 'Mesh'): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user']         = ['id' => $userId, 'rashi' => $rashi];
        $_SESSION['subscription'] = null;
    }

    /** Simulate a paid user session (for testing) */
    public static function mockPaid(string $plan = 'monthly', string $rashi = 'Mesh'): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $days = self::PLAN_DAYS[$plan] ?? 30;
        $_SESSION['user']         = ['id' => 1, 'rashi' => $rashi];
        $_SESSION['subscription'] = [
            'plan'       => $plan,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$days} days")),
            'status'     => 'active',
        ];
    }
}
