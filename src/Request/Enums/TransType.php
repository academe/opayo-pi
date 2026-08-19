<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Request\Enums;

/**
 * The 3D Secure v2 transaction type.
 * Enum equivalent of the StrongCustomerAuthentication::TRANS_TYPE_* constants.
 */

enum TransType: string
{
    case GoodsAndServicePurchase = 'GoodsAndServicePurchase';
    case CheckAcceptance = 'CheckAcceptance';
    case AccountFunding = 'AccountFunding';
    case QuasiCashTransaction = 'QuasiCashTransaction';
    case PrepaidActivationAndLoad = 'PrepaidActivationAndLoad';
}
