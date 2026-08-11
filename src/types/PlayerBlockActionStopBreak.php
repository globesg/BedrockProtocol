<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class PlayerBlockActionStopBreak implements PlayerBlockAction{

	public function getActionType() : int{
		return PlayerAction::STOP_BREAK;
	}

	/**
	 * Legacy PlayerAuthInput encoding (through protocol 2168 / 1.26.40): StopDestroyBlock carries
	 * no action-specific payload.
	 */
	public function write(ByteBufferWriter $out) : void{
		//NOOP
	}

	/**
	 * Cereal PlayerBlockActionData used by protocol 2169+ serializes the schema's
	 * default BlockPos and Facing even for StopDestroyBlock. The values aren't
	 * useful to PMMP for STOP_BREAK, but they MUST be consumed to keep the
	 * PlayerAuthInput stream aligned.
	 */
	public static function read12640(ByteBufferReader $in) : self{
		CommonTypes::getBlockPosition($in);
		VarInt::readSignedInt($in);
		return new self();
	}

	public function write12640(ByteBufferWriter $out) : void{
		CommonTypes::putBlockPosition($out, new BlockPosition(0, 0, 0));
		VarInt::writeSignedInt($out, 0);
	}
}
