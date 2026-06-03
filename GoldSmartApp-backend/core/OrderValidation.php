<?php declare(strict_types=1);

/**
 * Order Validation Trait
 * Shared logic for validating order limits (daily limit & cooldown)
 * Used by CartController and TransactionController
 */
trait OrderValidation
{
    // Order limits per role (shared between Cart and Transaction Controllers)
    protected static function getOrderLimits(): array
    {
        return [
            'user' => ['daily_limit' => 10, 'cooldown_seconds' => 60],
            'member' => ['daily_limit' => 15, 'cooldown_seconds' => 35],
        ];
    }

    /**
     * Validate order limits (daily limit & cooldown) before checkout/create
     * Returns error response and exits if limit exceeded.
     */
    protected function validateOrderLimits(int $userId, array $user, Transaction $transactionModel): void
    {
        $role = $user['role'] ?? 'user';
        $limits = static::getOrderLimits()[$role] ?? static::getOrderLimits()['user'];

        // Check daily order limit
        $todayOrders = $transactionModel->countTodayOrders($userId);
        if ($todayOrders >= $limits['daily_limit']) {
            Response::error(
                "Batas pesanan harian tercapai ({$limits['daily_limit']} pesanan/hari). Coba lagi besok.",
                429
            );
        }

        // Check cooldown interval
        $lastOrderTime = $transactionModel->getLastOrderTime($userId);
        if ($lastOrderTime) {
            $elapsed = time() - strtotime($lastOrderTime);
            $remaining = $limits['cooldown_seconds'] - $elapsed;
            if ($remaining > 0) {
                Response::error(
                    "Harap tunggu {$remaining} detik sebelum melakukan checkout berikutnya.",
                    429
                );
            }
        }
    }

    /**
     * Get checkout status info for API response
     */
    protected function getCheckoutStatusInfo(int $userId, array $user, Transaction $transactionModel): array
    {
        $role = $user['role'] ?? 'user';
        $limits = static::getOrderLimits()[$role] ?? static::getOrderLimits()['user'];

        $todayOrders = $transactionModel->countTodayOrders($userId);
        $lastOrderTime = $transactionModel->getLastOrderTime($userId);

        $cooldownRemaining = 0;
        if ($lastOrderTime) {
            $elapsed = time() - strtotime($lastOrderTime);
            $cooldownRemaining = max(0, $limits['cooldown_seconds'] - $elapsed);
        }

        return [
            'daily_limit' => $limits['daily_limit'],
            'today_orders' => $todayOrders,
            'remaining_orders' => max(0, $limits['daily_limit'] - $todayOrders),
            'cooldown_seconds' => $limits['cooldown_seconds'],
            'cooldown_remaining' => $cooldownRemaining,
            'can_checkout' => $todayOrders < $limits['daily_limit'] && $cooldownRemaining === 0,
            'role' => $role,
        ];
    }
}
