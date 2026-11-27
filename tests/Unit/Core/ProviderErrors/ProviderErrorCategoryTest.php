<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\ProviderErrors;

use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorCategory;
use PHPUnit\Framework\TestCase;

/** Tests parsing and recognition of provider error categories. */
class ProviderErrorCategoryTest extends TestCase
{
    public function testRecognizesDocumentedCategories(): void
    {
        $category = ProviderErrorCategory::fromString(ProviderErrorCategory::VALIDATION);

        $this->assertSame('VALIDATION', $category->value());
        $this->assertTrue($category->isKnown());
        $this->assertTrue($category->equals(ProviderErrorCategory::fromString('VALIDATION')));
        $this->assertFalse($category->equals(ProviderErrorCategory::fromString('AUTH')));
    }

    public function testPreservesUnknownCategoryText(): void
    {
        $category = ProviderErrorCategory::fromString(' future-category ');

        $this->assertSame(' future-category ', $category->value());
        $this->assertFalse($category->isKnown());
    }
}
