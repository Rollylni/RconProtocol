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
namespace RconProtocol\Exception;

class PacketException extends RconException {}