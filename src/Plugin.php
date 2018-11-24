<?php
/**
 * Commerce Register on Checkout plugin for Craft CMS
 *
 * Register customers on checkout with Craft Commerce
 *
 * @author    Jeremy Daalder
 * @copyright Copyright (c) 2016 Jeremy Daalder
 * @link      https://github.com/bossanova808
 * @package   CommerceRegisterOnCheckout
 * @since     0.0.1
 */

namespace bossanova808\commerceregisteroncheckout;

use Craft;
use craft\commerce\elements\Order;
use yii\log\Logger;
use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    /**
     * Static log functions for this plugin
     *
     * @param mixed $msg
     * @param string $level
     *
     * @return null
     */
    public static function logError($msg){
        Plugin::log($msg, Logger::LEVEL_ERROR);
    }

    public static function logWarning($msg){
        Plugin::log($msg, Logger::LEVEL_WARNING);
    }

    public static function log($msg, $level = Logger::LEVEL_INFO)
    {
        if (is_string($msg))
        {
            $msg = "\n\n" . $msg . "\n";
        }
        else
        {
            $msg = "\n\n" . json_encode($msg) . "\n";
        }

        Craft::getLogger()->log($msg, $level);
    }

    public function init(){
        parent::init();

        $this->setComponents([
            'events' => \bossanova808\commerceregisteroncheckout\services\Events::class,
        ]);

        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, function(Event $event) {
            Plugin::getInstance()->events->orderCompleted($event);
        });
    }
}
