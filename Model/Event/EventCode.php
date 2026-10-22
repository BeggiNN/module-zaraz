<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

/**
 * Codes of events shipped with the module (keys of BuilderPool and of the "Events" admin rows)
 */
class EventCode
{
    public const PRODUCT_LIST_VIEWED = 'product_list_viewed';
    public const PRODUCTS_SEARCHED = 'products_searched';
    public const PRODUCT_CLICKED = 'product_clicked';
    public const PRODUCT_VIEWED = 'product_viewed';
    public const PRODUCT_ADDED = 'product_added';
    public const PRODUCT_REMOVED = 'product_removed';
    public const PRODUCT_ADDED_TO_WISHLIST = 'product_added_to_wishlist';
    public const PRODUCT_ADDED_TO_COMPARE = 'product_added_to_compare';
    public const CART_VIEWED = 'cart_viewed';
    public const CHECKOUT_STARTED = 'checkout_started';
    public const CHECKOUT_STEP_VIEWED = 'checkout_step_viewed';
    public const CHECKOUT_STEP_COMPLETED = 'checkout_step_completed';
    public const SHIPPING_INFO_ENTERED = 'shipping_info_entered';
    public const PAYMENT_INFO_ENTERED = 'payment_info_entered';
    public const ORDER_COMPLETED = 'order_completed';
    public const VIEWED_PROMOTION = 'viewed_promotion';
    public const CLICKED_PROMOTION = 'clicked_promotion';
    public const COUPON_APPLIED = 'coupon_applied';
    public const COUPON_REMOVED = 'coupon_removed';
    public const LOGIN = 'login';
    public const SIGN_UP = 'sign_up';
}
