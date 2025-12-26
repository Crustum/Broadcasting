# CakePHP Broadcasting Plugin

- [Introduction](#introduction)
- [Quickstart](#quickstart)
- [Server Side Installation](#server-side-installation)
    - [Pusher Channels](#pusher-channels)
    - [Redis](#redis)
    - [Log Driver](#log-driver)
- [Client Side Installation](#client-side-installation)
    - [Laravel Echo](#laravel-echo)
    - [Pusher Channels](#client-pusher-channels)
- [Concept Overview](#concept-overview)
    - [Using an Example Application](#using-example-application)
- [Defining Broadcast Events](#defining-broadcast-events)
    - [Broadcast Name](#broadcast-name)
    - [Broadcast Data](#broadcast-data)
    - [Broadcast Queue](#broadcast-queue)
    - [Queue Adapter System](#queue-adapter-system)
    - [Broadcast Conditions](#broadcast-conditions)
- [Authorizing Channels](#authorizing-channels)
    - [Defining Authorization Callbacks](#defining-authorization-callbacks)
    - [Channel Authorization Routes](#channel-authorization-routes)
- [Broadcasting Events](#broadcasting-events)
    - [Only to Others](#only-to-others)
    - [Customizing the Connection](#customizing-the-connection)
    - [Anonymous Events](#anonymous-events)
- [Receiving Broadcasts](#receiving-broadcasts)
    - [Listening for Events](#listening-for-events)
    - [Leaving a Channel](#leaving-a-channel)
    - [Namespaces](#namespaces)
    - [Using React or Vue](#using-react-or-vue)
- [Presence Channels](#presence-channels)
    - [Authorizing Presence Channels](#authorizing-presence-channels)
    - [Joining Presence Channels](#joining-presence-channels)
    - [Broadcasting to Presence Channels](#broadcasting-to-presence-channels)
- [Model Broadcasting](#model-broadcasting)
    - [Model Broadcasting Behavior](#model-broadcasting-behavior)
    - [Listening for Model Broadcasts](#listening-for-model-broadcasts)
- [Client Events](#client-events)
- [Notifications](#notifications)
- [Testing](#testing)
    - [Asserting Broadcasts Sent](#asserting-broadcasts-sent)
    - [Asserting Broadcasts to Channels](#asserting-broadcasts-to-channels)
    - [Inspecting Broadcast Content](#inspecting-broadcast-content)
    - [Testing Connections](#testing-connections)
    - [Testing Socket Exclusion](#testing-socket-exclusion)
    - [Testing Model Broadcasting](#testing-model-broadcasting)
    - [Available Assertions](#available-assertions)

<a name="introduction"></a>
## Introduction

In many modern web applications, WebSockets are used to implement realtime, live-updating user interfaces. When some data is updated on the server, a message is typically sent over a WebSocket connection to be handled by the client. WebSockets provide a more efficient alternative to continually polling your application's server for data changes that should be reflected in your UI.

For example, imagine your application is able to export a user's data to a CSV file and email it to them. However, creating this CSV file takes several minutes so you choose to create and mail the CSV within a queued job. When the CSV has been created and mailed to the user, we can use event broadcasting to dispatch a `UserDataExported` event that is received by our application's JavaScript. Once the event is received, we can display a message to the user that their CSV has been emailed to them without them ever needing to refresh the page.

To assist you in building these types of features, CakePHP makes it easy to "broadcast" your server-side events over a WebSocket connection. Broadcasting your CakePHP events allows you to share the same event names and data between your server-side CakePHP application and your client-side JavaScript application.

The core concepts behind broadcasting are simple: clients connect to named channels on the frontend, while your CakePHP application broadcasts events to these channels on the backend. These events can contain any additional data you wish to make available to the frontend.

<a name="supported-drivers"></a>
#### Supported Drivers

By default, CakePHP includes four server-side broadcasting drivers for you to choose from: [Pusher Channels](https://pusher.com/channels), Redis, Log, and Null.

<a name="quickstart"></a>
## Quickstart

### Installing the Plugin

Install via Composer:

```bash
composer require crustum/broadcasting
```

> [!NOTE]
> This plugin should be registered in your `config/plugins.php` file.

```bash
bin/cake plugin load Crustum/Broadcasting
```

> [!TIP]
> **After the plugin registers itself**, it's recommended to install the configuration with the manifest system:

```bash
bin/cake manifest install --plugin Crustum/Broadcasting
```

The Broadcasting plugin will create the `config/broadcasting.php` configuration file and the `config/channels.php` file where you may register your application's broadcast authorization routes and callbacks. Additionally, it will copy the migrations to the application's migrations directory and append the loading of the `config/broadcasting.php` file to the `config/bootstrap.php` file.

Alternatively, you can load the plugin in your `Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Crustum/Broadcasting');
}
```
This will enable broadcasting by configuring the Broadcasting plugin and setting up your broadcasting drivers.

Plugin supports several broadcast drivers out of the box: [Pusher Channels](https://pusher.com/channels), Redis, and a `log` driver for local development and debugging. Additionally, a `null` driver is included which allows you to disable broadcasting during testing. A configuration example is included for each of these drivers in the `config/broadcasting.php` configuration file.

All of your application's event broadcasting configuration is stored in the `config/broadcasting.php` configuration file:

```php
return [
    'Broadcasting' => [
        'default' => [
            'className' => 'Crustum/Broadcasting.Pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
            ],
        ],
        'redis' => [
            'className' => 'Crustum/Broadcasting.Redis',
            'connection' => 'default',
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'password' => null,
                'database' => 0,
            ],
        ],
        'log' => [
            'className' => 'Crustum/Broadcasting.Log',
        ],
        'null' => [
            'className' => 'Crustum/Broadcasting.Null',
        ],
    ],
];
```

<a name="quickstart-next-steps"></a>
#### Next Steps

Once you have enabled event broadcasting, you're ready to learn more about [defining broadcast events](#defining-broadcast-events) and [listening for events](#listening-for-events).

> [!NOTE]
> Before broadcasting any events, you should first configure and run a queue worker. All event broadcasting is done via queued jobs so that the response time of your application is not seriously affected by events being broadcast.

<a name="server-side-installation"></a>
## Server Side Installation

To get started using CakePHP's event broadcasting, we need to do some configuration within the CakePHP application as well as install a few packages.

Event broadcasting is accomplished by a server-side broadcasting driver that broadcasts your CakePHP events so that Laravel Echo (a JavaScript library) can receive them within the browser client. Don't worry - we'll walk through each part of the installation process step-by-step.

<a name="pusher-channels"></a>
### Pusher Channels

The Pusher Channels PHP SDK is already included with the Broadcasting plugin, so no additional installation is required.

Next, you should configure your Pusher Channels credentials in the `config/broadcasting.php` configuration file. An example Pusher Channels configuration is already included in this file, allowing you to quickly specify your key, secret, and application ID. Typically, you should configure your Pusher Channels credentials in your application's `.env` file:

```ini
PUSHER_APP_ID="your-pusher-app-id"
PUSHER_APP_KEY="your-pusher-key"
PUSHER_APP_SECRET="your-pusher-secret"
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME="https"
PUSHER_APP_CLUSTER="mt1"
```

The `config/broadcasting.php` file's `pusher` configuration also allows you to specify additional `options` that are supported by Channels, such as the cluster:

```php
'default' => [
    'className' => 'Crustum/Broadcasting.Pusher',
    'key' => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'app_id' => env('PUSHER_APP_ID'),
    'options' => [
        'cluster' => env('PUSHER_APP_CLUSTER'),
        'useTLS' => true,
        'host' => '127.0.0.1',
        'port' => 6001,
        'scheme' => 'http',
    ],
],
```

Finally, you are ready to install and configure [Laravel Echo](#client-side-installation), which will receive the broadcast events on the client-side.

<a name="redis"></a>
### Redis

If you are using the Redis broadcaster, you should install the Redis PHP extension:

```shell
pecl install redis
```

The Redis broadcaster will broadcast messages using Redis' pub/sub feature; however, you will need to pair this with a WebSocket server that can receive the messages from Redis and broadcast them to your WebSocket channels.

When the Redis broadcaster publishes an event, it will be published on the event's specified channel names and the payload will be a JSON encoded string containing the event name, a `data` payload, and the user that generated the event's socket ID (if applicable).

<a name="log-driver"></a>
### Log Driver

The `log` broadcaster is primarily used for development and debugging. Instead of broadcasting events to a real-time service, it will log all broadcast events to your application's log files. This is useful for testing your broadcasting logic without setting up external services.

<a name="client-side-installation"></a>
## Client Side Installation

<a name="laravel-echo"></a>
### Laravel Echo

[Laravel Echo](https://github.com/laravel/echo) is a JavaScript library that makes it painless to subscribe to channels and listen for events broadcast by your server-side broadcasting driver. Even though it's called "Laravel" Echo, it works perfectly with CakePHP's broadcasting system.

You may install Echo via the NPM package manager. In this example, we will also install the `pusher-js` package since we will be using the Pusher Channels broadcaster:

```shell
npm install --save-dev laravel-echo pusher-js
```

<a name="client-pusher-channels"></a>
#### Pusher Channels

Once Echo is installed, you are ready to create a fresh Echo instance in your application's JavaScript. A great place to do this is at the bottom of the `resources/js/bootstrap.js` file:

```js
import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true
});
```

Next, you should define the appropriate values for the Pusher environment variables in your application's `.env` file. If these variables do not already exist in your `.env` file, you should add them:

```ini
PUSHER_APP_ID="your-pusher-app-id"
PUSHER_APP_KEY="your-pusher-key"
PUSHER_APP_SECRET="your-pusher-secret"
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME="https"
PUSHER_APP_CLUSTER="mt1"

MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

Once you have adjusted the Echo configuration according to your application's needs, you may compile your application's assets.

<a name="concept-overview"></a>
## Concept Overview

CakePHP's event broadcasting allows you to broadcast your server-side CakePHP events to your client-side JavaScript application using a driver-based approach to WebSockets. Currently, CakePHP ships with [Pusher Channels](https://pusher.com/channels) and Redis drivers. The events may be easily consumed on the client-side using the [Laravel Echo](#client-side-installation) JavaScript package.

Events are broadcast over "channels", which may be specified as public or private. Any visitor to your application may subscribe to a public channel without any authentication or authorization; however, in order to subscribe to a private channel, a user must be authenticated and authorized to listen on that channel.

<a name="using-example-application"></a>
### Using an Example Application

Before diving into each component of event broadcasting, let's take a high level overview using an e-commerce store as an example.

In our application, let's assume we have a page that allows users to view the shipping status for their orders. Let's also assume that an `OrderShipmentStatusUpdated` event is fired when a shipping status update is processed by the application:

```php
use Crustum\Broadcasting\Broadcasting;

// Broadcast the event
Broadcasting::to('orders.' . $order->id)
    ->event('OrderShipmentStatusUpdated')
    ->data(['order' => $order])
    ->send();
```

<a name="the-broadcastable-interface"></a>
#### The `BroadcastableInterface` Interface

When a user is viewing one of their orders, we don't want them to have to refresh the page to view status updates. Instead, we want to broadcast the updates to the application as they are created. So, we need to create an event class that implements the `BroadcastableInterface` interface. This will instruct CakePHP to broadcast the event when it is fired:

```php
<?php
declare(strict_types=1);

namespace App\Event;

use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Channel\PrivateChannel;
use Crustum\Broadcasting\Event\BroadcastableInterface;

class OrderShipmentStatusUpdated implements BroadcastableInterface
{
    public function __construct(
        public Order $order,
    ) {}

    public function broadcastChannel(): Channel|array
    {
        return new PrivateChannel('orders.' . $this->order->id);
    }

    public function broadcastEvent(): string
    {
        return 'OrderShipmentStatusUpdated';
    }

    public function broadcastData(): ?array
    {
        return [
            'order' => $this->order->toArray(),
        ];
    }

    public function broadcastSocket(): ?string
    {
        return null;
    }
}
```

The `BroadcastableInterface` interface requires our event to define a `broadcastChannel` method. This method is responsible for returning the channels that the event should broadcast on. We only want the creator of the order to be able to view status updates, so we will broadcast the event on a private channel that is tied to the order:

```php
public function broadcastChannel(): Channel|array
{
    return new PrivateChannel('orders.' . $this->order->id);
}
```

If you wish the event to broadcast on multiple channels, you may return an `array` instead:

```php
public function broadcastChannel(): array
{
    return [
        new PrivateChannel('orders.' . $this->order->id),
        new Channel('order-status-updates'),
    ];
}
```

<a name="example-application-authorizing-channels"></a>
#### Authorizing Channels

Remember, users must be authorized to listen on private channels. We may define our channel authorization rules in our application's `config/channels.php` file. In this example, we need to verify that any user attempting to listen on the private `orders.1` channel is actually the creator of the order:

```php
use Crustum\Broadcasting\Broadcasting;
use Cake\ORM\TableRegistry;

Broadcasting::channel('private-orders.{orderId}', function ($user, $orderId) {
    if (!$user) {
        return false;
    }

    $ordersTable = TableRegistry::getTableLocator()->get('Orders');
    $order = $ordersTable->get($orderId);

    return $user->id === $order->user_id;
});
```

The `channel` method accepts two arguments: the name of the channel and a callback which returns `true` or `false` indicating whether the user is authorized to listen on the channel.

All authorization callbacks receive the currently authenticated user as their first argument and any additional wildcard parameters as their subsequent arguments. In this example, we are using the `{orderId}` placeholder to indicate that the "ID" portion of the channel name is a wildcard.

<a name="listening-for-event-broadcasts"></a>
#### Listening for Event Broadcasts

Next, all that remains is to listen for the event in our JavaScript application. We can do this using Laravel Echo. First, we'll use the `private` method to subscribe to the private channel. Then, we may use the `listen` method to listen for the `OrderShipmentStatusUpdated` event. By default, all of the event's public properties will be included on the broadcast event:

```js
Echo.private(`orders.${orderId}`)
    .listen('OrderShipmentStatusUpdated', (e) => {
        console.log(e.order);
    });
```

<a name="defining-broadcast-events"></a>
## Defining Broadcast Events

To inform CakePHP that a given event should be broadcast, you must implement the `Crustum\Broadcasting\Event\BroadcastableInterface` interface on the event class. This interface requires you to implement several methods that define how the event should be broadcast.

Here's a minimal working example:

```php
<?php
declare(strict_types=1);

namespace App\Event;

use Crustum\Broadcasting\Channel\Channel;
use Crustum\Broadcasting\Channel\PrivateChannel;
use Crustum\Broadcasting\Event\BroadcastableInterface;

class ServerCreated implements BroadcastableInterface
{
    public function __construct(
        public User $user,
    ) {}

    public function broadcastChannel(): Channel|array
    {
        return new PrivateChannel('user.' . $this->user->id);
    }

    public function broadcastEvent(): string
    {
        return 'ServerCreated';
    }

    public function broadcastData(): ?array
    {
        return [
            'user' => $this->user->toArray(),
        ];
    }

    public function broadcastSocket(): ?string
    {
        return null;
    }
}
```

After implementing the `BroadcastableInterface` interface, you only need to fire the event using the `broadcast` helper function. The Broadcasting system will automatically extract the required data using only the core methods (`broadcastChannel()`, `broadcastEvent()`, `broadcastData()`, and `broadcastSocket()`) and queue the event for broadcasting:

```php
use function Crustum\Broadcasting\broadcast;

broadcast(new ServerCreated($user));
```

<a name="broadcast-name"></a>
### Broadcast Name

By default, CakePHP will broadcast the event using the value returned by `broadcastEvent()`. The event name is what your JavaScript listens for:

```javascript
.listen('.server.created', function (e) {
    // ...
});
```

<a name="broadcast-data"></a>
### Broadcast Data

When an event is broadcast, all data returned by its `broadcastData` method is automatically serialized and broadcast as the event's payload, allowing you to access any of the data from your JavaScript application. So, for example, if your event has a `broadcastData` method that returns an array, the event's broadcast payload would be:

```json
{
    "user": {
        "id": 1,
        "name": "Patrick Stewart"
        ...
    }
}
```

You control your broadcast payload through the `broadcastData` method. This method should return the array of data that you wish to broadcast as the event payload:

```php
public function broadcastData(): ?array
{
    return ['id' => $this->user->id];
}
```

<a name="broadcast-queue"></a>
### Broadcast Queue

By default, each broadcast event is placed on the default queue using the CakePHP Queue plugin. The Broadcasting plugin integrates with CakePHP Queue to handle async broadcasting. You may customize the queue by implementing the `QueueableInterface` on your event class:

```php
use Crustum\Broadcasting\Event\QueueableInterface;

class ServerCreated implements BroadcastableInterface, QueueableInterface
{
    public function broadcastQueue(): ?string
    {
        return 'broadcasts';
    }

    public function broadcastDelay(): ?int
    {
        return 60;
    }

    public function broadcastExpires(): ?int
    {
        return 3600;
    }

    public function broadcastPriority(): ?string
    {
        return 'high';
    }
}
```

<a name="broadcast-conditions"></a>
### Broadcast Conditions

Sometimes you want to broadcast your event only if a given condition is true. You may define these conditions by implementing the `ConditionalInterface` on your event class:

```php
use Crustum\Broadcasting\Event\ConditionalInterface;

class OrderUpdated implements BroadcastableInterface, ConditionalInterface
{
    public function broadcastWhen(): bool
    {
        return $this->order->value > 100;
    }
}
```

<a name="queue-adapter-system"></a>
### Queue Adapter System

The Broadcasting plugin provides a flexible queue adapter system that allows different implementations for queue interactions. The `QueueAdapterInterface` defines a contract for queue operations that the `BroadcastingManager` uses.

#### Default Adapter

The `CakeQueueAdapter` wraps the standard CakePHP `QueueManager` and provides basic queue functionality. It is used by default if no specific adapter is configured.

#### Custom Adapters

To create a custom queue adapter, implement `QueueAdapterInterface`:

```php
class CustomQueueAdapter implements QueueAdapterInterface
{
    public function push(string $jobClass, array $data = [], array $options = []): bool
    {
        return true;
    }

    public function getUniqueId(string $eventName, string $type, array $data = []): string
    {
        return uniqid($eventName . '_' . $type);
    }
}
```

Configure it in `config/broadcasting.php`:

```php
return [
    'default' => 'pusher',
    'connections' => [
        'pusher' => [
            'className' => 'Crustum/Broadcasting.Pusher',
        ],
    ],
    'queue_adapter' => \Crustum\Broadcasting\Queue\CakeQueueAdapter::class,
];
```

<a name="authorizing-channels"></a>
## Authorizing Channels

Private channels require you to authorize that the currently authenticated user can actually listen on the channel. This is accomplished by making an HTTP request to your CakePHP application with the channel name and allowing your application to determine if the user can listen on that channel. When using [Laravel Echo](#client-side-installation), the HTTP request to authorize subscriptions to private channels will be made automatically; however, you do need to define the proper routes to respond to these requests.

<a name="defining-authorization-callbacks"></a>
### Defining Authorization Callbacks

Fortunately, CakePHP makes it easy to define the routes to respond to channel authorization requests. In the `BroadcastingPlugin` included with your CakePHP application, there is a `config/channels.php` file. In this file, you may use the `Broadcasting::channel` method to register channel authorization callbacks.

#### Using Closures

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::channel('private-orders.{orderId}', function ($user, $orderId) {
    return $user->id === $ordersTable->get($orderId)->user_id;
});
```

The `channel` method accepts two arguments: the name of the channel and a callback which returns `true` or `false` indicating whether the user is authorized to listen on the channel.

All authorization callbacks receive the currently authenticated user as their first argument and any additional wildcard parameters as their subsequent arguments. In this example, we are using the `{orderId}` placeholder to indicate that the "ID" portion of the channel name is a wildcard.

#### Using Channel Classes

You can also use dedicated channel classes for better organization. Generate a channel class using bake:

```bash
bin/cake bake channel Order
```

This creates a channel class implementing `ChannelInterface`:

```php
<?php
namespace App\Broadcasting;

use Crustum\Broadcasting\Channel\ChannelInterface;
use Cake\Datasource\EntityInterface;

class OrderChannel implements ChannelInterface
{
    public function join(EntityInterface $user, ?EntityInterface $model = null): array|bool
    {
        if ($model !== null) {
            return $user->id === $model->user_id;
        }

        return false;
    }
}
```

Register the channel class in `config/channels.php`:

```php
use App\Broadcasting\OrderChannel;
use Crustum\Broadcasting\Broadcasting;

Broadcasting::channel('private-orders.{order}', OrderChannel::class);
```

Channel classes support route model binding. Parameters in the channel pattern (e.g., `{order}`) will be resolved to model entities:

```php
public function join(EntityInterface $user, Order $order): bool
{
    return $user->id === $order->user_id;
}
```

For presence channels, return user data array:

```php
public function join(EntityInterface $user, ?EntityInterface $model = null): array|bool
{
    if ($model !== null && !$model->hasUser($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar_url,
    ];
}
```

<a name="channel-authorization-routes"></a>
### Channel Authorization Routes

When broadcasting is enabled, CakePHP automatically registers the `/broadcasting/auth` route to handle authorization requests. The `/broadcasting/auth` route is automatically placed within the `web` middleware group.

The Broadcasting plugin provides a `BroadcastingAuthController` that handles these authorization requests. You may customize this controller by extending it or by defining your own routes.

<a name="broadcasting-events"></a>
## Broadcasting Events

Once you have defined an event and marked it with the `BroadcastableInterface` interface, you only need to fire the event using the `broadcast` helper function. The event dispatcher will notice that the event is marked with the `BroadcastableInterface` interface and will queue the event for broadcasting:

```php
use function Crustum\Broadcasting\broadcast;

broadcast(new OrderShipmentStatusUpdated($order));
```

<a name="only-to-others"></a>
### Only to Others

When building an application that utilizes event broadcasting, you may occasionally need to broadcast an event to all subscribers to a given channel except for the current user. You may accomplish this using the `Broadcasting` facade and the `toOthers` method:

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::to('orders.' . $order->id)
    ->event('OrderShipmentStatusUpdated')
    ->data(['order' => $order])
    ->toOthers()
    ->send();
```

To better understand when you may want to use the `toOthers` method, let's imagine a task list application where a user may create a new task by entering a task name. To create a task, your application might make a request to a `/task` URL which broadcasts the task's creation and returns a JSON representation of the new task. When your JavaScript application receives the response from the end-point, it might directly insert the new task into its task list like so:

```js
axios.post('/task', task)
    .then((response) => {
        this.tasks.push(response.data);
    });
```

However, remember that we also broadcast the task's creation. If your JavaScript application is also listening for this event in order to add tasks to the task list, you will have duplicate tasks in your list: one from the end-point and one from the broadcast. You may solve this by using the `toOthers` method to instruct the broadcaster to not broadcast the event to the current user.

<a name="only-to-others-configuration"></a>
#### Configuration

When you initialize a Laravel Echo instance, a socket ID is assigned to the connection. If you are using a global [Axios](https://github.com/axios/axios) instance to make HTTP requests from your JavaScript application, the socket ID will automatically be attached to every outgoing request as an `X-Socket-ID` header. Then, when you call the `toOthers` method, CakePHP will extract the socket ID from the header and instruct the broadcaster to not broadcast to any connections with that socket ID.

If you are not using a global Axios instance, you will need to manually configure your JavaScript application to send the `X-Socket-ID` header with all outgoing requests. You may retrieve the socket ID using the `Echo.socketId` method:

```js
var socketId = Echo.socketId();
```

<a name="customizing-the-connection"></a>
### Customizing the Connection

If your application interacts with multiple broadcast connections and you want to broadcast an event using a broadcaster other than your default, you may specify which connection to push an event to using the `via` method:

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::to('orders.' . $order->id)
    ->event('OrderShipmentStatusUpdated')
    ->data(['order' => $order])
    ->via('pusher')
    ->send();
```

<a name="anonymous-events"></a>
### Anonymous Events

Sometimes, you may want to broadcast a simple event to your application's frontend without creating a dedicated event class. To accommodate this, the `Broadcasting` facade allows you to broadcast "anonymous events":

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::to('orders.' . $order->id)
    ->event('OrderPlaced')
    ->data(['order' => $order])
    ->send();
```

The example above will broadcast an event like the following:

```json
{
    "event": "OrderPlaced",
    "data": {"order": {"id": 1, "total": 100}},
    "channel": "orders.1"
}
```

If you would like to broadcast the anonymous event on a private or presence channel, you may utilize the `private` and `presence` methods:

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::private('orders.' . $order->id)
    ->event('OrderPlaced')
    ->data(['order' => $order])
    ->send();

Broadcasting::presence('channels.' . $channel->id)
    ->event('UserJoined')
    ->data(['user' => $user])
    ->send();
```

Broadcasting an anonymous event using the `send` method dispatches the event to your application's queue for processing. However, if you would like to broadcast the event immediately, you may use the `sendNow` method:

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::to('orders.' . $order->id)
    ->event('OrderPlaced')
    ->data(['order' => $order])
    ->sendNow();
```

To broadcast the event to all channel subscribers except the currently authenticated user, you can invoke the `toOthers` method:

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::to('orders.' . $order->id)
    ->event('OrderPlaced')
    ->data(['order' => $order])
    ->toOthers()
    ->send();
```

<a name="receiving-broadcasts"></a>
## Receiving Broadcasts

<a name="listening-for-events"></a>
### Listening for Events

Once you have [installed and instantiated Laravel Echo](#client-side-installation), you are ready to start listening for events that are broadcast from your CakePHP application. First, use the `channel` method to retrieve an instance of a channel, then call the `listen` method to listen for a specified event:

```js
Echo.channel(`orders.${this.order.id}`)
    .listen('OrderShipmentStatusUpdated', (e) => {
        console.log(e.order.name);
    });
```

If you would like to listen for events on a private channel, use the `private` method instead. You may continue to chain calls to the `listen` method to listen for multiple events on a single channel:

```js
Echo.private(`orders.${this.order.id}`)
    .listen('OrderShipmentStatusUpdated', (e) => {
        console.log(e.order);
    })
    .listen('OrderCancelled', (e) => {
        console.log(e.order);
    });
```

<a name="stop-listening-for-events"></a>
#### Stop Listening for Events

If you would like to stop listening to a given event without [leaving the channel](#leaving-a-channel), you may use the `stopListening` method:

```js
Echo.private(`orders.${this.order.id}`)
    .stopListening('OrderShipmentStatusUpdated');
```

<a name="leaving-a-channel"></a>
### Leaving a Channel

To leave a channel, you may call the `leaveChannel` method on your Echo instance:

```js
Echo.leaveChannel(`orders.${this.order.id}`);
```

If you would like to leave a channel and also its associated private and presence channels, you may call the `leave` method:

```js
Echo.leave(`orders.${this.order.id}`);
```

<a name="namespaces"></a>
### Namespaces

You may have noticed in the examples above that we did not specify the full `App\Events` namespace for the event classes. This is because Echo will automatically assume the events are located in the `App\Events` namespace. However, you may configure the root namespace when you instantiate Echo by passing a `namespace` configuration option:

```js
window.Echo = new Echo({
    broadcaster: 'pusher',
    // ...
    namespace: 'App.Other.Namespace'
});
```

Alternatively, you may prefix event classes with a `.` when subscribing to them using Echo. This will allow you to always specify the fully-qualified class name:

```js
Echo.channel('orders')
    .listen('.Namespace\\Event\\Class', (e) => {
        // ...
    });
```

<a name="using-react-or-vue"></a>
### Using React or Vue

Laravel Echo includes React and Vue hooks that make it painless to listen for events. To get started, invoke the `useEcho` hook, which is used to listen for private events. The `useEcho` hook will automatically leave channels when the consuming component is unmounted:

```js tab=React
import { useEcho } from "@laravel/echo-react";

useEcho(
    `orders.${orderId}`,
    "OrderShipmentStatusUpdated",
    (e) => {
        console.log(e.order);
    },
);
```

```vue tab=Vue
<script setup lang="ts">
import { useEcho } from "@laravel/echo-vue";

useEcho(
    `orders.${orderId}`,
    "OrderShipmentStatusUpdated",
    (e) => {
        console.log(e.order);
    },
);
</script>
```

You may listen to multiple events by providing an array of events to `useEcho`:

```js
useEcho(
    `orders.${orderId}`,
    ["OrderShipmentStatusUpdated", "OrderShipped"],
    (e) => {
        console.log(e.order);
    },
);
```

You may also specify the shape of the broadcast event payload data, providing greater type safety and editing convenience:

```ts
type OrderData = {
    order: {
        id: number;
        user: {
            id: number;
            name: string;
        };
        created_at: string;
    };
};

useEcho<OrderData>(`orders.${orderId}`, "OrderShipmentStatusUpdated", (e) => {
    console.log(e.order.id);
    console.log(e.order.user.id);
});
```

The `useEcho` hook will automatically leave channels when the consuming component is unmounted; however, you may utilize the returned functions to manually stop / start listening to channels programmatically when necessary:

```js tab=React
import { useEcho } from "@laravel/echo-react";

const { leaveChannel, leave, stopListening, listen } = useEcho(
    `orders.${orderId}`,
    "OrderShipmentStatusUpdated",
    (e) => {
        console.log(e.order);
    },
);

// Stop listening without leaving channel...
stopListening();

// Start listening again...
listen();

// Leave channel...
leaveChannel();

// Leave a channel and also its associated private and presence channels...
leave();
```

```vue tab=Vue
<script setup lang="ts">
import { useEcho } from "@laravel/echo-vue";

const { leaveChannel, leave, stopListening, listen } = useEcho(
    `orders.${orderId}`,
    "OrderShipmentStatusUpdated",
    (e) => {
        console.log(e.order);
    },
);

// Stop listening without leaving channel...
stopListening();

// Start listening again...
listen();

// Leave channel...
leaveChannel();

// Leave a channel and also its associated private and presence channels...
leave();
</script>
```

<a name="react-vue-connecting-to-public-channels"></a>
#### Connecting to Public Channels

To connect to a public channel, you may use the `useEchoPublic` hook:

```js tab=React
import { useEchoPublic } from "@laravel/echo-react";

useEchoPublic("posts", "PostPublished", (e) => {
    console.log(e.post);
});
```

```vue tab=Vue
<script setup lang="ts">
import { useEchoPublic } from "@laravel/echo-vue";

useEchoPublic("posts", "PostPublished", (e) => {
    console.log(e.post);
});
</script>
```

<a name="react-vue-connecting-to-presence-channels"></a>
#### Connecting to Presence Channels

To connect to a presence channel, you may use the `useEchoPresence` hook:

```js tab=React
import { useEchoPresence } from "@laravel/echo-react";

useEchoPresence("posts", "PostPublished", (e) => {
    console.log(e.post);
});
```

```vue tab=Vue
<script setup lang="ts">
import { useEchoPresence } from "@laravel/echo-vue";

useEchoPresence("posts", "PostPublished", (e) => {
    console.log(e.post);
});
</script>
```

<a name="presence-channels"></a>
## Presence Channels

Presence channels build on the security of private channels while exposing the additional feature of awareness of who is subscribed to the channel. This makes it easy to build powerful, collaborative application features such as notifying users when another user is viewing the same page or listing the inhabitants of a chat room.

<a name="authorizing-presence-channels"></a>
### Authorizing Presence Channels

All presence channels are also private channels; therefore, users must be [authorized to access them](#authorizing-channels). However, when defining authorization callbacks for presence channels, you will not return `true` if the user is authorized to join the channel. Instead, you should return an array of data about the user.

The data returned by the authorization callback will be made available to the presence channel event listeners in your JavaScript application. If the user is not authorized to join the presence channel, you should return `false` or `null`:

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::channel('presence-chat.{roomId}', function ($user, $roomId) {
    if ($user->canJoinRoom($roomId)) {
        return ['id' => $user->id, 'name' => $user->name];
    }
});
```

<a name="joining-presence-channels"></a>
### Joining Presence Channels

To join a presence channel, you may use Echo's `join` method. The `join` method will return a `PresenceChannel` implementation which, along with exposing the `listen` method, allows you to subscribe to the `here`, `joining`, and `leaving` events.

```js
Echo.join(`chat.${roomId}`)
    .here((users) => {
        // ...
    })
    .joining((user) => {
        console.log(user.name);
    })
    .leaving((user) => {
        console.log(user.name);
    })
    .error((error) => {
        console.error(error);
    });
```

The `here` callback will be executed immediately once the channel is joined successfully, and will receive an array containing the user information for all of the other users currently subscribed to the channel. The `joining` method will be executed when a new user joins a channel, while the `leaving` method will be executed when a user leaves the channel. The `error` method will be executed when the authentication endpoint returns an HTTP status code other than 200 or if there is a problem parsing the returned JSON.

<a name="broadcasting-to-presence-channels"></a>
### Broadcasting to Presence Channels

Presence channels may receive events just like public or private channels. Using the example of a chatroom, we may want to broadcast `NewMessage` events to the room's presence channel. To do so, we'll return an instance of `PresenceChannel` from the event's `broadcastChannel` method:

```php
use Crustum\Broadcasting\Channel\PresenceChannel;

public function broadcastChannel(): array
{
    return [
        new PresenceChannel('chat.' . $this->message->room_id),
    ];
}
```

As with other events, you may use the `Broadcasting` facade and the `toOthers` method to exclude the current user from receiving the broadcast:

```php
use Crustum\Broadcasting\Broadcasting;

Broadcasting::presence('chat.' . $message->room_id)
    ->event('NewMessage')
    ->data(['message' => $message])
    ->send();

Broadcasting::presence('chat.' . $message->room_id)
    ->event('NewMessage')
    ->data(['message' => $message])
    ->toOthers()
    ->send();
```

As typical of other types of events, you may listen for events sent to presence channels using Echo's `listen` method:

```js
Echo.join(`chat.${roomId}`)
    .here(/* ... */)
    .joining(/* ... */)
    .leaving(/* ... */)
    .listen('NewMessage', (e) => {
        // ...
    });
```

<a name="model-broadcasting"></a>
## Model Broadcasting

> **Warning**
> Before reading the following documentation about model broadcasting, we recommend you become familiar with the general concepts of CakePHP's model broadcasting services as well as how to manually create and listen to broadcast events.

It is common to broadcast events when your application's ORM entities are created, updated, or deleted. Of course, this can easily be accomplished by manually defining custom events for ORM model state changes and marking those events with the `BroadcastableInterface` interface.

However, if you are not using these events for any other purposes in your application, it can be cumbersome to create event classes for the sole purpose of broadcasting them. To remedy this, CakePHP allows you to indicate that an ORM model should automatically broadcast its state changes.

<a name="model-broadcasting-behavior"></a>
### Model Broadcasting Behavior

To get started, your ORM Table should use the `Broadcasting.Broadcasting` behavior. The behavior should define which events should be broadcast and how:

```php
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class PostsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->addBehavior('Broadcasting.Broadcasting', [
            'events' => [
                'Model.afterSave' => 'saved',
                'Model.afterDelete' => 'deleted',
            ],
            'channels' => function ($entity, $event) {
                return ['posts.' . $entity->id];
            },
            'payload' => function ($entity, $event) {
                return [
                    'post' => $entity->toArray(),
                ];
            },
        ]);
    }
}
```

Once your model includes this behavior and defines its broadcast configuration, it will begin automatically broadcasting events when a model instance is created, updated, or deleted.

#### Default Channel Name Generation

By default, the behavior broadcasts to channels using the entity's fully qualified class name pattern. For example, if you save an `App\Model\Entity\User` entity with ID 5, it will broadcast on the `App.Model.Entity.User.5` channel.

The channel name generation process:

1. Gets the entity's fully qualified class name (e.g., `App\Model\Entity\User`)
2. Replaces backslashes with dots (e.g., `App.Model.Entity.User`)
3. Appends the entity ID if present (e.g., `App.Model.Entity.User.5`)

You can customize the channels using the `channels` configuration option with a callback:

```php
$this->addBehavior('Broadcasting.Broadcasting', [
    'channels' => function ($entity, $event) {
        return ['user.' . $entity->user_id];
    }
]);
```

Or specify static channels as an array:

```php
$this->addBehavior('Broadcasting.Broadcasting', [
    'channels' => ['orders', 'notifications']
]);
```

If you don't specify custom channels, the behavior will use the default entity-based channel naming.

#### Default Event Name Generation

By default, the behavior generates event names by combining the entity's short class name with the event type. For example, saving a `User` entity that is new will broadcast as `UserCreated`, while updating an existing user will broadcast as `UserUpdated`.

The event name generation process:

1. Gets the entity's short class name (e.g., `User` from `App\Model\Entity\User`)
2. Capitalizes the event type (e.g., `created`, `updated`, `deleted`)
3. Concatenates them (e.g., `UserCreated`, `UserUpdated`, `UserDeleted`)

The behavior automatically maps `Model.afterSave` events to either `created` or `updated` based on the entity's `isNew()` status.

You can customize the event name using the `eventName` configuration option with a callback:

```php
$this->addBehavior('Broadcasting.Broadcasting', [
    'eventName' => function ($entity, $event) {
        return 'order.' . $event;
    }
]);
```

Or specify a static event name:

```php
$this->addBehavior('Broadcasting.Broadcasting', [
    'eventName' => 'model.updated'
]);
```

#### Configuration Options

The behavior supports several configuration options:

- `events`: Maps CakePHP model events to broadcast event names (default: `['Model.afterSave' => 'saved', 'Model.afterDelete' => 'deleted']`)
- `broadcastEvents`: Controls which event types are enabled (default: `['created' => true, 'updated' => true, 'deleted' => true]`)
- `channels`: Callback or array defining which channels to broadcast to (default: entity-based channel naming)
- `payload`: Callback or array defining the data to broadcast (default: `$entity->toArray()` with `event_type`)
- `eventName`: Callback or string defining the broadcast event name (default: `{EntityClass}{EventType}`)
- `connection`: Broadcasting connection to use (default: `'default'`)
- `queue`: Queue name for async broadcasting (default: `null` for synchronous)
- `enabled`: Whether broadcasting is enabled (default: `true`)

You may have noticed that the behavior receives a string `$event` argument in the callbacks. This argument contains the type of event that has occurred on the model and will have a value of `created`, `updated`, `deleted`, etc. By inspecting the value of this variable, you may determine which channels (if any) the model should broadcast to for a particular event:

```php
'channels' => function ($entity, $event) {
    return match ($event) {
        'deleted' => [],
        default => ['posts.' . $entity->id, 'posts'],
    };
}
```

<a name="listening-for-model-broadcasts"></a>
### Listening for Model Broadcasts

Once you have added the `Broadcasting.Broadcasting` behavior to your model and defined your model's broadcast configuration, you are ready to start listening for broadcasted model events within your client-side application. Before getting started, you may wish to consult the complete documentation on [listening for events](#listening-for-events).

First, use the `private` method to retrieve an instance of a channel, then call the `listen` method to listen for a specified event. Since model broadcast events are not associated with an "actual" event within your application's `src/Event` directory, the event name must be prefixed with a `.` to indicate it does not belong to a particular namespace. Each model broadcast event has a `model` property which contains all of the broadcastable properties of the model:

```js
Echo.private(`posts.${this.post.id}`)
    .listen('.PostUpdated', (e) => {
        console.log(e.post);
    });
```

<a name="client-events"></a>
## Client Events

> [!NOTE]
> When using [Pusher Channels](https://pusher.com/channels), you must enable the "Client Events" option in the "App Settings" section of your [application dashboard](https://dashboard.pusher.com/) in order to send client events.

Sometimes you may wish to broadcast an event to other connected clients without hitting your CakePHP application at all. This can be particularly useful for things like "typing" notifications, where you want to alert users of your application that another user is typing a message on a given screen.

To broadcast client events, you may use Echo's `whisper` method:

```js
Echo.private(`chat.${roomId}`)
    .whisper('typing', {
        name: this.user.name
    });
```

To listen for client events, you may use the `listenForWhisper` method:

```js
Echo.private(`chat.${roomId}`)
    .listenForWhisper('typing', (e) => {
        console.log(e.name);
    });
```

<a name="notifications"></a>
## Notifications

By pairing event broadcasting with [Notification Plugin](https://github.com/Crustum/Notification), your JavaScript application may receive new notifications as they occur without needing to refresh the page. Before getting started, be sure to read over the documentation on using [the broadcast notification channel](https://github.com/Crustum/BroadcastingNotification/blob/main/docs/index.md).

Once you have configured a notification to use the broadcast channel, you may listen for the broadcast events using Echo's `notification` method. Remember, the channel name should match the class name of the entity receiving the notifications:

```js
Echo.private(`App.Model.Entity.User.${userId}`)
    .notification((notification) => {
        console.log(notification.type);
    });
```

In this example, all notifications sent to `App\Model\Entity\User` instances via the `broadcast` channel would be received by the callback. A channel authorization callback for the `App.Model.Entity.User.{id}` channel is included in your application's `config/channels.php` file.

<a name="testing"></a>
## Testing

You may use the `\Crustum\Broadcasting\TestSuite\BroadcastingTrait` to prevent broadcasts from being sent during testing. Typically, sending broadcasts is unrelated to the code you are actually testing. Most likely, it is sufficient to simply assert that your application was instructed to broadcast a given event.

After adding the `BroadcastingTrait` to your test case, you may then assert that broadcasts were sent to channels and even inspect the data the broadcasts received:

```php
<?php
namespace App\Test\TestCase;

use Crustum\Broadcasting\Broadcasting;
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Cake\TestSuite\TestCase;

class OrderTest extends TestCase
{
    use BroadcastingTrait;

    protected array $fixtures = ['app.Users', 'app.Orders'];

    public function testOrderCreatedBroadcast(): void
    {
        $ordersTable = $this->getTableLocator()->get('Orders');

        $order = $ordersTable->newEntity([
            'user_id' => 1,
            'total' => 99.99,
            'status' => 'paid',
        ]);
        $ordersTable->save($order);

        $this->assertBroadcastSent('OrderCreated');

        $this->assertBroadcastSentToChannel('orders', 'OrderCreated');

        $this->assertBroadcastPayloadContains('OrderCreated', 'total', 99.99);

        $this->assertBroadcastSentTimes('OrderCreated', 1);

        $this->assertBroadcastCount(1);
    }
}
```

When you use the `BroadcastingTrait`, all broadcasts are captured instead of being sent, allowing you to make assertions. The trait provides several helper methods to inspect captured broadcasts:

```php
public function testBroadcastDetails(): void
{
    Broadcasting::to('orders')
        ->event('OrderCreated')
        ->data(['order_id' => 123, 'total' => 99.99])
        ->send();

    $broadcasts = $this->getBroadcastsByEvent('OrderCreated');
    $this->assertCount(1, $broadcasts);

    $broadcastData = $broadcasts[0];
    $this->assertEquals('OrderCreated', $broadcastData['event']);
    $this->assertContains('orders', $broadcastData['channels']);
    $this->assertEquals(123, $broadcastData['payload']['order_id']);
}
```

<a name="asserting-broadcasts-sent"></a>
### Asserting Broadcasts Sent

You can assert that specific events were broadcast:

```php
public function testMultipleBroadcasts(): void
{
    Broadcasting::to('orders')->event('OrderCreated')->send();
    Broadcasting::to('orders')->event('OrderUpdated')->send();

    $this->assertBroadcastSent('OrderCreated');
    $this->assertBroadcastSent('OrderUpdated');
    $this->assertBroadcastNotSent('OrderDeleted');
}
```

Assert broadcasts were sent a specific number of times:

```php
public function testBroadcastCount(): void
{
    Broadcasting::to('orders')->event('OrderCreated')->send();
    Broadcasting::to('users')->event('OrderCreated')->send();
    Broadcasting::to('admin')->event('OrderCreated')->send();

    $this->assertBroadcastSentTimes('OrderCreated', 3);
    $this->assertBroadcastCount(3);
}
```

Or verify no broadcasts were sent:

```php
public function testNoBroadcastsWhenInactive(): void
{
    $ordersTable = $this->getTableLocator()->get('Orders');
    $ordersTable->disableBroadcasting();

    $order = $ordersTable->newEntity(['user_id' => 1]);
    $ordersTable->save($order);

    $this->assertNoBroadcastsSent();
}
```

<a name="asserting-broadcasts-to-channels"></a>
### Asserting Broadcasts to Channels

You can assert that broadcasts were sent to specific channels:

```php
public function testBroadcastToSpecificChannel(): void
{
    Broadcasting::to('orders')->event('OrderCreated')->send();

    $this->assertBroadcastSentToChannel('orders', 'OrderCreated');
}
```

Assert broadcasts to multiple channels:

```php
public function testBroadcastToMultipleChannels(): void
{
    Broadcasting::to(['orders', 'admin', 'notifications'])
        ->event('OrderCreated')
        ->send();

    $this->assertBroadcastSentToChannels(['orders', 'admin'], 'OrderCreated');
}
```

Or verify a broadcast was not sent to a channel:

```php
public function testBroadcastNotSentToPrivateChannel(): void
{
    Broadcasting::to('orders')->event('OrderCreated')->send();

    $this->assertBroadcastSentToChannel('orders', 'OrderCreated');
    $this->assertBroadcastNotSentToChannel('private-admin', 'OrderCreated');
}
```

Count broadcasts to specific channels:

```php
public function testMultipleBroadcastsToChannel(): void
{
    Broadcasting::to('orders')->event('OrderCreated')->send();
    Broadcasting::to('orders')->event('OrderUpdated')->send();

    $broadcasts = $this->getBroadcastsToChannel('orders');
    $this->assertCount(2, $broadcasts);

    $this->assertBroadcastToChannelTimes('orders', 'OrderCreated', 1);
}
```

<a name="inspecting-broadcast-content"></a>
### Inspecting Broadcast Content

Sometimes you need to verify the specific content or data contained in a broadcast. The `BroadcastingTrait` provides several methods to retrieve and inspect captured broadcasts:

```php
public function testBroadcastContainsCorrectData(): void
{
    Broadcasting::to('orders')
        ->event('OrderCreated')
        ->data([
            'order_id' => 123,
            'user_id' => 456,
            'total' => 99.99,
            'status' => 'paid',
        ])
        ->send();

    $this->assertBroadcastPayloadContains('OrderCreated', 'order_id', 123);
    $this->assertBroadcastPayloadContains('OrderCreated', 'total', 99.99);

    $broadcasts = $this->getBroadcastsByEvent('OrderCreated');
    $payload = $broadcasts[0]['payload'];

    $this->assertEquals(123, $payload['order_id']);
    $this->assertEquals(456, $payload['user_id']);
    $this->assertEquals('paid', $payload['status']);
}
```

Assert exact payload match:

```php
public function testBroadcastPayloadMatch(): void
{
    $expectedPayload = [
        'order_id' => 123,
        'status' => 'paid',
    ];

    Broadcasting::to('orders')
        ->event('OrderCreated')
        ->data($expectedPayload)
        ->send();

    $this->assertBroadcastPayloadEquals('OrderCreated', $expectedPayload);
}
```

Inspect complex nested data:

```php
public function testComplexBroadcastData(): void
{
    $orderData = [
        'order' => [
            'id' => 123,
            'items' => [
                ['name' => 'Product 1', 'qty' => 2],
                ['name' => 'Product 2', 'qty' => 1],
            ],
            'total' => 299.99,
        ],
        'user' => [
            'id' => 456,
            'name' => 'John Doe',
        ],
    ];

    Broadcasting::to('orders')->event('OrderCreated')->data($orderData)->send();

    $broadcasts = $this->getBroadcasts();
    $this->assertCount(1, $broadcasts);

    $payload = $broadcasts[0]['payload'];
    $this->assertArrayHasKey('order', $payload);
    $this->assertArrayHasKey('user', $payload);
    $this->assertCount(2, $payload['order']['items']);
}
```

<a name="testing-connections"></a>
### Testing Connections

You can assert that broadcasts were sent via specific broadcasting connections:

```php
public function testBroadcastViaConnection(): void
{
    Broadcasting::setConfig('pusher', [
        'className' => \Crustum\Broadcasting\TestSuite\TestBroadcaster::class,
        'connectionName' => 'pusher',
    ]);
    Broadcasting::getRegistry()->reset();

    Broadcasting::to('orders')
        ->event('OrderCreated')
        ->connection('pusher')
        ->send();

    $this->assertBroadcastSentViaConnection('pusher', 'OrderCreated');
}
```

Filter broadcasts by connection:

```php
public function testMultipleConnections(): void
{
    Broadcasting::setConfig('redis', [
        'className' => \Crustum\Broadcasting\TestSuite\TestBroadcaster::class,
        'connectionName' => 'redis',
    ]);
    Broadcasting::getRegistry()->reset();

    Broadcasting::to('orders')->event('OrderCreated')->connection('default')->send();
    Broadcasting::to('users')->event('UserRegistered')->connection('redis')->send();

    $defaultBroadcasts = $this->getBroadcastsByConnection('default');
    $redisBroadcasts = $this->getBroadcastsByConnection('redis');

    $this->assertCount(1, $defaultBroadcasts);
    $this->assertCount(1, $redisBroadcasts);
}
```

<a name="testing-socket-exclusion"></a>
### Testing Socket Exclusion

When using the `toOthers` method to exclude specific sockets from receiving broadcasts, you can test this functionality:

```php
public function testBroadcastExcludesSocket(): void
{
    Broadcasting::to('orders')
        ->event('OrderCreated')
        ->toOthers()
        ->setSocket('socket-abc-123')
        ->send();

    $this->assertBroadcastSent('OrderCreated');
    $this->assertBroadcastExcludedSocket('socket-abc-123', 'OrderCreated');

    $broadcasts = $this->getBroadcasts();
    $this->assertEquals('socket-abc-123', $broadcasts[0]['socket']);
}
```

<a name="testing-model-broadcasting"></a>
### Testing Model Broadcasting

When testing models that use the `BroadcastingBehavior`, you can verify broadcasts are sent automatically:

```php
use Crustum\Broadcasting\TestSuite\BroadcastingTrait;
use Cake\TestSuite\TestCase;

class PostTest extends TestCase
{
    use BroadcastingTrait;

    protected array $fixtures = ['app.Posts'];

    public function testPostBroadcastsOnSave(): void
    {
        $postsTable = $this->getTableLocator()->get('Posts');
        $postsTable->addBehavior('Broadcasting.Broadcasting', [
            'channels' => fn($entity) => ['posts.' . $entity->id],
        ]);

        $post = $postsTable->newEntity(['title' => 'Test Post']);
        $postsTable->save($post);

        $this->assertBroadcastSent('PostCreated');
        $this->assertBroadcastSentToChannel('posts.' . $post->id, 'PostCreated');
    }

    public function testPostBroadcastCustomization(): void
    {
        $postsTable = $this->getTableLocator()->get('Posts');
        $postsTable->addBehavior('Broadcasting.Broadcasting', [
            'channels' => ['posts', 'admin'],
            'eventName' => 'post.created',
            'payload' => function ($entity) {
                return [
                    'id' => $entity->id,
                    'title' => $entity->title,
                ];
            },
        ]);

        $post = $postsTable->newEntity(['title' => 'Test Post']);
        $postsTable->save($post);

        $this->assertBroadcastSent('post.created');
        $this->assertBroadcastSentToChannels(['posts', 'admin'], 'post.created');
        $this->assertBroadcastPayloadContains('post.created', 'title', 'Test Post');
    }
}
```

<a name="available-assertions"></a>
### Available Assertions

The `BroadcastingTrait` provides the following assertion methods for your tests:

| Method | Description |
|--------|-------------|
| `assertBroadcastSent(string $event)` | Assert a broadcast of the given event was sent |
| `assertBroadcastSentAt(int $at, string $event)` | Assert a broadcast at specific index was sent |
| `assertBroadcastNotSent(string $event)` | Assert a broadcast was not sent |
| `assertBroadcastSentToChannel(string $channel, string $event)` | Assert a broadcast was sent to a specific channel |
| `assertBroadcastSentToChannels(array $channels, string $event)` | Assert a broadcast was sent to multiple channels |
| `assertBroadcastNotSentToChannel(string $channel, string $event)` | Assert a broadcast was not sent to a channel |
| `assertBroadcastPayloadContains(string $event, string $key, mixed $value)` | Assert broadcast contains specific data |
| `assertBroadcastPayloadEquals(string $event, array $payload)` | Assert broadcast payload matches exactly |
| `assertBroadcastSentViaConnection(string $connection, string $event)` | Assert a broadcast was sent via a connection |
| `assertBroadcastExcludedSocket(string $socket, string $event)` | Assert a broadcast excluded a socket |
| `assertBroadcastSentTimes(string $event, int $times)` | Assert a broadcast was sent N times |
| `assertBroadcastToChannelTimes(string $channel, string $event, int $times)` | Assert a broadcast to channel was sent N times |
| `assertBroadcastCount(int $count)` | Assert the total number of broadcasts sent |
| `assertNoBroadcastsSent()` | Assert no broadcasts were sent |

Helper methods for retrieving captured broadcasts:

| Method | Description |
|--------|-------------|
| `getBroadcasts()` | Get all captured broadcasts |
| `getBroadcastsByEvent(string $event)` | Get broadcasts of a specific event |
| `getBroadcastsToChannel(string $channel)` | Get broadcasts sent to a channel |
| `getBroadcastsByConnection(string $connection)` | Get broadcasts sent via a connection |
