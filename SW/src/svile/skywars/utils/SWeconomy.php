<?php

/*
 *                _   _
 *  ___  __   __ (_) | |   ___
 * / __| \ \ / / | | | |  / _ \
 * \__ \  \ / /  | | | | |  __/
 * |___/   \_/   |_| |_|  \___|
 *
 * SkyWars plugin for PocketMine-MP & forks
 *
 * @Authors: svile, Laith98Dev
 * @Kik: _svile_
 * @Telegram_Group: https://telegram.me/svile
 * @E-mail: thesville@gmail.com
 * @Github: https://github.com/svilex/SkyWars-PocketMine
 * @Github: https://github.com/Laith98Dev/SkyWars-svile
 *
 * Copyright (C) 2016 svile
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * DONORS LIST :
 * - Ahmet
 * - Jinsong Liu
 * - no one
 *
 */

namespace svile\skywars\utils;


use svile\skywars\SWmain;
use pocketmine\plugin\Plugin;
use pocketmine\player\Player;


class SWeconomy
{
    const EconomyAPI = 1;
    const PocketMoney = 2;
    const MassiveEconomy = 3;

    /** @var int */
    private $ver = 0;
    /** @var null|\pocketmine\plugin\Plugin */
    private $api = null;


    public function __construct(
        private SWmain $pg
    ){
        $api = $this->pg->getServer()->getPluginManager()->getPlugin('EconomyAPI');
        if ($api !== null && $api->getDescription()->getVersion() == '5.7.3-PM4') {// lasted version
            $this->ver = self::EconomyAPI;
            $this->api = $api;
            return;
        }
        // also should add BedrockEconomy
        $api = $this->pg->getServer()->getPluginManager()->getPlugin('PocketMoney');// who still use this?
        if ($api !== null && $api->getDescription()->getVersion() == '4.0.1') {
            $this->ver = self::PocketMoney;
            $this->api = $api;
            return;
        }
        $api = $this->pg->getServer()->getPluginManager()->getPlugin('MassiveEconomy');// who still use this?
        if ($api !== null && $api->getDescription()->getVersion() == '1.0 R3') {
            $this->ver = self::MassiveEconomy;
            $this->api = $api;
            return;
        }
    }


    /**
     * @return bool|\pocketmine\plugin\Plugin
     */
    public function getApi()
    {
        return $this->api;
    }


    /**
     * @param bool $string
     * @return int|string
     */
    public function getApiVersion($string = false)
    {
        $select = 0;
        switch ($this->ver) {
            case 1:
                if ($string){
                    $select = 'EconomyAPI';
                } else {
                    $select = self::EconomyAPI;
                }
                break;
            case 2:
                if ($string){
                    $select = 'PocketMoney';
                } else {
                    $select = self::PocketMoney;
                }
                break;
            case 3:
                if ($string){
                    $select = 'MassiveEconomy';
                } else {
                    $select = self::MassiveEconomy;
                }
                break;
            default:
                if ($string){
                    $select = 'Not Found';
                } else {
                    $select = 0;
                }
                break;
        }
        return $select;
    }


    /**
     * @param Player $player
     * @param int $amount
     * @return bool
     */
    public function addMoney(Player $player, $amount = 0)
    {
        switch ($this->ver) {
            case 1:
                if ($this->api !== null && $this->api->addMoney($player, $amount, true)){
                    return true;
                }
                break;
            case 2:
                if ($this->api !== null && $this->api->grantMoney($player->getName(), $amount)){
                    return true;
                }
                break;
            case 3:
                if ($this->api !== null && $this->api->payPlayer($player->getName(), $amount)){
                    return true;
                }
                break;
        }

        return false;
    }


    /**
     * @param Player $player
     * @param int $amount
     * @return bool
     */
    public function takeMoney(Player $player, $amount = 0)
    {
        switch ($this->ver) {
            case 1:
                if ($this->api !== null && $this->api->reduceMoney($player, $amount, true))
                    return true;
                break;
            case 2:
                if ($this->api !== null && $this->api->grantMoney($player->getName(), -$amount))
                    return true;
                break;
            case 3:
                if ($this->api !== null && $this->api->takeMoney($player, $amount))
                    return true;
                break;
        }
        return false;
    }


    /**
     * @param Player $player
     * @return bool|int
     */
    public function getMoney(Player $player)
    {
        if($this->api == null)
            return;
        switch ($this->ver) {
            case 1:
                $money = $this->api->myMoney($player);
                if ($money != false)
                    return (int)$money;
                break;
            case 2:
            case 3:
                $money = $this->api->getMoney($player->getName());
                if ($money != false)
                    return (int)$money;
                break;
        }
        return false;
    }
}