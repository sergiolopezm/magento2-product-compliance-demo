<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;
use Selopez\ProductCompliance\Model\Config;

class Mode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_BLOCK, 'label' => new Phrase('Block save')],
            ['value' => Config::MODE_WARN, 'label' => new Phrase('Log warning')],
        ];
    }
}
