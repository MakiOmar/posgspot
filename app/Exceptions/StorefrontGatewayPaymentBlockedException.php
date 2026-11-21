<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Staff tried to mark a storefront online (Geidea/Fawry) payment complete without gateway capture.
 */
class StorefrontGatewayPaymentBlockedException extends RuntimeException
{
}
