<?php declare(strict_types=1);

namespace Rvvup\Sdk\Test\Unit;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Config\Jwt\Validator;

/**
 * @covers \Rvvup\Payments\Model\Config\Jwt\Validator
 */
class JwtConfigValidatorTest extends TestCase
{
    /** @var EncryptorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $encryptor;
    /** @var Validator */
    private $systemUnderTest;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $registry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $scopeConfig = $this->getMockBuilder(ScopeConfigInterface::class)->disableOriginalConstructor()->getMock();
        $typeList = $this->getMockBuilder(TypeListInterface::class)->disableOriginalConstructor()->getMock();
        $this->encryptor = $this->getMockBuilder(EncryptorInterface::class)->disableOriginalConstructor()->getMock();
        $this->systemUnderTest = new Validator(
            $context,
            $registry,
            $scopeConfig,
            $typeList,
            $this->encryptor,
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->systemUnderTest = null;
    }

    public function testEmptyDataIsNotSaved()
    {
        $this->systemUnderTest->setData('value', '');
        $this->encryptor->expects($this->never())->method('encrypt');
        $this->systemUnderTest->beforeSave();
    }

    public function testObscuredDataIsNotSaved()
    {
        $this->systemUnderTest->setData('value', '******');
        $this->encryptor->expects($this->never())->method('encrypt');
        $this->systemUnderTest->beforeSave();
    }

    public function testValidJwtSaves()
    {
        $this->systemUnderTest->setData('value', $this->generateValidJwt());
        $this->encryptor->expects($this->once())->method('encrypt')->with($this->generateValidJwt());
        $this->systemUnderTest->beforeSave();
    }

    public function testInvalidJwtErrors()
    {
        $this->systemUnderTest->setData('value', 'bahhhhhhhhhhh');
        $this->expectException(ValidatorException::class);
        $this->systemUnderTest->beforeSave();
    }

    public function testCorruptPayloadErrors()
    {
        $this->systemUnderTest->setData('value', 'xxx.;.xxx');
        $this->expectException(ValidatorException::class);
        $this->systemUnderTest->beforeSave();
    }


    public function testPayloadInvalidJsonErrors()
    {
        $this->systemUnderTest->setData('value', 'xxx.' . base64_encode('{xxxx}') . '.xxx');
        $this->expectException(ValidatorException::class);
        $this->systemUnderTest->beforeSave();
    }

    private function generateValidJwt(): string
    {
        $header = [
            "typ" => "JWT",
            "alg" => "HS256",
        ];
        $payload = [
            "aud" => "https://example.com/graphql",
            "password" => "this-is-a-fake-password",
            "merchantId" => "this-is-a-fake-merchant-up",
            "iat" => 1654178262,
            "username" => "this-is-a-fake-username",
        ];
        return base64_encode(json_encode($header)) .
            "." .
            base64_encode(json_encode($payload)) .
            "." .
            "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
    }
}
