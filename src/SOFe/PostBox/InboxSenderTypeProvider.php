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

use Generator;
use SOFe\Libkinetic\API\IconListFactory;
use SOFe\Libkinetic\API\IconListProvider;
use SOFe\Libkinetic\Flow\FlowContext;
use SOFe\Libkinetic\UserString;

class InboxSenderTypeProvider extends BaseController implements IconListProvider{
	public function provideIconList(FlowContext $context, IconListFactory $factory) : Generator{
		$result = yield $this->plugin->yieldSelect(Queries::POSTBOX_PLAYER_GROUP_BY_SENDER_TYPE, [
			"name" => $context->getUser()->getName(),
		]);

		$messages = 0;
		foreach($result as $row){
			$type = $row["sender_type"];
			$count = $row["count"];
			$factory->add($type, new UserString("unreads_types_each", [
				"type" => $type,
				"count" => $count,
			]), $type);
			$messages += $count;
		}

		$context->getVariables()->setNested("totalMessages", $messages);
	}
}
