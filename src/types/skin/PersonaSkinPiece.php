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

namespace pocketmine\network\mcpe\protocol\types\skin;

use Ramsey\Uuid\UuidInterface;

final class PersonaSkinPiece{

	// Bedrock 1.26.40 numeric persona piece IDs. Legacy string constants below remain for older protocols.
	public const PIECE_TYPE_SKELETON = 0;
	public const PIECE_TYPE_BODY = 1;
	public const PIECE_TYPE_SKIN = 2;
	public const PIECE_TYPE_BOTTOM = 3;
	public const PIECE_TYPE_FEET = 4;
	public const PIECE_TYPE_DRESS = 5;
	public const PIECE_TYPE_TOP = 6;
	public const PIECE_TYPE_HIGH_PANTS = 7;
	public const PIECE_TYPE_HANDS = 8;
	public const PIECE_TYPE_OUTERWEAR = 9;
	public const PIECE_TYPE_FACIAL_HAIR = 10;
	public const PIECE_TYPE_MOUTH = 11;
	public const PIECE_TYPE_EYES = 12;
	public const PIECE_TYPE_HAIR = 13;
	public const PIECE_TYPE_HOOD = 14;
	public const PIECE_TYPE_BACK = 15;
	public const PIECE_TYPE_FACE_ACCESSORY = 16;
	public const PIECE_TYPE_HEAD = 17;
	public const PIECE_TYPE_LEGS = 18;
	public const PIECE_TYPE_LEFT_LEG = 19;
	public const PIECE_TYPE_RIGHT_LEG = 20;
	public const PIECE_TYPE_ARMS = 21;
	public const PIECE_TYPE_LEFT_ARM = 22;
	public const PIECE_TYPE_RIGHT_ARM = 23;
	public const PIECE_TYPE_CAPES = 24;
	public const PIECE_TYPE_CLASSIC_SKIN = 25;
	public const PIECE_TYPE_EMOTE = 26;

	public const PIECE_TYPE_PERSONA_BODY = "persona_body";
	public const PIECE_TYPE_PERSONA_BOTTOM = "persona_bottom";
	public const PIECE_TYPE_PERSONA_EYES = "persona_eyes";
	public const PIECE_TYPE_PERSONA_FACIAL_HAIR = "persona_facial_hair";
	public const PIECE_TYPE_PERSONA_FEET = "persona_feet";
	public const PIECE_TYPE_PERSONA_HAIR = "persona_hair";
	public const PIECE_TYPE_PERSONA_MOUTH = "persona_mouth";
	public const PIECE_TYPE_PERSONA_SKELETON = "persona_skeleton";
	public const PIECE_TYPE_PERSONA_SKIN = "persona_skin";
	public const PIECE_TYPE_PERSONA_TOP = "persona_top";

	public function __construct(
		private string $pieceId,
		private int|string $pieceType,
		private UuidInterface|string $packId,
		private bool $isDefaultPiece,
		private string $productId
	){}

	public function getPieceId() : string{
		return $this->pieceId;
	}

	public function getPieceType() : int|string{
		return $this->pieceType;
	}

	public function getPackId() : UuidInterface|string{
		return $this->packId;
	}

	public function isDefaultPiece() : bool{
		return $this->isDefaultPiece;
	}

	public function getProductId() : string{
		return $this->productId;
	}
}
