<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Event;

use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\Data\EventInterfaceFactory;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Event\BuilderPool;
use Perspective\Zaraz\Model\Event\Context;
use Perspective\Zaraz\Model\Event\Event;
use Perspective\Zaraz\Model\Event\Generator;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class GeneratorTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * @param array $settings
     * @param array|null $payload
     * @return Generator
     */
    private function createGenerator(array $settings, ?array $payload = ['price' => 1.0]): Generator
    {
        $builder = $this->createMock(EventBuilderInterface::class);
        $builder->method('getDefaultName')->willReturn('Product Added');
        $builder->method('getMethod')->willReturn(EventInterface::METHOD_ECOMMERCE);
        $builder->method('build')->willReturn($payload);

        $factory = $this->createMock(EventInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (array $data): Event => new Event(...$data));

        return new Generator(new BuilderPool(['product_added' => $builder]), $this->createConfig($settings), $factory);
    }

    /**
     * @param array $row
     * @return array
     */
    private function eventsRow(array $row): array
    {
        return [Config::XML_EVENTS => json_encode(['_1' => $row + ['event' => 'product_added']])];
    }

    /**
     * @return void
     */
    public function testEventWithoutAdminRowIsSentFromBrowserWithDefaultName(): void
    {
        $event = $this->createGenerator([])->generate('product_added', new Context(1), 'browser', 'id-1');

        $this->assertSame('Product Added', $event->getName());
        $this->assertSame(['price' => 1.0], $event->getPayload());
        $this->assertSame('id-1', $event->getId());
        $this->assertNull(
            $this->createGenerator([])
                ->generate('product_added', new Context(1), EventGeneratorInterface::CHANNEL_SERVER)
        );
    }

    /**
     * Server-only events registered by Perspective_ZarazServerSide default to the server without an admin row
     *
     * @return void
     */
    public function testDefaultChannelsOfEventsWithoutAdminRow(): void
    {
        $builder = $this->createMock(EventBuilderInterface::class);
        $builder->method('getDefaultName')->willReturn('Order Refunded');
        $builder->method('getMethod')->willReturn(EventInterface::METHOD_ECOMMERCE);
        $builder->method('build')->willReturn(['order_id' => '1']);
        $factory = $this->createMock(EventInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (array $data): Event => new Event(...$data));
        $generator = new Generator(
            new BuilderPool(['order_refunded' => $builder, 'order_cancelled' => $builder]),
            $this->createConfig([]),
            $factory,
            ['order_refunded' => ['server'], 'order_cancelled' => []]
        );

        $this->assertTrue($generator->isAllowed('order_refunded', EventGeneratorInterface::CHANNEL_SERVER, 1));
        $this->assertFalse($generator->isAllowed('order_refunded', EventGeneratorInterface::CHANNEL_BROWSER, 1));
        $this->assertFalse($generator->isAllowed('order_cancelled', EventGeneratorInterface::CHANNEL_SERVER, 1));
        $this->assertNotNull(
            $generator->generate('order_refunded', new Context(1), EventGeneratorInterface::CHANNEL_SERVER)
        );
    }

    /**
     * @return void
     */
    public function testNameOverride(): void
    {
        $event = $this->createGenerator($this->eventsRow(['enabled' => '1', 'name' => 'add_custom', 'browser' => '1']))
            ->generate('product_added', new Context(1));

        $this->assertSame('add_custom', $event->getName());
    }

    /**
     * @return void
     */
    public function testDisabledCases(): void
    {
        $context = new Context(1);
        $this->assertNull(
            $this->createGenerator([Config::XML_ENABLED => '0'])->generate('product_added', $context),
            'module disabled'
        );
        $this->assertNull(
            $this->createGenerator($this->eventsRow(['enabled' => '0', 'browser' => '1']))
                ->generate('product_added', $context),
            'event disabled'
        );
        $this->assertNull(
            $this->createGenerator($this->eventsRow(['enabled' => '1', 'browser' => '0', 'server' => '1']))
                ->generate('product_added', $context),
            'browser channel disabled'
        );
        $this->assertNull($this->createGenerator([])->generate('unknown', $context), 'no builder');
        $this->assertNull($this->createGenerator([], null)->generate('product_added', $context), 'builder skipped');
    }

    /**
     * @return void
     */
    public function testEventJsonContract(): void
    {
        $this->assertSame(
            '{"method":"track","name":"login","payload":{}}',
            json_encode((new Event('track', 'login'))->toArray())
        );
        $this->assertSame(
            '{"method":"ecommerce","name":"Order Completed","payload":{"order_id":"1"},"id":"order-1"}',
            json_encode((new Event('ecommerce', 'Order Completed', ['order_id' => '1'], 'order-1'))->toArray())
        );
    }
}
