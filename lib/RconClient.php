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

use function stream_socket_client;
use function is_resource;
use function fclose;

use RconProtocol\Exception\RconException;

class RconClient {
    
    public const ID_AUTHORIZE = 1;
    public const ID_COMMAND = 2;
    
    /**
     *
     * @var string $host Host of the server to connect to
     * @var integer $port Port of the server to connect to
     */
    private $host = "127.0.0.1", $port = 27015;
    
    /**
     * 
     * @var int
     */
    private $timeout = 10;
    
    /**
     * 
     * @var bool
     */
    private $authorized = false;
    
    /**
     * 
     * @var resource
     */
    protected $socket = null;
    
    /**
     * 
     * @param string $host
     * @param int $port
     * @param int $timeout
     */
    public function __construct(string $host = "127.0.0.1", int $port = 27015, int $timeout = 10) {
        $this->timeout = $timeout;
        $this->host = $host;
        $this->port = $port;
    }
    
    /**
     * 
     * @param string $password
     * @return bool
     */
    public function authorize(string $password): bool {
        $this->authorized = true;
        $response = $this->send(self::ID_AUTHORIZE, Packet::TYPE_SERVERDATA_AUTH, $password);
        if ($response instanceof Packet) {
            if ($response->getType() === Packet::TYPE_SERVERDATA_AUTH_RESPONSE && $response->getId() === self::ID_AUTHORIZE) {
                $this->authorized = true;
            }
        }
        return $this->isAuthorized();
    }
    
    /**
     * 
     * @param string $command
     * @return string|null
     */
    public function sendCommand(string $command): ?string {
        $response = $this->send(self::ID_COMMAND, Packet::TYPE_SERVERDATA_EXECCOMMAND, $command);
        if ($response instanceof Packet) {
            if ($response->getType() === Packet::TYPE_SERVERDATA_RESPONSE_VALUE && $response->getId() === self::ID_COMMAND) {
                return $response->getBody();
            }
        }
        return null;
    }
    
    /**
     * 
     * @param int $id
     * @param int $type
     * @param string $body
     * @return Packet|null
     */
    public function send(int $id, int $type, string $body): ?Packet {
        $this->checkConnection();
        RconServer::sendPacket($this->getSocket(), $id, $type, $body);      
        return RconServer::receivePacket($this->getSocket());
    }
    
    /**
     * Connect to server
     * 
     * @throws RconException
     * @return self
     */
    public function connect(): RconClient {
        $this->socket = stream_socket_client("tcp://". $this->getHost() .":". $this->getPort(), $errno, $errstr, $this->getTimeout());
        if (!$this->socket) {
            throw new RconException("failed to connect to ". $this->getHost() .":". $this->getPort() .": $errstr ($errno)");
        }
        return $this;
    }
    
    /**
     * create a new connection if not one created
     */
    public function checkConnection() {
        if (!is_resource($this->socket)) {
            $this->connect();
        }
    }
    
    /**
     * Disconnect from server
     */
    public function disconnect() {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }
    
    /**
     * 
     * @return bool
     */
    public function isAuthorized(): bool {
        return $this->authorized;
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
}