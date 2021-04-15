<?php

/**
 * RCON Protocol is a TCP / IP, communication-based protocol that allows console commands to be issued to a server via a "remote console",
 * or RCON. Most often, RCON is used to allow server owners to manage their game servers without direct access to the machine the server is running on.
 * For commands to be accepted, the connection must first be authenticated using the server's RCON password
 * 
 * Project Homepage {@link https://github.com/Rollylni/RconProtocol}
 * Source RCON Protocol {@link https://developer.valvesoftware.com/wiki/Source_RCON_Protocol}
 * 
 * @copyright 2019-2021 Rollylni
 * @author Faruch N. <rollyllni@gmail.com>
 * @license MIT
 */
namespace RconProtocol;

use function stream_socket_get_name;
use function stream_socket_server;
use function stream_socket_accept;
use function stream_set_blocking;
use function stream_select;
use function is_resource;
use function array_search;
use function in_array;
use function unpack;
use function fclose;
use function fwrite;
use function strlen;
use function fread;
use function count;
use function feof;
use function time;

use Triggers;
use Throwable;

use RconProtocol\Exception\RconException;

class RconServer {
    
    /**
     * RCON Server Password
     * 
     * @var string
     */
    private $password = "";
    
    /**
     * 
     * @var string 
     */
    private $host = "127.0.0.1";
    
    /**
     * the port on which the RCON server will run
     * 
     * @var int
     */
    private $port = 27015;
    
    /**
     * time for client authorization
     * 
     * @var int
     */
    private $timeout = 10;
    
    /**
     * the number of clients whose requests will be processing
     * 
     * @var int
     */
    private $maxClients = 20;
    
    /**
     * Connections
     * 
     * @var ServerClient[]
     */
    protected $clients = [];
    
    /**
     * 
     * @var resource
     */
    protected $socket = null;
    
    /**
     * 
     * @var bool
     */
    protected $stop = false;
    
    /**
     * 
     * @var Triggers
     */
    protected $handler = null;


    /**
     * 
     * @param int $port
     * @param string $host
     * @param string $password
     * @param int $maxClients
     */
    public function __construct(int $port = 27015, string $host = "127.0.0.1", string $password = "", int $maxClients = 20) {
        $this->password = $password;
        $this->maxClients = $maxClients;
        $this->host = $host;
        $this->port = $port;
    }
    
    /**
     * 
     * @param resource $stream
     * @return Packet|null
     */
    public static function receivePacket($stream): ?Packet {
        if (!is_resource($stream)) {
            return null;
        }
        
        if (!$data = fread($stream, 4)) {
            return null;
        }
        
        $size = unpack("V1size", $data)["size"];
        $pack = fread($stream, $size);
        
        if (!$pack || strlen($pack) < 0) {
            return null;
        }
        
        $packet = new Packet($pack);
        try {
            return $packet->decode(false);
        } catch (Throwable $e) {
            return null;
        }
    }
    
    /**
     * 
     * @param resource $stream
     * @param int $id
     * @param int $type
     * @param string $body
     */
    public static function sendPacket($stream, int $id, int $type, string $body = Packet::EMPTY_STRING) {
        if (!is_resource($stream)) {
            return;
        }
        $packet = new Packet($id, $type, $body);
        $packet->encode();
        fwrite($stream, $packet->getBinary(), strlen($packet->getBinary()));
    }
    
    /**
     * 
     * @throws RconException
     */
    public function start() {
        if (!is_resource($this->getSocket())) {
            $this->openSocket();
        }
        
        $connections = [];
        while (!$this->isStopped()) {
            foreach ($this->getClients() as $peer => $client) {
                if ($client->getStatus() === ServerClient::STATUS_AUTHORIZATION && time() - $client->getConnTime() >= $this->getTimeout()) {
                    $this->getHandler()->handle("onTimeout", [$client]);
                    unset($connections[$peer]);
                    unset($this->clients[$peer]);
                    fclose($client->getStream());
                }
            }
            
            $r = $connections;
            $r[] = $this->getSocket();
            $w = $e = null;
            
            if (stream_select($r, $w, $e, 0)) {
                if (in_array($this->getSocket(), $r)) {
                    if ($connection = stream_socket_accept($this->getSocket(), -1, $peer)) {
                        if (count($this->getClients()) > $this->getMaxClients()) {
                            fclose($connection);
                        } else {
                            $connections[$peer] = $connection;
                            $this->clients[$peer] = new ServerClient($connection);
                            $this->getHandler()->handle("onConnection", [$this->clients[$peer]]);
                        }
                    }
                    unset($r[array_search($this->getSocket(), $r)]);
                }
 
                foreach ($r as $c) {
                    if ($this->isStopped()) {
                        break;
                    }
             
                    $peer = stream_socket_get_name($c, true);
                    $client = $this->clients[$peer];
                    
                    if (feof($c) || $client->getStatus() === ServerClient::STATUS_AUTHORIZATION_FAILED) {
                        $this->getHandler()->handle("onDisconnection", [$client]);
                        unset($connections[$peer]);
                        unset($this->clients[$peer]);
                        fclose($c);
                        continue;
                    }
                    
                    if (($packet = $this->receivePacket($c)) === null) {
                        continue;
                    }
                    
                    $this->getHandler()->handle("onReceive", [$client, $packet]);
                    if ($packet->getType() === Packet::TYPE_SERVERDATA_AUTH && $client->getStatus() === ServerClient::STATUS_AUTHORIZATION) {
                        if ($packet->getBody() === $this->getPassword()) {
                            $this->sendPacket($c, $packet->getId(), Packet::TYPE_SERVERDATA_AUTH_RESPONSE);
                            $client->setStatus(ServerClient::STATUS_AUTHORIZED);
                            $this->getHandler()->handle("onAuthorized", [$client]);
                        } else {
                            $this->sendPacket($c, -1, Packet::TYPE_SERVERDATA_AUTH_RESPONSE);
                            $client->setStatus(ServerClient::STATUS_AUTHORIZATION_FAILED);
                            $this->getHandler()->handle("onFailed", [$client, $packet->getBody()]);
                        }
                    } elseif ($packet->getType() === Packet::TYPE_SERVERDATA_EXECCOMMAND && $client->getStatus() === ServerClient::STATUS_AUTHORIZED) {
                        $this->getHandler()->handle("onCommand", [$client, $packet->getBody()]);
                    }
                }
            }
        }
    }
    
    public function stop() {
        $this->stop = true;
        $this->close();
    }
    
    /**
     * 
     * @return bool
     */
    public function isStopped(): bool {
        return $this->stop;
    }
    
    /**
     * 
     * @throws RconException
     * @return self
     */
    public function openSocket(): RconServer {
        if (!($this->socket = stream_socket_server("tcp://". $this->getHost() .":". $this->getPort(), $errno, $errstr))) {
            throw new RconException("Unable to open socket on ". $this->getHost() .":". $this->getPort() .": $errstr ($errno)");
        }
        stream_set_blocking($this->socket, true);
        return $this;
    }
    
    /**
     * Closes socket stream
     */
    public function close() {
       if (is_resource($this->getSocket())) {
           fclose($this->getSocket());
       }
    }
    
    /**
     * 
     * @return resource|null
     */
    public function getSocket() {
        return $this->socket;
    }
    
    /**
     * 
     * @param int $len
     */
    public function generatePassword(int $len = 8) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $this->password = \substr(\str_shuffle($chars), 0, $len);
    }
    
    /**
     * 
     * @return ServerClient[]
     */
    public function getClients(): array {
        return $this->clients;
    }
    
    /**
     * 
     * @param int $maxClients
     */
    public function setMaxClients(int $maxClients) {
        $this->maxClients = $maxClients;
    }
    
    /**
     * 
     * @return int
     */
    public function getMaxClients(): int {
        return $this->maxClients;
    }
    
    /**
     * 
     * @param string $password
     */
    public function setPassword(string $password) {
        $this->password = $password;
    }
    
    /**
     * 
     * @return string
     */
    public function getPassword(): string {
        return $this->password;
    }
    
    /**
     * 
     * @param string $host
     */
    public function setHost(string $host) {
        $this->host = $host;
    }
    
    /**
     * 
     * @return string
     */
    public function getHost(): string {
        return $this->host;
    }
    
    /**
     * 
     * @param int $port
     */
    public function setPort(int $port) {
        $this->port = $port;
    }
    
    /**
     * 
     * @return int
     */
    public function getPort(): int {
        return $this->port;
    }
    
    /**
     * 
     * @param int $timeout
     */
    public function setTimeout(int $timeout) {
        $this->timeout = $timeout;
    }
    
    /**
     * 
     * @return int
     */
    public function getTimeout(): int {
        return $this->timeout;
    }
    
    /**
     * 
     * @return Triggers
     */
    public function getHandler() {
        if (!($this->handler instanceof Triggers)) {
            $this->handler = new Triggers();
        }
        return $this->handler;
    }
}
