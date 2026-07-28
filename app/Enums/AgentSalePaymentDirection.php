<?php

namespace App\Enums;

/**
 * Which side of a sale a payment record settles — mirrors the IATA BSP idea that a
 * ticket sale has two independent cash flows: what the customer owes the agent, and
 * what the agent owes the supplier/airline. Tracking both means either side can run
 * ahead of the other (customer paid in full before the supplier is remitted, or vice
 * versa) without the two ever being conflated into one balance.
 */
enum AgentSalePaymentDirection: string
{
    case FromCustomer = 'from_customer';
    case ToSupplier = 'to_supplier';

    public function label(): string
    {
        return match ($this) {
            self::FromCustomer => __('From customer'),
            self::ToSupplier => __('To supplier'),
        };
    }
}
