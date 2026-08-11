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
use pocketmine\color\Color;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\MapDecoration;
use pocketmine\network\mcpe\protocol\types\MapImage;
use pocketmine\network\mcpe\protocol\types\MapTrackedObject;
use pocketmine\utils\Binary;
use function array_slice;
use function count;

class ClientboundMapItemDataPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_MAP_ITEM_DATA_PACKET;

	public const BITFLAG_TEXTURE_UPDATE = 0x02;
	public const BITFLAG_DECORATION_UPDATE = 0x04;
	public const BITFLAG_MAP_CREATION = 0x08;

	public int $mapId;
	public int $type = 0;
	public int $dimensionId = DimensionIds::OVERWORLD;
	public bool $isLocked = false;
	public BlockPosition $origin;

	/** @var int[] */
	public array $parentMapIds = [];
	public int $scale = 0;

	/** @var MapTrackedObject[] */
	public array $trackedEntities = [];
	/** @var MapDecoration[] */
	public array $decorations = [];

	public int $xOffset = 0;
	public int $yOffset = 0;
	public ?MapImage $colors = null;

	/** @var int[]|null @phpstan-var list<int>|null */
	public ?array $creationMapIds = null;
	public ?int $currentScale = null;
	/** @var MapTrackedObject[]|null @phpstan-var list<MapTrackedObject>|null */
	public ?array $currentTrackedEntities = null;
	/** @var MapDecoration[]|null @phpstan-var list<MapDecoration>|null */
	public ?array $currentDecorations = null;
	public ?int $width = null;
	public ?int $height = null;
	public ?int $startX = null;
	public ?int $startY = null;
	/** @var Color[]|null @phpstan-var list<Color>|null */
	public ?array $pixels = null;

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->mapId = CommonTypes::getActorUniqueId($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$this->dimensionId = Byte::readUnsigned($in);
			$this->isLocked = CommonTypes::getBool($in);
			$this->origin = CommonTypes::getBlockPosition($in);
			$this->creationMapIds = CommonTypes::readOptional($in, function(ByteBufferReader $in) : array{
				$result = [];
				for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){ $result[] = CommonTypes::getActorUniqueId($in); }
				return $result;
			});
			$this->parentMapIds = $this->creationMapIds ?? [];
			$this->currentScale = CommonTypes::readOptional($in, Byte::readUnsigned(...));
			if($this->currentScale !== null){ $this->scale = $this->currentScale; }
			$this->currentTrackedEntities = CommonTypes::readOptional($in, function(ByteBufferReader $in) : array{
				$result = [];
				for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){ $result[] = MapTrackedObject::read12640($in); }
				return $result;
			});
			$this->trackedEntities = $this->currentTrackedEntities ?? [];
			$this->currentDecorations = CommonTypes::readOptional($in, function(ByteBufferReader $in) : array{
				$result = [];
				for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
					$result[] = new MapDecoration(Byte::readUnsigned($in), Byte::readUnsigned($in), Byte::readUnsigned($in), Byte::readUnsigned($in), CommonTypes::getString($in), Color::fromRGBA(Binary::flipIntEndianness(LE::readUnsignedInt($in))));
				}
				return $result;
			});
			$this->decorations = $this->currentDecorations ?? [];
			$this->width = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
			$this->height = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
			$this->startX = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
			$this->startY = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
			$this->xOffset = $this->startX ?? 0;
			$this->yOffset = $this->startY ?? 0;
			$this->pixels = CommonTypes::readOptional($in, function(ByteBufferReader $in) : array{
				$result = [];
				for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){ $result[] = Color::fromRGBA(Binary::flipIntEndianness(LE::readUnsignedInt($in))); }
				return $result;
			});
			if($this->pixels !== null && $this->width !== null && $this->height !== null && count($this->pixels) === $this->width * $this->height){
				$rows = [];
				for($y = 0; $y < $this->height; ++$y){ $rows[] = array_slice($this->pixels, $y * $this->width, $this->width); }
				$this->colors = new MapImage($rows);
			}
			return;
		}
		$this->type = VarInt::readUnsignedInt($in);
		$this->dimensionId = Byte::readUnsigned($in);
		$this->isLocked = CommonTypes::getBool($in);
		$this->origin = CommonTypes::getBlockPosition($in);

		if(($this->type & self::BITFLAG_MAP_CREATION) !== 0){
			$count = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $count; ++$i){
				$this->parentMapIds[] = CommonTypes::getActorUniqueId($in);
			}
		}

		if(($this->type & (self::BITFLAG_MAP_CREATION | self::BITFLAG_DECORATION_UPDATE | self::BITFLAG_TEXTURE_UPDATE)) !== 0){ //Decoration bitflag or colour bitflag
			$this->scale = Byte::readUnsigned($in);
		}

		if(($this->type & self::BITFLAG_DECORATION_UPDATE) !== 0){
			for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
				$object = new MapTrackedObject();
				$object->type = LE::readUnsignedInt($in);
				if($object->type === MapTrackedObject::TYPE_BLOCK){
					$object->blockPosition = CommonTypes::getBlockPosition($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
				}elseif($object->type === MapTrackedObject::TYPE_ENTITY){
					$object->actorUniqueId = CommonTypes::getActorUniqueId($in);
				}else{
					throw new PacketDecodeException("Unknown map object type $object->type");
				}
				$this->trackedEntities[] = $object;
			}

			for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
				$icon = Byte::readUnsigned($in);
				$rotation = Byte::readUnsigned($in);
				$xOffset = Byte::readUnsigned($in);
				$yOffset = Byte::readUnsigned($in);
				$label = CommonTypes::getString($in);
				$color = Color::fromRGBA(Binary::flipIntEndianness(VarInt::readUnsignedInt($in)));
				$this->decorations[] = new MapDecoration($icon, $rotation, $xOffset, $yOffset, $label, $color);
			}
		}

		if(($this->type & self::BITFLAG_TEXTURE_UPDATE) !== 0){
			$width = VarInt::readSignedInt($in);
			$height = VarInt::readSignedInt($in);
			$this->xOffset = VarInt::readSignedInt($in);
			$this->yOffset = VarInt::readSignedInt($in);

			$count = VarInt::readUnsignedInt($in);
			if($count !== $width * $height){
				throw new PacketDecodeException("Expected colour count of " . ($height * $width) . " (height $height * width $width), got $count");
			}

			$this->colors = MapImage::decode($in, $height, $width);
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putActorUniqueId($out, $this->mapId);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			Byte::writeUnsigned($out, $this->dimensionId);
			CommonTypes::putBool($out, $this->isLocked);
			CommonTypes::putBlockPosition($out, $this->origin);
			$creationMapIds = $this->creationMapIds ?? ($this->parentMapIds !== [] ? $this->parentMapIds : null);
			CommonTypes::writeOptional($out, $creationMapIds, function(ByteBufferWriter $out, array $ids) : void{
				VarInt::writeUnsignedInt($out, count($ids)); foreach($ids as $id){ CommonTypes::putActorUniqueId($out, $id); }
			});
			$hasAnyData = $creationMapIds !== null || $this->decorations !== [] || $this->trackedEntities !== [] || $this->colors !== null;
			CommonTypes::writeOptional($out, $this->currentScale ?? ($hasAnyData ? $this->scale : null), Byte::writeUnsigned(...));
			$tracked = $this->currentTrackedEntities ?? ($this->trackedEntities !== [] ? $this->trackedEntities : null);
			CommonTypes::writeOptional($out, $tracked, function(ByteBufferWriter $out, array $values) : void{
				VarInt::writeUnsignedInt($out, count($values)); foreach($values as $value){ $value->write12640($out); }
			});
			$decorations = $this->currentDecorations ?? ($this->decorations !== [] ? $this->decorations : null);
			CommonTypes::writeOptional($out, $decorations, function(ByteBufferWriter $out, array $values) : void{
				VarInt::writeUnsignedInt($out, count($values));
				foreach($values as $decoration){
					Byte::writeUnsigned($out, $decoration->getIcon()); Byte::writeUnsigned($out, $decoration->getRotation()); Byte::writeUnsigned($out, $decoration->getXOffset()); Byte::writeUnsigned($out, $decoration->getYOffset()); CommonTypes::putString($out, $decoration->getLabel()); LE::writeUnsignedInt($out, Binary::flipIntEndianness($decoration->getColor()->toRGBA()));
				}
			});
			$pixels = $this->pixels;
			$width = $this->width;
			$height = $this->height;
			if($pixels === null && $this->colors !== null){
				$width = $this->colors->getWidth(); $height = $this->colors->getHeight(); $pixels = [];
				foreach($this->colors->getPixels() as $row){ foreach($row as $pixel){ $pixels[] = $pixel; } }
			}
			CommonTypes::writeOptional($out, $width, VarInt::writeSignedInt(...));
			CommonTypes::writeOptional($out, $height, VarInt::writeSignedInt(...));
			CommonTypes::writeOptional($out, $this->startX ?? ($pixels !== null ? $this->xOffset : null), VarInt::writeSignedInt(...));
			CommonTypes::writeOptional($out, $this->startY ?? ($pixels !== null ? $this->yOffset : null), VarInt::writeSignedInt(...));
			CommonTypes::writeOptional($out, $pixels, function(ByteBufferWriter $out, array $values) : void{
				VarInt::writeUnsignedInt($out, count($values)); foreach($values as $pixel){ LE::writeUnsignedInt($out, Binary::flipIntEndianness($pixel->toRGBA())); }
			});
			return;
		}

		$type = 0;
		if(($parentMapIdsCount = count($this->parentMapIds)) > 0){
			$type |= self::BITFLAG_MAP_CREATION;
		}
		if(($decorationCount = count($this->decorations)) > 0){
			$type |= self::BITFLAG_DECORATION_UPDATE;
		}
		if($this->colors !== null){
			$type |= self::BITFLAG_TEXTURE_UPDATE;
		}

		VarInt::writeUnsignedInt($out, $type);
		Byte::writeUnsigned($out, $this->dimensionId);
		CommonTypes::putBool($out, $this->isLocked);
		CommonTypes::putBlockPosition($out, $this->origin);

		if(($type & self::BITFLAG_MAP_CREATION) !== 0){
			VarInt::writeUnsignedInt($out, $parentMapIdsCount);
			foreach($this->parentMapIds as $parentMapId){
				CommonTypes::putActorUniqueId($out, $parentMapId);
			}
		}

		if(($type & (self::BITFLAG_MAP_CREATION | self::BITFLAG_TEXTURE_UPDATE | self::BITFLAG_DECORATION_UPDATE)) !== 0){
			Byte::writeUnsigned($out, $this->scale);
		}

		if(($type & self::BITFLAG_DECORATION_UPDATE) !== 0){
			VarInt::writeUnsignedInt($out, count($this->trackedEntities));
			foreach($this->trackedEntities as $object){
				LE::writeUnsignedInt($out, $object->type);
				if($object->type === MapTrackedObject::TYPE_BLOCK){
					CommonTypes::putBlockPosition($out, $object->blockPosition, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
				}elseif($object->type === MapTrackedObject::TYPE_ENTITY){
					CommonTypes::putActorUniqueId($out, $object->actorUniqueId);
				}else{
					throw new \InvalidArgumentException("Unknown map object type $object->type");
				}
			}

			VarInt::writeUnsignedInt($out, $decorationCount);
			foreach($this->decorations as $decoration){
				Byte::writeUnsigned($out, $decoration->getIcon());
				Byte::writeUnsigned($out, $decoration->getRotation());
				Byte::writeUnsigned($out, $decoration->getXOffset());
				Byte::writeUnsigned($out, $decoration->getYOffset());
				CommonTypes::putString($out, $decoration->getLabel());
				VarInt::writeUnsignedInt($out, Binary::flipIntEndianness($decoration->getColor()->toRGBA()));
			}
		}

		if($this->colors !== null){
			VarInt::writeSignedInt($out, $this->colors->getWidth());
			VarInt::writeSignedInt($out, $this->colors->getHeight());
			VarInt::writeSignedInt($out, $this->xOffset);
			VarInt::writeSignedInt($out, $this->yOffset);

			VarInt::writeUnsignedInt($out, $this->colors->getWidth() * $this->colors->getHeight()); //list count, but we handle it as a 2D array... thanks for the confusion mojang

			$this->colors->encode($out);
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleClientboundMapItemData($this);
	}
}
