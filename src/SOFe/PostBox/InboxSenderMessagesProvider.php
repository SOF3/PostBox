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

use function date;
use Generator;
use SOFe\Libkinetic\API\DataListProvider;
use SOFe\Libkinetic\Flow\FlowContext;

class InboxSenderMessagesProvider extends BaseController implements DataListProvider{
	public function provide(FlowContext $context) : Generator{
		$type = $context->getVariables()->getNested("sender.type");
		$name = $context->getVariables()->getNested("sender.name");

		$messages = yield $this->plugin->yieldSelect(Queries::POSTBOX_PLAYER_LIST_SENDER_TYPE_NAME, [
			"senderType" => $type,
			"senderName" => $name,
			"name" => $context->getUser()->getName(),
		]);

		$output = [];
		foreach($messages as $message){
			$output[] = [
				"source" => $message["sender_name"],
				"message" => $message["message"],
				"time" => $message["send_time"],
			];
		}
		return $output;
	}
}
