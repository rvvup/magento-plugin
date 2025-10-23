<?php

namespace Rvvup\Payments\Service\OrderDetail;

use Magento\Sales\Api\Data\OrderInterface;

class OrderDetailService
{

    /**
     * Sync order with details from rvvup order data (response in API).
     * This currently only impacts orders placed with Rvvup Zopa Retail Finance method, and other methods
     * are ignored.
     * @param OrderInterface $order
     * @param array|null $rvvupData
     * @return OrderInterface
     */
    public function syncOrderWithRvvupData(OrderInterface $order, ?array $rvvupData): OrderInterface
    {
        if ($order->getPayment() === null || $order->getPayment()->getMethod() !== "rvvup_ZOPA_RETAIL_FINANCE") {
            return $order;
        }
        if (empty($rvvupData)) {
            return $order;
        }
        $changes = [];

        $customerFirstName = $rvvupData['customer']['givenName'] ?? '';
        $customerSurname = $rvvupData['customer']['surname'] ?? '';

        if ($order->getCustomerFirstname() !== $customerFirstName) {
            $changes[] = [
                "field" => "Customer First Name",
                "from" => $order->getCustomerFirstname(),
                "to" => $customerFirstName];
            $order->setCustomerFirstname($customerFirstName);
        }
        if ($order->getCustomerLastname() !== $customerSurname) {
            $changes[] = [
                "field" => "Customer Last Name",
                "from" => $order->getCustomerLastname(),
                "to" => $customerSurname];
            $order->setCustomerLastname($customerSurname);
        }

        // Set billing address
        $billingAddress = $order->getBillingAddress();
        if (isset($rvvupData['billingAddress'])) {
            $nameParts = !empty($rvvupData['billingAddress']['name']) ?
                explode(' ', $rvvupData['billingAddress']['name'], 2) :
                [$customerFirstName, $customerSurname];

            // Track billing address changes
            $newFirstName = $nameParts[0];
            $newLastName = $nameParts[1] ?? '';
            if ($billingAddress->getFirstname() !== $newFirstName) {
                $changes[] = [
                    "field" => "Billing Address First Name",
                    "from" => $billingAddress->getFirstname(),
                    "to" => $newFirstName];
                $billingAddress->setFirstname($newFirstName);
            }
            if ($billingAddress->getLastname() !== $newLastName) {
                $changes[] = [
                    "field" => "Billing Address Last Name",
                    "from" => $billingAddress->getLastname(),
                    "to" => $newLastName];
                $billingAddress->setLastname($newLastName);
            }

            $newStreet = [$rvvupData["billingAddress"]["line1"]];
            if (!empty($rvvupData["billingAddress"]["line2"])) {
                $newStreet[] = $rvvupData["billingAddress"]["line2"];
            }
            $currentStreet = $billingAddress->getStreet();
            $currentStreetString = implode(', ', $currentStreet);
            $newStreetString = implode(', ', $newStreet);
            if ($currentStreetString !== $newStreetString) {
                $changes[] = [
                    "field" => "Billing Address Street",
                    "from" => $currentStreetString,
                    "to" => $newStreetString];
                $billingAddress->setStreet($newStreet);
            }

            if ($billingAddress->getPostcode() !== $rvvupData["billingAddress"]["postcode"]) {
                $changes[] = [
                    "field" => "Billing Address Postcode",
                    "from" => $billingAddress->getPostcode(),
                    "to" => $rvvupData["billingAddress"]["postcode"]];
                $billingAddress->setPostcode($rvvupData["billingAddress"]["postcode"]);
            }

            if ($billingAddress->getRegion() !== $rvvupData["billingAddress"]["state"]) {
                $changes[] = [
                    "field" => "Billing Address State",
                    "from" => $billingAddress->getRegion(),
                    "to" => $rvvupData["billingAddress"]["state"]];
                $billingAddress->setRegion($rvvupData["billingAddress"]["state"]);
            }

            if ($billingAddress->getCity() !== $rvvupData["billingAddress"]["city"]) {
                $changes[] = [
                    "field" => "Billing Address City",
                    "from" => $billingAddress->getCity(),
                    "to" => $rvvupData["billingAddress"]["city"]];
                $billingAddress->setCity($rvvupData["billingAddress"]["city"]);
            }

            if ($billingAddress->getTelephone() !== $rvvupData["billingAddress"]["phoneNumber"]) {
                $changes[] = [
                    "field" => "Billing Address Phone",
                    "from" => $billingAddress->getTelephone(),
                    "to" => $rvvupData["billingAddress"]["phoneNumber"]];
                $billingAddress->setTelephone($rvvupData["billingAddress"]["phoneNumber"]);
            }
        }

        $shippingAddress = $order->getShippingAddress();
        if (isset($rvvupData['shippingAddress'])) {
            $nameParts = !empty($rvvupData['shippingAddress']['name']) ?
                explode(' ', $rvvupData['shippingAddress']['name'], 2) :
                [$customerFirstName, $customerSurname];

            // Track shipping address changes
            $newFirstName = $nameParts[0];
            $newLastName = $nameParts[1] ?? '';
            if ($shippingAddress->getFirstname() !== $newFirstName) {
                $changes[] = [
                    "field" => "Shipping Address First Name",
                    "from" => $shippingAddress->getFirstname(),
                    "to" => $newFirstName];
                $shippingAddress->setFirstname($newFirstName);
            }
            if ($shippingAddress->getLastname() !== $newLastName) {
                $changes[] = [
                    "field" => "Shipping Address Last Name",
                    "from" => $shippingAddress->getLastname(),
                    "to" => $newLastName];
                $shippingAddress->setLastname($newLastName);
            }

            $newStreet = [$rvvupData["shippingAddress"]["line1"]];
            if (!empty($rvvupData["shippingAddress"]["line2"])) {
                $newStreet[] = $rvvupData["shippingAddress"]["line2"];
            }
            $currentStreet = $shippingAddress->getStreet();
            $currentStreetString = implode(', ', $currentStreet);
            $newStreetString = implode(', ', $newStreet);
            if ($currentStreetString !== $newStreetString) {
                $changes[] = [
                    "field" => "Shipping Address Street",
                    "from" => $currentStreetString,
                    "to" => $newStreetString];
                $shippingAddress->setStreet($newStreet);
            }

            if ($shippingAddress->getPostcode() !== $rvvupData["shippingAddress"]["postcode"]) {
                $changes[] = [
                    "field" => "Shipping Address Postcode",
                    "from" => $shippingAddress->getPostcode(),
                    "to" => $rvvupData["shippingAddress"]["postcode"]];
                $shippingAddress->setPostcode($rvvupData["shippingAddress"]["postcode"]);
            }

            if ($shippingAddress->getRegion() !== $rvvupData["shippingAddress"]["state"]) {
                $changes[] = [
                    "field" => "Shipping Address State",
                    "from" => $shippingAddress->getRegion(),
                    "to" => $rvvupData["shippingAddress"]["state"]];
                $shippingAddress->setRegion($rvvupData["shippingAddress"]["state"]);
            }

            if ($shippingAddress->getCity() !== $rvvupData["shippingAddress"]["city"]) {
                $changes[] = [
                    "field" => "Shipping Address City",
                    "from" => $shippingAddress->getCity(),
                    "to" => $rvvupData["shippingAddress"]["city"]];
                $shippingAddress->setCity($rvvupData["shippingAddress"]["city"]);
            }

            if ($shippingAddress->getTelephone() !== $rvvupData["shippingAddress"]["phoneNumber"]) {
                $changes[] = [
                    "field" => "Shipping Address Phone",
                    "from" => $shippingAddress->getTelephone(),
                    "to" => $rvvupData["shippingAddress"]["phoneNumber"]];
                $shippingAddress->setTelephone($rvvupData["shippingAddress"]["phoneNumber"]);
            }
        }

        // Set email
        $customerEmail = $rvvupData['customer']['email'] ?? null;
        if (!empty($customerEmail)) {
            if ($order->getCustomerEmail() !== $customerEmail) {
                $changes[] = [
                    "field" => "Customer Email",
                    "from" => $order->getCustomerEmail(),
                    "to" => $customerEmail];
                $order->setCustomerEmail($customerEmail);
            }
            if ($billingAddress->getEmail() !== $customerEmail) {
                $changes[] = [
                    "field" => "Billing Email",
                    "from" => $billingAddress->getEmail(),
                    "to" => $customerEmail];
                $billingAddress->setEmail($customerEmail);
            }
            if ($shippingAddress->getEmail() !== $customerEmail) {
                $changes[] = [
                    "field" => "Shipping Email",
                    "from" => $shippingAddress->getEmail(),
                    "to" => $customerEmail];
                $shippingAddress->setEmail($customerEmail);
            }
        }

        if (!empty($changes)) {
            $message = "Order details have CHANGED because the customer changed them " .
                "during the DivideBuy Checkout Flow:<br /><br />" .
                implode("<br />", array_map(function ($change) {
                    return "- <strong>" . $change["field"] . "</strong> " .
                        "changed from '<strong>" . $change["from"] . "</strong>' " .
                        "to '<strong>" . $change["to"] . "</strong>'";
                }, $changes));

            $order->addStatusToHistory($order->getStatus(), $message);
        }

        return $order;
    }
}
