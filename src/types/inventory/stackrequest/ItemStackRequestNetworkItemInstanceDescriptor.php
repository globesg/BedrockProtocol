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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraData;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;

final class ItemStackRequestNetworkItemInstanceDescriptor{
	/**
	 * @param string $rawExtraData Serialized ItemStackExtraData (use ItemStackExtraData->write())
	 * @see ItemStackExtraData::write()
	 */
	public function __construct(
		private RecipeIngredient $ingredient,
		private int $blockRuntimeId,
		private string $rawExtraData
	){}

	public function getIngredient() : RecipeIngredient{ return $this->ingredient; }

	public function getBlockRuntimeId() : int{ return $this->blockRuntimeId; }

	public function getRawExtraData() : string{ return $this->rawExtraData; }

	public static function read(ByteBufferReader $in, int $protocolId) : self{
		$ingredient = $protocolId >= \pocketmine\network\mcpe\protocol\ProtocolInfo::PROTOCOL_1_26_40 ? CommonTypes::getRecipeIngredient12640($in) : CommonTypes::getRecipeIngredient($in);
		$blockRuntimeId = VarInt::readUnsignedInt($in);
		$rawExtraData = CommonTypes::getString($in);
		return new self($ingredient, $blockRuntimeId, $rawExtraData);
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId >= \pocketmine\network\mcpe\protocol\ProtocolInfo::PROTOCOL_1_26_40){
			CommonTypes::putRecipeIngredient12640($out, $this->ingredient);
		}else{
			CommonTypes::putRecipeIngredient($out, $this->ingredient);
		}
		VarInt::writeUnsignedInt($out, $this->blockRuntimeId);
		CommonTypes::putString($out, $this->rawExtraData);
	}
}
