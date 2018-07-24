<?php
/**
 * Commerce Register on Checkout plugin for Craft CMS
 *
 * Commerce Register on Checkout Variable
 *
 * @author    Jeremy Daalder
 * @copyright Copyright (c) 2016 Jeremy Daalder
 * @link      https://github.com/bossanova808
 * @package   CommerceRegisterOnCheckout
 * @since     0.0.1
 */

namespace bossanova808\commerceregisteroncheckout\variables;

use Craft;

class Variable
{
    /**
     * Returns the session data lodged during registration at checkout time
     */
    public function checkoutRegistered(){

        $return = null;

        $registered = Craft::$app->getSession()->get("registered");
        if(is_bool($registered)){
            $return = $registered;
        }

        return $return;
    }

    public function checkoutAccount(){

        $return = "";

        $account = Craft::$app->getSession()->get("account");
        if($account) $return = $account;

        return $return;
    }

    public function clearRegisterSession(){
        $session = Craft::$app->getSession();
        $session->remove("registered");
        $session->remove("account");
    }
}
