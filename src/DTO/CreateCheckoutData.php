<?php

namespace DokuLaravel\DTO;

readonly class CreateCheckoutData
{
    /**
     * @param  array<int, string>  $paymentMethodTypes
     * @param  array<int, array<string, mixed>>  $lineItems
     * @param  array<string, mixed>  $additionalInfo
     */
    public function __construct(
        public string $orderNumber,
        public int $amount,
        public string $currency = 'IDR',
        public string $customerName = '',
        public string $customerEmail = '',
        public ?string $callbackUrl = null,
        public ?string $callbackUrlResult = null,
        public ?string $notificationUrl = null,
        public int $paymentDueDate = 60,
        public bool $autoRedirect = true,
        public array $paymentMethodTypes = [],
        public array $lineItems = [],
        public array $additionalInfo = [],
    ) {
    }
}
