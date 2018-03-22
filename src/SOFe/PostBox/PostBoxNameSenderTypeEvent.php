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

use pocketmine\event\plugin\PluginEvent;

class PostBoxNameSenderTypeEvent extends PluginEvent{
	public const TYPE_MODE_FROM = "postbox.from";
	public const TYPE_MODE_CATEGORY = "postbox.category";
	public static $handlerList = null;

	/** @var string */
	private $type;

	/** @var string|null */
	private $name;
	/** @var string */
	private $mode;

	public function __construct(PostBox $plugin, string $type, string $mode){
		parent::__construct($plugin);
		$this->type = $type;
		$this->mode = $mode;
	}

	public function getType() : string{
		return $this->type;
	}

	public function getMode() : string{
		return $this->mode;
	}

	public function getName() : ?string{
		return $this->name;
	}

	public function setName(string $name) : void{
		$this->name = $name;
	}
}
