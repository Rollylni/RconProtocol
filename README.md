# RconProtocol

[![Version](https://img.shields.io/packagist/v/rollylni/rcon-protocol?style=plastic)](https://packagist.org/packages/rollylni/rcon-protocol)
[![Downloads](https://img.shields.io/packagist/dt/rollylni/rcon-protocol?style=plastic)](https://packagist.org/packages/rollylni/rcon-protocol)
[![License](https://img.shields.io/packagist/l/rollylni/rcon-protocol?style=plastic)](https://en.wikipedia.org/wiki/MIT_License)
[![PHPVersion](https://img.shields.io/packagist/php-v/rollylni/rcon-protocol?style=plastic)](https://packagist.org/packages/rollylni/rcon-protocol)

RCON Protocol is a TCP / IP, communication-based protocol that allows console commands t mo be issued to a server via a "remote console",  or RCON. Most often, RCON is used to allow server owners toanage their game servers without direct access to the machine the server is running on.  For commands to be accepted, the connection must first be authenticated using the server's RCON password

## Installation
```bash
composer require rollylni/rcon-protocol
```

## Example Server
```php
require "vendor/autoload.php";
use RconProtocol\RconServer;
use RconProtocol\ServerClient;
use RconProtocol\Packet;

$port = 27015;
$host = "0.0.0.0";

$server = new RconServer($port, $host);

# set ur password
$server->setPassword("Your Password");
# or generate
$len = 8;
$server->generatePassword($len);

$server->setMaxClients(10);
# client login timeout
$server->setTimeout(10);

# Events
$handler = $server->getHandler();
$handler->add("onTimeout", function(ServerClient $c) {
   echo $c->getPeerName() . ": timeout!\n";
});

$handler->add("onConnection", function(ServerClient $c) {
   echo $c->getPeerName() . ": connection...\n";
});

$handler->add("onDisconnection", function(ServerClient $c) {
   echo $c->getPeerName() . ": disconnection...\n";
});

$handler->add("onReceive", function(ServerClient $c, Packet $p) {
   echo $c->getPeerName() . ": ". $p->getBody() ."\n";
});

$handler->add("onAuthorized", function(ServerClient $c) {
   echo $c->getPeerName() . ": authorized!\n";
});

$handler->add("onFailed", function(ServerClient $c, string $input) {
   echo $c->getPeerName() . ": '$input' wrong password!\n";
});

$handler->add("onCommand", function(ServerClient $c, string $cmd) {
   //exec command
   echo $c->getPeerName() . ": $cmd\n";
});

// Starting RCON server
$server->start();
// Stopping
$server->stop();
```

## Example Client
```php
use RconProtocol\RconClient;

$serverHost = "0.0.0.0";
$serverPort = 27015;
$serverPassword = "";
$execCommand = "cmd";
$timeout = 10;

$client = new RconClient($serverHost, $serverPort, $timeout);
$client->connect();
if ($client->authorize($serverPassword)) {
   echo "Response: " . $client->sendCommand($execCommand);
} else {
   $client->disconnect();
}
```
