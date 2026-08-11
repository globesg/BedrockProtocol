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

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use function count;

class SetScorePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::SET_SCORE_PACKET;

	public const TYPE_CHANGE = 0;
	public const TYPE_REMOVE = 1;

	private const ACTION_IDS = [
		ScorePacketEntry::TYPE_REMOVE => "remove",
		ScorePacketEntry::TYPE_PLAYER => "changeplayer",
		ScorePacketEntry::TYPE_ENTITY => "changeentity",
		ScorePacketEntry::TYPE_FAKE_PLAYER => "changefakeplayer",
	];

	public int $type;
	/** @var ScorePacketEntry[] */
	public array $entries = [];

	/**
	 * @generate-create-func
	 * @param ScorePacketEntry[] $entries
	 */
	public static function create(int $type, array $entries) : self{
		$result = new self;
		$result->type = $type;
		$result->entries = $entries;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$allRemove = true;
			for($i = 0, $i2 = VarInt::readUnsignedInt($in); $i < $i2; ++$i){
				$entry = new ScorePacketEntry();
				$entry->type = VarInt::readUnsignedInt($in);
				CommonTypes::getString($in); //redundant action ID
				switch($entry->type){
					case ScorePacketEntry::TYPE_REMOVE:
						$entry->scoreboardId = VarInt::readSignedLong($in);
						$entry->objectiveName = CommonTypes::readOptional($in, CommonTypes::getString(...));
						break;
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						$allRemove = false;
						$entry->scoreboardId = VarInt::readSignedLong($in);
						$entry->objectiveName = CommonTypes::getString($in);
						$entry->score = LE::readSignedInt($in);
						$entry->actorUniqueId = CommonTypes::getActorUniqueId($in);
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						$allRemove = false;
						$entry->scoreboardId = VarInt::readSignedLong($in);
						$entry->objectiveName = CommonTypes::getString($in);
						$entry->score = LE::readSignedInt($in);
						$entry->customName = CommonTypes::getString($in);
						break;
					default:
						throw new PacketDecodeException("Unknown entry type $entry->type");
				}
				$this->entries[] = $entry;
			}
			$this->type = $allRemove ? self::TYPE_REMOVE : self::TYPE_CHANGE;
			return;
		}

		$this->type = Byte::readUnsigned($in);
		for($i = 0, $i2 = VarInt::readUnsignedInt($in); $i < $i2; ++$i){
			$entry = new ScorePacketEntry();
			$entry->scoreboardId = VarInt::readSignedLong($in);
			$entry->objectiveName = CommonTypes::getString($in);
			$entry->score = LE::readSignedInt($in);
			if($this->type !== self::TYPE_REMOVE){
				$entry->type = Byte::readUnsigned($in);
				switch($entry->type){
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						$entry->actorUniqueId = CommonTypes::getActorUniqueId($in);
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						$entry->customName = CommonTypes::getString($in);
						break;
					default:
						throw new PacketDecodeException("Unknown entry type $entry->type");
				}
			}else{
				$entry->type = ScorePacketEntry::TYPE_REMOVE;
			}
			$this->entries[] = $entry;
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			VarInt::writeUnsignedInt($out, count($this->entries));
			foreach($this->entries as $entry){
				$entryType = $this->type === self::TYPE_REMOVE ? ScorePacketEntry::TYPE_REMOVE : $entry->type;
				$actionId = self::ACTION_IDS[$entryType] ?? throw new \InvalidArgumentException("Unknown entry type $entryType");
				VarInt::writeUnsignedInt($out, $entryType);
				CommonTypes::putString($out, $actionId);
				switch($entryType){
					case ScorePacketEntry::TYPE_REMOVE:
						VarInt::writeSignedLong($out, $entry->scoreboardId);
						CommonTypes::writeOptional($out, $entry->objectiveName, CommonTypes::putString(...));
						break;
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						VarInt::writeSignedLong($out, $entry->scoreboardId);
						CommonTypes::putString($out, $entry->objectiveName ?? throw new \InvalidArgumentException("objectiveName must be set"));
						LE::writeSignedInt($out, $entry->score);
						CommonTypes::putActorUniqueId($out, $entry->actorUniqueId ?? throw new \InvalidArgumentException("actorUniqueId must be set"));
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						VarInt::writeSignedLong($out, $entry->scoreboardId);
						CommonTypes::putString($out, $entry->objectiveName ?? throw new \InvalidArgumentException("objectiveName must be set"));
						LE::writeSignedInt($out, $entry->score);
						CommonTypes::putString($out, $entry->customName ?? throw new \InvalidArgumentException("customName must be set"));
						break;
				}
			}
			return;
		}

		Byte::writeUnsigned($out, $this->type);
		VarInt::writeUnsignedInt($out, count($this->entries));
		foreach($this->entries as $entry){
			VarInt::writeSignedLong($out, $entry->scoreboardId);
			CommonTypes::putString($out, $entry->objectiveName ?? "");
			LE::writeSignedInt($out, $entry->score);
			if($this->type !== self::TYPE_REMOVE){
				Byte::writeUnsigned($out, $entry->type);
				switch($entry->type){
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						CommonTypes::putActorUniqueId($out, $entry->actorUniqueId ?? throw new \InvalidArgumentException("actorUniqueId must be set"));
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						CommonTypes::putString($out, $entry->customName ?? throw new \InvalidArgumentException("customName must be set"));
						break;
					default:
						throw new \InvalidArgumentException("Unknown entry type $entry->type");
				}
			}
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleSetScore($this);
	}
}
