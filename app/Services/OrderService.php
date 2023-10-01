<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;

class OrderService
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {}

    /**
     * Process an order and log any commissions.
     * This should create a new affiliate if the customer_email is not already associated with one.
     * This method should also ignore duplicates based on order_id.
     *
     * @param  array{order_id: string, subtotal_price: float, merchant_domain: string, discount_code: string, customer_email: string, customer_name: string} $data
     * @return void
     */
    public function processOrder(array $data)
    {
        $existingOrder = Order::where('external_order_id', $data['order_id'])->first();

        if ($existingOrder) {
            return;
        }

        $affiliate = Affiliate::whereHas('user', function ($query) use ($data) {
            $query->where('email', $data['customer_email']);
        })->first();


        if (!$affiliate) {
            $affiliate = $this->affiliateService->register(
                $this->getMerchantByDomain($data['merchant_domain']),
                $data['customer_email'],
                $data['customer_name'],
                0.1
            );
        }

        Order::updateOrCreate(
            ['external_order_id' => $data['order_id']],
            [
                'subtotal' => $data['subtotal_price'],
                'affiliate_id' => $affiliate->id,
                'merchant_id' => $this->getMerchantByDomain($data['merchant_domain'])->id,
                'commission_owed' => $data['subtotal_price'] * $affiliate->commission_rate,
                'external_order_id' => $data['order_id']
            ]
        );

    }

    /**
     * Get the merchant by its domain.
     *
     * @param string $domain
     * @return Merchant
     */
    protected function getMerchantByDomain(string $domain): Merchant
    {
        return Merchant::where('domain', $domain)->firstOrFail();
    }
}
