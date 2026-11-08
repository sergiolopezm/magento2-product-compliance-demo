<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit;

use Magento\Catalog\Api\Data\CategoryLinkInterface;
use Magento\Framework\Api\ExtensionAttributesInterface;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\CategoryIdsResolver;

final class CategoryIdsResolverTest extends TestCase
{
    use ProductFixture;

    public function testPrefersSubmittedCategoryIdsIncludingEmptyAssignments(): void
    {
        $resolver = new CategoryIdsResolver();
        self::assertSame([12, 34], $resolver->resolve($this->product(['category_ids' => ['12', 34]])));
        self::assertSame([], $resolver->resolve($this->product(['category_ids' => []])));
    }

    public function testReadsCategoryLinksFromExtensionAttributes(): void
    {
        $link = $this->createMock(CategoryLinkInterface::class);
        $link->method('getCategoryId')->willReturn('34');
        $extension = $this->getMockBuilder(ExtensionAttributesInterface::class)
            ->addMethods(['getCategoryLinks'])->getMock();
        $extension->method('getCategoryLinks')->willReturn([$link]);
        $product = $this->product();
        $product->method('getExtensionAttributes')->willReturn($extension);
        self::assertSame([34], (new CategoryIdsResolver())->resolve($product));
    }

    public function testMissingAssignmentsAreEmpty(): void
    {
        self::assertSame([], (new CategoryIdsResolver())->resolve($this->product()));
    }
}
