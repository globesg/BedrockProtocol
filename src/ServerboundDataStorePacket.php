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

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate as LegacyDataStoreUpdate;
use pocketmine\network\mcpe\protocol\types\DataStoreUpdate as CurrentDataStoreUpdate;

class ServerboundDataStorePacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_DATA_STORE_PACKET;

	private LegacyDataStoreUpdate|CurrentDataStoreUpdate $update;

	/**
	 * @generate-create-func
	 */
	public static function create(LegacyDataStoreUpdate|CurrentDataStoreUpdate $update) : self{
		$result = new self;
		$result->update = $update;
		return $result;
	}

	public function getUpdate() : LegacyDataStoreUpdate|CurrentDataStoreUpdate{ return $this->update; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->update = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? CurrentDataStoreUpdate::read($in) : LegacyDataStoreUpdate::read($in, $protocolId);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			if(!$this->update instanceof CurrentDataStoreUpdate){ throw new \InvalidArgumentException("Current DataStoreUpdate required"); }
			$this->update->write($out);
		}else{
			if(!$this->update instanceof LegacyDataStoreUpdate){ throw new \InvalidArgumentException("Legacy DataStoreUpdate required"); }
			$this->update->write($out, $protocolId);
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleServerboundDataStore($this);
	}
}
