<?php

declare(strict_types=1);

use DLme\Core\AvailabilityService;
use DLme\Core\AvailabilityStatus;
use DLme\Core\ButtonVisualVariant;
use DLme\Core\CallToActionService;
use DLme\Core\CtaStyleConfig;
use DLme\Core\CtaType;
use DLme\Core\DefaultCtaEnabledPolicy;
use DLme\Core\InMemorySellerAvailabilityRepository;
use DLme\Core\InMemoryTimezoneProvider;
use DLme\Core\PageType;
use DLme\Core\ProductContext;

beforeEach(function () {
    $this->repository = new InMemorySellerAvailabilityRepository();
    $this->timezoneProvider = new InMemoryTimezoneProvider();
    $this->availabilityService = new AvailabilityService($this->repository, $this->timezoneProvider);
    $this->styleConfig = new CtaStyleConfig();
    $this->enabledPolicy = new DefaultCtaEnabledPolicy();
    $this->ctaService = new CallToActionService(
        $this->availabilityService,
        $this->styleConfig,
        $this->enabledPolicy
    );
});

describe('CallToActionService - Seller Landing', function () {
    test('available now seller landing shows instant checkout', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, true);

        $decision = $this->ctaService->decideForSellerLanding($sellerId);

        expect($decision->type)->toBe(CtaType::INSTANT_CHECKOUT);
        expect($decision->availabilityStatus)->toBe(AvailabilityStatus::AVAILABLE_NOW);
        expect($decision->config->label)->toBe('Talk to me now');
        expect($decision->config->variant)->toBe(ButtonVisualVariant::PRIMARY);
        expect($decision->config->cssKey)->toBe('btn-instant-landing');
        expect($decision->enabled)->toBeTrue();
    });

    test('offline seller landing shows contact seller', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, false);

        $decision = $this->ctaService->decideForSellerLanding($sellerId);

        expect($decision->type)->toBe(CtaType::CONTACT_SELLER);
        expect($decision->availabilityStatus)->toBe(AvailabilityStatus::OFFLINE);
        expect($decision->config->label)->toBe('Contact me');
        expect($decision->config->variant)->toBe(ButtonVisualVariant::SECONDARY);
        expect($decision->config->cssKey)->toBe('btn-contact-landing');
        expect($decision->enabled)->toBeFalse(); // Default policy: only AVAILABLE_NOW + instant
    });
});

describe('CallToActionService - Product Page', function () {
    test('non-instant product is disabled', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, true);

        $product = new ProductContext(
            productId: 456,
            isInstantConsult: false,
            sku: 'PROD-001',
            price: 99.99
        );

        $decision = $this->ctaService->decideForProduct($sellerId, $product);

        expect($decision->type)->toBe(CtaType::DISABLED);
        expect($decision->enabled)->toBeFalse();
        expect($decision->config->label)->toBe('Learn More');
        expect($decision->config->variant)->toBe(ButtonVisualVariant::DISABLED);
        expect($decision->config->cssKey)->toBe('btn-disabled-product');
    });

    test('instant product with available seller shows instant checkout', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, true);

        $product = new ProductContext(
            productId: 456,
            isInstantConsult: true,
            sku: 'INSTANT-001',
            price: 149.99
        );

        $decision = $this->ctaService->decideForProduct($sellerId, $product);

        expect($decision->type)->toBe(CtaType::INSTANT_CHECKOUT);
        expect($decision->availabilityStatus)->toBe(AvailabilityStatus::AVAILABLE_NOW);
        expect($decision->config->label)->toBe('Buy Now');
        expect($decision->config->variant)->toBe(ButtonVisualVariant::PRIMARY);
        expect($decision->config->cssKey)->toBe('btn-instant-product');
        expect($decision->enabled)->toBeTrue();
    });

    test('instant product with offline seller shows contact seller', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, false);

        $product = new ProductContext(
            productId: 456,
            isInstantConsult: true,
            sku: 'INSTANT-001',
            price: 149.99
        );

        $decision = $this->ctaService->decideForProduct($sellerId, $product);

        expect($decision->type)->toBe(CtaType::CONTACT_SELLER);
        expect($decision->availabilityStatus)->toBe(AvailabilityStatus::OFFLINE);
        expect($decision->config->label)->toBe('Notify Me');
        expect($decision->config->variant)->toBe(ButtonVisualVariant::SECONDARY);
        expect($decision->config->cssKey)->toBe('btn-notify-product');
        expect($decision->enabled)->toBeFalse();
    });

    test('all combinations of availability and product type', function () {
        $cases = [
            // [isInstant, isAvailable, expectedType, expectedEnabled]
            [true, true, CtaType::INSTANT_CHECKOUT, true],
            [true, false, CtaType::CONTACT_SELLER, false],
            [false, true, CtaType::DISABLED, false],
            [false, false, CtaType::DISABLED, false],
        ];

        foreach ($cases as [$isInstant, $isAvailable, $expectedType, $expectedEnabled]) {
            $sellerId = 123;
            $this->repository->setAvailableNowFlag($sellerId, $isAvailable);

            $product = new ProductContext(
                productId: 456,
                isInstantConsult: $isInstant
            );

            $decision = $this->ctaService->decideForProduct($sellerId, $product);

            expect($decision->type)->toBe($expectedType);
            expect($decision->enabled)->toBe($expectedEnabled);
        }
    });
});

describe('CallToActionService - Custom Enabled Policy', function () {
    test('can use custom enabled policy', function () {
        // Create custom policy that always returns true
        $customPolicy = new class implements \DLme\Core\CtaEnabledPolicy {
            public function isEnabled(
                \DLme\Core\AvailabilityStatus $status,
                bool $isInstantConsult,
                \DLme\Core\PageType $pageType
            ): bool {
                return true; // Always enabled
            }
        };

        $ctaService = new CallToActionService(
            $this->availabilityService,
            $this->styleConfig,
            $customPolicy
        );

        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, false);

        $decision = $ctaService->decideForSellerLanding($sellerId);

        expect($decision->type)->toBe(CtaType::CONTACT_SELLER);
        expect($decision->enabled)->toBeTrue(); // Custom policy says true
    });
});
