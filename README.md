# CakePHP Broadcasting Plugin

The **Broadcasting** plugin provides real-time event broadcasting for CakePHP applications using WebSocket connections compatible with the Pusher protocol.

Broadcasting enables real-time communication between your CakePHP application and connected clients through WebSocket channels. The plugin supports public channels for open communication, private channels for authenticated users, and presence channels for tracking active users in real-time.

The plugin implements the Pusher protocol, making it compatible with Pusher JavaScript libraries and other Pusher-compatible clients. It includes a built-in WebSocket server, channel authorization system, and flexible broadcasting adapters for immediate or queued message delivery.

The plugin integrates seamlessly with CakePHP's event system and provides command line tools for running the WebSocket server and managing connections.

## Requirements

* PHP 8.2+

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.

