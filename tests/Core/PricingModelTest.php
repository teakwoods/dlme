<?php

declare(strict_types=1);

namespace Tests\Core;

use DLme\Core\PricingModel;

describe('PricingModel', function () {
    it('has PAYG and PREPAID_TIME values', function () {
        expect(PricingModel::PAY_AS_YOU_GO->value)->toBe('payg');
        expect(PricingModel::PREPAID_TIME->value)->toBe('prepaid');
    });

    it('can be created from string value', function () {
        expect(PricingModel::from('payg'))->toBe(PricingModel::PAY_AS_YOU_GO);
        expect(PricingModel::from('prepaid'))->toBe(PricingModel::PREPAID_TIME);
    });
});
