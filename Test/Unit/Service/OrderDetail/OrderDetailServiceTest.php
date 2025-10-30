<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service\OrderDetail;

use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Service\OrderDetail\OrderDetailService;
use Rvvup\Payments\Test\Unit\Fixtures\OrderAddressFixture;
use Rvvup\Payments\Test\Unit\Fixtures\OrderFixture;
use Rvvup\Payments\Test\Unit\Fixtures\OrderPaymentFixture;

class OrderDetailServiceTest extends TestCase
{
    /** @var OrderDetailService */
    private $orderDetailsService;

    private $rvvupData = [
        "id" => "OR123",
        "customer" => [
            "givenName" => "Liam",
            "surname" => "George",
            "email" => "test@rvvup.com"
        ],
        "billingAddress" => [
            "name" => "Joe Consumer",
            "line1" => "1 Rvvup Road",
            "line2" => "Test District",
            "city" => "Testville",
            "state" => "Testshire",
            "postcode" => "TE5 7ST",
            "country" => "GB",
            "phoneNumber" => "01234567890"
        ],
        "shippingAddress" => [
            "name" => "Joe Consumer",
            "line1" => "1 Rvvup Road",
            "line2" => "Test District",
            "city" => "Testville",
            "state" => "Testshire",
            "postcode" => "TE5 7ST",
            "country" => "GB",
            "phoneNumber" => "01234567890"
        ],
        "payments" => [
            [
                "id" => "PA123"
            ]
        ]
    ];
    private $billingAddress;
    private $shippingAddress;
    private $payment;

    protected function setUp(): void
    {
        $this->orderDetailsService = new OrderDetailService();
        $this->billingAddress = OrderAddressFixture::builder($this)
            ->withMethod("getFirstname", "Joe")
            ->withMethod("getLastname", "Consumer")
            ->withMethod("getStreet", [$this->rvvupData["billingAddress"]["line1"], $this->rvvupData["billingAddress"]["line2"]])
            ->withMethod("getPostcode", $this->rvvupData["billingAddress"]["postcode"])
            ->withMethod("getCity", $this->rvvupData["billingAddress"]["city"])
            ->withMethod("getRegion", $this->rvvupData["shippingAddress"]["state"])
            ->withMethod("getTelephone", $this->rvvupData["billingAddress"]["phoneNumber"])
            ->withMethod("getEmail", $this->rvvupData['customer']['email'])
            ->build();
        $this->shippingAddress = OrderAddressFixture::builder($this)
            ->withMethod("getFirstname", "Joe")
            ->withMethod("getLastname", "Consumer")
            ->withMethod("getStreet", [$this->rvvupData["shippingAddress"]["line1"], $this->rvvupData["shippingAddress"]["line2"]])
            ->withMethod("getPostcode", $this->rvvupData["shippingAddress"]["postcode"])
            ->withMethod("getCity", $this->rvvupData["shippingAddress"]["city"])
            ->withMethod("getRegion", $this->rvvupData["shippingAddress"]["state"])
            ->withMethod("getTelephone", $this->rvvupData["shippingAddress"]["phoneNumber"])
            ->withMethod("getEmail", $this->rvvupData['customer']['email'])
            ->build();
        $this->payment = OrderPaymentFixture::builder($this)->withPaymentMethod("rvvup_ZOPA_RETAIL_FINANCE")->build();

    }

    public function testOrderIsUnchangedWhenPaymentIsNull()
    {
        $source = OrderFixture::builder($this)->withOrderPayment(null)->build();
        $source->expects($this->never())->method('setCustomerFirstname');
        $source->expects($this->never())->method('addStatusToHistory');

        $this->orderDetailsService->syncOrderWithRvvupData($source, $this->rvvupData, "test");

    }

    public function testOrderIsUnchangedWhenPaymentIsNotZrf()
    {
        $payment = OrderPaymentFixture::builder($this)->withPaymentMethod("rvvup_TEST")->build();
        $source = OrderFixture::builder($this)->withOrderPayment($payment)->build();

        $source->expects($this->never())->method('setCustomerFirstname');
        $source->expects($this->never())->method('addStatusToHistory');

        $this->orderDetailsService->syncOrderWithRvvupData($source, $this->rvvupData, "test");
    }


    public function testOrderIsUnchangedWhenRvvupDataIsEmpty()
    {
        $source = OrderFixture::builder($this)->withOrderPayment($this->payment)->build();
        $source->expects($this->never())->method('setCustomerFirstname');
        $source->expects($this->never())->method('addStatusToHistory');


        $this->orderDetailsService->syncOrderWithRvvupData($source, [], "test");
    }


    public function testNoChangesToDataOrHistoryIfDataIsSame()
    {
        $source = OrderFixture::builder($this)->withOrderPayment($this->payment)
            ->withCustomerFirstname($this->rvvupData['customer']['givenName'])
            ->withCustomerLastname($this->rvvupData['customer']['surname'])
            ->withCustomerEmail($this->rvvupData['customer']['email'])
            ->withBillingAddress($this->billingAddress)
            ->withShippingAddress($this->shippingAddress)
            ->build();

        $source->expects($this->never())->method('setCustomerFirstname');
        $source->expects($this->never())->method('addStatusToHistory');

        $this->orderDetailsService->syncOrderWithRvvupData($source, $this->rvvupData, "test");
    }


    public function testOrderChanges()
    {
        $billingAddress = OrderAddressFixture::builder($this)
            ->withMethod("getFirstname", "Test")
            ->withMethod("getLastname", "User")
            ->withMethod("getStreet", ["123 Test St"])
            ->withMethod("getPostcode", "DE1 1RT")
            ->withMethod("getCity", "Liverpool")
            ->withMethod("getTelephone", "0111111")
            ->withMethod("getEmail", "new@rvvup.com")
            ->build();
        $shippingAddress = OrderAddressFixture::builder($this)
            ->withMethod("getFirstname", "Test")
            ->withMethod("getLastname", "User")
            ->withMethod("getStreet", ["123 Test St"])
            ->withMethod("getPostcode", "DE1 1RT")
            ->withMethod("getCity", "Liverpool")
            ->withMethod("getTelephone", "0111111")
            ->withMethod("getEmail", "new@rvvup.com")
            ->build();
        $source = OrderFixture::builder($this)->withOrderPayment($this->payment)
            ->withCustomerFirstname("John")
            ->withCustomerLastname("Smith")
            ->withCustomerEmail("new@rvvup.com")
            ->withBillingAddress($billingAddress)
            ->withShippingAddress($shippingAddress)
            ->build();

        $source->expects($this->once())->method('setCustomerFirstname')->with("Liam");
        $source->expects($this->once())->method('setCustomerLastname')->with("George");
        $source->expects($this->once())->method('addStatusToHistory')
            ->with(
                $source->getStatus(),
                "Order details have CHANGED because the customer changed them during the DivideBuy Checkout Flow:<br /><br />".
                "- <strong>Customer First Name</strong> changed from '<strong>John</strong>' to '<strong>Liam</strong>'" .
                "<br />- <strong>Customer Last Name</strong> changed from '<strong>Smith</strong>' to '<strong>George</strong>'" .
                "<br />- <strong>Billing Address First Name</strong> changed from '<strong>Test</strong>' to '<strong>Joe</strong>'" .
                "<br />- <strong>Billing Address Last Name</strong> changed from '<strong>User</strong>' to '<strong>Consumer</strong>'" .
                "<br />- <strong>Billing Address Street</strong> changed from '<strong>123 Test St</strong>' to '<strong>1 Rvvup Road, Test District</strong>'" .
                "<br />- <strong>Billing Address Postcode</strong> changed from '<strong>DE1 1RT</strong>' to '<strong>TE5 7ST</strong>'" .
                "<br />- <strong>Billing Address State</strong> changed from '<strong></strong>' to '<strong>Testshire</strong>'" .
                "<br />- <strong>Billing Address City</strong> changed from '<strong>Liverpool</strong>' to '<strong>Testville</strong>'" .
                "<br />- <strong>Billing Address Phone</strong> changed from '<strong>0111111</strong>' to '<strong>01234567890</strong>'" .
                "<br />- <strong>Shipping Address First Name</strong> changed from '<strong>Test</strong>' to '<strong>Joe</strong>'" .
                "<br />- <strong>Shipping Address Last Name</strong> changed from '<strong>User</strong>' to '<strong>Consumer</strong>'" .
                "<br />- <strong>Shipping Address Street</strong> changed from '<strong>123 Test St</strong>' to '<strong>1 Rvvup Road, Test District</strong>'" .
                "<br />- <strong>Shipping Address Postcode</strong> changed from '<strong>DE1 1RT</strong>' to '<strong>TE5 7ST</strong>'" .
                "<br />- <strong>Shipping Address State</strong> changed from '<strong></strong>' to '<strong>Testshire</strong>'" .
                "<br />- <strong>Shipping Address City</strong> changed from '<strong>Liverpool</strong>' to '<strong>Testville</strong>'" .
                "<br />- <strong>Shipping Address Phone</strong> changed from '<strong>0111111</strong>' to '<strong>01234567890</strong>'" .
                "<br />- <strong>Customer Email</strong> changed from '<strong>new@rvvup.com</strong>' to '<strong>test@rvvup.com</strong>'" .
                "<br />- <strong>Billing Email</strong> changed from '<strong>new@rvvup.com</strong>' to '<strong>test@rvvup.com</strong>'" .
                "<br />- <strong>Shipping Email</strong> changed from '<strong>new@rvvup.com</strong>' to '<strong>test@rvvup.com</strong>'"


            );
        $this->orderDetailsService->syncOrderWithRvvupData($source, $this->rvvupData, "test");
    }

    public function testBillingNameSplitting()
    {
        $this->rvvupData["billingAddress"]["name"] = "John Middle Smith";

        $billingAddress = OrderAddressFixture::builder($this)
            ->withMethod("getFirstname", "Test")
            ->withMethod("getLastname", "Test")
            ->withMethod("getStreet", [$this->rvvupData["billingAddress"]["line1"], $this->rvvupData["billingAddress"]["line2"]])
            ->withMethod("getPostcode", $this->rvvupData["billingAddress"]["postcode"])
            ->withMethod("getCity", $this->rvvupData["billingAddress"]["city"])
            ->withMethod("getRegion", $this->rvvupData["shippingAddress"]["state"])
            ->withMethod("getTelephone", $this->rvvupData["billingAddress"]["phoneNumber"])
            ->withMethod("getEmail", $this->rvvupData['customer']['email'])
            ->build();

        $source = OrderFixture::builder($this)->withOrderPayment($this->payment)
            ->withCustomerFirstname($this->rvvupData['customer']['givenName'])
            ->withCustomerLastname($this->rvvupData['customer']['surname'])
            ->withCustomerEmail($this->rvvupData['customer']['email'])
            ->withBillingAddress($billingAddress)
            ->withShippingAddress($this->shippingAddress)
            ->build();

        $billingAddress->expects($this->once())->method('setFirstname')->with('John');
        $billingAddress->expects($this->once())->method('setLastname')->with('Middle Smith');

        $this->orderDetailsService->syncOrderWithRvvupData($source, $this->rvvupData, "test");
    }


    public function testShippingNameSplitting()
    {
        $this->rvvupData["shippingAddress"]["name"] = "John Middle Smith";

        $shippingAddress = OrderAddressFixture::builder($this)
            ->withMethod("getFirstname", "Test")
            ->withMethod("getLastname", "Test")
            ->withMethod("getStreet", [$this->rvvupData["shippingAddress"]["line1"], $this->rvvupData["shippingAddress"]["line2"]])
            ->withMethod("getPostcode", $this->rvvupData["shippingAddress"]["postcode"])
            ->withMethod("getCity", $this->rvvupData["shippingAddress"]["city"])
            ->withMethod("getRegion", $this->rvvupData["shippingAddress"]["state"])
            ->withMethod("getTelephone", $this->rvvupData["shippingAddress"]["phoneNumber"])
            ->withMethod("getEmail", $this->rvvupData['customer']['email'])
            ->build();

        $source = OrderFixture::builder($this)->withOrderPayment($this->payment)
            ->withCustomerFirstname($this->rvvupData['customer']['givenName'])
            ->withCustomerLastname($this->rvvupData['customer']['surname'])
            ->withCustomerEmail($this->rvvupData['customer']['email'])
            ->withBillingAddress($this->billingAddress)
            ->withShippingAddress($shippingAddress)
            ->build();

        $shippingAddress->expects($this->once())->method('setFirstname')->with('John');
        $shippingAddress->expects($this->once())->method('setLastname')->with('Middle Smith');

        $this->orderDetailsService->syncOrderWithRvvupData($source, $this->rvvupData, "test");
    }

}
