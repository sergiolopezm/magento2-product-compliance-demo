<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model;

use Magento\Catalog\Api\Data\ProductInterface;

class CategoryIdsResolver
{
    public function resolve(ProductInterface $product): array
    {
        $categoryIds = $product->getCustomAttribute('category_ids');
        if ($categoryIds !== null) {
            return array_map('intval', (array) $categoryIds->getValue());
        }
        $links = $product->getExtensionAttributes()?->getCategoryLinks() ?? [];
        return array_map(static fn ($link): int => (int) $link->getCategoryId(), $links);
    }
}
