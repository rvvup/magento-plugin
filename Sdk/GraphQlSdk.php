<?php declare(strict_types=1);

// @codingStandardsIgnoreFile

namespace Rvvup\Payments\Sdk;

class GraphQlSdk
{
    const REDACTED = "***REDACTED***";
    /** @var string */
    private $endpoint;
    /** @var string */
    private $merchantId;
    /** @var string */
    private $authToken;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var bool */
    private $debug;
    /** @var string */
    private $userAgent;
    /** @var Curl */
    private $adapter;

    /**
     * @param string $endpoint
     * @param string $merchantId
     * @param string $authToken
     * @param string $userAgent
     * @param $adapter
     * @param null $logger
     * @param bool $debug
     */
    public function __construct(
        string $endpoint,
        string $merchantId,
        string $authToken,
        string $userAgent,
        $adapter,
        $logger = null,
        bool $debug = false
    ) {
        if (!$merchantId || !$authToken || !$endpoint) {
            throw new \InvalidArgumentException("Unable to initialize Rvvup SDK, missing init parameters");
        }
        $this->endpoint = $endpoint;
        $this->merchantId = $merchantId;
        $this->authToken = $authToken;
        $this->userAgent = $userAgent;
        $this->logger = $logger;
        $this->debug = $debug;
        $this->adapter = $adapter;
    }

    /**
     * @param string $cartTotal
     * @param string $currency
     * @return array
     * @throws \Exception
     */
    public function getMethods(string $cartTotal, string $currency, array $inputOptions = null): array
    {
        $query = <<<'QUERY'
query merchant ($id: ID!, $total: MoneyInput!) {
    merchant (id: $id) {
        paymentMethods (search: {includeInactive: false, total: $total}) {
            edges {
                node {
                    name,
                    displayName,
                    description,
                    summaryUrl
                    limits {
                        total {
                            min
                            max
                            currency
                        }
                        expiresAt
                    }
                }
            }
        }
    }
}
QUERY;
        $variables = [
            "id" => $this->merchantId,
            "total" => [
                "amount" => $cartTotal,
                "currency" => $currency,
            ],
        ];
        try {
            $response = $this->doRequest($query, $variables, $inputOptions);
        } catch (\Exception $e) {
            return [];
        }
        $responseMethods = $response["data"]["merchant"]["paymentMethods"]["edges"];
        $methods = [];
        foreach ($responseMethods as $responseMethod) {
            $method = $responseMethod["node"];
            $methods[] = [
                "name" => $method["name"],
                "displayName" => $method["displayName"],
                "description" => $method["description"],
                "summaryUrl" => $method["summaryUrl"],
                "limits" => $method["limits"],
            ];
        }
        return $methods;
    }

    /**
     * @param $orderData
     * @return mixed
     * @throws \Exception
     */
    public function createOrder($orderData)
    {
        $query = <<<'QUERY'
mutation OrderCreate($input: OrderCreateInput!) {
    orderCreate(input: $input) {
        id
        status
        redirectToCheckoutUrl
        dashboardUrl
    }
}
QUERY;
        return $this->doRequest($query, $orderData);
    }

    /**
     * @param $orderId
     * @return false|mixed
     * @throws \Exception
     */
    public function getOrder($orderId)
    {
        $query = <<<'QUERY'
query order ($id: ID!, $merchant: IdInput!) {
    order (id: $id, merchant: $merchant) {
        id
        externalReference
        redirectToStoreUrl
        redirectToCheckoutUrl
        status
        dashboardUrl
    }
}
QUERY;
        $variables = [
            "id" => $orderId,
            "merchant" => [
                "id" => $this->merchantId,
            ],
        ];
        $response = $this->doRequest($query, $variables);
        if (is_array($response) && isset($response["data"]["order"])) {
            return $response["data"]["order"];
        }
        return false;
    }

    /**
     * @param $orderId
     * @param $amount
     * @param $reason
     * @param $idempotency
     * @return false|mixed
     * @throws \Exception
     */
    public function refundOrder($orderId, $amount, $reason, $idempotency)
    {
        $query = <<<'QUERY'
mutation orderRefund ($input: OrderRefundInput!) {
    orderRefund (input: $input) {
        id
        externalReference
        payments {
          refunds {
            id
            status
            reason
          }
        }
    }
}
QUERY;
        $variables = [
            "input" => [
                "id" => $orderId,
                "merchant" => [
                    "id" => $this->merchantId,
                ],
                "amount" => [
                    "amount" => $amount,
                    "currency" => "GBP",
                ],
                "reason" => $reason,
                "idempotencyKey" => $idempotency,
            ],
        ];
        $response = $this->doRequest($query, $variables);

        if (is_array($response) && isset($response["data"]["orderRefund"])) {
            return $response["data"]["orderRefund"];
        }
        return false;
    }

    /**
     * Check if current credentials are valid and working
     *
     * @return bool
     * @throws \Exception
     */
    public function ping(): bool
    {
        $query = <<<QUERY
query ping {
  ping {
    pong
  }
}
QUERY;
        $response = $this->doRequest($query);
        if (is_array($response) && isset($response["data"]["ping"]["pong"])) {
            return true;
        }
        return false;
    }

    /**
     * Update the webhook URL in the payments backend
     *
     * @param string $url
     * @return void
     * @throws \Exception
     */
    public function registerWebhook(string $url): void
    {
        $query = <<<'QUERY'
mutation merchantWebhookCreate($input: WebhookCreateInput!) {
	merchantWebhookCreate(input: $input) {
		url
	}
}
QUERY;
        $variables = [
            "input" => [
                "url" => $url,
                "merchant" => [
                    "id" => $this->merchantId,
                ],
            ],
        ];

        $response = $this->doRequest($query, $variables);
        if (isset($response["data"]["merchantWebhookCreate"]["url"]) &&
            $response["data"]["merchantWebhookCreate"]["url"] === $url) {
            return;
        }
        throw new \Exception('Response does not match specified URL');
    }

    /**
     * @param $query
     * @param null $variables
     * @param array|null $inputOptions
     * @return mixed
     * @throws \Exception
     */
    private function doRequest($query, $variables = null, array $inputOptions = null)
    {
        $data = ["query" => $query];
        if ($variables !== null) {
            $data["variables"] = $variables;
        }
        $options = [
            "json" => $data,
            "headers" => [
                "Content-Type" => "application/json; charset=utf-8",
                "Accept" => "application/json",
                "Authorization" => "Basic " . $this->authToken,
                "User-Agent" => $this->userAgent,
            ],
        ];
        if ($this->debug) {
            $debug = fopen('php://memory', 'r+');
            $options['debug'] = $debug;
        }
        if ($inputOptions !== null) {
            $options = array_merge($options, $inputOptions);
        }
        $response = $this->adapter->request("POST", $this->endpoint, $options);
        //rewind($debug);
        //$debugOutput = stream_get_contents($debug);
        //fclose($debug);
        $request = $this->sanitiseRequestBody($data);
        $this->sanitiseRequestHeaders($request, $response);
        $this->formatResponseHeaders($response);
        $body = (string) $response->getBody();
        $responseCode = $response->getStatusCode();
        if ($responseCode === 200) {
            $processed = json_decode($body, true);
            if (isset($processed["errors"])) {
                $this->log("GraphQL response error", $request, $response);
                $errors = $processed["errors"];
                if (count($errors) > 1) {
                    $errorString = '';
                    foreach ($errors as $key => $error) {
                        $errorString .= sprintf('%s: %s', ++$key, $error["message"]);
                    }
                } else {
                    $errorString = $errors[0]["message"];
                }
                throw new \Exception($errorString);
            }
            if ($this->debug) {
                $this->log("Successful GraphQL request", $request, $response);
            }
            return $processed;
        }
        //Unexpected HTTP response code
        $this->log('Unexpected HTTP response code', $request, $response);
        throw new \Exception("Unexpected HTTP response code");
    }

    /**
     * @param string $message
     * @param $request
     * @param $response
     * @return void
     */
    private function log(string $message, $request, $response): void
    {
        if ($this->logger) {
            $this->logger->debug($message, [
                "response_code" => $response->getStatusCode(),
                "request_payload" => $request,
                "response_headers" => $response->getHeaders(),
                "response_body" => (string) $response->getBody(),
            ]);
        }
    }

    private function sanitiseRequestBody($request)
    {
        $redactableKeys = ["customer", "billingAddress", "shippingAddress"];
        if (!isset($request["variables"]["input"])) {
            return $request;
        }
        foreach ($request["variables"]["input"] as $key => $value) {
            if (in_array($key, $redactableKeys)) {
                $request["variables"]["input"][$key] = self::REDACTED;
            }
        }
        return $request;
    }

    private function sanitiseRequestHeaders($request, $response)
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode === 0 || $statusCode === 403) {
            return;
        }
        return;
        $matches = preg_replace(
            "/Authorization: Basic (\S+)/",
            "Authorization: Basic " . self::REDACTED,
            $response->debug["request_header"]
        );
        if (is_string($matches)) {
            $response->debug["request_header"] = $matches;
        }
    }

    private function formatResponseHeaders($response)
    {
        return;
        $response->getHeaders();
        $headers = $response->response_headers;
        $formattedHeaders = "";
        foreach ($headers as $type => $header) {
            foreach ($header as $line) {
                $formattedHeaders .= "$type: $line" . PHP_EOL;
            }
        }
        $response->response_headers = $formattedHeaders;
    }
}
