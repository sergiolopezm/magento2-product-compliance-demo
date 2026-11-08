<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;
use Selopez\ProductCompliance\Api\ComplianceCheckerInterface;
use Selopez\ProductCompliance\Api\Data\ComplianceResultInterface;
use Selopez\ProductCompliance\Api\Data\ViolationInterface;
use WeakMap;

class ComplianceEnforcer
{
    private const CHECKED_FLAG = '_selopez_compliance_checked';

    /**
     * @var WeakMap<ProductInterface, bool>
     */
    private readonly WeakMap $checkedProducts;

    /**
     * @param ComplianceCheckerInterface $checker
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ComplianceCheckerInterface $checker,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
        $this->checkedProducts = new WeakMap();
    }

    public function enforce(ProductInterface $product): void
    {
        // The repository copies product data before dispatching the model event, so the flag must survive that copy.
        $alreadyChecked = isset($this->checkedProducts[$product])
            || ($product instanceof DataObject && $product->getData(self::CHECKED_FLAG) === true);
        if ($alreadyChecked) {
            return;
        }
        $result = $this->checker->check($product);
        if ($result->getEnabled() && !$result->getValid()) {
            $this->enforceViolations($result);
        }
        if ($product instanceof DataObject) {
            $product->setData(self::CHECKED_FLAG, true);
        }
        $this->checkedProducts[$product] = true;
    }

    public function release(ProductInterface $product): void
    {
        if ($product instanceof DataObject) {
            $product->unsetData(self::CHECKED_FLAG);
            // The repository persists a copy, so the original instance with the same SKU must also be released.
            foreach ($this->checkedProducts as $checkedProduct => $checked) {
                $sameSku = $checkedProduct instanceof DataObject
                    && $checkedProduct->getData('sku') === $product->getData('sku');
                if ($sameSku) {
                    $checkedProduct->unsetData(self::CHECKED_FLAG);
                    unset($this->checkedProducts[$checkedProduct]);
                }
            }
        }
        unset($this->checkedProducts[$product]);
    }

    private function enforceViolations(ComplianceResultInterface $result): void
    {
        $messages = array_map(
            static fn (ViolationInterface $violation): string => $violation->getMessage(),
            $result->getViolations()
        );
        if ($this->config->getMode() === Config::MODE_BLOCK) {
            throw new LocalizedException(new Phrase('Product compliance failed: %1', [implode(' ', $messages)]));
        }
        $this->logger->warning('Product compliance validation failed.', [
            'sku' => $result->getSku(),
            'violations' => array_map(static fn (ViolationInterface $violation): array => [
                'code' => $violation->getCode(),
                'attribute' => $violation->getAttribute(),
                'message' => $violation->getMessage(),
            ], $result->getViolations()),
        ]);
    }
}
