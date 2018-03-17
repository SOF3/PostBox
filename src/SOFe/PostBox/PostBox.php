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

use jojoe77777\FormAPI\FormAPI;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\result\SqlSelectResult;
use function count;
use function date;
use function is_int;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

class PostBox extends PluginBase implements Listener{
	/** @var DataConnector */
	private $provider;
	/** @var FormAPI */
	private $formAPI;

	public function onEnable() : void{
		$this->saveDefaultConfig();

		$this->formAPI = $this->getServer()->getPluginManager()->getPlugin("FormAPI");

		$this->provider = libasynql::create($this, $this->getConfig()->get("database"), [
			"sqlite" => "sqlite.sql",
			"mysql" => "mysql.sql",
		]);
		$this->provider->executeGeneric("postbox.init-posts", []);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function e_onJoin(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		$this->provider->executeSelect("postbox.unread.dashboard", ["username" => $player->getName()], function(SqlSelectResult $result) use ($player){
			$rows = $result->getRows();
			if(count($rows) === 1){
				$this->showUnreads($player, $rows[0]["sender_type"], $rows[0]["sender_count"], $rows[0]["message_count"]);
			}elseif(count($rows) >= 2){
				$form = $this->formAPI->createSimpleForm();
				$messages = 0;
				$senders = 0;
				foreach($rows as $row){
					$type = $this->nameSenderType($row["sender_type"]); // TODO convert type to player-readable name
					$form->addButton(sprintf("%s (%d senders, %d messages)", $type, $row["sender_count"], $row["message_count"]));
					$messages += $row["message_count"];
					$senders += $row["sender_count"];
				}
				$form->setTitle("PostBox");
				$form->setContent("You have $messages messages from $senders senders:");
				$form->setCallable(function($p, int $choice) use ($player, $rows){
					if(is_int($choice)){
						$row = $rows[$choice];
						$type = $row["sender_type"];
						$this->showUnreads($player, $type, $row["sender_count"], $row["message_count"]);
					}
				});
				$form->sendToPlayer($player);
			}
		});
	}

	public function showUnreads(Player $player, string $type, int $senderCount, int $messageCount) : void{
		if($senderCount >= $this->getConfig()->getNested("ui.group-unreads.min-senders", 5) &&
			$messageCount / $senderCount >= $this->getConfig()->getNested("ui.group-unreads.min-ratio", 2.0)){
			// collapse mode
			$this->provider->executeSelect("postbox.unread.by-type.collapse", [
				"username" => $player->getName(),
				"type" => $type
			], function(SqlSelectResult $result) use ($player, $type): void{
				$typeName = $this->nameSenderType($type);
				$form = $this->formAPI->createSimpleForm();
				$form->setTitle("PostBox - Messages from $typeName");
				$messages = 0;
				$rows = $result->getRows();
				foreach($rows as $row){
					$form->addButton(sprintf("%d messages from %s", $row["message_count"], $row["sender_name"]));
					$messages += $row["message_count"];
				}
				$form->setContent(sprintf("%d messages from %d senders", $messages, count($rows)));
				$form->setCallable(function($p, int $choice) use ($player, $type, $rows){
					$who = $rows[$choice]["sender_name"];
					$this->provider->executeSelect("postbox.unread.by-type-name", [
						"username" => $player->getName(),
						"type" => $type,
						"sender" => $who
					], function(SqlSelectResult $result) use ($player, $who){
						$this->showMessages($player, $who, $result->getRows());
					});
				});
				$form->sendToPlayer($player);
			});
		}else{
			// expand mode
			$this->provider->executeSelect("postbox.unread.by-type.expand", [
				"username" => $player->getName(),
				"type" => $type,
			], function(SqlSelectResult $result) use ($player): void{
				$this->showMessages($player, null, $result->getRows());
			});
		}
	}

	/**
	 * @param Player      $player
	 * @param null|string $who
	 * @param array       $rows [post_id, message, send_time]
	 */
	private function showMessages(Player $player, ?string $who, array $rows){
		$form = $this->formAPI->createCustomForm();
		$form->setTitle("Messages from $who");
		$messages = []; // security check: don't let the player delete arbitrary messages!
		foreach($rows as $row){
			$form->addLabel(date("Y-m-d H:i:s", $row["send_time"]));
			$form->addLabel($row["message"]);
			$form->addToggle("Mark as read", true, "read_" . $row["post_id"]);
			$messages[$row["post_id"]] = true;
		}
		$form->setCallable(function($p, $data) use ($player, $messages){
			$readIds = [];
			foreach($data as $k => $v){
				if($v === true && strpos($k, "read_") !== false){
					$postId = substr($k, strlen("read_"));
					if(!isset($messages[$postId])){
						$player->sendMessage(TextFormat::RED . "$postId is not in the post ID list. This incident will be reported. https://xkcd.com/838/");
						$this->getLogger()->critical($player->getName() . " wants to hack $postId!");
						return;
					}
				}
				$readIds[] = (int) $v;
			}
			$this->provider->executeChange("postbox.mark-read", [
				"username" => $player->getName(),
				"ids" => $readIds
			]);
		});
		$form->sendToPlayer($player);
	}

	public function e_nameSenderType(PostBoxNameSenderTypeEvent $event) : void{
		if($event->getType() === "postbox.player"){
			$event->setName("Players");
		}
	}

	public function sendMessage(string $message, string $recipientName, string $senderName, string $senderType, int $priority, ?callable $callback = null) : void{
		$this->provider->executeInsert("postbox.send-message", [
			"recipient" => $recipientName,
			"senderType" => $senderType,
			"senderName" => $senderName,
			"priority" => $priority,
			"message" => $message,
		], $callback);
	}

	public function nameSenderType(string $type){
		$this->getServer()->getPluginManager()->callEvent($ev = new PostBoxNameSenderTypeEvent($this, $type));
		return $ev->getName() ?? $type;
	}
}
