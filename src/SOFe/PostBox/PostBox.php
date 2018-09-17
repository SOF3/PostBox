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

use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use ReflectionClass;
use ReflectionMethod;
use SOFe\Libkinetic\KineticAdapter;
use SOFe\Libkinetic\KineticAdapterBase;
use SOFe\Libkinetic\KineticManager;
use SOFe\PostBox\Lang\Translation;
use spoondetector\SpoonDetector;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_file;
use function method_exists;

class PostBox extends PluginBase implements KineticAdapter, Listener{
	use KineticAdapterBase;

	/** @var DataConnector */
	protected $provider;
	/** @var Translation[] */
	protected $translations = [];
	/** @var KineticManager */
	protected $kinetic;

	public function onEnable() : void{
		$this->saveDefaultConfig();

		SpoonDetector::printSpoon($this);
		$this->provider = libasynql::create($this, $this->getConfig()->get("database"), [
			"sqlite" => "sqlite.sql",
			'mysql" => "mysql.sql",'
		]);
		$this->initTranslations();
		$this->kinetic = new KineticManager($this, $this);

		$this->provider->executeGeneric(Queries::POSTBOX_INIT_POSTS);
		$this->provider->executeGeneric(Queries::POSTBOX_INIT_PLAYERS);
		$this->provider->waitAll();

		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->initPlayer($player);
		}

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function initPlayer(Player $player) : void{
		$this->kinetic->execute(KineticIds::MESSAGES_BY_TYPE, $player);
	}

	public function e_onJoin(PlayerJoinEvent $event) : void{
		$this->initPlayer($event->getPlayer());
	}

	public function hasMessage(string $identifier) : bool{
		return method_exists($this->translations[Translation::DEFAULT], $identifier);
	}

	public function getMessage(?CommandSender $sender, string $identifier, array $parameters) : string{
		$lang = $sender instanceof Player ? $sender->getLocale() : Translation::DEFAULT;
		return ($this->translations[$lang] ?? Translation::DEFAULT)->{$identifier}($parameters);
	}

	protected function initTranslations() : void{
		if(!is_dir($this->getDataFolder() . "lang")){
			mkdir($this->getDataFolder() . "lang");
		}
		foreach(Translation::VARIANTS as $lang){
			$file = $this->getDataFolder() . "lang/{$lang}.php";
			if(!is_file($file)){
				$m = [];
				foreach((new ReflectionClass(Translation::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method){
					$a = [];
					foreach($method->getParameters() as $parameter){
						$format = "";
						if($parameter->getType() !== null){
							if($parameter->getType()->allowsNull()){
								$format .= "?";
							}
							$format .= $parameter->getType()->getName();
						}
						$format .= ' $';
						$format .= $parameter->getName();
						$a[] = $format;
					}
					$args = implode(", ", $a);
					$m[] = <<<EOM
//  {$method->getDocComment()}
//  function {$method->getName()}($args){
//    return "";
//  }
EOM;

				}
				$methods = implode("\n", $m);
				file_put_contents($file, <<<EOF
<?php

namespace SOFe\Libkinetic\Lang\User;

class $lang extends \SOFe\Libkinetic\Lang\$lang{
$methods
}

EOF
				);
			}
			require_once $file;
		}
	}
}
