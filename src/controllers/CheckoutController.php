<?php
namespace bossanova808\commerceregisteroncheckout\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use bossanova808\commerceregisteroncheckout\Plugin;
use craft\web\Controller;

class CheckoutController extends Controller
{
    protected $allowAnonymous = true;

    public function actionSaveRegistrationDetails()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $url = $request->referrer;

        Plugin::log("actionSaveRegistrationDetails (from: $url )");

        $ajax = $request->isAjax;
        $cart = Commerce::getInstance()->carts->getCart();
        $vars = $request->post();

        $password = $vars["password"];
        if(!$password){
            // Password is required (encryption of empty string fails)
            if($ajax){
                $this->asErrorJson("Password cannot be empty");
            } else {
                throw new HttpException(400, Craft::t("Password cannot be empty"));
            }
        }

        $encryptedPassword = base64_encode(Craft::$app->security->encryptByKey($password));

        $number = 0;
        $lusaid = 0;
        $lubaid = 0;

        $number = $cart->number;

        if($cart->shippingAddress){
            $lusaid = $cart->shippingAddress->id;
        }

        if($cart->billingAddress){
           $lubaid = $cart->billingAddress->id;
        }

        Plugin::log("Saving registration record for order: " . $number ." lusaid " . $lusaid . " lubaid " . $lubaid);

        $result = Craft::$app->getDb()
            ->createCommand()
            ->insert("{{%commerceregisteroncheckout}}",[
                "orderNumber" => $number,
                "EPW" => $encryptedPassword,
                "lastUsedShippingAddressId" => $lusaid,
                "lastUsedBillingAddressId" => $lubaid
            ])
            ->execute();

        // Appropriate Ajax responses...
        if($ajax){
            return $this->asJson(["success"=>true]);
        }

        return $this->redirectToPostedUrl();
    }

}