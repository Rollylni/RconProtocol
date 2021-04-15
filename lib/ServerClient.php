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
use function time;

class ServerClient {
    
    public const STATUS_AUTHORIZED = 1;
    public const STATUS_AUTHORIZATION = 0;
    public const STATUS_AUTHORIZATION_FAILED = -1;
    
    /**
     * 
     * @var resource
     */
    private $stream = null;
    
    /**
     * 
     * @var int
     */
    private $status = self::STATUS_AUTHORIZATION;
    
    /**
     * Connected time
     * 
     * @var int
     */
    private $connTime = 0;
    
    /**
     * 
     * @param resource $stream
     * @param int $connTime
     */
    public function __construct($stream, int $connTime = null) {
        $this->stream = $stream;
        $this->connTime = $connTime ?? time();
    }
    
    /**
     * 
     * @internal
     * @param int $status
     */
    public function setStatus(int $status) {
        $this->status = $status;
    }
    
    /**
     * 
     * @return int
     */
    public function getStatus(): int {
        return $this->status;
    }
    
    /**
     * 
     * @return int
     */
    public function getConnTime(): int {
        return $this->connTime;
    }


    /**
     * 
     * @return resource|null
     */
    public function getStream() {
        return $this->stream;
    }
    
    /**
     * 
     * @param bool $wantPeer
     * @return string
     */
    public function getPeerName(bool $wantPeer = true): string {
        return stream_socket_get_name($this->getStream(), $wantPeer);
    }
}