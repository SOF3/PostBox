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

use pocketmine\plugin\PluginBase;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use SOFe\Libglocal\LangManager;
use SOFe\Libglocal\Libglocal;
use SOFe\Libkinetic\KineticAdapter;
use SOFe\Libkinetic\KineticAdapterBase;
use SOFe\Libkinetic\KineticManager;
use spoondetector\SpoonDetector;

class PostBox extends PluginBase implements KineticAdapter{
	use KineticAdapterBase;

	/** @var DataConnector */
	protected $provider;
	/** @var LangManager */
	protected $lang;
	/** @var */
	protected $kinetic;

	public function onEnable() : void{
		$this->saveDefaultConfig();

		SpoonDetector::printSpoon($this);
		$this->provider = libasynql::create($this, $this->getConfig()->get("database"), [
			"sqlite" => "sqlite.sql",
			'mysql" => "mysql.sql",'
		]);
		$this->lang = Libglocal::init($this);
		$this->kinetic = new KineticManager($this, $this);

		$this->provider->executeGeneric(Queries::POSTBOX_INIT_POSTS);
		$this->provider->executeGeneric(Queries::POSTBOX_INIT_PLAYERS);
	}
}
