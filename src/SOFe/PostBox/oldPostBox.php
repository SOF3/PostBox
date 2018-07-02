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

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\result\SqlSelectResult;
use SOFe\Libglocal\LanguageManager;
use SOFe\Libglocal\Libglocal;
use SOFe\libkinetic\KineticManager;
use SOFe\libkinetic\LanguageProvider;
use function count;
use function date;
use function is_int;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

class oldPostBox extends PluginBase implements Listener, LanguageProvider{
	public const TYPE_PLAYER = "postbox.player";
	public const TYPE_SERVER = "postbox.server";

	public const NEUTRAL_PRIORITY = 0;
	public const HIGH_PRIORITY = 5;
	public const LOW_PRIORITY = -5;

	/** @var DataConnector */
	private $provider;
	/** @var LanguageManager */
	private $lang;

	public function onEnable() : void{
		$this->saveDefaultConfig();

		$this->provider = libasynql::create($this, $this->getConfig()->get("database"), [
			"sqlite" => "sqlite.sql",
			"mysql" => "mysql.sql",
		]);
		$this->provider->executeGeneric(Queries::POSTBOX_INIT_POSTS);
		$this->provider->executeGeneric(Queries::POSTBOX_INIT_PLAYERS);

		$this->lang = Libglocal::init($this);
		new KineticManager($this, $this, "kinetic.xml", "kinetic.json");

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getCommandMap()->register("postbox", new MailCommand($this));
	}

	public function hasMessage(string $identifier) : bool{
		return $this->lang->hasTranslation($identifier);
	}

	public function getMessage(?Player $player, string $identifier, array $parameters) : string{
		// libkinetic translations
		if($identifier{0} === "="){
			return substr($identifier, 1);
		}

		return $this->lang->translate($player !== null ? $player->getLocale() : "en_US", "postbox.kinetic." . $identifier, $parameters);
	}

	public function e_onJoin(PlayerJoinEvent $event) : void{
		$player = $event->getPlayer();
		$this->provider->executeInsert(Queries::POSTBOX_PLAYER_TOUCH, ["name" => $player->getName()]);

		$this->provider->executeSelect(Queries::POSTBOX_UNREAD_DASHBOARD, [
			"username" => $player->getName(),
		], function(array $rows) use ($player){

		});
		$this->checkUnreads($player);
	}

	public function checkUnreads(Player $player){
		$this->provider->executeSelect(Queries::POSTBOX_UNREAD_DASHBOARD, ["username" => $player->getName()], function(SqlSelectResult $result) use ($player){
			$rows = $result->getRows();
			if(count($rows) === 1){
				$this->showTypeUnreads($player, $rows[0]["sender_type"], $rows[0]["sender_count"], $rows[0]["message_count"]);
			}elseif(count($rows) >= 2){
				$form = $this->formAPI->createSimpleForm();
				$messages = 0;
				$senders = 0;
				foreach($rows as $row){
					$form->addButton(sprintf("%s (%d senders, %d messages)",
						$this->nameSenderType($row["sender_type"], PostBoxNameSenderTypeEvent::TYPE_MODE_CATEGORY),
						$row["sender_count"], $row["message_count"]));
					$messages += $row["message_count"];
					$senders += $row["sender_count"];
				}
				$form->setTitle("PostBox");
				$form->setContent("You have $messages messages from $senders senders:");
				$form->setCallable(function($p, int $choice) use ($player, $rows){
					if(is_int($choice)){
						$row = $rows[$choice];
						$type = $row["sender_type"];
						$this->showTypeUnreads($player, $type, $row["sender_count"], $row["message_count"]);
					}
				});
				$form->sendToPlayer($player);
			}
		});
	}

	public function showTypeUnreads(Player $player, string $type, int $senderCount, int $messageCount) : void{
		if($senderCount >= $this->getConfig()->getNested("ui.group-unreads.min-senders", 5) &&
			$messageCount / $senderCount >= $this->getConfig()->getNested("ui.group-unreads.min-ratio", 2.0)){
			// collapse mode
			$this->provider->executeSelect(Queries::POSTBOX_UNREAD_BY_TYPE_COLLAPSE, [
				"username" => $player->getName(),
				"type" => $type
			], function(SqlSelectResult $result) use ($player, $type): void{
				$form = $this->formAPI->createSimpleForm();
				$form->setTitle("PostBox - Messages from " . $this->nameSenderType($type, PostBoxNameSenderTypeEvent::TYPE_MODE_FROM));
				$messages = 0;
				$rows = $result->getRows();
				foreach($rows as $row){
					$form->addButton(sprintf("%d messages from %s", $row["message_count"], $row["sender_name"]));
					$messages += $row["message_count"];
				}
				$form->setContent(sprintf("%d messages from %d senders", $messages, count($rows)));
				$form->setCallable(function($p, int $choice) use ($player, $type, $rows){
					$who = $rows[$choice]["sender_name"];
					$this->provider->executeSelect(Queries::POSTBOX_UNREAD_BY_TYPE_NAME, [
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
			$this->provider->executeSelect(Queries::POSTBOX_UNREAD_BY_TYPE_EXPANDED, [
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
	 * @param array       $rows [post_id, message, send_time, ?sender_name]
	 */
	private function showMessages(Player $player, ?string $who, array $rows){
		$form = $this->formAPI->createCustomForm();
		$form->setTitle($who !== null ? "New Messages from $who" : "New Messages");
		$messages = []; // security check: don't let the player delete arbitrary messages!
		foreach($rows as $row){
			$form->addLabel(($who !== null ? "" : ($row["sender_name"] . ", ")) . date("Y-m-d H:i:s", $row["send_time"]));
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
					$readIds[] = (int) $postId;
				}
			}
			if(count($readIds) > 0){
				$this->provider->executeChange(Queries::POSTBOX_MARK_READ, [
					"username" => $player->getName(),
					"ids" => $readIds
				]);
			}
		});
		$form->sendToPlayer($player);
	}

	public function e_nameSenderType(PostBoxNameSenderTypeEvent $event) : void{
		switch($event->getType()){
			case self::TYPE_PLAYER:
				switch($event->getMode()){
					case PostBoxNameSenderTypeEvent::TYPE_MODE_CATEGORY:
						$event->setName("Personal");
						break;
					case PostBoxNameSenderTypeEvent::TYPE_MODE_FROM:
						$event->setName("Players");
						break;
				}
				break;
			case self::TYPE_SERVER:
				switch($event->getMode()){
					case PostBoxNameSenderTypeEvent::TYPE_MODE_CATEGORY:
						$event->setName("Official");
						break;
					case PostBoxNameSenderTypeEvent::TYPE_MODE_FROM:
						$event->setName($this->getServer()->getMotd());
						break;
				}
				break;
		}
	}

	public function sendMessage(string $message, string $recipientName, string $senderName, string $senderType, int $priority, ?callable $callback = null) : void{
		$this->provider->executeInsert(Queries::POSTBOX_SEND_MESSAGE, [
			"recipient" => $recipientName,
			"senderType" => $senderType,
			"senderName" => $senderName,
			"priority" => $priority,
			"message" => $message,
		], $callback);
	}

	public function nameSenderType(string $type, string $mode){
		$this->getServer()->getPluginManager()->callEvent($ev = new PostBoxNameSenderTypeEvent($this, $type, $mode));
		return $ev->getName() ?? $type;
	}

	public function getProvider() : DataConnector{
		return $this->provider;
	}
}
