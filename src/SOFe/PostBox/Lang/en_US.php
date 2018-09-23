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

namespace SOFe\PostBox\Lang;

class en_US implements Translation{
	public function unreads_types_title(array $args) : string{
		return "Unread messages";
	}

	public function unreads_types_synopsis(array $args) : string{
		return "You have {$args["totalMessages"]} unread messages.";
	}

	public function unreads_types_each(array $args) : string{
		return "{$args["count"]} {$this->sender_type_object($args["type"], $args["count"])}";
	}

	public function unreads_names_title(array $args) : string{
		return "{$this->sender_type_prefix($args["sender"]["type"])} {$args["sender"]["name"]}";
	}

	public function unreads_names_synopsis(array $args) : string{
		return "You have {$args["totalMessages"]} {$this->sender_type_prefix($args["sender"]["type"])}";
	}

	public function unreads_names_each(array $args) : string{
		return "{$args["name"]} ({$args["count"]} new messages)";
	}


	protected function sender_type_prefix(string $type) : string{
		switch($type){
			case "postbox.player":
				return "Player";
		}
		return $type;
	}

	protected function sender_type_object(string $type, int $quantity) : string{
		switch($type){
			case "postbox.player":
				return $quantity > 1 ? "player PMs" : "player PM";
		}
		return "{{$type}}";
	}

	protected function sender_type_title(string $type) : string{
		switch($type){
			case "postbox.player":
				return "Player PMs";
		}
		return "{{$type}}";
	}
}
