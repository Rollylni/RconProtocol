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

use function is_string;
use function is_array;
use function is_int;
use function pack;
use function unpack;
use function strlen;
use function array_keys;

use RconProtocol\Exception\PacketException;

class Packet {
    
    /** 
     * Packet Type. (Client to Server)
     * Typically, the first packet sent by the client will be a SERVERDATA_AUTH packet,
     * which is used to authenticate the connection with the server.
     * 
     * Body: the RCON password of the server (if this matches the server's password, the auth will succeed)
     * 
     * @var int
     */
    public const TYPE_SERVERDATA_AUTH = 3;
    
    /**
     * Packet Type. (Server to Client)
     * This packet is a notification of the connection's current auth status
     * 
     * ID: If authentication was successful, the ID assigned by the request. If auth failed, -1 
     * Body: Empty String (0x00)
     * 
     * @var int
     */
    public const TYPE_SERVERDATA_AUTH_RESPONSE = 2;
    
    /**
     * Packet Type. (Client to Server)
     * This packet type represents a command issued to the server by a client
     * 
     * ID: any positive integer, chosen by the client (will be mirrored back in the server's response)
     * Body: the command to be executed on the server
     * 
     * @var int
     */
    public const TYPE_SERVERDATA_EXECCOMMAND = 2;
    
    /**
     * Packet Type. (Server to Client)
     * A SERVERDATA_RESPONSE_VALUE packet is the response to a SERVERDATA_EXECCOMMAND request.
     * 
     * ID: The ID assigned by the original request
     * Body: The server's response to the original command. May be empty string (0x00) in some cases.
     * 
     * @var int
     */
    public const TYPE_SERVERDATA_RESPONSE_VALUE = 0;
    
    /**
     * The end of a Source RCON Protocol packet is marked by an empty ASCII string
     * 
     * @var string
     */
    public const EMPTY_STRING = "\x00";
    
    /**
     * The packet structure
     * 
     * @var string[]
     */
    public const PACKET_FORMAT = [
        "id",
        "type", 
        "body"
    ];
    
    /**
     * The packet binary data 
     * 
     * @var string
     */
    private $binaryData = null;
    
    /**
     * The packet id field is a 32-bit little endian integer chosen by the client for each request. 
     * It may be set to any positive integer. When the server responds to the request,
     * the response packet will have the same packet id as the original request (unless it is a failed SERVERDATA_AUTH_RESPONSE packet - see below.) 
     * It need not be unique, but if a unique packet id is assigned, it can be used to match incoming responses to their corresponding requests.
     * 
     * @var int
     */
    private $id = 0;
    
    /**
     * The packet type field is a 32-bit little endian integer, which indicates the purpose of the packet.
     * 
     * @var int
     */
    private $type = null;
    
    /**
     * The packet body field is a null-terminated string encoded in ASCII (i.e. ASCIIZ). 
     * Depending on the packet type, it may contain either the RCON password for the server, the command to be executed,
     * or the server's response to a request.
     * 
     * @var string
     */
    private $body = null;
    
    /**
     * 
     * @param int|string $id
     * @param int $type
     * @param string $body
     */
    public function __construct($id = 0, int $type = -1, string $body = self::EMPTY_STRING) {
        if (is_int($id)) {
            $this->id = $id;
        } elseif (is_string($id)) {
            $this->binaryData = $id;
        }
        $this->type = $type;
        $this->body = $body;
    }
    
    /**
     * Encode packet to binary data
     * 
     * @return self
     */
    public function encode(): Packet {
        $packet = pack("VVZ*", $this->getId(), $this->getType(), $this->getBody());
        $packet .= self::EMPTY_STRING;
        $packet = pack('V', strlen($packet)) . $packet;
        $this->setBinary($packet);
        return $this;
    }
    
    /**
     * Decode binary packet data into encapsulated
     * 
     * @param bool $size
     * @throws PacketException
     * @return self
     */
    public function decode(bool $size = true): Packet {
        if ($this->getBinary()) {
            
            if ($size) {
                $res = unpack("V1size/V1id/V1type/Z*body", $this->getBinary());
                unset($res["size"]);
            } else {
                $res = unpack("V1id/V1type/Z*body", $this->getBinary());
            }
            
            if (!is_array($res) || array_keys($res) !== self::PACKET_FORMAT) {
                throw new PacketException("Invalid binary packet data!");
            }
            
            $this->setBinary();
            list(
                "id" => $this->id,
                "type" => $this->type,
                "body" => $this->body
            ) = $res;
        }
        return $this;
    }
    
    /**
     * 
     * @param int $id
     */
    public function setId(int $id = 0) {
        $this->id = $id;
    }
    
    /**
     * 
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }
    
    /**
     * 
     * @param int $type
     */
    public function setType(int $type) {
        $this->type = $type;
    }
    
    /**
     * 
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }
    
    /**
     * 
     * @param string $body
     */
    public function setBody(string $body) {
        $this->body = $body;
    }
    
    /**
     * 
     * @return string|null
     */
    public function getBody(): string {
        return $this->body;
    }
    
    /**
     * 
     * @param string $data
     */
    public function setBinary(?string $data = null) {
        $this->binaryData = $data; 
    }
    
    /**
     * 
     * @return string|null
     */
    public function getBinary(): ?string {
        return $this->binaryData;
    }
}