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

namespace svile\skywars;

use pocketmine\player\Player;
use pocketmine\player\GameMode;

use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\world\Position;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\block\tile\Chest;
use pocketmine\item\ItemFactory;


final class SWarena
{
    /** @var int */
    public $GAME_STATE = 0;//0 -> GAME_COUNTDOWN | 1 -> GAME_RUNNING | 2 -> no-pvp
    /** @var SWmain */
    private $pg;

    /** @var string */
    private $SWname;
    /** @var int */
    private $slot;
    /** @var string */
    private $world;
    /** @var int */
    private $countdown = 60;//Seconds to wait before the game starts
    /** @var int */
    private $maxtime = 300;//Max seconds after the countdown, if go over this, the game will finish
    /** @var int */
    public $void = 0;//This is used to check "fake void" to avoid fall (stunck in air) bug
    /** @var array */
    private $spawns = [];//Players spawns

    /** @var int */
    private $time = 0;//Seconds from the last reload | GAME_STATE
    /** @var array */
    private $players = [];
    /** @var array */
    private $spectators = [];
    private $saveCage = [];


    /**
     * @param SWmain $plugin
     * @param string $SWname
     * @param int $slot
     * @param string $world
     * @param int $countdown
     * @param int $maxtime
     * @param int $void
     */
    public function __construct(SWmain $plugin, $SWname = 'sw', $slot = 0, $world = 'world', $countdown = 60, $maxtime = 300, $void = 0)
    {
        $this->pg = $plugin;
        $this->SWname = $SWname;
        $this->slot = ($slot + 0);
        $this->world = $world;
        $this->countdown = ($countdown + 0);
        $this->maxtime = ($maxtime + 0);
        $this->void = $void;
        if (!$this->reload()) {
            $this->pg->getLogger()->info(TextFormat::RED . 'An error occured while reloading the arena: ' . TextFormat::WHITE . $this->SWname);
            $this->pg->getServer()->getPluginManager()->disablePlugin($this->pg);
        }
    }


    /**
     * @return bool
     */
    private function reload()
    {
        //Map reset
        if (!is_file($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar') && !is_file($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz'))
            return false;
        if ($this->pg->getServer()->getWorldManager()->isWorldLoaded($this->world)) {
            if ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getAutoSave() || $this->pg->configs['world.reset.from.tar']) {
                $this->pg->getServer()->getWorldManager()->unloadWorld($this->pg->getServer()->getWorldManager()->getWorldByName($this->world));
                if (is_file($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar'))
                    $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar');
                elseif (is_file($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz'))
                    $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz');
                else
                    return false;//WILL NEVER REACH THIS
                $tar->extractTo($this->pg->getServer()->getDataPath() . 'worlds/' . $this->world, null, true);
                unset($tar);
                $this->pg->getServer()->getWorldManager()->loadWorld($this->world);
            }
            $this->pg->getServer()->getWorldManager()->unloadWorld($this->pg->getServer()->getWorldManager()->getWorldByName($this->world));
            $this->pg->getServer()->getWorldManager()->loadWorld($this->world);
            $this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->setAutoSave(false);
        } else {
            if (is_file($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar'))
                $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar');
            elseif (is_file($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz'))
                $tar = new \PharData($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/' . $this->world . '.tar.gz');
            else
                return false;//WILL NEVER REACH THIS
            $tar->extractTo($this->pg->getServer()->getDataPath() . 'worlds/' . $this->world, null, true);
            unset($tar);
            $this->pg->getServer()->getWorldManager()->loadWorld($this->world);
        }

        $config = new Config($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/settings.yml', CONFIG::YAML, [//TODO: put descriptions
            'name' => $this->SWname,
            'slot' => $this->slot,
            'world' => $this->world,
            'countdown' => $this->countdown,
            'maxGameTime' => $this->maxtime,
            'void_Y' => $this->void,
            'spawns' => []
        ]);
        $this->SWname = $config->get('name');
        $this->slot = ($config->get('slot') + 0);
        $this->world = $config->get('world');
        $this->countdown = ($config->get('countdown') + 0);
        $this->maxtime = ($config->get('maxGameTime') + 0);
        $this->spawns = $config->get('spawns');
        $this->void = ($config->get('void_Y') + 0);
        $this->players = [];
        $this->spectators = [];
        $this->time = 0;
        $this->GAME_STATE = 0;

        //Reset Sign
        $this->pg->refreshSigns(false, $this->SWname, 0, $this->slot);
        return true;
    }


    /**
     * @return string
     */
    public function getState()
    {
        $state = TextFormat::WHITE . 'Tap to join';
        switch ($this->GAME_STATE) {
            case 1:
            case 2:
                $state = TextFormat::RED . TextFormat::BOLD . 'Running';
                break;
            case 0:
                if (count($this->players) >= $this->slot)
                    $state = TextFormat::RED . TextFormat::BOLD . 'Running';
                break;
        }
        return $state;
    }


    /**
     * @param bool $players
     * @return int
     */
    public function getSlot($players = false)
    {
        if ($players)
            return count($this->players);
        return $this->slot;
    }


    /**
     * @param bool $spawn
     * @param string $playerName
     * @return string|array
     */
    public function getWorld($spawn = false, $playerName = '')
    {
        if ($spawn && array_key_exists($playerName, $this->players))
            return $this->players[$playerName];
        else
            return $this->world;
    }


    /**
     * @param string $playerName
     * @return int
     */
    public function inArena($playerName = '')
    {
        if (array_key_exists($playerName, $this->players))
            return 1;
        if (in_array($playerName, $this->spectators))
            return 2;
        return 0;
    }


    /**
     * @param Player $player
     * @param int $slot
     * @return bool
     */
    public function setSpawn(Player $player, $slot = 1)
    {
        if ($slot > $this->slot) {
            $player->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'This arena have only got ' . TextFormat::WHITE . $this->slot . TextFormat::RED . ' slots');
            return false;
        }
        $config = new Config($this->pg->getDataFolder() . 'arenas/' . $this->SWname . '/settings.yml', CONFIG::YAML);

        if (empty($config->get('spawns', []))) {
            $keys = [];
            for ($i = $this->slot; $i >= 1; $i--) {
                $keys[] = $i;
            }
            unset($i);
            $config->set('spawns', array_fill_keys(array_reverse($keys), [
                'x' => 'n.a',
                'y' => 'n.a',
                'z' => 'n.a',
                'yaw' => 'n.a',
                'pitch' => 'n.a'
            ]));
            unset($keys);
        }
        $s = $config->get('spawns');
        $s[$slot] = [
            'x' => floor($player->getPosition()->x),
            'y' => floor($player->getPosition()->y),
            'z' => floor($player->getPosition()->z),
            'yaw' => $player->getLocation()->yaw,
            'pitch' => $player->getLocation()->pitch
        ];
        $config->set('spawns', $s);
		$config->save();
        $this->spawns = $s;
        unset($s);
        // if (!$config->save()/*  || count($this->spawns) != $this->slot */) {
            // $player->sendMessage(TextFormat::AQUA . '→' . TextFormat::RED . 'An error occured setting the spawn, pls contact the developer');
            // return false;
        // } else
            // return true;
		
		return true;
    }


    /**
     * @return bool
     */
    public function checkSpawns()
    {
        if (empty($this->spawns))
            return false;
        foreach ($this->spawns as $key => $val) {
            if (!is_array($val) || count($val) != 5 || $this->slot != count($this->spawns) || in_array('n.a', $val, true))
                return false;
        }
        return true;
    }


    /** VOID */
    private function refillChests()
    {
        $contents = $this->pg->getChestContents();
        foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getLoadedChunks() as $chunk) {
			foreach ($chunk->getTiles() as $tile){
				if ($tile instanceof Chest) {
					//CLEARS CHESTS
					for ($i = 0; $i < $tile->getInventory()->getSize(); $i++) {
						$tile->getInventory()->setItem($i, ItemFactory::getInstance()->get(0));
					}
					//SET CONTENTS
					if (empty($contents))
						$contents = $this->pg->getChestContents();
					foreach (array_shift($contents) as $key => $val) {
						$tile->getInventory()->setItem($key, ItemFactory::getInstance()->get($val[0], 0, $val[1]));
					}
				}
			}
        }
		unset($contents, $tile);
    }


    /** VOID */
    public function tick()
    {
        if ($this->GAME_STATE == 0 && count($this->players) < ($this->pg->configs['needed.players.to.run.countdown'] + 0))
            return;
        $this->time++;

        //START and STOP
        if ($this->GAME_STATE == 0 && $this->pg->configs['start.when.full'] && $this->slot <= count($this->players)) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE > 0 && 2 > count($this->players)) {
            $this->stop();
            return;
        }
        if ($this->GAME_STATE == 0 && $this->time >= $this->countdown) {
            $this->start();
            return;
        }
        if ($this->GAME_STATE > 0 && $this->time >= $this->maxtime) {
            $this->stop();
            return;
        }

        //Chest refill
        if ($this->GAME_STATE > 0 && $this->pg->configs['chest.refill'] && ($this->time % $this->pg->configs['chest.refill.rate']) == 0) {
            $this->refillChests();
            foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getPlayers() as $p) {
                $p->sendMessage($this->pg->lang['game.chest.refill']);
            }
            return;
        }

        //PvP - updates
        if ($this->GAME_STATE == 2) {
            if ($this->time <= $this->pg->configs['no.pvp.countdown'])
                foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getPlayers() as $p)
                    $p->sendPopup(str_replace('{COUNT}', $this->pg->configs['no.pvp.countdown'] - $this->time + 1, $this->pg->lang['no.pvp.countdown']));
            else
                $this->GAME_STATE = 1;
            return;
        }

        //Chat and Popup messanges
        if ($this->GAME_STATE == 0 && $this->time % 30 == 0) {
            foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getPlayers() as $p) {
                $p->sendMessage(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['chat.countdown']));
            }
        }
        if ($this->GAME_STATE == 0) {
            foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getPlayers() as $p) {
                $p->sendPopup(str_replace('{N}', date('i:s', ($this->countdown - $this->time)), $this->pg->lang['popup.countdown']));
                if (($this->countdown - $this->time) <= 10)
                    $p->getWorld()->addSound($p->getPosition()->asVector3(), (new \pocketmine\world\sound\ClickSound(2)));
            }
        }
    }


    /**
     * @param Player $player
     * @param bool $msg
     * @return bool
     */
    public function join(Player $player, $msg = true)
    {
        if ($this->GAME_STATE > 0) {
            if ($msg)
                $player->sendMessage($this->pg->lang['sign.game.running']);
            return false;
        }
        if (count($this->players) >= $this->slot || empty($this->spawns)) {
            if ($msg)
                $player->sendMessage($this->pg->lang['sign.game.full']);
            return false;
        }
        //Sound
        $player->getWorld()->addSound($player->getPosition()->asVector3(), (new \pocketmine\world\sound\EndermanTeleportSound()));

        //Removes player things
        $player->setGamemode(GameMode::SURVIVAL());
        if ($this->pg->configs['clear.inventory.on.arena.join'])
            $player->getInventory()->clearAll();
        if ($this->pg->configs['clear.effects.on.arena.join'])
            $player->getEffects()->clear();
        $player->setMaxHealth($this->pg->configs['join.max.health']);
        $player->setMaxHealth($player->getMaxHealth());
        if ($player->getAttributeMap() != null) {//just to be really sure
            if (($health = $this->pg->configs['join.health']) > $player->getMaxHealth() || $health < 1)
                $health = $player->getMaxHealth();
            $player->setHealth($health);
            // $player->setFood(20);
            $player->getHungerManager()->setFood(20);
        }
        $this->pg->getServer()->getWorldManager()->loadWorld($this->world);
        $level = $this->pg->getServer()->getWorldManager()->getWorldByName($this->world);
        $tmp = array_shift($this->spawns);
        $player->teleport(new Position($tmp['x'] + 0.5, $tmp['y'], $tmp['z'] + 0.5, $level), $tmp['yaw'], $tmp['pitch']);
        $this->players[$player->getName()] = $tmp;
		
		$this->build($player->getPosition(), BlockLegacyIds::GLASS);
		$this->saveCage[$player->getName()] = $player->getPosition();
        foreach ($level->getPlayers() as $p) {
            $p->sendMessage(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $player->getName(), $this->pg->lang['game.join'])));
        }
        $this->pg->refreshSigns(false, $this->SWname, $this->getSlot(true), $this->slot, $this->getState());
        return true;
    }
	
	public function build(Position $pos, int $id, int $meta = 0){
		
		$world = $pos->getWorld();
		
		foreach ([
			$pos->asVector3()->add(0, -1, 0),
			$pos->asVector3()->add(1, 0, 0),
			$pos->asVector3()->add(-1, 0, 0),
			$pos->asVector3()->add(0, 0, 1),
			$pos->asVector3()->add(0, 0, -1),
			$pos->asVector3()->add(-1, 1, 0),
			$pos->asVector3()->add(1, 1, 0),
			$pos->asVector3()->add(0, 1, 1),
			$pos->asVector3()->add(0, 1, -1),
			$pos->asVector3()->add(0, 2, 0)
			] as $pos){
			$world->setBlock($pos, BlockFactory::getInstance()->get($id, $meta));
		}
	}


    /**
     * @param string $playerName
     * @param bool $left
     * @param bool $spectate
     * @return bool
     */
    private function quit($playerName, $left = false, $spectate = false)
    {
        if (in_array($playerName, $this->spectators)) {
            unset($this->spectators[array_search($playerName, $this->spectators)]);
            if (($s = $this->pg->getServer()->getPlayerByPrefix($playerName)) instanceof Player){
                $s->setGamemode(GameMode::SURVIVAL());
            }
            // foreach ($this->players as $name => $spawn) {
            //     if ((($p = $this->pg->getServer()->getPlayerByPrefix($name)) instanceof Player) && (($s = $this->pg->getServer()->getPlayerByPrefix($playerName)) instanceof Player))
            //         $p->showPlayer($s);
            // }
            return true;
        }
        if (!array_key_exists($playerName, $this->players))
            return false;
        if ($this->GAME_STATE == 0)
            $this->spawns[] = $this->players[$playerName];
        unset($this->players[$playerName]);
        $this->pg->refreshSigns(false, $this->SWname, $this->getSlot(true), $this->slot, $this->getState());
        if ($left)
            foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getPlayers() as $p)
                $p->sendMessage(str_replace('{COUNT}', '[' . $this->getSlot(true) . '/' . $this->slot . ']', str_replace('{PLAYER}', $playerName, $this->pg->lang['game.left'])));
        if ($spectate && !in_array($playerName, $this->spectators))
            $this->spectators[] = $playerName;
        // foreach ($this->spectators as $sp) {
        //     if ((($p = $this->pg->getServer()->getPlayerByPrefix($playerName)) instanceof Player) && (($s = $this->pg->getServer()->getPlayerByPrefix($sp)) instanceof Player))
        //         $p->showPlayer($s);
        // }
        return true;
    }


    /**
     * @param Player $p
     * @param bool $left
     * @param bool $spectate
     * @return bool
     */
    public function closePlayer(Player $p, $left = false, $spectate = false)
    {
        if ($this->quit($p->getName(), $left, $spectate)) {
			if(isset($this->saveCage[$p->getName()])){
				$pos = $this->saveCage[$p->getName()];
				$this->build($pos, 0);
				unset($this->saveCage[$p->getName()]);
			}
            // $p->gamemode = 4;//Just to make sure setGamemode() won't return false if the gm is the same
            $p->setGamemode(GameMode::SPECTATOR());
            $p->setGamemode($p->getServer()->getGamemode());
            $p->getArmorInventory()->clearAll();
            $p->getInventory()->clearAll();
            // $p->removeAllEffects();
            $p->getEffects()->clear();
            if ($p->isAlive()) {
                $p->setSprinting(false);
                $p->setSneaking(false);
                $p->extinguish();
                $p->setMaxHealth(20);
                $p->setMaxHealth($p->getMaxHealth());
                if ($p->getAttributeMap() != null) {//just to be really sure
                    $p->setHealth($p->getMaxHealth());
                    $p->getHungerManager()->setFood(20);
                }
            }
            if (!$spectate) {
                //TODO: Invisibility issues for death players
                $p->teleport($p->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
            } elseif ($this->GAME_STATE > 0 && 1 < count($this->players)) {
                $p->setGamemode(GameMode::SPECTATOR());
                // foreach ($this->players as $dname => $spawn) {
                //     if (($d = $this->pg->getServer()->getPlayerByPrefix($dname)) instanceof Player)
                //         $d->hidePlayer($p);
                // }
                $idmeta = explode(':', $this->pg->configs['spectator.quit.item']);
                $p->getInventory()->setHeldItemIndex(0);
                $p->getInventory()->setItemInHand(ItemFactory::getInstance()->get((int)$idmeta[0], (int)$idmeta[1], 1));
                $p->getInventory()->setHeldItemIndex(1);
                $p->sendMessage($this->pg->lang['death.spectator']);
            }
            return true;
        }
        return false;
    }


    /** VOID */
    private function start()
    {
        if ($this->pg->configs['chest.refill'])
            $this->refillChests();
        foreach ($this->players as $name => $spawn) {
            if (($p = $this->pg->getServer()->getPlayerByPrefix($name)) instanceof Player) {
                $p->setMaxHealth($this->pg->configs['join.max.health']);
                $p->setMaxHealth($p->getMaxHealth());
                if ($p->getAttributeMap() != null) {//just to be really sure
                    if (($health = $this->pg->configs['join.health']) > $p->getMaxHealth() || $health < 1)
                        $health = $p->getMaxHealth();
                    $p->setHealth($health);
                    $p->getHungerManager()->setFood(20);
                }
                $p->sendMessage($this->pg->lang['game.start']);
				if(isset($this->saveCage[$p->getName()])){
					$pos = $this->saveCage[$p->getName()];
					$this->build($pos, 0);
					unset($this->saveCage[$p->getName()]);
				}
            }
        }
        $this->time = 0;
        $this->GAME_STATE = 2;
        $this->pg->refreshSigns(false, $this->SWname, $this->getSlot(true), $this->slot, $this->getState());
    }


    /**
     * @param bool $force
     * @return bool
     */
    public function stop($force = false)
    {
        $this->pg->getServer()->getWorldManager()->loadWorld($this->world);
        //CLOSE SPECTATORS
        foreach ($this->spectators as $playerName) {
            if (($s = $this->pg->getServer()->getPlayerByPrefix($playerName)) instanceof Player)
                $this->closePlayer($s);
        }
        //CLOSE PLAYERS
        foreach ($this->players as $name => $spawn) {
            if (($p = $this->pg->getServer()->getPlayerByPrefix($name)) instanceof Player) {
                $this->closePlayer($p);
                if (!$force) {
                    //Broadcast winner
                    foreach ($this->pg->getServer()->getWorldManager()->getDefaultWorld()->getPlayers() as $pl) {
                        $pl->sendMessage(str_replace('{SWNAME}', $this->SWname, str_replace('{PLAYER}', $p->getName(), $this->pg->lang['server.broadcast.winner'])));
                    }
                    //Economy reward
                    if ($this->pg->configs['reward.winning.players'] && is_numeric($this->pg->configs['reward.value']) && is_int(($this->pg->configs['reward.value'] + 0)) && $this->pg->economy instanceof \svile\skywars\utils\SWeconomy && $this->pg->economy->getApiVersion() != 0) {
                        $this->pg->economy->addMoney($p, (int)$this->pg->configs['reward.value']);
                        $p->sendMessage(str_replace('{MONEY}', $this->pg->economy->getMoney($p), str_replace('{VALUE}', $this->pg->configs['reward.value'], $this->pg->lang['winner.reward.msg'])));
                    }
                    //Reward command
                    $command = trim($this->pg->configs['reward.command']);
                    if (strlen($command) > 1 && $command[0] == '/') {
                        $this->pg->getServer()->dispatchCommand(new \pocketmine\console\ConsoleCommandSender($this->pg->getServer(), $this->pg->getServer()->getLanguage()), str_replace('{PLAYER}', $p->getName(), substr($command, 1)));
                    }
                }
            }
        }
        //Other players
        foreach ($this->pg->getServer()->getWorldManager()->getWorldByName($this->world)->getPlayers() as $p)
            $p->teleport($p->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
        $this->reload();
        return true;
    }
}