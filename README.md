# Commerce Register on Checkout plugin for Craft CMS

Register customers on checkout with Craft Commerce

## Installation

To install from git, add a new repository to the `repositories` section in your composer.json:

```json
"repositories": [
  {
    "type": "git",
    "url": "https://github.com/bossanova808/CommerceRegisterOnCheckout"
  }
]
```

And add the relevant `require`:

```json
"require": {
  "bossanova808/commerceregisteroncheckout": "dev-craft3",
}
```

`composer install` to download the plugin. In Craft Control Panel -> Settings -> Plugins, install Commerce Register On Checkout.

## Commerce Register on Checkout Overview

This plugin allows you to more easily add user registration as part of your Commerce checkout process.

Currently only allows for registering users with their username set to their email - however given commerce keys everything by the email, this is the most natural set up anyway.

As of 0.0.6+ it also transfers address records and sets the last used billing and shipping address IDs so that the newly created Craft Users immediately get access to their address records in their address book for their next order (this does not occur if you integrate a standard Craft registration form).

## Why Not Use A Standard Craft Registation Form?

An easy solution to this issue is to present a mostly pre-filled form to the user immediately following checkout - and of course this works fine and means one less plugin, which is a good thing.  However, from a business perspective and based on real world experience, you will see *vastly* lower user registration numbers with this method.

See discussion here: https://craftcms.stackexchange.com/questions/18974/register-a-user-as-part-of-a-commerce-checkout/18993#18993

In short, this plugin  allows for a more integrated approach - registering the user _during_ the actual checkout, which signifcantly increases the number of users that will register, and this has many potential business benefits of course.

In addition, if you use a standard registration form, your customers will need to re-enter their address details when they do their second order as these are not automatically transferred with the registration (order records are, but not addresses). This is less than ideal from a UX perspective.

## Using Commerce Register on Checkout

At any point during the checkout flow, before the final payment/order completion, POST request can be made to `/commerceregisteroncheckout/checkout/save-registration-details`.

Example form:

```html
 <form method="POST">
    {{ csrfInput() }}

    <input type="hidden" name="action" value="/commerceregisteroncheckout/checkout/save-registration-details">
    {{ redirectInput('/commerce/cart/update-cart') }}

    <input type="password" value="" name="password">

    <label for="">Email</label>
    <input type="text" name="email"/>
    <input type="submit" value="Continue"/>
</form>
```

Alternatively, an AJAX request can be used:

```html
<input type="checkbox" value="true" id="registerUser" name="registerUser" checked>
<input type="password" value="" placeholder="New Password (min. 6 characters)" name="password">
```

Somehere in e.g. your master layout set a variable to the CSRF token value (if you're using CSRF verification):

```javascript
window.csrfTokenValue = "{{ craft.request.csrfToken|e('js') }}"
```

Then the JS you need is just something like this:

```javascript
// Has the customer chosen to register an account?
if ($('#registerUser').prop('checked')) {
    var pw_value = $('input[type="password"]').val();
    var pw_error = '';
    // Validate the passwrod meets Craft's rules
    if (pw_value.length < 6) {
        pw_error = "Password length must be 6 or more characters";
    }
    if (pw_error) {
        alert(pw_error);
    }
    // Lodge the registration details for later retrieval
    else {
        $.ajax({
            type: 'POST',
            url: '/commerceregisteroncheckout/checkout/save-registration-details',
            data: {
                CRAFT_CSRF_TOKEN: window.csrfTokenValue,
                password: pw_value,
            }
            complete: {
                // NB! Your call to your payment function must
                // run only AFTER the registration details have been lodged
                // So pop it here...
                doPayment();
            }
        });
    }
}
// Register account not chosen, just do the actual payment...
else {
    doPayment();
}
```

The password is encrypted using Craft's encryption API. Once the order is completed, the user is registered and immediately logged in.

## Handling Success & Errors

In your order complete template, you can check the results of the registration process and handle any errors - giving the user another chance to register if something went wrong, for example.  The users account information is returned to the template in an `account` variable.

Here's some sketch code to get you started:

```

    {# Get the results of user registration, if there are any... #}
    {% set registered = craft.commerceRegisterOnCheckout.checkoutRegistered ?? null %}
    {% set account = craft.commerceRegisterOnCheckout.checkoutAccount ?? null %}

    {# Explicitly clear the http session variables now that we've used them #}
    {% do craft.commerceRegisterOnCheckout.clearRegisterSession %}


    {# Was registration attempted? #}
    {% if registered is not null %}

        {# Success, if true #}
        {% if registered %}
            <do some stuff>

        {# Failure, otherwise #}
        {% else %}
            {% if account|length %}
                {% if "has already been taken" in account.getError('email') %}

                ... Point out they are already registered...

            {% else %}
                ...etc, e.g. present a user registration form with as much filled in as possible>
            }
```


## Events

CROC offers an event before attempting to save the new user `onBeforeRegister`, and on the completion of succesful user registration `onRegisterComplete`.

The event parameters for both events are the Order and User models.

You can listen and act on these events if needed, e.g.:

```php
use bossanova808\commerceregisteroncheckout\services\Events as EventsService;

...

Event::on(EventsService::class, EventsService::EVENT_ON_BEFORE_REGISTER, function(Event $event) {
    $order = $event->order;
    $user = $event->user;
});

```

```php
use bossanova808\commerceregisteroncheckout\services\Events as EventsService;

...

Event::on(EventsService::class, EventsService::EVENT_ON_REGISTER_COMPLETE, function(Event $event) {
    $order = $event->order;
    $user = $event->user;
});

```


## Commerce Register on Checkout Changelog

See [releases.json](https://github.com/bossanova808/CommerceRegisterOnCheckout/blob/master/releases.json)

## Who is to blame?

Brought to you by [Jeremy Daalder](https://github.com/bossanova808)

## Issues?

Please use github issues or find me on the Craft slack, I mostly hang out in the #commerce channel

## Icon

by IconfactoryTeam from the Noun Project
