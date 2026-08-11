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

namespace pocketmine\network\mcpe\protocol\types\recipe;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function get_class;

final class RecipeIngredient{
	private const TYPE_NAME = "name";
	private const TYPE_MOLANG = "molang";
	private const TYPE_ITEM_TAG = "item_tag";
	private const EMPTY_AUX_VALUE = 0x7fff;
	public function __construct(
		private ?ItemDescriptor $descriptor,
		private int $count
	){}

	public function getDescriptor() : ?ItemDescriptor{
		return $this->descriptor;
	}

	public function getCount() : int{
		return $this->count;
	}
	/** @throws PacketDecodeException */
	public static function read12640(ByteBufferReader $in) : self{
		$valid = VarInt::readUnsignedInt($in);
		if($valid === 0){
			VarInt::readSignedInt($in);
			return new self(null, VarInt::readSignedInt($in));
		}
		$type = CommonTypes::getString($in);
		$descriptor = match($type){
			self::TYPE_NAME => NameItemDescriptor::read($in),
			self::TYPE_MOLANG => MolangItemDescriptor::read12640($in),
			self::TYPE_ITEM_TAG => TagItemDescriptor::read($in),
			default => throw new PacketDecodeException("Unknown item descriptor type \"$type\"")
		};
		if($descriptor instanceof TagItemDescriptor){ VarInt::readSignedInt($in); }
		return new self($descriptor, VarInt::readSignedInt($in));
	}

	public function write12640(ByteBufferWriter $out) : void{
		$descriptor = $this->descriptor;
		if($descriptor === null){
			VarInt::writeUnsignedInt($out, 0);
			VarInt::writeSignedInt($out, self::EMPTY_AUX_VALUE);
			VarInt::writeSignedInt($out, $this->count);
			return;
		}
		VarInt::writeUnsignedInt($out, 1);
		CommonTypes::putString($out, match(true){
			$descriptor instanceof NameItemDescriptor => self::TYPE_NAME,
			$descriptor instanceof MolangItemDescriptor => self::TYPE_MOLANG,
			$descriptor instanceof TagItemDescriptor => self::TYPE_ITEM_TAG,
			default => throw new \LogicException("Unknown item descriptor type " . get_class($descriptor))
		});
		if($descriptor instanceof MolangItemDescriptor){ $descriptor->write12640($out); }else{ $descriptor->write($out); }
		if($descriptor instanceof TagItemDescriptor){ VarInt::writeSignedInt($out, self::EMPTY_AUX_VALUE); }
		VarInt::writeSignedInt($out, $this->count);
	}
}
