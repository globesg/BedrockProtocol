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
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;

final class SmithingTransformRecipe extends RecipeWithTypeId{

	public function __construct(
		int $typeId,
		private string $recipeId,
		private RecipeIngredient $template,
		private RecipeIngredient $input,
		private RecipeIngredient $addition,
		private ItemStack $output,
		private string $blockName,
		private int $recipeNetId
	){
		parent::__construct($typeId);
	}

	public function getRecipeId() : string{ return $this->recipeId; }

	public function getTemplate() : RecipeIngredient{ return $this->template; }

	public function getInput() : RecipeIngredient{ return $this->input; }

	public function getAddition() : RecipeIngredient{ return $this->addition; }

	public function getOutput() : ItemStack{ return $this->output; }

	public function getBlockName() : string{ return $this->blockName; }

	public function getRecipeNetId() : int{ return $this->recipeNetId; }

	public static function decode(int $typeId, ByteBufferReader $in, int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : self{
		$recipeId = CommonTypes::getString($in);
		$template = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? RecipeIngredient::read12640($in) : CommonTypes::getRecipeIngredient($in);
		$input = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? RecipeIngredient::read12640($in) : CommonTypes::getRecipeIngredient($in);
		$addition = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? RecipeIngredient::read12640($in) : CommonTypes::getRecipeIngredient($in);
		$output = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? CommonTypes::getItemStackWithoutStackId12640($in) : CommonTypes::getItemStackWithoutStackId($in);
		$blockName = CommonTypes::getString($in);
		$recipeNetId = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? VarInt::readSignedInt($in) : CommonTypes::readRecipeNetId($in);

		return new self(
			$typeId,
			$recipeId,
			$template,
			$input,
			$addition,
			$output,
			$blockName,
			$recipeNetId
		);
	}

	public function encode(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putString($out, $this->recipeId);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$this->template->write12640($out); $this->input->write12640($out); $this->addition->write12640($out);
		}else{
			CommonTypes::putRecipeIngredient($out, $this->template); CommonTypes::putRecipeIngredient($out, $this->input); CommonTypes::putRecipeIngredient($out, $this->addition);
		}
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){ CommonTypes::putItemStackWithoutStackId12640($out, $this->output); }else{ CommonTypes::putItemStackWithoutStackId($out, $this->output); }
		CommonTypes::putString($out, $this->blockName);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){ VarInt::writeSignedInt($out, $this->recipeNetId); }else{ CommonTypes::writeRecipeNetId($out, $this->recipeNetId); }
	}
}
