<?php
namespace bossanova808\commerceregisteroncheckout\services;

use Craft;
use craft\elements\User;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\db\Query;
use bossanova808\commerceregisteroncheckout\Plugin;
use DateTime;
use DateInterval;
use yii\base\Event;
use yii\base\Component;

class Events extends Component
{

    const EVENT_ON_BEFORE_REGISTER = 'onBeforeRegister';
    const EVENT_ON_REGISTER_COMPLETE = 'onRegisterComplete';

    /**
     * Event method
     *
     * @param Order $order
     * @param UserModel $user
     *
     * @throws \Exception
     */
    public function onBeforeRegister(Order $order, User $user)
    {
        Event::trigger(static::class, self::EVENT_ON_BEFORE_REGISTER, array('order' => $order, 'user' => $user));
    }

    /**
     * Event method
     *
     * @param Order $order
     * @param UserModel $user
     *
     * @throws \Exception
     */
    public function onRegisterComplete(Order $order, User $user)
    {
        Event::trigger(static::class, self::EVENT_ON_REGISTER_COMPLETE, array('order' => $order, 'user' => $user));
    }

    /*
     * Clean up the registration records in the DB - for the current order, and for any incomplete carts older than the purge duration
    */
    private function cleanUp($order)
    {
        $db = Craft::$app->getDb();
        // Delete the DB record for this order
        $db->createCommand()
            ->delete("{{%commerceregisteroncheckout}}", array("orderNumber" => $order->number))
            ->execute();

        // Also take the chance to clean out any old order records that are associated with incomplete carts older than the purge duration
        // Code from getCartsToPurge in Commerce_CartService.php

        $configInterval = Commerce::getInstance()->getSettings()->purgeInactiveCartsDuration;
        $edge = new DateTime();
        $interval = new DateInterval($configInterval);
        $interval->invert = 1;
        $edge->add($interval);

        // Added this...
        $mysqlEdge = $edge->format('Y-m-d H:i:s');
        $success = $db->createCommand()
            ->delete("{{%commerceregisteroncheckout}}", "`dateUpdated` <= :mysqlEdge", array(':mysqlEdge' => $mysqlEdge))
            ->execute();

        Plugin::log("Cleaned records from before cart purge duration date: $mysqlEdge (result: $success)");
    }

    public function orderCompleted($event)
    {
        $order = $event->sender;

        Plugin::log("Customer id is: $order->customerId");

        //Get all records, latest first
        $result = (new Query())
            ->select('*')
            ->from("{{%commerceregisteroncheckout}}")
            ->where(array("orderNumber" => $order->number))
            ->orderBy("dateUpdated DESC")
            ->all();

        // Short circuit if we don't have registration details for this order
        if (!$result){
            Plugin::log("Register on checkout record not found for order : " . $order->number . " - short circuiting here");
            return true;
        }

        Plugin::log("Register on checkout record FOUND for order: " . $order->number);

        // Clean up the DB so we're not keeping even encrypted passwords around for any longer than is necessary
        $this->cleanUp($order);

        // Retrieve and decrypt the stored password, short circuit if we can't get it...
        try {
            //refer only to the latest record
            $encryptedPassword = $result[0]['EPW'];
            $password = Craft::$app->security->decryptByKey(base64_decode($encryptedPassword));
        }
        catch (\Exception $e) {
            Plugin::logError("Couldn't retrieve registration password for order: " . $order->number);
            Plugin::logError($e);
            return false;
        }

        //Grab our other saved data
        try {
            $lastUsedShippingAddressId = $result[0]['lastUsedShippingAddressId'];
            $lastUsedBillingAddressId = $result[0]['lastUsedBillingAddressId'];
        }
        catch (\Exception $e) {
            Plugin::logError("Couldn't retrieve the lastUsedAddress Ids");
            Plugin::logError($e);
            $lastUsedShippingAddressId = 0;
            $lastUsedBillingAddressId = 0;
        }

        $firstName = "";
        $lastName = "";

        //Is there a billing address?  If so by default use that
        $address = $order->getBillingAddress();
        if($address){
            $firstName = $address->firstName;
            $lastName = $address->lastName;
        }

        $request = Craft::$app->getRequest();
        //Overrule with POST data if that's supplied instead (this won't work with offiste gateways like PayPal though)
        if($request->getParam('firstName')){
            $firstName = $request->getParam('firstName');
        }
        if($request->getParam('lastName')){
            $lastName = $request->getParam('lastName');
        }


        //@TODO - we offer only username = email support currently - since in Commerce everything is keyed by emails...
        $user = new User();
        $user->username         = $order->email;
        $user->email            = $order->email;
        $user->firstName        = $firstName;
        $user->lastName         = $lastName;
        $user->newPassword      = $password;

        $this->onBeforeRegister($order, $user);

        $success = Craft::$app->getElements()->saveElement($user, false);

        if ($success) {
            Plugin::log("Registered new user $address->firstName $address->lastName [$order->email] on checkout");

            // Assign them to the default user group (customers)
            Craft::$app->users->assignUserToDefaultGroup($user);
            // & Log them in
            Craft::$app->user->loginByUserId($user->id);
            // & record we've done this so the template variable can be set
            Craft::$app->getSession()->set("registered", true);

            //Try & copy the last used addresses into the new record
            // We have to get the OLD commerce_customer record, and the new one...
            $old = (new Query())
                ->select('*')
                ->from("{{%commerce_customers}}")
                ->where(array("id" => $order->customerId))
                ->orderBy("dateUpdated DESC")
                ->all();
            $new = (new Query())
                ->select('*')
                ->from("{{%commerce_customers}}")
                ->where(array("userId" => $user->id))
                ->orderBy("dateUpdated DESC")
                ->all();

            if ($old && $new) {

                $oldId = $old[0]['id'];
                $newId = $new[0]['id'];
                $userId = $user->id;

                Plugin::log("Updating customer and address records for newly created user.  NewId: $newId, OldId: $oldId, Craft User id: $userId");

                // First try and update the last used addresses in new record in the commerce_customers table
                try {
                    $updateResult = Craft::$app->getDb()
                        ->createCommand()
                        ->update('{{%commerce_customers}}',[
                            'primaryShippingAddressId' => $lastUsedShippingAddressId,
                            "primaryBillingAddressId" => $lastUsedBillingAddressId
                        ], 'id=:id', array(':id'=>$newId))
                        ->execute();

                    Plugin::log("Updated ($updateResult) customer records. To lusaId: $lastUsedShippingAddressId, lubaId: $lastUsedBillingAddressId");
                }
                catch (\Exception $e) {
                    Plugin::logError("Couldn't update the lastUsedAddress Ids");
                    Plugin::logError($e);
                }

                // Now try and update the commerce_customers_addresses table and move the address records over to the
                try {
                    $updateResult = Craft::$app->getDb()
                        ->createCommand()
                        ->update('{{%commerce_customers_addresses}}',['customerId'=>$newId],'customerId=:oldId', array(':oldId'=>$oldId))
                        ->execute();

                    Plugin::log("Updated ($updateResult) address records. From customer id: $oldId to new id: $newId");
                }
                catch (\Exception $e) {
                    Plugin::logError("Couldn't transfer addresses to the new user id");
                    Plugin::logError($e);
                }

            }
            else {
                Plugin::logError("Couldn't find the records needed to copy over the addresses");
                Plugin::logError($old);
                Plugin::logError($new);
            }

            $this->onRegisterComplete($order, $user);

            return true;
        }

        //If we haven't returned already, registration failed....
        Plugin::logError("Failed to register new user $address->firstName $address->lastName [$order->email] on checkout");
        Plugin::log($user->getErrors());

        Craft::$app->getSession()->set("registered", false);
        Craft::$app->getSession()->set("account", $user);

        return false;
    }
}