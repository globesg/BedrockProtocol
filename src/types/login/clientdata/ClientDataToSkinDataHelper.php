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

namespace pocketmine\network\mcpe\protocol\types\login\clientdata;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use Ramsey\Uuid\Uuid;
use function array_map;
use function ltrim;
use function hexdec;
use function count;
use function array_values;
use function array_slice;
use function base64_decode;
use function is_array;
use function is_string;
use function json_decode;
use function strtolower;

final class ClientDataToSkinDataHelper{

	private const PIECE_TYPE_MAP = [
		"persona_skeleton" => PersonaSkinPiece::PIECE_TYPE_SKELETON,
		"persona_body" => PersonaSkinPiece::PIECE_TYPE_BODY,
		"persona_skin" => PersonaSkinPiece::PIECE_TYPE_SKIN,
		"persona_bottom" => PersonaSkinPiece::PIECE_TYPE_BOTTOM,
		"persona_feet" => PersonaSkinPiece::PIECE_TYPE_FEET,
		"persona_dress" => PersonaSkinPiece::PIECE_TYPE_DRESS,
		"persona_top" => PersonaSkinPiece::PIECE_TYPE_TOP,
		"persona_high_pants" => PersonaSkinPiece::PIECE_TYPE_HIGH_PANTS,
		"persona_hand" => PersonaSkinPiece::PIECE_TYPE_HANDS,
		"persona_outerwear" => PersonaSkinPiece::PIECE_TYPE_OUTERWEAR,
		"persona_facial_hair" => PersonaSkinPiece::PIECE_TYPE_FACIAL_HAIR,
		"persona_mouth" => PersonaSkinPiece::PIECE_TYPE_MOUTH,
		"persona_eyes" => PersonaSkinPiece::PIECE_TYPE_EYES,
		"persona_hair" => PersonaSkinPiece::PIECE_TYPE_HAIR,
		"persona_hood" => PersonaSkinPiece::PIECE_TYPE_HOOD,
		"persona_back" => PersonaSkinPiece::PIECE_TYPE_BACK,
		"persona_face_accessory" => PersonaSkinPiece::PIECE_TYPE_FACE_ACCESSORY,
		"persona_head" => PersonaSkinPiece::PIECE_TYPE_HEAD,
		"persona_legs" => PersonaSkinPiece::PIECE_TYPE_LEGS,
		"persona_left_leg" => PersonaSkinPiece::PIECE_TYPE_LEFT_LEG,
		"persona_right_leg" => PersonaSkinPiece::PIECE_TYPE_RIGHT_LEG,
		"persona_arms" => PersonaSkinPiece::PIECE_TYPE_ARMS,
		"persona_left_arm" => PersonaSkinPiece::PIECE_TYPE_LEFT_ARM,
		"persona_right_arm" => PersonaSkinPiece::PIECE_TYPE_RIGHT_ARM,
		"persona_capes" => PersonaSkinPiece::PIECE_TYPE_CAPES,
		"persona_classic_skin" => PersonaSkinPiece::PIECE_TYPE_CLASSIC_SKIN,
		"persona_emote" => PersonaSkinPiece::PIECE_TYPE_EMOTE,
	];

	private static function convertArmSize12640(string $armSize) : int{
		return match($armSize){
			"slim" => 0,
			"wide", "" => 1,
			default => throw new \InvalidArgumentException("Unknown arm size \"$armSize\"")
		};
	}

	private static function convertColor12640(string $color) : int{
		return (int) hexdec(ltrim($color, "#"));
	}

	private static function convertPieceType12640(string $pieceType) : int{
		return self::PIECE_TYPE_MAP[$pieceType] ?? throw new \InvalidArgumentException("Unknown persona piece type \"$pieceType\"");
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private static function safeB64Decode(string $base64, string $context) : string{
		$result = base64_decode($base64, true);
		if($result === false){
			throw new \InvalidArgumentException("$context: Malformed base64, cannot be decoded");
		}
		return $result;
	}

	private static function usesBuiltInHumanoidGeometry(string $resourcePatch) : bool{
		$decoded = json_decode($resourcePatch, true);
		if(!is_array($decoded) || !isset($decoded["geometry"]["default"]) || !is_string($decoded["geometry"]["default"])){
			return false;
		}
		$name = strtolower($decoded["geometry"]["default"]);
		return $name === "geometry.humanoid.custom" || $name === "geometry.humanoid.customslim";
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public static function fromClientData(ClientData $clientData, int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : SkinData{
		$current = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40;
		$resourcePatch = self::safeB64Decode($clientData->SkinResourcePatch, "SkinResourcePatch");
		$builtInHumanoidGeometry = self::usesBuiltInHumanoidGeometry($resourcePatch);

		/** @var SkinAnimation[] $animations */
		$animations = [];
		foreach($clientData->AnimatedImageData as $k => $animation){
			$animations[] = new SkinAnimation(
				new SkinImage(
					$animation->ImageHeight,
					$animation->ImageWidth,
					self::safeB64Decode($animation->Image, "AnimatedImageData.$k.Image")
				),
				$animation->Type,
				$animation->Frames,
				$animation->AnimationExpression
			);
		}
		return new SkinData(
			$clientData->SkinId,
			$current ? "" : ($clientData->PlayFabId ?? ""),
			$resourcePatch,
			new SkinImage($clientData->SkinImageHeight, $clientData->SkinImageWidth, self::safeB64Decode($clientData->SkinData, "SkinData")),
			$animations,
			new SkinImage($clientData->CapeImageHeight, $clientData->CapeImageWidth, self::safeB64Decode($clientData->CapeData, "CapeData")),
			$builtInHumanoidGeometry ? "" : self::safeB64Decode($clientData->SkinGeometryData, "SkinGeometryData"),
			$builtInHumanoidGeometry ? "" : self::safeB64Decode($clientData->SkinGeometryDataEngineVersion, "SkinGeometryDataEngineVersion"), //yes, they actually base64'd the version!
			self::safeB64Decode($clientData->SkinAnimationData, "SkinAnimationData"),
			$clientData->CapeId,
			null,
			$current ? self::convertArmSize12640($clientData->ArmSize) : $clientData->ArmSize,
			$current ? self::convertColor12640($clientData->SkinColor) : $clientData->SkinColor,
			array_map(function(ClientDataPersonaSkinPiece $piece) use ($current) : PersonaSkinPiece{
				return new PersonaSkinPiece(
					$piece->PieceId,
					$current ? self::convertPieceType12640($piece->PieceType) : $piece->PieceType,
					$current ? Uuid::fromString($piece->PackId) : $piece->PackId,
					$piece->IsDefault,
					$piece->ProductId
				);
			}, $clientData->PersonaPieces),
			array_map(function(ClientDataPersonaPieceTintColor $tint) use ($current) : PersonaPieceTintColor{
				if(!$current){
					return new PersonaPieceTintColor($tint->PieceType, $tint->Colors);
				}
				$colors = [];
				foreach(array_slice(array_values($tint->Colors), 0, PersonaPieceTintColor::COLOR_COUNT) as $color){
					$colors[] = self::convertColor12640($color);
				}
				while(count($colors) < PersonaPieceTintColor::COLOR_COUNT){ $colors[] = 0; }
				return new PersonaPieceTintColor($tint->PieceType, $colors);
			}, $clientData->PieceTintColors),
			true,
			$clientData->PremiumSkin,
			$clientData->PersonaSkin,
			$clientData->CapeOnClassicSkin,
			true, //assume this is true? there's no field for it ...
			$clientData->OverrideSkin ?? true,
			$current ? SkinData::TRUSTED_SKIN_FLAG_UNSET : ($clientData->TrustedSkin ? SkinData::TRUSTED_SKIN_FLAG_TRUE : SkinData::TRUSTED_SKIN_FLAG_FALSE),
			$current ? "" : ($clientData->ProfileHash ?? ""),
		);
	}
}
