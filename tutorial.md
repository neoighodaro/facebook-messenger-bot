# Creating your first Facebook Messenger bot

When Facebook announced its Messenger bot platform, I am sure a lot of developers could easily see how well the platform could work for them. This article is going to be a guide on how you can easily create your own Facebook Messenger bot.

The bot will basically be a webhook that responds to queries submitted to it. Anytime someone sends your Facebook Page a message, it will respond appropriately with what you want it to respond with. This article will cover mainly how to create the bot and have it respond with a hard-coded message. How you get it to be smarter is up to you.



### Getting started

We are going to be creating the bot using the popular PHP framework, [Lumen](https://lumen.laravel.com) which is basically a light version of Laravel. However, since Facebook communicates to your webhook using http, you can use any language and any framework you wish to use, provided you can receive and send requests.

##### 1. Setting up your code

To start a new Lumen project, you need to use `lumen new projectname`. Then open the code in your favorite editor.

We want to create one endpoint but two methods that respond to the endpoint depending on the HTTP request type. One for `GET` and one for `POST`. The `GET` would be used to verify with Facebook that you indeed own the domain and have appropriate access, while the `POST` would be used to respond to the messages sent to your bot.

Open the routes definition file (`./routes/web.php`) and add the following routes:

```Php
$app->get('/webhook', 'BotController@verify_token');
$app->post('/webhook', 'BotController@handle_query');
```

Now we need to create the `BotController` where we would define the methods. Create a `./app/Http/Controllers/BotController.php` file and copy in the contents:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BotController extends Controller {

    /**
     * The verification token for Facebook
     *
     * @var string
     */
    protected $token;

    public function __construct()
    {
        $this->token = env('BOT_VERIFY_TOKEN');
    }

    /**
     * Verify the token from Messenger. This helps verify your bot.
     *
     * @param  Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function verify_token(Request $request)
    {
        $mode  = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');

        if ($mode === "subscribe" && $this->token and $token === $this->token) {
            return response($request->get('hub_challenge'));
        }

        file_put_contents('log.txt', json_encode($request->all()));
        return response("Invalid token!", 400);
    }

    /**
     * Handle the query sent to the bot.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function handle_query(Request $request)
    {
        $entry = $request->get('entry');

        $sender  = array_get($entry, '0.messaging.0.sender.id');
        // $message = array_get($entry, '0.messaging.0.message.text');

        $this->dispatchResponse($sender, 'Hello world. You can customise my response.');

        return response('', 200);
    }

    /**
     * Post a message to the Facebook messenger API.
     *
     * @param  integer $id
     * @param  string  $response
     * @return bool
     */
    protected function dispatchResponse($id, $response)
    {
        $access_token = env('BOT_PAGE_ACCESS_TOKEN');
        $url = "https://graph.facebook.com/v2.6/me/messages?access_token={$access_token}";

        $data = json_encode([
            'recipient' => ['id' => $id],
            'message'   => ['text' => $response]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}	
```

Now you need to create an `.env` file in the root of the project. This is where we will store the secret credentials.

```env
APP_ENV=local
APP_DEBUG=true
APP_KEY=
APP_TIMEZONE=UTC

BOT_VERIFY_TOKEN="INSERT_TOKEN_HERE"
BOT_PAGE_ACCESS_TOKEN="INSERT_ACCESS_TOKEN_HERE"

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=homestead
DB_USERNAME=homestead
DB_PASSWORD=secret

CACHE_DRIVER=file
QUEUE_DRIVER=sync

```

You can add a random value to `BOT_VERIFY_TOKEN` now but the `BOT_PAGE_ACCESS_TOKEN` will be provided to you by Facebook later. Close the file. That's all for now.

##### 2. Exposing your application to the web

Now we have created the application but we can only access it locally, however, Facebook requires that you must have a web accessible URL and also it must have https. So we have to expose our current application to the web so the Facebook verifier can access the page.

To expose the application to the internet, we will be using a tool called **[ngrok](https://ngrok.com/)**. This tool will basically tunnel your localhost so that it is available to the world. Neat. Since we are using Lumen, we are going to be using [Valet](https://laravel.com/docs/5.4/valet) which is a light web server and it comes with ngrok installed. So, we would start our web server if not already started, then share it using Valet.

```shell
$ valet link projectname
$ valet share
```

The `valet link` command just makes it so you can access your project with the `projectname.dev` locally and the `valet share` command just uses ngrok to tunnel your local server to the world.

![](https://dl.dropbox.com/s/argvp0at2c4b1a0/creating-your-first-messenger-bot-4.png)

Now if we visit the URL that ngrok has provided us we should see our application running. Cool.

> Note: To stop ngrok, press ctrl+c on your keyboard. However, running the `valet share` command will generate another random url for you by default.

##### 3. Create a Facebook App and Page

The first step would be to create a Facebook App and page or you can use existing ones. If you are going to be creating a new page then go to [https://www.facebook.com/pages/create/](https://www.facebook.com/pages/create/) and start creating a new Facebook Page.

Once you have created your page, you now need to create an application for your Page. Go to [developers.facebook.com](https://developers.facebook.com). Look for a link that helps you create a new application. Then you can fill the form to create your Facebook application. Great. 

![](https://dl.dropbox.com/s/tppmx7nqva6vrnb/creating-your-first-messenger-bot-1.png)

Now, go to the **App Dashboard** then in the Product Settings click "Add Product" and select "Messenger.".

##### 4. Setting up your Facebook Messenger webhooks

Now, click on "Setup Webhooks", it should be an option available to you.

![](https://dl.dropbox.com/s/18icv6c41cr1xm5/creating-your-first-messenger-bot-3.png)

In the popup, enter a URL for a webhook, enter the verify token that you added to your `.env` file earlier, then select `messages` and `messaging_postbacks` under Subscription Fields.

For your webhook URL, use the URL that ngrok provided (the https version). Click Verify and Save in the New Page Subscription to call your webhook with a `GET` request. Your webhook should respond with the `hub_challenge` provided by Facebook if the `hub_verify_token` provided is correct with the one stored on your server.

##### 5. Get a Page Access Token

To get an access token, one that you will add to the `BOT_PAGE_ACCESS_TOKEN` value in your `.env` file, use the Token Generation section of the Messenger settings page. Select the page you want to authorize and then follow the prompt and grant the application permission if you haven't already. The page should now display an access token, take this and set it in the `.env` file.

![](https://dl.dropbox.com/s/m17ykfk5m8drbwa/creating-your-first-messenger-bot-5.png)

##### 6. Get a Page Access Token

Now we need to subscribe to the Messenger events so that when they happen, Facebook will send us a payload with the details we need to process the event query. Click on select a page in the Webhooks section of the Messenger settings page, select the page and select the events you would like to subscribe to. You can always edit the events you are subscribed to at any time.

![](https://dl.dropbox.com/s/n63zv5pbu3yalb7/creating-your-first-messenger-bot-6.png) 

##### Testing it out

Now you should have completed the set up of your messenger bot, now head on over to your page and then in the page, send the bot a message. If you have done everything correctly, you should receive a response from the bot.

![](https://dl.dropbox.com/s/dv9lfybjpm7ou9n/creating-your-first-messenger-bot-7.png)

### Conclusion

We have been able to create the skeleton for a Facebook messenger bot using PHP. This barely scratches the surface of what your new Messenger bot can actually do. For exercise, try to use the [wit.ai](https://wit.ai) service to create a smarter bot for your page.

Have any questions or feedbacks on the article, please leave them as a comment below. You can find the source code to the article on [GitHub]().