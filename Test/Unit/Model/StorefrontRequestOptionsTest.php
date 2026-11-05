<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\StorefrontRequestOptions;

/**
 * @covers \Rvvup\Payments\Model\StorefrontRequestOptions
 */
class StorefrontRequestOptionsTest extends TestCase
{
    public function testItDefaultsToAFewSecondsSoAStorefrontPageIsNotHeldUp(): void
    {
        $this->assertSame(
            ['timeout' => 3.0, 'connect_timeout' => 1.0],
            (new StorefrontRequestOptions())->get()
        );
    }

    public function testItReturnsTheConfiguredTimeouts(): void
    {
        $this->assertSame(
            ['timeout' => 5.0, 'connect_timeout' => 2.0],
            (new StorefrontRequestOptions(5.0, 2.0))->get()
        );
    }

    public function testItFallsBackToTheDefaultsForZeroOrNegativeValuesBecauseGuzzleReadsThemAsNoTimeout(): void
    {
        $this->assertSame(
            ['timeout' => 3.0, 'connect_timeout' => 1.0],
            (new StorefrontRequestOptions(0.0, -1.0))->get()
        );
    }
}
