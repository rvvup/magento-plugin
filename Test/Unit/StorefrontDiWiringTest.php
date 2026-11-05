<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SimpleXMLElement;

/**
 * Static checks of the di.xml wiring for the time bounded storefront lookups. Unit tests build classes directly, so
 * without this a wrong argument name, a missing virtual type or wiring in the wrong area goes unnoticed until a real
 * Magento runs.
 *
 * @coversNothing
 */
class StorefrontDiWiringTest extends TestCase
{
    private const NS = 'Rvvup\\Payments\\';

    /**
     * Consumers that must get the bounded lookups, and the argument that carries them.
     */
    private const GLOBAL_CONSUMERS = [
        self::NS . 'ViewModel\\PayPal' => [
            'isPaymentMethodAvailable' => self::NS . 'Model\\StorefrontIsPaymentMethodAvailable',
        ],
        self::NS . 'Model\\ClearpayAvailability' => [
            'isPaymentMethodAvailable' => self::NS . 'Model\\StorefrontIsPaymentMethodAvailable',
        ],
        self::NS . 'Model\\ThresholdProvider' => [
            'requestOptions' => self::NS . 'Model\\StorefrontRequestOptions',
        ],
        self::NS . 'Model\\ApiSettingsProvider' => [
            'requestOptions' => self::NS . 'Model\\StorefrontRequestOptions',
        ],
    ];

    public function testEveryStorefrontVirtualTypeUsesRealConstructorArguments(): void
    {
        $global = $this->load('di.xml');
        $virtualTypes = $this->virtualTypes($global);

        $storefront = array_filter(
            $virtualTypes,
            static function (string $name): bool {
                return strpos($name, self::NS . 'Model\\Storefront') === 0;
            },
            ARRAY_FILTER_USE_KEY
        );

        $this->assertNotEmpty($storefront, 'No Storefront* virtual types found in di.xml.');

        foreach ($storefront as $name => $definition) {
            $this->assertArgumentsExistOnConstructor($definition['type'], array_keys($definition['arguments']), $name);

            foreach ($definition['arguments'] as $argument => $value) {
                if ($value['xsi:type'] === 'object') {
                    $this->assertTrue(
                        isset($virtualTypes[$value['value']]) || class_exists($value['value']),
                        sprintf('%s argument "%s" refers to unknown %s.', $name, $argument, $value['value'])
                    );
                }
            }
        }
    }

    public function testTheBoundedConsumersAreWiredInTheGlobalScope(): void
    {
        // The Clearpay widget reads availability from a REST route (webapi_rest area), which does not load
        // frontend/di.xml. Wiring only there leaves that path unbounded.
        $global = $this->load('di.xml');
        $types = $this->types($global);
        $virtualTypes = $this->virtualTypes($global);

        foreach (self::GLOBAL_CONSUMERS as $class => $expectedArguments) {
            $this->assertArrayHasKey($class, $types, sprintf('%s is not configured in the global di.xml.', $class));
            $this->assertArgumentsExistOnConstructor($class, array_keys($types[$class]), $class);

            foreach ($expectedArguments as $argument => $expectedValue) {
                $this->assertArrayHasKey($argument, $types[$class], sprintf('%s lacks "%s".', $class, $argument));
                $this->assertSame($expectedValue, $types[$class][$argument]['value']);
                $this->assertTrue(
                    isset($virtualTypes[$expectedValue]) || class_exists($expectedValue),
                    sprintf('%s is neither a virtual type nor a class.', $expectedValue)
                );
            }
        }
    }

    public function testTheAssetsViewModelGetsTheBoundedLookupsInTheFrontendScope(): void
    {
        $types = $this->types($this->load('frontend/di.xml'));
        $class = self::NS . 'ViewModel\\Assets';

        $this->assertArrayHasKey($class, $types);
        $this->assertArgumentsExistOnConstructor($class, array_keys($types[$class]), $class);
        $this->assertSame(
            self::NS . 'Model\\StorefrontPaymentMethodsSettingsGet',
            $types[$class]['storefrontPaymentMethodsSettingsGet']['value']
        );
        $this->assertSame(
            self::NS . 'Model\\StorefrontPaymentMethodsAssetsGet',
            $types[$class]['storefrontPaymentMethodsAssetsGet']['value']
        );
    }

    public function testCheckoutAndOrderPlacementKeepTheUnboundedLookups(): void
    {
        $global = $this->load('di.xml');
        $types = $this->types($global);
        $bounded = [
            self::NS . 'Model\\StorefrontIsPaymentMethodAvailable',
            self::NS . 'Model\\StorefrontRequestOptions',
        ];

        // The express payment creation checks availability before taking payment, it must wait for the real answer.
        $class = self::NS . 'Model\\Payment\\PaymentCreateExpress';
        foreach (($types[$class] ?? []) as $argument => $value) {
            $this->assertNotContains(
                $value['value'],
                $bounded,
                sprintf('%s "%s" must not be bounded.', $class, $argument)
            );
        }

        // The default services stay unbounded.
        $defaults = [
            self::NS . 'Model\\PaymentMethodsAvailableGet',
            self::NS . 'Model\\IsPaymentMethodAvailable',
        ];
        foreach ($defaults as $class) {
            $this->assertArrayNotHasKey('requestOptions', $types[$class] ?? [], $class . ' must stay unbounded.');
        }
    }

    /**
     * @param string $class
     * @param string[] $arguments
     * @param string $context
     * @return void
     */
    private function assertArgumentsExistOnConstructor(string $class, array $arguments, string $context): void
    {
        $this->assertTrue(class_exists($class) || interface_exists($class), $class . ' does not exist.');

        $constructor = (new ReflectionClass($class))->getConstructor();
        $this->assertNotNull($constructor, $class . ' has no constructor.');

        $parameters = array_map(
            static function (\ReflectionParameter $parameter): string {
                return $parameter->getName();
            },
            $constructor->getParameters()
        );

        foreach ($arguments as $argument) {
            $this->assertContains(
                $argument,
                $parameters,
                sprintf('%s sets "%s", which is not a constructor parameter of %s.', $context, $argument, $class)
            );
        }
    }

    /**
     * @param string $file Relative to src/etc
     * @return SimpleXMLElement
     */
    private function load(string $file): SimpleXMLElement
    {
        $xml = simplexml_load_file(dirname(__DIR__, 2) . '/src/etc/' . $file);
        $this->assertNotFalse($xml, $file . ' is not valid XML.');

        return $xml;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array<string, array{type: string, arguments: array}>
     */
    private function virtualTypes(SimpleXMLElement $xml): array
    {
        $result = [];

        foreach ($xml->virtualType as $virtualType) {
            $result[(string) $virtualType['name']] = [
                'type' => (string) $virtualType['type'],
                'arguments' => $this->arguments($virtualType),
            ];
        }

        return $result;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array<string, array>
     */
    private function types(SimpleXMLElement $xml): array
    {
        $result = [];

        foreach ($xml->type as $type) {
            $name = (string) $type['name'];
            $result[$name] = array_merge($result[$name] ?? [], $this->arguments($type));
        }

        return $result;
    }

    /**
     * @param SimpleXMLElement $element
     * @return array<string, array{xsi:type: string, value: string}>
     */
    private function arguments(SimpleXMLElement $element): array
    {
        $result = [];

        foreach ($element->arguments->argument ?? [] as $argument) {
            $attributes = $argument->attributes('http://www.w3.org/2001/XMLSchema-instance');
            $result[(string) $argument['name']] = [
                'xsi:type' => (string) ($attributes['type'] ?? ''),
                'value' => trim((string) $argument),
            ];
        }

        return $result;
    }
}
