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
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreChange as LegacyDataStoreChange;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreOperation;
use pocketmine\network\mcpe\protocol\types\DataStoreChange as CurrentDataStoreChange;
use pocketmine\network\mcpe\protocol\types\DataStoreRemoval as CurrentDataStoreRemoval;
use pocketmine\network\mcpe\protocol\types\DataStoreType;
use pocketmine\network\mcpe\protocol\types\DataStoreUpdate as CurrentDataStoreUpdate;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreRemoval as LegacyDataStoreRemoval;
use pocketmine\network\mcpe\protocol\types\ddui\DataStoreUpdate as LegacyDataStoreUpdate;
use function count;

class ClientboundDataStorePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_DATA_STORE_PACKET;

	/**
	 * @var DataStoreOperation[]
	 * @phpstan-var list<DataStoreOperation>
	 */
	public array $values = [];

	/**
	 * @generate-create-func
	 * @param DataStoreOperation[] $values
	 * @phpstan-param list<DataStoreOperation> $values
	 */
	public static function create(array $values) : self{
		$result = new self;
		$result->values = $values;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->values = [];
		for($i = 0, $len = VarInt::readUnsignedInt($in); $i < $len; ++$i){
			$this->values[] = match(VarInt::readUnsignedInt($in)){
				($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? DataStoreType::UPDATE : \pocketmine\network\mcpe\protocol\types\ddui\DataStoreOperationType::UPDATE) => $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? CurrentDataStoreUpdate::read($in) : LegacyDataStoreUpdate::read($in, $protocolId),
				($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? DataStoreType::CHANGE : \pocketmine\network\mcpe\protocol\types\ddui\DataStoreOperationType::CHANGE) => $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? CurrentDataStoreChange::read($in) : LegacyDataStoreChange::read($in, $protocolId),
				($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? DataStoreType::REMOVAL : \pocketmine\network\mcpe\protocol\types\ddui\DataStoreOperationType::REMOVAL) => $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? CurrentDataStoreRemoval::read($in) : LegacyDataStoreRemoval::read($in, $protocolId),
				default => throw new PacketDecodeException("Unknown DataStore type"),
			};
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		VarInt::writeUnsignedInt($out, count($this->values));
		foreach($this->values as $value){
			VarInt::writeUnsignedInt($out, $value->getTypeId());
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				if(!$value instanceof \pocketmine\network\mcpe\protocol\types\DataStore){
					throw new \InvalidArgumentException("1.26.40 requires current DataStore values");
				}
				$value->write($out);
			}else{
				if(!$value instanceof \pocketmine\network\mcpe\protocol\types\ddui\DataStoreOperation){
					throw new \InvalidArgumentException("Legacy protocols require legacy DataStoreOperation values");
				}
				$value->write($out, $protocolId);
			}
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleClientboundDataStore($this);
	}
}
