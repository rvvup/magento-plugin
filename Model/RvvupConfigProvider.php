<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

class RvvupConfigProvider
{
    /**
     * @TODO this should be pulled in dynamically. Perhaps use `rvvup` as a default then extend the payment adaptor
     * ta accept updates
     */
    public const CODE = 'rvvup';

    public const GROUP_CODE =  self::CODE .'_group';
}
