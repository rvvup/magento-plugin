<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

class ComplexProductTypePool
{
    /**
     * @var array
     */
    private $productTypes;

    /**
     * @param array $productTypes
     */
    public function __construct(array $productTypes = [])
    {
        $this->productTypes = $productTypes;
    }

    /**
     * @return array
     */
    public function getProductTypes(): array
    {
        return $this->productTypes;
    }
}
