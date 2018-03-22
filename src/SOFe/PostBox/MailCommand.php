<?php

/*
 * PostBox
 *
 * Copyright (C) 2018 SOFe
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace SOFe\PostBox;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use poggit\libasynql\result\SqlSelectResult;
use function array_shift;
use function implode;
use function time;

class MailCommand extends Command implements PluginIdentifiableCommand{
	/** @var PostBox */
	private $plugin;
	private $senderType = PostBox::TYPE_PLAYER;
	private $priority = PostBox::NEUTRAL_PRIORITY;

	public function __construct(PostBox $plugin){
		parent::__construct("mail", "Send a personal mail to another player", "/mail <target full name> <message ...>");
		$this->plugin = $plugin;
		$this->setPermission("postbox.mail.personal");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!isset($args[1])){
			$sender->sendMessage(TextFormat::RED . "Usage: " . $this->getUsage());
			return false;
		}
		$target = array_shift($args);
		$message = implode(" ", $args);
		$this->plugin->getProvider()->executeSelect(Queries::POSTBOX_PLAYER_FIND_BY_NAME, ["name" => $target], function(SqlSelectResult $result) use ($target, $message, $sender){
			if(empty($result->getRows())){
				$sender->sendMessage(TextFormat::RED . "The player called '$target' has never been on this server.");
			}else{
				$days = (int) ((time() - $result->getRows()[0]["last_online"]) / 86400);
				if($days > (float) $this->plugin->getConfig()->getNested("ui.mail.warn-inactive")){
					$sender->sendMessage(TextFormat::YELLOW . "[PostBox] Warning: '$target has not been online for $days days!");
				}
			}
			$this->plugin->sendMessage($message, $target, $sender->getName(), $this->senderType, $this->priority, function() use ($sender, $target, $message){
				if($sender instanceof Player && !$sender->isOnline()){
					return;
				}
				$sender->sendMessage("[{$sender->getName()} -> {$target}] $message");
			});
		});
		return true;
	}

	public function getPlugin() : Plugin{
		return $this->plugin;
	}

	public function testPermissionSilent(CommandSender $target) : bool{
		return !($target instanceof ConsoleCommandSender) && parent::testPermissionSilent($target);
	}
}
