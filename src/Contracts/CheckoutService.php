<?php

namespace DokuLaravel\Contracts;

use DokuLaravel\DTO\CheckoutResult;
use DokuLaravel\DTO\CreateCheckoutData;

interface CheckoutService
{
    public function createCheckout(CreateCheckoutData $data): CheckoutResult;
}
