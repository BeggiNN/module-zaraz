<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api\Data;

/**
 * One call to the Zaraz Web API: zaraz.ecommerce(name, payload), zaraz.track(name, payload) or zaraz.set(name, payload)
 *
 * @api
 */
interface EventInterface
{
    public const METHOD_ECOMMERCE = 'ecommerce';
    public const METHOD_TRACK = 'track';
    public const METHOD_SET = 'set';

    /**
     * Module event code, e.g. "product_added" (stable when the Zaraz name is overridden in the admin)
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Zaraz Web API method: ecommerce | track | set
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Zaraz event name (for "set" — the key)
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Event properties (for "set" — ['value' => mixed, 'scope' => string])
     *
     * @return array
     */
    public function getPayload(): array;

    /**
     * Delivery id; when set, the JS core sends the event at most once per browser
     *
     * @return string|null
     */
    public function getId(): ?string;

    /**
     * Public JS contract representation, see README "JS contract"
     *
     * @return array
     */
    public function toArray(): array;
}
