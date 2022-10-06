<?php declare(strict_types=1);
// @codingStandardsIgnoreFile
namespace Rvvup\Payments\Sdk;

class Curl
{
    /**
     * @throws \Exception
     */
    public function __construct()
    {
        if (!extension_loaded("curl")) {
            throw new \Exception("Curl PHP extension is required. Refer to your system administrator or host");
        }
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return \stdClass
     */
    public function request(string $method, string $uri, array $options = []): \stdClass
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $options["timeout"] ?? 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $options["json"] ? json_encode($options["json"]) : null,
            CURLOPT_HTTPHEADER => $options["headers"] ?? [],
            CURLOPT_USERAGENT => $options["user_agent"] ?? null,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(":", $header, 2);
                if (count($header) < 2) {
                    // ignore invalid headers
                    return $len;
                }
                $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                return $len;
            },
        ]);
        $body = curl_exec($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        return (object) [
            "response_code" => $responseCode,
            "response_headers" => $headers,
            "body" => $body,
            "debug" => curl_getinfo($curl),
            "error" => curl_error($curl),
        ];
    }
}
