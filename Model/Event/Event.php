<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

use Perspective\Zaraz\Api\Data\EventInterface;

/**
 * Immutable Zaraz event
 */
class Event implements EventInterface
{
    /**
     * @param string $method
     * @param string $name
     * @param array $payload
     * @param string|null $id
     * @param string $code
     */
    public function __construct(
        private readonly string $method,
        private readonly string $name,
        private readonly array $payload = [],
        private readonly ?string $id = null,
        private readonly string $code = '',
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        $data = ['method' => $this->method, 'name' => $this->name, 'payload' => (object)$this->payload];
        if ($this->code !== '') {
            $data['code'] = $this->code;
        }
        if ($this->id !== null) {
            $data['id'] = $this->id;
        }
        return $data;
    }
}
