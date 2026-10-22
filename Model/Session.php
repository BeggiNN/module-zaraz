<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model;

use Magento\Framework\Session\SessionManager;

/**
 * Session namespace "perspective_zaraz" for the action event queue (storage is configured in di.xml).
 *
 * A real class (not a virtual type) so that a Proxy can defer session start until an event is queued.
 */
class Session extends SessionManager
{
}
