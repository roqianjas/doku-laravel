<?php

namespace DokuLaravel\Contracts;

use DokuLaravel\DTO\StatusResult;

interface StatusService
{
    public function checkStatus(string $reference): StatusResult;
}
